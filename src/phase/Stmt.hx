package phase;

@:autoBuild(phase.tools.AstBuilder.buildNode())
interface Stmt {
  public function accept<T>(visitor:StmtVisitor<T>):T;
}

class Expression implements Stmt {
  var expression:Expr;
}

enum UseKind {
  UseNormal;
  UseAlias(alias:Token);
  UseSub(items:Array<Token>);
}

class Use implements Stmt {
  var path:Array<Token>;
  var absolute:Bool;
  var kind:UseKind;
  var annotation:Array<Expr>;
}

class Package implements Stmt {
  var path:Array<Token>;
  var decls:Array<Stmt>;
  var annotation:Array<Expr>;
}

class Var implements Stmt {
  var name:Token;
  var initializer:Expr;
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
}

class For implements Stmt {
  var key:Token;
  var value:Null<Token>;
  var target:Expr;
  var body:Stmt;
}

class Block implements Stmt {
  var statements:Array<Stmt>;
}

class If implements Stmt {
  var condition:Expr;
  var thenBranch:Stmt;
  var elseBranch:Stmt;
}

typedef FunctionArg = {
  name:Token,
  type:Null<Expr.Type>,
  expr:Null<Expr>
};

class Function implements Stmt {
  var name:Token;
  var params:Array<FunctionArg>;
  var body:Stmt;
  var ret:Expr.Type;
  var annotation:Array<Expr>;
}

class Return implements Stmt {
  var keyword:Token;
  var value:Expr;
}

enum FieldKind {
  FUse(type:Expr.Type); // Note: will need to add all the complex trait stuff at some point.
  FVar(v:Var, type:Expr.Type);
  FFun(fun:Function);
}

enum FieldAccess {
  AStatic;
  APublic;
  APrivate;
  AAbstract;
}

class Field implements Stmt {
  var name:Token;
  var kind:FieldKind;
  var access:Array<FieldAccess>;
  var annotation:Array<Expr>;
}

enum ClassKind {
  KindClass;
  KindInterface;
  KindTrait;
}

class Class implements Stmt {
  var name:Token;
  var kind:ClassKind;
  var superclass:Token;
  var interfaces:Array<Token>;
  var fields:Array<Field>;
  var annotation:Array<Expr>;
}
