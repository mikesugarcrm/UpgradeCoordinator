<?php

if (!defined('sugarEntry')) define('sugarEntry', true);

// Run the repair and rebuild to install the custom fields that were populated into fields_meta_data table
require_once('include/entryPoint.php');

$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) != 'cli') {
    sugar_die("This script is CLI only.");
}

$GLOBALS['current_user'] = BeanFactory::getBean('Users', '1');

require('modules/Administration/language/en_us.lang.php');

SugarAutoLoader::requireWithCustom('modules/Administration/QuickRepairAndRebuild.php');
$repairClass = SugarAutoLoader::customClass('RepairAndClear');

$runSQL = true;
if (!empty($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'false') {
    $runSQL = false;
}

$showSQL = false;
if (!empty($_SERVER['argv'][2]) && $_SERVER['argv'][2] == 'true') {
    $showSQL = true;
}

$modules = array($mod_strings['LBL_ALL_MODULES']);
if (!empty($_SERVER['argv'][3]) && $modulesToRepair = array_filter(array_map('trim', explode(',', $_SERVER['argv'][3])))) {
    $modules = $modulesToRepair;
}

$randc = new $repairClass();

$randc->repairAndClearAll(array('clearAll'), $modules, $runSQL, $showSQL);
$GLOBALS['db']->query("delete from metadata_cache");
