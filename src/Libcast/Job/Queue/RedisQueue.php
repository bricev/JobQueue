<?php

namespace Libcast\Job\Queue;

use Libcast\Job\Exception\QueueException;
use Libcast\Job\Queue\AbstractQueue;
use Libcast\Job\Queue\QueueInterface;

use Libcast\Job\Job\NullJob;

use Libcast\Job\Task\Task;
use Libcast\Job\Task\TaskInterface;

/**
 * Redis Queue uses the following keys:
 * 
 * $prefix:task:lastid        string (incr)     unique Task id
 * $prefix:task:$id           string            stores Task data (json)
 * $prefix:task:children:$id  string (incr)     counts a Task children
 * $prefix:task:failed:$id    string (incr)     counts a Task failed atempts
 * $prefix:task:finished      list              lists finished Tasks (logs)
 * $prefix:profile:$profile   sorted set        lists Tasks from a Queue's profile
 * $prefix:profile:common     sorted set        list all Tasks from Queue
 * $prefix:union:$hash        sorted set union  union of Queue profiles (temporary)
 */
class RedisQueue extends AbstractQueue implements QueueInterface
{
  const PREFIX = 'libcast:encoding:';
  
  protected $client = null;

  protected function connect()
  {
    $this->client = new \Predis\Client(array(
        'scheme' => $this->getParameters('scheme', 'tcp'), 
        'host'   => $this->getParameters('host', 'localhost'), 
        'port'   => $this->getParameters('port', 6379), 
    ));
  }

  public function add(TaskInterface $task)
  {
    $task->setStatus(Task::STATUS_WAITING);

    // give the Task a uniq ID
    $task->setId($id = $this->client->incr(self::PREFIX.'task:lastid'));

    $pipe = $this->client->pipeline();

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
    
    // count children
    if ($parent_id = $task->getParentId())
    {
      $this->client->incr(self::PREFIX."task:children:$parent_id");
    }
    
    $pipe->execute();
    
    return $id;
  }

  public function update(TaskInterface $task)
  {
    $this->log("Task '{$task->getId()}' updated", array(
        'status'    => $task->getStatus(),
        'progress'  => $task->getProgress(false),
        'children'  => count($task->getChildren()),
    ));

    // update parent
    $parent = null;
    if ($parent_id = $task->getParentId())
    {
      $parent = $this->getTask($parent_id);
      $parent->updateChild($task);
      $this->update($parent);
    }

    // check if the Queue reserved the Task before updating its status to
    // encoding
    if (Task::STATUS_RUNNING === $task->getStatus())
    {
      if ($this->client->zscore(self::PREFIX.'profile:'.self::COMMON_PROFILE, $task->getId()) > self::PRIORITY_RUNNING)
      {
        throw new QueueException('Queue must reserve Task before it is encoded.');
      }
    }

    // try again (requeue) failed Tasks
    if (Task::STATUS_FAILED === $task->getStatus())
    {
      $fail_count = $this->client->incr(self::PREFIX."task:failed:{$task->getId()}");
      
      if ($fail_count < self::MAX_REQUEUE)
      {
        $task->setStatus(Task::STATUS_WAITING);

        // requeue the Task
        $this->flag($task, Task::STATUS_WAITING);
        
        $this->log(sprintf('Failed Task \'%d\' has been given another chance (%d/%d)',
                $task->getId(),
                $fail_count,
                self::MAX_REQUEUE));
      }
      else
      {
        // flag Task as failed
        $this->flag($task, Task::STATUS_FAILED);

        // delete requeue count
        $this->client->del(self::PREFIX."task:failed:{$task->getId()}");

        $this->log("Failed Task '{$task->getId()}' can't be treated : Worker gived up.");

        // set parent Task as failed (if any)
        if ($parent)
        {
          $parent->setStatus(Task::STATUS_FAILED);
          $this->update($parent);
        }
      }
    }

    // set parent Task as finished only when children are finished
    // remove finished Tasks
    if (Task::STATUS_FINISHED === $task->getStatus())
    {
      if ($parent)
      {
        // if all children Tasks have been executed
        if ($this->client->decr(self::PREFIX."task:children:$parent_id") <= 0)
        {
          // delete the child count
          $this->client->del(self::PREFIX."task:children:$parent_id");

          // mark the parent Task as finished, this will recursively mark all
          // parent job as finished
          $parent->setStatus(Task::STATUS_FINISHED);
          $this->update($parent);
        }
      }

      $this->client->lpush(self::PREFIX.'task:finished', $task->getId());
      $this->remove($task);

      return true;
    }

    // persist data
    return $this->client->set(self::PREFIX."task:{$task->getId()}", $task->jsonExport());
  }

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

