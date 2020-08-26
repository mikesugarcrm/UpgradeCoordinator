<?php
if(!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');

}
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
 */

require_once(clean_path($unzip_dir.'/scripts/upgrade_utils.php'));



/**
 * repair the workflow sessions
 */
function do_repair_workflow_conditions() {
	require_once('modules/WorkFlow/WorkFlow.php');
	require_once('modules/WorkFlowTriggerShells/WorkFlowTriggerShell.php');
	require_once('include/workflow/glue.php');
    $db = DBManagerFactory::getInstance();
    // grab all workflows that are time based and have not been deleted
    $query = "SELECT workflow_triggershells.id trigger_id FROM workflow LEFT JOIN workflow_triggershells ON workflow_triggershells.parent_id = workflow.id WHERE workflow.deleted = 0 AND workflow.type = 'Time' AND workflow_triggershells.type = 'compare_any_time'";
    $data = $db->query($query);
	if($db->checkError()){
	    //put in the array to use later on
	    $_SESSION['sqlSkippedQueries'][] = $query;
    }
    while($row = $db->fetchByAssoc($data)) {
			$shell = new WorkFlowTriggerShell();
			$glue_object = new WorkFlowGlue();
			$shell->retrieve($row['trigger_id']);
			$shell->eval = $glue_object->glue_normal_compare_any_time($shell);
			$shell->save();
    }
	//call repair workflow
	$workflow_object = new WorkFlow();
	$workflow_object->repair_workflow();
}

function add_EZ_PDF() {
	$cust_file =  "<?php\n";
	$cust_file .= '$sugarpdf_default["PDF_CLASS"] = "EZPDF";'."\n";
	$cust_file .= '$sugarpdf_default["PDF_ENABLE_EZPDF"] = "1";'."\n";
	$cust_file .= "?>\n";
	$file = 'custom/include/Sugarpdf/sugarpdf_default.php';
	if(!file_exists('custom/include/Sugarpdf')) {
		mkdir_recursive('custom/include/Sugarpdf'); // make sure the directory exists
	}

	file_put_contents($file,$cust_file);
}


function rebuild_dashlets(){
    if(is_file('cache/dashlets/dashlets.php')) {
        unlink('cache/dashlets/dashlets.php');
    }

    require_once('include/Dashlets/DashletCacheBuilder.php');
    $dc = new DashletCacheBuilder();
    $dc->buildCache();
}
function rebuild_teams() {
    require_once('modules/Administration/RepairTeams.php');
    process_team_access(false, false,true,'1');
}
function rebuild_roles(){
  $_REQUEST['upgradeWizard'] = true;
  require_once("data/SugarBean.php");
  global $ACLActions, $beanList, $beanFiles;
  include('modules/ACLActions/actiondefs.php');
  include('include/modules.php');
  include("modules/ACL/install_actions.php");
}

function upgrade_LDAP(){
	require_once('modules/Administration/Administration.php');
	$focus = new Administration();
	$focus->retrieveSettings('ldap', true);
	if(isset($focus->settings['ldap_admin_user']) && !empty($focus->settings['ldap_admin_user']))
	{
		$focus->saveSetting('ldap', 'authentication', '1');
	}else if(isset($focus->settings['ldap_admin_user'])) {
		$focus->saveSetting('ldap', 'authentication', '0');
	}
}
function runSqlFiles($origVersion,$destVersion,$queryType,$resumeFromQuery=''){
	global $sugar_config;
	global $unzip_dir;
	global $sugar_config;
	global $sugar_version;
	global $path;
	global $_SESSION;
	$self_dir = "$unzip_dir/scripts";

	// This flag is determined by the preflight check in the installer
	if(!isset($_SESSION['schema_change']) || /* pre-4.5.0 upgrade wizard */
		$_SESSION['schema_change'] == 'sugar') {
		_logThis("Upgrading the database from {$origVersion} to version {$destVersion}", $path);

        $origVersion = implodeVersion($origVersion, 3, 'x');
        $destVersion = implodeVersion($destVersion, 3, 'x');

		$schemaFileName = $origVersion."_to_".$destVersion;

		switch($sugar_config['dbconfig']['db_type']) {
			case 'mysql':
				$schemaFileName = $schemaFileName . '_mysql.sql';
				break;
			case 'mssql':
			    $schemaFileName = $schemaFileName . '_mssql.sql';
				break;
			case 'oci8':
				$schemaFileName = $schemaFileName . '_oracle.sql';
				break;
            case 'ibm_db2':
                $schemaFileName = $schemaFileName . '_ibm_db2.sql';
                break;
		}


		$schemaFile = $_SESSION['unzip_dir'].'/scripts/'.$schemaFileName;
		_logThis("Running SQL file $schemaFile", $path);
		if(is_file($schemaFile)) {
			//$sql_run_result = _run_sql_file($schemaFile);
			ob_start();
			@parseAndExecuteSqlFile($schemaFile,$queryType,$resumeFromQuery);
			ob_end_clean();
        } else if(strcmp($origVersion, $destVersion) == 0){
            _logThis("*** Skipping schema upgrade for point release.", $path);
        } else {
            _logThis("*** ERROR: Schema change script [{$schemaFile}] could not be found!", $path);
        }

	} else {
		_logThis('*** Skipping Schema Change Scripts - Admin opted to run queries manually and should have done so by now.', $path);
	}
}

