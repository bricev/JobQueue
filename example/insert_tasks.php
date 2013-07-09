<?php

/**
 * Here are some examples on how to submit Tasks to the Queue :
 */

require realpath(__DIR__.'/../vendor/autoload.php');

use Libcast\JobQueue\Task\Task;
use Libcast\JobQueue\Queue\QueueFactory;
use Libcast\JobQueue\Notification\Notification;
use Predis\Client;

foreach (glob(__DIR__.'/Job/*Job.php') as $job) {
    include $job;
}

$tasks = array();

$tasks['single'] = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'aaaaaa',
            'destination' => '/tmp/dummytest1',
        )
);

$parent = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'parent',
            'destination' => '/tmp/dummytest_with_child',
        )
);
$child_1 = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'child_1',
            'destination' => '/tmp/dummytest_with_child',
        )
);
$child_1_grandchild_a = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'child_1_grandchild_a',
            'destination' => '/tmp/dummytest_with_child',
        )
);
$child_1_grandchild_a_grandgrandchild = new Task(
        new DummyLongJob,
        array(),
        array(
            'dummytext' => 'child_1_grandchild_a',
            'destination' => '/tmp/dummytest_with_child',
        )
);
$child_1_grandchild_a->addChild($child_1_grandchild_a_grandgrandchild);
$child_1_grandchild_b = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'child_1_grandchild_a',
            'destination' => '/tmp/dummytest_with_child',
        )
);
$child_1->addChild($child_1_grandchild_a);
$child_1->addChild($child_1_grandchild_b);
$child_2 = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'child_2',
            'destination' => '/tmp/dummytest_with_child',
        )
);
$parent->addChild($child_1);
$parent->addChild($child_2);
$tasks['nested'] = $parent;

$tasks['higher_priority'] = new Task(
        new DummyJob,
        array(
            'priority' => 9,
        ),
        array(
            'dummytext' => 'aaaaaa',
            'destination' => '/tmp/dummytest_priority',
        )
);

$scheduled = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'aaaaaa',
            'destination' => '/tmp/dummytest_scheduled',
        )
);
$scheduled->setScheduledAt(date('Y-m-d H:i:s', time() + 60));
$tasks['scheduled'] = $scheduled;

$tasks['with_profile'] = new Task(
        new DummyJob,
        array(
            'profile' => 'notsodummy',
        ),
        array(
            'dummytext' => 'aaaaaa',
            'destination' => '/tmp/notsodummy',
        )
);

$success = \Swift_Message::newInstance()->
        setSubject('The Task has been successfuly treated!')->
        setBody('Congratulation, the Task you submitted to JobQueue has been successfully treated. Cheers!')->
        setSender('jobqueue_sender@yopmail.com')->
        setTo('jobqueue_receiver@yopmail.com');

$error = \Swift_Message::newInstance()->
        setSubject('The Task has not been treated!')->
        setBody('Sorry, the Task you submitted to JobQueue has not been treated.')->
        setSender('jobqueue_sender@yopmail.com')->
        setTo('jobqueue_receiver@yopmail.com');

$notification = new Notification;
$notification->addNotification($success, Notification::TYPE_SUCCESS);
$notification->addNotification($error, Notification::TYPE_ERROR);

$tasks['notified'] = new Task(
        new DummyJob,
        array(),
        array(
            'dummytext' => 'aaaaaa',
            'destination' => '/tmp/dummytest1',
        ),
        $notification
);

// load Queue
$redis = new Client('tcp://localhost:6379');
$queue = QueueFactory::load($redis);

// add all Tasks to Queue
foreach ($tasks as $task) {
    $queue->add($task);
}

