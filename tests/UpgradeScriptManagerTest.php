<?php
declare(strict_types=1);
require_once('lib/UpgradeScriptManager.php');
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject as MockObject;
use Sugarcrm\Sugarcrm\UpgradeCoordinator\lib\UpgradeScriptManager as UpgradeScriptManager;
use Sugarcrm\Sugarcrm\UpgradeCoordinator\lib\UpgradeScript as UpgradeScript;

class UpgradeScriptManagerTest extends TestCase
{
    /**
     * @dataProvider scriptHasBeenRunBeforeData
     * @param $fqcn
     * @param $previous
     * @param $expected
     */
    public function testScriptHasBeenRunBefore($fqcn, $previous, $expected)
    {
        $mock = $this->getManagerMock(null);
        $mock->previouslyRunScripts = $previous;
        $result = $mock->scriptHasBeenRunBefore($fqcn);
        $this->assertEquals($expected, $result);
    }


    public function scriptHasBeenRunBeforeData()
    {
        return [
            [
                'fqcn' => '',
                'previous' => array(),
                'expected' => false,
            ],
            [
                'fqcn' => '\some\f\q\c\n',
                'previous' => array('\some\f\q\c\n'),
                'expected' => true,
            ],
            [
                'fqcn' => '\Another\Class\Name',
                'previous' => array('\some\f\q\c\n', '\Previously\Run\Class'),
                'expected' => false,
            ],
            [
                'fqcn' => '\Another\Class\Name',
                'previous' => array('\some\f\q\c\n', '\Another\Class\Name', '\Previously\Run\Class'),
                'expected' => true,
            ],
        ];
    }


    /**
     * @dataProvider confirmStageData
     * @param $stage
     * @param $expected
     */
    public function testConfirmStage($stage, $expected)
    {
        $mock = $this->getManagerMock(null);
        $mock->stage = $stage;

        try {
            $result = $mock->confirmStage();
        } catch (\Exception $e) {
            $this->assertEquals($expected, false);
        }
        $this->assertEquals($expected, $result);
    }


