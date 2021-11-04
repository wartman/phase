<?php
namespace Std {

  use ArrayAccess;
  use ArrayIterator;
  use Countable;
  use IteratorAggregate;
  use Traversable;
  use InvalidArgumentException;

  class PhaseMap implements ArrayAccess, Countable, IteratorAggregate, Traversable
  {

    public function __construct(array $data = [])
    {
      $this->data = $data;
    }

    protected array $data;

    public function set(mixed $key, mixed $value)
    {
      $this->data[$key] = $value;
    }

    public function get(string $key):mixed
    {
      return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    public function contains(string $key):Bool
    {
      return isset($this->data[$key]);
    }

    public function remove(string $key):Bool
    {
      if (array_key_exists($key, $this->data))
      {
        unset($this->data[$key]);
        return true;
      }
      return false;
    }

    public function keys():\Std\PhaseArray
    {
      return new PhaseArray(array_keys($this->data));
    }

    public function copy()
    {
      return clone($this);
    }

    public function toString()
    {
      return $this->__toString();
    }

    public function __toString()
    {
      return implode(", ", $this->data);
    }

    public function clear()
    {
      $this->data = [];
    }

    public function offsetGet($offset)
    {
      try
      {
        return $this->data[$offset];
      }
      catch (\Throwable $e)
      {
        return null;
      }
    }

    public function offsetExists($offset)
    {
      return isset($this->data[$offset]);
    }

    public function offsetSet($offset, $value)
    {
      if ($offset == null)
      {
        throw new InvalidArgumentException();
      }
      else
      {
        $this->set($offset, $value);
      }
    }

    public function offsetUnset($offset)
    {
      if (isset($this->data[$offset]))
      {
        unset($this->data[$offset]);
      }
    }

    public function getIterator():Traversable
    {
      return new ArrayIterator($this->data);
    }

    public function count():int
    {
      return count($this->data);
    }

    public function unwrap()
    {
      return $this->data;
    }

  }

}