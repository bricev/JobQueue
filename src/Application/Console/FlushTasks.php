<?php

namespace JobQueue\Application\Console;

use JobQueue\Application\Utils\CommandTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class FlushTasks extends ManagerCommand
{
    use CommandTrait;

    public function configure()
    {
        $this
            ->setName('flush')
            ->setDescription('Deletes all tasks from the queue')
            ->addOption('force', 'y', InputOption::VALUE_NONE, 'Skip validation')
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
        if (!$input->getOption('force')) {
            $helper = $this->getHelper('question'); /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
            $question = new ConfirmationQuestion('Do you want to delete all tasks from the queue? (y/f) ', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Canceled');
                return 0;
            }
        }

        $this->queue->flush();

        $this->formatInfoSection('Queue flushed', $output);

        return 0;
    }
}
