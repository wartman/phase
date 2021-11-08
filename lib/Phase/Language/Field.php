<?php
namespace Phase\Language {


  class Field
  {

    public function __construct(string $name, FieldKind $kind, \Std\PhaseArray $access, \Std\PhaseArray $attributes, Position $pos)
    {
      $this->pos = $pos;
      $this->attributes = $attributes;
      $this->access = $access;
      $this->kind = $kind;
      $this->name = $name;
    }

    public string $name;

    public FieldKind $kind;

    public \Std\PhaseArray $access;

    public \Std\PhaseArray $attributes;

    public Position $pos;

  }

}