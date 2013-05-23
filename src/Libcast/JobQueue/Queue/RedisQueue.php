<?php

namespace Libcast\JobQueue\Queue;

use Libcast\JobQueue\Exception\QueueException;
use Libcast\JobQueue\Queue\AbstractQueue;
use Libcast\JobQueue\Queue\QueueInterface;

use Libcast\JobQueue\Job\NullJob;

use Libcast\JobQueue\Task\Task;
use Libcast\JobQueue\Task\TaskInterface;

use Predis\Client;

/**
 * Redis Queue uses the following keys:
 * 
 * $prefix:task:lastid        string (incr)     unique Task id
 * $prefix:task:$id           string            stores Task data (json)
 * $prefix:task:scheduled     sorted set        lists scheduled Tasks
 * $prefix:task:scheduled:$id string            stores scheduled Task data (json)
 * $prefix:task:children:$id  string (incr)     counts a Task children
 * $prefix:task:failed:$id    string (incr)     counts a Task failed atempts
 * $prefix:task:finished      list              lists finished Tasks (logs)
 * $prefix:profile:$profile   sorted set        lists Tasks from a Queue's profile
 * $prefix:profile:common     sorted set        lists all Tasks from Queue
 * $prefix:union:$hash        sorted set union  union of Queue profiles (temporary)
 */
class RedisQueue extends AbstractQueue implements QueueInterface
{
  const PREFIX = 'libcast:jobqueue:';

