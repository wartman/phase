package phase.analysis;

class Context {
  var typedExprs:Map<Expr, Type>;
  var typedDecls:Map<String, Type>;

  public function new(typedExprs, typedDecls) {
    this.typedExprs = typedExprs;
    this.typedDecls = typedDecls;
  }

  public function typeOf(expr:Expr) {
    return typedExprs.get(expr);
  }

  public function getType(name:String) {
    return typedDecls.get(name);
  }

  public function getTypes() {
    return typedDecls.copy();
  }

  public function unify(a:Type, b:Type) {
    // @todo: Something more robust
    return a.equals(b);
  }
}
