<?php

namespace Libcast\Job\Queue;

use Libcast\Job\Task\TaskInterface;

use Psr\Log\LoggerInterface;

interface QueueInterface
{
  /**
   * Register the Task, persist Task data
   * 
   * @param \Libcast\Job\Task\TaskInterface $task
   * @return int Task identifier
   */
  public function add(TaskInterface $task);
  
  /**
   * Persist updated Task Data, perform extra actions dependint on Task status:
   * - check that Task has been reserved before it is running
   * - requeue a Task that has failed a certain amount of times
   * - save finished Tasks for later stats
   * - makes sure all child Tasks are finished before parent Task is finished
   * 
   * @param \Libcast\Job\Task\TaskInterface $task 
   * @throws \Libcast\Job\Exception\QueueException
   */
  public function update(TaskInterface $task);
  
  /**
   * Flag the Task as :
   * - "reserved" : so that only one Worker may treat it
   * - "failed"   : to keep data in Queue and not loose it's data
   * - "waiting"  : to re-send Task in Queue
   * 
   * @param \Libcast\Job\Task\TaskInterface $task 
   * @param string $persist waiting|encoding|failed
   */
  public function flag(TaskInterface $task, $action = null);

  /**
   * Remove Task from Queue
   * 
   * @param \Libcast\Job\Task\TaskInterface $task
   */
  public function remove(TaskInterface $task);

  /**
   * Lists all sort options that may be used to sort self:getTasks results
   * 
   * @return array
   */
  public static function getSortByOptions();

  /**
   * Lists all Tasks from Queue
   * 
   * @param   string $sort_by     Sort by option (priority|profile|status)
   * @param   string $sort_order  Sort order (asc|desc)
   * @param   string $priority    Filter by priority (1, 2, ...)
   * @param   string $profile     Filter by profile (eg. "high-cpu")
   * @param   string $order       Filter by status (all|pending|waiting|running|success|failed|finished)
   * @return  array               List of Tasks
   * @throws  QueueException
   */
  public function getTasks($sort_by = null, $sort_order = null, $priority = null, $profile = null, $status = null);

  /**
   * Retrieve a Task from Queue based on its Id.
   * 
   * @param int $id
   * @return \Libcast\Job\Task\TaskInterface|null
   */
  public function getTask($id);
  
  /**
   * Retrieve a Task status, even for non existsing Tasks or finished Tasks.
   * 
   * @param int $id 
   * @return string pending|wait|running|success|failed|finished
   */
  public function getTaskStatus($id);

  /**
   * Pick the next Task (ordered by set, priority)
   * 
   * @param string $set The set in whick Tasks must be selected
   * @return \Libcast\Job\Task\TaskInterface|null
   */
  public function getNextTask($set = null);
  
  public function setLogger(LoggerInterface $logger);
}