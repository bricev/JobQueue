<?php

namespace JobQueue\Domain\Utils;

abstract class Bag implements \Countable, \SeekableIterator, \Serializable, \JsonSerializable
{
    /**
     *
     * @var array
     */
    protected $data = [];

    /**
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->data);
    }


    /**
     *
     * @param int $key
     * @return int
     */
    public function seek($key)
    {
        if ($key >= count($this->data)) {
            throw new \OutOfBoundsException;
        }

        $this->rewind();
        for ($i=0; $i<$key; $i++) {
            $this->next();
        }
    }

    /**
     *
     * @return mixed
     */
    public function current()
    {
        return current($this->data);
    }

    /**
     *
     * @return mixed
     */
    public function next()
    {
        return next($this->data);
    }

    /**
     *
     * @return mixed
     */
    public function key()
    {
        return key($this->data);
    }

    /**
     *
     * @return bool
     */
    public function valid(): bool
    {
        return null !== key($this->data);
    }

    /**
     *
     * @return mixed
     */
    public function rewind()
    {
        return reset($this->data);
    }

    /**
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this->data);
    }

    /**
     *
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $this->data = unserialize($serialized);
    }

    /**
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     *
     * @return array
     */
    public function __toArray(): array
    {
        return $this->data;
    }
}
