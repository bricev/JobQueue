<?php

namespace JobQueue\Domain\Task;

use JobQueue\Domain\Utils\Bag;

final class ParameterBag extends Bag
{
    /**
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            $this->add($key, $value);
        }
    }

    /**
     *
     * @param mixed $key
     * @return bool
     */
    public function has($key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     *
     * @param mixed $key
     * @return mixed
     */
    public function get($key)
    {
        if (!$this->has($key)) {
            throw new \RuntimeException(sprintf('There is no value for "%s" key', $key));
        }

        return $this->data[$key];
    }

    /**
     *
     * @param mixed $key
     * @param mixed $value
     */
    public function add($key, $value)
    {
        if (!is_string($key)) {
            throw new \RuntimeException('The key must be a string');
        }

        if (!is_scalar($value) and !is_null($value)) {
            throw new \RuntimeException(sprintf('The "%s" value must be a scalar or null', $key));
        }

        $this->data[$key] = $value;
    }

    /**
     *
     * @param mixed $key
     */
    public function remove($key)
    {
        unset($this->data[$key]);
    }
}
