<?php
namespace Sugarcrm\Sugarcrm\UpgradeCoordinator\lib;

/**
 * Class UpgradeScript
 * @package Sugarcrm\Sugarcrm\UpgradeCoordinator
 *
 * This is a base class for custom upgrade scripts that the coordinator will run either before ('pre') or after
 * ('post') running the silent upgrader for a given version.
 *
 * Files that extend this class must be named for the class they define.
 *
 * Classes that extend this class must use a namespace that matches their directory structure, which must include the
 * version and stage (pre or post).
 *
 * The namespace they use should be:
 * Sugarcrm\Sugarcrm\UpgradeCoordinator\CustomUpgradeScripts
 *
 */

/**
 * Class UpgradeScript
 * @codeCoverageIgnore
 * @package Sugarcrm\Sugarcrm\UpgradeCoordinator\lib
 */
abstract class UpgradeScript
{
    /**
     * @var int - priority is used to determine the run order for scripts.
     * Lowest priority runs first.
     * If you don't set this value to something unique, it will run in the order that scandir() finds it (probably alphabetical).
     */
    protected $priority = 100;

    /** @var null|UpgradeScriptManager - a reference to the upgrade script manager in case you need it */
    public $manager = null;

    /**
     * UpgradeScript constructor.
     */
    public function __construct(UpgradeScriptManager $manager)
    {
        // Classes which extend this class may overwrite this method.
        $this->manager = $manager;
    }

    public function execute()
    {
        // Classes which extend this class must overwrite this method.
    }


    /**
     * Returns the priority for this script. To make one script run before another script, give it a lower priority
     * than the other script when you define its class.
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }
}