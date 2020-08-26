<?php
declare(strict_types=1);
require_once('lib/UpgradeCoordinator.php');
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject as MockObject;
use Sugarcrm\Sugarcrm\UpgradeCoordinator\lib\UpgradeCoordinator as UpgradeCoordinator;

class UpgradeCoordinatorTest extends TestCase
{
    public function testDeleteTestsDirectory()
    {
        $mock = $this->getUCMock(['clearDirectory']);
        $mock->expects($this->once())->method('clearDirectory')->will($this->returnValue(null));
        $mock->deleteTestsDirectory();
    }


    public function testZipCoreUpgradeDir()
    {
        $version = '8.0.4';
        $coreUpgradePath = '/some/path';
        $coreUpgradeName = 'UpgradeName.php';
        $zipFileName = "SomeUpgrade.zip";
        $mock = $this->getUCMock(['getUpgradePackagePath', 'buildUpgradePackageName', 'zipDirectory']);
        $mock->expects($this->once())->method('getUpgradePackagePath')->will($this->returnValue($coreUpgradePath));
        $mock->expects($this->once())->method('buildUpgradePackageName')->will($this->returnValue($coreUpgradeName));
        $mock->expects($this->once())->method('zipDirectory')->will($this->returnValue($zipFileName));
        $result = $mock->zipCoreUpgradeDir($version);
        $this->assertNotEmpty($result);
    }


    public function testBuildUpgradeCommand()
    {
        $version = '8.0.4';
        $coreUpgradeZipFile = 'somezip.zip';
        $silentPath = 'silent_upgrader_path.php';
        $mock = $this->getUCMock(['getSilentUpgradePath']);
        $mock->expects($this->once())->method('getSilentUpgradePath')->will($this->returnValue($silentPath));
        $result = $mock->buildUpgradeCommand($version, $coreUpgradeZipFile);
        $this->assertNotEmpty($result);
    }


    /**
     * @dataProvider getUpgradePackagePathData
     * @param $path
     */
    public function testGetUpgradePackagePath($path)
    {
        $mock = $this->getUCMock(['getUpgradeComponentPath']);
        $mock->method('getUpgradeComponentPath')->will($this->returnValue($path));
        $componentPath = $mock->getUpgradePackagePath($path);
        if (empty($path)) {
            $this->assertEmpty($componentPath);
        } else {
            $this->assertNotEmpty($componentPath);
        }
    }


    public function getUpgradePackagePathData()
    {
        return [
            [
                'path' => '',
            ],
            [
                'path' => '/some/path',
            ],
        ];
    }


    /**
     * @dataProvider getSilentUpgradePathData
     * @param $path
     */
    public function testGetSilentUpgradePath($path)
    {
        $mock = $this->getUCMock(['getUpgradeComponentPath']);
        $mock->method('getUpgradeComponentPath')->will($this->returnValue($path));
        $componentPath = $mock->getSilentUpgradePath($path);
        if (empty($path)) {
            $this->assertEmpty($componentPath);
        } else {
            $this->assertNotEmpty($componentPath);
        }
    }


    public function getSilentUpgradePathData()
    {
        return [
            [
                'path' => '',
            ],
            [
                'path' => '/some/path',
            ],
        ];
    }


    /**
     * @dataProvider cleanUpZipFilesData
     * @param $fileList
     * @param $deleteFile
     * @param $fileExists
     */
    public function testCleanUpZipFiles($fileList, $deleteFile, $fileExists)
    {
        $mock = $this->getUCMock(['log', 'deleteFile', 'fileExists']);
        $mock->zipFilesToDelete = $fileList;
        $mock->method('log')->will($this->returnValue(null));
        $mock->expects($this->exactly(count($fileList)))->method('deleteFile')->will($this->returnValue($deleteFile));
        $mock->method('fileExists')->will($this->returnValue($fileExists));
        $mock->cleanUpZipFiles();
    }


    public function cleanUpZipFilesData()
    {
        return [
            [
                'fileList' => array(),
                'deleteFile' => false,
                'fileExists' => false,
            ],
            [
                'fileList' => array('onezip.zip', 'twozip.zip'),
                'deleteFile' => false,
                'fileExists' => false,
            ],
            [
                'fileList' => array('onezip.zip', 'twozip.zip'),
                'deleteFile' => false,
                'fileExists' => false,
            ],
            [
                'fileList' => array('onezip.zip', 'twozip.zip'),
                'deleteFile' => true,
                'fileExists' => true,
            ],
        ];
    }


    /**
     * @dataProvider checkZipAndUnzipData
     * @param $zip
     * @param $unzip
     * @param $expected
     */
    public function testCheckZipAndUnzip($zip, $unzip, $expected)
    {
        $mock = $this->getUCMock(['checkForInstalledUtility', 'log']);
        $mock->method('log')->will($this->returnValue(null));
            $mock->method('checkForInstalledUtility')->willReturnOnConsecutiveCalls($zip, $unzip);
        $result = $mock->checkZipAndUnzip();
        $this->assertEquals($expected, $result);
    }