function clearSugarImages(){
    $skipFiles = array('ACLRoles.gif','close.gif','delete.gif','delete_inline.gif','plus_inline.gif','sugar-yui-sprites-green.png',
             'sugar-yui-sprites-purple.png','sugar-yui-sprites-red.png','sugar-yui-sprites.png','themePreview.png');
    $themePath = clean_path(getcwd() . '/themes/Sugar/images');
    $allFiles = array();
    $allFiles = findSugarImages($themePath, $allFiles, $skipFiles);

    foreach( $allFiles as $the_file ){
        if( is_file( $the_file ) ){
            unlink( $the_file );
            _logThis("Deleted file: $the_file", $path);
        }
    }
}

function findSugarImages($the_dir, $the_array, $skipFiles){
    if(!is_dir($the_dir)) {
        return $the_array;
    }
    $skipFiles = array_flip($skipFiles);
    $d = dir($the_dir);
    while (false !== ($f = $d->read())) {
        if($f == "." || $f == ".." ){
            continue;
        }
        if( is_file( "$the_dir/$f" ) && !isset($skipFiles[$f]) ){
            array_push( $the_array, "$the_dir/$f" );
        }
    }
    return( $the_array );
}

function findCompanyLogo($the_dir, $the_array){
    if(!is_dir($the_dir)) {
        return $the_array;
    }
    $d = dir($the_dir);
    while (false !== ($f = $d->read())) {
        if($f == "." || $f == ".." || $f == 'default'){
            continue;
        }
        if( is_file( "$the_dir/$f/images/company_logo.png" ) ){
            array_push( $the_array, "$the_dir/$f/images/company_logo.png" );
        }
    }
    return( $the_array );
}

function clearCompanyLogo(){
    $themePath = clean_path(getcwd() . '/themes');
    $allFiles = array();
    $allFiles = findCompanyLogo($themePath,$allFiles);

    foreach( $allFiles as $the_file ){
        if( is_file( $the_file ) ){
            unlink( $the_file );
            _logThis("Deleted file: $the_file", $path);
        }
    }
}



function genericFunctions(){
    global $path;
	$server_software = $_SERVER["SERVER_SOFTWARE"];
	if(strpos($server_software,'Microsoft-IIS') !== true)
	{
		///////////////////////////////////////////////////////////////////////////
        ////    FILESYSTEM SECURITY FIX (Bug 9365)
	    _logThis("Applying .htaccess update security fix.", $path);
        include_once("modules/Administration/UpgradeAccess.php");
	}


	///////////////////////////////////////////////////////////////////////////
    ////    CLEAR SUGARLOGIC CACHE
	_logThis("Rebuilding SugarLogic Cache", $path);
	clear_SugarLogic_cache();

	///////////////////////////////////////////////////////////////////////////
	////	PRO/ENT ONLY FINAL TOUCHES

	///////////////////////////////////////////////////////////////////////////
	////	WORKFLOW REPAIR
	_logThis("Repairing WorkFlows", $path);
	do_repair_workflow_conditions();

		///////////////////////////////////////////////////////////////////////////
	////	REBUILD JS LANG
	_logThis("Rebuilding JS Langauages", $path);
	rebuild_js_lang();

	///////////////////////////////////////////////////////////////////////////
	////	REBUILD DASHLETS
	_logThis("Rebuilding Dashlets", $path);
	rebuild_dashlets();
}

function status_post_install_action($action){
	$currProg = post_install_progress();
	$currPostInstallStep = '';
	$postInstallQuery = '';
	if(is_array($currProg) && $currProg != null){
		foreach($currProg as $key=>$val){
			if($key==$action){
				return $val;
			}
		}
	}
	return '';
}



