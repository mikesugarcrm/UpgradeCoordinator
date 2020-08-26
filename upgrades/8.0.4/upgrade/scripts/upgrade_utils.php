<?php
global $sugar_version;
if(!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');

}
/**
 * This script executes after the files are copied during the install.
 *
 * LICENSE: The contents of this file are subject to the SugarCRM Professional
 * End User License Agreement("License") which can be viewed at
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
 * (i) the "Powered by SugarCRM" logo and
 * (ii) the SugarCRM copyright notice
 * in the same form as they appear in the distribution.  See full license for
 * requirements.
 *
 * Your Warranty, Limitations of liability and Indemnity are expressly stated
 * in the License.  Please refer to the License for the specific language
 * governing these rights and limitations under the License.  Portions created
 * by SugarCRM are Copyright(C) 2005 SugarCRM, Inc.; All Rights Reserved.
 *

 */

require_once('include/database/DBManagerFactory.php');
///////////////////////////////////////////////////////////////////////////////
////	UPGRADE UTILS
/**
 * upgrade wizard logging
 */
function _logThis($entry) {
	if(function_exists('logThis')) {
		logThis($entry);
	} else {

		$log = clean_path(getcwd().'/upgradeWizard.log');
		// create if not exists
		if(!file_exists($log)) {
			$fp = fopen($log, 'w+'); // attempts to create file
			if(!is_resource($fp)) {
				$GLOBALS['log']->fatal('UpgradeWizard could not create the upgradeWizard.log file');
			}
		} else {
			$fp = fopen($log, 'a+'); // write pointer at end of file
			if(!is_resource($fp)) {
				$GLOBALS['log']->fatal('UpgradeWizard could not open/lock upgradeWizard.log file');
			}
		}

		$line = date('r').' [UpgradeWizard] - '.$entry."\n";

		if(fwrite($fp, $line) === false) {
			$GLOBALS['log']->fatal('UpgradeWizard could not write to upgradeWizard.log: '.$entry);
		}

		fclose($fp);
	}
}

/**
 * This is specific for MSSQL. Before doing an alter table statement for MSSQL, this funciton will drop all the constraint
 * for that column
 */
 function dropColumnConstraintForMSSQL($tableName, $columnName) {
	global $sugar_config;
	if($sugar_config['dbconfig']['db_type'] == 'mssql') {
    	$db = DBManagerFactory::getInstance();
    	$query = "declare @name nvarchar(32), @sql nvarchar(1000)";

		$query = $query . " select @name = sys.objects.name from sys.objects where type_desc like '%CONSTRAINT' and (OBJECT_NAME(parent_object_id) like '%{$tableName}%') and sys.objects.object_id in (select default_object_id from sys.columns where name like '{$columnName}')";

		$query = $query . " begin
		    select @sql = 'ALTER TABLE {$tableName} DROP CONSTRAINT [' + @name + ']'
		    execute sp_executesql @sql
		end";

		$db->query($query);
	} // if
 } // fn

/**
 * gets Upgrade version
 */
function getUpgradeVersion() {
	$version = '';

	if(isset($_SESSION['sugar_version_file']) && !empty($_SESSION['sugar_version_file']) && is_file($_SESSION['sugar_version_file'])) {
		// do an include because the variables will load locally, and it will only populate in here.
		include($_SESSION['sugar_version_file']);
		return $sugar_db_version;
	}

	return $version;
}

// moving rebuild js to upgrade utils

function rebuild_js_lang(){
	require_once('include/utils/file_utils.php');
    global $sugar_config;

    $jsFiles = array();
    getFiles($jsFiles, $sugar_config['cache_dir'] . 'jsLanguage');
    foreach($jsFiles as $file) {
        unlink($file);
    }

    if(empty($sugar_config['js_lang_version']))
    	$sugar_config['js_lang_version'] = 1;
    else
    	$sugar_config['js_lang_version'] += 1;

    write_array_to_file( "sugar_config", $sugar_config, "config.php");

    //remove lanugage cache files
    require_once('include/SugarObjects/LanguageManager.php');
    LanguageManager::clearLanguageCache();
}

function clear_SugarLogic_cache(){
    require_once('include/utils/file_utils.php');
    global $sugar_config;

    $files = array();
    getFiles($files, $sugar_config['cache_dir'] . 'Expressions');
    foreach($files as $file) {
        unlink($file);
    }
}


/**
 * update DB version and sugar_version.php
 */

