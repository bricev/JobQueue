<?php

namespace JobQueue\Application\Manager;

use JobQueue\Application\Utils\CommandTrait;
use JobQueue\Infrastructure\ServiceContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class FlushTasks extends Command
{
    use CommandTrait;

    public function configure(): void
    {
        $this
            ->setName('flush')
            ->setDescription('Deletes all tasks from the queue')
            ->addOption('force', 'y', InputOption::VALUE_NONE, 'Skip validation')
        ;
    }

    /**
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        if (!$input->getOption('force')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Do you want to delete all tasks from the queue? (y/f) ', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Canceled');
                return;
            }
        }

        ServiceContainer::getInstance()
            ->queue
            ->flush();

        $this->formatInfoSection('Queue flushed', $output);
    }
}
