<?php

namespace Libcast\JobQueue\Task;

use Libcast\JobQueue\Job\JobInterface;
use Libcast\JobQueue\Task\TaskInterface;
use Libcast\JobQueue\Notification\Notification;

interface TaskInterface
{
  /**
   * List of all Task statuses
   * 
   * @return array
   */
  public static function getStatuses();

  /**
   * List of non persisted Task statuses
   * 
   * @return array
   */
  public static function getFakeTaskStatuses();

  /**
   * 
   * @param string $id
   */
  public function setId($id);

  /**
   * 
   * @return string
   */
  public function getId();

  /**
   * 
   * @param string $tag
   */
  public function setTag($tag);

  /**
   * 
   * @return string
   */
  public function getTag();

  /**
   * @param string $id
   */
  public function setParentId($id);

  /**
   * 
   * @return string
   */
  public function getParentId();

  /**
   * 
   * @param \Libcast\JobQueue\Job\JobInterface $job
   */
  public function setJob(JobInterface $job);

  /**
   * 
   * @return \Libcast\JobQueue\Job\JobInterface
   */
  public function getJob();

  /**
   * 
   * @param string $status pending|waiting|running|success|failed|finished
   * @throws \Libcast\JobQueue\Exception\TaskException
   */
  public function setStatus($status);

  /**
   * 
   * @return string pending|waiting|running|success|failed|finished
   */
  public function getStatus();

  /**
   * 
   * @param numeric $percent Must be like: 0 < $percent < 1
   * @throws \Libcast\JobQueue\Exception\TaskException
   */
  public function setProgress($percent);

  /**
   * Get Task progress.
   * 
   * Calculate an average progress of Task and its children.
   * 
   * @param bool $foat False to get a human readable string instead of float
   * @param bool $cumulate_children True calculate progress from Task and its children
   * @return float|string
   */
  public function getProgress($float = true, $cumulate_children = true);

  /**
   * Return the creation date of the Task (auto generated, no public setter)
   * 
   * @param bool $human_readable False for a Unix timestamp
   * @return string|int A string or a Unix timestamp
   * @throws \Libcast\JobQueue\Exception\TaskException
   */
  public function getCreatedAt($human_readable = true);

  /**
   * Schedule Task so it can't be executed before a date.
   * 
   * @param string $string A valid date format (Eg. '2013-11-30 20:30:50')
   * @throws \Libcast\JobQueue\Exception\TaskException
   */
  public function setScheduledAt($string);

  /**
   * 
   * @param bool $human_readable False for a Unix timestamp
   * @return string|int A string or a Unix timestamp
   */
  public function getScheduledAt($human_readable = true);

  /**
   * 
   * @param array $options
   */
  public function setOptions($options);

  /**
   * 
   * @return array
   */
  public function getOptions();

  /**
   * 
   * @param  string $name
   * @return string
   * @throws \Libcast\JobQueue\Exception\TaskException
   */
  public function getOption($name);

  /**
   * 
   * @param array $parameters
   */
  public function setParameters($parameters);

  /**
   * 
   * @return array
   */
  public function getParameters();

  /**
   * 
   * @param  string $name
   * @return string
   * @throws \Libcast\JobQueue\Exception\TaskException
   */
  public function getParameter($name);

  /**
   * 
   * @param \Libcast\JobQueue\Notification\Notification $notification
   */
  public function setNotification(Notification $notification);

  /**
   * 
   * @return \Libcast\JobQueue\Notification\Notification
   */
  public function getNotification();

  /**
   * Add a child to current Task
   * 
   * @param \Libcast\JobQueue\Task\TaskInterface $task
   * @throws \Libcast\JobQueue\Exception\TaskException
   */
  public function addChild(TaskInterface $task);

  /**
   * Update a current Task child
   * 
   * @param \Libcast\JobQueue\Task\TaskInterface $task
   * @throws \Libcast\JobQueue\Exception\TaskException
   */
  public function updateChild(TaskInterface $task);

  /**
   * Remove a child from current Task 
   * 
   * @param \Libcast\JobQueue\Task\TaskInterface $task
   * @throws \Libcast\JobQueue\Exception\TaskException
   */
  public function removeChild(TaskInterface $task);

  /**
   * List all children
   * 
   * @return array Array of Tasks
   */
  public function getChildren();

  /**
   * Retrieve a Task's child based on its tag
   * 
   * @param string $tag Children's tag
   * @return \Libcast\JobQueue\Task\TaskInterface
   * @throws \Libcast\JobQueue\Exception\TaskException
   */
  public function getChild($tag);

  /**
   * Check if a Task's child exists based on its tag
   * 
   * @param string $tag Children's tag
   * @return boolean
   */
  public function hasChild($tag);

  /**
   * Import a json encoded Task and return a Task object
   * 
   * @param string $json Json encoded Task
   * @return \Libcast\JobQueue\Task\TaskInterface
   */
  public static function jsonImport($json);

  /**
   * Export current Task as Json
   * 
   * @return string Json encoded Task
   */
  public function jsonExport();
}