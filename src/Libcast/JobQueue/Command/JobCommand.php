<?php

namespace Libcast\JobQueue\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Libcast\JobQueue\Exception\CommandException;
use Libcast\JobQueue\Queue\QueueFactory;

class JobCommand extends Command
{
  protected $queue = null;
  
  protected $lines = array();

  protected function configure()
  {
    $this->
            addOption('queue-provider', null, InputOption::VALUE_OPTIONAL, 'Provider', 'redis')->
            addOption('queue-conf',     null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Configuration (Eg. host:localhost)', array(
                'host' => 'localhost',
                'port' => 6379,
            ));
  }
  
  /**
   * @return \Libcast\JobQueue\Queue\QueueInterface
   */
  protected function getQueue(InputInterface $input)
  {
    if (!$this->queue)
    {
      // get provider configuration
      $queueConfiguration = array();
      if ($input->hasOption('queue-conf'))
      {
        foreach ($input->getOption('queue-conf') as $settings)
        {
          $setting = is_array($settings) ? $settings : explode(':', $settings);
          $queueConfiguration[$setting[0]] = $setting[1];
        }
      }

      $queueFactory = new QueueFactory(
              $input->getOption('queue-provider'),
              $queueConfiguration
      );

      $this->queue = $queueFactory->getQueue(); /* @var $queue \Libcast\JobQueue\Queue\RedisQueue */
    }
    
    return $this->queue;
  }
  
  protected function addLine($line = null)
  {
    if (!$line)
    {
      $line = '';
    }
    
    if (is_array($line))
    {
      $this->lines = array_merge($this->lines, $line);
      return;
    }
    
    $this->lines[] = $line;
  }
  
  protected function getLines()
  {
    return $this->lines;
  }
  
  protected function flushLines()
  {
    $this->lines = array();
  }
}