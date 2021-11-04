<?php
namespace Phase {

  use Phase\Language\Type;

  class TypeLoader
  {

    public function __construct(string $root)
    {
      $this->root = $root;
    }

    public string $root;

    public function load(string $path):?Type
    {
      return Type::TUnknown(null);
    }

  }

}