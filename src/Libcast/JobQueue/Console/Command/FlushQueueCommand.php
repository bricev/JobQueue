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

class FlushQueueCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('queue:flush')
            ->setDescription('Flush the queue')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip validation')
        ;

        parent::configure();
    }

    /**
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getHelperSet()->get('dialog');

        if ($input->getOption('force')) {
            $this->flush();
        } else {
            $validate = $dialog->select($output, 'Do you really want to flush the queue?', [
                'no'  => 'Cancel',
                'yes' => 'Validate (cannot be undone)',
            ], 'no');

            if ('yes' === $validate) {
                $this->flush();
            } else {
                $this->addLine('Cancelled');
            }
        }

        $output->writeln($this->getLines());
    }

    protected function flush()
    {
        $this->getQueue()->flush();
        $this->addLine('The queue has been flushed');
    }
}
