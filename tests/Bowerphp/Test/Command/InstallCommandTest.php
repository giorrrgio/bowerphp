<?php

namespace Bowerphp\Test\Command;

use Bowerphp\Console\Application;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group functional
 */
class InstallCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $application = new Application();
        $commandTester = new CommandTester($command = $application->get('install'));
        $commandTester->execute(array('command' => $command->getName(), 'package' => 'jquery'), array('decorated' => false));

        $this->assertRegExp('/jquery#2/', $commandTester->getDisplay());
        $this->assertFileExists(getcwd() . '/bower_components/jquery/.bower.json');
        $this->assertFileExists(getcwd() . '/bower_components/jquery/jquery.js');
    }

    public function testExecuteVerbose()
    {
        $application = new Application();
        $commandTester = new CommandTester($command = $application->get('install'));
        $commandTester->execute(array('command' => $command->getName(), 'package' => 'jquery'), array('decorated' => false, 'verbosity' => OutputInterface::VERBOSITY_DEBUG));

        $this->assertRegExp('/jquery#2/', $commandTester->getDisplay());
        $this->assertFileExists(getcwd() . '/bower_components/jquery/.bower.json');
        $this->assertFileExists(getcwd() . '/bower_components/jquery/jquery.js');
    }

    public function tearDown()
    {
        $dir = getcwd() . '/bower_components/';
        if (is_dir($dir)) {
            // see http://stackoverflow.com/a/15111679/369194
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $path) {
                $path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
            }
            rmdir($dir);
        }
    }
}
