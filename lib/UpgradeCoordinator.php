<?php
namespace Sugarcrm\Sugarcrm\UpgradeCoordinator\lib;


class UpgradeCoordinator
{
    /* @var string - Sugar instance path - must be absolute path. Include trailing slash (/). */
    public $instancePath = '/var/www/html/sugarcrm/ult/';

    /* @var string - THE administrative user name */
    public $adminUser = 'admin';

    /* @var string - log file path. Log will be written to local directory. */
    public $logFile = 'logs/coordinator.log';

    /* @var string - qrr file will be copied from local directory into instance directory. */
    public $qrrFileName = 'quickRepairAndRebuild.php';

    /* @var string - use the default alias for php - this can be overridden as the 2nd arg to this file when called. */
    public $phpPath = 'php';

    /* @var string - upgrades directory name. */
    public $upgradesDir = 'upgrades';

    /* @var array - list of upgrades in version number order, from earliest to latest. */
    public $upgrades = array();

    /* @var string - upgrade path files directory name. */
    public $upgradePatchFilesDir = 'files';

    /* @var string - custom upgrade scripts directory name. */
    public $upgradeScriptsDir = 'scripts';

    /* @var string - silent upgrader directory name. */
    public $silentUpgraderDir = 'silent_upgrader';

    /* @var string - core upgrade package directory name. */
    public $upgradeDir = 'upgrade';

    /* @var string - current working directory. */
    public $cwd = '';

    /* @var string - minimum version required of PHP. */
    public $phpVersionRequired = '7.1.0';

    /* @var array - zip files we need to delete when we're done with them */
    public $zipFilesToDelete = array();

    /* @var string - current version of sugar */
    public $currentSugarVersion = '';

    /* @var string - prefix for core upgrade package name */
    public $coreUpgradePrefix = 'SugarEnt-Upgrade-';


    public function __construct()
    {
    }


    /**
     * Sets everything up, then runs pre-flight checks. If pre-flight checks pass, runs each upgrade step,
     * then deploys the final upgrade patches and runs final upgrade scripts. Finally, it cleans up any zip
     * files we might have forgotten to delete.
     *
     * @param $argv
     * @return bool - true if everything ran as expected, false if there was a problem.
     */
    public function run($argv)
    {
        $upgradeSuccessful = false;

        if (!$this->init($argv)) {
            $this->log("Init failed - aborting run");
            return false;
        }

        // remove the tests directory if present.
        $this->deleteTestsDirectory();

        if (!$this->preflightChecks()) {
            $this->log("Preflight checks failed - aborting run");
            return false;
        }

        $this->log("Starting upgrade steps");
        foreach ($this->upgrades as $index => $version) {
            $upgradeSuccessful = $this->executeUpgrade($version);
            $this->cleanUpZipFiles();
            if (!$upgradeSuccessful) {
                $this->log("Upgrade step $index targetting version $version failed!");
                break;
            }
        }

        if ($upgradeSuccessful) {
            $this->log("All upgrade steps complete");
            return true;
        } else {
            $this->log("Upgrade Failed");
            return false;
        }
    }


    /**
     * Perform basic setup - create the logs directory, find the upgrades to run, and parse command line args.
     * Return false if anything goes wrong.
     * @param $argv
     * @return bool
     */
    public function init($argv)
    {
        // create the log directory if it doesn't exist
        if (!$this->isDir('./logs')) {
            if (!$this->mkDir('./logs')) {
                $this->print("Cannot create the 'logs' directory.\n");
                return false;
            }
        }

        $this->parseArgs($argv);
        $this->cwd = getcwd();
        $this->upgradesDir = $this->cwd . "/{$this->upgradesDir}";
        $this->logFile = $this->cwd . "/{$this->logFile}";
        $this->currentSugarVersion = $this->getCurrentSugarVersion();


        if (!$this->collectUpgrades()) {
            return false;
        }

        return true;
    }


    /**
     * scans the upgrades directories puts them in order of version number. This can be a little dicey because version
     * number order is different from alpha-numeric order. I.e. 7.9.1.2 is an earlier version than 7.9.1.14, but .14
     * would come first in alpha-numeric order.
     *
     * So use php's built-in version_compare() method to sort the upgrades by version number.
     *
     * @return array - an array of version numbers from the upgrades directory in order from earliest to latest.
     */
    public function collectUpgrades()
    {
        $upgrades = $this->scanDir($this->upgradesDir);

        if (empty($upgrades)) {
            $this->log("There are no upgrades in {$this->upgradesDir} - you should check that path.");
        }

        foreach ($upgrades as $index => $upgradeVersion) {
            if ($upgradeVersion != 'final') {
                $this->upgrades[] = $upgradeVersion;
            }
        }

        usort($this->upgrades, 'version_compare');
        $this->upgrades[] = 'final';
        return $this->upgrades;
    }


