<?php

error_reporting(E_ALL);

require __DIR__.'/vendor/autoload.php';

use Libcast\Job\Task\Task;
use Libcast\Job\Job\DummyJob;
use Libcast\Job\Job\FaultyJob;
use Libcast\Job\Queue\QueueFactory;

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
                new DummyJob,
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
                    new DummyJob,
                    array(),
                    array(
                        'dummytext' => 'child 3',
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

$queue = $queueFactory->getQueue(); /* @var $queue \Libcast\Job\Queue\RedisQueue */
$queue->add($basic);            // 1
$queue->add($faulty);           // 2
$queue->add($parent_basic);     // 3
$queue->add($parent_nested);    // 4
$queue->add($priority);         // 5
$queue->add($profiled);         // 6
$queue->add($faulty_profiled);  // 7
$queue->add($parent_profiled);  // 8

//$task = $queue->getNextTask('dummy-stuff');

//var_dump($task);

//print_children_r($task);

function print_children_r(Task $task, $rank = 0)
{
  $id = $task->getId();
  $job_class = $task->getJob();
  $job = new $job_class;
  
  echo str_repeat('--', $rank) . "-> $id: {$job->getClassName()}\n";

  $rank++;

  foreach ($task->getChildren() as $child)
  {
    print_children_r($child, $rank);
  }
}