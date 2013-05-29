<?php

namespace Libcast\JobQueue\Task;

use Libcast\JobQueue\Exception\TaskException;
use Libcast\JobQueue\Job\JobInterface;
use Libcast\JobQueue\Task\TaskInterface;

class Task implements TaskInterface
{
  // Statuses
  const   STATUS_PENDING  = 'pending',  // instance of a Task not yet enqueued
          STATUS_WAITING  = 'waiting',  // enqueued Task waiting to be executed
          STATUS_RUNNING  = 'running',  // running Task (executed by a Job)
          STATUS_SUCCESS  = 'success',  // success
          STATUS_FAILED   = 'failed',   // error
          STATUS_FINISHED = 'finished'; // Task may be destroyed

  /**
   * @var integer
   */
  protected $id = null;

  /**
   * @var string
   */
  protected $tag;

  /**
   * @var integer
   */
  protected $parent_id = null;

  /**
   * @var JobInterface
   */
  protected $job = null;

  /**
   * @var string
   */
  protected $status = null;

  /**
   * @var float
   */
  protected $progress = 0;

  /**
   * @var int
   */
  protected $created_at = null;

  /**
   * @var int
   */
  protected $scheduled_at = null;

  /**
   * @var array
   */
  protected $options = array();

  /**
   * @var array
   */
  protected $parameters = array();

  /**
   * @var \Swift_Message
   */
  protected $message;

  /**
   * @var array
   */
  protected $children = array();