    /**
     * Checks that everything we need to have in place before starting the upgrade process is in place and correct.
     *
     * @return bool - false if there are any problems.
     */
    public function preflightChecks()
    {
        // is this directory readable and writable?
        if (!$this->currentDirIsAccessible()) {
            $this->log("Cannot access current working directory.");
            return false;
        }

        // is the instance directory readable and writable?
        if (!$this->instanceDirIsAccessible()) {
            $this->log("Cannot access instance directory");
            return false;
        }

        // does the instance directory look like Sugar lives there?
        if (!$this->checkSugarInstance()) {
            $this->log("Could not find sugar_version.php in {$this->instancePath} - is this really the sugar instance directory?");
            return false;
        }

        if (!$this->checkZipAndUnzip()) {
            $this->log("Zip and/or Unzip are not available.");
            return false;
        }

        if (!$this->checkPHPPath()) {
            $this->log("Invalid php path: {$this->phpPath} is not a file.");
            return false;
        }

        if (!$this->checkPHPVersion()) {
            $this->log("You must perform this upgrade with PHP version {$this->phpVersionRequired} or higher");
            return false;
        }

        if (!$this->checkUpgradePackages()) {
            return false;
        }

        return true;
    }


    public function executeUpgrade($version)
    {
        $this->zipFilesToDelete = array();

        // if the current version of sugar is higher than this upgrade version, skip this upgrade step
        // because we've already done it.
        if (!$this->nextUpgradeVersionIsHigherThanCurrent($version)) {
            $this->log("Our current sugar version is {$this->currentSugarVersion}, so skipping the upgrade to $version");
            return true;
        }


        // delete any files you need to delete.
        if (!$this->deleteFilesFromList($version)) {
            $this->log("Could not delete files for $version - aborting!");
            return false;
        }

        // deploy pre-upgrade patches for this version.
        if (!$this->deployPatchFiles($version, 'pre')) {
            $this->log("Could not deploy pre-patch files - aborting");
            return false;
        }

        // run all pre-upgrade scripts for this version.
        if (!$this->runCustomUpgradeScripts($version, 'pre')) {
            $this->log("Pre-Upgrade script failed for $version - aborting.");
            return false;
        }

        // clear the cache directory.
        if ($this->clearCacheDir() === false) {
            $this->log("Cannot clear cache directory - aborting!");
            return false;
        }

        // run QRR
        if (!$this->runQRR()) {
            $this->log("QRR failed during PRE step - aborting");
            return false;
        }

        // execute silent upgrade
        if (!$this->executeSilentUpgrade($version)) {
            $this->log("Silent Upgrade for $version failed!");
            return false;
        }

        // deploy pre-upgrade patches for this version.
        if (!$this->deployPatchFiles($version, 'post')) {
            $this->log("Could not deploy post-patch files - aborting");
            return false;
        }
        // run all post-upgrade script for this version
        if (!$this->runCustomUpgradeScripts($version, 'post')) {
            $this->log("Post-Upgrade script failed for $version - aborting.");
            return false;
        }

        // clear cache directory
        if ($this->clearCacheDir() === false) {
            $this->log("Cannot clear cache directory - aborting!");
            return false;
        }

        // run QRR
        if (!$this->runQRR()) {
            $this->log("QRR failed after POST step - aborting");
            return false;
        }

        // keep track of our current sugar version after the upgrade is complete.
        $this->currentSugarVersion = $this->getCurrentSugarVersion();

        $this->log("Upgrade to $version complete");
        return true;
    }


