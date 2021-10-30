<?php
namespace Phase\Language {


  class CallArgument extends \Std\PhaseEnum
  {

    public static function Positional(Expr $expr):CallArgument
    {
      return new CallArgument(0, "Positional", new \Std\PhaseArray([$expr]));
    }

    public static function Named(string $name, Expr $expr):CallArgument
    {
      return new CallArgument(1, "Named", new \Std\PhaseArray([$name, $expr]));
    }

  }

}