<?php
namespace Phase\Language {


  class FieldKind extends \Std\PhaseEnum
  {

    public static function FUse(TypePath $type):FieldKind
    {
      return new FieldKind(0, "FUse", new \Std\PhaseArray([$type]));
    }

    public static function FVar(string $name, ?TypePath $type, ?Expr $initializer):FieldKind
    {
      return new FieldKind(1, "FVar", new \Std\PhaseArray([$name, $type, $initializer]));
    }

    public static function FProp(?FunctionDecl $getter, ?FunctionDecl $setter, ?TypePath $type):FieldKind
    {
      return new FieldKind(2, "FProp", new \Std\PhaseArray([$getter, $setter, $type]));
    }

    public static function FFun(FunctionDecl $fun):FieldKind
    {
      return new FieldKind(3, "FFun", new \Std\PhaseArray([$fun]));
    }

  }

}