  /**
   * @var \Predis\Client
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  protected function connect()
  {
    $this->client = new Client(array(
        'scheme' => $this->getParameter('scheme', 'tcp'), 
        'host'   => $this->getParameter('host', 'localhost'), 
        'port'   => $this->getParameter('port', 6379), 
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function add(TaskInterface $task, $first = true)
  {
    // give the Task a uniq Id
    if (!$task->getId())
    {
      $task->setId($id = $this->client->incr(self::PREFIX.'task:lastid'));
    }

    $pipe = $this->client->pipeline();

    // only put in Queue non scheduled or immediate Tasks
    if (!$task->getScheduledAt() || $task->getScheduledAt(false) <= time())
    {
      $task->setStatus(Task::STATUS_WAITING);

      // store Task data
      $pipe->set(self::PREFIX."task:{$task->getId()}", $task->jsonExport());

      // affect Task to its Queue profile's set
      $pipe->zadd(self::PREFIX."profile:{$task->getOption('profile')}", 
              $task->getOption('priority'), 
              $task->getId());

      // add Task to Queue's common set
      $pipe->zadd(self::PREFIX.'profile:'.self::COMMON_PROFILE, 
              $task->getOption('priority'), 
              $task->getId());
    }
    else
    {
      $this->schedule($task, $task->getScheduledAt(false));
    }

    // count children only the first time a Task is added
    if ($parent_id = $task->getParentId())
    {
      $pipe->incr(self::PREFIX."task:children:$parent_id");

      // update parent Task
      if ($parent = $this->getTask($parent_id))
      {
        $parent->updateChild($task);
        $this->update($parent);
      }
    }

    $pipe->execute();

    return $id;
  }

  /**
   * {@inheritdoc}
   */
  public function schedule(TaskInterface $task, $date)
  {
    $task->setStatus(Task::STATUS_PENDING);
    $task->setScheduledAt(date('Y-m-d H:i:s', $date));

    // remove Task from Queue
    $this->remove($task);

    $pipe = $this->client->pipeline();

    // store Task data in a dedicated key
    $pipe->set(self::PREFIX."task:scheduled:{$task->getId()}", $task->jsonExport());

    // add Task to sheduled set with time as score
    $pipe->zadd(self::PREFIX.'task:scheduled', 
            $task->getScheduledAt(false),
            $task->getId());

    return $pipe->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function unschedule(TaskInterface $task)
  {
    $pipe = $this->client->pipeline();

    // store Task data in a dedicated key
    $pipe->del(self::PREFIX."task:scheduled:{$task->getId()}");

    // add Task to sheduled set with time as score
    $pipe->zrem(self::PREFIX.'task:scheduled', $task->getId());

    $pipe->execute();

    return $this->add($task, false);
  }

  /**
   * {@inheritdoc}
   */
  public function update(TaskInterface $task)
  {
    $this->log("Task '{$task->getId()}' updated", array(
        'status'    => $task->getStatus(),
        'progress'  => $task->getProgress(false),
        'children'  => count($task->getChildren()),
    ), 'debug');

    // update parent
    $parent = null;
    if ($parent_id = $task->getParentId())
    {
      $parent = $this->getTask($parent_id);
      $parent->updateChild($task);
      $this->update($parent);
    }

    $enqueued = $this->getTask($task->getId());

    // if status change, trigger some extra actions
    if ($task->getStatus() !== $enqueued->getStatus())
    {
      // reserve Tasks that are marked as 'running'
      if (Task::STATUS_RUNNING === $task->getStatus())
      {
        $this->flag($task);
      }

      // make sure Tasks is flaged as 'waiting' when requeued
      elseif (Task::STATUS_WAITING === $task->getStatus())
      {
        $this->flag($task, Task::STATUS_WAITING);
      }

      // set progress for successful Tasks
      elseif (Task::STATUS_SUCCESS === $task->getStatus())
      {
        $task->setProgress(1);
      }

      // try again (requeue) failed Tasks
      elseif (Task::STATUS_FAILED === $task->getStatus())
      {
        $fail_count = $this->client->incr(self::PREFIX."task:failed:{$task->getId()}");

        if ($fail_count < self::MAX_REQUEUE)
        {
          $this->log(sprintf('Failed Task \'%d\' has been given another chance (%d/%d)',
                  $task->getId(),
                  $fail_count,
                  self::MAX_REQUEUE));

          $this->schedule($task, time() + 30);

          return true;
        }
        else
        {
          // flag Task as failed
          $this->flag($task, Task::STATUS_FAILED);

          // delete requeue count
          $this->client->del(self::PREFIX."task:failed:{$task->getId()}");

          $this->log("Task '{$task->getId()}' failed $fail_count times : Worker gived up.");
        }
      }

      // set parent Task as finished only when children are finished
      // remove finished Tasks
      elseif (Task::STATUS_FINISHED === $task->getStatus())
      {
        $this->remove($task);

        if ($parent)
        {
          // if all children Tasks have been executed
          if ((int) $this->client->get(self::PREFIX."task:children:$parent_id") <= 0)
          {
            // mark the parent Task as finished, this will recursively mark all
            // parent job as finished
            $parent->setStatus(Task::STATUS_FINISHED);
            $this->update($parent);
          }
        }

        $this->client->lpush(self::PREFIX.'task:finished', $task->getId());

        return true;
      }
    }

    // persist data
    return $this->client->set(self::PREFIX."task:{$task->getId()}", $task->jsonExport());
  }

  /**
   * {@inheritdoc}
   */
  public function flag(TaskInterface $task, $action = Task::STATUS_RUNNING)
  {
    // edit set member's score according to priority
    switch ($action)
    {
      case Task::STATUS_WAITING:
        $score = $task->getOption('priority');
        break;

      case Task::STATUS_RUNNING:
        $score = self::PRIORITY_RUNNING;
        break;

      case Task::STATUS_FAILED:
        $score = self::PRIORITY_FAILED;
        break;

      default :
        throw new QueueException("Task can't be flagged with '$action' action.");
    }

    $pipe = $this->client->pipeline();

    // update Task from its Queue profile's set
    $pipe->zrem(self::PREFIX."profile:{$task->getOption('profile')}", $task->getId());
    $pipe->zadd(self::PREFIX."profile:{$task->getOption('profile')}", $score, $task->getId());

    // update Task from common Queue profile's set
    $pipe->zrem(self::PREFIX.'profile:'.self::COMMON_PROFILE, $task->getId());
    $pipe->zadd(self::PREFIX.'profile:'.self::COMMON_PROFILE, $score, $task->getId());

    return $pipe->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function remove(TaskInterface $task, $update_parent = true)
  {
    foreach ($task->getChildren() as $child)
    {
      $this->remove($child, false);
    }

    if (!$task->getId())
    {
      return false;
    }

    $pipe = $this->client->pipeline();

    if ($parent_id = $task->getParentId())
    {
      $pipe->decr(self::PREFIX."task:children:$parent_id");

      // update parent Task
      if ($update_parent && $parent = $this->getTask($parent_id))
      {
        $parent->removeChild($task);
        $this->update($parent);
      }
    }

    $pipe->del(self::PREFIX."task:{$task->getId()}");
    $pipe->del(self::PREFIX."task:scheduled:{$task->getId()}");
    $pipe->del(self::PREFIX."task:children:{$task->getId()}");
    $pipe->zrem(self::PREFIX."profile:{$task->getOption('profile')}", $task->getId());
    $pipe->zrem(self::PREFIX.'profile:'.self::COMMON_PROFILE, $task->getId());
    $pipe->zrem(self::PREFIX.'task:scheduled', $task->getId());

    return $pipe->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getTasks($sort_by = null, $sort_order = null, $priority = null, $profile = null, $status = null)
  {
    if (!$sort_by)
    {
      $sort_by = self::SORT_BY_PRIORITY;
    }

    if (!$sort_order)
    {
      $sort_order = self::ORDER_ASC;
    }

    if (!in_array($sort_by, self::getSortByOptions()))
    {
      throw new QueueException("Sort by option must be priority|profile|status. Value '$sort_by' given.");
    }

    if (!$profile)
    {
      $profile = self::COMMON_PROFILE;
    }

    $status = $status === 'all' ? null : $status;
    if ($status && !in_array($status, Task::getStatuses()))
    {
      throw new QueueException("Status '$status' does not exists.");
    }

    if ($priority && (!is_int($priority) || $priority < self::PRIORITY_MIN))
    {
      throw new QueueException("Priority '$priority' is not valid.");
    }

    $tasks = array();

    // Queue does not store pending Tasks
    // Sheduled Tasks are pending
    if (Task::STATUS_PENDING === $status || !$status)
    {
      foreach ($this->client->zrange(self::PREFIX.'task:scheduled', 0, -1) as $id)
      {
        $task = $this->getTask($id);
        if ($task && 
                (self::COMMON_PROFILE === $profile || $profile === $task->getOption('profile')) &&
                (!$priority || $priority === $task->getOption('priority')) &&
                (!$status || $status === $task->getStatus()))
        {
          $tasks[] = $task;
        }
      }
    }

    // Queue stores waiting Tasks with a positive score
    if (Task::STATUS_WAITING === $status || !$status)
    {
      $key = self::PREFIX.'profile:'.($profile ? $profile : self::COMMON_PROFILE);

      foreach ($this->client->zrevrangebyscore($key, 
              $priority ? '('.($priority + 1) : '+inf', 
              $priority ? $priority : self::PRIORITY_MIN) as $id)
      {
        $task = $this->getTask($id);
        if ($task && (!$status || $status === $task->getStatus()))
        {
          $tasks[] = $task;
        }
      }
    }

    // Queue stores running and successfull Tasks the same way until Task is
    // finished. Failed Tasks are immediately requeued or remain untouched.
    if (in_array($status, array(
        Task::STATUS_RUNNING, 
        Task::STATUS_SUCCESS,
        Task::STATUS_FAILED,
    )) || !$status)
    {
      $key = self::PREFIX.'profile:'.($profile ? $profile : self::COMMON_PROFILE);

      foreach ($this->client->zrangebyscore($key, -1, '(1') as $id)
      {
        $task = $this->getTask($id);
        if ($task &&
                (!$status || $status === $task->getStatus()) &&
                (!$priority || $priority === $task->getOption('priority')))
        {
          $tasks[] = $task;
        }
      }
    }

    // finished Tasks have no data (their ids are just listed) so we have to 
    // create fake Tasks based on NullJob
    if ((Task::STATUS_FINISHED === $status) && // we only return finished Tasks if $status = finished
            self::COMMON_PROFILE === $profile)
    {
      foreach ($this->client->lrange(self::PREFIX.'task:finished', 0, -1) as $id)
      {
        $task = new Task(new NullJob);
        $task->setId($id);
        $task->setStatus(Task::STATUS_FINISHED);
        $task->setProgress(1);

        $tasks[] = $task;
      }
    }

    // group Tasks by 'sort_by' option
    $groups = array();
    foreach ($tasks as $task)
    {
      switch ($sort_by)
      {
        case self::SORT_BY_PROFILE:
          $sort = $task->getOption('profile');
          break;

        case self::SORT_BY_STATUS:
          $sort = $task->getStatus();
          break;

        case self::SORT_BY_PRIORITY: 
        default :
          $sort = $task->getOption('priority');
      }

      $groups[$sort][$task->getId()] = $task;
    }

    // order all members within groups
    foreach ($groups as $key => $array)
    {
      self::ORDER_ASC === $sort_order ? ksort($array) : (self::SORT_BY_PRIORITY === $sort_by ? ksort($array) : krsort($array));

      $groups[$key] = $array;
    }

    // order each group
    self::ORDER_ASC === $sort_order ? ksort($groups) : krsort($groups);

    // list Tasks, keeping arrays order
    $list = array();
    foreach ($groups as $group)
    {
      foreach ($group as $task)
      {
        $list[] = $task;
      }
    }

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function getTask($id)
  {
    // get Task from Queue
    if ($enqueued = $this->client->get(self::PREFIX."task:$id"))
    {
      return Task::jsonImport($enqueued);
    }

    // get from finished Tasks
    if ($this->client->lrem(self::PREFIX.'task:finished', 0, $id))
    {
      $this->client->lpush(self::PREFIX.'task:finished', $id);

      $task = new Task(new NullJob);
      $task->setId($id);
      $task->setStatus(Task::STATUS_FINISHED);
      $task->setProgress(1);

      return $task;
    }

    // get Scheduled Tasks
    if ($scheduled = $this->client->get(self::PREFIX."task:scheduled:$id"))
    {
      return Task::jsonImport($scheduled);
    }

    return null;
  }

  /**
   * {@inheritdoc}
   */
  public function getTaskStatus($id)
  {
    // if Task is currently in Queue, return its status
    if ($task = $this->getTask($id))
    {
      foreach ($task->getChildren() as $child)
      {
        if (Task::STATUS_FAILED === $this->getTaskStatus($child->getId()))
        {
          return Task::STATUS_FAILED;
        }
      }

      return $task->getStatus();
    }

    // else return STATUS_PENDING
    else 
    {
      return Task::STATUS_PENDING;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getNextTask($profiles = null)
  {
    // check for scheduled Tasks that needs to be enqueued
    $this->checkScheduledTasks();

    switch (true)
    {
      // get next task from an array of profiles
      case is_array($profiles) && count($profiles) > 1 :

        // generate a key to store a union of profile's sets
        $hash = md5(serialize($profiles));
        $key = self::PREFIX."union:$hash";
        $delete_key = true;

        // union only profiles that are not empty
        $keys = array();
        $last_profile = null;
        foreach($profiles as $profile)
        {
          if ($this->client->zcount(self::PREFIX."profile:$profile", self::PRIORITY_MIN, '+inf'))
          {
            $keys[] = self::PREFIX."profile:$profile";
            $last_profile = $profile;
          }
        }

        if (count($keys) <= 1)
        {
          $one_profile = reset($profiles);
          $profile = $last_profile ? $last_profile : $one_profile;
          $key = self::PREFIX."profile:$profile";
          $delete_key = false;

          break;
        }

        // create a temporary sorted list (union of profiles)
        call_user_func_array(
                array($this->client, 'zunionstore'),
                array_merge(
                        array($key, count($keys)), 
                        $keys, 
                        array(array('AGGREGATE MAX'))));

        break;

      // get next task from a single profile
      case is_string($profiles) || (is_array($profiles) && count($profiles) === 1) :

        if (is_array($profiles))
        {
          $profiles = reset($profiles);
        }

        $key = self::PREFIX."profile:$profiles";
        $delete_key = false;

        break;

      // get next task from the common queue (all profiles)
      default :

        $key = self::PREFIX.'profile:'.self::COMMON_PROFILE;
        $delete_key = false;
    }

    // get all non reserved Tasks ordered by priority
    if (count($tasks_ids = $this->client->zrevrangebyscore($key, '+inf', self::PRIORITY_MIN)))
    {
      /* @hack to fix FIFO order */
      // reduce Tasts set to those of highest priority
      $next_priority_id   = reset($tasks_ids);
      $next_priority      = $this->client->zscore($key, $next_priority_id);
      $priority_task_ids  = $this->client->zrevrangebyscore($key, 
              $next_priority, 
              sprintf('(%d', $next_priority - 1));

      // sort ids in ASC order to respect FIFO rule
      sort($priority_task_ids);

      $next_id = reset($priority_task_ids);
    }

    // delete temporary keys 
    if ($delete_key)
    {
      $this->client->del($key);
    }

    // empty Queue
    if (!isset($next_id) || !$next_id)
    {
      return null;
    }

    return Task::jsonImport($this->client->get(self::PREFIX."task:$next_id"));
  }

  /**
   * Enqueue all Tasks scheduled for now
   */
  protected function checkScheduledTasks()
  {
    foreach ($this->client->zrangebyscore(self::PREFIX.'task:scheduled', 0, time()) as $id)
    {
      // enqueue Task
      $this->unschedule($this->getTask($id));
    }
  }
}