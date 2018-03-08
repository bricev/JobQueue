<?php

namespace JobQueue\Application\Console;

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

    /**
     *
     * @var array
     */
    private $tags = [];

    public function configure()
    {
        $this
            ->setName('list')
            ->setDescription('Lists tasks')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Limits the listing to a profile')
            ->addOption('status',  's', InputOption::VALUE_OPTIONAL, 'Limits the listing to a status')
            ->addOption('tags',    't', InputOption::VALUE_IS_ARRAY|InputOption::VALUE_OPTIONAL, 'Limits the listing to one or many (array) tags')
            ->addOption('order',   'o', InputOption::VALUE_REQUIRED, 'Orders tasks by "date", "profile" or "status"', 'status')
            ->addOption('follow',  'f', InputOption::VALUE_NONE, 'Enables to keep tasks evolution on the console')
            ->addOption('legend',  'l', InputOption::VALUE_NONE, 'Displays a legend for status labels at the list footer')
        ;
    }

    /**
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setStyles($output);

        $this->profile = $input->getOption('profile')
            ? new Profile($input->getOption('profile'))
            : null;

        $this->status = $input->getOption('status')
            ? new Status($input->getOption('status'))
            : null;

        $this->tags = is_array($input->getOption('tags'))
            ? $input->getOption('tags')
            : [];

        // Clear screen for `follow` mode
        if ($follow = $input->getOption('follow')) {
            system('clear');
        }

        $this->display($follow, $input, $output);

        return 0;
    }

    /**
     *
     * @param bool            $follow
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    private function display(bool $follow, InputInterface $input, OutputInterface $output)
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

            $columns = ['Job', 'Profile', 'Date'];
            foreach ($this->tags as $key => $tag) {
                $columns[] = sprintf('T%d', $key + 1);
            }
            $columns[] = 'Identifier';

            (new Table($output))
                ->setHeaders($columns)
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

        foreach ($queue->search($this->profile, $this->status, $this->tags, $order) as $task) {
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

            $taskData = [
                sprintf('%s %s', $this->formatContent('■', $status, $output), $task->getJobName(true)),
                $profile,
                $task->getCreatedAt('Y-m-d H:i:s'),
            ];

            // Add `tags` columns
            foreach ($this->tags as $tag) {
                $taskData[] = $task->hasTag($tag) ? ' ✔ ' : ' ✘ ';
            }

            $taskData[] = $task->getIdentifier();

            $tasks[] = $taskData;

            $previousSeparator = $separator;
        }

        return $tasks;
    }

    /**
     *
     * @param array $rows
     * @param OutputInterface $output
     * @return array
     */
    private function addTableFooter(array $rows, OutputInterface $output): array
    {
        $legend = [];
        foreach (Status::listStatus() as $status) {
            $legend[] = sprintf('%s %s', $this->formatContent('■', $status, $output), $status);
        }

        $colspan = count(Status::listStatus()) + count($this->tags);

        $rows[] = new TableSeparator;
        $rows[] = [new TableCell(
            sprintf('Legend:   %s', implode('   ', $legend)),
            ['colspan' => $colspan]
        )];

        if (!empty($this->tags)) {
            $tags = [];
            foreach ($this->tags as $key => $tag) {
                $tags[] = sprintf('- T%s: tag "%s"', $key + 1, $tag);
            }

            $rows[] = [new TableCell(
                sprintf("Tag(s): \n%s", implode("\n", $tags)),
                ['colspan' => $colspan]
            )];
        }

        return $rows;
    }
}
