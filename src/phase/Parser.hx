package phase;

import phase.Stmt.FunctionArg;
using Lambda;

class Parser {

  static final continuationTokens:Array<TokenType> = [
    TokDot,
    TokPlus,
    TokConcat,
    TokPipe,
    TokMinus,
    TokBoolEqual,
    TokBangEqual,
    TokBoolAnd,
    TokBoolOr
  ];

  final tokens:Array<Token>;
  final reporter:ErrorReporter;
  var current:Int;
  var uid:Int = 0;
  var inNamespace:Bool = false;

  public function  new(tokens:Array<Token>, reporter:ErrorReporter) {
    this.tokens = tokens;
    this.reporter = reporter;
  }

  public function parse():Array<Stmt> {
    var stmts:Array<Stmt> = [];
    current = 0;

    ignoreNewlines();
    while (!isAtEnd()) {
      var stmt = declaration();
      if (stmt != null) stmts.push(stmt);
    }
    return stmts;
  }

  function declaration(?attribute:Array<Expr>):Stmt {
    if (attribute == null) attribute = [];
    // try {
      if (match([ TokLeftBracket ])) return declaration(attributeList());
      if (match([ TokVar ])) {
        if (attribute.length > 0) {
          error(previous(), 'Attributes are not allowed here');
        }
        return varDeclaration();
      }
      if (match([ TokGlobal ])) {
        if (attribute.length > 0) {
          error(previous(), 'Attributes are not allowed here');
        }
        return globalDeclaration();
      }
      if (match([ TokFunction ])) return functionDeclaration(false, attribute);
      if (match([ TokEnum ])) return enumDeclaration(attribute);
      if (match([ TokInterface ])) return interfaceDeclaration(attribute);
      if (match([ TokTrait ])) return traitDeclaration(attribute);
      if (match([ TokClass ])) return classDeclaration(attribute);
      if (match([ TokUse ])) return useDeclaration(attribute);
      if (match([ TokNamespace ])) return packageDeclaration(attribute);
      return statement();
    // } catch (error:ParserError) {
    //   synchronize();
    //   return null;
    // }
  }

  function statement():Stmt {
    if (match([ TokIf ])) return ifStatement();
    if (match([ TokWhile ])) return whileStatement();
    if (match([ TokFor ])) return forStatement();
    if (match([ TokSwitch ])) return switchStatement();
    if (match([ TokReturn ])) return returnStatement();
    if (match([ TokThrow ])) return throwStatement();
    if (match([ TokTry ])) return tryStatement();
    if (match([ TokLeftBrace ])) return blockStatement();
    return expressionStatement();
  }

  function packageDeclaration(attribute:Array<Expr>) {
    if (inNamespace) error(previous(), 'Namespaces cannot be nested');
    inNamespace = true;

    var path = parseList(
      TokScopeResolutionOperator, 
      () -> consume(TokTypeIdentifier, "Expect a package name seperated by '::'")
    );

    consume(TokLeftBrace, 'Expect `{` after a package name.');
    ignoreNewlines();

    var decls:Array<Stmt> = [];
    while (!check(TokRightBrace) && !isAtEnd()) {
      decls.push(declaration());
    }
    
    consume(TokRightBrace, 'Expect `}` at the end of a package declaration.');
    ignoreNewlines();

    inNamespace = false;
    return new Stmt.Namespace(path, decls, attribute);
  }

  function useDeclaration(attribute:Array<Expr>) {
    if (!inNamespace) error(previous(), '`use` is not allowed outside a namespace');
    
    var kind:Stmt.UseKind = UseNormal;
    var absolute = false;
    var path:Array<Token> = [];
    
    if (match([ TokScopeResolutionOperator ])) absolute = true;
    // var path = parseList(
    //   TokScopeResolutionOperator, 
    //   () -> consume(TokTypeIdentifier, "Expect a package name seperated by '::'")
    // );

    do {
      ignoreNewlines();
      if (match([ TokTypeIdentifier ])) {
        path.push(previous());
      } else if(match([ TokIdentifier ])) {
        // Is a function -- only allowed at the end.
        kind = UseSub([ TargetFunction(previous()) ]);
        if (match([ TokScopeResolutionOperator ])) {
          throw error(previous(), "Lowercase identifiers may only come at the end of a use statement.");
        }
        break;
      } else if (match([ TokLeftBrace ])) {
        kind = UseSub(parseList(TokComma, function():Stmt.UseTarget {
          if (match([ TokTypeIdentifier, TokIdentifier ])) {
            var tok = previous();
            return tok.type == TokIdentifier 
              ? TargetFunction(tok)
              : TargetType(tok);
          } else {
            throw error(peek(), "Expect an identifier or a type identifier");
            return null;
          }
        }));
        ignoreNewlines();
        consume(TokRightBrace, "Expect a '}'.");
        break;
      } else {
        throw error(previous(), "Expected a type identifier or a '{'");
      }
    } while (match([ TokScopeResolutionOperator ]) && !isAtEnd());

    if (match([ TokAs ])) {
      if (match([ TokTypeIdentifier, TokIdentifier ])) {
        var tok = previous();

        switch kind {
          case UseSub([ TargetFunction(p) ]):
            path.push(p);
          default:
        }

        kind = UseAlias(
          tok.type == TokIdentifier 
            ? TargetFunction(tok)
            : TargetType(tok)
        );
      } else {
        throw error(peek(), "Expect an identifier or a type identifier");
      }
    }

    expectEndOfStatement();
    return new Stmt.Use(path, absolute, kind, attribute);
  }

