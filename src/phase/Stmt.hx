package phase;

@:autoBuild(phase.tools.AstBuilder.buildNode())
interface Stmt {
  public function accept<T>(visitor:StmtVisitor<T>):T;
}

class Expression implements Stmt {
  var expression:Expr;
}

enum UseTarget {
  TargetType(name:Token);
  TargetFunction(name:Token);
}

enum UseKind {
  UseNormal;
  UseAlias(alias:UseTarget);
  UseSub(items:Array<UseTarget>);
}

class Use implements Stmt {
  var path:Array<Token>;
  var absolute:Bool;
  var kind:UseKind;
  var attribute:Array<Expr>;
}

class Namespace implements Stmt {
  var path:Array<Token>;
  var decls:Array<Stmt>;
  var attribute:Array<Expr>;
}

class Var implements Stmt {
  var name:Token;
  var type:Null<Expr.Type>;
  var initializer:Expr;
}

class Global implements Stmt {
  var name:Token;
}

class Throw implements Stmt {
  var keyword:Token;
  var expr:Expr;
}

typedef Caught = {
  name:Token,
  type:Expr.Type,
  body:Stmt
}

class Try implements Stmt {
  var body:Stmt;
  var catches:Array<Caught>;
}

class While implements Stmt {
  var condition:Expr;
  var body:Stmt;
  var inverted:Bool;
}

class For implements Stmt {
  var key:Token;
  var value:Null<Token>;
  var target:Expr;
  var body:Stmt;
}

class If implements Stmt {
  var condition:Expr;
  var thenBranch:Stmt;
  var elseBranch:Stmt;
}

typedef SwitchCase = {
  condition:Expr,
  body:Array<Stmt>,
  isDefault:Bool
};

class Switch implements Stmt {
  var target:Expr;
  var cases:Array<SwitchCase>;
}

class Block implements Stmt {
  var statements:Array<Stmt>;
}

typedef FunctionArg = {
  name:Token,
  type:Null<Expr.Type>,
  expr:Null<Expr>,
  ?isInit:Bool
};

class Function implements Stmt {
  var name:Token;
  var params:Array<FunctionArg>;
  var body:Stmt;
  var ret:Expr.Type;
  var attribute:Array<Expr>;
}

class Return implements Stmt {
  var keyword:Token;
  var value:Expr;
}

enum FieldKind {
  FUse(type:Expr.Type); // Note: will need to add all the complex trait stuff at some point.
  FVar(v:Var, type:Expr.Type);
  FProp(getter:Null<Function>, setter:Null<Function>, type:Expr.Type);
  FFun(fun:Function);
}

enum FieldAccess {
  AStatic;
  APublic;
  APrivate;
  AAbstract;
  AConst;
}

class Field implements Stmt {
  var name:Token;
  var kind:FieldKind;
  var access:Array<FieldAccess>;
  var attribute:Array<Expr>;
}

enum ClassKind {
  KindClass;
  KindInterface;
  KindTrait;
}

class Class implements Stmt {
  var name:Token;
  var kind:ClassKind;
  var superclass:Expr.Type;
  var interfaces:Array<Expr.Type>;
  var fields:Array<Field>;
  var attribute:Array<Expr>;
}
