<?php
namespace Phase\Language {


  class Field
  {

    public function __construct(string $name, FieldKind $kind, ?TypePath $type, \Std\PhaseArray $access, \Std\PhaseArray $attributes)
    {
      $this->attributes = $attributes;
      $this->access = $access;
      $this->type = $type;
      $this->kind = $kind;
      $this->name = $name;
    }

    public string $name;

    public FieldKind $kind;

    public ?TypePath $type;

    public \Std\PhaseArray $access;

    public \Std\PhaseArray $attributes;

  }

}