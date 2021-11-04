<?php
namespace Phase {

  use Phase\Language\Type;

  class Context
  {

    public function __construct(TypeLoader $loader, ?\Std\PhaseMap $types = null)
    {
      $this->types = $types;
      $this->loader = $loader;
      if ($this->types == null)
      {
        $this->types = new \Std\PhaseMap();
      }
    }

    public TypeLoader $loader;

    public ?\Std\PhaseMap $types;

    public function addTypes(\Std\PhaseMap $types):Context
    {
      foreach ($types as $name => $type)
      {
        $this->types->set($name, $type);
      }
      return $this;
    }

    public function getType(string $name):Type
    {
      if ($this->types->contains($name))
      {
        return $this->types->get($name);
      }
      $type = $this->loader->load($name);
      if ($type != null)
      {
        $this->types->set($name, $type);
        return $type;
      }
      return new TUnknown(null);
    }

    public function unify(Type $a, Type $b)
    {
      return $a == $b;
    }

  }

}