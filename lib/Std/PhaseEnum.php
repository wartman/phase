<?php
namespace Std {


  class PhaseEnum
  {

    public function __construct(int $index, string $tag, \Std\PhaseArray $params)
    {
      $this->params = $params;
      $this->tag = $tag;
      $this->index = $index;
    }

    public int $index;

    public string $tag;

    public \Std\PhaseArray $params;

  }

}