function post_install() {
	global $unzip_dir;
	global $sugar_config;
	global $sugar_version;
	global $path;
	global $_SESSION;
	if(!isset($_SESSION['sqlSkippedQueries'])){
	 	$_SESSION['sqlSkippedQueries'] = array();
	 }
	initialize_session_vars();
	if(!isset($unzip_dir) || $unzip_dir == null){
		$unzip_dir = $_SESSION['unzip_dir'];
	}
	_logThis('Entered post_install function.', $path);
	$self_dir = "$unzip_dir/scripts";

	///////////////////////////////////////////////////////////////////////////
	////	PUT DATABASE UPGRADE SCRIPT HANDLING HERE
    $new_sugar_version = getUpgradeVersion();

    $post_action = status_post_install_action('sql_query');
	if($post_action != null){
	   if($post_action != 'done'){
			//continue from where left in previous run
            runSqlFiles($sugar_version, $new_sugar_version, 'sql_query', $post_action);
		  	$currProg['sql_query'] = 'done';
		  	post_install_progress($currProg,'set');
		}
	 }
	 else{
		//never ran before
        runSqlFiles($sugar_version, $new_sugar_version, 'sql_query');
	  	$currProg['sql_query'] = 'done';
	  	post_install_progress($currProg,'set');
	  }

		genericFunctions();

		// add User field in Role
		include_once("modules/ACLActions/ACLAction.php");
		ACLAction::addActions('Users', 'module');
		upgradeDbAndFileVersion($new_sugar_version);

	// Bug 40044 JennyG - We removed modules/Administration/SaveTabs.php in 6.1. and we need to remove it
	// for upgraded instances.  We need to go through the controller for the Administration module (action_savetabs).
    if(file_exists('modules/Administration/SaveTabs.php'))
        unlink('modules/Administration/SaveTabs.php');
	// End Bug 40044 //////////////////

    // Bug 40119 - JennyG - The location of this file changed since 60RC.  So we need to remove it for
    // old instances that have this file.
    if(file_exists('include/Expressions/Expression/Enum/IndexValueExpression.php'))
        unlink('include/Expressions/Expression/Enum/IndexValueExpression.php');
    // End Bug 40119///////////////////

    // Bug 40382 - JennyG - This file was removed in 6.0.
    if(file_exists('modules/Leads/ConvertLead.php'))
        unlink('modules/Leads/ConvertLead.php');
    // End Bug 40382///////////////////

    // Bug 40458 - JennyG - This file was removed in 6.1. and we need to remove it for upgraded instances.
    if(file_exists('modules/Reports/add_schedule.php'))
        unlink('modules/Reports/add_schedule.php');
    // End Bug 40458///////////////////

    upgradeGroupInboundEmailAccounts();
    upgrade_custom_duration_defs();
    upgrade_panel_tab_defs();


	//add language pack config information to config.php
   	if(is_file('install/lang.config.php')){
   	    global $sugar_config;
		_logThis('install/lang.config.php exists lets import the file/array insto sugar_config/config.php', $path);
		require_once('install/lang.config.php');

		foreach($config['languages'] as $k=>$v){
			$sugar_config['languages'][$k] = $v;
		}

		if( !write_array_to_file( "sugar_config", $sugar_config, "config.php" ) ) {
	        _logThis('*** ERROR: could not write language config information to config.php!!', $path);
	    }else{
			_logThis('sugar_config array in config.php has been updated with language config contents', $path);
		}
    }else{
    	_logThis('*** ERROR: install/lang.config.php was not found and writen to config.php!!', $path);
    }

    //Remove jssource/src_files sub-directories if they still exist
    $jssource_dirs = array('jssource/src_files/include/javascript/ext-2.0',
    					   'jssource/src_files/include/javascript/ext-1.1.1',
    					   'jssource/src_files/include/javascript/yui'
                          );

    foreach($jssource_dirs as $js_dir)
    {
	    if(file_exists($js_dir))
	    {
	       _logThis("Remove {$js_dir} directory");
	       rmdir_recursive($js_dir);
	       _logThis("Finished removing {$js_dir} directory");
	    }
    }

    // move blowfish dir
    if(file_exists($sugar_config['cache_dir']."blowfish") && !file_exists("custom/blowfish")) {
           _logThis('Renaming cache/blowfish');
            rename($sugar_config['cache_dir']."blowfish", "custom/blowfish");
           _logThis('Renamed cache/blowfish to custom/blowfish');
    }

    if (version_compare($sugar_version, '6.7.0', '<'))
    {
        // add class::SugarJobCreateNextTimePeriod job if not there
        $job = new Scheduler();
        $job->retrieve_by_string_fields(array("job" => 'class::SugarJobCreateNextTimePeriod'));
        if(empty($job->id)) {
            $job->name               = translate('LBL_OOTB_CREATE_NEXT_TIMEPERIOD', 'Schedulers');
            $job->job                = 'class::SugarJobCreateNextTimePeriod';
            $job->date_time_start    = '2013-01-01 00:00:01';
            $job->date_time_end      = '2030-12-31 23:59:59';
            $job->job_interval       = '0::23::*::*::*';
            $job->status             = 'Active';
            $job->created_by         = '1';
            $job->modified_user_id   = '1';
            $job->catch_up           = '0';
            $job->save();
        }

        //Check for custom.less file and add if it is missing
        if(!file_exists('styleguide/less/clients/base/custom.less')) {
            sugar_file_put_contents('styleguide/less/clients/base/custom.less', '');
        }
    }

    require_once("install/install_utils.php");
    _logThis("Building sidecar config", $path);
    handleSidecarConfig();

    ///////////////////////////////////////////////////////////////////////////
	////	REBUILD PORTAL CONFIG
    _logThis("Rebuilding portal config", $path);
    handlePortalConfig();

    //bug 57426 was introduced in 654, and it's effects need to be repaired during upgrade.
    //Run the repair script if the original version is greater than 654, but less than 661
    //(bug fix was introduced into 658 and 661 branches)
    if (version_compare($sugar_version, '6.5.4', '>') && version_compare($sugar_version, '6.6.1', '<')) {
        process_email_address_relationships();
    }
}


