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

 */

function processProjectDates(){
	$sqlErrors=array();
	$db = DBManagerFactory::getInstance();

	$query = "SELECT min(project_task.date_start) AS start_date, max(project_task.date_finish) AS end_date, project.id AS project_id " .
			 "FROM project_task " .
			 "RIGHT OUTER JOIN project ON project_task.project_id = project.id " .
			 "GROUP BY project.id";

	$result = $db->query($query);
	 if($db->checkError()){
	 	$sqlErrors[]=$query;
	 }
	$row = $db->fetchByAssoc($result);

	while($row != null){
		if ($row['start_date'] == null || $row['end_date'] == null){
			$to_update_start = date(strtotime(date('Y-m-d')));
			$to_update_end = date(strtotime(date('Y-m-d')));

			$update_query = "UPDATE project" .
							" SET estimated_start_date = '" . date('Y-m-d', $to_update_start) . "', estimated_end_date = '" . date('Y-m-d', $to_update_end) . "'" .
							" WHERE id = '" . $row['project_id'] ."'";
		}
		else{
			$update_query = "UPDATE project" .
				 			" SET estimated_start_date = '" . $row['start_date'] ."', estimated_end_date = '" . $row['end_date'] . "'" .
				 			" WHERE id = '" . $row['project_id'] ."'";
		}

		$db->query($update_query);//, true, "Unable to update project start and end dates");
		if($db->checkError()){
	 		$sqlErrors[]=$query;
	 	}
		$row = $db->fetchByAssoc($result);
	}
    if(!empty($sqlErrors) && sizeof($sqlErrors) >0){
		 //Log into a Session variable
		 if($_SESSION['sqlErrors'] != null){
		 	$_SESSION['sqlErrors'] .= $sqlErrors;
		 }
		 else{
		 	$_SESSION['sqlErrors'] = $sqlErrors;
		 }
		 //print_r($_SESSION['sqlErrors']);
		//some errors in queries. save them in the $SESSION to show later on
	}
	return true;
}

function workdayDifference($start_date, $end_date){
	$workdays = 0;

	while ($start_date <= $end_date){
		if ( (date('w',$start_date) != 0) && (date('w',$start_date) != 6) ){
			$workdays++;
		}
		$start_date += 86400;
	}
	return $workdays;
}

function updateProjectTaskData(){
	$db = DBManagerFactory::getInstance();
	$sqlErrors = array();
	global $timedate;

    require_once('include/utils/db_utils.php');

	$query = "SELECT date_start, date_finish, id FROM project_task";

	$result = $db->query($query); //, true, "Unable to retrieve project task dates");
	if($db->checkError()){
		$sqlErrors[]=$query;
	}
	$row = $db->fetchByAssoc($result);

	while ($row != null){
		if (empty($row['date_start']) && empty($row['date_finish'])){
			$date_start = strtotime(date('Y-m-d'));
			$date_finish = strtotime(date('Y-m-d'));
		}
		else if (!empty($row['date_start']) && empty($row['date_finish'])){
			$date_finish = strtotime(date($row['date_start']));
			$date_start = strtotime(date($row['date_start']));
		}
		else if (empty($row['date_start']) && !empty($row['date_finish'])){
			$date_start = strtotime(date($row['date_finish']));
			$date_finish = strtotime(date($row['date_finish']));
		}
		else{
			$date_start = strtotime(date($row['date_start']));
			$date_finish = strtotime(date($row['date_finish']));
		}

		if ( date('w', $date_start) == 0 ){
			$date_start = $date_start + 86400;
		}
		else if ( date('w', $date_start) == 6 ){
			$date_start = $date_start + 86400*2;
		}

		if ( date('w', $date_finish) == 0 ){
			$date_finish = $date_finish + 86400;
		}
		else if ( date('w', $date_finish) == 6 ){
			$date_finish = $date_finish + 86400*2;
		}

        $to_update_start = date($date_start);
        $to_update_finish = date($date_finish);

        $duration = workdayDifference($date_start, $date_finish);

        $update_query = "UPDATE project_task" .
                        " SET duration = '" . $duration . "'," .
                        " date_start = '" . date('Y-m-d', $to_update_start) . "'," .
                        " date_finish = '" . date('Y-m-d', $to_update_finish) . "'" .
                        " WHERE id = '" . $row['id'] ."'";

        $db->query($update_query);//, true, "Unable to update duration");
		if($db->checkError()){
			$sqlErrors[]=$query;
		}
		$row = $db->fetchByAssoc($result);
	}
	if(!empty($sqlErrors) && sizeof($sqlErrors) >0){
		 //Log into a Session variable
		 if($_SESSION['sqlErrors'] != null){
		 	$_SESSION['sqlErrors'] .= $sqlErrors;
		 }
		 else{
		 	$_SESSION['sqlErrors'] = $sqlErrors;
		 }
		 //print_r($_SESSION['sqlErrors']);
		//some errors in queries. save them in the $SESSION to show later on
	}
	return true;
}

