<?php
namespace Phase {

  use Phase\Language\Type;

  class Scope
  {

    public function __construct(?Scope $parent = null, ?\Std\PhaseMap $values = null, ?\Std\PhaseArray $children = null)
    {
      $this->children = $children;
      $this->values = $values;
      $this->parent = $parent;
      if ($this->values === null)
      {
        $this->values = new \Std\PhaseMap();
      }
      if ($this->children === null)
      {
        $this->children = new \Std\PhaseArray([]);
      }
    }

    public ?Scope $parent;

    public ?\Std\PhaseMap $values;

    public ?\Std\PhaseArray $children;

    public function declare(string $name, Type $type)
    {
      $this->values[$name] = $type;
    }

    public function isDeclared(string $name):Bool
    {
      if (!(isset($this->values[$name])) && $this->parent !== null)
      {
        return $this->parent->isDeclared($name);
      }
      return isset($this->values[$name]);
    }

    public function resolve(string $name):Type
    {
      if (isset($this->values[$name]))
      {
        return $this->values[$name];
      }
      if ($this->parent !== null)
      {
        return $this->parent->resolve($name);
      }
      return Type::TUnknown(null);
    }

    public function addChild(Scope $child)
    {
      $this->children->push($child);
    }

    public function pushChild():Scope
    {
      $child = new Scope($this);
      $this->addChild($child);
      return $child;
    }

  }

}