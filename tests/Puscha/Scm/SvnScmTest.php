<?php

namespace Puscha\Scm;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SvnScmTest extends TestCase
{
    public function testParseChange()
    {
        /*
         * Ugly override of the class to test, so that we can call parseChange without a real repository
         */
        $git = new class(null, new NullLogger()) extends SvnScm
        {
            public function __construct($path, $logger)
            {
                $this->path = $path;
                $this->logger = $logger;

                $this->repositoryRoot = 'file:///test';
                $this->repositoryTree = 'trunk';
            }

            public function testableParseChange($changes)
            {
                return $this->parseChange($changes);
            }
        };

        $changes = array(
            'A       file:///test/trunk/a' => new ScmChange(ScmChange::TYPE_ADDED, 'a'),
            'D       file:///test/trunk/a/b' => new ScmChange(ScmChange::TYPE_DELETED, 'a/b'),
            'M       file:///test/trunk/bla%20bl%C3%A9.txt' => new ScmChange(ScmChange::TYPE_MODIFIED, 'bla blé.txt'),
            'M?      file:///test/trunk/a' => new ScmChange(ScmChange::TYPE_MODIFIED, 'a'),
        );
        foreach ($changes as $string => $expectedResult) {
            $result = $git->testableParseChange($string);
            $this->assertNotFalse($result);
            $this->assertEquals($expectedResult->getType(), $result->getType());
            $this->assertEquals($expectedResult->getFile(), $result->getFile());
        }
    }
}
