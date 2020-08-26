<?php
if(!defined('sugarEntry'))define('sugarEntry', true);
define('ENTRY_POINT_TYPE', 'gui');
require_once("include/EntryPoint.php");
require_once("upgrade/custom/lib/UpgradeScript.php");
require_once("upgrade/custom/lib/UpgradeScriptManager.php");

$usm = new \Sugarcrm\Sugarcrm\UpgradeCoordinator\lib\UpgradeScriptManager($argv);

try {
    $usm->executeScripts();
} catch (Exception $e) {
    print($e->getMessage());
    exit($e->getCode());
}

print("Upgrade Scripts Complete");
exit(0);
