<?php

/**
 * Here are some examples on how to submit Tasks to the Queue :
 */

require __DIR__.'/../vendor/autoload.php';

use Libcast\JobQueue\Task\Task;
use Libcast\JobQueue\Job\DummyJob;
use Libcast\JobQueue\Job\FaultyJob;
use Libcast\JobQueue\Job\FailingJob;
use Libcast\JobQueue\Queue\QueueFactory;

// ----------

$basic = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'aaaaaa',
            'destination' => '/tmp/dummytest1',
        )
);

// ----------

$faulty = new Task(
        new FaultyJob,
        array(),
        array(
            'dummytext' => 'bbbbbb',
            'destination' => '/tmp/faultytest2',
        )
);

// ----------

$failing = new Task(
        new FailingJob,
        array(),
        array(
            'dummytext' => 'failed',
            'destination' => '/tmp/failingtest',
        )
);

// ----------

$parent_basic = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'parent',
            'destination' => '/tmp/dummytest_with_child',
        )
);

$child_basic = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'child',
            'destination' => '/tmp/dummytest_with_child',
        )
);

$parent_basic->addChild($child_basic);

// ----------

$parent_nested = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'parent',
            'destination' => '/tmp/dummytest_nested',
        )
);

    $child_nested_11 = new Task(
            new DummyJob,
            array(),
            array(
                'dummytext' => 'child 11',
                'destination' => '/tmp/dummytest_nested',
            )
    );

    $child_nested_12 = new Task(
            new DummyJob,
            array(),
            array(
                'dummytext' => 'child 12',
                'destination' => '/tmp/dummytest_nested',
            )
    );

        $child_nested_21 = new Task(
                new FaultyJob,
                array(),
                array(
                    'dummytext' => 'child 21',
                    'destination' => '/tmp/dummytest_nested',
                )
        );

        $child_nested_22 = new Task(
                new DummyJob,
                array(),
                array(
                    'dummytext' => 'child 22',
                    'destination' => '/tmp/dummytest_nested',
                )
        );

            $child_nested_3 = new Task(
                    new FailingJob,
                    array(),
                    array(
                        'dummytext' => 'FAILING',
                        'destination' => '/tmp/dummytest_nested',
                    )
            );
            
            $child_nested_22->addChild($child_nested_3);

        $child_nested_23 = new Task(
                new DummyJob,
                array(),
                array(
                    'dummytext' => 'child 23',
                    'destination' => '/tmp/dummytest_nested',
                )
        );
        
        $child_nested_12->addChild($child_nested_21);
        $child_nested_12->addChild($child_nested_22);
        $child_nested_12->addChild($child_nested_23);

    $child_nested_13 = new Task(
            new DummyJob,
            array(),
            array(
                'dummytext' => 'child 13',
                'destination' => '/tmp/dummytest_nested',
            )
    );
    
    $parent_nested->addChild($child_nested_11);
    $parent_nested->addChild($child_nested_12);
    $parent_nested->addChild($child_nested_13);

// ----------

$priority = new Task(
        new DummyJob,
        array(
            'priority' => 9,
        ),
        array(
            'dummytext' => 'aaaaaa',
            'destination' => '/tmp/dummytest_priority',
        )
);

// ----------

$scheduled = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'aaaaaa',
            'destination' => '/tmp/dummytest_scheduled',
        )
);
$scheduled->setScheduledAt(date('Y-m-d H:i:s', time() + 60)); // 1min after

// ----------

$profiled = new Task(
        new DummyJob,
        array(
            'profile' => 'notsodummy',
        ),
        array(
            'dummytext' => 'aaaaaa',
            'destination' => '/tmp/notsodummy',
        )
);

$faulty_profiled = new Task(
        new FaultyJob,
        array(
            'profile' => 'notsodummy',
        ),
        array(
            'dummytext' => 'aaaaaa',
            'destination' => '/tmp/notsodummy_faulty',
        )
);

$parent_profiled = new Task(
        new DummyJob,
        array(
            'profile' => 'notsodummy',
        ),
        array(
            'dummytext' => 'parent',
            'destination' => '/tmp/notsodummy_profiled',
        )
);

$child_profiled = new Task(
        new DummyJob,
        array(
            'profile' => 'notsodummy',
        ),
        array(
            'dummytext' => 'child',
            'destination' => '/tmp/notsodummy_profiled',
        )
);

$parent_profiled->addChild($child_profiled);

// ----------

$queueFactory = new QueueFactory(
        'redis',
        array('host' => 'localhost', 'port' => 6379)
);

$queue = $queueFactory->getQueue(); /* @var $queue \Libcast\JobQueue\Queue\RedisQueue */
$queue->add($basic);            // 1
$queue->add($faulty);           // 2
$queue->add($failing);          // 3
$queue->add($parent_basic);     // 4
$queue->add($parent_nested);    // 5
$queue->add($priority);         // 6
$queue->add($scheduled);        // 7
$queue->add($profiled);         // 8
$queue->add($faulty_profiled);  // 9
$queue->add($parent_profiled);  // 10