  function varDeclaration() {
    var name:Token = consume(TokIdentifier, 'Expect variable name.');
    var type:Expr.Type = null;
    var init:Expr = null;
    if (match([ TokColon ])) {
      type = parseTypePath();
    }
    if (match([ TokEqual ])) {
      init = expression();
    }
    expectEndOfStatement();
    return new Stmt.Var(name, type, init);
  }

  function globalDeclaration() {
    var name:Token = consume(TokIdentifier, 'Expect variable name.');
    expectEndOfStatement();
    return new Stmt.Global(name);
  }

  function functionDef(?isAnnon:Bool, ?attributes:Array<Expr>):Stmt {
    if (attributes == null) attributes = [];
    var name:Token;
    if (!isAnnon || check(TokIdentifier)) {
      name = consume(TokIdentifier, 'Expect function name.');
    } else {
      name = new Token(TokIdentifier, '', null, previous().pos);
    }

    consume(TokLeftParen, 'Expect \'(\' after function name.');
    var params = functionParams();
    var ret = typeHint();
    consume(TokLeftBrace, 'Expect \'{\' before function body');
    var body = functionBody();

    return new Stmt.Function(name, params, body, ret, attributes);
  }

  function functionParams(?allowInit:Bool = false):Array<Stmt.FunctionArg> {
    var params:Array<Stmt.FunctionArg> = [];
    if (!check(TokRightParen)) {
      do {
        ignoreNewlines();
        var isInit = false;

        if (allowInit && match([ TokThis ])) {
          isInit = true;
          consume(TokDot, "Expect a '.' after 'this'.");
        }

        var name = consume(TokIdentifier, 'Expect parameter name');
        var type = typeHint();
        var expr:Expr = null;
        if (match([ TokEqual ])) {
          expr = expression();
        }

        params.push({
          name: name,
          type: type,
          expr: expr,
          isInit: isInit
        });
      } while(match([ TokComma ]));
    }
    ignoreNewlines();
    consume(TokRightParen, 'Expect \')\' after parameters');
    return params; 
  }

  function functionBody():Stmt {
    var body:Array<Stmt> = null;
    
    if (match([ TokRightBrace ])) {
      return new Stmt.Block([]);
    }

    if (!check(TokNewline) && !check(TokReturn)) {
      // Treat the next expression as a return.
      body = [ new Stmt.Return(peek(), expression()) ];
      ignoreNewlines();
      consume(TokRightBrace, 'Inline functions must contain only one expression.');
    } else {
      body = block();
    }
    
    return new Stmt.Block(body);
  }

  function functionDeclaration(isInline:Bool = false, ?attributes:Array<Expr>):Stmt {
    var def = functionDef(isInline, attributes);
    ignoreNewlines();
    return def;
  }

  function interfaceDeclaration(attribute:Array<Expr>) {
    var name = consume(TokTypeIdentifier, 'Expect a class name. Must start uppercase.');
    var interfaces:Array<Expr.Type> = [];
    var fields:Array<Stmt.Field> = [];

    ignoreNewlines();
    while (match([ TokExtends ]) && !isAtEnd()) {
      interfaces.push(parseTypePath());
      ignoreNewlines();
    }

    consume(TokLeftBrace, "Expect '{' before interface body.");
    ignoreNewlines();
    
    while (!check(TokRightBrace) && !isAtEnd()) {
      ignoreNewlines();
      fields.push(fieldDeclaration({ access:[ AAbstract ] }));
    }

    ignoreNewlines();
    consume(TokRightBrace, "Expect '}' at end of interface body");
    ignoreNewlines();

    return new Stmt.Class(name, KindInterface, null, interfaces, fields, attribute);
  }

  function traitDeclaration(attribute:Array<Expr>) {
    var name = consume(TokTypeIdentifier, 'Expect a trait name. Must start uppercase.');
    var fields:Array<Stmt.Field> = [];

    consume(TokLeftBrace, "Expect '{' before trait body.");
    ignoreNewlines();
    
    while (!check(TokRightBrace) && !isAtEnd()) {
      ignoreNewlines();
      fields.push(fieldDeclaration());
    }

    ignoreNewlines();
    consume(TokRightBrace, "Expect '}' at end of trait body");
    ignoreNewlines();

    return new Stmt.Class(name, KindTrait, null, [], fields, attribute);
  }

