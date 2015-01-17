<?php

/**
 * LegacyExport.Generate API
 * Generates CSV files for Memoria and devel.lid2 imports
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_legacy_export_generate($params = array()) {

	$exportPath = CIVICRM_TEMPLATE_COMPILEDIR . '/../legacy-export/';
	if(!file_exists($exportPath))
		mkdir($exportPath, 0777, true);

	CRM_LegacyExport_Generate::generate($exportPath);

	return civicrm_api3_create_success(array('message' => 'CSV-bestanden gegenereerd.'));
}

/**
 * LegacyExport.Generate API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_legacy_export_generate_spec(&$spec) {

}