/**
 * Group Inbound Email accounts should have the allow outbound selection enabled by default.
 *
 */
function upgradeGroupInboundEmailAccounts() {
    global $path;
    _logThis("Begining to upgrade group inbound email accounts", $path);
    $query = "SELECT id, stored_options FROM inbound_email WHERE mailbox_type='pick' AND deleted=0 AND is_personal=0 AND groupfolder_id != ''";
	$result = $GLOBALS['db']->query($query);
	$updateIE = array();
	while ($row = $GLOBALS['db']->fetchByAssoc($result)) {
	   $storedOptionsEncoded = $row['stored_options'];
	   if(empty($storedOptionsEncoded))
	       continue;

	   $storedOptions = unserialize(base64_decode($storedOptionsEncoded));
	   $storedOptions['allow_outbound_group_usage'] = 1;
	   $updateIE[$row['id']] = base64_encode(serialize($storedOptions));
	}
	foreach ($updateIE as $id => $options)
	{
	    _logThis("Upgrading stored options for IE: $id", $path);
	    $updateQuery = "UPDATE inbound_email SET stored_options = '$options' WHERE id = '$id' ";
	    $GLOBALS['db']->query($updateQuery);
	}
	_logThis("Finished upgrade group inbound email accounts", $path);
}

function upgradeOutboundSetting(){
	$query = "select count(*) as count from outbound_email where name='system' and mail_sendtype='sendmail' ";
	$result = $GLOBALS['db']->query($query);
	$row = $GLOBALS['db']->fetchByAssoc($result);

	if($row['count']>0) {
		require_once('modules/Configurator/Configurator.php');
		$configurator = new Configurator();
		$configurator->config['allow_sendmail_outbound'] = true;
		$configurator->handleOverride();
	}
}

/**
 * write_to_modules_ext_php
 * Writes the given module, class and path values to custom/Extensions/application/Include directory
 * for the module
 * @param $class String value of the class name of the module
 * @param $module String value of the name of the module entry
 * @param $path String value of the path of the module class file
 * @param $show Boolean value to determine whether or not entry should be added to moduleList or modInvisList Array
 */
function write_to_modules_ext_php($class, $module, $path, $show=false) {
	include('include/modules.php');
	global $beanList, $beanFiles;
	if(!isset($beanFiles[$class])) {
		$str = "<?php \n //WARNING: The contents of this file are auto-generated\n";

			if(!empty($module) && !empty($class) && !empty($path)){
				$str .= "\$beanList['$module'] = '$class';\n";
				$str .= "\$beanFiles['$class'] = '$path';\n";
				if($show){
					$str .= "\$moduleList[] = '$module';\n";
				}else{
					$str .= "\$modules_exempt_from_availability_check['$module'] = '$module';\n";
					$str .= "\$modInvisList[] = '$module';\n";
				}
			}

		$str.= "\n?>";
		if(!file_exists("custom/Extension/application/Ext/Include")){
			mkdir_recursive("custom/Extension/application/Ext/Include", true);
		}
		$out = sugar_fopen("custom/Extension/application/Ext/Include/{$module}.php", 'w');
		fwrite($out,$str);
		fclose($out);

		require_once('ModuleInstall/ModuleInstaller.php');
  		$moduleInstaller = new ModuleInstaller();
		$moduleInstaller->merge_files('Ext/Include', 'modules.ext.php', '', true);
	}

}

/**
 * Bug #53981 we have to disable duration_hours & duration_minutes fields for studio & remove duration_hours from editviewdefs.php
 * because it will be replaced by duration field
 */