  function enumDeclaration(attribute:Array<Expr>) {
    var enumName = consume(TokTypeIdentifier, 'Expect an enum name. Must start uppercase.');
    var fields:Array<Stmt.Field> = [];

    if (match([ TokAs ])) {
      var superClass = consume(TokTypeIdentifier, 'Expect a wrapped type name');
  
      consume(TokLeftBrace, "Expect '{' before enum body.");
      ignoreNewlines();
      
      var index = 0;
      while (!check(TokRightBrace) && !isAtEnd()) {
        ignoreNewlines();
        var fieldName = consume(TokTypeIdentifier, "Expect an uppercase identifier");
        var value:Expr = if (match([ TokEqual ])) {
          expression();
        } else switch superClass.lexeme {
          case 'String': new Expr.Literal(fieldName.lexeme);
          case 'Int': new Expr.Literal(index);
          case _: throw error(superClass, 'Unknown type -- currently enums may only be Strings or Ints');
        }
        index++;
        expectEndOfStatement();
        fields.push(new Stmt.Field(
          fieldName,
          FVar(new Stmt.Var(fieldName, null, value), null),
          [ AConst ],
          []
        ));
      }
  
      ignoreNewlines();
      consume(TokRightBrace, "Expect '}' at end of enum body");
      ignoreNewlines();
    
      return new Stmt.Class(enumName, KindClass, null, [], fields, attribute);
    }

    consume(TokLeftBrace, "Expect '{' before enum body.");
    ignoreNewlines();
    var index = 0;

    while (!check(TokRightBrace) && !isAtEnd()) {
      ignoreNewlines();
      var name = consume(TokTypeIdentifier, 'Expect an uppercase identifier');
      var params:Array<FunctionArg> = [];
      var ret = new Expr.Type([ enumName ], false);

      if (!check(TokNewline)) {
        consume(TokLeftParen, 'Expect \'(\' after function name.');
        params = functionParams(false);
      }

      var body = CodeBuilder.generate('{ return ${enumName.lexeme}(
        ${index++},
        "${name.lexeme}",
        [ ${params.map(p -> p.name.lexeme).join(', ')} ]
      ) }', enumName.pos, reporter);

      expectEndOfStatement();

      fields.push(new Stmt.Field(
        name,
        FFun(new Stmt.Function(name, params, body[0], ret, [])),
        [ APublic, AStatic ],
        attribute
      ));
    }

    ignoreNewlines();
    consume(TokRightBrace, "Expect '}' at end of enum body");
    ignoreNewlines();

