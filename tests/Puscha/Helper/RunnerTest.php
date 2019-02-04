<?php

namespace Puscha\Helper;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Puscha\Scm\ScmChange;

class RunnerTest extends TestCase
{
    public function testSortChanges()
    {
        $changes = array(
            new ScmChange(ScmChange::TYPE_ADDED, 'a'),
            new ScmChange(ScmChange::TYPE_DELETED, 'b'),
            new ScmChange(ScmChange::TYPE_MODIFIED, 'c'),
            new ScmChange(ScmChange::TYPE_ADDED, 'a/a1/a2'),
            new ScmChange(ScmChange::TYPE_ADDED, 'a/a1'),
            new ScmChange(ScmChange::TYPE_ADDED, 'a/a3/a4'),
            new ScmChange(ScmChange::TYPE_ADDED, 'a/a3'),
            new ScmChange(ScmChange::TYPE_ADDED, 'a'),
            new ScmChange(ScmChange::TYPE_MODIFIED, 'e/g'),
            new ScmChange(ScmChange::TYPE_MODIFIED, 'e/e'),
            new ScmChange(ScmChange::TYPE_MODIFIED, 'e/f'),
            new ScmChange(ScmChange::TYPE_DELETED, 'a'),
            new ScmChange(ScmChange::TYPE_DELETED, 'a/a1/a2'),
            new ScmChange(ScmChange::TYPE_DELETED, 'a/a1'),
            new ScmChange(ScmChange::TYPE_MODIFIED, 'd'),
            new ScmChange(ScmChange::TYPE_ADDED, 'h'),
        );

        /*
         * Ugly override of the class to test, so that we can call sortChanges without a real instance
         */
        $runner = new class(null, null, null, null, null, null, new NullLogger()) extends Runner {
            public static function testableSortChanges($changes)
            {
                return Runner::sortChanges($changes);
            }
        };
        $sortedChanges = $runner::testableSortChanges($changes);

        // Checking contents
        $this->assertCount(count($changes), $sortedChanges);
        foreach ($changes as $change) {
            $this->assertContains($change, $sortedChanges);
        }

        // Checking order
        /** @var ScmChange $previousChange */
        $previousChange = null;
        /** @var ScmChange $change */
        foreach ($sortedChanges as $change) {
            if ($previousChange) {
                // Checking ordering of types
                if ($change->getType() == ScmChange::TYPE_ADDED) {
                    $this->assertContains($previousChange->getType(), [ScmChange::TYPE_ADDED]);
                } elseif ($change->getType() == ScmChange::TYPE_MODIFIED) {
                    $this->assertContains($previousChange->getType(), [ScmChange::TYPE_ADDED, ScmChange::TYPE_MODIFIED]);
                } elseif ($change->getType() == ScmChange::TYPE_DELETED) {
                    $this->assertContains($previousChange->getType(), [ScmChange::TYPE_ADDED, ScmChange::TYPE_MODIFIED, ScmChange::TYPE_DELETED]);
                }

                // Checking ordering of files
                if ($previousChange->getType() == $change->getType()) {
                    if ($change->getType() == ScmChange::TYPE_ADDED) {
                        $this->assertGreaterThanOrEqual(0, strcmp($change->getFile(), $previousChange->getFile()));
                    } elseif ($change->getType() == ScmChange::TYPE_MODIFIED) {
                        $this->assertGreaterThanOrEqual(0, strcmp($change->getFile(), $previousChange->getFile()));
                    } elseif ($change->getType() == ScmChange::TYPE_DELETED) {
                        $this->assertLessThanOrEqual(0, strcmp($change->getFile(), $previousChange->getFile()));
                    }
                }
            }

            $previousChange = $change;
        }

        // Checking new position of some items
        $item = new ScmChange(ScmChange::TYPE_DELETED, 'a');
        $this->assertItemIndex($item, $sortedChanges, 15);
        $item = new ScmChange(ScmChange::TYPE_ADDED, 'a/a1');
        $this->assertItemIndex($item, $sortedChanges, 2);
        $item = new ScmChange(ScmChange::TYPE_MODIFIED, 'e/e');
        $this->assertItemIndex($item, $sortedChanges, 9);
        $item = new ScmChange(ScmChange::TYPE_DELETED, 'b');
        $this->assertItemIndex($item, $sortedChanges, 12);
        $item = new ScmChange(ScmChange::TYPE_ADDED, 'a/a3/a4');
        $this->assertItemIndex($item, $sortedChanges, 5);
    }

    protected function assertItemIndex($item, $array, $index)
    {
        $r = array_filter($array, function ($v) use ($item) {
            return ($item == $v);
        });
        $this->assertCount(1, $r);
        $this->assertEquals($index, array_keys($r)[0]);
    }
}