function upgradeDbAndFileVersion($version) {
	global $instancePath;
	if(!isset($instancePath) && isset($_SESSION['instancePath'])){
		 $instancePath = $_SESSION['instancePath'];
	}
	if(!function_exists('updateVersions')) {
		if(file_exists('modules/UpgradeWizard/uw_utils.php')){
			require_once('modules/UpgradeWizard/uw_utils.php');
		} elseif(file_exists($instancePath.'/modules/UpgradeWizard/uw_utils.php')){
			require_once($instancePath.'/modules/UpgradeWizard/uw_utils.php');
		}
	}
	updateVersions($version);
}
////	END UPGRADE UTILS
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
////	SCHEMA CHANGE PRIVATE METHODS
function _run_sql_file($filename) {
	global $path;

    if(!is_file($filename)) {
    	_logThis("*** ERROR: Could not find file: {$filename}", $path);
        return(false);
    }

    $fh         = fopen($filename,'r');
    $contents   = fread($fh, filesize($filename));
    fclose($fh);

    $lastsemi   = strrpos($contents, ';') ;
    $contents   = substr($contents, 0, $lastsemi);
    $queries    = explode(';', $contents);
    $db         = DBManagerFactory::getInstance();

	foreach($queries as $query){
		if(!empty($query)){
			_logThis("Sending query: ".$query, $path);
			if($db->dbType == 'oci8') {
				$query_result = $db->query($query, true, "An error has occured while performing db query.  See log file for details.<br>");
			} else {
				$query_result = $db->query($query.';', true, "An error has occured while performing db query.  See log file for details.<br>");
			}
		}
	}

	return(true);
}

function run_sql_file_for_oracle($filename) {
	if(!is_file($filename)) {
		_logThis("*** ERROR: Could not find file: {$filename}");
		return(false);
	}

	$fh         = fopen($filename,'r');
	$contents   = fread($fh, filesize($filename));
	fclose($fh);

	$lastsemi   = strrpos($contents, ';') ;
	$contents   = substr($contents, 0, $lastsemi);
	$queries    = explode(';', $contents);
	$db         = DBManagerFactory::getInstance();

	foreach($queries as $query) {
		if(!empty($query)) {
			_logThis("Sending query: ".$query);
			$db->query($query, true, "An error has occured while performing db query.  See log file for details.<br>", true);
		}
	}

	return(true);
}
////	END SCHEMA CHANGE METHODS
///////////////////////////////////////////////////////////////////////////////


///////////////////////////////////////////////////////////////////////////////
////	FIX THINGS IN UPGRADE FUNCTIONS
/**
 * creates global & private teams and adds users to them (meant for flavor
 * conversion upgrades
 */
function doTeams() {
	global $db;
	global $beanFiles;
	global $current_user;

	if(!class_exists('User')) {
		require_once('modules/Users/User.php');
	}
	if(!class_exists('Team')) {
		require_once('modules/Teams/Team.php');
	}
	if(!function_exists('get_user_array')) {
		require_once('include/utils.php');
	}

	// create Global team
	$global = new Team();
	$global->new_with_id = true;
	$global->id = '1';
	$global->name = 'Global';
	$global->description = 'Globally Visible';
	$global->created_by = '1';
	$global->modified_user_id = '1';
	$global->private = 0;
	$global->save();

	// get all users and add to global, then create new private teams for each
	$users = get_user_array();
	foreach($users as $id => $user) {
		if(empty($user))
			continue;
		// add to global
		$global->add_user_to_team($id);
		$q = "UPDATE team_memberships SET explicit_assign = 1 WHERE team_id='1' AND user_id='{$id}'";
		$db->query($q);

		// set default team to Global as well
		$q2 = "UPDATE users SET default_team = '1' WHERE id ='{$id}'";
		$db->query($q2);
		if($db->checkError()){
	        //put in the array to use later on
	        $_SESSION['sqlSkippedQueries'][] = $q2;
    	}
		// create private team
		$privTeam = new Team();
		$privTeam->name = "({$user})";
		$privTeam->description = "Private Team for {$user}";
		$privTeam->private = 1;
		$privTeam->created_by = '1';
		$privTeam->modified_user_id = '1';
		$privTeam->save();
		$privTeam->add_user_to_team($id);
	}

	$current_user->default_team = '1';

	// modules - set team_id = 1
	if(empty($beanFiles)) {
		include('include/modules.php'); // provides beanfiles
	}
	foreach($beanFiles as $class => $classFile) {
		if(!class_exists($class)) {
			require_once($classFile);
		}
		$classVars = get_class_vars($class);

		if(array_key_exists('team_id', $classVars)) {
			$bean = new $class();
			$q = "UPDATE {$bean->table_name} SET team_id = '1'";
			$db->query($q);
			if($db->checkError()){
	        	//put in the array to use later on
	        	$_SESSION['sqlSkippedQueries'][] = $q;
    		}
		}
	}
}


////	END FIX THINGS IN UPGRADE FUNCTIONS
///////////////////////////////////////////////////////////////////////////////
?>
