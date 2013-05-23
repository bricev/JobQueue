<?php

namespace Libcast\JobQueue\Queue;

use Libcast\JobQueue\Task\TaskInterface;

use Psr\Log\LoggerInterface;

interface QueueInterface
{
  /**
   * Register the Task, persist Task data
   * 
   * @param \Libcast\JobQueue\Task\TaskInterface $task
   * @param bool $first False if Task is added for the nth time
   * @return int Task identifier
   */
  public function add(TaskInterface $task, $first = true);
  
  /**
   * Keep a Task's data aside so that it can be execuetd later
   * 
   * @param \Libcast\JobQueue\Task\TaskInterface $task
   * @param timestamp $date
   */
  public function schedule(TaskInterface $task, $date);
  
  /**
   * Persist updated Task Data, perform extra actions dependint on Task status:
   * - check that Task has been reserved before it is running
   * - requeue a Task that has failed a certain amount of times
   * - save finished Tasks for later stats
   * - makes sure all child Tasks are finished before parent Task is finished
   * 
   * @param \Libcast\JobQueue\Task\TaskInterface $task 
   * @throws \Libcast\JobQueue\Exception\QueueException
   */
  public function update(TaskInterface $task);
  
  /**
   * Flag the Task as :
   * - "reserved" : so that only one Worker may treat each Task (default)
   * - "failed"   : helps keep Task data in Queue for later retry
   * - "waiting"  : to (re)send Task in Queue
   * 
   * @param \Libcast\JobQueue\Task\TaskInterface $task 
   * @param string                          $persist waiting|encoding|failed
   */
  public function flag(TaskInterface $task, $action = null);

  /**
   * Remove Task from Queue
   * 
   * @param \Libcast\JobQueue\Task\TaskInterface $task
   * @param boolean $update_parent false to prevent parent from being updated
   */
  public function remove(TaskInterface $task, $update_parent = true);

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
   * @return \Libcast\JobQueue\Task\TaskInterface|null
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
   * @return \Libcast\JobQueue\Task\TaskInterface|null
   */
  public function getNextTask($set = null);
}