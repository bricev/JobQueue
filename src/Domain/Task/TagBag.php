<?php

namespace JobQueue\Domain\Task;

use JobQueue\Domain\Utils\Bag;

final class TagBag extends Bag
{
    /**
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $value) {
            $this->add($value);
        }
    }

    /**
     *
     * @param $value
     * @return bool
     */
    public function has($value): bool
    {
        return in_array($value, $this->data);
    }

    /**
     *
     * @param $value
     */
    public function add($value)
    {
        if (!is_string($value)) {
            throw new \RuntimeException('Tag value must be a string');
        }

        $this->data[] = $value;
    }

    /**
     *
     * @param $value
     */
    public function remove($value)
    {
        foreach (array_keys($this->data, $value) as $key) {
            unset($this->data[$key]);
        }
    }
}
