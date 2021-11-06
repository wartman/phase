package phase.analysis;

import phase.analysis.Type;
import Type as HxType;

using Lambda;
using phase.analysis.TypeTools;

class StaticAnalyzer 
  implements ExprVisitor<Void> 
  implements StmtVisitor<Void>
{
  // @todo: This should NOT be hard-coded like this 
  // public static final stringType:ClassType = {
  //   namespace: [],
  //   superclass: null,
  //   interfaces: [],
  //   name: 'String',
  //   fields: [
  //     { 
  //       name: 'toLowerCase', 
  //       kind: TMethod({
  //         name: 'toLowerCase',
  //         args: [],
  //         ret: TPath({ namespace: [], name: 'String' }) 
  //       })
  //     },
  //     { 
  //       name: 'toUpperCase', 
  //       kind: TMethod({
  //         name: 'toUpperCase',
  //         args: [],
  //         ret: TPath({ namespace: [], name: 'String' }) 
  //       })
  //     },
  //     {
  //       name: 'split',
  //       kind: TMethod({
  //         name: 'split',
  //         args: [
  //           { name: 'sep', type: TPath({ namespace: [], name: 'String' }) }
  //         ],
  //         ret: TPath({ namespace: [], name: 'Array' })
  //       })
  //     }
  //   ]
  // };

  final stmts:Array<Stmt>;
  final reporter:ErrorReporter;
  final server:Server;
  var scope:Scope = null;
  var typedExprs:Map<Expr, Type> = [];
  var typedDecls:Map<String, Type> = [];
  var imports:Map<String, Type> = [];

  public function new(stmts, server, reporter) {
    this.stmts = stmts;
    this.server = server;
    this.reporter = reporter;
  }

  public function analyzeSurface() {
    analyze();
    return typedDecls;
  }

  // function analyzeSurfaceDecl(decl:Stmt, ns:Array<String>) {
  //   switch HxType.getClass(decl) {
  //     case Stmt.Namespace:
  //       var namespace:Stmt.Namespace = cast decl;
  //       var ns = namespace.path.map(s -> s.lexeme);
  //       for (decl in namespace.decls) analyzeSurfaceDecl(decl, ns);
  //     case Stmt.Function:
  //       var fn:Stmt.Function = cast decl;
  //       var fun = TFun({
  //         name: fn.name.lexeme,
  //         args: [],
  //         ret: typeFromTypeExpr(fn.ret)
  //       });
  //       typedDecls.set(ns.concat([ fn.name.lexeme ]).join('::'), fun);
  //     case Stmt.Class:
  //       var cls:Stmt.Class = cast decl;
  //       var type = TClass({
  //         namespace: ns,
  //         superclass: cls.superclass == null 
  //           ? null
  //           : typeFromTypeExpr(cls.superclass),
  //         interfaces: cls.interfaces.map(typeFromTypeExpr),
  //         name: cls.name.lexeme,
  //         fields: extractClassFields(cls)
  //       });
  //       typedDecls.set(ns.concat([ cls.name.lexeme ]).join('::'), type);
  //     default:
  //   }
  // }

  public function analyze() {
    scope = new Scope();
    imports = new Map();
    typedExprs = new Map();
    typedDecls = new Map();

    for (stmt in stmts) stmt.accept(this);

    return new Context(typedExprs, typedDecls);
  }

  public function visitNamespaceStmt(stmt:Stmt.Namespace):Void {
    wrapScope(() -> {
      var ns = stmt.path.map(s -> s.lexeme);

      // Get class and funciton declarations first. This allows declarations
      // to be hoisted, at least for the top level (and we'll need to work
      // on that).
      for (decl in stmt.decls) {
        switch HxType.getClass(decl) {
          case Stmt.Var:
            decl.accept(this);
          case Stmt.Function:
            var fn:Stmt.Function = cast decl;
            var fun = TFun({
              name: fn.name.lexeme,
              args: [],
              ret: typeFromTypeExpr(fn.ret)
            });
            scope.declare(fn.name.lexeme, fun);
            typedDecls.set(ns.concat([ fn.name.lexeme ]).join('::'), fun);
            typedDecls.set(fn.name.lexeme, fun);
          case Stmt.Class:
            var cls:Stmt.Class = cast decl;
            
            if (cls.superclass != null) cls.superclass.accept(this);
            for (i in cls.interfaces) i.accept(this);
            
            var type = TClass({
              namespace: ns,
              superclass: cls.superclass == null 
                ? null
                : typedExprs.get(cls.superclass),
              interfaces: cls.interfaces.map(i -> typedExprs.get(i)),
              name: cls.name.lexeme,
              fields: extractClassFields(cls)
            });

            scope.declare(cls.name.lexeme, type);
            typedDecls.set(ns.concat([ cls.name.lexeme ]).join('::'), type);
            typedDecls.set(cls.name.lexeme, type);
          default:
        }
      }

      for (stmt in stmt.decls) {
        stmt.accept(this);
      }
    });
  }

  public function visitBlockStmt(stmt:Stmt.Block):Void {
    wrapScope(() -> for (stmt in stmt.statements) {
      stmt.accept(this);
    });
  }

  public function visitExpressionStmt(stmt:Stmt.Expression) {
    stmt.expression.accept(this);
  }

  public function visitIfStmt(stmt:Stmt.If) {
    wrapScope(() -> {
      stmt.condition.accept(this);
      wrapScope(() -> stmt.thenBranch.accept(this));
      wrapScope(() -> if (stmt.elseBranch != null) stmt.elseBranch.accept(this));
    });
  }

  public function visitReturnStmt(stmt:Stmt.Return):Void {
    if (stmt.value != null) stmt.value.accept(this);
  }

  public function visitVarStmt(stmt:Stmt.Var) {
    var type:Type = null;
    if (stmt.type != null) {
      type = switch typeFromTypeExpr(stmt.type) {
        case TClass(cls): TInstance(cls);
        case other: other;
      }
    }
    if (stmt.initializer != null) {
      stmt.initializer.accept(this);
    }
    if (type == null) {
      type = if (stmt.initializer != null) 
        resolveType(stmt.initializer)
      else TUnknown;
      if (type == null) type = TUnknown;
    }
    scope.declare(stmt.name.lexeme, type);
  }

  public function visitGlobalStmt(stmt:Stmt.Global) {
    scope.declare(stmt.name.lexeme, TUnknown);
  }

  public function visitUseStmt(stmt:Stmt.Use) {
    var namespace = stmt.path.copy().map(t -> t.lexeme);
    switch stmt.kind {
      case UseNormal:
        var name = namespace.pop();
        imports.set(name, TPath({ namespace: namespace, name: name }));
        scope.declare(name, TPath({ namespace: [], name: name }));
      case UseAlias(target): switch target {
        case TargetFunction(name):
          imports.set(name.lexeme, TFun({ name: name.lexeme, args: [], ret: TUnknown }));
        case TargetType(alias):
          var name = namespace.pop();
          imports.set(alias.lexeme, TPath({ namespace: namespace, name: name }));
          scope.declare(alias.lexeme, TPath({ namespace: [], name: alias.lexeme }));
      } 
      case UseSub(items): for (item in items) switch item {
        case TargetFunction(name):
          imports.set(name.lexeme, TFun({ name: name.lexeme, args: [], ret: TUnknown }));
        case TargetType(name):
          imports.set(name.lexeme, TPath({ namespace: namespace, name: name.lexeme }));
          scope.declare(name.lexeme, TPath({ namespace: [], name: name.lexeme }));
      }
    }
  }

  public function visitClassStmt(stmt:Stmt.Class):Void {
    if (stmt.superclass != null) stmt.superclass.accept(this);
    for (i in stmt.interfaces) i.accept(this);

    var type = if (!typedDecls.exists(stmt.name.lexeme)) {
      var cls:ClassType = {
        namespace: [],
        name: stmt.name.lexeme,
        superclass: stmt.superclass != null 
          ? typedExprs.get(stmt.superclass)
          : null,
        interfaces: stmt.interfaces.map(i -> typedExprs.get(i)),
        fields: extractClassFields(stmt)
      };
      TClass(cls);
    } else {
      typedDecls.get(stmt.name.lexeme);
    }

    switch type {
      case TClass(cls):  
        wrapScope(() -> {
          scope.declare('this', TInstance(cls));
          scope.declare('static', TClass(cls));
          for (field in stmt.fields) {
            field.accept(this);
          }
        });
      default: throw "wtf";
    }
    
    scope.declare(stmt.name.lexeme, type);
    typedDecls.set(stmt.name.lexeme, type);
  }

  function extractClassFields(stmt:Stmt.Class):Array<Field> {
    return [ for (field in stmt.fields) {
      switch field.kind {
        case FUse(_): null;
        case FVar(_, type):  
          {
            name: field.name.lexeme,
            kind: TVar(typeFromTypeExpr(type))
          };
        case FProp(getter, setter, type):
          {
            name: field.name.lexeme,
            kind: TProp(
              typeFromTypeExpr(type),
              if (getter != null) getter.name.lexeme else null,
              if (setter != null) setter.name.lexeme else null
            )
          };
        case FFun(fun):
          {
            name: field.name.lexeme,
            kind: TMethod({
              name: fun.name.lexeme,
              args: fun.params.map(arg -> {
                name: arg.name.lexeme,
                type: typeFromTypeExpr(arg.type)
              }),
              ret: typeFromTypeExpr(fun.ret)
            })
          };
      }
    } ].filter(f -> f != null); 
  }

  public function visitFieldStmt(stmt:Stmt.Field):Void {
    switch stmt.kind {
      case FUse(_):
      case FVar(_, _):
      case FProp(getter, setter, _):
        if (getter != null) getter.accept(this);
        if (setter != null) setter.accept(this);
      case FFun(fun):
        fun.accept(this);
    }
  }

  public function visitFunctionStmt(stmt:Stmt.Function):Void {
    var fun:FunctionType = {
      name: stmt.name.lexeme,
      args: [],
      ret: typeFromTypeExpr(stmt.ret)
    };
    wrapScope(() -> {
      for (arg in stmt.params) {
        var type = typeFromTypeExpr(arg.type);
        scope.declare(arg.name.lexeme, type);
        fun.args.push({ name: arg.name.lexeme, type: type });
      }
      if (stmt.body != null) stmt.body.accept(this);
    });
    scope.declare(fun.name, TFun(fun));
  }

  public function visitThrowStmt(stmt:Stmt.Throw):Void {
    // noop
  }

  public function visitTryStmt(stmt:Stmt.Try) {
    wrapScope(() -> {
      stmt.body.accept(this);
      for (c in stmt.catches) wrapScope(() -> {
        scope.declare(c.name.lexeme, typeFromTypeExpr(c.type));
        c.body.accept(this);
      });
    });
  }

  public function visitWhileStmt(stmt:Stmt.While) {
    wrapScope(() -> {
      stmt.condition.accept(this);
      stmt.body.accept(this);
    });
  }

  public function visitForStmt(stmt:Stmt.For) {
    wrapScope(() -> {
      stmt.target.accept(this);
      // Todo: we have to find the types of the thing being iterated over.
      stmt.body.accept(this);
    });
  }

  public function visitSwitchStmt(stmt:Stmt.Switch):Void {
    wrapScope(() -> {
      stmt.target.accept(this);
      for (c in stmt.cases) wrapScope(() -> {
        if (c.condition != null) c.condition.accept(this);
        for (item in c.body) item.accept(this);
      });
    });
  }
  
  public function visitMatchExpr(expr:Expr.Match):Void {
    wrapScope(() -> {
      expr.target.accept(this);
      for (c in expr.cases) wrapScope(() -> {
        if (c.condition != null) c.condition.accept(this);
        for (item in c.body) item.accept(this);
      });
    });
  }

  public function visitAttributeExpr(expr:Expr.Attribute) {
    // noop?
  }

  public function visitArrayLiteralExpr(expr:Expr.ArrayLiteral) {
    if (expr.isNative) {
      setType(expr, TPhpScalar(TArray));
      return;
    }
    setType(expr, TPath({ namespace: [], name: 'Array' }));
  }

  public function visitMapLiteralExpr(expr:Expr.MapLiteral):Void {
    if (expr.isNative) {
      setType(expr, TPhpScalar(TArray));
      return;
    }
    setType(expr, TPath({ namespace: [], name: 'Map' }));
  }

  public function visitAssignExpr(expr:Expr.Assign):Void {
    if (!scope.isDeclared(expr.name.lexeme)) {
      throw error(expr.name, 'Invalid assignment');
    }
    setType(expr, scope.resolve(expr.name.lexeme));
  }

  public function visitIsExpr(expr:Expr.Is):Void {
    setType(expr, TPath({ namespace: [], name: 'Bool' }));
  }

  public function visitBinaryExpr(expr:Expr.Binary):Void {
    expr.left.accept(this);
    expr.right.accept(this);
    setType(expr, TPath({ namespace: [], name: 'Bool' }));
  }

  public function visitCallExpr(expr:Expr.Call):Void {
    expr.callee.accept(this);
    
    var calleeType = resolveType(expr.callee);
    if (calleeType == null) calleeType = TUnknown;

    for (arg in expr.args) switch arg {
      case Positional(expr):
        expr.accept(this);
      case Named(_, expr):
        expr.accept(this);
    }

    switch calleeType {
      case TUnknown | TPath(_):
        setType(expr, calleeType);
      case TFun(fun):
        setType(expr, fun.ret);
      case TClass(cls):
        // // Todo: we'll need to scan superclasses for this to work
        // var constructor = cls.fields.find(f -> f.name == 'new');
        // if (constructor == null) {
        //   throw error(expr.paren, 'The class ${cls.name} does not have a constructor');
        // }
        setType(expr, TInstance(cls));
      default:
        throw error(expr.paren, 'Not a callable');
    }
  }

  public function visitGetExpr(expr:Expr.Get):Void {
    expr.object.accept(this);

    var type = resolveType(expr.object);
    
    setType(expr, switch HxType.getClass(expr.name) {
      case Expr.Variable:
        var v:Expr.Variable = cast expr.name;
        var name = v.name.lexeme;
        if (name == 'class') {
          TPath({ namespace: [], name: 'String' });
          // TInstance(stringType);
        } else switch type {
          // Todo: split instance and class fields
          case TClass(cls) | TInstance(cls):
            var f = cls.fields.find(f -> f.name == name);
            if (f == null) {
              throw error(v.name, 'The class ${cls.name} does not have the field ${name}');
            }
            switch f.kind {
              case TVar(type): type;
              case TMethod(fun): TFun(fun);
              case TProp(type, _, _): type;
            }
          default:
            TUnknown;
        }
      default: 
        TUnknown;
    });
  }

  public function visitSetExpr(expr:Expr.Set):Void {
    setType(expr, TVoid);
  }

  public function visitSubscriptGetExpr(expr:Expr.SubscriptGet):Void {
    // todo
  }

  public function visitSubscriptSetExpr(expr:Expr.SubscriptSet):Void {
    // todo
  }

  public function visitGroupingExpr(expr:Expr.Grouping):Void {
    expr.expression.accept(this);
    setType(expr, resolveType(expr.expression));
  }

  public function visitLambdaExpr(expr:Expr.Lambda):Void {
    expr.func.accept(this);
  }

  public function visitNamespacedExpr(expr:Expr.Namespaced):Void {
    // todo
  }

  public function visitTernaryExpr(expr:Expr.Ternary):Void {
    expr.condition.accept(this);
    expr.thenBranch.accept(this);
    expr.elseBranch.accept(this);
    // todo: check that branches unify
    setType(expr, resolveType(expr.thenBranch));
  }

  public function visitLiteralExpr(expr:Expr.Literal):Void {
    if (Std.isOfType(expr.value, Int)) {
      setType(expr, server.locateType('Int'));
    } else if (Std.isOfType(expr.value, Bool)) {
      setType(expr, server.locateType('Bool'));
    } else {
      setType(expr, server.locateType('String'));
      // setType(expr, TInstance(stringType));
    }
  }

  public function visitLogicalExpr(expr:Expr.Logical) {
    expr.left.accept(this);
    expr.right.accept(this);
  }

  public function visitSuperExpr(expr:Expr.Super) {
    // todo
  }

  public function visitThisExpr(expr:Expr.This) {
    var type = scope.resolve('this');
    setType(expr, type);
  }

  public function visitStaticExpr(expr:Expr.Static) {
    var type = scope.resolve('static');
    setType(expr, type);
  }

  public function visitTypeExpr(expr:Expr.Type) {
    setType(expr, typeFromTypeExpr(expr));
  }

  public function visitUnaryExpr(expr:Expr.Unary) {
    expr.expr.accept(this);
  }

  public function visitRangeExpr(expr:Expr.Range) {
    setType(expr, TPath({ namespace: [], name: 'Int' }));
  }

  public function visitVariableExpr(expr:Expr.Variable) {
    var type = scope.resolve(expr.name.lexeme);
    setType(expr, type);
  }

  function typeFromTypeExpr(expr:Expr.Type):Type {
    if (expr == null) {
      return TUnknown;
    }

    var namespace = expr.path.copy().map(t -> t.lexeme);
    var name = namespace.pop();

    if (!expr.absolute) {
      if (namespace.length == 0) {
        if (imports.exists(name)) return imports.get(name);
        if (scope != null) switch scope.resolve(name) {
          case TUnknown:
          case other: return other;
        }
      }
    }

    return TPath({
      namespace: namespace,
      name: name
    });
  }

  function setType(expr:Expr, type:Type) {
    typedExprs.set(expr, switch type {
      case TPath(path):
        try {
          server.locateType(type.getTypeName());
        } catch (e) {
          TUnknown;
        }
      // case TPath({ namespace: [], name: 'String' }): 
      //   TInstance(stringType);
      default:
        type;
    });
  }

  function resolveType(expr:Expr) {
    var type = typedExprs.get(expr);
    if (type == null) return TUnknown;
    return switch type {
      case TPath(path):
        server.locateType(type.getTypeName());
      // case TPath({ namespace: [], name: 'String' }): 
      //   TInstance(stringType);
      default: 
        type;
    }
  }

  inline function wrapScope(cb:()->Void) {
    var prev = scope;
    scope = scope.pushChild();
    cb();
    scope = prev;
  }
  
  function error(token:Token, message:String) {
    reporter.report(token.pos, token.lexeme, message);
    return new TypeError();
  }
}

class TypeError {

  // todo

  public function new() {}

}