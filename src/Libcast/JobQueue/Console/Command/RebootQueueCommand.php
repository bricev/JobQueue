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
use Libcast\JobQueue\Console\Command\Command;

class RebootQueueCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('queue:reboot')
            ->setDescription('Reboot the queue')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'List of profiles')
        ;

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $profiles = $this->getProfileList($input);
        $dialog = $this->getHelperSet()->get('dialog');
        $validate = $dialog->select($output,
            "Do you really want to reboot the queue?", array(
                'no'  => 'Cancel',
                'yes' => 'Validate (cannot be undone)',
            ),
            'no'
        );

        if ('yes' === $validate) {
            $this->jobQueue['queue']->reboot($profiles);
            $this->addLine('The queue has been rebooted.');
        } else {
            $this->addLine('Cancelled.');
        }

        $output->writeln($this->getLines());
    }

    /**
     * Returns the profiles used, filtered if user has used the --profile option
     *
     * @param  InputInterface $input        User input from CLI
     *
     * @return array                        The list of profiles
     */
    protected function getProfileList(InputInterface $input)
    {
        $profiles = array();
        foreach ($this->jobQueue['workers'] as $worker => $worker_profiles) {
            $profiles = array_merge($profiles, $worker_profiles);
        }
        $filtered  = $input->getOption('profile');

        if (!empty($filtered)) {
            $profiles = array_intersect($profiles, $filtered);
        }
        return $profiles;
    }
}