    public function checkZipAndUnzipData()
    {
        return [
            [
                'zip' => false,
                'unzip' => false,
                'expected' => false,
            ],
            [
                'zip' => true,
                'unzip' => false,
                'expected' => false,
            ],
            [
                'zip' => false,
                'unzip' => true,
                'expected' => false,
            ],
            [
                'zip' => true,
                'unzip' => true,
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider checkPHPPathData
     * @param $phpPath
     * @param $fileExists
     * @param $expected
     */
    public function testCheckPHPPath($phpPath, $fileExists, $expected)
    {
        $mock = $this->getUCMock(['fileExists']);
        $mock->phpPath = $phpPath;
        $mock->method('fileExists')->will($this->returnValue($fileExists));
        $result = $mock->checkPHPPath();
        $this->assertEquals($expected, $result);
    }


    public function checkPHPPathData()
    {
        return [
            [
                'phpPath' => '',
                'fileExists' => false,
                'expected' => false,
            ],
            [
                'phpPath' => 'php',
                'fileExists' => false,
                'expected' => true,
            ],
            [
                'phpPath' => 'bogus-php',
                'fileExists' => false,
                'expected' => false,
            ],
            [
                'phpPath' => 'specified-php',
                'fileExists' => false,
                'expected' => false,
            ],
            [
                'phpPath' => 'specified-php',
                'fileExists' => true,
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider checkSugarInstanceData
     * @param $fileExists
     * @param $expected
     */
    public function testCheckSugarInstance($fileExists, $expected)
    {
        $mock = $this->getUCMock(['fileExists']);
        $mock->method('fileExists')->will($this->returnValue($fileExists));
        $result = $mock->checkSugarInstance();
        $this->assertEquals($expected, $result);
    }


    public function checkSugarInstanceData()
    {
        return [
            [
                'fileExists' => false,
                'expected' => false,
            ],
            [
                'fileExists' => true,
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider buildUpgradePackageNameData
     * @param $version
     * @param $upgradePath
     * @param $scanDir
     * @param $expected
     */
    public function testBuildUpgradePackageName($version, $upgradePath, $scanDir, $expected)
    {
        $mock = $this->getUCMock(['getUpgradePackagePath', 'scanDir', 'log']);
        $mock->method('getUpgradePackagePath')->will($this->returnValue($upgradePath));
        $mock->method('scanDir')->will($this->returnValue($scanDir));
        $mock->method('log')->will($this->returnValue(null));
        $result = $mock->buildUpgradePackageName($version);

        if (!$expected) {
            $this->assertEmpty($result);
        } else {
            $this->assertNotEmpty($result);
        }
    }


    public function buildUpgradePackageNameData()
    {
        return [
            [
                'version' => '8.0.4',
                'upgradePath' => '',
                'scanDir' => array(),
                'expected' => false,
            ],
            [
                'version' => '8.0.4',
                'upgradePath' => '/some/path',
                'scanDir' => array(),
                'expected' => false,
            ],
            [
                'version' => '8.0.4',
                'upgradePath' => '/some/path',
                'scanDir' => array('badfile.php', 'useless_file.php'),
                'expected' => false,
            ],
            [
                'version' => '8.0.4',
                'upgradePath' => '/some/path',
                'scanDir' => array('goodfile.php', 'SugarEnt-Upgrade-8.0.x-to-8.0.4'),
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider executeSilentUpgradeData
     * @param $version
     * @param $zipCoreUpgradeDir
     * @param $buildUpgradeCmd
     * @param $runCmd
     * @param $expected
     */
    public function testExecuteSilentUpgrade($version, $zipCoreUpgradeDir, $buildUpgradeCmd, $runCmd, $expected)
    {
        $mock = $this->getUCMock(['zipCoreUpgradeDir', 'buildUpgradeCommand', 'runCmd', 'log']);
        $mock->method('zipCoreUpgradeDir')->will($this->returnValue($zipCoreUpgradeDir));
        $mock->method('buildUpgradeCommand')->will($this->returnValue($buildUpgradeCmd));
        $mock->method('runCmd')->will($this->returnValue($runCmd));
        $mock->method('log')->will($this->returnValue(null));
        $result = $mock->executeSilentUpgrade($version);
        $this->assertEquals($expected, $result);
    }


    public function executeSilentUpgradeData()
    {
        return [
            [
                'version' => 'final',
                'zipCoreUpgradeDir' => '',
                'buildUpgradeCmd' => '',
                'runCmd' => ['', 1],
                'expected' => true,
            ],
            [
                'version' => '8.0.4',
                'zipCoreUpgradeDir' => '',
                'buildUpgradeCmd' => '',
                'runCmd' => ['', 1],
                'expected' => false,
            ],
            [
                'version' => '8.0.4',
                'zipCoreUpgradeDir' => 'a_zipfile.zip',
                'buildUpgradeCmd' => 'some_command --with --options=true',
                'runCmd' => ['', 1],
                'expected' => false,
            ],
            [
                'version' => '8.0.4',
                'zipCoreUpgradeDir' => 'a_zipfile.zip',
                'buildUpgradeCmd' => 'some_command --with --options=true',
                'runCmd' => ['', 0],
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider runQRRData
     * @param $fileExists
     * @param $copyFile
     * @param $runCmd
     * @param $expected
     */
    public function testRunQRR($fileExists, $copyFile, $runCmd, $expected)
    {
        $mock = $this->getUCMock(['log', 'fileExists', 'copyFile', 'runCmd', 'deleteFile']);
        $mock->method('log')->will($this->returnValue(null));
        $mock->method('fileExists')->will($this->returnValue($fileExists));
        $mock->method('copyFile')->will($this->returnValue($copyFile));
        $mock->method('runCmd')->will($this->returnValue($runCmd));
        $mock->method('deleteFile')->will($this->returnValue(null));
        $result = $mock->runQRR();
        $this->assertEquals($expected, $result);
    }


    public function runQRRData()
    {
        return [
            [
                'fileExists' => false,
                'copyFile' => false,
                'runCmd' => ['', 1],
                'expected' => false,
            ],
            [
                'fileExists' => true,
                'copyFile' => false,
                'runCmd' => ['', 1],
                'expected' => false,
            ],
            [
                'fileExists' => false,
                'copyFile' => true,
                'runCmd' => ['', 0],
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider zipDirectoryData
     * @param $path
     * @param $fileName
     * @param $runCmd
     * @param $expected
     */
    public function testZipDirectory($path, $fileName, $runCmd, $expected)
    {
        $mock = $this->getUCMock(['log', 'runCmd']);
        $mock->method('log')->will($this->returnValue(null));
        $mock->method('runCmd')->will($this->returnValue($runCmd));
        $result = $mock->zipDirectory($path, $fileName);
        if (!$expected) {
            $this->assertEquals($expected, $result);
        } else {
            $this->assertNotEmpty($result);
        }
    }


    public function zipDirectoryData()
    {
        return [
            [
                'path' => '',
                'fileName' => '',
                'runCmd' => ['', 1],
                'expected' => false,
            ],
            [
                'path' => '/some/path',
                'fileName' => '',
                'runCmd' => ['', 1],
                'expected' => false,
            ],
            [
                'path' => '/some/path',
                'fileName' => 'a_zip_file.zip',
                'runCmd' => ['', 1],
                'expected' => false,
            ],
            [
                'path' => '/some/path',
                'fileName' => 'a_zip_file.zip',
                'runCmd' => ['', 0],
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider buildAndDeployZipFileData
     * @param $args
     * @param $expected
     */
    public function testBuildAndDeployZipFile($args, $expected)
    {
        $mock = $this->getUCMock(['getUpgradeComponentPath', 'scanDir', 'zipDirectory', 'log', 'unzip']);
        $mock->method('getUpgradeComponentPath')->will($this->returnValue($args['getUpgradeComponentPath']));
        $mock->method('scanDir')->will($this->returnValue($args['scanDir']));
        $mock->method('zipDirectory')->will($this->returnValue($args['zipDirectory']));
        $mock->method('unzip')->will($this->returnValue($args['unzip']));
        $mock->method('log')->will($this->returnValue(null));
        $result = $mock->buildAndDeployZipFile($args['version'], $args['stage'], $args['component']);
        $this->assertEquals($expected, $result);
    }


    public function buildAndDeployZipFileData()
    {
        return [
            [
                'args' => [
                    'version' => '8.0.4',
                    'stage' => 'pre',
                    'component' => 'upgrade',
                    'getUpgradeComponentPath' => '',
                    'scanDir' => array(),
                    'zipDirectory' => '',
                    'unzip' => false
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'version' => '8.0.4',
                    'stage' => 'pre',
                    'component' => 'upgrade',
                    'getUpgradeComponentPath' => '/some/path',
                    'scanDir' => array(),
                    'zipDirectory' => '',
                    'unzip' => false
                ],
                'expected' => true,
            ],
            [
                'args' => [
                    'version' => '8.0.4',
                    'stage' => 'pre',
                    'component' => 'upgrade',
                    'getUpgradeComponentPath' => '/some/path',
                    'scanDir' => array('some_file.php', 'another_file.php'),
                    'zipDirectory' => '',
                    'unzip' => false
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'version' => '8.0.4',
                    'stage' => 'pre',
                    'component' => 'upgrade',
                    'getUpgradeComponentPath' => '/some/path',
                    'scanDir' => array('some_file.php', 'another_file.php'),
                    'zipDirectory' => 'a_zip_file.zip',
                    'unzip' => false
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'version' => '8.0.4',
                    'stage' => 'pre',
                    'component' => 'upgrade',
                    'getUpgradeComponentPath' => '/some/path',
                    'scanDir' => array('some_file.php', 'another_file.php'),
                    'zipDirectory' => 'a_zip_file.zip',
                    'unzip' => true
                ],
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider runCustomUpgradeScriptsData
     * @param $args
     * @param $expected
     */
    public function testRunCustomUpgradeScripts($args, $expected)
    {
        $methods = array(
            'getUpgradeComponentPath',
            'scanDir',
            'log',
            'mkDir',
            'copyFile',
            'zipDirectory',
            'unzip',
            'deleteFile',
            'deleteDirectory',
            'runCmd',
        );
        $mock = $this->getUCMock($methods);
        $mock->method('getUpgradeComponentPath')->will($this->returnValue($args['componentPath']));
        $mock->method('scanDir')->will($this->returnValue($args['scanDir']));
        $mock->method('log')->will($this->returnValue(null));
        $mock->method('mkDir')->will($this->returnValue(null));
        $mock->method('copyFile')->will($this->returnValue(null));
        $mock->method('zipDirectory')->will($this->returnValue(null));
        $mock->method('unzip')->will($this->returnValue(null));
        $mock->method('deleteFile')->will($this->returnValue(null));
        $mock->method('runCmd')->will($this->returnValue($args['runCmd']));
        $mock->method('deleteDirectory')->will($this->returnValue(null));
        $result = $mock->runCustomUpgradeScripts($args['version'], $args['stage']);
        $this->assertEquals($expected, $result);
    }


    public function runCustomUpgradeScriptsData()
    {
        return [
            [
                'args' => [
                    'version' => '8.0.4',
                    'stage' => 'pre',
                    'componentPath' => '/some/path',
                    'scanDir' => array(),
                    'runCmd' => array('', 0),
                ],
                'expected' => true,
            ],
            [
                'args' => [
                    'version' => '8.0.4',
                    'stage' => 'pre',
                    'componentPath' => '',
                    'scanDir' => array(),
                    'runCmd' => array('', 0),
                ],
                'expected' => true,
            ],
            [
                'args' => [
                    'version' => '8.0.4',
                    'stage' => 'pre',
                    'componentPath' => '/some/path',
                    'scanDir' => array(),
                    'runCmd' => array('', 0),
                ],
                'expected' => true,
            ],
            [
                'args' => [
                    'version' => '8.0.4',
                    'stage' => 'pre',
                    'componentPath' => '/some/path',
                    'scanDir' => array('an_upgrade_script.php', 'another_upgrade_script.php'),
                    'runCmd' => array('', 0),
                ],
                'expected' => true,
            ],
            [
                'args' => [
                    'version' => '8.0.4',
                    'stage' => 'pre',
                    'componentPath' => '/some/path',
                    'scanDir' => array('an_upgrade_script.php', 'another_upgrade_script.php'),
                    'runCmd' => array('', 1),
                ],
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider getUpgradeComponentPathData
     * @param $version
     * @param $component
     * @param $stage
     * @param $fileExists
     * @param $isDir
     * @param $expected
     */
    public function testGetUpgradeComponentPath($version, $component, $stage, $fileExists, $isDir, $expected)
    {
        $mock = $this->getUCMock(['fileExists', 'log', 'isDir']);
        $mock->method('fileExists')->will($this->returnValue($fileExists));
        $mock->method('log')->will($this->returnValue(null));
        $mock->method('isDir')->will($this->returnValue($isDir));
        $result = $mock->getUpgradeComponentPath($version, $component, $stage);
        if ($expected) {
            $this->assertNotEmpty($result);
        } else {
            $this->assertEmpty($result);
        }
    }


    public function getUpgradeComponentPathData()
    {
        return [
            [
                'version' => '8.0.4',
                'component' => 'upgrade',
                'stage' => 'pre',
                'fileExists' => false,
                'isDir' => false,
                'expected' => false,
            ],
            [
                'version' => '8.0.4',
                'component' => 'upgrade',
                'stage' => 'pre',
                'fileExists' => true,
                'isDir' => false,
                'expected' => false,
            ],
            [
                'version' => '8.0.4',
                'component' => 'upgrade',
                'stage' => 'pre',
                'fileExists' => true,
                'isDir' => true,
                'expected' => true,
            ],
            [
                'version' => '8.0.4',
                'component' => 'upgrade',
                'stage' => '',
                'fileExists' => true,
                'isDir' => true,
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider deployPatchFilesData
     * @param $version
     * @param $stage
     * @param $buildAndDeploy
     * @param $expected
     */
    public function testDeployPatchFiles($version, $stage, $buildAndDeploy, $expected)
    {
        $mock = $this->getUCMock(['buildAndDeployZipFile']);
        $mock->method('buildAndDeployZipFile')->will($this->returnValue($buildAndDeploy));
        $result = $mock->deployPatchFiles($version, $stage);
        $this->assertEquals($expected, $result);
    }


    public function deployPatchFilesData()
    {
        return [
            [
                'version' => '8.0.4',
                'stage' => 'pre',
                'buildAndDeploy' => false,
                'expected' => false,
            ],
            [
                'version' => '8.0.4',
                'stage' => 'pre',
                'buildAndDeploy' => true,
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider clearDirectoryData
     * @param $dirPath
     * @param $fileExists
     * @param $isDir
     * @param $runCmd
     * @param $expected
     */
    public function testClearDirectory($dirPath, $fileExists, $isDir, $runCmd, $expected)
    {
        $mock = $this->getUCMock(['log', 'fileExists', 'isDir', 'runCmd']);
        $mock->method('log')->will($this->returnValue(null));
        $mock->method('fileExists')->will($this->returnValue($fileExists));
        $mock->method('isDir')->will($this->returnValue($isDir));
        $mock->method('runCmd')->will($this->returnValue($runCmd));
        $result = $mock->clearDirectory($dirPath);
        $this->assertEquals($expected, $result);
    }


    public function clearDirectoryData()
    {
        return [
            [
                'dirPath' => '',
                'fileExists' => false,
                'isDir' => false,
                'runCmd' => ['', 1],
                'expected' => false,
            ],
            [
                'dirPath' => '/',
                'fileExists' => false,
                'isDir' => false,
                'runCmd' => ['', 1],
                'expected' => false,
            ],
            [
                'dirPath' => '/some/path',
                'fileExists' => false,
                'isDir' => false,
                'runCmd' => ['', 1],
                'expected' => false,
            ],
            [
                'dirPath' => '/some/path',
                'fileExists' => true,
                'isDir' => false,
                'runCmd' => ['', 1],
                'expected' => false,
            ],
            [
                'dirPath' => '/some/path',
                'fileExists' => true,
                'isDir' => true,
                'runCmd' => ['', 1],
                'expected' => false,
            ],
            [
                'dirPath' => '/some/path',
                'fileExists' => true,
                'isDir' => true,
                'runCmd' => ['', 0],
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider deleteFilesFromListData
     * @param $fileExists
     * @param $deleteFile
     * @param $fileContents
     * @param $expected
     */
    public function testDeleteFilesFromList($fileExists, $fileContents, $deleteFile, $expected)
    {
        $mock = $this->getUCMock(['fileExists', 'deleteFile', 'getFileContentsAsArray', 'log']);
        $mock->method('fileExists')->will($this->returnValue($fileExists));
        $mock->method('deleteFile')->will($this->returnValue($deleteFile));
        $mock->method('getFileContentsAsArray')->will($this->returnValue($fileContents));
        $result = $mock->deleteFilesFromList('8.0.4');
        $this->assertEquals($expected, $result);
    }


    public function deleteFilesFromListData()
    {
        return [
            [
                'fileExists' => false,
                'fileContents' => array(),
                'deleteFile' => false,
                'expected' => true,
            ],
            [
                'fileExists' => true,
                'fileContents' => array(),
                'deleteFile' => false,
                'expected' => true,
            ],
            [
                'fileExists' => true,
                'fileContents' => array('some_file.txt'),
                'deleteFile' => false,
                'expected' => false,
            ],
            [
                'fileExists' => true,
                'fileContents' => array('some_file.txt'),
                'deleteFile' => true,
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider runData
     * @param $upgrades
     * @param $init
     * @param $deleteTests
     * @param $preflight
     * @param $executeUpgrade
     * @param $cleanUpZipFiles
     * @param $expected
     */
    public function testRun($upgrades, $init, $preflight, $executeUpgrade, $expected)
    {
        $mock = $this->getUCMock(['init', 'deleteTestsDirectory', 'preflightChecks', 'executeUpgrade', 'cleanUpZipFiles', 'log']);
        $mock->upgrades = $upgrades;
        $mock->method('init')->will($this->returnValue($init));
        $mock->method('deleteTestsDirectory')->will($this->returnValue(null));
        $mock->method('preflightChecks')->will($this->returnValue($preflight));
        $mock->method('executeUpgrade')->will($this->returnValue($executeUpgrade));
        $mock->method('cleanUpZipFiles')->will($this->returnValue(null));
        $mock->method('log')->will($this->returnValue(null));
        $result = $mock->run([]);
        $this->assertEquals($expected, $result);
    }


    public function runData()
    {
        return [
            [
                'upgrades' => array('8.0.4', 'final'),
                'init' => false,
                'preflight' => false,
                'executeUpgrade' => false,
                'expected' => false,
            ],
            [
                'upgrades' => array('8.0.4', 'final'),
                'init' => true,
                'preflight' => false,
                'executeUpgrade' => false,
                'expected' => false,
            ],
            [
                'upgrades' => array('8.0.4', 'final'),
                'init' => true,
                'preflight' => true,
                'executeUpgrade' => false,
                'expected' => false,
            ],
            [
                'upgrades' => array('8.0.4', 'final'),
                'init' => true,
                'preflight' => true,
                'executeUpgrade' => true,
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider executeUpgradeData
     * @param $args
     * @param $expected
     */
    public function testExecuteUpgrade($args, $expected)
    {
        $methods = array(
            'nextUpgradeVersionIsHigherThanCurrent',
            'deleteFilesFromList',
            'deployPatchFiles',
            'runCustomUpgradeScripts',
            'clearCacheDir',
            'runQRR',
            'executeSilentUpgrade',
            'getCurrentSugarVersion',
            'log',
        );
        $mock = $this->getUCMock($methods);
        $mock->method('nextUpgradeVersionIsHigherThanCurrent')->will($this->returnValue($args['higher']));
        $mock->method('deleteFilesFromList')->will($this->returnValue($args['deleteFiles']));
        $mock->method('deployPatchFiles')->willReturnOnConsecutiveCalls($args['deployPatchFiles1'], $args['deployPatchFiles2']);
        $mock->method('runCustomUpgradeScripts')->willReturnOnConsecutiveCalls($args['runCustomUpgradeScripts1'], $args['runCustomUpgradeScripts2']);
        $mock->method('clearCacheDir')->willReturnOnConsecutiveCalls($args['clearCacheDir1'], $args['clearCacheDir2']);
        $mock->method('runQrr')->willReturnOnConsecutiveCalls($args['runQrr1'], $args['runQrr2']);
        $mock->method('executeSilentUpgrade')->will($this->returnValue($args['executeSilentUpgrade']));
        $mock->method('getCurrentSugarVersion')->will($this->returnValue(null));
        $mock->method('log')->will($this->returnValue(null));
        $result = $mock->executeUpgrade('8.0.4');
        $this->assertEquals($expected, $result);
    }


    public function executeUpgradeData()
    {
        return [
            [
                'args' => [
                    'higher' => false,
                    'deleteFiles' => false,
                    'deployPatchFiles1' => false,
                    'runCustomUpgradeScripts1' => false,
                    'clearCacheDir1' => false,
                    'runQrr1' => false,
                    'executeSilentUpgrade' => false,
                    'deployPatchFiles2' => false,
                    'runCustomUpgradeScripts2' => false,
                    'clearCacheDir2' => false,
                    'runQrr2' => false,
                ],
                'expected' => true,
            ],
            [
                'args' => [
                    'higher' => true,
                    'deleteFiles' => false,
                    'deployPatchFiles1' => false,
                    'runCustomUpgradeScripts1' => false,
                    'clearCacheDir1' => false,
                    'runQrr1' => false,
                    'executeSilentUpgrade' => false,
                    'deployPatchFiles2' => false,
                    'runCustomUpgradeScripts2' => false,
                    'clearCacheDir2' => false,
                    'runQrr2' => false,
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'higher' => true,
                    'deleteFiles' => true,
                    'deployPatchFiles1' => false,
                    'runCustomUpgradeScripts1' => false,
                    'clearCacheDir1' => false,
                    'runQrr1' => false,
                    'executeSilentUpgrade' => false,
                    'deployPatchFiles2' => false,
                    'runCustomUpgradeScripts2' => false,
                    'clearCacheDir2' => false,
                    'runQrr2' => false,
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'higher' => true,
                    'deleteFiles' => true,
                    'deployPatchFiles1' => false,
                    'runCustomUpgradeScripts1' => false,
                    'clearCacheDir1' => false,
                    'runQrr1' => false,
                    'executeSilentUpgrade' => false,
                    'deployPatchFiles2' => false,
                    'runCustomUpgradeScripts2' => false,
                    'clearCacheDir2' => false,
                    'runQrr2' => false,
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'higher' => true,
                    'deleteFiles' => true,
                    'deployPatchFiles1' => true,
                    'runCustomUpgradeScripts1' => false,
                    'clearCacheDir1' => false,
                    'runQrr1' => false,
                    'executeSilentUpgrade' => false,
                    'deployPatchFiles2' => false,
                    'runCustomUpgradeScripts2' => false,
                    'clearCacheDir2' => false,
                    'runQrr2' => false,
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'higher' => true,
                    'deleteFiles' => true,
                    'deployPatchFiles1' => true,
                    'runCustomUpgradeScripts1' => true,
                    'clearCacheDir1' => false,
                    'runQrr1' => false,
                    'executeSilentUpgrade' => false,
                    'deployPatchFiles2' => false,
                    'runCustomUpgradeScripts2' => false,
                    'clearCacheDir2' => false,
                    'runQrr2' => false,
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'higher' => true,
                    'deleteFiles' => true,
                    'deployPatchFiles1' => true,
                    'runCustomUpgradeScripts1' => true,
                    'clearCacheDir1' => true,
                    'runQrr1' => false,
                    'executeSilentUpgrade' => false,
                    'deployPatchFiles2' => false,
                    'runCustomUpgradeScripts2' => false,
                    'clearCacheDir2' => false,
                    'runQrr2' => false,
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'higher' => true,
                    'deleteFiles' => true,
                    'deployPatchFiles1' => true,
                    'runCustomUpgradeScripts1' => true,
                    'clearCacheDir1' => true,
                    'runQrr1' => true,
                    'executeSilentUpgrade' => false,
                    'deployPatchFiles2' => false,
                    'runCustomUpgradeScripts2' => false,
                    'clearCacheDir2' => false,
                    'runQrr2' => false,
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'higher' => true,
                    'deleteFiles' => true,
                    'deployPatchFiles1' => true,
                    'runCustomUpgradeScripts1' => true,
                    'clearCacheDir1' => true,
                    'runQrr1' => true,
                    'executeSilentUpgrade' => true,
                    'deployPatchFiles2' => false,
                    'runCustomUpgradeScripts2' => false,
                    'clearCacheDir2' => false,
                    'runQrr2' => false,
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'higher' => true,
                    'deleteFiles' => true,
                    'deployPatchFiles1' => true,
                    'runCustomUpgradeScripts1' => true,
                    'clearCacheDir1' => true,
                    'runQrr1' => true,
                    'executeSilentUpgrade' => true,
                    'deployPatchFiles2' => true,
                    'runCustomUpgradeScripts2' => false,
                    'clearCacheDir2' => false,
                    'runQrr2' => false,
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'higher' => true,
                    'deleteFiles' => true,
                    'deployPatchFiles1' => true,
                    'runCustomUpgradeScripts1' => true,
                    'clearCacheDir1' => true,
                    'runQrr1' => true,
                    'executeSilentUpgrade' => true,
                    'deployPatchFiles2' => true,
                    'runCustomUpgradeScripts2' => true,
                    'clearCacheDir2' => false,
                    'runQrr2' => false,
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'higher' => true,
                    'deleteFiles' => true,
                    'deployPatchFiles1' => true,
                    'runCustomUpgradeScripts1' => true,
                    'clearCacheDir1' => true,
                    'runQrr1' => true,
                    'executeSilentUpgrade' => true,
                    'deployPatchFiles2' => true,
                    'runCustomUpgradeScripts2' => true,
                    'clearCacheDir2' => true,
                    'runQrr2' => false,
                ],
                'expected' => false,
            ],
            [
                'args' => [
                    'higher' => true,
                    'deleteFiles' => true,
                    'deployPatchFiles1' => true,
                    'runCustomUpgradeScripts1' => true,
                    'clearCacheDir1' => true,
                    'runQrr1' => true,
                    'executeSilentUpgrade' => true,
                    'deployPatchFiles2' => true,
                    'runCustomUpgradeScripts2' => true,
                    'clearCacheDir2' => true,
                    'runQrr2' => true,
                ],
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider clearCacheDirectoryData
     * @param $isDir0
     * @param $isDir1
     * @param $clearDirectory
     * @param $isFile
     * @param $expected
     */
    public function testClearCacheDirectory($isDir0, $isDir1, $clearDirectory, $isFile, $deleteFile, $expected)
    {
        $mock = $this->getUCMock(['isDir', 'clearDirectory', 'isFile', 'deleteFile', 'log']);
        $mock->method('isDir')->willReturnOnConsecutiveCalls($isDir0, $isDir1);
        $mock->method('clearDirectory')->will($this->returnValue($clearDirectory));
        $mock->method('isFile')->will($this->returnValue($isFile));
        $mock->method('log')->will($this->returnValue(null));
        $mock->method('deleteFile')->will($this->returnValue($deleteFile));
        $result = $mock->clearCacheDir();
        $this->assertEquals($expected, $result);
    }


    public function clearCacheDirectoryData()
    {
        return [
            [
                'isDir0' => false,
                'isDir1' => false,
                'clearDirectory' => false,
                'isFile' => false,
                'deleteFile' => false,
                'expected' => false,
            ],
            [
                'isDir0' => true,
                'isDir1' => false,
                'clearDirectory' => false,
                'isFile' => false,
                'deleteFile' => false,
                'expected' => true,
            ],
            [
                'isDir0' => true,
                'isDir1' => true,
                'clearDirectory' => false,
                'isFile' => false,
                'deleteFile' => false,
                'expected' => true,
            ],
            [
                'isDir0' => true,
                'isDir1' => true,
                'clearDirectory' => true,
                'isFile' => false,
                'deleteFile' => false,
                'expected' => true,
            ],
            [
                'isDir0' => true,
                'isDir1' => true,
                'clearDirectory' => false,
                'isFile' => true,
                'deleteFile' => false,
                'expected' => true,
            ],
            [
                'isDir0' => true,
                'isDir1' => false,
                'clearDirectory' => false,
                'isFile' => true,
                'deleteFile' => true,
                'expected' => true,
            ],
            [
                'isDir0' => true,
                'isDir1' => false,
                'clearDirectory' => false,
                'isFile' => true,
                'deleteFile' => false,
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider checkUpgradePackagesData
     * @param $silentUpgrader
     * @param $upgradePackage
     * @param $dirContents
     * @param $expected
     */
    public function testCheckUpgradePackages($upgrades, $silentUpgrader, $upgradePackage, $dirContents, $expected)
    {
        $mock = $this->getUCMock(['getSilentUpgradePath', 'getUpgradePackagePath', 'scanDir', 'log']);
        $mock->upgrades = $upgrades;
        $mock->method('log')->will($this->returnValue(null));
        $mock->method('getSilentUpgradePath')->will($this->returnValue($silentUpgrader));
        $mock->method('getUpgradePackagePath')->will($this->returnValue($upgradePackage));
        $mock->method('scanDir')->will($this->returnValue($dirContents));
        $result = $mock->checkUpgradePackages();
        $this->assertEquals($expected, $result);
    }


    public function checkUpgradePackagesData()
    {
        return [
            [
                'upgrades' => array(),
                'silentUpgrader' => '',
                'upgradePackage' => '',
                'dirContents' => array(),
                'expected' => false,
            ],
            [
                'upgrades' => array('8.0.2'),
                'silentUpgrader' => 'upgrades/8.0.2/silent_upgrader_test',
                'upgradePackage' => '',
                'dirContents' => array('some', 'file', 'names'),
                'expected' => false,
            ],
            [
                'upgrades' => array('8.0.2'),
                'silentUpgrader' => 'upgrades/8.0.2/silent_upgrader_test',
                'upgradePackage' => 'upgrades/8.0.2/upgrade_test',
                'dirContents' => array(),
                'expected' => false,
            ],
            [
                'upgrades' => array('8.0.2'),
                'silentUpgrader' => 'upgrades/8.0.2/silent_upgrader_test',
                'upgradePackage' => 'upgrades/8.0.2/upgrade_test',
                'dirContents' => array('some', 'file', 'names'),
                'expected' => true,
            ],
            [
                'upgrades' => array('8.0.2', 'final'),
                'silentUpgrader' => 'upgrades/8.0.2/silent_upgrader_test',
                'upgradePackage' => 'upgrades/8.0.2/upgrade_test',
                'dirContents' => array('some', 'file', 'names'),
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider preflightChecksData
     * @param array $args
     */
    public function testPreflightChecks($args, $expected)
    {
        $methods = [
            'currentDirIsAccessible',
            'instanceDirIsAccessible',
            'checkSugarInstance',
            'checkZipAndUnzip',
            'checkPHPPath',
            'checkPHPVersion',
            'checkUpgradePackages',
            'log'];
        $mock = $this->getUCMock($methods);
        $mock->method('currentDirIsAccessible')->will($this->returnValue($args['currentDir']));
        $mock->method('instanceDirIsAccessible')->will($this->returnValue($args['instanceDir']));
        $mock->method('checkSugarInstance')->will($this->returnValue($args['checkSugarInstance']));
        $mock->method('checkZipAndUnzip')->will($this->returnValue($args['checkZipAndUnzip']));
        $mock->method('checkPHPPath')->will($this->returnValue($args['checkPHPPath']));
        $mock->method('checkPHPVersion')->will($this->returnValue($args['checkPHPVersion']));
        $mock->method('checkUpgradePackages')->will($this->returnValue($args['checkUpgradePackages']));
        $mock->method('log')->will($this->returnValue(null));
        $result = $mock->preflightChecks();
        $this->assertEquals($expected, $result);
    }


    public function preflightChecksData()
    {
        return [
            [
                [
                    'currentDir' => false,
                    'instanceDir' => false,
                    'checkSugarInstance' => false,
                    'checkZipAndUnzip' => false,
                    'checkPHPPath' => false,
                    'checkPHPVersion' => false,
                    'checkUpgradePackages' => false,
                ],
                false,
            ],
            [
                [
                    'currentDir' => true,
                    'instanceDir' => false,
                    'checkSugarInstance' => false,
                    'checkZipAndUnzip' => false,
                    'checkPHPPath' => false,
                    'checkPHPVersion' => false,
                    'checkUpgradePackages' => false,
                ],
                false,
            ],
            [
                [
                    'currentDir' => true,
                    'instanceDir' => true,
                    'checkSugarInstance' => false,
                    'checkZipAndUnzip' => false,
                    'checkPHPPath' => false,
                    'checkPHPVersion' => false,
                    'checkUpgradePackages' => false,
                ],
                false,
            ],
            [
                [
                    'currentDir' => true,
                    'instanceDir' => true,
                    'checkSugarInstance' => true,
                    'checkZipAndUnzip' => false,
                    'checkPHPPath' => false,
                    'checkPHPVersion' => false,
                    'checkUpgradePackages' => false,
                ],
                false,
            ],
            [
                [
                    'currentDir' => true,
                    'instanceDir' => true,
                    'checkSugarInstance' => true,
                    'checkZipAndUnzip' => true,
                    'checkPHPPath' => false,
                    'checkPHPVersion' => false,
                    'checkUpgradePackages' => false,
                ],
                false,
            ],
            [
                [
                    'currentDir' => true,
                    'instanceDir' => true,
                    'checkSugarInstance' => true,
                    'checkZipAndUnzip' => true,
                    'checkPHPPath' => true,
                    'checkPHPVersion' => false,
                    'checkUpgradePackages' => false,
                ],
                false,
            ],
            [
                [
                    'currentDir' => true,
                    'instanceDir' => true,
                    'checkSugarInstance' => true,
                    'checkZipAndUnzip' => true,
                    'checkPHPPath' => true,
                    'checkPHPVersion' => true,
                    'checkUpgradePackages' => false,
                ],
                false,
            ],
            [
                [
                    'currentDir' => true,
                    'instanceDir' => true,
                    'checkSugarInstance' => true,
                    'checkZipAndUnzip' => true,
                    'checkPHPPath' => true,
                    'checkPHPVersion' => true,
                    'checkUpgradePackages' => true,
                ],
                true,
            ],
        ];
    }

    /**
     * @dataProvider initData
     * @param $args
     * @param $isDir
     * @param $mkDir
     * @param $collectUpgrades
     * @param $expected
     */
    public function testInit($args, $isDir, $mkDir, $collectUpgrades, $expected)
    {
        $mock = $this->getUCMock(['isDir', 'mkDir', 'collectUpgrades', 'print', 'getCurrentSugarVersion', 'parseArgs']);
        $mock->method('isDir')->will($this->returnValue($isDir));
        $mock->method('mkDir')->will($this->returnValue($mkDir));
        $mock->method('parseArgs')->will($this->returnValue(null));
        $mock->method('print')->will($this->returnValue(null));
        $mock->method('getCurrentSugarVersion')->will($this->returnValue(null));
        $mock->method('collectUpgrades')->will($this->returnValue($collectUpgrades));
        $result = $mock->init($args);
        $this->assertEquals($expected, $result);
    }


    public function initData()
    {
        return [
            [
                'argv' => [],
                'isDir_return' => false,
                'mkDir_return' => false,
                'collectUpgrades_return' => false,
                'expected' => false,
            ],
            [
                'argv' => [],
                'isDir_return' => true,
                'mkDir_return' => false,
                'collectUpgrades_return' => false,
                'expected' => false,
            ],
            [
                'argv' => [],
                'isDir_return' => true,
                'mkDir_return' => false,
                'collectUpgrades_return' => true,
                'expected' => true,
            ],
            [
                'argv' => [],
                'isDir_return' => false,
                'mkDir_return' => true,
                'collectUpgrades_return' => true,
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider utilsData
     * @param $exitCode
     * @param $expected
     */
    public function testCheckForInstalledUtility($exitCode, $expected)
    {
        $mock = $this->getUCMock(['runCmd']);
        $mock->expects($this->once())->method('runCmd')->will($this->returnValue(['', $exitCode]));
        $result = $mock->checkForInstalledUtility('testutil');
        $this->assertEquals($expected, $result);
    }


    public function utilsData()
    {
        return [
            [
                'exitCode' => 0,
                'expected' => true,
            ],
            [
                'exitCode' => 1,
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider unzipData
     * @param $zipPath
     * @param $dest
     * @param $fileExists
     * @param $cmdSucceeded
     * @param $expected
     */
    public function testUnzip($zipPath, $dest, $fileExists, $cmdResult, $expected)
    {
        $mock = $this->getUCMock(['log', 'fileExists', 'isDir', 'runCmd']);
        $mock->method('log')->will($this->returnValue(null));
        $mock->method('fileExists')->will($this->returnValue($fileExists));
        $mock->method('isDir')->will($this->returnValue(!empty($dest)));
        $mock->method('runCmd')->will($this->returnValue($cmdResult));
        $unzipResult = $mock->unzip($zipPath, $dest);
        $this->assertEquals($expected, $unzipResult);
    }


    public function unzipData()
    {
        return [
            [
                'zipPath' => '/phoney/zip/path.zip',
                'unzipDest' => '/invalid/destination/path',
                'fileExists' => false,
                'cmdResult' => ['', 1],
                'expected' => false
            ],
            [
                'zipPath' => '/phoney/zip/path.zip',
                'unzipDest' => '/invalid/destination/path',
                'fileExists' => true,
                'cmdResult' => ['', 1],
                'expected' => false
            ],
            [
                'zipPath' => '',
                'unzipDest' => '/invalid/destination/path',
                'fileExists' => false,
                'cmdResult' => ['', 1],
                'expected' => false
            ],
            [
                'zipPath' => 'valid/zip/path',
                'unzipDest' => '',
                'fileExists' => true,
                'cmdResult' => ['', 1],
                'expected' => false
            ],
            [
                'zipPath' => 'valid/zip/path',
                'unzipDest' => '/invalid/destination/path',
                'fileExists' => true,
                'cmdResult' => ['', 0],
                'expected' => true
            ],
            [
                'zipPath' => 'valid/zip/path',
                'unzipDest' => '/valid/dest/',
                'fileExists' => true,
                'cmdResult' => ['', 0],
                'expected' => true
            ],
        ];
    }


    /**
     * @dataProvider arguments
     * @param $args
     */
    public function testParseArgs($args, $missingTrailingSlash)
    {
        $mock = $this->getUCMock(null);
        $defaultArg1 = $mock->instancePath;
        $defaultArg2 = $mock->phpPath;
        $mock->parseArgs($args);

        if (isset($args[1])) {
            if ($missingTrailingSlash) {
                $this->assertEquals($args[1] . '/', "$mock->instancePath");
            } else {
                $this->assertEquals($args[1], $mock->instancePath);
            }
        } else {
            $this->assertEquals($defaultArg1, $mock->instancePath);
        }

        if (isset($args[2])) {
            $this->assertEquals($args[2], $mock->phpPath);
        } else {
            $this->assertEquals($defaultArg2, $mock->phpPath);
        }
    }


    public function arguments()
    {
        return [
            [['UpgradeCoordinator.php'], false],
            [['UpgradeCoordinator.php', '/some/invalid/path'], true],
            [['UpgradeCoordinator.php', '/some/invalid/path/'], false],
            [['UpgradeCoordinator.php', '/some/invalid/path', 'another/invalid/path'], true],
            [['UpgradeCoordinator.php', '/some/invalid/path/', 'another/invalid/path'], false],
        ];
    }


    /**
     * @dataProvider sugarVersions
     * @param $version
     * @param $expected
     */
    public function testNextUpgradeVersionIsHigherThanCurrent($current, $next, $expected)
    {
        $mock = $this->getUCMock(null);
        $mock->currentSugarVersion = $current;
        $lower = $mock->nextUpgradeVersionIsHigherThanCurrent($next);
        $this->assertEquals($expected, $lower);
    }


    public function sugarVersions()
    {
        return [
            [
                'currentVersion' => '7.9.4.0',
                'nextVersion' => '8.0.4',
                'expected' => true,
            ],
            [
                'currentVersion' => '8.0.2',
                'nextVersion' => '8.0.4',
                'expected' => true,
            ],
            [
                'currentVersion' => '9.2.0',
                'nextVersion' => 'final',
                'expected' => true,
            ],
            [
                'currentVersion' => '9.1.0',
                'nextVersion' => '8.0.4',
                'expected' => false,
            ],
            [
                'currentVersion' => '9.1.0',
                'nextVersion' => '9.1.0',
                'expected' => false,
            ],
        ];
    }


    /**
     * @dataProvider upgradeDirectories
     * @param $dirs
     * @param $expected
     */
    public function testCollectUpgrades($dirs, $expected)
    {
        $mock = $this->getUCMock(['log', 'scanDir']);
        if (empty($dirs)) {
            $mock->expects($this->once())->method('log')->will($this->returnValue(null));
        } else {
            $mock->expects($this->never())->method('log')->will($this->returnValue(null));
        }
        $mock->expects($this->once())->method('scanDir')->will($this->returnValue($dirs));
        $mock->collectUpgrades();
        $this->assertEquals('final', array_pop($mock->upgrades));
    }


    public function upgradeDirectories()
    {
        return [
            [
                'dirs' => ['8.0.4', 'final', '9.1.0', '9.2.0'],
                'expected' => true,
            ],
            [
                'dirs' => ['final'],
                'expected' => true,
            ],
            [
                'dirs' => [],
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider phpVersions
     */
    public function testCheckPHPVersion($phpVersion, $useEmptyDefault, $expected)
    {
        $mock = $this->getUCMock(['log', 'runCmd']);
        if ($useEmptyDefault) {
            $mock->phpVersionRequired = '';
        }
        $mock->expects($this->once())->method('log')->will($this->returnValue(null));
        $mock->expects($this->any())->method('runCmd')->will($this->returnValue([$phpVersion, 0]));
        $this->assertEquals($expected, $mock->checkPHPVersion());
    }


    public function phpVersions()
    {
        return [
            [
                'version' => 'PHP 7.1.29 (cli) (built: May 21 2019 20:05:17) ( NTS )',
                'useEmptyDefault' => false,
                'expected' => true,
            ],
            [
                'version' => 'PHP 5.1.14 (cli) (built: May 21 2019 20:05:17) ( NTS )',
                'useEmptyDefault' => false,
                'expected' => false,
            ],
            [
                'version' => 'PHP 5.1.14 (cli) (built: May 21 2019 20:05:17) ( NTS )',
                'useEmptyDefault' => true,
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider directoryPaths
     */
    public function testDeleteDirectory($path, $isWritable_return, $isDir_return, $exitCode, $expected)
    {
        $mock = $this->getUCMock(['isWritable', 'isDir', 'runCmd', 'log']);
        $mock->expects($this->any())->method('isWritable')->will($this->returnValue($isWritable_return));
        $mock->expects($this->any())->method('isDir')->will($this->returnValue($isDir_return));
        $mock->expects($this->any())->method('runCmd')->will($this->returnValue(['', $exitCode]));
        $mock->expects($this->any())->method('log')->will($this->returnValue(null));
        $this->assertEquals($expected, $mock->deleteDirectory($path));
    }



    public function directoryPaths()
    {
        return [
            [
                'dir' => '',
                'isWritable_return' => false,
                'is_dir_return' => false,
                'exitCode' => 0,
                'expected' => false
            ],
            [
                'dir' => 'invalid/path/for/you',
                'isWritable_return' => false,
                'is_dir_return' => false,
                'exitCode' => 0,
                'expected' => false
            ],
            [
                'dir' => '/Not/Writable/Today',
                'isWritable_return' => false,
                'is_dir_return' => true,
                'exitCode' => 0,
                'expected' => false
            ],
            [
                'dir' => '/directory/is/writable',
                'isWritable_return' => true,
                'is_dir_return' => true,
                'exitCode' => 0,
                'expected' => true
            ],
            [
                'dir' => '/directory/is/writable',
                'isWritable_return' => true,
                'is_dir_return' => true,
                'exitCode' => 1,
                'expected' => false
            ],
        ];
    }


    /**
     * @dataProvider dirIsAccessibleData
     * @param $isDir
     * @param $isReadable
     * @param $isWritable
     * @param $expected
     */
    public function testDirIsAccessible($isDir, $isReadable, $isWritable, $expected)
    {
        $mock = $this->getUCMock(['isDir', 'isReadable', 'isWritable', 'log']);
        $mock->method('isDir')->will($this->returnValue($isDir));
        $mock->method('isReadable')->will($this->returnValue($isReadable));
        $mock->method('isWritable')->will($this->returnValue($isWritable));
        $mock->method('log')->will($this->returnValue(null));
        $result = $mock->dirIsAccessible('/some/path');
        $this->assertEquals($expected, $result);
    }


    public function dirIsAccessibleData()
    {
        return [
            [
                'isDir' => false,
                'isReadable' => false,
                'isWritable' => false,
                'expected' => false,
            ],
            [
                'isDir' => true,
                'isReadable' => false,
                'isWritable' => false,
                'expected' => false,
            ],
            [
                'isDir' => true,
                'isReadable' => true,
                'isWritable' => false,
                'expected' => false,
            ],
            [
                'isDir' => true,
                'isReadable' => true,
                'isWritable' => true,
                'expected' => true,
            ],
        ];
    }


    public function testCurrentDirIsAccessible()
    {
        $mock1 = $this->getUCMock(['dirIsAccessible']);
        $mock2 = $this->getUCMock(['dirIsAccessible']);

        $mock1->method('dirIsAccessible')->will($this->returnValue(true));
        $mock2->method('dirIsAccessible')->will($this->returnValue(false));

        $result1 = $mock1->currentDirIsAccessible();
        $result2 = $mock2->currentDirIsAccessible();
        $this->assertEquals(true, $result1);
        $this->assertEquals(false, $result2);
    }


    public function testInstanceDirIsAccessible()
    {
        $mock1 = $this->getUCMock(['dirIsAccessible']);
        $mock2 = $this->getUCMock(['dirIsAccessible']);

        $mock1->method('dirIsAccessible')->will($this->returnValue(true));
        $mock2->method('dirIsAccessible')->will($this->returnValue(false));

        $result1 = $mock1->instanceDirIsAccessible();
        $result2 = $mock2->instanceDirIsAccessible();
        $this->assertEquals(true, $result1);
        $this->assertEquals(false, $result2);
    }


    public function getUCMock($methods): MockObject
    {
        $builder = $this->getMockBuilder(UpgradeCoordinator::class);
        $builder->setMethods($methods);
        return $builder->getMock();
    }
}