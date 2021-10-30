<?php
namespace Std\Compiler {

  use Attribute;

  #[Attribute(Attribute::TARGET_CLASS)]
  class Rename
  {

    public function __construct(string $name)
    {
      $this->name = $name;
    }

    public string $name;

  }

}