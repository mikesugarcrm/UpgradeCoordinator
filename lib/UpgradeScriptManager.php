<?php

namespace Sugarcrm\Sugarcrm\UpgradeCoordinator\lib;
use Sugarcrm\Sugarcrm\UpgradeCoordinator\lib\UpgradeScript as UpgradeScript;

/**
 * Class UpgradeScriptManager
 * @package Sugarcrm\Sugarcrm\UpgradeCoordinator\lib
 *
 * This class will coordinate running custom upgrade scripts during the execution of the
 * UpgradeCoordinator.
 *
 * The coordinator will package this class, and the UpgradeScript abstract class, and the actual
 * upgrade scripts into a zip file and copy that file into the sugar instance directory. The
 * directory structure of the zip file will be:
 *
 *  executeUpgradeScripts.php
 *  upgrade/custom/
 *      lib/
 *          UpgradeScriptManager.php
 *          UpgradeScript.php
 *      pre/
 *          SomeScript1.php
 *          SomeScript2.php
 *          ...
 *      post/
 *          SomeScript3.php
 *          SomeScript4.php
 *          ...
 *
 * executeUpgradeScripts.php take a stage ("pre" or "post") as a command line argument,
 * and will call this class, which will collect the scripts  and run them in order of their
 * priority property.
 *
 * The scripts must all extend the UpgradeScript abstract class, so they will define a priority
 * and a getPriority() method.
 *
 * The scripts must also be given this namespace:
 *      Sugarcrm\Sugarcrm\UpgradeCoordinator\CustomUpgradeScripts
 */
class UpgradeScriptManager
{
    public $stage = '';

    public $version = '';

    public $upgradeScriptsDir = 'upgrade/custom/';

    public $scriptFileNames = array();

    public $scriptObjects = array();

    public $scriptsLogFile = '';

    public $previouslyRunScripts = array();


    public function __construct($argv)
    {
        if (isset($argv[1])) {
            $this->version = $argv[1];
        }

        if (isset($argv[2])) {
            if ($argv[2] == 'pre' || $argv[2] == 'post') {
                $this->stage = $argv[2];
            }
        }

        $this->scriptsLogFile = $this->upgradeScriptsDir . "/scripts_log.{$this->version}.{$this->stage}.txt";
    }


    public function executeScripts()
    {
        try {
            $this->confirmStage();
            $this->collectScripts();
            $this->instantiateScriptObjects();
            $this->sortScriptObjects();
            foreach ($this->scriptObjects as $upgradeScript) {
                $upgradeScript->execute();
                $this->logScript($upgradeScript);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }


    public function collectScripts()
    {
        $scriptDir = $this->getUpgradeScriptsDirPath();
        if (!$this->fileExists($scriptDir) || !$this->isDir($scriptDir)) {
            throw new \Exception(__METHOD__ . " '$scriptDir' does not exist or isn't a directory.", 2);
        }

        $this->scriptFileNames = $this->scanDir($scriptDir);
    }


    public function instantiateScriptObjects()
    {
        $this->readLogFile();
        foreach ($this->scriptFileNames as $fileName) {
            $path = $this->getUpgradeScriptsDirPath() . '/' . $fileName;
            $this->requireScriptFile($path);
            $className = pathinfo($fileName, PATHINFO_FILENAME);
            $namespace = 'Sugarcrm\Sugarcrm\UpgradeCoordinator\CustomUpgradeScripts';
            $fqcn = "$namespace\\$className";

            if (!$this->classExists($fqcn)) {
                throw new \Exception("$fqcn is not a valid class name", 3);
            }

            $object = $this->instantiateScriptObject($fqcn);

            if (!$this->isUpgradeScriptObject($object, $fileName)) {
                throw new \Exception("$fileName must extend UpgradeScript", 4);
            }

            if ($this->scriptHasBeenRunBefore($fqcn)) {
                // silently skip scripts that have already been run.
                continue;
            }

            $this->scriptObjects[] = $object;
        }
    }


    public function sortScriptObjects()
    {
        usort($this->scriptObjects, function (UpgradeScript $a, UpgradeScript $b) {
            $priorityDiff = $a->getPriority() - $b->getPriority();
            if ($priorityDiff > 0) {
                return 1;
            } else if ($priorityDiff < 0) {
                return -1;
            } else {
                return 0;
            }
        });
    }


    public function getUpgradeScriptsDirPath()
    {
        return "{$this->upgradeScriptsDir}/{$this->stage}";
    }


    public function readLogFile()
    {
        if ($this->fileExists($this->scriptsLogFile)) {
            $this->previouslyRunScripts = $this->getFileContentsAsArray($this->scriptsLogFile);
        }
    }


    public function confirmStage()
    {
        if (empty($this->stage)) {
            throw new \Exception(__METHOD__ . " stage is empty or not valid", 1);
        }
        return true;
    }


    public function scriptHasBeenRunBefore($fqcn)
    {
        if (!empty($this->previouslyRunScripts) && in_array($fqcn, $this->previouslyRunScripts)) {
            return true;
        }
        return false;
    }


    /**
     * @codeCoverageIgnore
     * @param $filePath
     * @return array
     */
    public function getFileContentsAsArray($filePath)
    {
        return file($filePath);
    }

    /**
     * @codeCoverageIgnore
     * @param \Sugarcrm\Sugarcrm\UpgradeCoordinator\lib\UpgradeScript $script
     */
    public function logScript(UpgradeScript $script)
    {
        file_put_contents($this->scriptsLogFile, get_class($script) . "\n", FILE_APPEND);
    }


    /**
     * @codeCoverageIgnore
     * @param $className
     * @return bool
     */
    public function classExists($className)
    {
        return class_exists($className);
    }

    /**
     * @codeCoverageIgnore
     * @param $path
     * @return bool
     */
    public function fileExists($path)
    {
        return file_exists($path);
    }


    /**
     * @codeCoverageIgnore
     * @param $path
     */
    public function requireScriptFile($path)
    {
        require_once($path);
    }


    /**
     * @codeCoverageIgnore
     * @param $fullyQualifiedClassName
     * @return mixed
     */
    public function instantiateScriptObject($fullyQualifiedClassName)
    {
        return new $fullyQualifiedClassName($this);
    }


    /**
     * @codeCoverageIgnore
     * @param string $path - directory path
     * @return bool
     */
    public function isDir($path)
    {
        return is_dir($path);
    }


    /**
     * Returns an array of files from a given directory path, without the '.' and '..' relative directories.
     *
     * @codeCoverageIgnore
     * @param string $path - the path of the directory you want the contents of.
     * @return array - a list of every file/directory in $path.
     */
    public function scanDir($path)
    {
        if (!$this->isDir($path)) {
            return array();
        }
        return array_diff(scandir($path), ['.', '..']);
    }


    /**
     * @codeCoverageIgnore
     * @param $object
     * @return bool
     */
    public function isUpgradeScriptObject($object)
    {
        if (!is_a($object, UpgradeScript::class)) {
            return false;
        }
        return true;
    }
}