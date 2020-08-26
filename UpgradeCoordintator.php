<?php
namespace Sugarcrm\Sugarcrm\UpgradeCoordinator;
require_once('lib/UpgradeCoordinator.php');
use Sugarcrm\Sugarcrm\UpgradeCoordinator\lib\UpgradeCoordinator as UpgradeCoordinator;
set_time_limit(0);
define('sugarEntry', true);
ini_set('error_reporting', E_ALL & ~E_DEPRECATED & ~E_STRICT);
$uc = new UpgradeCoordinator();
$uc->run($argv);