function updateProjectResources(){
	global $current_user;
	$db = DBManagerFactory::getInstance();
    $sqlErrors = array();
	$modified_user_id = $current_user->id;
	$created_by = $current_user->id;

	$query = "SELECT DISTINCT assigned_user_id, project_id from project_task";

	$result = $db->query($query);//, true, "Unable to retrieve assigned users of tasks");
	if($db->checkError()){
		$sqlErrors[]=$query;
	}
	$row = $db->fetchByAssoc($result);

	while ($row != null){
		$id = create_guid();
		$date_modified = gmdate("Y-m-d H:i:s");
		$insert_query =	"INSERT INTO project_resources(id, date_modified, modified_user_id, " .
													  "created_by, project_id, resource_id, resource_type) " .
						"VALUES('" . $id . "'," .
							   "'" . $date_modified . "'," .
							   "'" . $modified_user_id . "'," .
							   "'" . $created_by . "'," .
							   "'" . $row['project_id'] . "'," .
							   "'" . $row['assigned_user_id'] . "'," .
							   "'Users')";

		$db->query($insert_query);//, true, "Unable to update project resources");
		if($db->checkError()){
			$sqlErrors[]=$insert_query;
		}

		$row = $db->fetchByAssoc($result);
	}

	// update Project Task Resource
	$update_ptr_query = "UPDATE project_task SET resource_id = assigned_user_id";
	$db->query($update_ptr_query);//, true, "Unable to update project task resources");
	$db->query($update_ptr_query);//, true, "Unable to update project task resources");
		if($db->checkError()){
			$sqlErrors[]=$insert_query;
	}
    if(!empty($sqlErrors) && sizeof($sqlErrors) >0){
		 //Log into a Session variable
		 if($_SESSION['sqlErrors'] != null){
		 	$_SESSION['sqlErrors'] .= $sqlErrors;
		 }
		 else{
		 	$_SESSION['sqlErrors'] = $sqlErrors;
		 }
		 //print_r($_SESSION['sqlErrors']);
		//some errors in queries. save them in the $SESSION to show later on
	}
	return true;
}

function updateProjectTaskPredecessors(){
	$db = DBManagerFactory::getInstance();
	$sqlErrors = array();
	$query = "SELECT P2.project_task_id, P1.id " .
			 "FROM project_task P1, project_task P2 " .
			 "WHERE P1.depends_on_id = P2.id AND P1.project_id = P2.project_id";

	$result = $db->query($query);//, true, "Unable to retrieve project_task_id");
	if($db->checkError()){
		$sqlErrors[]=$query;
	}
	$row = $db->fetchByAssoc($result);

	while ($row != null){
		$update_query = "UPDATE project_task " .
						"SET predecessors = '" . $row['project_task_id'] . "'" .
						"WHERE id = '". $row['id'] ."'";

		$db->query($update_query);//, true, "Unable to update predecessors");
		if($db->checkError()){
			$sqlErrors[]=$update_query;
		}
		$row = $db->fetchByAssoc($result);
	}
    if(!empty($sqlErrors) && sizeof($sqlErrors) >0){
		 //Log into a Session variable
		 if($_SESSION['sqlErrors'] != null){
		 	$_SESSION['sqlErrors'] .= $sqlErrors;
		 }
		 else{
		 	$_SESSION['sqlErrors'] = $sqlErrors;
		 }
		 //print_r($_SESSION['sqlErrors']);
		//some errors in queries. save them in the $SESSION to show later on
	}
	return true;
}

function fixOrderNumbers(){
	$db = DBManagerFactory::getInstance();
	$sqlErrors = array();
	$query = "SELECT project_id " .
			 "FROM project_task " .
			 "GROUP BY project_id";

	$result = $db->query($query);//, true, "Unable to retrieve number of tasks for project_id");
	if($db->checkError()){
		$sqlErrors[]=$query;
	}
	$row = $db->fetchByAssoc($result);

	while ($row != null){
		// eliminate duplicate order_numbers, build project_task_id by ordering and counting up
		$order_num_query = "SELECT order_number, id FROM project_task WHERE project_id = '" . $row['project_id'] . "' AND order_number IS NOT NULL AND deleted=0 ORDER BY order_number";

		$order_num_result = $db->query($order_num_query);//, true, "Unable to retrieve order_number");
		if($db->checkError()){
			$sqlErrors[]=$order_num_result;
		}
		$order_num_row = $db->fetchByAssoc($order_num_result);

		$counter = 1;

		while ($order_num_row != null){
			if ($order_num_row['order_number'] != $counter){
				$update_qry = "UPDATE project_task SET project_task_id = " . $counter ." WHERE id = '" . $order_num_row['id'] ."'";
			}
			else{
				$update_qry = "UPDATE project_task SET project_task_id = " . $order_num_row['order_number'] ." WHERE id = '" . $order_num_row['id'] ."'";
			}
			$db->query($update_qry);//, true, "Update project_task_id");
 			if($db->checkError()){
				$sqlErrors[]=$update_qry;
			}
			$order_num_row = $db->fetchByAssoc($order_num_result);

			$counter = $counter+1;
		}

		// assign project_task_id for unassigned order_numbers
		$null_order_num_query = "SELECT order_number, id FROM project_task WHERE project_id = '" . $row['project_id'] . "' AND order_number IS NULL AND deleted=0 ORDER BY order_number";
		$null_order_num_result = $db->query($null_order_num_query);//, true, "Unable to retrieve order_number");
		if($db->checkError()){
			$sqlErrors[]=$null_order_num_query;
		}
		$null_order_num_row = $db->fetchByAssoc($null_order_num_result);

		while ($null_order_num_row != null){
			$update_qry = "UPDATE project_task SET project_task_id = " . $counter ." WHERE id = '" . $null_order_num_row['id'] ."'";
		 	$db->query($update_qry);//, true, "Update project_task_id");
   			if($db->checkError()){
				$sqlErrors[]=$update_qry;
			}
			$null_order_num_row = $db->fetchByAssoc($null_order_num_result);

			$counter = $counter+1;
		}

		$row = $db->fetchByAssoc($result);
	}
    if(!empty($sqlErrors) && sizeof($sqlErrors) >0){
		 //Log into a Session variable
		 if($_SESSION['sqlErrors'] != null){
		 	$_SESSION['sqlErrors'] .= $sqlErrors;
		 }
		 else{
		 	$_SESSION['sqlErrors'] = $sqlErrors;
		 }
		 //print_r($_SESSION['sqlErrors']);
		//some errors in queries. save them in the $SESSION to show later on
	}
	return true;
}

