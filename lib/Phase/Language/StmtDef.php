<?php
namespace Phase\Language {


  class StmtDef extends \Std\PhaseEnum
  {

    public static function SExpr(Expr $expr):StmtDef
    {
      return new StmtDef(0, "SExpr", new \Std\PhaseArray([$expr]));
    }

    public static function SUse(\Std\PhaseArray $path, UseKind $kind, \Std\PhaseArray $attributes):StmtDef
    {
      return new StmtDef(1, "SUse", new \Std\PhaseArray([$path, $kind, $attributes]));
    }

    public static function SNamespace(TypePath $path, \Std\PhaseArray $decls, \Std\PhaseArray $attributes):StmtDef
    {
      return new StmtDef(2, "SNamespace", new \Std\PhaseArray([$path, $decls, $attributes]));
    }

    public static function SVar(string $name, ?TypePath $type, Expr $initializer):StmtDef
    {
      return new StmtDef(3, "SVar", new \Std\PhaseArray([$name, $type, $initializer]));
    }

    public static function SGlobal(string $name):StmtDef
    {
      return new StmtDef(4, "SGlobal", new \Std\PhaseArray([$name]));
    }

    public static function SThrow(Expr $expr):StmtDef
    {
      return new StmtDef(5, "SThrow", new \Std\PhaseArray([$expr]));
    }

    public static function STry(Stmt $body, \Std\PhaseArray $catches):StmtDef
    {
      return new StmtDef(6, "STry", new \Std\PhaseArray([$body, $catches]));
    }

    public static function SCatch(string $name, ?TypePath $type, Stmt $body):StmtDef
    {
      return new StmtDef(7, "SCatch", new \Std\PhaseArray([$name, $type, $body]));
    }

    public static function SWhile(Expr $condition, Stmt $body, Bool $inverted):StmtDef
    {
      return new StmtDef(8, "SWhile", new \Std\PhaseArray([$condition, $body, $inverted]));
    }

    public static function SFor(string $key, ?string $value, Expr $target, Stmt $body):StmtDef
    {
      return new StmtDef(9, "SFor", new \Std\PhaseArray([$key, $value, $target, $body]));
    }

    public static function SIf(Expr $condition, Stmt $thenBranch, ?Stmt $elseBranch):StmtDef
    {
      return new StmtDef(10, "SIf", new \Std\PhaseArray([$condition, $thenBranch, $elseBranch]));
    }

    public static function SSwitch(Expr $target, \Std\PhaseArray $cases):StmtDef
    {
      return new StmtDef(11, "SSwitch", new \Std\PhaseArray([$target, $cases]));
    }

    public static function SBlock(\Std\PhaseArray $statements):StmtDef
    {
      return new StmtDef(12, "SBlock", new \Std\PhaseArray([$statements]));
    }

    public static function SReturn(?Expr $value):StmtDef
    {
      return new StmtDef(13, "SReturn", new \Std\PhaseArray([$value]));
    }

    public static function SFunction(FunctionDecl $decl):StmtDef
    {
      return new StmtDef(14, "SFunction", new \Std\PhaseArray([$decl]));
    }

    public static function SClass(ClassDecl $cls):StmtDef
    {
      return new StmtDef(15, "SClass", new \Std\PhaseArray([$cls]));
    }

  }

}