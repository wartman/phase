package phase;

@:build(phase.tools.AstBuilder.buildVisitor('phase.Expr'))
interface ExprVisitor<T> {}
