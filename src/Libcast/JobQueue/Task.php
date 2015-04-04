<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue;

use Libcast\JobQueue\Exception\TaskException;
use Libcast\JobQueue\Job\JobInterface;

class Task implements \JsonSerializable
{
    /**
     * instance of a Task not yet enqueued
     */
    const STATUS_PENDING = 'pending';

    /**
     * enqueued Task waiting to be executed
     */
    const STATUS_WAITING = 'waiting';

    /**
     * running Task (executed by a Worker)
     */
    const STATUS_RUNNING = 'running';

    /**
     * success
     */
    const STATUS_SUCCESS = 'success';

    /**
     * error
     */
    const STATUS_FAILED = 'failed';

    /**
     * Task may be destroyed
     */
    const STATUS_FINISHED = 'finished';

    /**
     * @var integer
     */
    protected $id;

    /**
     * @var integer
     */
    protected $parent_id;

    /**
     * @var JobInterface
     */
    protected $job;

    /**
     * @var string
     */
    protected $profile;

    /**
     * @var string
     */
    protected $status = self::STATUS_PENDING;

    /**
     * @var float
     */
    protected $progress = 0;

    /**
     * @var int
     */
    protected $created_at;

    /**
     * @var array
     */
    protected $parameters = [];

    /**
     * @var array
     */
    protected $children = [];

    /**
     * Create a new Task
     *
     * @param JobInterface $job
     * @param $profile
     * @param array $parameters
     */
    public function __construct(JobInterface $job, $profile, array $parameters = [])
    {
        $this->setJob($job);
        $this->setProfile($profile);
        $this->setParameters($parameters);
        $this->setCreatedAt();
    }

    /**
     *
     * @return array
     */
    public static function listStatuses()
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_WAITING,
            self::STATUS_RUNNING,
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
            self::STATUS_FINISHED,
        ];
    }

    /**
     *
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *
     * @param $id
     */
    public function setParentId($id)
    {
        $this->parent_id = $id;
    }

    /**
     *
     * @return int
     */
    public function getParentId()
    {
        return $this->parent_id;
    }

    /**
     *
     * @param JobInterface $job
     */
    public function setJob(JobInterface $job)
    {
        $this->job = $job;
    }

    /**
     *
     * @return JobInterface
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     *
     * @param string $profile
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
    }

    /**
     *
     * @return string
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     *
     * @param $status
     * @throws TaskException
     */
    public function setStatus($status)
    {
        if (!in_array($status, self::listStatuses())) {
            throw new TaskException("The status '$status' does not exists.");
        }

        $this->status = $status;
    }

    /**
     *
     * @return string
     * @throws TaskException
     */
    public function getStatus()
    {
        if (!$this->status) {
            $this->setStatus(self::STATUS_PENDING);
        }

        return $this->status;
    }

    /**
     *
     * @param float $percent
     * @throws TaskException
     */
    public function setProgress($percent)
    {
        if (!is_numeric($percent)) {
            throw new TaskException('Progress must be numeric.');
        }

        if ($percent < 0) {
            throw new TaskException('Progress must be bigger or equal to 0 (0%).');
        }

        if ($percent > 1) {
            throw new TaskException('Progress must be less or equal to 1 (100%).');
        }

        $this->progress = $percent;
    }

    /**
     *
     * @return float
     */
    public function getProgress()
    {
        return $this->progress;
    }

    /**
     *
     * @param string $string A valid date format (Eg. '2013-11-30 20:30:50')
     * @throws \Libcast\JobQueue\Exception\TaskException
     */
    protected function setCreatedAt($date = null)
    {
        try {
            $dateTime = new \DateTime($date);
            $this->created_at = $dateTime->getTimestamp();
        } catch (\Exception $e) {
            throw new TaskException("Impossible to set '$date' as date of creation.");
        }
    }

    /**
     *
     * @param string $format
     * @return bool|string
     * @throws TaskException
     */
    public function getCreatedAt($format = 'U')
    {
        if (!$this->created_at) {
            $this->setCreatedAt();
        }

        return date($format, $this->created_at);
    }

    /**
     *
     * @param array $parameters
     */
    protected function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     *
     * @param $key
     * @return bool
     */
    protected function hasParameter($key)
    {
        return in_array($key, array_keys($this->getParameters()));
    }

    /**
     *
     * @param $key
     * @param null $default
     * @return null
     */
    protected function getParameter($key, $default = null)
    {
        return $this->hasParameter($key) ? $this->parameters[$key] : $default;
    }

    /**
     *
     * @param Task $task
     */
    public function addChild(Task $task)
    {
        $task->setParentId($this->getId());

        // As children are queued in list's tail
        // they need to be added in reverse order
        array_unshift($this->children, $task);
    }

    /**
     *
     * @return array
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     *
     * @return int
     */
    public function countChildren()
    {
        $children = $this->getChildren();

        $count = count($children);
        foreach ($children as $child) { /* @var $child Task */
            $count += $child->countChildren();
        }

        return $count;
    }

    /**
     *
     * @param $json
     * @return Task
     * @throws TaskException
     */
    public static function build($json)
    {
        if (is_array($json)) {
            $data = $json;
        } elseif (!$data = json_decode($json, true)) {
            return null;
        }

        if (!isset($data['job'])) {
            throw new TaskException('Missing Job.');
        }

        if (!isset($data['profile'])) {
            throw new TaskException('Missing profile.');
        }

        $task = new Task(new $data['job'], $data['profile'], $data['parameters']);
        $task->setId($data['id']);
        $task->setParentId($data['parent_id']);
        $task->setStatus($data['status']);
        $task->setProgress($data['progress']);
        $task->setCreatedAt(date('Y-m-d H:i:s', $data['created_at']));

        foreach ($data['children'] as $child) {
            $task->addChild(self::build($child));
        }

        return $task;
    }

    /**
     * \JsonSerializable implementation
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $array = [
            'id'         => $this->getId(),
            'parent_id'  => $this->getParentId(),
            'job'        => (string) $this->getJob(),
            'profile'    => $this->getProfile(),
            'status'     => $this->getStatus(),
            'progress'   => $this->getProgress(),
            'created_at' => $this->getCreatedAt(),
            'parameters' => $this->getParameters(),
        ];

        $array['children'] = [];
        foreach ($this->getChildren() as $child) { /* @var $child Task */
            $array['children'][] = $child->jsonSerialize();
        }

        return $array;
    }

    /**
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->getId()) {
            return (string) $this->getId();
        }

        return sprintf('%s "%s" Task (%s).',
                ucfirst($this->getStatus()),
                $this->getProfile(),
                $this->getCreatedAt('Y-m-d H:i:s'));
    }
}
