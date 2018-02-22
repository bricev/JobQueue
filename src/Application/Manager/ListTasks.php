<?php

namespace JobQueue\Application\Manager;

use JobQueue\Application\Utils\CommandTrait;
use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Status;
use JobQueue\Infrastructure\ServiceContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ListTasks extends Command
{
    use CommandTrait;

    public function configure(): void
    {
        $this
            ->setName('list')
            ->setDescription('Lists tasks')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Limits the listing to a profile')
            ->addOption('status', 's', InputOption::VALUE_OPTIONAL, 'Limits the listing to a status')
            ->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Orders tasks by "date", "profile" or "status"', 'status')
            ->addOption('follow', 'f', InputOption::VALUE_NONE, 'Enables to keep tasks evolution on the console')
        ;
    }

    /**
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        // Clear screen for `follow` mode
        if ($input->getOption('follow')) {
            system('clear');
        }

        $queue = ServiceContainer::getInstance()->queue;

        $profile = $input->getOption('profile')
            ? new Profile($input->getOption('profile'))
            : null;

        $status = $input->getOption('status')
            ? new Status($input->getOption('status'))
            : null;

        $order = $input->getOption('order');

        $this->setStyles($output);

        $tasks = [];
        $previousSeparator = null;
        foreach ($queue->dump($profile, $status, $order) as $task) {
            $taskStatus = (string) $task->getStatus();
            $taskProfile = (string) $task->getProfile();

            switch ($order) {
                case 'date':
                    $separator = $task->getCreatedAt('Ymd');
                    break;

                case 'profile':
                    $separator = $taskProfile;
                    break;

                case 'status':
                default:
                    $separator = $taskStatus;
            }

            if ($previousSeparator and $separator !== $previousSeparator) {
                $tasks[] = new TableSeparator;
            }

            $tasks[] = [
                $this->formatCellContent($taskStatus, $taskStatus, $output),
                $taskProfile,
                $task->getJobName(true),
                $task->getCreatedAt('Y-m-d H:i:s'),
                $task->getIdentifier(),
            ];

            $previousSeparator = $separator;
        }

        if (empty($tasks)) {
            $this->formatInfoBlock(
                sprintf('There is currently no task corresponding to %s profile and %s status in queue.',
                    $profile ?: 'any',
                    $status ?: 'any'),
                $output
            );

        } else {
            $table = (new Table($output))
                ->setHeaders(['Status', 'Profile', 'Job', 'Date', 'Identifier'])
                ->setRows($tasks)
            ;
            $table->render();
        }

        // follow mode
        if ($input->getOption('follow')) {
            sleep(1);
            system('clear');

            $this->execute($input, $output);
        }
    }

    /**
     *
     * @param OutputInterface $output
     */
    private function setStyles(OutputInterface $output): void
    {
        foreach ([
            'waiting'  => new OutputFormatterStyle('blue'),
            'running'  => new OutputFormatterStyle('cyan', null, ['bold', 'blink']),
            'finished' => new OutputFormatterStyle('green'),
            'failed'   => new OutputFormatterStyle('red', null, ['bold']),
        ] as $name => $style) {
            $output->getFormatter()->setStyle($name, $style);
        }
    }
}
