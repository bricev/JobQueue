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
     *
     * @var integer
     */
    protected $id;

    /**
     *
     * @var string
     */
    protected $name;

    /**
     *
     * @var string
     */
    protected $profile;

    /**
     *
     * @var JobInterface
     */
    protected $job;

    /**
     *
     * @var array
     */
    protected $parameters = [];

    /**
     *
     * @var string
     */
    protected $status = self::STATUS_PENDING;

    /**
     *
     * @var float
     */
    protected $progress = 0;

    /**
     *
     * @var int
     */
    protected $created_at;

    /**
     *
     * @var bool
     */
    protected $allow_failure = null;

    /**
     *
     * @var integer
     */
    protected $root_id;

    /**
     *
     * @var integer
     */
    protected $parent_id;

    /**
     *
     * @var array
     */
    protected $children = [];

    /**
     * Create a new Task
     *
     * @param $name
     * @param $profile
     * @param JobInterface $job
     * @param array $parameters
     * @throws TaskException
     */
    public function __construct($name, $profile, JobInterface $job, array $parameters = [])
    {
        $this->setName($name);
        $this->setProfile($profile);
        $this->setJob($job);
        $this->setParameters($parameters);
        $this->setCreatedAt($this->getParameter('created_at'));
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
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
     * @param $status
     * @throws TaskException
     */
    public function setStatus($status)
    {
        if (!in_array($status, self::listStatuses())) {
            throw new TaskException("The status '$status' does not exists");
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
     * @param float $percent
     * @throws TaskException
     */
    public function setProgress($percent)
    {
        if (!is_numeric($percent)) {
            throw new TaskException('Progress must be numeric');
        }

        if ($percent < 0) {
            throw new TaskException('Progress must be bigger or equal to 0 (0%)');
        }

        if ($percent > 1) {
            throw new TaskException('Progress must be less or equal to 1 (100%)');
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
            throw new TaskException("Impossible to set '$date' as date of creation");
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
     * @return bool
     */
    public function canFail()
    {
        if (is_null($this->allow_failure)) {
            $this->allow_failure = $this->getParameter('allow_failure', false);
        }

        return (bool) $this->allow_failure;
    }

    /**
     *
     * @param int $root_id
     */
    public function setRootId($root_id)
    {
        $this->root_id = $root_id;
    }

    /**
     *
     * @return int
     */
    public function getRootId()
    {
        return $this->isRoot() ? $this->getId() : $this->root_id;
    }

    /**
     *
     * @return bool
     */
    public function isRoot()
    {
        return is_null($this->root_id);
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
     * @param Task $task
     */
    public function addChild(Task $task)
    {
        $task->setParentId($this->getId());

        $this->children[] = $task;
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
            throw new TaskException('Missing Job');
        }

        if (!isset($data['profile'])) {
            throw new TaskException('Missing profile');
        }

        $task = new Task($data['name'], $data['profile'], new $data['job'], $data['parameters']);
        $task->setId($data['id']);
        $task->setStatus($data['status']);
        $task->setProgress($data['progress']);
        $task->setCreatedAt(date('Y-m-d H:i:s', $data['created_at']));
        $task->setRootId($data['root_id']);
        $task->setParentId($data['parent_id']);

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
            'name'       => $this->getName(),
            'profile'    => $this->getProfile(),
            'job'        => (string) $this->getJob(),
            'parameters' => $this->getParameters(),
            'status'     => $this->getStatus(),
            'progress'   => $this->getProgress(),
            'created_at' => $this->getCreatedAt(),
            'root_id'    => $this->getRootId(),
            'parent_id'  => $this->getParentId(),
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
        if ($name = $this->getName()) {
            return $name;
        }

        if ($id = $this->getId()) {
            return (string) $id;
        }

        return sprintf('%s "%s" Task (%s)',
                ucfirst($this->getStatus()),
                $this->getProfile(),
                $this->getCreatedAt('Y-m-d H:i:s'));
    }
}
