<?php

namespace Puscha\Scm;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GitScmTest extends TestCase
{
    public function testParseChange()
    {
        /*
         * Ugly override of the class to test, so that we can call parseChange without a real repository
         */
        $git = new class(null, new NullLogger()) extends GitScm
        {
            public function __construct($path, $logger)
            {
                $this->path = $path;
                $this->logger = $logger;
            }

            public function testableParseChange($changes)
            {
                return $this->parseChange($changes);
            }
        };

        $changes = array(
            'A	a' => new ScmChange(ScmChange::TYPE_ADDED, 'a'),
            'D	a/b' => new ScmChange(ScmChange::TYPE_DELETED, 'a/b'),
            'M	"bla bl\303\251.txt"' => new ScmChange(ScmChange::TYPE_MODIFIED, 'bla blé.txt'),
        );
        foreach ($changes as $string => $expectedResult) {
            $result = $git->testableParseChange($string);
            $this->assertNotFalse($result);
            $this->assertEquals($expectedResult, $result);
        }
    }
}