function upgrade_custom_duration_defs()
{
    require_once('include/utils/file_utils.php');
    global $path;

    // fields for replacement
    $fieldsForReplacement = array(
        'duration_hours' => 'duration',
        'duration_minutes' => 'duration',
        'reminder_checked' => 'reminder_time'
    );

    //check to see if custom vardefs exist for calls and/or meetings
    $modsToCheck = array('Meeting', 'Call');

    //first lets make any custom vardefs not show up in studio
    foreach ($modsToCheck as $mods)
    {
        $filestr = 'custom/Extension/modules/' . $mods . 's/Ext/Vardefs/';
        if (file_exists($filestr) && is_dir($filestr))
        {
            //custom vardef directory exists, lets iterate through the files and grab the defs

            $modDir = opendir($filestr);
            while (false !== ($file = readdir($modDir)))
            {
                if ($file == "." || $file == "..")
                {
                    continue;
                }

                $dictionary = array();
                include($filestr . $file);
                $rewrite = false;
                //read the file and see check for duration_minutes or duration_hours
                if (!empty($dictionary[$mods]['fields']['duration_hours']))
                {
                    $dictionary[$mods]['fields']['duration_hours']['studio'] = false;
                    $rewrite = true;
                }

                if (!empty($dictionary[$mods]['fields']['duration_minutes']))
                {
                    $dictionary[$mods]['fields']['duration_minutes']['studio'] = false;
                    $rewrite = true;
                }

                //found the field, rewrite the vardef and overwrite the file
                if ($rewrite)
                {
                    $out =  "<?php\n// created: " . date('Y-m-d H:i:s') . "\n";
                    $iterator = new RecursiveArrayIterator($dictionary);
                    $iterator = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);

                    $keys = array();
                    foreach($iterator as $k => $v)
                    {
                        if (is_array($v))
                        {
                            array_push($keys, $k);
                        }
                        else
                        {
                            $keys = array_slice($keys, 0, $iterator->getDepth());
                            array_push($keys, $k);
                            $out .= "\$dictionary['" . implode("']['", $keys) . "'] = " . var_export_helper($v) . ";\n";
                            array_pop($keys);
                        }
                    }

                    if (!sugar_file_put_contents($filestr . $file, $out, LOCK_EX))
                    {
                        logThis("could not write $mods dictionary to {$filestr}{$file}", $path);
                        return false;
                    }
                }
            }
        }

        //now lets get rid of the duration fields in any custom editview defs
        //the view def will alrady have the input files as hidden, so lets get rid of the duplicates

        //these fields will be replaced inline in the form instead of being added to the end
        $fieldsToReplaceInline = array(
            'duration_hours' => 'duration',
            'reminder_checked' => 'reminder_time'
        );


        foreach ($modsToCheck as $mods)
        {
            $filestr = 'custom/modules/' . $mods . 's/metadata/';
            $files = array(
                'editviewdefs.php' => 'EditView',
                'detailviewdefs.php' => 'DetailView'
            );
            foreach ($files as $file => $key)
            {
            		$fieldPositions = array();
                $rewrite = false;
                $viewdefs = array();
                if (file_exists($filestr . $file))
                {
                    //custom view exists, lets get rid of the dupes
                    include($filestr . $file);
                    $fieldsList = array();
                    $iterator = new RecursiveArrayIterator($viewdefs[$mods.'s'][$key]['panels']);
                    $iterator = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
                    foreach($iterator as $v)
                    {
                        if (!empty($v['name']))
                        {
                            $fieldsList[] = $v['name'];
                        }
                    }

                    //iterate through and unset the duration_fields
                    foreach ($viewdefs[$mods.'s'][$key]['panels'] as $panelName => $panel)
                    {
                        foreach ($panel as $rowctr => $fieldrow)
                        {
                            foreach ($fieldrow as $fieldctr => $fields)
                            {
                                if (!empty($fields['name']))
                                {
                                    //check to see if this is the new field we need to move
                                    if( in_array($fields['name'], $fieldsToReplaceInline)){
                                        //we've located the position of a field that needs to be move to replace another, let's hang on to the position
                                        $fieldPositions['new'][$fields['name']] = array('key'=>$key, 'panelName'=>$panelName,'rowctr'=>$rowctr,'fieldctr'=>$fieldctr);
                                    }
                                    //else, lets check to see if this field is marked for deletion
                                    elseif (array_key_exists($fields['name'], $fieldsForReplacement) && in_array($fieldsForReplacement[$fields['name']], $fieldsList))
                                    {
                                            //mark the position of this field for deletion
                                           $fieldPositions['old'][$fields['name']] = array('key'=>$key, 'panelName'=>$panelName,'rowctr'=>$rowctr,'fieldctr'=>$fieldctr);
                                           $rewrite = true;
                                    }
                                }
                            }
                        }
                    }
                }

                //changes are needed, let's rewrite the custom file
                if ($rewrite)
                {

                    //lets unset and replace fields as needed
                    foreach ($fieldPositions['old'] as $k=>$unsetPos){
                        //check to see if this field is set to be replaced
                        if(!empty($fieldsToReplaceInline[$k])){
                            //check to see if replacement position has been located
                            if(!empty($fieldPositions['new'][$fieldsToReplaceInline[$k]])){
                                //get the value of the replacement position
                                $replPos = $fieldPositions['new'][$fieldsToReplaceInline[$k]];

                                //copy the new field over to the position of the old field
                                $viewdefs[$mods . 's'][$unsetPos['key']]['panels'][$unsetPos['panelName']][$unsetPos['rowctr']][$unsetPos['fieldctr']] =
                                $viewdefs[$mods . 's'][$replPos['key']]['panels'][$replPos['panelName']][$replPos['rowctr']][$replPos['fieldctr']] ;

                                //now lets remove the replacement from it's previous position
                                unset($viewdefs[$mods . 's'][$replPos['key']]['panels'][$replPos['panelName']][$replPos['rowctr']][$replPos['fieldctr']]);
                            }else{
                                //field is set to be removed, but the replacement field was NOT located, don't do anything with it (keep the original field)
                            }
                        }else{
                            //field is not marked for replacment and just needs to be removed, unset the position of the old field
                            unset($viewdefs[$mods . 's'][$unsetPos['key']]['panels'][$unsetPos['panelName']][$unsetPos['rowctr']][$unsetPos['fieldctr']]);
                        }
                    }

                    if (!write_array_to_file("viewdefs['{$mods}s']['" . $key . "']", $viewdefs[$mods.'s'][$key], $filestr . $file, 'w'))
                    {
                        logThis("could not write $mods dictionary to {$filestr}{$file}", $path);
                    }
                }
            }
        }
    }
}

