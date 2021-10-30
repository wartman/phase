<?php
namespace Phase\Language {


  class Stmt
  {

    public function __construct(StmtDef $stmt, Position $pos)
    {
      $this->pos = $pos;
      $this->stmt = $stmt;
    }

    public StmtDef $stmt;

    public Position $pos;

  }

}