  /**
   * Create a new Task
   * 
   * Some options are required:
   * - priority : from 1 (lower) to infinite (higher)
   * - set      : any set name registred from Queue configuration
   * 
   * Required parameters may be required depending on the Job associated
   * with this Task
   * 
   * @param \Libcast\JobQueue\Job\JobInterface  $job        Affect a job to the task
   * @param array                               $options    Task options
   * @param array                               $parameters Task parameters
   * @param \Swift_Message                      $message    SwiftMailer message for notification
   */
  function __construct(JobInterface $job, $options = array(), $parameters = array(), \Swift_Message $message = null)
  {
    $this->setJob($job);
    $this->setOptions($options);
    $this->setParameters($parameters);
    if ($message)
    {
      $this->setMessage($message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getStatuses()
  {
    return array(
        self::STATUS_PENDING,
        self::STATUS_WAITING,
        self::STATUS_RUNNING,
        self::STATUS_SUCCESS,
        self::STATUS_FAILED,
        self::STATUS_FINISHED,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getFakeTaskStatuses()
  {
    return array(
        self::STATUS_PENDING,
        self::STATUS_FAILED,
        self::STATUS_FINISHED,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setId($id)
  {
    $this->id = $id;
  }

  /**
   * {@inheritdoc}
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function setTag($tag)
  {
    $this->tag = $tag;
  }

  /**
   * {@inheritdoc}
   */
  public function getTag()
  {
    if (!$this->tag)
    {
      $this->setTag(md5(microtime().rand(0, 9999)));
    }

    return $this->tag;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentId($id)
  {
    $this->parent_id = $id;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentId()
  {
    return $this->parent_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setJob(JobInterface $job)
  {
    $this->job = $job;
  }

  /**
   * 
   * @return \Libcast\JobQueue\Job\JobInterface $job
   */
  public function getJob()
  {
    return $this->job;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status)
  {
    if (!in_array($status, $this->getStatuses()))
    {
      throw new TaskException("The status '$status' does not exists.");
    }

    $this->status = $status;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus()
  {
    if (!$this->status)
    {
      $this->setStatus(self::STATUS_PENDING);
    }

    return $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function setProgress($percent)
  {
    if (!is_numeric($percent))
    {
      throw new TaskException('Progress must be numeric.');
    }

    if ($percent < 0)
    {
      throw new TaskException('Progress must be bigger or equal to 0 (0%).');
    }

    if ($percent > 1)
    {
      throw new TaskException('Progress must be less or equal to 1 (100%).');
    }

    $this->progress = $percent;
  }

  /**
   * {@inheritdoc}
   */
  public function getProgress($float = true)
  {
    $count = 1;
    $total = $this->progress;

    foreach ($this->getChildren() as $child)
    {
      $count++;
      $total += $child->getProgress();
    }

    $percent = ceil(($total / $count) * 100) / 100;

    return $float ? $percent : ($percent * 100).'%';
  }

  /**
   * 
   * @param string $string A valid date format (Eg. '2013-11-30 20:30:50')
   * @throws \Libcast\JobQueue\Exception\TaskException
   */
  protected function setCreatedAt($string = null)
  {
    try
    {
      $date = new \DateTime($string);

      $this->created_at = $date->getTimestamp();
    }
    catch (\Exception $e)
    {
      throw new TaskException("Impossible to set '$string' as date of creation ({$e->getMessage()}).");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedAt($human_readable = true)
  {
    if (!$this->created_at)
    {
      $this->setCreatedAt();
    }

    return $human_readable ? date('Y-m-d H:i:s', $this->created_at) : (int) $this->created_at;
  }

  /**
   * {@inheritdoc}
   */
  public function setScheduledAt($string = null)
  {
    try
    {
      $date = new \DateTime($string);

      $this->scheduled_at = $date->getTimestamp();
    }
    catch (\Exception $e)
    {
      throw new TaskException("Impossible to set '$string' as schedule date ({$e->getMessage()}).");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getScheduledAt($human_readable = true)
  {
    if (!$this->scheduled_at)
    {
      return null;
    }

    return $human_readable ? date('Y-m-d H:i:s', $this->scheduled_at) : (int) $this->scheduled_at;
  }

  /**
   * {@inheritdoc}
   */
  public function setOptions($options)
  {
    if ($job = $this->getJob())
    {
      $options = array_merge($job->getOptions(), $options);
    }

    $this->options = (array) $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions()
  {
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($name)
  {
    if (!isset($this->options[$name]))
    {
      throw new TaskException("The option '$name' does not exists.");
    }

    return $this->options[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function setParameters($parameters)
  {
    if ($job = $this->getJob())
    {
      $parameters = array_merge($job->getParameters(), $parameters);
    }

    $this->parameters = (array) $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getParameters()
  {
    return $this->parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getParameter($name)
  {
    if (!isset($this->parameters[$name]))
    {
      throw new Exception\TaskException("The parameter '$name' does not exists.");
    }

    return $this->parameters[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage(\Swift_Message $message)
  {
    $this->message = $message;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage()
  {
    return $this->message;
  }

  /**
   * {@inheritdoc}
   */
  public function addChild(TaskInterface $task)
  {
    if (in_array($task->getTag(), array_keys($this->getChildren())))
    {
      throw new TaskException("Child with tag '{$task->getTag()}' already exists.");
    }

    $task->setParentId($this->getId());

    $this->children[$task->getTag()] = $task;
  }

  /**
   * {@inheritdoc}
   */
  public function updateChild(TaskInterface $task)
  {
    if (!isset($this->children[$task->getTag()]))
    {
      throw new TaskException("Child with tag '{$task->getTag()}' does not exists.");
    }

    $this->children[$task->getTag()] = $task;
  }

  /**
   * {@inheritdoc}
   */
  public function removeChild(TaskInterface $task)
  {
    if (!isset($this->children[$task->getTag()]))
    {
      throw new TaskException("Child with tag '{$task->getTag()}' does not exists.");
    }

    unset($this->children[$task->getTag()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren()
  {
    return $this->children;
  }

  /**
   * {@inheritdoc}
   */
  public function getChild($tag)
  {
    if (!isset($this->children[$task->getTag()]))
    {
      throw new TaskException("Child with tag '{$task->getTag()}' does not exists.");
    }

    return $this->children[$tag];
  }

  /**
   * {@inheritdoc}
   */
  public static function jsonImport($json)
  {
    if (!$data = json_decode($json, true))
    {
      return null;
    }

    $task = new Task(new $data['job'], $data['options'], $data['parameters'], unserialize($data['message']));
    $task->setId($data['id']);
    $task->setTag($data['tag']);
    $task->setParentId($data['parent_id']);
    $task->setStatus($data['status']);
    $task->setProgress($data['progress']);
    $task->setCreatedAt(date('Y-m-d H:i:s', $data['created_at']));
    $task->setScheduledAt(date('Y-m-d H:i:s', $data['scheduled_at']));

    foreach ($data['children'] as $child)
    {
      $task->addChild(self::jsonImport($child));
    }

    return $task;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonExport()
  {
    $array = array(
        'id'            => $this->getId(),
        'tag'           => $this->getTag(),
        'parent_id'     => $this->getParentId(),
        'job'           => $this->getJob()->getClassName(),
        'status'        => $this->getStatus(),
        'progress'      => $this->getProgress(),
        'created_at'    => $this->getCreatedAt(false),
        'scheduled_at'  => $this->getScheduledAt(false),
        'options'       => $this->getOptions(),
        'parameters'    => $this->getParameters(),
        'message'       => serialize($this->getMessage()),
        'children'      => array(),
    );

    foreach ($this->getChildren() as $child)
    {
      $array['children'][] = $child->jsonExport();
    }

    return json_encode($array);
  }

  public function __toString()
  {
    if ($this->getId())
    {
      return (string) $this->getId();
    }

    return (string) $this->getTag();
  }
}