/**
 * Bug #55487 we introduced panel/tab switch feature in panels level of detailvew and editview in 6.5.3
 * when upgrading from older version to 6.5.3 or later, we need to convert the tab setting from old versions
 */
function upgrade_panel_tab_defs()
{
    require_once('include/utils/file_utils.php');
    global $path, $moduleList, $beanList;
    $modsToCheck = array();

    foreach($moduleList as $module)
    {
        if (empty($beanList[$module]))
            continue;
        else
            $modsToCheck[] = $module;
    }

    foreach ($modsToCheck as $mods)
    {
        $EditView = false;
        $DetailView = false;
        $QuickCreate = false;
        $dirs = array(
            'custom/modules/' . $mods . '/metadata/',
            'modules/' . $mods . '/metadata/'
        );
        $files = array(
            'editviewdefs.php' => 'EditView',
            'detailviewdefs.php' => 'DetailView',
            'quickcreatedefs.php' => 'QuickCreate'
        );
        //check custom folder first then default folder
        foreach ($dirs as $dir)
        {
            if (file_exists($dir) && is_dir($dir)) {

                //check to see if custom detailviewdefs or editviewdefs exist
                foreach ($files as $file => $key)
                {
                    $rewrite = false;
                    $viewdefs = array();
                    if (file_exists($dir . $file) && $$key == false)
                    {
                        //view exists, lets do the conversion
                        $$key = true;
                        include($dir . $file);
                        $fieldsList = array();
                        if (isset($viewdefs[$mods][$key]['panels'])) {
                            foreach($viewdefs[$mods][$key]['panels'] as $n=>$v)
                            {
                                if (!empty($n))
                                {
                                    $fieldsList[] = $n;
                                }
                            }
                        }

                        //iterate through and convert the useTabs
                        foreach ($viewdefs[$mods][$key]['templateMeta'] as $name => $value)
                        {
                            if ($name == 'useTabs') {
                                $tabDefs = array();
                                foreach($fieldsList as $panelName) {
                                    $tabDefs[strtoupper($panelName)] = array('newTab'=>$value, 'panelDefault'=>'expanded');
                                }
                                if (!empty($tabDefs) && !isset($viewdefs[$mods][$key]['templateMeta']['tabDefs'])) {
                                    $viewdefs[$mods][$key]['templateMeta']['tabDefs'] = $tabDefs;
                                    $rewrite = true;
                                }
                            }
                        }

                        //when custom module having detailviewdefs or editviewdefs setup as default (no change on these views)
                        //useTabs may not exist in these cases, set newTab to false as that's the default value for custom modules
                        if (!isset($viewdefs[$mods][$key]['templateMeta']['tabDefs'])) {
                            $tabDefs = array();
                            foreach($fieldsList as $panelName) {
                                $tabDefs[strtoupper($panelName)] = array('newTab'=>false, 'panelDefault'=>'expanded');
                            }
                            if (!empty($tabDefs)) {
                                $viewdefs[$mods][$key]['templateMeta']['tabDefs'] = $tabDefs;
                                $rewrite = true;
                            }
                        }

                        //changes are needed, let's rewrite the file
                        if ($rewrite)
                        {
                            if (!write_array_to_file("viewdefs['{$mods}']['" . $key . "']", $viewdefs[$mods][$key], $dir . $file, 'w'))
                            {
                                logThis("could not write $mods dictionary to {$dir}{$file}", $path);
                            }
                        }
                    }
                }
            }
        }
    }
}