    public function confirmStageData()
    {
        return [
            [
                'stage' => '',
                'expected' => false,
            ],
            [
                'stage' => 'anything',
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider readLogFileData
     * @param $fileExists
     * @param $fileContents
     */
    public function testReadLogFile($fileExists, $fileContents)
    {
        $mock = $this->getManagerMock(['fileExists', 'getFileContentsAsArray']);
        $mock->expects($this->once())->method('fileExists')->will($this->returnValue($fileExists));
        if ($fileExists) {
            $mock->expects($this->once())->method('getFileContentsAsArray')->will($this->returnValue($fileContents));
        } else {
            $mock->expects($this->never())->method('getFileContentsAsArray');
        }

        $mock->readLogFile();
        $this->assertEquals($fileContents, $mock->previouslyRunScripts);
    }


    public function readLogFileData()
    {
        return [
            [
                'fileExists' => false,
                'fileContents' => array(),
            ],
            [
                'fileExists' => true,
                'fileContents' => array(),
            ],
            [
                'fileExists' => true,
                'fileContents' => array('scriptFile1.php', 'scriptFile2.php'),
            ],
        ];
    }


    public function testGetUpgradeScriptsDirPath()
    {
        $mock = $this->getManagerMock(null);
        $mock->upgradeScriptsDir = '/some/path';
        $mock->stage = 'pre';
        $result = $mock->getUpgradeScriptsDirPath();
        $this->assertEquals("{$mock->upgradeScriptsDir}/{$mock->stage}", $result);
    }


    public function testSortScriptObjects()
    {
        $mock = $this->getManagerMock(['getUpgradeScriptsDirPath']);
        $mock->scriptObjects = $this->getScriptObjects(5);
        $mock->scriptObjects[0]->priority = 500;
        $mock->scriptObjects[0]->method('getPriority')->will($this->returnValue($mock->scriptObjects[0]->priority));
        $mock->scriptObjects[1]->priority = 50;
        $mock->scriptObjects[1]->method('getPriority')->will($this->returnValue($mock->scriptObjects[1]->priority));
        $mock->scriptObjects[2]->priority = 250;
        $mock->scriptObjects[2]->method('getPriority')->will($this->returnValue($mock->scriptObjects[2]->priority));
        $mock->scriptObjects[3]->priority = 350;
        $mock->scriptObjects[3]->method('getPriority')->will($this->returnValue($mock->scriptObjects[3]->priority));
        $mock->scriptObjects[4]->priority = 50;
        $mock->scriptObjects[4]->method('getPriority')->will($this->returnValue($mock->scriptObjects[4]->priority));

        $mock->sortScriptObjects();
        $this->assertEquals(50, $mock->scriptObjects[0]->priority);
        $this->assertEquals(50, $mock->scriptObjects[1]->priority);
        $this->assertEquals(250, $mock->scriptObjects[2]->priority);
        $this->assertEquals(350, $mock->scriptObjects[3]->priority);
        $this->assertEquals(500, $mock->scriptObjects[4]->priority);
    }


    /**
     * @dataProvider collectScriptsData
     * @param $fileExists
     * @param $isDir
     * @param $scriptFiles
     */
    public function testCollectScripts($fileExists, $isDir, $scriptFiles)
    {
        $mock = $this->getManagerMock(['getUpgradeScriptsDirPath', 'fileExists', 'isDir', 'scanDir']);
        $mock->expects($this->once())->method('getUpgradeScriptsDirPath')->will($this->returnValue('/some/path'));

        if ($fileExists) {
            $mock->expects($this->once())->method('fileExists')->will($this->returnValue($fileExists));
            $mock->expects($this->once())->method('isDir')->will($this->returnValue($isDir));
        } else {
            $mock->expects($this->once())->method('fileExists')->will($this->returnValue($fileExists));
            $mock->expects($this->never())->method('isDir');
            $mock->expects($this->never())->method('scanDir');
        }

        if ($fileExists && $isDir) {
            $mock->expects($this->once())->method('scanDir')->will($this->returnValue($scriptFiles));
        }

        try {
            $mock->collectScripts();
            $this->assertEquals($scriptFiles, $mock->scriptFileNames);
        } catch (\Exception $e) {
            $this->assertInstanceOf('Exception', $e);
        }
    }


    public function collectScriptsData()
    {
        return [
            [
                'fileExists' => false,
                'isDir' => false,
                'scanDir' => array(),
            ],
            [
                'fileExists' => true,
                'isDir' => false,
                'scanDir' => array(),
            ],
            [
                'fileExists' => true,
                'isDir' => true,
                'scanDir' => array(),
            ],
            [
                'fileExists' => true,
                'isDir' => true,
                'scanDir' => array('scriptFile1.php', 'scriptFile2.php'),
            ],
        ];
    }


    /**
     * @dataProvider instantiateScriptObjectsData
     * @param $scriptFileNames
     * @param $classExists
     * @param $scriptObjects
     * @param $runBefore
     * @param $isUpgradeScript
     */
    public function testInstantiateScriptObjects($scriptFileNames, $classExists, $scriptObjects, $runBefore, $isUpgradeScript)
    {
        $methods = [
            'readLogFile',
            'getUpgradeScriptsDirPath',
            'requireScriptFile',
            'classExists',
            'instantiateScriptObject',
            'scriptHasBeenRunBefore',
            'isUpgradeScriptObject',
        ];
        $mock = $this->getManagerMock($methods);
        $mock->scriptFileNames = $scriptFileNames;
        $mock->expects($this->once())->method('readLogFile')->will($this->returnValue(null));

        if (!empty($scriptObjects)) {

            if ($classExists) {
                if ($isUpgradeScript) {
                    $mock->expects($this->exactly(count($scriptObjects)))->method('getUpgradeScriptsDirPath')->will($this->returnValue(null));
                    $mock->expects($this->exactly(count($scriptObjects)))->method('requireScriptFile')->will($this->returnValue(null));
                    $mock->expects($this->exactly(count($scriptObjects)))->method('classExists')->will($this->returnValue($classExists));
                    $mock->expects($this->exactly(count($scriptObjects)))->method('instantiateScriptObject')->willReturnOnConsecutiveCalls(...$scriptObjects);
                    $mock->expects($this->exactly(count($scriptObjects)))->method('isUpgradeScriptObject')->will($this->returnValue($isUpgradeScript));
                } else {
                    $mock->expects($this->once())->method('getUpgradeScriptsDirPath')->will($this->returnValue(null));
                    $mock->expects($this->once())->method('requireScriptFile')->will($this->returnValue(null));
                    $mock->expects($this->once())->method('classExists')->will($this->returnValue($classExists));
                    $mock->expects($this->once())->method('instantiateScriptObject')->willReturnOnConsecutiveCalls(...$scriptObjects);
                    $mock->expects($this->once())->method('isUpgradeScriptObject')->will($this->returnValue($isUpgradeScript));
                }
            } else {
                $mock->expects($this->once())->method('getUpgradeScriptsDirPath')->will($this->returnValue(null));
                $mock->expects($this->once())->method('requireScriptFile')->will($this->returnValue(null));
                $mock->expects($this->once())->method('classExists')->will($this->returnValue($classExists));
                $mock->expects($this->never())->method('instantiateScriptObject');
                $mock->expects($this->never())->method('isUpgradeScriptObject');
            }
        } else {
            $mock->expects($this->never())->method('getUpgradeScriptsDirPath')->will($this->returnValue(null));
            $mock->expects($this->never())->method('requireScriptFile')->will($this->returnValue(null));
            $mock->expects($this->never())->method('classExists')->will($this->returnValue($classExists));
            $mock->expects($this->never())->method('instantiateScriptObject');
            $mock->expects($this->never())->method('isUpgradeScriptObject');
        }

        $mock->method('scriptHasBeenRunBefore')->will($this->returnValue($runBefore));

        try {
            $mock->instantiateScriptObjects();
        } catch (\Exception $e) {
            $this->assertInstanceOf('Exception', $e);
        }
    }


    public function instantiateScriptObjectsData()
    {
        return [
            [
                'scriptFileNames' => array(),
                'classExists' => false,
                'scriptObjects' => array(),
                'runBefore' => false,
                'isUpgradeScript' => false,
            ],
            [
                'scriptFileNames' => array('upgrade_script_1.php', 'upgrade_script_2.php'),
                'classExists' => false,
                'scriptObjects' => $this->getScriptObjects(2),
                'runBefore' => false,
                'isUpgradeScript' => false,
            ],
            [
                'scriptFileNames' => array('upgrade_script_1.php', 'upgrade_script_2.php'),
                'classExists' => true,
                'scriptObjects' => $this->getScriptObjects(2),
                'runBefore' => false,
                'isUpgradeScript' => false,
            ],
            [
                'scriptFileNames' => array('upgrade_script_1.php', 'upgrade_script_2.php'),
                'classExists' => true,
                'scriptObjects' => $this->getScriptObjects(2),
                'runBefore' => true,
                'isUpgradeScript' => false,
            ],
            [
                'scriptFileNames' => array('upgrade_script_1.php', 'upgrade_script_2.php'),
                'classExists' => true,
                'scriptObjects' => $this->getScriptObjects(2),
                'runBefore' => true,
                'isUpgradeScript' => true,
            ],
            [
                'scriptFileNames' => array('upgrade_script_1.php', 'upgrade_script_2.php'),
                'classExists' => true,
                'scriptObjects' => $this->getScriptObjects(2),
                'runBefore' => false,
                'isUpgradeScript' => true,
            ],
        ];
    }


    /**
     * @dataProvider executeScriptsData
     * @param $stage
     * @param $scripts
     * @param $scriptObjects
     */
    public function testExecuteScripts($stage, $scripts, $scriptObjects)
    {
        $methods = ['confirmStage', 'collectScripts', 'instantiateScriptObjects', 'sortScriptObjects', 'logScript'];
        $args = ['scriptname.php', '8.0.4', $stage];
        $mock = $this->getManagerMock($methods, $args);
        if (empty($stage)) {
            $mock->expects($this->once())->method('confirmStage')->willThrowException(new \Exception());
        } else {
            $mock->expects($this->once())->method('confirmStage')->will($this->returnValue(null));
        }

        if (empty($scripts)) {
            $mock->method('collectScripts')->willThrowException(new \Exception());
        } else {
            $mock->method('collectScripts')->will($this->returnValue($scripts));
        }

        if (empty($scriptObjects)) {
            $mock->method('instantiateScriptObjects')->willThrowException(new \Exception());
        } else {
            $mock->method('instantiateScriptObjects')->will($this->returnValue(null));
            $mock->scriptObjects = $scriptObjects;
        }

        $mock->method('sortScriptObjects')->will($this->returnValue(null));

        try {
            $mock->executeScripts();
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }


    public function executeScriptsData()
    {
        return [
            [
                'stage' => '',
                'scripts' => array(),
                'scriptObjects' => array(),
            ],
            [
                'stage' => 'pre',
                'scripts' => array(),
                'scriptObjects' => array(),
            ],
            [
                'stage' => 'pre',
                'scripts' => array('someScript.php', 'anotherScript.php'),
                'scriptObjects' => array(),
            ],
            [
                'stage' => 'pre',
                'scripts' => array('someScript.php', 'anotherScript.php'),
                'scriptObjects' => $this->getScriptObjects(),
            ],
        ];
    }


    public function getManagerMock($methods, $args=array()): MockObject
    {
        $builder = $this->getMockBuilder(UpgradeScriptManager::class);
        $builder->setMethods($methods);

        if (!empty($args)) {
            $builder->setConstructorArgs([$args]);
        } else {
            $builder->disableOriginalConstructor();
        }
        return $builder->getMock();
    }

    public function getScriptObjects($count = 1)
    {
        $scriptObjects = array();
        for ($i = 0; $i < $count; $i++) {
            $obj = $this->getScriptMock(['execute', 'getPriority']);
            $obj->method('execute')->will($this->returnValue(null));
            $scriptObjects[] = $obj;
        }
        return $scriptObjects;
    }

    public function getScriptMock($methods): MockObject
    {
        $builder = $this->getMockBuilder(UpgradeScript::class);
        $builder->setMethods($methods);
        return $builder->getMock();
    }
}