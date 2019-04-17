package phase;

@:autoBuild(phase.tools.AstBuilder.buildNode())
interface Expr {
  public function accept<T>(visitor:ExprVisitor<T>):T;
}

class Annotation implements Expr {
  var path:Array<Token>;
  var params:Array<Expr>;
  var relative:Bool;
  var expr:Expr;
}

class Assign implements Expr {
  var name:Token;
  var value:Expr;
}

class Binary implements Expr {
  var left:Expr;
  var op:Token;
  var right:Expr;
}

class Unary implements Expr {
  var op:Token;
  var expr:Expr;
  var right:Bool;
}

class Is implements Expr {
  var left:Expr;
  var type:Expr.Type;
}

class Logical implements Expr {
  var left:Expr;
  var op:Token;
  var right:Expr;
}

class Range implements Expr {
  var from:Expr;
  var to:Expr;
}

class Call implements Expr {
  var callee:Expr;
  var paren:Token;
  var args:Array<Expr>;
}
class Get implements Expr {
  var object:Expr;
  var name:Token;
}

class Set implements Expr {
  var object:Expr;
  var name:Token;
  var value:Expr;
}

class SubscriptGet implements Expr {
  var end:Token;
  var object:Expr;
  var index:Expr;
}

class SubscriptSet implements Expr {
  var end:Token;
  var object:Expr;
  var index:Expr;
  var value:Expr;
}

class Super implements Expr {
  var keyword:Token;
  var method:Token;
}

class This implements Expr {
  var keyword:Token;
}

class Static implements Expr {
  var keyword:Token;
}

class Grouping implements Expr {
  var expression:Expr;
}

class Literal implements Expr {
  var value:Dynamic;
}

class ArrayLiteral implements Expr {
  var end:Token;
  var values:Array<Expr>;
}

class AssocArrayLiteral implements Expr {
  var end:Token;
  var keys:Array<Expr>;
  var values:Array<Expr>;
}

class Lambda implements Expr {
  var func:Stmt;
}

class Type implements Expr {
  var path:Array<Token>;
  var absolute:Bool;
}

class Variable implements Expr {
  var name:Token;
}