/**
 * This function will process email address table and run the repair script to correct the
 * Case sensitive duplicates behavior as outlined in bug 57426
 */
function process_email_address_relationships()
{
    global $path;

    $broken_escaped_emails = array(); // these are emails where the email_address has escaped quotes (\',\") while email_address_caps does not
    $troubled_emails = array(); // these are emails where the email_address has been changed incorrectly, thus not matching with email_address_caps

    // find troubled rows - ones that violate upper(email_address) <> email_address_caps
    $query = "SELECT * FROM email_addresses WHERE deleted=0 AND upper(email_address) <> email_address_caps";
    $result = $GLOBALS['db']->query($query);
    while ($row = $GLOBALS['db']->fetchByAssoc($result,false)) {// we don't want to be converted to html
        // determine if they are the same up to escaping -- something else bad might have happened
        if (strtoupper(stripslashes($row['email_address'])) == $row['email_address_caps']) {
            $broken_escaped_emails[] = $row;
        }
        else {
            $troubled_emails[] = $row;
        }
    }
    _logThis("Found ".count($broken_escaped_emails).' escaped emails. Ignoring for now', $path);
    _logThis("Found ".count($troubled_emails).' emails whose caps field does not match.', $path);

    // determine if troubled emails have a row (matched by email_address_caps), otherwise we need to create one
    /*
     * logic for troubled rows:
     * 1. find (or create) a row in email_addresses to match the current email_address (by email_address_caps)
     * 2. the troubled row gets updated so that the email_address = some form of email_address_caps (likely strtolower)
     * 3. all relationships having a created date > modified date for the troubled row, will get assigned to the new email address
     * 4. all relationships having a created date < modified date will stay with the old email address.
     * 5. all troubled rows will have upper(email_address) = email_address_caps
     */
    foreach($troubled_emails as $row) {
        $new_email_address = $row['email_address']; // the changed email address is in this field
        $old_email_address = $row['email_address_caps']; // the old email address is in this field
        $old_uuid = $row['id'];
        $time_changed = $row['date_modified']; // the point assumed to be when the email address incorrectly was changed.
        //_logThis('Inconsistent row has address '.$new_email_address.' and caps field '.$old_email_address, $path);

        // attempt to find a better row for the new email address
        $find_new_rows_qry = $GLOBALS['db']->query("SELECT * from email_addresses WHERE email_address_caps = '".$GLOBALS['db']->quote(strtoupper($new_email_address))."' AND deleted=0");
        $first_new_row = $GLOBALS['db']->fetchByAssoc($find_new_rows_qry,false);
        if ($first_new_row) {
            // this will be our new id
            $new_uuid = $first_new_row['id'];
            //_logThis('Found a matching row of id '.$new_uuid.' for email address '.$new_email_address, $path);
        }
        else {
            // create new uuid
            //_logThis('No matching row for new email address '.$new_email_address.', creating one', $path);
            $new_uuid = create_guid();
            $noMatchQuery = "INSERT INTO email_addresses VALUES ('".$new_uuid."', '".$new_email_address."', '".$GLOBALS['db']->quote(strtoupper($new_email_address))."', '".
                     $row['invalid_email']."', '".$row['opt_out']."', '".$time_changed."', ".$GLOBALS['db']->now().", '0')";
            $GLOBALS['db']->query($noMatchQuery);
            //_logThis("Added as $new_uuid, query was ".$noMatchQuery, $path);
        }

        fix_email_address_relationships($old_uuid, $new_uuid, $time_changed);

        //_logThis('Restoring old row to proper email address', $path);
        $restore_old_row_qry = "UPDATE email_addresses SET email_address = '".$GLOBALS['db']->quote(strtolower($old_email_address))."'  where email_address_caps = '".$GLOBALS['db']->quote($old_email_address)."' ";
        $GLOBALS['db']->query($restore_old_row_qry);
    }

    // at this point handle duplicate emails
    /*
     * logic for a duplicate row:
     * 1. match duplicate rows by email_address_caps (handling the troubled rows should make this field reliable)
     * 2. for each matching email_address, chose one of the ids to be the canonical id.  this id/row will be used
     *    for all relationships for that email address.
     *
     */
    _logThis("------------------------------------------------------------------", $path);
    _logThis('Determining which email addresses are duplicated within the system', $path);
    $dupe_email_addresses = array();

    $dupe_query = "SELECT email_address_caps, count(*) AS email_count FROM email_addresses WHERE deleted=0 GROUP BY email_address_caps HAVING COUNT(*) > 1";
    $dupe_results = $GLOBALS['db']->query($dupe_query);
    while ($row = $GLOBALS['db']->fetchByAssoc($dupe_results,false)) {
        $email_address_caps = $row['email_address_caps'];
        //_logThis("Found ".$email_address_caps.' with rows='.$row['email_count'], $path);

        $ids = array();
        $opt_out = '0'; // by default don't opt out, unless one of the dupes has an opt-out flag.
        // we want to get id's of all duplicate rows so we can handle relationships
        $find_matching_rows = "SELECT id, opt_out FROM email_addresses WHERE email_address_caps = '".$GLOBALS['db']->quote($email_address_caps)."' AND deleted=0";
        $matchingRowResult = $GLOBALS['db']->query($find_matching_rows);
        while ($matching_email_row = $GLOBALS['db']->fetchByAssoc($matchingRowResult,false)) {
            $matching_email_id = $matching_email_row['id'];
            //_logThis("Found duplicate with id=".$matching_email_id, $path);
            $ids[] = $matching_email_id;
            if (intval($matching_email_row['opt_out']) == 1) {
                $opt_out = 1;
                //_logThis("Flagged as opted out.", $path);
            }
        }

        $dupe_email_addresses[$email_address_caps] = array('ids' =>$ids, 'opt_out' =>$opt_out);
    }

    _logThis('Repairing duplicate email address relationships and marking duplicates as deleted', $path);
    foreach ($dupe_email_addresses as $email_address_caps => $data) {
        //_logThis('Working on '.$email_address_caps, $path);
        $ids = $data['ids'];

        // make the first id the canonical one.
        $canonical_id = array_shift($ids);
        //_logThis("Canonical ID is now: ".$canonical_id, $path);

        if ($data['opt_out'] == 1) {
            //_logThis("Marking email as opted out due to one of the duplicates being flagged.", $path);
            $GLOBALS['db']->query("UPDATE email_addresses SET opt_out=1 WHERE id='$canonical_id'");
        }
        foreach($ids as $id) {
            //_logThis("Duplicate ID: ".$id, $path);
            fix_email_address_relationships($id, $canonical_id);
            //_logThis("Marking as deleted", $path);
            $GLOBALS['db']->query("UPDATE email_addresses SET deleted=1 WHERE id='$id'");
        }
    }
}