    /**
     * Checks each upgrade version (except for final) to make sure that the silent_upgrader and upgrade sub directories
     * are not empty. The coordinator expects to find the appropriate files in those locations, so if they're empty
     * we cannot proceed.
     *
     * We don't check the final upgrade version, because that will only be patch files and custom upgrade scripts
     * which will be executed after the last upgrade version is executed. Patch files and upgrade scripts are
     * optional for any upgrade step, so we don't insist they be present or populated.
     *
     * @return bool - true if the upgrade and silent_upgrader directories have contents for each upgrade version.
     */
    public function checkUpgradePackages()
    {
        $allPackagesOK = true;

        if (empty($this->upgrades)) {
            $this->log("no upgrades are stored - nothing to do. Did init() fail?");
            return false;
        }

        foreach ($this->upgrades as $index => $version) {
            if ($version == 'final') {
                continue;
            }

            $components = array(
                'silent_upgrader' => $this->getSilentUpgradePath($version),
                'upgrade' => $this->getUpgradePackagePath($version),
            );

            foreach ($components as $name => $dir) {
                if (empty($dir)) {
                    $allPackagesOK = false;
                    $this->log("Cannot find directory for component $name - cannot run upgrade");
                }

                if (empty($this->scanDir($dir))) {
                    $allPackagesOK = false;
                    $this->log("$name directory for $version ($dir) is empty - cannot run upgrade");
                }
            }
        }

        if (!$allPackagesOK) {
            $this->log("Missing upgrade packages and/or silent upgrader - upgrade check failed.");
            return false;
        }

        $this->log("All upgrade packages look ok");
        return true;
    }


    /**
     * Zip up the patch files for the given version and stage, and then unzip that zip file
     * into the instance directory.
     *
     * @param string $version - your upgrade target version
     * @param string $stage - 'pre' or 'post'
     * @return bool|string - The zip file name if the zip file was created and deployed successfully.
     *  boolean true if the directory to zip up was empty, boolean false if the zip or unzip failed.
     */
    public function deployPatchFiles($version, $stage)
    {
        return $this->buildAndDeployZipFile($version, $stage, 'files');
    }


    /**
     * Zips up a directory and unzips that zip file into the instance directory. The path to
     * the directory to zip up is based on the version, stage (pre or post) and the component
     * (files, scripts, upgrade, etc.)
     *
     * @param string $version - your upgrade target version
     * @param string $stage - 'pre' or 'post'
     * @param $component - a subdirectory in upgrades/<$version>
     * @return bool|string - The zip file name if the zip file was created and deployed successfully.
     *  boolean true if the directory to zip up was empty, boolean false if the zip or unzip failed.
     */
    public function buildAndDeployZipFile($version, $stage, $component)
    {
        $patchFilesDirPath = $this->getUpgradeComponentPath($version, $component, $stage);
        // will be false if $patchFilesDirPath is not valid.
        if (empty($patchFilesDirPath)) {
            $this->log("Cannot find directory for $stage stage component $component in version $version, so no zip file created.");
            return false;
        }

        if (empty($this->scanDir($patchFilesDirPath))) {
            $this->log("Patch files for $version $stage ($patchFilesDirPath) is empty - skipping");
            return true;
        }

        $zipFilePath = $this->zipDirectory($patchFilesDirPath, "{$component}.{$version}.{$stage}.zip");

        if (!empty($zipFilePath)) {
            return $this->unzip($zipFilePath, $this->instancePath);
        }
        $this->log("Could not build a zip file for the $stage stage of component $component in version $version");
        return false;
    }


    /**
     * Zip up a directory and write the zip file to the upgrade coordinator's root directory.
     *
     * @param string $path - the directory path to zip up.
     * @param string $zipFileName - the name to use for the zip file.
     * @return bool|string - the file path for the zipped file if it was created successfully. False if there was a problem.
     */
    public function zipDirectory($path, $zipFileName)
    {
        if (empty($path)) {
            $this->log("Cannot zip an empty path!");
            return false;
        }

        if (empty($zipFileName)) {
            $this->log("You must specify a zip file path name");
            return false;
        }

        $zipFilePath = "{$this->cwd}/{$zipFileName}";
        $result = $this->runCmd("cd $path; zip --exclude .placeholder -q -r $zipFilePath *");
        if ($result[1] !== 0) {
            $this->log("Failed to zip $path");
            $this->log($result[0]);
            return false;
        }
        $this->log("Zipped $path to $zipFilePath");
        $this->zipFilesToDelete[] = $zipFilePath;
        return $zipFilePath;
    }


    /**
     * Builds a path to a given upgrade component (files, scripts, upgrade, etc.). If the version or component
     * does not exist, an empty string is returned.
     *
     * @param string $version - your upgrade target version
     * @param string $stage - 'pre' or 'post' - optional here.
     * @param $component - a subdirectory in upgrades/<$version>
     * @return string - the path to the specified component
     */
    public function getUpgradeComponentPath($version, $component, $stage = '')
    {
        $componentPath = "{$this->upgradesDir}/{$version}/{$component}";

        if (!empty($stage)) {
            $componentPath .= "/{$stage}";
        }

        if (!$this->fileExists($componentPath)) {
            $this->log("Path for $version $component does not exist - $componentPath");
            return "";
        }

        if (!$this->isDir($componentPath)) {
            $this->log("Path for $version $component is not a directory - $componentPath");
            return "";
        }

        return $componentPath;
    }


