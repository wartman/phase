<?php
namespace Phase\Language {


  class TypedExpr
  {

    public function __construct(ExprDef $expr, Position $pos, Type $type)
    {
      $this->type = $type;
      $this->pos = $pos;
      $this->expr = $expr;
    }

    public ExprDef $expr;

    public Position $pos;

    public Type $type;

  }

}