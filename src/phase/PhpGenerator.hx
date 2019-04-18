package phase;

using Type;
using StringTools;

enum GeneratorMode {
  GeneratingRoot;
  GeneratingClass;
  GeneratingInterface;
  GeneratingTrait;
  GeneratingClosure;
  GeneratingFunction;
}

enum GeneratorAnnotationStrategy {
  AnnotatePhase;
  AnnotateDocblock;
}

typedef PhpGeneratorOptions = {
  ?annotation: GeneratorAnnotationStrategy
};

class PhpGenerator 
  implements StmtVisitor<String>
  implements ExprVisitor<String>
{

  static final typeAliases = [
    'String' => 'string',
    'Int' => 'integer',
    'Array' => 'array',
    'Callable' => 'callable',
    'Scalar' => 'scalar'
  ];

  final options:PhpGeneratorOptions;
  final stmts:Array<Stmt>;
  final reporter:ErrorReporter;
  var mode:GeneratorMode = GeneratingRoot;
  var closureCaptures:Array<String> = [];
  var scope:PhpScope;
  var localDepth:Int;
  var indentLevel:Int = 0;
  var uid:Int = 0;
  var append:Array<String> = [];
  var exprKind:Map<Expr, PhpKind> = [];

  public function new(
    stmts:Array<Stmt>,
    reporter:ErrorReporter,
    ?options:PhpGeneratorOptions
  ) {
    this.options = options == null  ? {} : options;
    this.stmts = stmts;
    this.reporter = reporter;

    if (this.options.annotation == null) {
      this.options.annotation = AnnotateDocblock;
    }
  }

  public function generate():String {
    append = [];
    exprKind = [];
    scope = new PhpScope();

    var out:Array<String> = [];
    for (stmt in stmts) {
      var s = generateStmt(stmt);
      if (s != null && s != '') out.push(s);
    }

    return '<?php\n' + out.concat(this.append).join('\n');
  }
  
  function generateStmt(stmt:Null<Stmt>):String {
    if (stmt == null) return '';
    return stmt.accept(this);
  }

  function generateExpr(expr:Null<Expr>):String {
    if (expr == null) return '';
    return expr.accept(this);
  }
  
  public function visitPackageStmt(stmt:Stmt.Package):String {
    var out = 'namespace ' + stmt.path.map(t -> t.lexeme).join('\\') + ' {\n\n';
    scope.push();
    indent();
    out += stmt.decls.map(generateStmt).join('\n');
    scope.pop();
    if (append.length > 0) {
      out += '\n\n' + append.map(a -> getIndent() + a).join('\n');
      append = [];
    }
    outdent();
    return out + '\n\n' + getIndent() + '}';
  }

  public function visitBlockStmt(stmt:Stmt.Block):String {
    var out = getIndent() + '{\n';
    
    scope.push();
    
    indent(); 
    out += stmt.statements.map(generateStmt).join('\n') + '\n';
    outdent();
    
    scope.pop();

    out += getIndent() + '}';
    return out;
  }

  public function visitExpressionStmt(stmt:Stmt.Expression):String {
    return getIndent() + generateExpr(stmt.expression) + ';';
  }

  public function visitIfStmt(stmt:Stmt.If):String {
    var out = getIndent() + 'if (' + generateExpr(stmt.condition) + ')\n' + generateStmt(stmt.thenBranch);
    if (stmt.elseBranch != null) {
      out += '\n' + getIndent() + 'else\n' + generateStmt(stmt.elseBranch);
    }
    return out;
  }

  public function visitReturnStmt(stmt:Stmt.Return):String {
    return getIndent() + (stmt.value == null
      ? 'return;'
      : 'return ' + generateExpr(stmt.value) + ';');
  }

  public function visitVarStmt(stmt:Stmt.Var):String {
    scope.define(stmt.name.lexeme, PhpVar);
    return getIndent() + '$' + safeVar(stmt.name) + ' = '
      + (stmt.initializer != null ? generateExpr(stmt.initializer) : 'null')
      + ';';
  }

  public function visitUseStmt(stmt:Stmt.Use):String {
    // todo: handle annotations
    var path = stmt.path.map(t -> t.lexeme).join('\\');
    if (stmt.absolute) path = '\\' + path;

  

    return switch stmt.kind {
      case UseNormal:
        var name = stmt.path[stmt.path.length - 1].lexeme;
        scope.define(name, PhpType);
        getIndent() + 'use $path;';
      case UseAlias(target): switch target {
        case TargetFunction(alias):
          scope.define(alias.lexeme, PhpFun);
          getIndent() + 'use function $path as ${alias.lexeme};';
        case TargetType(alias):
          scope.define(alias.lexeme, PhpType);
          getIndent() + 'use $path as ${alias.lexeme};';
      }
      case UseSub(items): items.map(target -> switch target {
        case TargetFunction(f):
          scope.define(f.lexeme, PhpFun);
          getIndent() + 'use function $path\\${f.lexeme};';
        case TargetType(f):
          scope.define(f.lexeme, PhpType);
          getIndent() + 'use $path\\${f.lexeme};';
      }).join('\n');
    }
  }

  public function visitClassStmt(stmt:Stmt.Class):String {
    var out = '';
    
    if (stmt.annotation.length > 0) {
      out += generateAnnotations({ cls: stmt }, stmt.annotation);
    }

    var keyword = switch stmt.kind {
      case KindClass: 'class';
      case KindInterface: 'interface';
      case KindTrait: 'trait';
    }

    out += '\n' + getIndent() + keyword + ' ' + stmt.name.lexeme;
    scope.define(stmt.name.lexeme, PhpType);
    
    if (stmt.superclass != null) {
      out += ' extends ' + stmt.superclass.lexeme;
    }

    if (stmt.interfaces.length > 0) {
      out += switch stmt.kind {
        case KindClass:' implements ';
        case KindInterface: ' extends ';
        case KindTrait: ''; // should not be reachable
      }
      out += stmt.interfaces.map(t -> t.lexeme).join(', ');
    }

    out += '\n' + getIndent() + '{\n';
    indent();
    
    scope.push();
    var prevMode = mode;
    mode = switch stmt.kind { 
      case KindClass: GeneratingClass;
      case KindInterface: GeneratingInterface;
      case KindTrait: GeneratingTrait;
    }

    for (field in stmt.fields) {
      if (field.annotation.length > 0) {
        out += generateAnnotations({
          cls: stmt,
          field: field.name.lexeme
        }, field.annotation);
      }
      out += generateStmt(field) + '\n';
    }

    mode = prevMode;
    scope.pop();

    outdent();
    return out + '\n' + getIndent() + '}';
  }
  
  public function visitFieldStmt(stmt:Stmt.Field):String {
    var isConst:Bool = false;
    return '\n' + getIndent() + stmt.access.map(a -> switch a {
      case AStatic: 'static';
      case APublic: 'public';
      case APrivate: 'protected';
      case AConst: 
        isConst = true;
        'const';
      case AAbstract: mode == GeneratingInterface ? null : 'abstract';
    }).filter(f -> f != null).join(' ') + switch stmt.kind {
      case FVar(v, _):
        var out = isConst 
          ? ' ' + safeVar(stmt.name)
          : ' $' + safeVar(stmt.name);
        if (v.initializer != null) {
          out += ' = ' + generateExpr(v.initializer);
        }
        out + ';';
      case FUse(type):
        'use ' + generateTypePath(type) + ';';
      case FFun(fun):
        var name = stmt.name.lexeme == 'new'
          ? '__construct'
          : safeVar(stmt.name);
        
        scope.push();

        var out = ' function ' + name + '(' + functionParams(fun.params) + ')';

        if (stmt.access.indexOf(AAbstract) < 0) {
          var body:Stmt.Block = cast fun.body; 
          
          // Handle initializers
          for (p in fun.params) {
            if (p.isInit == true) {
              var init = new Stmt.Expression(
                new Expr.Set(new Expr.This(p.name), p.name, new Expr.Variable(p.name))
              );
              body.statements.unshift(init);
            }
          }

          out += '\n' + generateStmt(body);
        } else {
          out += ';';
        }

        scope.pop();

        out;
    }
  }

  public function visitFunctionStmt(stmt:Stmt.Function):String {
    scope.define(safeVar(stmt.name), PhpFun);

    scope.push();
    var prevMode = mode;
    mode = GeneratingFunction;

    var out = '\n' + getIndent() + 'function ' + safeVar(stmt.name) + '(' + functionParams(stmt.params) + ')\n';
    out += generateStmt(stmt.body);
    
    mode = prevMode;
    scope.pop();
    return out;
  }

  function functionParams(params:Array<Stmt.FunctionArg>) {
    return params.map(param -> {
      var name = safeVar(param.name);
      scope.define(name, PhpVar);
      var out = if (param.type != null) {
        generateTypePath(param.type) + ' $' + name;
      } else {
        '$' + name;
      }
      if (param.expr != null) {
        out += ' = ' + generateExpr(param.expr);
      }
      return out;
    }).join(', ');
  }
  
  public function visitThrowStmt(stmt:Stmt.Throw):String {
    return getIndent() + 'throw ' + generateExpr(stmt.expr) + ';';
  }

  public function visitTryStmt(stmt:Stmt.Try):String {
    var out = getIndent() + 'try\n';
    out += generateStmt(stmt.body);
    for (c in stmt.catches) {
      scope.push();
      var name = safeVar(c.name);
      scope.define(name, PhpVar);
      out += '\n' + getIndent() + 'catch (';
      if (c.type != null) {
        out += generateTypePath(c.type) + ' $' + name + ')\n';
      } else {
        out += '$' + name + ')\n';
      }
      out += generateStmt(c.body);
      scope.pop();
    }
    return out;
  }

  public function visitWhileStmt(stmt:Stmt.While):String {
    return getIndent() + 'while (' + generateExpr(stmt.condition) + ')\n' 
      + generateStmt(stmt.body);
  }

  public function visitForStmt(stmt:Stmt.For):String {
    return switch stmt.target.getClass() {
      case Expr.Range:
        scope.push();

        var range:Expr.Range = cast stmt.target;
        var key = safeVar(stmt.key);
        var init = generateExpr(range.from);
        var limit = generateExpr(range.to);

        scope.define(key, PhpVar);
        var out = getIndent() + 'for ($' + key + ' = ' + init + ';'
          + ' $' + key + ' < ' + limit + '; $' + key + '++)\n'
          + generateStmt(stmt.body);

        scope.pop();
        out;
      default:
        scope.push();

        var key = safeVar(stmt.key);
        var out = getIndent() + 'foreach (' + generateExpr(stmt.target) 
          + ' as $' + key;
        scope.define(key, PhpVar);
        if (stmt.value != null) {
          var value = safeVar(stmt.value);
          scope.define(value, PhpVar);
          out += ' => $' + value;
        }
        out += ')\n' + generateStmt(stmt.body);

        scope.pop();
        out;
    }
  }

  public function visitSwitchStmt(stmt:Stmt.Switch):String {
    var out = getIndent() + 'switch (' + generateExpr(stmt.target) + ')\n' + getIndent() + '{\n';
    indent();
    for (c in stmt.cases) {
      if (c.isDefault) {
        out += getIndent() + 'default:\n';
      } else {
        out += getIndent() + 'case ' + generateExpr(c.condition) + ':\n';
      }
      indent();
      out += c.body.map(generateStmt).join('') + '\n';
      out += getIndent() + 'break;\n';
      outdent();
    }
    outdent();
    out += getIndent() + '}';
    return out;
  }

  public function visitAnnotationExpr(expr:Expr.Annotation):String {
    var name = expr.path.map(t -> t.lexeme).join('\\');
    return switch options.annotation {
      case AnnotateDocblock: 
        '@' + name + '('  + expr.params.map(param -> switch param.getClass() {
          case Expr.Assign:
            var assign:Expr.Assign = cast param;
            '${assign.name.lexeme} = ${generateExpr(assign.value)}';
          default: generateExpr(param);
        }).join(', ') + ')';
      case AnnotatePhase:
        'new ' + name + '([' + expr.params.map(param -> switch param.getClass() {
          case Expr.Assign:
            var assign:Expr.Assign = cast param;
            '"${assign.name.lexeme}" => ${generateExpr(assign.value)}';
          default: generateExpr(param);
        }).join(', ') + '])';
    }
  }

  public function visitArrayLiteralExpr(expr:Expr.ArrayLiteral):String {
    return '[' + generateList(expr.values) + ']';
  }

  public function visitAssocArrayLiteralExpr(expr:Expr.AssocArrayLiteral):String {
    return '[' + [ for (i in 0...expr.keys.length) {
      var key = expr.keys[i];
      var value = expr.values[i];
      '${generateExpr(key)} => ${generateExpr(value)}';
    } ].join(', ') + ']';
  }

  // public function visitObjectLiteralExpr(expr:Expr.ObjectLiteral):String {
  //   return '(object) [' + [ for (i in 0...expr.keys.length) {
  //     var key = expr.keys[i];
  //     var value = expr.values[i];
  //     '"$key" => ${generateExpr(value)}';
  //   } ].join(', ') + ']';
  // }

  public function visitAssignExpr(expr:Expr.Assign):String {
    var name = safeVar(expr.name);
    var kind:PhpKind = scope.get(name);
    if (kind != PhpVar) throw error(expr.name, 'Invalid assignment');
    return '$' + name + ' = ${generateExpr(expr.value)}';
  }

  public function visitIsExpr(expr:Expr.Is):String {
    return generateExpr(expr.left) + ' instanceof ' + generateExpr(expr.type);
  }

  public function visitBinaryExpr(expr:Expr.Binary):String {
    var op = switch expr.op.type {
      case TokConcat: '.';
      default: expr.op.lexeme;
    }
    return generateExpr(expr.left) + ' ' + op + ' ' + generateExpr(expr.right);
  }

  public function visitCallExpr(expr:Expr.Call):String {
    var callee = switch expr.callee.getClass() {
      case Expr.Get:
        var getter:Expr.Get = cast expr.callee;
        switch getter.object.getClass() {
          case Expr.Type | Expr.Static:
            // Don't add the `$` to the function name.
            generateExpr(getter.object) + '::' + safeVar(getter.name);
          default: generateExpr(getter.object) + '->' + safeVar(getter.name);
        }
      case Expr.Type:
        // Initializer
        'new ' + generateExpr(expr.callee);
      default: generateExpr(expr.callee);
    }
    return '$callee(' + expr.args.map(generateExpr).join(', ') + ')';
  }

  public function visitGetExpr(expr:Expr.Get):String {
    return switch expr.object.getClass() {
      case Expr.Type | Expr.Static:
        generateExpr(expr.object) + '::' + switch expr.name.type {
          case TokTypeIdentifier: safeVar(expr.name);
          default: '$' + safeVar(expr.name);
        } 
      default: 
        generateExpr(expr.object) + '->' + safeVar(expr.name);
    }
  }

  public function visitSetExpr(expr:Expr.Set):String {
    var left = switch expr.object.getClass() {
      case Expr.Type | Expr.Static: 
        generateExpr(expr.object) + '::' + switch expr.name.type {
          case TokTypeIdentifier: safeVar(expr.name);
          default: '$' + safeVar(expr.name);
        } 
      default: 
        generateExpr(expr.object) + '->' + safeVar(expr.name);
    }
    return left + ' = ' + generateExpr(expr.value);
  }

  public function visitSubscriptGetExpr(expr:Expr.SubscriptGet):String {
    if (expr.index == null) {
      return generateExpr(expr.object) + '[]';
    }
    return generateExpr(expr.object) + '[' + generateExpr(expr.index) + ']';
  }

  public function visitSubscriptSetExpr(expr:Expr.SubscriptSet):String {
    var left = if (expr.index == null) {
      generateExpr(expr.object) + '[]';
    } else { 
      generateExpr(expr.object) + '[' + generateExpr(expr.index) + ']';
    }
    return left + ' = ' + generateExpr(expr.value);
  }

  public function visitGroupingExpr(expr:Expr.Grouping):String {
    return '(${generateExpr(expr.expression)})';
  }

  // Todo: need to allow for reference vars
  public function visitLambdaExpr(expr:Expr.Lambda):String {
    var func:Stmt.Function = cast expr.func;

    var prevCaptures = closureCaptures;
    var prevMode = mode;
    var prevDepth = localDepth;

    closureCaptures = closureCaptures.copy();
    mode = GeneratingClosure;

    scope.push();
    localDepth = scope.getTop().depth;

    var out = 'function (' + functionParams(func.params) + ')'; 
    var body = generateStmt(func.body);

    if (closureCaptures.length > 0) {
      var uniq:Array<String> = [];
      for (name in closureCaptures) {
        if (uniq.indexOf(name) < 0) uniq.push(name);
      }
      out += ' use (' + uniq.map(n -> '$' + n).join(', ') + ')';
    }
    out += '\n' + body;

    localDepth = prevDepth;
    scope.pop();

    mode = prevMode;
    switch prevMode {
      case GeneratingClosure:
        closureCaptures = prevCaptures
          .concat(closureCaptures.filter(name -> !isLocal(name)));
      default:
        closureCaptures = [];
    }

    return out;
  }

  public function visitLiteralExpr(expr:Expr.Literal):String {
    return if (Std.is(expr.value, Int)) 
      expr.value;
    else if (Std.is(expr.value, Bool))
      expr.value;
    else if (expr.value == null)
      return 'null';
    else {
      var value:String = expr.value;
      '"' + value.replace('"', '\\"') + '"'; // todo: escape strings
    }
  }

  public function visitLogicalExpr(expr:Expr.Logical):String {
    return generateExpr(expr.left) 
      + ' ' + expr.op.lexeme
      + ' ' + generateExpr(expr.right);
  }
  
  public function visitSuperExpr(expr:Expr.Super):String {
    var method = expr.method.lexeme;
    if (method == 'new') method = '__construct';
    return 'parent::' + method;
  }

  public function visitThisExpr(expr:Expr.This):String {
    return "$this";
  }

  public function visitStaticExpr(expr:Expr.Static):String {
    return "static";
  }

  public function visitTypeExpr(expr:Expr.Type):String {
    return generateTypePath(expr);
  }

  public function visitUnaryExpr(expr:Expr.Unary):String {
    return expr.right 
      ? expr.op.lexeme + generateExpr(expr.expr)
      : generateExpr(expr.expr) + expr.op.lexeme;
  }

  // Not convinced this is the best way to do this
  public function visitRangeExpr(expr:Expr.Range):String {
    return 'range(' +  generateExpr(expr.from) + ',' 
      + generateExpr(expr.to) + ')';
  }

  public function visitVariableExpr(expr:Expr.Variable):String {
    var name = safeVar(expr.name);
    var kind:PhpKind = scope.get(name);

    if (kind == null) {
      kind = PhpFun;
    } else if (mode == GeneratingClosure) {
      if (!isLocal(name)) {
        closureCaptures.push(name);
      }
    }

    return switch kind {
      case PhpVar: '$' + name;
      default: name;
    }
  }

  function generateTypePath(t:Expr.Type):String {
    var type = t.path.map(t -> t.lexeme).join('\\');
    if (typeAliases.exists(type)) {
      return typeAliases.get(type);
    }
    if (t.absolute) {
      type = '\\' + type;
    }
    return type;
  }

  function generateAnnotations(target:{
    cls:Stmt.Class,
    ?field:String
  }, annotations:Array<Expr>):String {
    var clsName = target.cls.name.lexeme;
    return switch options.annotation {
      case AnnotateDocblock:
        var out = '\n' + getIndent() + '/**';
        for (a in annotations) {
          out += '\n' + getIndent() + ' * ' + generateExpr(a);
        }
        out += '\n' + getIndent() + ' */';
      case AnnotatePhase:
        var kind = target.field == null ? '__CLASS__' : target.field;
        var reg = '\\Phase\\Boot::registerAnnotation($clsName::class, "$kind", '
          + visitArrayLiteralExpr(new Expr.ArrayLiteral(
            target.cls.name,
            annotations
          )) + ');';
        append.push(reg);
        '';
    }
  }

  function generateList(items:Array<Expr>):String {
    return items.map(generateExpr).join(', ');
  }

  function safeVar(tok:Token) {
    var name = tok.lexeme;
    // if (reserved.indexOf(name) >= 0) {
    //   return '_' + name;
    // }
    return name;
  }

  function isLocal(name:String) {
    if (localDepth != null) {
      var locals = scope.getAt(localDepth);
      if (locals == null) return false;
      return locals.get(name) != null;
    }
    return scope.getTop().get(name) != null;
  }

  function tempVar(prefix:String) {
    uid++;
    return '__${prefix}_${uid}';
  }

  function getIndent() {
    var out = '';
    for (i in 0...this.indentLevel) {
      out += '  ';
    }
    return out;
  }

  function indent() {
    indentLevel++;
    return this;
  }

  function outdent() {
    indentLevel--;
    if (indentLevel < 0) {
      indentLevel = 0;
    }
    return this;
  }

  function error(token:Token, message:String) {
    reporter.report(token.pos, token.lexeme, message);
    return new GeneratorError();
  }

}

class GeneratorError {

  // todo

  public function new() {}

}