    return new Stmt.Class(enumName, KindClass, new Expr.Type(
      [ 
        new Token(TokTypeIdentifier, 'Std', 'Std', enumName.pos),
        new Token(TokTypeIdentifier, 'PhaseEnum', 'PhaseEnum', enumName.pos)
      ],
      true
    ), [], fields, attribute);
  }

  function classDeclaration(attribute:Array<Expr>) {
    var name = consume(TokTypeIdentifier, 'Expect a class name. Must start uppercase.');
    var superclass:Expr.Type = null;
    var interfaces:Array<Expr.Type> = [];
    var fields:Array<Stmt.Field> = [];

    ignoreNewlines();
    while(match([
      TokExtends,
      TokImplements
    ]) && !isAtEnd()) {
      switch previous().type {
        case TokExtends:
          if (superclass != null) throw error(previous(), 'Can only extend once');
          superclass = parseTypePath();
        case TokImplements:
          interfaces.push(parseTypePath());
        default:
      }
      ignoreNewlines();
    }

    consume(TokLeftBrace, "Expect '{' before class body.");
    ignoreNewlines();
    
    while (!check(TokRightBrace) && !isAtEnd()) {
      ignoreNewlines();

      var field = fieldDeclaration();
      fields.push(field);

      // Add initializers
      switch field.kind {
        case FFun(func): for (a in func.params) {
          if (a.isInit == true && !fields.exists(f -> f.name.lexeme == a.name.lexeme)) {
            fields.push(new Stmt.Field(
              a.name,
              FVar(new Stmt.Var(a.name, a.type, null), a.type),
              [ APublic ],
              []
            ));
          }
        } 
        default:
      }
    }

    ignoreNewlines();
    consume(TokRightBrace, "Expect '}' at end of class body");
    ignoreNewlines();

    return new Stmt.Class(name, KindClass, superclass, interfaces, fields, attribute);
  }

  function fieldDeclaration(?options:{ access:Array<Stmt.FieldAccess> }) {
    if (options == null) options = { access: [] };

    if (match([ TokUse ])) {
      // Note: this will need to get more complex later.
      var out = new Stmt.Field(
        previous(),
        FUse(parseTypePath()),
        [],
        []
      );
      expectEndOfStatement();
      return out;
    }

    if (match([ TokConst ])) {
      var name = consume(TokTypeIdentifier, 'Expect uppercase identifier');
      ignoreNewlines();
      consume(TokEqual, 'Expect assignment for consts');
      var value = expression();
      var out = new Stmt.Field(
        name,
        FVar(new Stmt.Var(name, null, value), null),
        [ AConst ],
        []
      );
      expectEndOfStatement();
      return out;
    }

    var access:Array<Stmt.FieldAccess> = options.access;
    var attribute:Array<Expr> = [];
    function addAccess(a:Stmt.FieldAccess) {
      if (access.has(a)) error(previous(), 'Only one `$a` declaration is allowed per field'); 
      access.push(a);
    }

    if (match([ TokLeftBracket ])) {
      attribute = attributeList();
    }

    while (match([
      TokStatic,
      TokPublic,
      TokPrivate,
      TokAbstract
      // Todo: we need getters and setters as well
    ]) && !isAtEnd()) switch previous().type {
      case TokStatic: addAccess(AStatic);
      case TokPrivate: addAccess(APrivate);
      case TokPublic: addAccess(APublic);
      case TokAbstract: addAccess(AAbstract);
      default:
    };

    if (access.length == 0 || (!access.has(APublic) && !access.has(APrivate))) {
      access.push(APublic);
    }

    var name = consume(TokIdentifier, 'Expected an identifier');
    var type:Expr.Type = null;

    if (match([ TokColon ])) {
      type = parseTypePath();
    }
    
    if (match([ TokNewline ])) {
      ignoreNewlines();
      return new Stmt.Field(
        name,
        FVar(new Stmt.Var(name, type, null), type),
        access,
        attribute
      );
    }

    if (match([ TokLeftBrace ])) {
      var getter:Stmt.Function = null;
      var setter:Stmt.Function = null;
      while (!check(TokRightBrace) && !isAtEnd()) {
        ignoreNewlines();
        var mode = consume(TokIdentifier, 'Expected an identifier');
        switch mode.lexeme {
          case 'get':
            if (getter != null) {
              throw error(mode, '`get` already defined');
            }
            consume(TokLeftBrace, 'expected a `{`');
            var body = functionBody();
            expectEndOfStatement();
            getter = new Stmt.Function(mode, [], body, type, []);
          case 'set':
            if (setter != null) {
              throw error(mode, '`set` already defined');
            }
            consume(TokLeftBrace, 'expected a `{`');
            var body = functionBody();
            expectEndOfStatement();
            setter = new Stmt.Function(mode, [
              { 
                name: new Token(TokIdentifier, 'value', 'value', previous().pos),
                type: type,
                expr: null,
              }
            ], body, type, []);
          default:
            throw error(mode, 'Expected `get` or `set`');
        }
      }
      ignoreNewlines();
      consume(TokRightBrace, 'Expected a `}`');
      return new Stmt.Field(
        name,
        FProp(getter, setter, type),
        access,
        attribute
      );
    }

    if (match([ TokEqual ])) {
      if (access.has(AAbstract)) {
        throw error(previous(), 'No assignment allowed');
      }

      ignoreNewlines();
      var expr = expression();
      expectEndOfStatement();
      return new Stmt.Field(
        name,
        FVar(new Stmt.Var(name, type, expr), type),
        access,
        attribute
      ); 
    }

    consume(TokLeftParen, 'Expect \'(\' after function name.');
    var params = functionParams(name.lexeme == 'new');
    var ret = typeHint();
    var body:Stmt = null;
    if (access.has(AAbstract)) {
      expectEndOfStatement();
    } else {
      consume(TokLeftBrace, 'Expect \'{\' before function body');
      body = functionBody();
      expectEndOfStatement();
    }

    return new Stmt.Field(
      name,
      FFun(new Stmt.Function(name, params, body, ret, [])),
      access,
      attribute
    );
  }

  function attributeList():Array<Expr> {
    var attribute:Array<Expr> = [];
    do {
      var absolute = false;
      if (match([ TokScopeResolutionOperator ])) absolute = true;
      var path = parseList(
        TokScopeResolutionOperator, 
        () -> consume(TokTypeIdentifier, "Expect a package name seperated by '::'")
      );
      var params:Array<Expr.CallArgument> = [];
      if (match([ TokLeftParen ])) {
        if (!check(TokRightParen)) {
          params = parseArguments();
        }
        ignoreNewlines();
        consume(TokRightParen, "Expect ')' at the end of an attribute");
      }
      ignoreNewlines();
      attribute.push(new Expr.Attribute(path, params, absolute, null));
    } while (match([ TokComma ]));
    consume(TokRightBracket, "Expect a ']' at the end of an attribute");
    ignoreNewlines();
    if (match([ TokLeftBracket ])) {
      attribute = attribute.concat(attributeList());
    }
    return attribute;
  }

  function throwStatement():Stmt {
    var value = expression();
    expectEndOfStatement();
    return new Stmt.Throw(previous(), value);
  }

  function tryStatement():Stmt {
    ignoreNewlines();
    consume(TokLeftBrace, "Expect '{' after 'try'");
    ignoreNewlines();

    var body = blockStatement();
    var caught:Array<Stmt.Caught> = [];

    ignoreNewlines();
    while (match([ TokCatch ]) && !isAtEnd()) {
      consume(TokLeftParen, "Expect '('");
      var name = consume(TokIdentifier, "Expect an identifier");
      var type = typeHint();
      consume(TokRightParen, "Expect ')'");
      consume(TokLeftBrace, "Expect '{'");
      ignoreNewlines();
      var body = blockStatement();
      caught.push({
        name: name,
        type: type,
        body: body
      });
    }
    return new Stmt.Try(body, caught);
  }

  function ifStatement():Stmt {
    consume(TokLeftParen, "Expect '(' after 'if'.");
    var condition:Expr = expression();
    consume(TokRightParen, "Expect ')' after if condition.");

    var thenBranch = statement();
    if (!Std.isOfType(thenBranch, Stmt.Block)) {
      thenBranch = new Stmt.Block([ thenBranch ]);
    }
    
    var elseBranch:Stmt = null;
    if (match([ TokElse ])) {
      elseBranch = statement();
      if (!Std.isOfType(elseBranch, Stmt.Block)) {
        elseBranch = new Stmt.Block([ elseBranch ]);
      }
    }

    return new Stmt.If(condition, thenBranch, elseBranch);
  }

  function whileStatement():Stmt {
    consume(TokLeftParen, "Expect '(' after 'while'.");
    var condition = expression();
    consume(TokRightParen, "Expect ')' after 'while' condition.");
    var body = statement();

    return new Stmt.While(condition, body);
  }

  function forStatement():Stmt {
    consume(TokLeftParen, "Expect '(' after 'for'.");
    var key = consume(TokIdentifier, 'Expect an identifier');
    var value = null;
    if (check(TokColon)) {
      advance();
      value = consume(TokIdentifier, 'Expect an identifier after a colon');
    }
    consume(TokIn, 'Expect `in` after destructuring');
    var target = expression();
    consume(TokRightParen, "Expect ')'");
    var body = statement();
    return new Stmt.For(key, value, target, body);
  }

  function switchStatement() {
    consume(TokLeftParen, "Expect '(' after 'switch'.");
    ignoreNewlines();
    var target = expression();
    ignoreNewlines();
    consume(TokRightParen, "Expect ')' after switch target");
    ignoreNewlines();
    consume(TokLeftBrace, "Expect '{'");
    ignoreNewlines();
    
    var cases:Array<Stmt.SwitchCase> = [];
    
    while(!isAtEnd() && match([ TokCase, TokDefault ])) {
      ignoreNewlines();
      var condition:Expr = null;
      var isDefault = false;
      
      if (previous().type == TokDefault) {
        isDefault = true;
      } else {
        condition = expression();
      }

      consume(TokColon, "Expect a ':' after case condition");
      ignoreNewlines();

      var body:Array<Stmt> = [];
      while(
        !isAtEnd() 
        && !check(TokCase) 
        && !check(TokDefault)
        && !check(TokRightBrace)
      ) {
        body.push(statement());
      }

      cases.push({
        condition: condition,
        body: body,
        isDefault: isDefault
      });
    }

    ignoreNewlines();
    consume(TokRightBrace, "Expect a '}' at the end of a switch statement");
    ignoreNewlines();

    return new Stmt.Switch(target, cases);
  }

  function matchExpr():Expr {
    consume(TokLeftParen, "Expect '(' after 'match'.");
    ignoreNewlines();
    var target = expression();
    ignoreNewlines();
    consume(TokRightParen, "Expect ')' after match target");
    ignoreNewlines();
    consume(TokLeftBrace, "Expect '{'");
    ignoreNewlines();
    
    var cases:Array<Stmt.SwitchCase> = [];    

    while(!isAtEnd() && !check(TokRightBrace)) {
      ignoreNewlines();
      
      var isDefault = false;
      var condition:Expr = match([ TokDefault ]) ? {
        isDefault = true;
        null;
      } : expression();

      consume(TokArrow, 'Expect a -> after matches');
      ignoreNewlines();
      
      var body = statement();

      cases.push({
        condition: condition,
        body: [ body ],
        isDefault: isDefault
      });
    }

    ignoreNewlines();
    consume(TokRightBrace, "Expect a '}' at the end of a match statement");
    ignoreNewlines();

    return new Expr.Match(target, cases);
  }

  function returnStatement():Stmt {
    var keyword = previous();
    var value:Expr = null;
    if (!check(TokSemicolon) && !check(TokNewline)) {
      value = expression();
    }
    expectEndOfStatement();
    return new Stmt.Return(keyword, value);
  }

  function block() {
    ignoreNewlines();
    var statements:Array<Stmt> = [];
    while (!check(TokRightBrace) && !isAtEnd()) {
      statements.push(declaration());
    }
    ignoreNewlines();
    consume(TokRightBrace, "Expect '}' at the end of a block.");
    // ignoreNewlines();
    return statements;
  }

  function blockStatement() {
    var statements = block();
    ignoreNewlines();
    return new Stmt.Block(statements);
  }
  
  private function expressionStatement():Stmt {
    var expr = expression();
    expectEndOfStatement();
    return new Stmt.Expression(expr);
  }

  function expression() {
    return assignment();
  }

  function assignment() {
    var expr:Expr = or();

    if (match([ TokEqual/*, TokPlusEqual */])) {
      var equals = previous();
      // Todo: how do we handle TokPlusEqual?

      ignoreNewlines();
      var value = assignment();

      if (Std.isOfType(expr, Expr.Variable)) {
        var name = (cast expr).name;
        return new Expr.Assign(name, value);
      } else if (Std.isOfType(expr, Expr.Get)) {
        var get:Expr.Get = cast expr;
        return new Expr.Set(get.object, get.name, value);
      } else if (Std.isOfType(expr, Expr.SubscriptGet)) {
        var get:Expr.SubscriptGet = cast expr;
        return new Expr.SubscriptSet(previous(), get.object, get.index, value);
      }

      throw error(equals, "Invalid assignment target.");
    }

    return expr;
  }

  function or() {
    var expr:Expr = and();

    while (match([ TokBoolOr ])) {
      var op = previous();
      var right = and();
      expr = new Expr.Logical(expr, op, right);
    }

    return expr;
  }

  function and() {
    var expr:Expr = equality();

    while (match([ TokBoolAnd ])) {
      var op = previous();
      var right = equality();
      expr = new Expr.Logical(expr, op, right);
    }

    return expr;
  }

  function equality() {
    var expr:Expr = comparison();

    while(match([TokBangEqual, TokBoolEqual])) {
      var op = previous();
      var right = comparison();
      expr = new Expr.Binary(expr, op, right);
    }

    return expr;
  }

  function comparison():Expr {
    var expr = addition();

    if (match([ TokIs ])) {
      var type = parseTypePath();
      ignoreNewlines();
      return new Expr.Is(expr, type);
    }

    while (match([ TokGreater, TokGreaterEqual, TokLess, TokLessEqual ])) {
      var op = previous();
      ignoreNewlines();
      var right = addition();
      expr = new Expr.Binary(expr, op, right);
    }

    return expr;
  }

  function addition() {
    var expr = multiplication();

    while (match([ TokMinus, TokPlus, TokConcat ])) {
      var op = previous();
      ignoreNewlines();
      var right = multiplication();
      expr = new Expr.Binary(expr, op, right);
    }

    return expr;
  }

  function multiplication() {
    var expr = range();

    while (match([ TokSlash, TokStar ])) {
      var op = previous();
      ignoreNewlines();
      var right = range();
      expr = new Expr.Binary(expr, op, right);
    }

    return expr;
  }

  function range() {
    var expr = pipe();

    while (match([ TokRange ])) {
      ignoreNewlines();
      var to = pipe();
      expr = new Expr.Range(expr, to);
    }

    return expr;
  }

  function pipe() {
    var expr = unary();

    while (match([ TokPipe ])) {
      var op = previous();
      var target = unary();
      switch Type.getClass(target) {
        case Expr.Call: 
          var caller:Expr.Call = cast target;
          caller.args.push(Positional(expr));
          expr = caller;
        case Expr.Lambda:
          expr = new Expr.Call(
            new Expr.Grouping(target),
            op,
            [ Positional(expr) ]
          );
        default:
          throw error(op, 'Expected a function/method call or a lambda');
      }
    }

    return expr;
  }

  function unary():Expr {
    if (match([ TokBang, TokMinus, TokPlusPlus, TokMinusMinus ])) {
      var op = previous();
      ignoreNewlines();
      return new Expr.Unary(op, unary(), true);
    }

    var expr = call();

    if (match([ TokPlusPlus, TokMinusMinus ])) {
      var op = previous();
      return new Expr.Unary(op, expr, false);
    }

    return expr;
  }

  function call():Expr {
    var expr:Expr = primary();

    while(!isAtEnd()) {
      conditionalIgnoreNewlines();
      
      if (match([ TokLeftParen ])) {
        expr = finishCall(expr);
      } else if (match([ TokLeftBrace ])) {
        expr = new Expr.Call(expr, previous(), [ Positional(shortLambda(!check(TokNewline))) ]);
      } else if (match([ TokDot ])) {
        ignoreNewlines();
        var name:Expr = if (match([ TokLeftBrace ])) {
          ignoreNewlines();
          var ret = expression();
          if (Std.isOfType(ret, Expr.Variable)) {
            // Required to make this point to a var. 
            ret = new Expr.Grouping(ret);
          }
          ignoreNewlines();
          consume(TokRightBrace, "Expect a '}'");
          ret;
        } else if (match([ TokTypeIdentifier, TokClass ])) {
          new Expr.Variable(previous());
        } else {
          new Expr.Variable(consume(TokIdentifier, "Expect property name after '.'."));
        }
        expr = new Expr.Get(expr, name);
      } else if (match([ TokLeftBracket ])) {
        if (match([ TokRightBracket ])) {
          expr = new Expr.SubscriptGet(previous(), expr, null);
        } else {
          ignoreNewlines();
          var index = expression();
          ignoreNewlines();
          consume(TokRightBracket, "Expect ']' after expression");
          expr = new Expr.SubscriptGet(previous(), expr, index);
        }
      } else {
        break;
      }
    }
    
    return expr;
  }

  function finishCall(callee:Expr):Expr {
    var arguments:Array<Expr.CallArgument> = [];

    if (!check(TokRightParen)) {
      arguments = parseArguments();
    }

    ignoreNewlines();
    var paren = consume(TokRightParen, "Expect ')' after arguments.");

    // Handle trailing arguments (eg, `foo('a') { it }`)
    if (match([ TokLeftBrace ])) {
      arguments.push(Positional(shortLambda(!check(TokNewline))));
    }

    return new Expr.Call(callee, paren, arguments);
  }

  function parseArguments():Array<Expr.CallArgument> {
    var isAfterNamedArgument = false;
    return parseList(TokComma, () -> {
      if (check(TokIdentifier) && checkNext(TokColon)) {
        isAfterNamedArgument = true;
        consume(TokIdentifier, 'expected an identifier');
        var name = previous().lexeme;
        consume(TokColon, 'expected a ":"');
        var expr = expression();
        Expr.CallArgument.Named(name, expr);
      } else {
        if (isAfterNamedArgument) {
          throw error(peek(), 'Positional arguments cannot come after named ones');
        }
        Expr.CallArgument.Positional(expression());
      }
    });
  }

  function taggedTemplate(callee:Expr):Expr {
    var firstTok = peek();
    var parts:Array<Expr> = [];
    var placeholders:Array<Expr> = [];

    // Note: Interpolated strings end on a `TokString`
    if (!check(TokString)) {
      do {
        if (match([ TokInterpolation ])) {
          parts.push(new Expr.Literal(previous().literal));
        } else {
          placeholders.push(expression());
        }
      } while (!check(TokString) && !isAtEnd());
    }
    parts.push(primary());

    return new Expr.Call(callee, firstTok, [
      Positional(new Expr.ArrayLiteral(firstTok, parts, false)),
      Positional(new Expr.ArrayLiteral(firstTok, placeholders, false))
    ]);
  }

  function interpolation(expr:Expr):Expr {
    while (!isAtEnd()) {
      var next:Expr;
      if (match([ TokString ])) { 
        return new Expr.Binary(
          expr,
          new Token(TokConcat, '+++', null, previous().pos),
          new Expr.Literal(previous().literal)
        ); 
      } else if (match([ TokInterpolation ])) {
        next = new Expr.Literal(previous().literal);
      } else {
        next = new Expr.Grouping(expression());
      }
      expr = new Expr.Binary(
        expr,
        new Token(TokConcat, '+++', null, peek().pos),
        next 
      );
    }
    error(peek(), 'Unexpected end of interpolated string');
    return expr;
  }

  function primary():Expr {
    if (match([ TokFalse ])) return new Expr.Literal(false);
    if (match([ TokTrue ])) return new Expr.Literal(true);
    if (match([ TokNull ])) return new Expr.Literal(null);
    if (match([ TokNumber, TokString ])) return new Expr.Literal(previous().literal);

    if (match([ TokInterpolation ])) { 
      return interpolation(new Expr.Literal(previous().literal));
    }

    if (match([ TokSuper ])) {
      var keyword = previous();
      consume(TokDot, "Expect '.' after 'super'.");
      ignoreNewlines();
      var method = consume(TokIdentifier, "Expect superclass method name.");
      return new Expr.Super(keyword, method);
    }

    if (match([ TokThis ])) {
      return new Expr.This(previous());
    }

    if (match([ TokStatic ])) {
      return new Expr.Static(previous());
    }

    if (match([ TokScopeResolutionOperator ])) {
      return namespacedExprOrType(true);
    }

    if (check(TokTypeIdentifier)) {
      return namespacedExprOrType(false);
    }

    if (match([ TokIdentifier ])) {
      return new Expr.Variable(previous());
    }

    if (match([ TokTemplateTag ])) {
      return taggedTemplate(new Expr.Variable(previous()));
    }

    if (match([ TokLeftParen ])) {
      ignoreNewlines();
      var expr = expression();
      ignoreNewlines();
      consume(TokRightParen, "Expect ')' after expression.");
      return new Expr.Grouping(expr);
    }

    if (match([ TokDollar ])) {
      if (match([ TokLeftBracket ])) {
        return arrayOrMapLiteral(true);
      }
    }

    if (match([ TokLeftBracket ])) {
      return arrayOrMapLiteral();
    }

    if (match([ TokLeftBrace ])) {
      return shortLambda(!check(TokNewline));
    }

    if (match([ TokFunction ])) {
      return new Expr.Lambda(functionDef(true));
    }

    if (match([ TokIf ])) {
      return ternary();
    }

    if (match([ TokMatch ])) {
      return matchExpr();
    }

    var tok = peek();
    throw error(tok, 'Unexpected ${tok.type}');
  }

  function namespacedExprOrType(isAbsolute:Bool):Expr {
    var path:Array<Token> = [ 
      consume(TokTypeIdentifier, "Expect at least one type identifier")
    ];
    while(
      check(TokScopeResolutionOperator) 
      && checkNext(TokTypeIdentifier) 
      && !isAtEnd()
    ) {
      advance();
      path.push(advance());
    }
    var type = new Expr.Type(path, isAbsolute);
    if (match([ TokScopeResolutionOperator ])) {
      var tok = consume(TokIdentifier, "Expect an identifier");
      return new Expr.Namespaced(type, tok);
    }
    return type;
  }

  function ternary():Expr {
    consume(TokLeftParen, "Expect '(' after 'if'.");
    var condition:Expr = expression();
    consume(TokRightParen, "Expect ')' after if condition.");
    ignoreNewlines();
    var thenBranch = expression();
    ignoreNewlines();
    consume(TokElse, "Expected an 'else' branch");
    ignoreNewlines();
    var elseBranch = expression();
    return new Expr.Ternary(condition, thenBranch, elseBranch);
  }

  function arrayOrMapLiteral(isNative:Bool = false):Expr {
    ignoreNewlines();
    
    if (checkNext(TokColon)) {
      return mapLiteral(isNative);
    }

    return arrayLiteral(isNative);
  }

  function arrayLiteral(isNative:Bool = false):Expr {
    var prev = current;
    var values:Array<Expr> = [];
    
    if (!check(TokRightBracket)) {
      values = parseList(TokComma, expression);
    }
    
    if (match([ TokColon ])) {
      current = prev;
      return mapLiteral(isNative);
    }

    ignoreNewlines(); // May be one after the last item in the list
    var end = consume(TokRightBracket, "Expect ']' after values.");
    return new Expr.ArrayLiteral(end, values, isNative);
  }

  function mapLiteral(isNative:Bool = false):Expr {
    var keys:Array<Expr> = [];
    var values:Array<Expr> = [];

    if (!check(TokRightBracket)) {
      do {
        ignoreNewlines();
        keys.push(expression());
        consume(TokColon, "Expect colons after object keys");
        ignoreNewlines();
        values.push(expression());
      } while (match([ TokComma ]));
      ignoreNewlines();
    }

    var end = consume(TokRightBracket, "Expect ']' at the end of an assoc array literal");

    return new Expr.MapLiteral(end, keys, values, isNative);
  }

  function shortLambda(isInline:Bool = false) {
    ignoreNewlines();
    var params:Array<Stmt.FunctionArg> = [];
    var maybeNeedIt:Bool = false;
    if (match([ TokBar ])) {
      if (!check(TokBar)) {
        do {
          params.push({
            name: consume(TokIdentifier, 'Expect parameter name'),
            type: null,
            expr: null
          });
        } while(match([ TokComma ]));
      }
      consume(TokBar, 'Expect \'|\' after parameters');
      isInline = !check(TokNewline);
    } else {
      params = [
        { 
          name: new Token(TokIdentifier, 'it', null, previous().pos),
          type: null,
          expr: new Expr.Literal(null) // Allows `it` to be optional 
        }
      ];
    }

    var body:Array<Stmt> = [];
    if (isInline && !check(TokReturn)) {
      // Treat the next expression as a return.
      body.push(new Stmt.Return(peek(), expression()));
      ignoreNewlines();
      consume(TokRightBrace, 'Inline lambdas must contain only one expression.');
    } else {
      body = block();
    }

    return new Expr.Lambda(new Stmt.Function(
      new Token(TokIdentifier, '', null, previous().pos),
      params,
      new Stmt.Block(body),
      null,
      []
    ));
  }

  // function objectLiteral():Expr {
  //   var keys:Array<Token> = [];
  //   var values:Array<Expr> = [];

  //   if (!check(TokRightBrace)) {
  //     do {
  //       ignoreNewlines();
  //       if (check(TokString)) {
  //         keys.push(consume(TokString, "Expect identifiers or strings for object keys"));
  //       } else {
  //         keys.push(consume(TokIdentifier, "Expect identifiers or strings for object keys"));
  //       }
  //       consume(TokColon, "Expect colons after object keys");
  //       ignoreNewlines();
  //       values.push(expression());
  //     } while (match([ TokComma ]));
  //     ignoreNewlines();
  //   }

  //   var end = consume(TokRightBrace, "Expect '}' at the end of an object literal");

  //   return new Expr.ObjectLiteral(end, keys, values);
  // }

  function typeHint():Null<Expr.Type> {
    if (match([ TokColon ])) {
      return parseTypePath();
    }
    return null;
  }

  function parseTypePath():Expr.Type {
    if (match([ TokQuestion ])) {
      // @todo: mark as nullable
    }

    if (match([ TokDollar ])) {
      var native = consume(TokIdentifier, 'Expect a native PHP scalar identifier');
      return new Expr.Type([ native ], false);
    }

    var absolute = match([ TokScopeResolutionOperator ]);
    var path = parseList(
      TokScopeResolutionOperator, 
      () -> consume(TokTypeIdentifier, 'Expect a TypeIdentifier')
    );

    if (match([ TokLess ])) {
      var params = parseList(TokComma, parseTypePath);
      // @todo: do something with the params
      consume(TokGreater, 'Expect a `>` after type params');
    }

    return new Expr.Type(path, absolute);
  }

  function tempVar(prefix:String) {
    uid++;
    return '${prefix}_${uid}';
  }

  function synchronize() {
    advance();
    while (!isAtEnd()) {
      if (previous().type == TokSemicolon) return;
      if (previous().type == TokNewline) return;

      switch (peek().type) {
        case TokClass | TokFunction | TokVar | TokFor | TokIf |
             TokWhile | TokSwitch | TokReturn: return;
        default: advance();
      }
    }
  }

  function parseList<T>(sep:TokenType, parser:Void->T):Array<T> {
    var items:Array<T> = [];
    do {
      ignoreNewlines();
      items.push(parser());
    } while (match([ sep ]) && !isAtEnd());
    return items;
  }

  function conditionalIgnoreNewlines() {
    if (check(TokNewline)) {
      while (!isAtEnd()) {
        if (checkNext(TokNewline)) advance();

        if (continuationTokens.exists(f -> checkNext(f))) {
          advance();
          return;
        }

        if (!checkNext(TokNewline)) return;
      }
    }
  }

  function ignoreNewlines() {
    while (!isAtEnd()) {
      if (!match([ TokNewline ])) {
        return;
      }
    }
  }

  function expectEndOfStatement() {
    if (check(TokRightBrace)) {
      // special case -- allows stuff like '{ |a| a }'
      // We don't consume it here, as the parser needs to check for it.
      return true;
    }
    if (match([ TokNewline, TokEof ])) {
      ignoreNewlines(); // consume any extras
      return true;
    }
    consume(TokSemicolon, "Expect newline or semicolon after statement");
    ignoreNewlines(); // consume any newlines
    return false;
  }

  function match(types:Array<TokenType>):Bool {
    for (type in types) {
      if (check(type)) {
        advance();
        return true;
      }
    }
    return false;
  }

  function consume(type:TokenType, message:String) {
    if (check(type)) return advance();
    throw error(peek(), message);
  }

  function check(type:TokenType):Bool {
    if (isAtEnd()) return false;
    return peek().type == type;
  }

  function checkNext(type:TokenType):Bool {
    if (isAtEnd()) return false;
    return peekNext().type == type;
  }

  function advance():Token {
    if (!isAtEnd()) current++;
    return previous();
  }

  function peek():Token {
    return tokens[current];
  }

  function peekNext():Token {
    return tokens[current + 1];
  }

  function previous():Token {
    return tokens[current - 1];
  }

  function isAtEnd() {
    return peek().type == TokEof;
  }

  function error(token:Token, message:String) {
    reporter.report(token.pos, token.lexeme, message);
    return new ParserError();
  }

}

class ParserError {

  // todo

  public function new() {}

}
