<?php
namespace Phase\Language {


  class Literal extends \Std\PhaseEnum
  {

    public static function LString(string $value):Literal
    {
      return new Literal(0, "LString", new \Std\PhaseArray([$value]));
    }

    public static function LNumber(string $value):Literal
    {
      return new Literal(1, "LNumber", new \Std\PhaseArray([$value]));
    }

    public static function LTrue():Literal
    {
      return new Literal(2, "LTrue", new \Std\PhaseArray([]));
    }

    public static function LFalse():Literal
    {
      return new Literal(3, "LFalse", new \Std\PhaseArray([]));
    }

    public static function LNull():Literal
    {
      return new Literal(4, "LNull", new \Std\PhaseArray([]));
    }

  }

}