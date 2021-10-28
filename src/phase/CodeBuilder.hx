package phase;

import phase.PhpGenerator.GeneratorError;

class CodeBuilder {
  public static function generate(code:String, pos:Position, reporter) {
    var scanner = new Scanner(code, pos.file, reporter);
    var parser = new Parser(scanner.scan(), reporter);
    return parser.parse();
  }

  public static function generateExpr(code:String, pos:Position, reporter) {
    var stmts = generate(code, pos, reporter);
    if (stmts.length > 1) {
      reporter.report(pos, '<generated>', 'Expected a single expression');
      throw new GeneratorError();
    }
    var expr = stmts[0];
    return switch Std.downcast(expr, Stmt.Expression) {
      case null:
        reporter.report(pos, '<generated>', 'Expected a single expression');
        throw new GeneratorError();
      case expr:
        expr.expression;
    }
  }
}
