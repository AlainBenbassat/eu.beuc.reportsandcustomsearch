<?php
use CRM_Reportsandcustomsearch_ExtensionUtil as E;

class CRM_Reportsandcustomsearch_Form_Report_WeightedVotes extends CRM_Report_Form {

  public function __construct() {
    $this->_columns = [
      'civicrm_dummy_entity' => [
        'fields' => $this->getReportColumns(),
        'filters' => $this->getReportFilters(),
      ]
    ];

    parent::__construct();
  }

  private function getReportColumns() {
    $cols = [];

    $colTitles = [
      'display_name' => 'Member',
      'country' => 'Country',
      'membership_type' => 'Membership Type',
      'membership_fee' => 'Fee',
      'membership_fee_percentage' => '% of Total',
      'membership_fee_status' => 'Payment Status',
      'theoretical_number_of_votes' => 'Theoretical Number of Votes',
      'voting_rights' => 'Voting rights?',
      'number_of_votes' => 'Actual Number of Votes',
      'reimbursement' => 'Reimbursement?',
    ];

    foreach ($colTitles as $k => $colTitle) {
      $cols[$k] = [
        'title' => $colTitle,
        'default' => TRUE,
        'required' => FALSE,
        'dbAlias' => '1',
      ];
    }

    return $cols;
  }

  private function getReportFilters() {
    $currentYear = intval(date('Y'));
    $toYear = $currentYear - 7;

    $years = [];
    for ($i = $currentYear; $i >= $toYear; $i--) {
      $years[$i] = $i;
    }

    $filters = [
      'reference_year' => [
        'title' => 'Reference Year',
        'dbAlias' => '1',
        'type' => CRM_Utils_Type::T_INT,
        'operatorType' => CRM_Report_Form::OP_SELECT,
        'options' => $years,
        'default' => $currentYear,
      ],
    ];

    return $filters;
  }

  function preProcess() {
    $this->assign('reportTitle', 'BEUC members');
    parent::preProcess();
  }

  function from() {
    // take small table
    $this->_from = "FROM civicrm_domain {$this->_aliases['civicrm_dummy_entity']} ";
  }