/**
 * Alters email_address relationship tables from the old uuid to the new uuid for 'email_addr_bean_rel' table.  This is relationship
 * linking the email address and the person bean user/lead/contact.
 * This script optionally updates the 'emails_email_addr_rel' using the $time_changed parameter as a flag.  This is the relationship
 * linking the email messages to the email address.
 * @param $old_uuid - this id is to be changed
 * @param $new_uuid - change to this id
 * @param null $time_changed (if this parameter is not set, run the query that updates 'emails_email_addr_rel' relationship table)
 */
function fix_email_address_relationships($old_uuid, $new_uuid, $time_changed=null)
{
    global $path;
    // if we have time query, we need joins
    //Relates all emails currently related to duplicates of the current email address to the first id in the array of duplicates
    if ($time_changed == null) {
        $stm_emails_email_addr = "UPDATE emails_email_addr_rel SET email_address_id='$new_uuid' WHERE email_address_id='$old_uuid'";
        _logThis($stm_emails_email_addr, $path);
        $rs = $GLOBALS['db']->query($stm_emails_email_addr);
        //_logThis(' Number of row(s) changed = '.$GLOBALS['db']->getAffectedRowCount($rs), $path);
    }

    //Relates all beans(People) currently related to duplicates of the current email address to the first id in the array of duplicates
    // it is highly unlikely that the records using this email address want the old one, so avoid making a bad guess.
    $stm_email_addr_bean = "UPDATE email_addr_bean_rel SET email_address_id='$new_uuid' WHERE email_address_id='$old_uuid'";

    _logThis($stm_email_addr_bean, $path);
    $rs = $GLOBALS['db']->query($stm_email_addr_bean);
    //_logThis(' Number of row(s) changed = '.$GLOBALS['db']->getAffectedRowCount($rs), $path);
}

