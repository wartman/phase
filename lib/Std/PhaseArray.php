<?php
namespace Std {

  use ArrayAccess;
  use ArrayIterator;
  use Countable;
  use IteratorAggregate;
  use Traversable;

  class PhaseArray implements ArrayAccess, Countable, IteratorAggregate, Traversable
  {

    public function __construct(array $value)
    {
      $this->value = $value;
    }

    protected array $value;

    public function __get_length():int 
    {
      return count($this->value);
    }

    public function at(int $index)
    {
      return $this->value[$index];
    }

    public function insert(int $index, $value):int
    {
      return $this->value[$index] = $value;
    }

    public function push($value):int
    {
      $this->value[] = $value;
      return $this->length;
    }

    public function copy():PhaseArray
    {
      return clone($this);
    }

    public function filter($f):PhaseArray
    {
      $out = new \Std\PhaseArray([]);
      foreach ($this->value as $item)
      {
        if ($f($item))
        {
          $out->push($item);
        }
      }
      return $out;
    }

    public function map($transform):PhaseArray
    {
      $out = new \Std\PhaseArray([]);
      foreach ($this->value as $item)
      {
        $out->push($transform(value));
      }
      return $out;
    }

    public function contains($item):Bool
    {
      return $this->indexOf($item) != -1;
    }

    public function indexOf($item):int
    {
      $index = array_search($item, $this->value, true);
      return $index == false ? -1 : $index;
    }

    public function remove($item):Bool
    {
      $removed = false;
      for ($index = 0; $index < $this->length; $index++)
      {
        if ($this->value[$index] == $item)
        {
          array_splice($this->value, $index, 1);
          $removed = true;
          break;
        }
      }
      return $removed;
    }

    public function reverse()
    {
      $this->value = array_reverse($this->value);
    }

    public function pop()
    {
      return array_pop($this->value);
    }

    public function shift()
    {
      return array_shift($this->value);
    }

    public function sort($f)
    {
      usort($this->value, $f);
    }

    public function join(string $sep):string
    {
      return implode($sep, $this->value);
    }

    public function slice(int $pos, int $end = null):PhaseArray
    {
      if ($pos < 0)
      {
        $pos = $pos + $this->length;
      }
      if ($pos < 0)
      {
        $pos = 0;
      }
      if ($end == null)
      {
        return array_slice($this->value, $pos);
      }
      else
      {
        if ($end <= 0)
        {
          $end = $end + $this->length;
        }
        if ($end <= $pos)
        {
          return new \Std\PhaseArray([]);
        }
        else
        {
          return array_slice($this->value, $pos, $end - $pos);
        }
      }
    }

    public function concat(PhaseArray $other)
    {
      return new PhaseArray(array_merge($this->value, $other->unwrap()));
    }

    public function splice(int $pos, int $len):PhaseArray
    {
      if ($len < 0)
      {
        return new \Std\PhaseArray([]);
      }
      return array_splice($this->value, $pos, $len);
    }

    public function unshift($item)
    {
      return array_unshift($this->value, $item);
    }

    public function offsetGet($offset)
    {
      try
      {
        return $this->value[$offset];
      }
      catch (\Throwable $e)
      {
        return null;
      }
    }

    public function offsetExists($offset)
    {
      return isset($this->value[$offset]);
    }

    public function offsetSet($offset, $value)
    {
      if ($offset == null)
      {
        $this->value[] = $value;
      }
      else
      {
        $this->insert($offset, $value);
      }
    }

    public function offsetUnset($offset)
    {
      if (isset($this->value[$offset]))
      {
        unset($this->value[$offset]);
      }
    }

    public function getIterator():Traversable
    {
      return new ArrayIterator($this->value);
    }

    public function count():int
    {
      return $this->length;
    }

    public function unwrap()
    {
      return $this->value;
    }

    public function __get($prop)
    {
      return $this->{'__get_' . $prop}();
    }

    public function __set($prop, $value)
    {
      $this->{'__set_' . $prop}($value);
    }
  }

}