  public function remove(TaskInterface $task)
  {
    if (!$this->client->get(self::PREFIX."task:{$task->getId()}"))
    {
      return false;
    }

    $pipe = $this->client->pipeline();
    
    // delete Task
    $pipe->del(self::PREFIX."task:{$task->getId()}");
    $pipe->zrem(self::PREFIX."profile:{$task->getOption('profile')}", $task->getId());
    $pipe->zrem(self::PREFIX.'profile:'.self::COMMON_PROFILE, $task->getId());

    // delete failed attempts count if any
    $pipe->del(self::PREFIX."task:failed:{$task->getId()}");

    return $pipe->execute();
  }

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

    $groups = array();

    // Queue does not store pending Tasks
    if (Task::STATUS_PENDING === $status)
    {
      return array();
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

        if (!$status || $status === $task->getStatus())
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

          $groups[$sort][$id] = $task;
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

      foreach ($this->client->zrangebyscore($key, 0, '(1') as $id)
      {
        $task = $this->getTask($id);

        if ((!$status || $status === $task->getStatus()) &&
                (!$priority || $priority === $task->getOption('priority')))
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

          $groups[$sort][$id] = $task;
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

        $groups[$sort][$id] = $task;
      }
    }
    
    // order each 'sort_by' group's members
    foreach ($groups as $key => $array)
    {
      self::ORDER_ASC === $sort_order ? ksort($array) : (self::SORT_BY_PRIORITY === $sort_by ? ksort($array) : krsort($array));
      
      $groups[$key] = $array;
    }
    
    // order each 'sort_by' group
    self::ORDER_ASC === $sort_order ? ksort($groups) : krsort($groups);
    
    $tasks = array();
    foreach ($groups as $group)
    {
      foreach ($group as $task)
      {
        $tasks[] = $task;
      }
    }
    
    return $tasks;
  }

  public function getTask($id)
  {
    return Task::jsonImport($this->client->get(self::PREFIX."task:$id"));
  }

  public function getTaskStatus($id)
  {
    // if Task is currently in Queue, return its status
    if ($task = $this->getTask($id))
    {
      return $task->getStatus();
    }
    
    // if Task has been registred as finished, return STATUS_FINISHED
    elseif ($this->client->lrem(self::PREFIX.'task:finished', 0, $id))
    {
      $this->client->lpush(self::PREFIX.'task:finished', $task->getId());
      return Task::STATUS_FINISHED;
    }
    
    // else return STATUS_PENDING
    else 
    {
      Task::STATUS_PENDING;
    }
  }

  public function getNextTask($profiles = null)
  {
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
        foreach($profiles as $profile)
        {
          if ($this->client->zcount(self::PREFIX."profile:$profile", self::PRIORITY_MIN, '+inf'))
          {
            $keys[] = self::PREFIX."profile:$profile";
          }
        }
        
        // create a temporary sorted list (union of profiles)
        call_user_func_array(
                array($this->client, 'zunionstore'),
                array_merge(
                        array(
                            $key, 
                            count($keys),
                        ), 
                            $keys, 
                        array(
                            array('AGGREGATE MAX')
                        )));
        
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
    $tasks_ids = $this->client->zrevrangebyscore($key, '+inf', self::PRIORITY_MIN);
    // if Queue is not empty
    if (count($tasks_ids))
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

      // get first id
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
}