package phase;

@:build(phase.tools.AstBuilder.buildVisitor('phase.Stmt'))
interface StmtVisitor<T> {}