    /**
     * Copies the qrr file ($this->qrrFileName) into the instance directory if it's not already there, and runs it.
     *
     * @return bool|mixed - false if the qrr script can't be copied, or the exit code it returned when ran.
     */
    public function runQRR()
    {
        $this->log("Running QRR");
        $qrrFilePath = $this->instancePath . $this->qrrFileName;
        if (!$this->fileExists($qrrFilePath)) {
            $installOK = $this->copyFile("lib/{$this->qrrFileName}", $qrrFilePath);
            if (!$installOK) {
                $this->log("Cannot install QRR File.");
                return false;
            }
        }

        $result = $this->runCmd("cd {$this->instancePath}; {$this->phpPath} -f {$this->qrrFileName}");
        $this->deleteFile($qrrFilePath);
        return ($result[1] === 0);
    }

    /**
     * Selectively deletes files from the cache directory.
     *
     * clearing the whole cache directory can break QRR. But not deleting
     * the class_map file can give the autoloader a stale cache. So we delete
     * cache files selectively.
     *
     * QRR should also do this, but being thorough doesn't hurt.
     *
     * @return bool - false only if the cache directory cannot be found.
     */
    public function clearCacheDir()
    {
        $cachePath = "{$this->instancePath}cache";
        $this->log("Clearing Cache Directory $cachePath");

        if (!$this->isDir($cachePath)) {
            $this->log("$cachePath is not a directory");
            return false;
        }

        $cacheFiles = array(
            'class_map.php',
            'javascript/base',
            'include/javascript',
        );

        foreach ($cacheFiles as $fileName) {
            $cacheFilePath = "{$cachePath}/{$fileName}";
            if ($this->isDir($cacheFilePath)) {
                if (!$this->clearDirectory($cacheFilePath)) {
                    $this->log("Could not clear cache directory $cacheFilePath");
                }
            }

            if ($this->isFile($cacheFilePath)) {
                if (!$this->deleteFile($cacheFilePath)) {
                    $this->log("Could not delete cache file $cacheFilePath");
                }
            }
        }
        return true;
    }


    /**
     * Recursively deletes all the files in a directory, but doesn't delete the directory itself.
     *
     * @param string $dirPath
     * @return bool
     */
    public function clearDirectory($dirPath) {
        $this->log("Deleteing Directory $dirPath");

        if (empty($dirPath) || $dirPath == '/') {
            $this->log("You cannot delete the root directory!");
            return false;
        }

        if ($this->fileExists($dirPath) && $this->isDir($dirPath)) {
            list($output, $exitCode) = $this->runCmd("rm -rf $dirPath/*");
            return $exitCode === 0;
        }
        $this->log("$dirPath is not a directory or doesn't exist");
        return false;
    }


    /**
     * deletes the tests directory - JPM won't have this directory, we don't deliver it. But sugar's internal instances
     * may have it and it causes healthcheck failures.
     */
    public function deleteTestsDirectory()
    {
        $this->clearDirectory("{$this->instancePath}tests");
    }


    /**
     * Executes a shell command and returns the results. The return value is an array of the command's output and the
     * command's exit code.
     *
     * @codeCoverageIgnore
     * @param string $cmd
     * @param string $stdin
     * @return array - the command's output and exit code.
     */
    public function runCmd($cmd, $stdin="")
    {
        if (is_array($cmd))
        {
            foreach ($cmd as $aCommand)
            {
                $results = $this->runCmd($aCommand, $stdin);
            }
            return $results;
        }

        $this->log("running $cmd");

        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
            2 => array("pipe", "w") // stderr is a file to write to
        );
        $process = proc_open($cmd, $descriptorspec, $pipes);

        if (is_resource($process))
        {
            if (!empty($stdin))
            {
                fwrite($pipes[0], $stdin);
            }
            fclose($pipes[0]);

            $output = trim(stream_get_contents($pipes[1])) . "\n" . trim(stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnValue = proc_close($process);
            $this->log("command returned: $returnValue\n" . trim($output));

            return array($output, $returnValue);
        } else {
            $this->log("Could not run command $cmd");
            return array();
        }
    }


    /**
     * Returns true if this specified utility is present.
     *
     * @param $utilName
     * @return bool
     */
    public function checkForInstalledUtility($utilName)
    {
        list($output, $exitCode) = $this->runCmd("which $utilName");
        if ($exitCode === 0) {
            return true;
        }
        return false;
    }


