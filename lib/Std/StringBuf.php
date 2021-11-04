<?php
namespace Std {


  class StringBuf
  {

    public function __construct()
    {
      $this->value = "";
    }

    public string $value;

    public function __get_length():int 
    {
      return strlen($this->value);
    }

    public function add(?string $value):StringBuf
    {
      if ($value == null)
      {
        return $this;
      }
      $this->value = "" . ($this->value) . "" . ($value) . "";
      return $this;
    }

    public function toString():string
    {
      return $this->__toString();
    }

    public function __toString():string
    {
      return $this->value;
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