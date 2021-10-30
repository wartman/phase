<?php
namespace Phase\Language {


  class Expr
  {

    public function __construct(ExprDef $expr, Position $pos)
    {
      $this->pos = $pos;
      $this->expr = $expr;
    }

    public ExprDef $expr;

    public Position $pos;

  }

}