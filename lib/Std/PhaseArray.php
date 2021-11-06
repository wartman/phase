<?php
namespace Std {

  use ArrayAccess;
  use ArrayIterator;
  use Countable;
  use IteratorAggregate;
  use Traversable;

  class PhaseArray implements ArrayAccess, Countable, IteratorAggregate, Traversable
  {

    public function __construct(array $data = [])
    {
      $this->data = $data;
    }

    protected array $data;

    public function __get_length():int 
    {
      return count($this->data);
    }

    public function at(int $index)
    {
      return $this->data[$index];
    }

    public function insert(int $index, $value):int
    {
      return $this->data[$index] = $value;
    }

    public function push($value):int
    {
      $this->data[] = $value;
      return $this->length;
    }

    public function copy():PhaseArray
    {
      return clone($this);
    }

    public function filter($f):PhaseArray
    {
      $out = new \Std\PhaseArray([]);
      foreach ($this->data as $item)
      {
        if ($f($item))
        {
          $out->push($item);
        }
      }
      return $out;
    }

    public function find($elt):mixed
    {
      foreach ($this->data as $item)
      {
        if ($elt($item))
        {
          return $item;
        }
      }
      return null;
    }

    public function map($transform):PhaseArray
    {
      $out = new \Std\PhaseArray([]);
      foreach ($this->data as $item)
      {
        $out->push($transform($item));
      }
      return $out;
    }

    public function contains($item):Bool
    {
      return $this->indexOf($item) > -1;
    }

    public function indexOf($item):int
    {
      $index = array_search($item, $this->data, true);
      return $index == false ? -1 : $index;
    }

    public function remove($item):Bool
    {
      $removed = false;
      for ($index = 0; $index < $this->length; $index++)
      {
        if ($this->data[$index] == $item)
        {
          array_splice($this->data, $index, 1);
          $removed = true;
          break;
        }
      }
      return $removed;
    }

    public function reverse()
    {
      $this->data = array_reverse($this->data);
    }

    public function pop()
    {
      return array_pop($this->data);
    }

    public function shift()
    {
      return array_shift($this->data);
    }

    public function sort($f)
    {
      usort($this->data, $f);
    }

    public function join(string $sep):string
    {
      return implode($sep, $this->data);
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
        return array_slice($this->data, $pos);
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
          return array_slice($this->data, $pos, $end - $pos);
        }
      }
    }

    public function concat(PhaseArray $other)
    {
      return new PhaseArray(array_merge($this->data, $other->unwrap()));
    }

    public function splice(int $pos, int $len):PhaseArray
    {
      if ($len < 0)
      {
        return new \Std\PhaseArray([]);
      }
      return array_splice($this->data, $pos, $len);
    }

    public function unshift($item)
    {
      return array_unshift($this->data, $item);
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
        $this->data[] = $value;
      }
      else
      {
        $this->insert($offset, $value);
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
      return $this->length;
    }

    public function unwrap()
    {
      return $this->data;
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