    /**
     * Just a wrapper for php's copy() method, with checks for the src being readable and
     * the destination being writable.
     *
     * @codeCoverageIgnore
     * @param string $src - source file path.
     * @param string $dest - destination file file.
     * @return bool - true if file was copied successfully.
     */
    public function copyFile($src, $dest)
    {
        if (!$this->isReadable($src)) {
            $this->log("Copy $src to $dest failed: $src is not readable.");
            return false;
        }

        if (!$this->isWritable($dest)) {
            $this->log("Copy $src to $dest failed: $dest is not writable.");
            return false;
        }

        return copy($src, $dest);
    }


    /**
     * Deletes the specified file.
     *
     * @codeCoverageIgnore
     * @param string $filePath - path of file to delete.
     * @return bool - true if the file is deleted or if the file is already deleted - false if deletion fails.
     */
    public function deleteFile($filePath)
    {
        $this->log("deleting $filePath");
        if ($this->fileExists($filePath)) {
            return unlink($filePath);
        }
        $this->log("cannot delete $filePath - it does not exist - already deleted?");
        return true;
    }


    /**
     * Return true if the path is readable. Log error message and return false if not.
     *
     * @codeCoverageIgnore
     * @param string $path - file path.
     * @return bool - true if $path is readable.
     */
    public function isReadable($path)
    {
        if (!is_readable($path)) {
            $this->print("Cannot write to $path\n");
            return false;
        }
        return true;
    }


    /**
     * Return true if the path is writable. Log error message and return false if not.
     *
     * @codeCoverageIgnore
     * @param $path - file path
     * @return bool - true if $path is writable.
     */
    public function isWritable($path)
    {
        if (!is_writable(pathinfo($path, PATHINFO_DIRNAME))) {
            $this->print("Cannot write to $path\n");
            return false;
        }
        return true;
    }


    /**
     * Copies a zip file from $zipPath into $dest and then unzips the copied zip file.
     *
     * Returns the exit code for unzip, or false if there's a problem.
     *
     * @param string $zipPath - path to zip file.
     * @param string $dest - path to copy the zip file to and where it will be unzipped. If omitted, zip file will be
     *  unzipped where it currently is.
     * @return bool
     */
    public function unzip($zipPath, $dest = '')
    {
        $this->log("unzipping $zipPath");

        if (!$this->fileExists($zipPath)) {
            $this->log("$zipPath is not a valid file.");
            return false;
        }

        if (!$this->isDir($dest)) {
            $this->log("Cannot unzip $zipPath to $dest - it's not a directory.");
            return false;
        }

        $result = $this->runCmd("unzip -o $zipPath -d $dest");
        $this->zipFilesToDelete[] = $zipPath;

        if ($result[1] !== 0) {
            $this->log("Unzipping $zipPath to $dest failed with exit code {$result[1]} and error msg: {$result[0]}");
        }

        return $result[1] === 0;
    }


    /**
     * Gets the current sugar version by including the sugar_version.php file from the instance.
     *
     * @codeCoverageIgnore
     * @return mixed - string (version number) or false if we can't find a version number.
     */
    public function getCurrentSugarVersion()
    {
        $sugar_version = null;
        include("{$this->instancePath}sugar_version.php");

        $this->log("Determining the current version of sugar - $sugar_version");
        if (!is_null($sugar_version)) {
            return $sugar_version;
        }
        return false;
    }


    /**
     * Returns true if the current sugar version is lower than or equal to the passed in version number.
     *
     * @note: the 'final' version (code patches and scripts in the upgrades/final directory)
     * is always greater than the current version.
     *
     * @param string $version - target version.
     * @return bool - true if the next version is higher, false if it's not.
     */
    public function nextUpgradeVersionIsHigherThanCurrent($nextVersion)
    {
        if ($nextVersion == 'final') {
            return true;
        }
        return (version_compare($nextVersion, $this->currentSugarVersion) > 0);
    }


    /**
     * Write a message to the log file and output to stdout.
     *
     * @codeCoverageIgnore
     * @param string $msg - text to write to the log file.
     */
    public function log($msg)
    {
        if (!$this->isWritable($this->logFile)) {
            $this->print("Cannot write to log file {$this->logFile}\n");
            $this->print("$msg\n");
            return false;
        }

        $this->print("$msg\n");

        $date = date("Y-m-d H:i:s");
        $msg = "$date: $msg\n";
        file_put_contents($this->logFile, $msg, FILE_APPEND);
    }


