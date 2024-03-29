<?php
namespace Phase\Language {


  class ClassDecl
  {

    public function __construct(string $name, string $kind, ?TypePath $superclass, \Std\PhaseArray $interfaces, \Std\PhaseArray $fields, \Std\PhaseArray $attributes)
    {
      $this->attributes = $attributes;
      $this->fields = $fields;
      $this->interfaces = $interfaces;
      $this->superclass = $superclass;
      $this->kind = $kind;
      $this->name = $name;
    }

    public string $name;

    public string $kind;

    public ?TypePath $superclass;

    public \Std\PhaseArray $interfaces;

    public \Std\PhaseArray $fields;

    public \Std\PhaseArray $attributes;

  }

}