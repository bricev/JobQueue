<?php

namespace JobQueue\Application\Utils;

use JobQueue\Domain\Task\Task;
use Symfony\Component\Console\Output\OutputInterface;

trait CommandTrait
{
    /**
     *
     * @param string          $text
     * @param string          $type
     * @param OutputInterface $output
     */
    private function formatSection(string $text, string $type, OutputInterface $output): void {
        /** @var \Symfony\Component\Console\Helper\FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        $block = $formatter->formatSection($type, $text, $type);

        $output->writeln($block);
    }

    /**
     *
     * @param string          $text
     * @param OutputInterface $output
     */
    private function formatInfoSection(string $text, OutputInterface $output): void {
        $this->formatSection($text, 'info', $output);
    }

    /**
     *
     * @param string          $text
     * @param OutputInterface $output
     */
    private function formatErrorSection(string $text, OutputInterface $output): void {
        $this->formatSection($text, 'error', $output);
    }

    /**
     *
     * @param string          $text
     * @param string          $type
     * @param OutputInterface $output
     */
    private function formatBlock(string $text, string $type, OutputInterface $output): void {
        /** @var \Symfony\Component\Console\Helper\FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        $block = $formatter->formatBlock([$type, $text], $type, true);

        $output->writeln($block);
    }

    /**
     *
     * @param string          $text
     * @param OutputInterface $output
     */
    private function formatInfoBlock(string $text, OutputInterface $output): void {
        $this->formatBlock($text, 'info', $output);
    }

    /**
     *
     * @param Task            $task
     * @param OutputInterface $output
     */
    private function formatTaskBlock(Task $task, OutputInterface $output): void {
        $i = 0;
        $paramString = null;
        foreach ($task->getParameters() as $name => $value) {
            if ($i) {
                $paramString .= "                 ";
            }
            $i++;

            $paramString .= "$i) $name: $value \n";
        }

        $text = <<<EOL
- Identifier : {$task->getIdentifier()}
  - Date       : {$task->getCreatedAt('r')}
  - Job class  : {$task->getJobName()} 
  - Job name   : {$task->getJobName(true)} 
  - Profile    : {$task->getProfile()} 
  - Status     : {$task->getStatus()} 
  - Parameters : $paramString
EOL;

        $this->formatBlock($text, 'info', $output);
    }

    /**
     *
     * @param string          $value
     * @param string          $style
     * @param OutputInterface $output
     * @return string
     */
    private function formatCellContent(string $value, string $style, OutputInterface $output): string {
        return $output
            ->getFormatter()
            ->format(sprintf('<%s>%s</>', $style, $value));
    }
}