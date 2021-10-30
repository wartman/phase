<?php
namespace Phase\Language {


  class Type extends \Std\PhaseEnum
  {

    public static function TNullable(Type $type):Type
    {
      return new Type(0, "TNullable", new \Std\PhaseArray([$type]));
    }

    public static function TVoid():Type
    {
      return new Type(1, "TVoid", new \Std\PhaseArray([]));
    }

    public static function TAny():Type
    {
      return new Type(2, "TAny", new \Std\PhaseArray([]));
    }

    public static function TUnknown(?TypePath $path):Type
    {
      return new Type(3, "TUnknown", new \Std\PhaseArray([$path]));
    }

    public static function TFun(FunctionDecl $func):Type
    {
      return new Type(4, "TFun", new \Std\PhaseArray([$func]));
    }

    public static function TClass(ClassDecl $cls):Type
    {
      return new Type(5, "TClass", new \Std\PhaseArray([$cls]));
    }

    public static function TInstance(ClassDecl $cls):Type
    {
      return new Type(6, "TInstance", new \Std\PhaseArray([$cls]));
    }

  }

}