function installProjectRelationships(){
	require_once('ModuleInstall/ModuleInstaller.php');

	$mi = new ModuleInstaller();

	ob_start();
	$mi->install_relationship("$unzip_dir/relationships/users_holidaysMetaData.php");
	$mi->install_relationship("$unzip_dir/relationships/project_casesMetaData.php");
	$mi->install_relationship("$unzip_dir/relationships/project_bugsMetaData.php");
	$mi->install_relationship("$unzip_dir/relationships/project_productsMetaData.php");
	$mi->install_relationship("$unzip_dir/relationships/projects_accountsMetaData.php");
	$mi->install_relationship("$unzip_dir/relationships/projects_contactsMetaData.php");
	$mi->install_relationship("$unzip_dir/relationships/projects_opportunitiesMetaData.php");
	$mi->install_relationship("$unzip_dir/relationships/projects_quotesMetaData.php");
	ob_end_clean();
}

function migrateProjectRelationships(){
	$db = DBManagerFactory::getInstance();
	$sqlErrors = array();
	$project_relationships = array( 'account' => 'Accounts',
									'contact' => 'Contacts',
									'opportunity' => 'Opportunities',
									'bug' => 'Bugs',
									'case' => 'Cases',
									'product' => 'Products',
									'quote' => 'Quotes',
									);

	foreach($project_relationships as $pr_id => $pr){
		$query = "INSERT INTO projects_" . strtolower($pr) . "(id, " . strtolower($pr_id) . "_id, project_id, date_modified, deleted)" .
				 " SELECT id, relation_id, project_id, date_modified, deleted" .
				 " FROM project_relation" .
				 " WHERE relation_type = '" . $pr . "'";

		$db->query($query);//, true, "Unable to add to project relationship table projects_" . strtolower($pr));
		if($db->checkError()){
			$sqlErrors[]=$query;
		}

	}
	if(!empty($sqlErrors) && sizeof($sqlErrors) >0){
		 //Log into a Session variable
		 if($_SESSION['sqlErrors'] != null){
		 	$_SESSION['sqlErrors'] .= $sqlErrors;
		 }
		 else{
		 	$_SESSION['sqlErrors'] = $sqlErrors;
		 }
		 //print_r($_SESSION['sqlErrors']);
		//some errors in queries. save them in the $SESSION to show later on
	}
	return true;
}

///////////////////////////////////////////////////////////////////////////////
////	BEGIN PROJECTS INSTALL

global $sugar_config;
global $path;

_logThis("Beginning Projects conversion", $path);

// FIX ORDER NUMBERS
_logThis("Fixing Duplicate and Empty Order Numbers", $path);
fixOrderNumbers();

// FIX PROJECT TASK WEEKEND DATES
_logThis("Updating Project Task Weekend Dates and Durations", $path);
updateProjectTaskData();

// DETERMINE PROJECT START AND END DATES
_logThis("Determining Project Start and End Dates", $path);
processProjectDates();

// UPDATE PROJECT RESOURCES
_logThis("Updating Project Resources", $path);
updateProjectResources();

// UPDATE PROJECT TASK PREDECESSORS
_logThis("Updating Project Task Predecessors", $path);
updateProjectTaskPredecessors();

// INSTALLING PROJECT RELATIONSHIPS
_logThis("Installing Project Relationships", $path);
installProjectRelationships();

// MIGRATING PROJECT RELATION DATA TO PROJECT RELATIONSHIPS TABLES
_logThis("Migrating Project Relationships", $path);
migrateProjectRelationships();

?>
