<?php
namespace Phase\Language {


  class TypePath
  {

    public function __construct(\Std\PhaseArray $ns, string $name, \Std\PhaseArray $params, Bool $isAbsolute = false, Bool $isNullable = false)
    {
      $this->isNullable = $isNullable;
      $this->isAbsolute = $isAbsolute;
      $this->params = $params;
      $this->name = $name;
      $this->ns = $ns;
    }

    public \Std\PhaseArray $ns;

    public string $name;

    public \Std\PhaseArray $params;

    public Bool $isAbsolute;

    public Bool $isNullable;

  }

}