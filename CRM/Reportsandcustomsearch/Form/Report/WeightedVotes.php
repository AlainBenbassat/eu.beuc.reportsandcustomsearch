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
      'voting_rights' => 'Voting rights?',
      'number_of_votes' => 'Number of Votes',
    ];

    $i = 1;
    foreach ($colTitles as $k => $colTitle) {
      $cols[$k] = [
        'title' => $colTitle,
        'required' => TRUE,
        'dbAlias' => '1',
      ];

      $i++;
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

    // build the report from scratch
    $rows = [];

    $members = new CRM_Reportsandcustomsearch_Members($referenceYear);
    $allMembers = $members->get();
    $totalFees = 0;

    foreach ($allMembers as $member) {
      $row = [];

      $row['civicrm_dummy_entity_display_name'] = $this->getNameWithLinkToContact($member);
      $row['civicrm_dummy_entity_country'] = $member['address.country_id:label'];
      $row['civicrm_dummy_entity_membership_type'] = $member['membership_type_id:label'];

      [$fee, $status] = $members->getMembershipFee($member['contact_id']);
      $row['civicrm_dummy_entity_membership_fee'] = $fee;
      $row['civicrm_dummy_entity_membership_fee_percentage'] = '';
      $row['civicrm_dummy_entity_membership_fee_status'] = $status;
      $row['civicrm_dummy_entity_voting_rights'] = '';
      $row['civicrm_dummy_entity_number_of_votes'] =  0;

      $totalFees += $row['civicrm_dummy_entity_membership_fee'];

      $rows[] = $row;
    }

    $totalNumberOfVotes = 0;
    $totalVotingRights = 0;

    for ($i = 0; $i < count($rows); $i++) {
      if ($totalFees > 0) {
        $percentageFee = round($rows[$i]['civicrm_dummy_entity_membership_fee'] / $totalFees * 100, 2);
      }
      else {
        $percentageFee = 0;
      }

      $rows[$i]['civicrm_dummy_entity_membership_fee_percentage'] =  $percentageFee;

      if ($rows[$i]['civicrm_dummy_entity_membership_type'] !== 'Full Member') {
        $rows[$i]['civicrm_dummy_entity_voting_rights'] =  'No, not a full member';
        $rows[$i]['civicrm_dummy_entity_number_of_votes'] =  0;
      }
      elseif ($rows[$i]['civicrm_dummy_entity_membership_fee'] == 0) {
        $rows[$i]['civicrm_dummy_entity_voting_rights'] =  'No, no fee';
        $rows[$i]['civicrm_dummy_entity_number_of_votes'] =  0;
      }
      elseif ($rows[$i]['civicrm_dummy_entity_membership_fee_status'] !== 'Paid') {
        $rows[$i]['civicrm_dummy_entity_voting_rights'] =  'No, not paid';
        $rows[$i]['civicrm_dummy_entity_number_of_votes'] =  0;
      }
      else {
        $rows[$i]['civicrm_dummy_entity_voting_rights'] =  'Yes';
        $totalVotingRights++;

        if ($percentageFee <= 1) {
          $numVotes = 1;
        }
        elseif ($percentageFee <= 5) {
          $numVotes = 3;
        }
        else {
          $numVotes = 5;
        }
        $rows[$i]['civicrm_dummy_entity_number_of_votes'] =  $numVotes;
      }

      $totalNumberOfVotes += $rows[$i]['civicrm_dummy_entity_number_of_votes'];
    }

    // totals
    $row = [
      'civicrm_dummy_entity_display_name' => '',
      'civicrm_dummy_entity_country' => '',
      'civicrm_dummy_entity_membership_type' => '',
      'civicrm_dummy_entity_membership_fee' => $totalFees,
      'civicrm_dummy_entity_membership_fee_percentage' => '',
      'civicrm_dummy_entity_membership_fee_status' => '',
      'civicrm_dummy_entity_voting_rights' => $totalVotingRights,
      'civicrm_dummy_entity_number_of_votes' => $totalNumberOfVotes,
    ];
    $this->makeBold($row);
    $rows[] = $row;

    $row = [
      'civicrm_dummy_entity_display_name' => '',
      'civicrm_dummy_entity_country' => '',
      'civicrm_dummy_entity_membership_type' => '',
      'civicrm_dummy_entity_membership_fee' => '',
      'civicrm_dummy_entity_membership_fee_percentage' => 'MINIMUM',
      'civicrm_dummy_entity_membership_fee_status' => '2/3',
      'civicrm_dummy_entity_voting_rights' => ceil($totalVotingRights * 2 / 3),
      'civicrm_dummy_entity_number_of_votes' => ceil($totalNumberOfVotes * 2 / 3),
    ];
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
