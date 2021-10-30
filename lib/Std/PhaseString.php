<?php
namespace Std {


  class PhaseString
  {

    public function __construct($value)
    {
      $this->value = $value;
    }

    protected string $value;

    public function toLowerCase():string
    {
      return mb_strtolower($this->value);
    }

    public function toUpperCase():string
    {
      return mb_strtoupper($this->value);
    }

    public function split(string $sep):PhaseArray
    {
      return new PhaseArray(explode($sep, $this->value));
    }

  }

}