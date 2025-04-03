<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return [
  [
    'name' => 'CRM_Reportsandcustomsearch_Form_Report_WeightedVotes',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'WeightedVotes',
      'description' => 'WeightedVotes (eu.beuc.reportsandcustomsearch)',
      'class_name' => 'CRM_Reportsandcustomsearch_Form_Report_WeightedVotes',
      'report_url' => 'eu.beuc.reportsandcustomsearch/weightedvotes',
      'component' => '',
    ],
  ],
];
