<?php
namespace Phase\Language {


  class ExprDef extends \Std\PhaseEnum
  {

    public static function EAttribute(TypePath $path, \Std\PhaseArray $args):ExprDef
    {
      return new ExprDef(0, "EAttribute", new \Std\PhaseArray([$path, $args]));
    }

    public static function EAssign(string $name, Expr $value):ExprDef
    {
      return new ExprDef(1, "EAssign", new \Std\PhaseArray([$name, $value]));
    }

    public static function EBinary(Expr $left, string $op, Expr $right):ExprDef
    {
      return new ExprDef(2, "EBinary", new \Std\PhaseArray([$left, $op, $right]));
    }

    public static function EUnary(string $op, Expr $expr, Bool $isRight):ExprDef
    {
      return new ExprDef(3, "EUnary", new \Std\PhaseArray([$op, $expr, $isRight]));
    }

    public static function EIs(Expr $left, TypePath $type):ExprDef
    {
      return new ExprDef(4, "EIs", new \Std\PhaseArray([$left, $type]));
    }

    public static function ELogical(Expr $left, string $op, Expr $right):ExprDef
    {
      return new ExprDef(5, "ELogical", new \Std\PhaseArray([$left, $op, $right]));
    }

    public static function ERange(Expr $from, Expr $to):ExprDef
    {
      return new ExprDef(6, "ERange", new \Std\PhaseArray([$from, $to]));
    }

    public static function ECall(Expr $callee, \Std\PhaseArray $args):ExprDef
    {
      return new ExprDef(7, "ECall", new \Std\PhaseArray([$callee, $args]));
    }

    public static function EGet(Expr $target, Expr $field):ExprDef
    {
      return new ExprDef(8, "EGet", new \Std\PhaseArray([$target, $field]));
    }

    public static function ESet(Expr $target, Expr $field, Expr $value):ExprDef
    {
      return new ExprDef(9, "ESet", new \Std\PhaseArray([$target, $field, $value]));
    }

    public static function EArrayGet(Expr $target, ?Expr $field):ExprDef
    {
      return new ExprDef(10, "EArrayGet", new \Std\PhaseArray([$target, $field]));
    }

    public static function EArraySet(Expr $target, ?Expr $field, Expr $value):ExprDef
    {
      return new ExprDef(11, "EArraySet", new \Std\PhaseArray([$target, $field, $value]));
    }

    public static function ETernary(Expr $condition, Expr $thenBranch, Expr $elseBranch):ExprDef
    {
      return new ExprDef(12, "ETernary", new \Std\PhaseArray([$condition, $thenBranch, $elseBranch]));
    }

    public static function ESuper(string $method):ExprDef
    {
      return new ExprDef(13, "ESuper", new \Std\PhaseArray([$method]));
    }

    public static function EPath(TypePath $path):ExprDef
    {
      return new ExprDef(14, "EPath", new \Std\PhaseArray([$path]));
    }

    public static function EThis():ExprDef
    {
      return new ExprDef(15, "EThis", new \Std\PhaseArray([]));
    }

    public static function EStatic():ExprDef
    {
      return new ExprDef(16, "EStatic", new \Std\PhaseArray([]));
    }

    public static function EGrouping(Expr $expr):ExprDef
    {
      return new ExprDef(17, "EGrouping", new \Std\PhaseArray([$expr]));
    }

    public static function ELiteral(Literal $value):ExprDef
    {
      return new ExprDef(18, "ELiteral", new \Std\PhaseArray([$value]));
    }

    public static function EArrayLiteral(\Std\PhaseArray $values, Bool $isNative):ExprDef
    {
      return new ExprDef(19, "EArrayLiteral", new \Std\PhaseArray([$values, $isNative]));
    }

    public static function EMapLiteral(\Std\PhaseArray $keys, \Std\PhaseArray $values, Bool $isNative):ExprDef
    {
      return new ExprDef(20, "EMapLiteral", new \Std\PhaseArray([$keys, $values, $isNative]));
    }

    public static function ELambda(Stmt $func):ExprDef
    {
      return new ExprDef(21, "ELambda", new \Std\PhaseArray([$func]));
    }

    public static function EVariable(string $name):ExprDef
    {
      return new ExprDef(22, "EVariable", new \Std\PhaseArray([$name]));
    }

    public static function EMatch(Expr $target, \Std\PhaseArray $cases):ExprDef
    {
      return new ExprDef(23, "EMatch", new \Std\PhaseArray([$target, $cases]));
    }

  }

}