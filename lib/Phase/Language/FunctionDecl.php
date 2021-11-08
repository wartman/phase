<?php
namespace Phase\Language {


  class FunctionDecl
  {

    public function __construct(string $name, \Std\PhaseArray $args, ?Stmt $body, ?TypePath $ret, \Std\PhaseArray $attributes)
    {
      $this->attributes = $attributes;
      $this->ret = $ret;
      $this->body = $body;
      $this->args = $args;
      $this->name = $name;
    }

    public string $name;

    public \Std\PhaseArray $args;

    public ?Stmt $body;

    public ?TypePath $ret;

    public \Std\PhaseArray $attributes;

  }

}