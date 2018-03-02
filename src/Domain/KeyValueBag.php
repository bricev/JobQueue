<?php

namespace JobQueue\Domain;

use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;

final class KeyValueBag implements \ArrayAccess, \Countable, \SeekableIterator, \Serializable, \JsonSerializable
{
    /**
     *
     * @var array
     */
    private $data = [];

    /**
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $offset => $value) {
            $this->offsetSet($offset, $value);
        }
    }

    /**
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new \RuntimeException(sprintf('There is no value for "%s" key', $offset));
        }

        return $this->data[$offset];
    }

    /**
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (!is_string($offset)) {
            throw new \RuntimeException('The key must be a string');
        }

        if (!is_scalar($value) and !is_null($value)) {
            throw new \RuntimeException(sprintf('The "%s" value must be a scalar or null', $offset));
        }

        $this->data[$offset] = $value;
    }

    /**
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

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
     * @param int $offset
     * @return int
     */
    public function seek($offset)
    {
        if (!isset($this->data[$offset])) {
            throw new OutOfBoundsException;
        }

        return $offset;
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
