<?php
namespace Phase\Language {


  class MatchCase
  {

    public function __construct(Expr $condition, \Std\PhaseArray $body, Bool $isDefault)
    {
      $this->isDefault = $isDefault;
      $this->body = $body;
      $this->condition = $condition;
    }

    public Expr $condition;

    public \Std\PhaseArray $body;

    public Bool $isDefault;

  }

}