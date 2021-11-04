<?php
namespace Phase\Language {


  class FunctionArg
  {

    public function __construct(string $name, ?TypePath $type, ?Expr $expr, Bool $isInit = false)
    {
      $this->isInit = $isInit;
      $this->expr = $expr;
      $this->type = $type;
      $this->name = $name;
    }

    public string $name;

    public ?TypePath $type;

    public ?Expr $expr;

    public Bool $isInit;

  }

}