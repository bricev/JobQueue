<?php

namespace JobQueue\Application\Manager;

use JobQueue\Application\Utils\CommandTrait;
use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Status;
use JobQueue\Infrastructure\ServiceContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ListTasks extends Command
{
    use CommandTrait;

    /**
     *
     * @var Profile
     */
    private $profile;

    /**
     *
     * @var Status
     */
    private $status;

    public function configure(): void
    {
        $this
            ->setName('list')
            ->setDescription('Lists tasks')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Limits the listing to a profile')
            ->addOption('status', 's', InputOption::VALUE_OPTIONAL, 'Limits the listing to a status')
            ->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Orders tasks by "date", "profile" or "status"', 'status')
            ->addOption('follow', 'f', InputOption::VALUE_NONE, 'Enables to keep tasks evolution on the console')
            ->addOption('legend', 'l', InputOption::VALUE_NONE, 'Displays a legend for status labels at the list footer')
        ;
    }

    /**
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->setStyles($output);

        $this->profile = $input->getOption('profile')
            ? new Profile($input->getOption('profile'))
            : null;

        $this->status = $input->getOption('status')
            ? new Status($input->getOption('status'))
            : null;

        // Clear screen for `follow` mode
        if ($follow = $input->getOption('follow')) {
            system('clear');
        }

        $this->display($follow, $input, $output);
    }

    /**
     *
     * @param bool            $follow
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    private function display(bool $follow, InputInterface $input, OutputInterface $output): void
    {
        $tasks = $this->getTasks($input->getOption('order'), $output);

        if (empty($tasks)) {
            $this->formatInfoBlock(
                sprintf('There is currently no task corresponding to %s profile and %s status in queue.',
                    $this->profile ?: 'any',
                    $this->status ?: 'any'),
                $output
            );

        } else {
            if ($input->getOption('legend')) {
                $tasks = $this->addTableFooter($tasks, $output);
            }

            (new Table($output))
                ->setHeaders(['Job', 'Profile', 'Date', 'Identifier'])
                ->setRows($tasks)
                ->render()
            ;

            $output->writeln(sprintf('There is %d tasks in queue', count($tasks)));
            $output->writeln('');
        }

        // follow mode
        if ($follow) {
            sleep(1);
            system('clear');

            $this->display($follow, $input, $output);
        }
    }

    /**
     *
     * @param string          $order
     * @param OutputInterface $output
     * @return array
     */
    public function getTasks(string $order, OutputInterface $output): array
    {
        $tasks = [];
        $previousSeparator = null;
        $queue = ServiceContainer::getInstance()->queue;

        foreach ($queue->dump($this->profile, $this->status, $order) as $task) {
            $status = (string) $task->getStatus();
            $profile = (string) $task->getProfile();

            switch ($order) {
                case 'date':
                    $separator = $task->getCreatedAt('Ymd');
                    break;

                case 'profile':
                    $separator = $profile;
                    break;

                case 'status':
                default:
                    $separator = $status;
            }

            if ($previousSeparator and $separator !== $previousSeparator) {
                $tasks[] = new TableSeparator;
            }

            $tasks[] = [
                sprintf('%s %s', $this->formatContent('■', $status, $output), $task->getJobName(true)),
                $profile,
                $task->getCreatedAt('Y-m-d H:i:s'),
                $task->getIdentifier(),
            ];

            $previousSeparator = $separator;
        }

        return $tasks;
    }

    /**
     *
     * @param OutputInterface $output
     * @return array
     */
    private function addTableFooter(array $rows, OutputInterface $output): array
    {
        $legend = [];
        foreach (Status::listStatus() as $status) {
            $legend[] = sprintf('%s %s', $this->formatContent('■', $status, $output), $status);
        }

        $rows[] = new TableSeparator;
        $rows[] = [new TableCell(
            sprintf('Legend:   %s', implode('   ', $legend)),
            ['colspan' => count(Status::listStatus())]
        )];

        return $rows;
    }
}
