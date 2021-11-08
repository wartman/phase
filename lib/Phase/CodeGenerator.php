<?php
namespace Phase {

  use Phase\Language\Stmt;
  use Phase\Language\StmtDef;
  use Phase\Language\Expr;
  use Phase\Language\Position;

  class CodeGenerator
  {

    static public function generate(string $code, Position $pos, ErrorReporter $reporter):\Std\PhaseArray
    {
      $scanner = new Scanner(new Source(content: $code, file: "<generated>"), $reporter);
      $parser = new Parser($scanner->scan(), $reporter);
      return $parser->parse();
    }

    static public function generateStmt(string $code, Position $pos, ErrorReporter $reporter):Stmt
    {
      $stmts = static::generate($code, $pos, $reporter);
      if ($stmts->length !== 1)
      {
        $reporter->report($pos, "Expected a single statement");
        throw new ParserException();
      }
      return $stmts[0];
    }

    static public function generateExpr(string $code, Position $pos, ErrorReporter $reporter):Expr
    {
      $stmts = static::generate($code, $pos, $reporter);
      if ($stmts->length !== 1)
      {
        $reporter->report($pos, "Expected a single expression");
        throw new ParserException();
      }
      $stmt = $stmts[0]->stmt;
      $__matcher_1 = $stmt;
      if ($__matcher_1->tag === "SExpr") { 
        $expr = $__matcher_1->params[0];
        return $expr;
      }
      else {
        null;
      };
      $reporter->report($pos, "Expected an expression");
      throw new ParserException();
    }

  }

}