    /**
     * Collect the settings from the command line and assign them to instancePath and phpPath, if they are present.
     *
     * @param $argv
     */
    public function parseArgs($argv)
    {
        if (isset($argv[1])) {
            // Make sure $this->instancePath always ends with a slash
            $this->instancePath = rtrim($argv[1], '/').'/';
        }

        if (isset($argv[2])) {
            $this->phpPath = $argv[2];
        }
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
            $this->log("$path is not a directory");
            return array();
        }
        return array_diff(scandir($path), ['.', '..', '.placeholder']);
    }


    /**
     * Performs rm -rf on the given directory path, if it is a directory.
     *
     * @param string $path - directory to delete.
     */
    public function deleteDirectory($path)
    {
        if (empty($path) || $path == '/') {
            $this->log("Invalid path passed to deleteDirectory(): '$path'");
            return false;
        }

        if (!$this->isDir($path)) {
            $this->log("The path '$path' is not a directory - you cannot delete it with deleteDirectory()");
            return false;
        }

        if (!$this->isWritable($path)) {
            $this->log("The path '$path' is not writable - deleteDirectory() cannot delete it.");
            return false;
        }

        $result = $this->runCmd("rm -rf $path");
        if ($result[1] === 0) {
            return true;
        }
        return false;
    }


    /**
     * Makes sure the installed version of PHP is equal to or higher than our required version.
     * @return bool
     */
    public function checkPHPVersion()
    {
        if (empty($this->phpVersionRequired)) {
            $this->log("No miminum php version has been specified - not checking PHP version");
            return true;
        }

        list($output, $retVal) = $this->runCmd("{$this->phpPath} -v");

        $lines = explode("\n", $output); // PHP X.X.XX should be how the first line starts.
        $words = explode(' ', $lines[0]);
        $version = $words[1]; // version value should be second word.

        $versionOK = version_compare($version, $this->phpVersionRequired) >= 0;

        if ($versionOK) {
            $this->log("PHP version is OK! $version");
            return true;
        }
        $this->log("Installed PHP version is $version, which is lower than the minimum required version {$this->phpVersionRequired}");
        return false;
    }


    /**
     * This method creates a zip file of the custom upgrade scripts for this version and stage,
     * and some infrastructure classes (abstract UpgradeScript class and the UpgradeScriptManager)
     * as well as a simple php script to run the UpgradeScriptManager. The Upgrade Scripts and infrastructure
     * files will be staged in a temp directory. Then that directory is zipped up and copied to the instance.
     * Finally, the executeUpgradeScripts.php will be called, which runs the UpgradeScriptManager which in
     * turns runs each upgrade script for this version and stage.
     *
     * Upgrade scripts should throw exceptions if they encounter an error condition. The UpgradeScriptManager will
     * stop if any script throws an exception.
     *
     * @see UpgradeScriptManager and UpgradeScript for more details about how those classes work.
     * @param string $version - your upgrade target version
     * @param string $stage - 'pre' or 'post'
     * @return bool - true if upgrade scripts all ran with no errors, false otherwise.
     */
    public function runCustomUpgradeScripts($version, $stage)
    {
        $upgradeScriptsPath = $this->getUpgradeComponentPath($version, 'scripts', $stage);

        if (empty($upgradeScriptsPath)) {
            $this->log("No path to scripts component for $stage stage in version $version - skipping");
            return true;
        }

        if (empty($this->scanDir($upgradeScriptsPath))) {
            $this->log("Upgrade scripts for $version $stage ($upgradeScriptsPath) is empty - skipping");
            return true;
        }

        // first we have to stage the upgrade scripts and classes.
        // all upgrade scripts and classes will be copied into $basePath.
        $basePath = "upgradeScripts_{$version}_$stage/";

        // $upgrade matches the upgrade/ directory in the instance.
        $upgrade = 'upgrade/custom/';

        // lib is where the UpgradeScript abstract class and the UpgradeScriptManager class are stored.
        $libPath = "$basePath/{$upgrade}lib/";

        // scripts path is where the actual custom upgrade scripts are stored.
        $scriptsPath = "$basePath/$upgrade/$stage/";

        $this->mkDir($libPath, 0755, true);
        $this->mkDir($scriptsPath, 0755, true);

        $filesToCopy= array(
            "UpgradeScript.php" => $libPath,
            "UpgradeScriptManager.php" => $libPath,
            "executeUpgradeScripts.php" => $basePath
        );

        foreach ($filesToCopy as $fileName => $destDir) {
            $this->copyFile("lib/$fileName", "{$destDir}{$fileName}");
        }

        $zipName = "upgradeScripts.{$version}.{$stage}.zip";
        $this->zipDirectory($upgradeScriptsPath, $zipName);
        $this->unzip($zipName, $scriptsPath);
        $this->deleteFile($zipName);

        // then zip the staged files up and copy them to the instance.
        $zipName = "upgradeScriptManager.{$version}.{$stage}.zip";
        $this->zipDirectory($basePath, $zipName);
        $this->unzip($zipName, $this->instancePath);

        // run the executeUpgradeScripts.php file, which will return an exit code of 0 if no exceptions were thrown.
        $result = $this->runCmd("cd {$this->instancePath}; {$this->phpPath} executeUpgradeScripts.php $version $stage");

        // clean up the custom upgrade scripts in upgrade/custom directory in instance.
        $this->deleteFile("{$this->instancePath}/executeUpgradeScripts.php");
        $this->deleteDirectory("{$this->instancePath}/$upgrade");

        // clean up upgrade/custom directory in coordinator.
        $this->deleteFile($zipName);
        $this->deleteDirectory($basePath);

        if ($result[1] !== 0) {
            $this->log("Upgrade scripts failed: {$result[0]}");
            return false;
        }
        return true;
    }


    /**
     * Runs the silent upgrader for the given version.
     *
     * @param string $version - your upgrade target version
     * @return bool - true if the command returns an exit code of 0, false otherwise.
     */
    public function executeSilentUpgrade($version)
    {
        // the final upgrade should only be patches and custom upgrade scripts - there will be no
        // silent upgrader and no core upgrade package.
        if ($version == 'final') {
            return true;
        }

        $zipFile = $this->zipCoreUpgradeDir($version);
        $result = $this->runCmd($this->buildUpgradeCommand($version, $zipFile));
        $this->log($result[0]);
        if ($result[1] != 0) {
            return false;
        }
        return true;
    }


    /**
     * Zips up the core upgrade directory (upgrades/<$version>/upgrade) and saves it as
     * to the coordinator's directory.
     *
     * @param string $version - your upgrade target version
     * @return string - The name of the zipped file.
     */
    public function zipCoreUpgradeDir($version)
    {
        $coreUpgradePath = $this->getUpgradePackagePath($version);
        $coreUpgradeName = $this->buildUpgradePackageName($version);
        return $this->zipDirectory($coreUpgradePath, $coreUpgradeName . '.zip');
    }


    /**
     * Builds the command to run the silent upgraders for the given version.
     *
     * @param string $version - your upgrade target version
     * @param string $coreUpgradeZipFile - the name of zip file of the core upgrade files.
     * @return string - the command to run the silent upgrader.
     */
    public function buildUpgradeCommand($version, $coreUpgradeZipFile)
    {
        $parts = array();
        $parts[] = $this->phpPath;
        $parts[] = $this->getSilentUpgradePath($version) . '/CliUpgrader.php';
        $parts[] = '-z ' . $coreUpgradeZipFile;
        $parts[] = '-b 0'; // 0 = do not create backups.
        $parts[] = '-s ' . $this->instancePath;
        $parts[] = '-l ' . 'logs/' . $version . '.log';
        $parts[] = '-u ' . $this->adminUser;
        $parts[] = '-A 1';

        $command = implode(' ', $parts);
        return $command;
    }


    /**
     * Builds the name for the core upgrade package zip file. The name is based on the directory name in the upgrade
     * component directory (upgrades/<$version>/upgrade/SugarEnt-Upgrade-X.x.x-to-Y.y.y). This will be the same name
     * the original zip file used.
     *
     * @param string $version - your upgrade target version
     * @return mixed|string
     */
    public function buildUpgradePackageName($version)
    {
        $coreUpgradePath = $this->getUpgradePackagePath($version);
        $files = $this->scanDir($coreUpgradePath);
        foreach ($files as $fileName) {
            if (stripos($fileName, $this->coreUpgradePrefix) === 0) {
                return $fileName;
            }
        }
        return '';
    }


    /**
     * Reads the delete_files.txt file for the given version, and deletes every file listed from
     * the instance directory.
     *
     * The files in delete_list.txt should use relative paths.
     *
     * @param string $version - your upgrade target version
     * @return bool - true if the delete
     */
    public function deleteFilesFromList($version)
    {
        $path = "{$this->upgradesDir}/{$version}/delete_list.txt";

        if (!$this->fileExists($path)) {
            $this->log("no delete list file exists at $path - skipping");
            return true;
        }

        $this->log("Getting ready to delete files listed in $path");
        $files = $this->getFileContentsAsArray($path);
        foreach ($files as $file) {
            $file = trim($file);
            if (! empty($file)) {
                $filePath = $this->instancePath . $file;
                if (!$this->deleteFile($filePath)) {
                    $this->log("Failed to delete $filePath");
                    return false;
                }
            }
        }
        return true;
    }


    /**
     * @return bool - true if the PHP path exists
     */
    public function checkPHPPath()
    {
        if ($this->phpPath != 'php' && !$this->fileExists($this->phpPath)) {
            return false;
        }
        return true;
    }


    /**
     * @return bool - true if we can find sugar_version.php in the instance directory.
     */
    public function checkSugarInstance()
    {
        if (!$this->fileExists("{$this->instancePath}sugar_version.php")) {
            return false;
        }
        return true;
    }


    /**
     * @return bool - true if the zip and unzip utilities are installed and available.
     */
    public function checkZipAndUnzip()
    {
        $utilsOK = true;
        if (!$this->checkForInstalledUtility("zip")) {
            $this->log("Utility 'zip' does not appear to be available in this environment. You must install it before proceeding.");
            $utilsOK = false;
        }

        if (!$this->checkForInstalledUtility("unzip")) {
            $this->log("Utility 'unzip' does not appear to be available in this environment. You must install it before proceeding.");
            $utilsOK = false;
        }
        return $utilsOK;
    }


    /**
     * @return bool - true if the sugar instance directory is readable and writable.
     */
    public function instanceDirIsAccessible()
    {
        return $this->dirIsAccessible($this->instancePath);
    }


    /**
     * @return bool - true if the current working directory is readable and writable.
     */
    public function currentDirIsAccessible()
    {
        return $this->dirIsAccessible(getcwd());
    }


    /**
     * Determine is a directory can be accessed for reading and writing.
     *
     * @param string $dirPath - path to a directory to test for existence, and read/write access.
     * @return bool - true if we can read and write, false otherwise.
     */
    public function dirIsAccessible($dirPath)
    {
        if (!$this->isDir($dirPath)) {
            $this->log("'$dirPath' is not an accessible directory because it's not a directory.");
            return false;
        }

        if (!$this->isReadable($dirPath)) {
            $this->log("'$dirPath' is not accessible because it's not readable");
            return false;
        }

        if (!$this->isWritable($dirPath)) {
            $this->log("'$dirPath' is not accessiable because it's not writable");
            return false;
        }
        return true;
    }


    /**
     * Returns the path to the silent upgrader directory for this upgrade version.
     *
     * @param string $version - version number of target upgrade
     * @return string - path to upgrades/<$version>/silent_upgrader
     */
    public function getSilentUpgradePath($version)
    {
        return $this->getUpgradeComponentPath($version, $this->silentUpgraderDir);
    }


    /**
     * Returns the path to the core upgrade package directory for this upgrade version.
     *
     * @param string $version - version number of target upgrade
     * @return string - path to upgrades/<$version>/upgrade
     */
    public function getUpgradePackagePath($version)
    {
        return $this->getUpgradeComponentPath($version, $this->upgradeDir);
    }


    /**
     * Loops through all of our zipFilesToDelete and deletes each one. The deleteFile() method
     * will report success or failure on a file-by-file basis.
     */
    public function cleanUpZipFiles()
    {
        $this->log("cleaning up zip files.");
        foreach ($this->zipFilesToDelete as $filePath) {
            if (!$this->deleteFile($filePath)) {
                $fileExists = $this->fileExists($filePath) ? "File exists but cannot be deleted." : "File does not exist.";
                $this->log("Could not delete file $filePath. $fileExists");
            }
        }
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
     * @codeCoverageIgnore
     * @param $path
     * @param int $mode
     * @param bool $recursive
     * @return bool
     */
    public function mkDir($path, $mode=0777, $recursive=false)
    {
        return mkdir($path, $mode, $recursive);
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
     * @return bool
     */
    public function isFile($path)
    {
        return is_file($path);
    }


    /**
     * @codeCoverageIgnore
     * @param $msg
     */
    public function print($msg)
    {
        print($msg);
    }

    /**
     * @codeCoverageIgnore
     * @param $path
     * @return array
     */
    public function getFileContentsAsArray($path)
    {
        return file($path);
    }


    /**
     * @codeCoverageIgnore
     * @param $argv
     */
    public function test($argv)
    {
        $argv[1] = "/Library/WebServer/Documents/sugarcrm/ent";
        $this->init($argv);
        $zipFile = $this->zipCoreUpgradeDir('8.0.4');
        $this->buildUpgradeCommand('8.0.4', $zipFile);
    }
}