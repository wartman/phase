package phase;

import phase.Expr.CallArgument;
import phase.analysis.StaticAnalyzer;

using Type;
using StringTools;
using Lambda;
using phase.analysis.TypeTools;

enum GeneratorMode {
  GeneratingRoot;
  GeneratingClass;
  GeneratingInterface;
  GeneratingTrait;
  GeneratingClosure;
  GeneratingFunction;
}

enum abstract GeneratorAttributeStrategy(String) from String {
  var AnnotatePhase = 'phase';
  var AnnotateDocblock = 'docblock';
  var AnnotatePhpAttribute = 'attribute';
  var AnnotateOnClass = 'on-class';
}

enum abstract PhpVersion(String) from String {
  var Php8 = '8';
  var Php7 = '7';
}

typedef PhpGeneratorOptions = {
  ?attribute: GeneratorAttributeStrategy,
  ?version: PhpVersion
};

class PhpGenerator 
  implements StmtVisitor<String>
  implements ExprVisitor<String>
{

  static final typeAliases = [
    'String' => 'string',
    'Int' => 'int',
    // 'Array' => 'array',
    'Array' => '\\Std\\PhaseArray',
    'Map' => '\\Std\\PhaseMap',
    'Callable' => 'callable',
    'Any' => 'mixed',
    'Scalar' => 'scalar'
  ];

  final options:PhpGeneratorOptions;
  final stmts:Array<Stmt>;
  final reporter:ErrorReporter;
  final server:Server;
  var mode:GeneratorMode = GeneratingRoot;
  var closureCaptures:Array<String> = [];
  var classLocalInits:Map<Stmt.Field, Expr> = [];
  var classStaticInits:Map<Stmt.Field, Expr> = [];
  var scope:PhpScope;
  var localDepth:Int;
  var indentLevel:Int = 0;
  var uid:Int = 0;
  var append:Array<String> = [];
  var context:phase.analysis.Context = null;
  var isCall:Bool = false;

  public function new(
    stmts:Array<Stmt>,
    reporter:ErrorReporter,
    server:Server,
    ?options:PhpGeneratorOptions
  ) {
    this.options = options == null  ? {} : options;
    this.stmts = stmts;
    this.server = server;
    this.reporter = reporter;

    if (this.options.version == null) {
      this.options.version = Php8;
    }
    if (this.options.attribute == null) {
      this.options.attribute = switch this.options.version {
        case Php7: AnnotateOnClass;
        case Php8: AnnotatePhpAttribute;
      }
    }
  }

  public function generate():String {
    append = [];
    scope = new PhpScope(); // todo: get rid of this

    trace('   analyzing');
    var analysis = new StaticAnalyzer(stmts, server, reporter);
    context = analysis.analyze();
    trace('   done');

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
  
  public function visitNamespaceStmt(stmt:Stmt.Namespace):String {
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
    if (stmt.value != null) switch Std.downcast(stmt.value, Expr.Match) {
      case null:
      case match:
        // @todo: we need to make this more generic :P. It has to 
        //        work for vars and stuff too.
        for (item in match.cases) {
          var last = item.body[ item.body.length - 1 ];
          switch Std.downcast(last, Stmt.Expression) {
            case null:
              throw error(stmt.keyword, 'Matches must return an expression');
            case stmtExpr:
              item.body[ item.body.length - 1 ] = new Stmt.Return(
                stmt.keyword,
                stmtExpr.expression
              );
          }
        }
        return getIndent() + generateExpr(match);
    }
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

  public function visitGlobalStmt(stmt:Stmt.Global):String {
    scope.define(stmt.name.lexeme, PhpVar);
    return getIndent() + 'global $' + safeVar(stmt.name) + ';';
  }

  public function visitUseStmt(stmt:Stmt.Use):String {
    // todo: handle attributes
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
    var props:Array<String> = [];
    var body = '';
    
    if (stmt.attribute.length > 0) {
      out += generateAttributes({ cls: stmt }, stmt.attribute);
    }

    // @todo: we need to check our attributes for compiler-level stuff.

    var keyword = switch stmt.kind {
      case KindClass: 'class';
      case KindInterface: 'interface';
      case KindTrait: 'trait';
    }

    out += '\n' + getIndent() + keyword + ' ' + stmt.name.lexeme;
    scope.define(stmt.name.lexeme, PhpType);
    
    if (stmt.superclass != null) {
      out += ' extends ' + generateExpr(stmt.superclass);
    }

    if (stmt.interfaces.length > 0) {
      out += switch stmt.kind {
        case KindClass:' implements ';
        case KindInterface: ' extends ';
        case KindTrait: ''; // should not be reachable
      }
      out += stmt.interfaces.map(generateExpr).join(', ');
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

    classLocalInits = [];
    classStaticInits = [];

    var constructor = stmt.fields.find(stmt -> stmt.name.lexeme == 'new');

    for (field in stmt.fields) {
      if (field == constructor) {
        continue;
      }
      if (mode == GeneratingInterface) switch field.kind {
        case FVar(_, _) | FProp(_, _, _): continue;
        default:
      }
      if (field.attribute.length > 0) {
        body += generateAttributes({
          cls: stmt,
          field: field.name.lexeme
        }, field.attribute);
      }
      switch field.kind {
        case FProp(_, _, _):
          props.push(safeVar(field.name));
        default:
      }
      body += generateStmt(field) + '\n';
    }

    // todo: this will need to be tested.
    if (mode != GeneratingInterface) if (constructor == null && classLocalInits.count() > 0) {
      var name = new Token(TokIdentifier, 'new', '', stmt.name.pos);
      var args = [];
      var init:Stmt = if (stmt.superclass != null) {
        var type = context.typeOf(stmt.superclass);
        trace(type);
        var superConstructor = switch type {
          case TClass(cls) | TInstance(cls):
            cls.fields.find(f -> f.name == 'new');
          default: null;
        }
        args = if (superConstructor != null) switch superConstructor.kind {
          case TMethod(fun): fun.args;
          default: [];
        } else [];
        new Stmt.Expression(
          new Expr.Call(
            new Expr.Super(name, new Token(TokIdentifier, 'new', '', name.pos)),
            name,
            args.map(arg -> CallArgument.Positional(new Expr.Variable(
              new Token(TokIdentifier, arg.name, '', name.pos)
            )))
          )
        );
      } else null;
      constructor = new Stmt.Field(
        name,
        FFun(new Stmt.Function(
          name,
          args.map(arg -> {
            name: new Token(TokIdentifier, arg.name, '', name.pos),
            type: null,
            expr: null,
            isInit: false
          }),
          new Stmt.Block([ init ].filter(n -> n != null)),
          null,
          []
        )),
        [ APublic ],
        []
      );
    }

    if (mode != GeneratingInterface && constructor != null) if (classLocalInits.count() > 0) {
      var pre:Array<Stmt> = [];
      for (field => expr in classLocalInits) {
        var name = field.name;
        pre.push(new Stmt.Expression(
          new Expr.Set(
            new Expr.This(name),
            new Expr.Variable(name),
            expr
          )
        ));
      }
      var fun = switch constructor.kind {
        case FFun(fun): fun;
        default: throw 'assert';
      }
      constructor.kind = FFun(new Stmt.Function(
        fun.name,
        fun.params,
        switch Std.downcast(fun.body, Stmt.Block) {
          case null:
            new Stmt.Block(pre.concat([ fun.body ]));
          case block:
            for (stmt in pre) block.statements.unshift(stmt);
            block;
        },
        fun.ret,
        fun.attribute
      ));
    }

    if (constructor != null) body = generateStmt(constructor) + '\n' + body;

    out += body;

    if (props.length > 0) {
      // @todo: this is iffy -- make better
      out += '\n' + getIndent() + "public function __get($prop)";
      out += '\n' + getIndent() + "{";
      indent();
      out += '\n' + getIndent() + "return $this->{'__get_' . $prop}();";
      outdent();
      out += '\n' + getIndent() + "}";
      out += '\n';
      out += '\n' + getIndent() + "public function __set($prop, $value)";
      out += '\n' + getIndent() + "{";
      indent();
      out += '\n' + getIndent() + "$this->{'__set_' . $prop}($value);";
      outdent();
      out += '\n' + getIndent() + "}";
    }

    scope.pop();

    outdent();
    out += '\n' + getIndent() + '}';

    if (classStaticInits.count() > 0) {
      for (field => expr in classStaticInits) {
        var name = field.name;
        out += '\n' + generateStmt(new Stmt.Expression(
          new Expr.Set(
            new Expr.Type([ stmt.name ], false, false),
            new Expr.Variable(name),
            expr
          )
        ));
      }
    }
    
    mode = prevMode;
    return out;
  }
  
  public function visitFieldStmt(stmt:Stmt.Field):String {
    var isConst:Bool = false;
    var access = '\n' + getIndent() + stmt.access.map(a -> switch a {
      case AStatic: 'static';
      case APublic: 'public';
      case APrivate: 'protected';
      case AConst: 
        isConst = true;
        'const';
      case AAbstract: mode == GeneratingInterface ? null : 'abstract';
    }).filter(f -> f != null).join(' ');
    
    return switch stmt.kind {
      case FVar(v, t):
        var out = access + switch options.version {
          case Php8 if (t != null && !isConst): ' ' + generateTypePath(t);
          default: '';
        }
        out += isConst
          ? ' ' + safeVar(stmt.name)
          : ' $' + safeVar(stmt.name);
        if (v.initializer != null) {
          if (isConst) {
            out += ' = ' + generateExpr(v.initializer);
          } else if (stmt.access.contains(AStatic)) {
            classStaticInits.set(stmt, v.initializer);
          } else {
            classLocalInits.set(stmt, v.initializer);
          }
        }
        out + ';';
      case FProp(getter, setter, type):
        var name = safeVar(stmt.name);
        var ret = type != null ? ':' + generateTypePath(type) : '';
        var out = '';
        if (getter != null) {
          scope.push();
          out += access + ' function __get_$name()$ret \n' + generateStmt(getter.body);
          scope.pop();
        }
        if (setter != null) {
          scope.push();
          out += access + ' function __set_$name(' + functionParams(setter.params) + ') \n' + generateStmt(setter.body);
          scope.pop();
        }
        out;
      case FUse(type):
        'use ' + generateTypePath(type) + ';';
      case FFun(fun):
        var name = stmt.name.lexeme == 'new'
          ? '__construct'
          : safeVar(stmt.name);
        
        scope.push();

        var out = access + ' function ' + name + '(' + functionParams(fun.params) + ')';
        if (fun.ret != null) {
          out += ':' + generateTypePath(fun.ret);
        }

        if (stmt.access.indexOf(AAbstract) < 0) {
          var body:Stmt.Block = cast fun.body; 
          
          // Handle initializers
          for (p in fun.params) {
            if (p.isInit == true) {
              var init = new Stmt.Expression(
                new Expr.Set(
                  new Expr.This(p.name),
                  new Expr.Variable(p.name),
                  new Expr.Variable(p.name)
                )
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
    if (stmt.inverted) {
      return getIndent() + 'do\n' + generateStmt(stmt.body) 
        + '\n' + getIndent() + 'while (' + generateExpr(stmt.condition) + ');';
    }
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
      out += c.body.map(generateStmt).join('\n') + '\n';
      out += getIndent() + 'break;\n';
      outdent();
    }
    outdent();
    out += getIndent() + '}';
    return out;
  }

  public function visitMatchExpr(expr:Expr.Match):String {
    var type = typeOf(expr.target);

    switch type {
      case TInstance(cls) | TClass(cls) if (cls.superclass != null): // temp 
        var superclass = findType(cls.superclass);
        if (superclass == null) {
          return '/* NOT FOUND: ${cls.superclass.getTypeName()} */';
        }
        switch superclass {
          case TClass({ 
            name: 'PhaseEnum',
            namespace: [ 'Std' ],
            superclass: _,
            interfaces: _,
            fields: _
          }):
            return generateEnumMatch(expr, cls);
          default: 
            return '/* NOT A TCLASS: ${cls.superclass.getTypeName()} */';
        }
      default:
        if (type == null) return '/* NOT FOUND */';
        return '/* NOT A CLASS: ${type.getTypeName()} */';
    }
  
    return '/* NOT A CLASS: ${type.getTypeName()} */';
    // return '';
  }

  function generateEnumMatch(expr:Expr.Match, cls:phase.analysis.Type.ClassType):String {
    var temp = tempVar('matcher');
    var out = "$" + temp + ' = ' + generateExpr(expr.target) + ';\n';
    var body:Array<String> = [];
    var def:String = '';
    // var handled:Array<String> = [];
    // var hasDefault = false;

    scope.push();
    scope.define(temp, PhpVar);

    for (c in expr.cases) {
      if (c.isDefault) {
        def = '\n' + getIndent() + 'else {\n'; 
        indent();
        def += c.body.map(generateStmt).join('\n');
        outdent();
        def += '\n' + getIndent() + '}';
      } else switch Std.downcast(c.condition, Expr.Call) {
        case null:
        case matcher:
          var name = generateExpr(matcher.callee);
          if (cls.fields.exists(f -> f.name == name)) {
            var checks:Array<String> = [
              generateExpr(
                CodeBuilder.generateExpr('$temp.tag == "$name";', matcher.paren.pos, reporter)
              )
            ];
            var extracts:Array<String> = [];

            indent();

            for (arg in matcher.args) switch arg {
              case Positional(expr): switch Std.downcast(expr, Expr.Variable) {
                case null:
                  var i = matcher.args.indexOf(arg);
                  checks.push(
                    generateExpr(
                      CodeBuilder.generateExpr('$temp.params[$i];', matcher.paren.pos, reporter)
                    )
                    + ' === ' + generateExpr(expr)
                  );
                case variable:
                  var name = safeVar(variable.name);
                  var i = matcher.args.indexOf(arg);
                  extracts.push(
                    generateStmt(
                      CodeBuilder.generate('var $name = $temp.params[${i}];', matcher.paren.pos, reporter)[0]
                    )
                  );
              }
              case Named(name, expr):
                throw error(matcher.paren, 'Cant use named args yet');
            };
            var code = 'if (${ checks.join(' && ') }) { \n';
            
            code += extracts.length > 0 
              ? extracts.join('\n') + '\n'
              : '';
            code += c.body.map(generateStmt).join('\n');

            outdent();
            
            code += '\n' + getIndent() + '}';

            body.push(code);
          } else {
            throw error(matcher.paren, '$name is not a vaid constructor');
          }
      }
    }

    scope.pop();

    return out + getIndent() 
      + body.join('\n' + getIndent() + 'else ')
      + def;
  }

  public function visitAttributeExpr(expr:Expr.Attribute):String {
    var name = expr.path.map(t -> t.lexeme).join('\\');
    function generateArg(arg:Expr.CallArgument) return switch arg {
      case Positional(expr): generateExpr(expr);
      case Named(name, expr): '${name}: ${generateExpr(expr)}';
    }
    return switch options.attribute {
      case AnnotateDocblock: 
        '@' + name + '('  + expr.params.map(generateArg).join(', ') + ')';
      case AnnotatePhpAttribute: 
        name + '('  + expr.params.map(generateArg).join(', ') + ')';
      case AnnotatePhase | AnnotateOnClass:
        var tmp = tempVar('attribute');
        indent();
        var out = '';
        out = getIndent() + '$' + tmp + ' = new ' + name + '(' + expr.params.map(generateArg).join(', ') + ');\n' + out;
        out += getIndent() + 'return $' + tmp + ';\n';
        outdent();
        '(function () {\n' + out + getIndent() + '})()';
        // 'new ' + name + '([' + expr.params.map(param -> switch param.getClass() {
        //   case Expr.Assign:
        //     var assign:Expr.Assign = cast param;
        //     '"${assign.name.lexeme}" => ${generateExpr(assign.value)}';
        //   default: generateExpr(param);
        // }).join(', ') + '])';
    }
  }

  public function visitArrayLiteralExpr(expr:Expr.ArrayLiteral):String {
    if (expr.isNative) {
      return '[' + generateList(expr.values) + ']';
    }

    var cls = generateTypePath(new Expr.Type([ 
      new Token(TokTypeIdentifier, 'Array', 'Array', null)
    ], true, false));
    return 'new $cls([' + generateList(expr.values) + '])';
  }

  public function visitMapLiteralExpr(expr:Expr.MapLiteral):String {
    indent();
    var out = '[\n' + [ for (i in 0...expr.keys.length) {
      var key = expr.keys[i];
      var value = expr.values[i];
      getIndent() + '${generateExpr(key)} => ${generateExpr(value)}';
    } ].join(',\n');
    outdent();
    return out + '\n' + getIndent() + ']';
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
    // var kind:PhpKind = scope.get(name);
    // if (kind != PhpVar) throw error(expr.name, 'Invalid assignment');
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
    isCall = true;
    var type = context.typeOf(expr.callee);
    var callee = switch type {
      case TPath(_) | TClass(_):
        'new ' + generateExpr(expr.callee);
      case TPhpScalar(kind):
        throw error(expr.paren, '$kind is not callable');
      case TInstance(_):
        throw error(expr.paren, 'Cannot call an instance of ${type.getTypeName()}');
      default:
        // fallback behavior 
        switch expr.callee.getClass() {
          case Expr.Type:
            'new ' + generateExpr(expr.callee);
          default: 
            generateExpr(expr.callee);
        }
    }
    isCall = false;
    return '$callee(' + expr.args.map(arg -> switch arg {
      case Positional(expr): generateExpr(expr);
      case Named(name, expr): '${name}: ${generateExpr(expr)}';
    }).join(', ') + ')';
  }

  public function visitGetExpr(expr:Expr.Get):String {
    var objectType = context.typeOf(expr.object);
    switch objectType {
      case TInstance(cls) | TClass(cls) if (cls.name == 'String'):
      // case TInstance(StaticAnalyzer.stringType):
        // @todo: this is just a proof of concept -- we need a 
        //        better solution.
        var name = getProperty(expr.name, false);
        var str = generateExpr(expr.object);
        return '(new \\Std\\PhaseString(' + str + '))->' + name;
      // case TInstance(_):
      //   switch Type.getClass(expr.name) {
      //     case Expr.Variable:
      //       var e:Expr.Variable = cast expr.name;
      //       var tok = e.name;
      //       switch tok.type {
      //         case TokIdentifier: 
      //           var name = safeVar(e.name);
      //           return generateExpr(expr.object) + '->' + name;
      //         default: 
      //           // safeVar(e.name);
      //       }
      //     default:
      //   }
      // case TClass(cls):
      //   switch Type.getClass(expr.name) {
      //     case Expr.Variable:
      //       var e:Expr.Variable = cast expr.name;
      //       var tok = e.name;
      //       switch tok.type {
      //         case TokIdentifier: 
      //           var name = tok.lexeme;
      //           var field = cls.fields.find(f -> f.name == name);
      //           if (field == null) {
      //             throw error(tok, 'No property with the name $name exists on ${cls.name}');
      //           }
      //           return generateExpr(expr.object) + '::' + switch field.kind {
      //             case TVar(_): '$$' + safeVar(tok);
      //             default: safeVar(tok);
      //           };
      //         case TokClass:
      //           return generateExpr(expr.object) + '::class';
      //         default: 
      //           // safeVar(e.name);
      //       }
      //     default:
      //   }
      default:
    }

    return switch expr.object.getClass() {
      case Expr.Type | Expr.Static:
        generateExpr(expr.object) + '::' + getProperty(expr.name, !isCall);
      default: 
        generateExpr(expr.object) + '->' + getProperty(expr.name);
    }
  }

  public function visitSetExpr(expr:Expr.Set):String {
    var left = switch expr.object.getClass() {
      case Expr.Type | Expr.Static: 
        generateExpr(expr.object) + '::' + getProperty(expr.name, true);
      default: 
        generateExpr(expr.object) + '->' + getProperty(expr.name);
    }
    return left + ' = ' + generateExpr(expr.value);
  }

  function getProperty(expr:Expr, isStatic:Bool = false):String return switch Type.getClass(expr) {
    case Expr.Variable: 
      var e:Expr.Variable = cast expr;
      switch e.name.type {
        case TokTypeIdentifier: safeVar(e.name);
        case TokClass: 'class';
        default: isStatic ? '$' + safeVar(e.name) : safeVar(e.name);
      }
    default:
      '{' + generateExpr(expr) + '}';
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

  public function visitNamespacedExpr(expr:Expr.Namespaced):String {
    return generateExpr(expr.type) + '\\' + safeVar(expr.name);
  }

  public function visitTernaryExpr(expr:Expr.Ternary):String {
    return generateExpr(expr.condition) 
      + ' ? ' + generateExpr(expr.thenBranch)
      + ' : ' + generateExpr(expr.elseBranch);
  }

  public function visitLiteralExpr(expr:Expr.Literal):String {
    return if (Std.isOfType(expr.value, Int)) 
      expr.value;
    else if (Std.isOfType(expr.value, Bool))
      if (expr.value == true) 'true' else 'false';
    else if (expr.value == null)
      'null';
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
      if (!isLocal(name) && kind != PhpFun) {
        if (!closureCaptures.contains(name)) closureCaptures.push(name);
      }
    }

    return switch kind {
      case PhpVar: '$' + name;
      default: name;
    }
  }

  function generateTypePath(t:Expr.Type):String {
    var type = t.path.map(t -> t.lexeme).join('\\');
    if (type == 'Any') return 'mixed'; // Can't be nullable
    if (typeAliases.exists(type)) {
      type = typeAliases.get(type);
      if (t.nullable) return '?$type';
      return type;
    }
    if (t.absolute) {
      type = '\\' + type;
    }
    if (t.nullable) {
      type = '?' + type;
    }
    return type;
  }

  function generateAttributes(target:{
    cls:Stmt.Class,
    ?field:String
  }, attributes:Array<Expr>):String {
    var clsName = target.cls.name.lexeme;
    
    if (options.attribute == AnnotateOnClass) {
      if (!target.cls.fields.exists(f -> f.name.lexeme == '__attributes__')) {
        var pos = target.cls.name.pos;
        var tok = new Token(TokIdentifier, '__attributes__', '', pos);
        target.cls.fields.push(new Stmt.Field(
          tok,
          FVar(new Stmt.Var(tok, null, new Expr.ArrayLiteral(tok, [], false)), null),
          [ APublic, AStatic ],
          []
        ));
      }
    }

    return switch options.attribute {
      case AnnotateDocblock:
        var out = '\n' + getIndent() + '/**';
        for (a in attributes) {
          out += '\n' + getIndent() + ' * ' + generateExpr(a);
        }
        out += '\n' + getIndent() + ' */';
      case AnnotatePhpAttribute:
        var out = '';
        for (a in attributes) {
          out += '\n' + getIndent() + '#[' + generateExpr(a) + ']';
        }
        out;
      case AnnotatePhase:
        var kind = target.field == null ? '__CLASS__' : target.field;
        var reg = '\\Phase\\Boot::registerAttribute($clsName::class, "$kind", '
          + visitArrayLiteralExpr(new Expr.ArrayLiteral(
            target.cls.name,
            attributes,
            false
          )) + ');';
        append.push(reg);
        '';
      case AnnotateOnClass:
        var kind = target.field == null ? '__CLASS__' : target.field;
        var reg = '$clsName::$$__attributes__["$kind"] = '
          + visitArrayLiteralExpr(new Expr.ArrayLiteral(
            target.cls.name,
            attributes,
            false
          )) + ';';
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

  function typeOf(expr:Expr) {
    return switch context.typeOf(expr) {
      case TPath(tp):
        server.locateType(tp.namespace.concat([ tp.name ]).join('::'));
      case other: 
        other;
    }
  }

  function findType(type:phase.analysis.Type) {
    return switch type {
      case TPath(tp): 
        server.locateType(type.getTypeName());
      case other:
        other;
    }
  }

}

class GeneratorError {

  // todo

  public function new() {}

}