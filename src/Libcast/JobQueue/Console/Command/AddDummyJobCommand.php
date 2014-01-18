<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Libcast\JobQueue\Console\Command\Command;
use Libcast\JobQueue\Console\OutputTable;
use Libcast\JobQueue\Task\Task;

class AddDummyJobCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('job:add-dummy')
            ->setDescription('Add dummy jobs to the queue, for testing purpose')
        ;
        parent::configure();

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("");
        $this->addTasks($output);
        $output->writeln("\nTasks added to the queue.");
    }
    protected function addTasks(OutputInterface $output)
    {
        $tasks = array();

        $tasks['single'] = new Task(
                new \DummyJob,
                array(),
                array(
                    'dummytext' => 'aaaaaa',
                    'destination' => '/tmp/dummytest1',
                )
        );

        $parent = new Task(
                new \DummyJob,
                array(),
                array(
                    'dummytext' => 'parent',
                    'destination' => '/tmp/dummytest_with_child',
                )
        );
        $child_1 = new Task(
                new \DummyJob,
                array(),
                array(
                    'dummytext' => 'child_1',
                    'destination' => '/tmp/dummytest_with_child',
                )
        );
        $child_1_grandchild_a = new Task(
                new \DummyJob,
                array(),
                array(
                    'dummytext' => 'child_1_grandchild_a',
                    'destination' => '/tmp/dummytest_with_child',
                )
        );
        $child_1_grandchild_a_grandgrandchild = new Task(
                new \DummyLongJob,
                array(),
                array(
                    'dummytext' => 'child_1_grandchild_a',
                    'destination' => '/tmp/dummytest_with_child',
                )
        );
        $child_1_grandchild_a->addChild($child_1_grandchild_a_grandgrandchild);
        $child_1_grandchild_b = new Task(
                new \DummyJob,
                array(),
                array(
                    'dummytext' => 'child_1_grandchild_a',
                    'destination' => '/tmp/dummytest_with_child',
                )
        );
        $child_1->addChild($child_1_grandchild_a);
        $child_1->addChild($child_1_grandchild_b);
        $child_2 = new Task(
                new \DummyJob,
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
                new \DummyJob,
                array(
                    'priority' => 9,
                ),
                array(
                    'dummytext' => 'aaaaaa',
                    'destination' => '/tmp/dummytest_priority',
                )
        );

        $scheduled = new Task(
                new \DummyJob,
                array(),
                array(
                    'dummytext' => 'aaaaaa',
                    'destination' => '/tmp/dummytest_scheduled',
                )
        );
        $scheduled->setScheduledAt(date('Y-m-d H:i:s', time() + 60));
        $tasks['scheduled'] = $scheduled;

        $tasks['with_profile'] = new Task(
                new \DummyJob,
                array(
                    'profile' => 'notsodummy',
                ),
                array(
                    'dummytext' => 'aaaaaa',
                    'destination' => '/tmp/notsodummy',
                )
        );
        foreach ($tasks as $task) {
            $this->jobQueue['queue']->add($task);
            $output->write(".");
        }
    }
}
