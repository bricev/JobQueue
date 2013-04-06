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