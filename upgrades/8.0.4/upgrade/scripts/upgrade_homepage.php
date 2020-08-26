<?php

/**
 * This script executes after the files are copied during the install.
 *
 * LICENSE: The contents of this file are subject to the SugarCRM Professional
 * End User License Agreement ("License") which can be viewed at
 * http://www.sugarcrm.com/EULA.  By installing or using this file, You have
 * unconditionally agreed to the terms and conditions of the License, and You
 * may not use this file except in compliance with the License.  Under the
 * terms of the license, You shall not, among other things: 1) sublicense,
 * resell, rent, lease, redistribute, assign or otherwise transfer Your
 * rights to the Software, and 2) use the Software for timesharing or service
 * bureau purposes such as hosting the Software for commercial gain and/or for
 * the benefit of a third party.  Use of the Software may be subject to
 * applicable fees and any use of the Software without first paying applicable
 * fees is strictly prohibited.  You do not have the right to remove SugarCRM
 * copyrights from the source code or user interface.
 *
 * All copies of the Covered Code must include on each user interface screen:
 *  (i) the "Powered by SugarCRM" logo and
 *  (ii) the SugarCRM copyright notice
 * in the same form as they appear in the distribution.  See full license for
 * requirements.
 *
 * Your Warranty, Limitations of liability and Indemnity are expressly stated
 * in the License.  Please refer to the License for the specific language
 * governing these rights and limitations under the License.  Portions created
 * by SugarCRM are Copyright (C) 2005 SugarCRM, Inc.; All Rights Reserved.
 *
 * $Id$
 */

function create_new_default_report(){
	global $current_language;

	$lang_strings = return_module_language($current_language, 'Reports');

	// Create "Leads By Lead Source" Seed Report
	$report = array('Leads', $lang_strings['DEFAULT_REPORT_TITLE_18'], '{"report_type":"summary","display_columns":[],"summary_columns":[{"name":"count","label":"Count","group_function":"count","table_key":"self"},{"name":"lead_source","label":"Leads: Lead Source","table_key":"self","is_group_by":"visible"}],"filters_def":[],"filters_combiner":"AND","group_defs":[{"name":"lead_source","label":"Lead Source","table_key":"self"}],"full_table_list":{"self":{"parent":"","value":"Leads","module":"Leads","label":"Leads","children":{"self_link_0":"self_link_0"}},"self_link_0":{"parent":"self","children":[],"value":"assigned_user_link","label":"Assigned To User","link_def":{"name":"assigned_user_link","relationship_name":"leads_assigned_user","bean_is_lhs":"","link_type":"one","label":"Assigned To User","table_key":"self_link_0"},"module":"Users"}},"module":"Leads","report_name":"' . $lang_strings['DEFAULT_REPORT_TITLE_18'] . '","chart_type":"vBarF","chart_description":"","numerical_chart_column":"count","assigned_user_id":"1"}', 'summary');

	require_once('modules/Reports/SavedReport.php');
	$saved_report = new SavedReport();

	$result = $saved_report->save_report(-1, 1, $report[1], $report[0], $report[3], $report[2], 1, '1', 1);
}

// ORACLE Bug - saved report is case sensitive in Oracle
function fix_report_string(){
	$query = "UPDATE saved_reports SET name = 'Opportunities By Lead Source' WHERE name = 'Opportunities by Lead Source'";
	$GLOBALS['db']->query($query, true, "Unable to update report string");
}

function add_report_chart_types(){
	require_once('modules/Reports/SavedReport.php');
	require_once('modules/Reports/Report.php');
	
	$focus = new SavedReport();
	$focus->disable_row_level_security = false;
	
	$savedReports = $focus->get_full_list(""," report_type in('summary','detailed_summary')");
	
	if (!empty($savedReports)){
		foreach($savedReports as $savedReport){
			$report_content = new Report (str_replace('&quot;', '"', $savedReport->content));
			$report_content->saved_report = &$savedReport;
			
			$update_qry = "UPDATE saved_reports SET chart_type = '" . $report_content->chart_type . "' WHERE id = '" . $savedReport->id . "'";
			$GLOBALS['db']->query($update_qry, true, "Unable to update chart types");
		}
	}
}

function upgrade_homepage(){
	create_new_default_report();
	fix_report_string();
}
?>
