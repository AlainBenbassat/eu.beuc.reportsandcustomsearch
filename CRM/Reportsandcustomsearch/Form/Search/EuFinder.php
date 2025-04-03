<?php
use CRM_Reportsandcustomsearch_ExtensionUtil as E;

class CRM_Reportsandcustomsearch_Form_Search_EuFinder extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  function __construct(&$formValues) {
    parent::__construct($formValues);
  }

  function buildForm(&$form) {
    $items = [];
    $defaults = [];
    CRM_Utils_System::setTitle(E::ts('EP Committee Search'));

    // Committees
    $committees = $this->getCommittees();
    $form->addElement('select', 'committees', E::ts('Committee'), $committees);
    $items[] = 'committees';

    // Political Groups
    $politicalGroups = $this->getPoliticalGroups(FALSE);
    $form->add('select', 'political_groups',
      E::ts('Political Groups'),
      $politicalGroups,
      FALSE,
      ['class' => 'crm-select2', 'multiple' => 'multiple', 'style' => 'width:420px']
    );
    $items[] = 'political_groups';

    // Countries
    $countries = $this->getCountries();
    $form->add('select', 'countries',
      E::ts('Countries'),
      $countries,
      FALSE,
      ['class' => 'crm-select2', 'multiple' => 'multiple', 'style' => 'width:420px']
    );
    $items[] = 'countries';

    // set defaults and assign to template
    $form->setDefaults($defaults);
    $form->assign('elements', $items);
  }

  function &columns() {
    $columns = [
      E::ts('Name') => 'sort_name',
      E::ts('Contact Id') => 'contact_id',
      E::ts('First Name') => 'first_name',
      E::ts('Last Name') => 'last_name',
      E::ts('Political Group') => 'political_groups',
      E::ts('Country') => 'countries',
      E::ts('Role(s)') => 'roles',
      E::ts('Email') => 'email',
    ];
    return $columns;
  }

  function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    $groupBy = ' GROUP BY contact_a.sort_name, contact_a.first_name, contact_a.last_name, e.email';
    $sql = $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, $groupBy);
//die($sql);
    return $sql;
  }

  function select() {
    $select = "
      contact_a.id contact_id,
      contact_a.sort_name,
      contact_a.first_name,
      contact_a.last_name,
      group_concat(DISTINCT(pg.title)) political_groups,
      group_concat(DISTINCT(ctr.title)) countries,
      group_concat(rt.label_a_b) roles,
      e.email
    ";

    return $select;
  }

  function from() {
    $politicalGroups = $this->getPoliticalGroups(TRUE);
    $countries = $this->getCountries(TRUE);

    $from = "
      FROM
        civicrm_contact contact_a
      INNER JOIN
        civicrm_relationship r on r.contact_id_a = contact_a.id
      INNER JOIN
        civicrm_relationship_type rt on r.relationship_type_id = rt.id
      LEFT OUTER JOIN
        civicrm_email e ON e.contact_id = contact_a.id AND e.is_primary = 1
      LEFT OUTER JOIN
        civicrm_group_contact pgc ON pgc.contact_id = contact_a.id and pgc.group_id in (" . implode(',', $politicalGroups) . ") and pgc.status = 'Added'
      LEFT OUTER JOIN
        civicrm_group pg ON pg.id = pgc.group_id
      LEFT OUTER JOIN
        civicrm_group_contact ctrc ON ctrc.contact_id = contact_a.id and ctrc.group_id in (" . implode(',', $countries) . ") and ctrc.status = 'Added'
      LEFT OUTER JOIN
        civicrm_group ctr ON ctr.id = ctrc.group_id
    ";

    return $from;
  }

  function where($includeContactIDs = FALSE) {
    $where = ' contact_a.is_deleted = 0 and r.is_active = 1 ';

    $committee = CRM_Utils_Array::value('committees', $this->_formValues);
    $where .= " and r.contact_id_b = $committee ";

    $politicalGroups = CRM_Utils_Array::value('political_groups', $this->_formValues);
    if ($politicalGroups) {
      $where .= ' and exists (select * from civicrm_group_contact gc1 where gc1.contact_id = contact_a.id and gc1.group_id in (' . implode(',', $politicalGroups) . ')) ';
    }

    $countries = CRM_Utils_Array::value('countries', $this->_formValues);
    if ($countries) {
      $where .= ' and exists (select * from civicrm_group_contact gc2 where gc2.contact_id = contact_a.id and gc2.group_id in (' . implode(',', $countries) . ')) ';
    }

    return $where;
  }

  function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }

  function getCommittees() {
    $committees = [];

    $sql = "
      select
        id,
        organization_name
      from
        civicrm_contact
      where
        contact_sub_type  like '%EP_Committee%'
      and
        is_deleted = 0
      order by
        sort_name
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $committees[$dao->id] = $dao->organization_name;
    }

    return $committees;
  }

  function getCountries($onlyIds = FALSE) {
    $countries = [];

    $sql = "
      select
        id,
        title
      from
        civicrm_group g
      where
        g.parents = '6'
      order by
        title
    ";

    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      if ($onlyIds) {
        $countries[] = $dao->id;
      }
      else {
        $countries[$dao->id] = $dao->title;
      }
    }

    return $countries;
  }

  function getPoliticalGroups($onlyIds = FALSE) {
    $politicalGroups = [];

    $sql = "
      select
        id,
        title
      from
        civicrm_group g
      where
        g.parents = '112'
      order by
        title
    ";

    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      if ($onlyIds) {
        $politicalGroups[] = $dao->id;
      }
      else {
        $politicalGroups[$dao->id] = $dao->title;
      }
    }

    return $politicalGroups;
  }
}
