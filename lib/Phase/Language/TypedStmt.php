<?php
namespace Phase\Language {


  class TypedStmt
  {

    public function __construct(StmtDef $stmt, Position $pos, Type $type)
    {
      $this->type = $type;
      $this->pos = $pos;
      $this->stmt = $stmt;
    }

    public StmtDef $stmt;

    public Position $pos;

    public Type $type;

  }

}