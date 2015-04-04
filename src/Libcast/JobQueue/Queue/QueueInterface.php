<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file 
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Queue;

use Libcast\JobQueue\Task;

interface QueueInterface
{
    /**
     * Enqueues a Task into the head of the Queue.
     * 
     * @param \Libcast\JobQueue\Task $task
     * @return Task
     */
    public function enqueue(Task $task);

    /**
     * Shifts the Queue to ads the Task at the tail.
     *
     * @param \Libcast\JobQueue\Task $task
     * @return Task
     */
    public function shift(Task $task);

    /**
     * Persist an updated Task Data.
     * 
     * @param \Libcast\JobQueue\Task $task
     */
    public function update(Task $task);

    /**
     * Deletes a Task and its parents.
     *
     * @param \Libcast\JobQueue\Task $task
     */
    public function delete(Task $task);

    /**
     * Fetches a waiting Task from the Queue.
     *
     * @param string  $profile
     * @return Task
     */
    public function fetch($profile);

    /**
     * Flushes the Queue from all of its Tasks
     *
     * @return boolean
     */
    public function flush();

    /**
     * Retrieve a Task from Queue based on its Id.
     * 
     * @param int $id
     * @return \Libcast\JobQueue\Task
     */
    public function getTask($id);

    /**
     * Lists all Tasks from Queue.
     *
     * @param   array  $filter_by_profile  Filter by profile (eg. "high-cpu")
     * @param   array  $filter_by_status   Filter by status (pending|waiting|running|success|failed|finished)
     * @param   string $sort_by_order      Order (asc|desc)
     * @return  array                      List of Tasks
     */
    public function getTasks($filter_by_profile = [], $filter_by_status = [], $sort_by_order = 'asc');

    /**
     * Calculate the progress of a Task and its children.
     *
     * @param Task $task
     * @param bool $human_readable
     * @return float|string
     */
    public function getProgress(Task $task, $human_readable = true);
}
