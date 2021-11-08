<?php
namespace Std {


  class PhaseString
  {

    public function __construct($value)
    {
      $this->value = $value;
    }

    protected string $value;

    public function __get_length():int 
    {
      return strlen($this->value);
    }

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

    public function substr(int $pos, ?int $len = null):string
    {
      return mb_substr($this->value, $pos, $len);
    }

    public function substring(int $startIndex, ?int $endIndex = null):string
    {
      $str = $this->value;
      if ($endIndex === null)
      {
        if ($startIndex < 0)
        {
          $startIndex = 0;
        }
        return mb_substr($str, $startIndex);
      }
      if ($endIndex < 0)
      {
        $endIndex = 0;
      }
      if ($startIndex < 0)
      {
        $startIndex = 0;
      }
      if ($startIndex > $endIndex)
      {
        $tmp = $endIndex;
        $endIndex = $startIndex;
        $startIndex = $tmp;
      }
      return mb_substr($str, $startIndex, $endIndex - $startIndex);
    }

    public function __get($prop)
    {
      return $this->{'__get_' . $prop}();
    }

    public function __set($prop, $value)
    {
      $this->{'__set_' . $prop}($value);
    }
  }

}