  function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    return parent::selectClause($tableName, $tableKey, $fieldName, $field);
  }

  public function whereClause(&$field, $op, $value, $min, $max) {
    return '';
  }

  public function alterDisplay(&$rows) {
    $referenceYear = $this->getReferenceYear();

    // STEP 1: build the report from scratch
    $rows = [];

    $members = new CRM_Reportsandcustomsearch_Members($referenceYear);
    $allMembers = $members->get();
    $totalFees = 0;

    // build the basic table with all the members
    foreach ($allMembers as $member) {
      $row = $this->buildNewRow($members, $member);
      $totalFees += $row['civicrm_dummy_entity_membership_fee'];

      $rows[] = $row;
    }

    $totalNumberOfVotes = 0;
    $totalVotingRights = 0;

    // STEP 2: loop over the table and calculate voting rights
    for ($i = 0; $i < count($rows); $i++) {
      $percentageFee = $this->calculatePercentageFee($totalFees, $rows[$i]);
      $theoreticalNumVotes = $this->calculateTheoreticalNumberOfVotes($percentageFee);

      $rows[$i]['civicrm_dummy_entity_membership_fee_percentage'] =  $percentageFee;
      $rows[$i]['civicrm_dummy_entity_voting_rights'] = $this->determineVotingRights($rows[$i]);
      $rows[$i]['civicrm_dummy_entity_theoretical_number_of_votes'] = $this->determineTheoreticalNumberOfVotes($rows[$i], $theoreticalNumVotes);
      $rows[$i]['civicrm_dummy_entity_number_of_votes'] = $this->determineNumberOfVotes($rows[$i], $theoreticalNumVotes);
      $rows[$i]['civicrm_dummy_entity_reimbursement'] = $this->determineReimbursement($rows[$i]);

      // adjust totals
      if ($rows[$i]['civicrm_dummy_entity_voting_rights'] == 'Yes') {
        $totalVotingRights++;
      }
      $totalTheoreticalNumberOfVotes += $rows[$i]['civicrm_dummy_entity_theoretical_number_of_votes'];
      $totalNumberOfVotes += $rows[$i]['civicrm_dummy_entity_number_of_votes'];
    }

    // STEP 3: add rows with totals and quorum
    $row = $this->buildNewTotalsRow($totalFees, $totalTheoreticalNumberOfVotes, $totalVotingRights, $totalNumberOfVotes);
    $this->makeBold($row);
    $rows[] = $row;

    $row = $this->buildNewQuorumRow(1, 2, $totalTheoreticalNumberOfVotes, $totalVotingRights, $totalNumberOfVotes);
    $this->makeBold($row);
    $rows[] = $row;

    $row = $this->buildNewQuorumRow(2, 3, $totalTheoreticalNumberOfVotes, $totalVotingRights, $totalNumberOfVotes);
    $this->makeBold($row);
    $rows[] = $row;

    // add summary table
    $this->assign('summaryTitle', "BEUC Members in $referenceYear");
    $this->assign('summaryData', $members->getSummary());
  }

  public function getTemplateFileName() {
    return Civi::paths()->getPath('[civicrm.files]/ext/eu.beuc.reportsandcustomsearch/templates/CRM/Reportsandcustomsearch/Form/Report/WeightedVotes.tpl');
  }

  public function statistics(&$rows) {
    return [];
  }

  private function buildNewRow(CRM_Reportsandcustomsearch_Members $members, $member) {
    $row = [];

    $row['civicrm_dummy_entity_display_name'] = $this->getNameWithLinkToContact($member);
    $row['civicrm_dummy_entity_country'] = $member['address.country_id:label'];
    $row['civicrm_dummy_entity_membership_type'] = $member['membership_type_id:label'];

    [$fee, $status] = $members->getMembershipFee($member['contact_id']);
    $row['civicrm_dummy_entity_membership_fee'] = $fee;
    $row['civicrm_dummy_entity_membership_fee_percentage'] = '';
    $row['civicrm_dummy_entity_membership_fee_status'] = $status;
    $row['civicrm_dummy_entity_theoretical_number_of_votes'] =  0;
    $row['civicrm_dummy_entity_voting_rights'] = '';
    $row['civicrm_dummy_entity_number_of_votes'] =  0;
    $row['civicrm_dummy_entity_reimbursement'] =  '';

    return $row;
  }

  private function calculatePercentageFee($totalFees, $row) {
    if ($totalFees > 0) {
      $percentageFee = round($row['civicrm_dummy_entity_membership_fee'] / $totalFees * 100, 2);
    }
    else {
      $percentageFee = 0;
    }

    return $percentageFee;
  }

  private function calculateTheoreticalNumberOfVotes($percentageFee) {
    if ($percentageFee < 1) {
      $theoreticalNumVotes = 1;
    }
    elseif ($percentageFee <= 5) {
      $theoreticalNumVotes = 3;
    }
    else {
      $theoreticalNumVotes = 5;
    }

    return $theoreticalNumVotes;
  }

  private function determineVotingRights($row) {
    if ($row['civicrm_dummy_entity_membership_type'] !== 'Full Member') {
      return 'No, not a full member';
    }

    if ($row['civicrm_dummy_entity_membership_fee'] == 0) {
      return 'No, no fee';
    }

    if ($row['civicrm_dummy_entity_membership_fee_status'] !== 'Paid') {
      return 'No, not paid';
    }

    return 'Yes';
  }

  private function determineTheoreticalNumberOfVotes($row, $theoreticalNumVotes) {
    if ($row['civicrm_dummy_entity_membership_type'] == 'Full Member') {
      return $theoreticalNumVotes;
    }
    else {
      return 0;
    }
  }

  private function determineNumberOfVotes($row, $theoreticalNumVotes) {
    if ($row['civicrm_dummy_entity_membership_type'] == 'Full Member' && $row['civicrm_dummy_entity_membership_fee_status'] == 'Paid') {
      return $theoreticalNumVotes;
    }
    else {
      return 0;
    }
  }

  private function determineReimbursement($row) {
    if ($row['civicrm_dummy_entity_membership_fee_status'] != 'Paid') {
      return '';
    }

    $reimbursementFor = [];
    $priceFields = \Civi\Api4\PriceField::get(FALSE)
      ->addSelect('label', 'price_field_value.amount')
      ->addJoin('PriceFieldValue AS price_field_value', 'INNER')
      ->addWhere('price_set_id:name', '=', 'Reimbursement_Events')
      ->addOrderBy('price_field_value.amount', 'ASC')
      ->execute();
    foreach ($priceFields as $priceField) {
      if ($row['civicrm_dummy_entity_membership_fee'] < $priceField['price_field_value.amount']) {
        $reimbursementFor[] = $priceField['label'];
      }
    }

    return implode(', ', $reimbursementFor);
  }

  private function buildNewTotalsRow($totalFees, $totalTheoreticalNumberOfVotes, $totalVotingRights, $totalNumberOfVotes) {
    return [
      'civicrm_dummy_entity_display_name' => 'TOTAL',
      'civicrm_dummy_entity_country' => '',
      'civicrm_dummy_entity_membership_type' => '',
      'civicrm_dummy_entity_membership_fee' => $totalFees,
      'civicrm_dummy_entity_membership_fee_percentage' => '',
      'civicrm_dummy_entity_membership_fee_status' => '',
      'civicrm_dummy_entity_theoretical_number_of_votes' => $totalTheoreticalNumberOfVotes,
      'civicrm_dummy_entity_voting_rights' => $totalVotingRights,
      'civicrm_dummy_entity_number_of_votes' => $totalNumberOfVotes,
      'civicrm_dummy_entity_reimbursement' => '',
    ];
  }

  private function buildNewQuorumRow($qTop, $qBottom, $totalTheoreticalNumberOfVotes, $totalVotingRights, $totalNumberOfVotes) {
    return [
      'civicrm_dummy_entity_display_name' => '',
      'civicrm_dummy_entity_country' => '',
      'civicrm_dummy_entity_membership_type' => '',
      'civicrm_dummy_entity_membership_fee' => '',
      'civicrm_dummy_entity_membership_fee_percentage' => '',
      'civicrm_dummy_entity_membership_fee_status' => "MINIMUM FOR $qTop/$qBottom",
      'civicrm_dummy_entity_theoretical_number_of_votes' => ceil($totalTheoreticalNumberOfVotes * $qTop / $qBottom),
      'civicrm_dummy_entity_voting_rights' => ceil($totalVotingRights * $qTop / $qBottom),
      'civicrm_dummy_entity_number_of_votes' => ceil($totalNumberOfVotes * $qTop / $qBottom),
      'civicrm_dummy_entity_reimbursement' => '',
    ];
  }

  private function makeBold(&$row) {
    foreach ($row as $k => $v) {
      $row[$k] = "<b>$v</b>";
    }
  }

  private function getNameWithLinkToContact($member) {
    return '<a target=_blank href="' . CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=' . $member['contact_id']) . '">' . $member['contact_id.display_name'] . '</a>';
  }

  private function getReferenceYear() {
    $values =  $this->exportValues();

    return $values['reference_year_value'];
  }


}
