<?php

namespace Libcast\Job\Task;

use Libcast\Job\Job\JobInterface;
use Libcast\Job\Task\TaskInterface;

interface TaskInterface
{
  public function setId($id);

  public function getId();
  
  public function setTag($tag);

  public function getTag();

  public function setParentId($id);

  public function getParentId();

  public function setJob(JobInterface $job);

  public function getJob();
  
  public function setStatus($status);

  public function getStatus();

  public function setProgress($percent);

  /**
   * Get Task progress.
   * Calculate an average progress of Task and its children.
   * 
   * @param bool $foat False to get a human readable string instead of float
   * @return float|string
   */
  public function getProgress($float = true);

  /**
   * Schedule Task so it can't be executed before a date.
   * 
   * @param string $string A valid date format (Eg. '2013-11-30 20:30:50')
   * @throws \Libcast\Job\Exception\TaskException
   */
  public function setScheduledAt($string);
  
  /**
   * @param bool $human_readable False for a Unix timestamp
   * @return string|int A string or a Unix timestamp
   */
  public function getScheduledAt($human_readable = true);

  public function setOptions($options);

  public function getOptions();

  public function getOption($name);

  public function setParameters($parameters);

  public function getParameters();

  public function getParameter($name);
  
  public function addChild(TaskInterface $task);
  
  public function updateChild(TaskInterface $task);

  public function getChildren();
  
  public function getChild($tag);

  public static function jsonImport($json);

  public function jsonExport();
}