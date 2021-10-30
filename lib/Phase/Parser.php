<?php
namespace Phase {

  use Phase\Language\Token;
  use Phase\Language\TokenType;
  use Phase\Language\Position;
  use Phase\Language\Expr;
  use Phase\Language\ExprDef;
  use Phase\Language\Literal;
  use Phase\Language\Stmt;
  use Phase\Language\StmtDef;
  use Phase\Language\UseKind;
  use Phase\Language\UseTarget;
  use Phase\Language\TypePath;
  use Phase\Language\CallArgument;
  use Phase\Language\FunctionArg;
  use Phase\Language\FunctionDecl;
  use Phase\Language\Field;
  use Phase\Language\FieldAccess;
  use Phase\Language\FieldKind;
  use Phase\Language\ClassDecl;
  use Phase\Language\ClassKind;
  use Phase\Language\MatchCase;

  class Parser
  {

    public function __construct(\Std\PhaseArray $tokens, ErrorReporter $reporter)
    {
      $this->reporter = $reporter;
      $this->tokens = $tokens;
      $this->inNamespace = false;
      $this->uid = 0;
    }

    static public \Std\PhaseArray $continuationTokens;

    protected \Std\PhaseArray $tokens;

    protected ErrorReporter $reporter;

    protected int $current;

    protected int $uid;

    protected Bool $inNamespace;

    public function parse():\Std\PhaseArray
    {
      $statements = new \Std\PhaseArray([]);
      $this->current = 0;
      $this->ignoreNewlines();
      while (!$this->isAtEnd())
      {
        $stmt = $this->declaration();
        if ($stmt != null)
        {
          $statements->push($stmt);
        }
      }
      return $statements;
    }

    public function declaration(\Std\PhaseArray $attributes = null):Stmt
    {
      if ($attributes == null)
      {
        $attributes = new \Std\PhaseArray([]);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBracket])))
      {
        return $this->declaration($this->attributeList());
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokVar])))
      {
        if ($attributes->length > 0)
        {
          throw $this->error($this->previous(), "Attributes are not allowed here");
        }
        return $this->varDeclaration();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokGlobal])))
      {
        if ($attributes->length > 0)
        {
          throw $this->error($this->previous(), "Attributes are not allowed here");
        }
        return $this->globalDeclaration();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokNamespace])))
      {
        return $this->namespaceDeclaration($attributes);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokUse])))
      {
        return $this->useDeclaration($attributes);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokFunction])))
      {
        return $this->functionDeclaration($attributes);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokClass])))
      {
        return $this->classDeclaration($attributes);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokInterface])))
      {
        return $this->interfaceDeclaration($attributes);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokTrait])))
      {
        return $this->traitDeclaration($attributes);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokEnum])))
      {
        return $this->enumDeclaration($attributes);
      }
      return $this->statement();
    }

    public function statement():Stmt
    {
      if ($this->matches(new \Std\PhaseArray([TokenType::TokVar])))
      {
        return $this->varDeclaration();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokGlobal])))
      {
        return $this->globalDeclaration();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokIf])))
      {
        return $this->ifStatement();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokReturn])))
      {
        return $this->returnStatement();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokWhile])))
      {
        return $this->whileStatement();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokDo])))
      {
        return $this->doStatement();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokFor])))
      {
        return $this->forStatement();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokSwitch])))
      {
        return $this->switchStatement();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokThrow])))
      {
        return $this->throwStatement();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokTry])))
      {
        return $this->tryStatement();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBrace])))
      {
        return $this->blockStatement();
      }
      return $this->expressionStatement();
    }

    protected function namespaceDeclaration(\Std\PhaseArray $attributes):Stmt
    {
      if ($this->inNamespace)
      {
        throw $this->error($this->previous(), "Namespaces cannot be nested");
      }
      $this->inNamespace = true;
      $start = $this->previous();
      $parts = $this->parseList(TokenType::TokScopeResolutionOperator, function ($it = null)
      {
        return $this->consume(TokenType::TokTypeIdentifier, "Expect a package name seperated by '::'")->lexeme;
      });
      $name = $parts->pop();
      $path = new TypePath(ns: $parts, name: $name, params: new \Std\PhaseArray([]));
      if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBrace])))
      {
        $this->ignoreNewlines();
        $decls = new \Std\PhaseArray([]);
        while (!$this->check(TokenType::TokRightBrace) && !$this->isAtEnd())
        {
          $decls->push($this->declaration());
        }
        $this->consume(TokenType::TokRightBrace, "Expect '}' at the end of a package declaration.");
        $this->ignoreNewlines();
        $this->inNamespace = false;
        return new Stmt(stmt: StmtDef::SNamespace(path: $path, decls: $decls, attributes: $attributes), pos: $start->pos->merge($this->previous()->pos));
      }
      $this->expectEndOfStatement();
      $decls = new \Std\PhaseArray([]);
      while (!$this->isAtEnd())
      {
        $decls->push($this->declaration());
      }
      $this->ignoreNewlines();
      return new Stmt(stmt: StmtDef::SNamespace(path: $path, decls: $decls, attributes: $attributes), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function useDeclaration(\Std\PhaseArray $attributes):Stmt
    {
      if (!$this->inNamespace)
      {
        throw $this->error($this->previous(), "`use` is not allowed outside a namespace");
      }
      $kind = UseKind::UseNormal();
      $absolute = false;
      $path = new \Std\PhaseArray([]);
      if ($this->matches(new \Std\PhaseArray([TokenType::TokScopeResolutionOperator])))
      {
        $absolute = true;
      }
      do
      {
        $this->ignoreNewlines();
        if ($this->matches(new \Std\PhaseArray([TokenType::TokTypeIdentifier])))
        {
          $path->push($this->previous()->lexeme);
        }
        else
        {
          if ($this->matches(new \Std\PhaseArray([TokenType::TokIdentifier])))
          {
            $kind = UseKind::UseSub(new \Std\PhaseArray([UseTarget::TargetFunction($this->previous()->lexeme)]));
            if ($this->matches(new \Std\PhaseArray([TokenType::TokScopeResolutionOperator])))
            {
              throw $this->error(previous(), "Lowercase identifiers may only come at the end of a use statement.");
            }
            break;
          }
          else
          {
            if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBrace])))
            {
              $kind = UseKind::UseSub($this->parseList(TokComma, function ($it = null)
              {
                if ($this->matches(new \Std\PhaseArray([TokenType::TokTypeIdentifier, TokenType::TokIdentifier])))
                {
                  $tok = $this->previous();
                  return $tok->type == TokenType::TokIdentifier ? UseTarget::TargetFunction($tok) : UseTarget::TargetType($tok);
                }
                else
                {
                  throw $this->error($this->peek(), "Expect an identifier or a type identifier");
                  return null;
                }
              }));
              $this->ignoreNewlines();
              $this->consume(TokenType::TokRightBrace, "Expect a '}'.");
              break;
            }
            else
            {
              throw $this->error($this->previous(), "Expected a type identifier or a '{'");
            }
          }
        }
      }
      while ($this->matches(new \Std\PhaseArray([TokenType::TokScopeResolutionOperator])) && !$this->isAtEnd());
      if ($this->matches(new \Std\PhaseArray([TokenType::TokAs])))
      {
        if ($this->matches(new \Std\PhaseArray([TokenType::TokTypeIdentifier, TokenType::TokIdentifier])))
        {
          $tok = $this->previous();
          $__matcher_1 = $kind;
          if ($__matcher_1->tag == "UseSub") { 
            $items = $__matcher_1->params[0];
            if ($items->length == 1 && $items[0]->tag == "TargetFunction")
            {
              $p = $items[0]->params[0];
              $path->push($p);
            }
          }
          else {
            null;
          };
          $kind = UseKind::UseAlias($tok->type == TokIdentifier ? UseTarget::TargetFunction($tok->lexeme) : UseTarget::TargetType($tok->lexeme));
        }
        else
        {
          throw $this->error($this->peek(), "Expect an identifier or a type identifier");
        }
      }
      $this->expectEndOfStatement();
      return new Stmt(stmt: StmtDef::SUse($path, $absolute, $kind, attribute), pos: start->pos->merge($this->previous()->pos));
    }

    protected function varDeclaration()
    {
      $start = $this->previous();
      $name = $this->consume(TokenType::TokIdentifier, "Expect a variable name");
      $type = null;
      $init = null;
      if ($this->matches(new \Std\PhaseArray([TokenType::TokColon])))
      {
        $type = $this->parseTypePath();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokEqual])))
      {
        $init = $this->expression();
      }
      $this->expectEndOfStatement();
      return new Stmt(stmt: StmtDef::SVar(name: $name->lexeme, type: $type, initializer: $init), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function globalDeclaration():Stmt
    {
      $name = $this->consume(TokenType::TokIdentifier, "Expect a variable name");
      $this->expectEndOfStatement();
      return new Stmt(stmt: StmtDef::SGlobal($name->lexeme), pos: $name->pos);
    }

    protected function functionDecl(Bool $isAnnon, \Std\PhaseArray $attributes):FunctionDecl
    {
      if ($attributes == null)
      {
        $attributes = new \Std\PhaseArray([]);
      }
      $start = $this->previous();
      $name = !$isAnnon || $this->check(TokenType::TokIdentifier) ? $this->consume(TokenType::TokIdentifier, "Expect a function name")->lexeme : "";
      $this->consume(TokenType::TokLeftParen, "Expect '(' after function name.");
      $args = $this->functionArgs();
      $ret = null;
      if ($this->matches(new \Std\PhaseArray([TokenType::TokColon])))
      {
        $ret = $this->parseTypePath();
      }
      $this->consume(TokenType::TokLeftBrace, "Expect '{' before function body");
      $body = $this->functionBody();
      return new FunctionDecl(name: $name, args: $args, body: $body, ret: $ret, attributes: $attributes);
    }

    protected function functionArgs(Bool $allowInit = false):\Std\PhaseArray
    {
      $args = new \Std\PhaseArray([]);
      if (!$this->check(TokenType::TokRightParen))
      {
        do
        {
          $this->ignoreNewlines();
          $isInit = false;
          if ($allowInit && $this->matches(new \Std\PhaseArray([TokenType::TokThis])))
          {
            $isInit = true;
            $this->consume(TokenType::TokDot, "Expect a '.' after 'this'.");
          }
          $name = $this->consume(TokenType::TokIdentifier, "Expect parameter name");
          $type = null;
          $expr = null;
          if ($this->matches(new \Std\PhaseArray([TokenType::TokColon])))
          {
            $type = $this->parseTypePath();
          }
          if ($this->matches(new \Std\PhaseArray([TokenType::TokEqual])))
          {
            $expr = $this->expression();
          }
          $args->push(new FunctionArg(name: $name, type: $type, expr: $expr, isInit: $isInit));
        }
        while ($this->matches(new \Std\PhaseArray([TokenType::TokComma])));
      }
      $this->ignoreNewlines();
      $this->consume(TokenType::TokRightParen, "Expect ')' after parameters");
      return $args;
    }

    protected function functionBody():Stmt
    {
      $start = $this->previous();
      $body = null;
      if ($this->matches(new \Std\PhaseArray([TokenType::TokRightBrace])))
      {
        return new Stmt(stmt: StmtDef::SBlock(new \Std\PhaseArray([])), pos: $start->pos);
      }
      if (!$this->check(TokenType::TokNewline) && !$this->check(TokenType::TokReturn))
      {
        $body = new \Std\PhaseArray([new Stmt(stmt: StmtDef::SReturn(expression()), pos: $start->pos->merge($this->previous()->pos))]);
        $this->ignoreNewlines();
        $this->consume(TokenType::TokRightBrace, "Inline functions must contain only one expression.");
      }
      else
      {
        $body = $this->block();
      }
      return new Stmt(stmt: StmtDef::SBlock($body), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function functionDeclaration(\Std\PhaseArray $attributes):Stmt
    {
      $start = $this->previous();
      $def = $this->functionDecl(false, $attributes);
      $this->ignoreNewlines();
      return new Stmt(stmt: StmtDef::SFunction($def), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function classDeclaration(\Std\PhaseArray $attributes):Stmt
    {
      $start = $this->previous();
      $name = $this->consume(TokenType::TokTypeIdentifier, "Expect a class name. Must start with an uppercase letter.");
      $superclass = null;
      $interfaces = new \Std\PhaseArray([]);
      $fields = new \Std\PhaseArray([]);
      $this->ignoreNewlines();
      while ($this->matches(new \Std\PhaseArray([TokenType::TokExtends, TokenType::TokImplements])) && !$this->isAtEnd())
      {
        $op = $this->previous();
        if ($op == TokenType::TokExtends)
        {
          if ($superclass != null)
          {
            throw $this->error($op, "Can only extend once");
          }
          $superclass = $this->parseTypePath(false);
        }
        else
        {
          $interfaces->push($this->parseTypePath(false));
        }
        $this->ignoreNewlines();
      }
      $this->consume(TokenType::TokLeftBrace, "Expect '{' before class body.");
      $this->ignoreNewlines();
      while (!$this->check(TokenType::TokRightBrace) && !$this->isAtEnd())
      {
        $this->ignoreNewlines();
        $field = $this->fieldDeclaration();
        $kind = $field->kind;
        $__matcher_2 = $kind;
        if ($__matcher_2->tag == "FFun") { 
          $func = $__matcher_2->params[0];
          foreach ($func->args as $a)
          {
            if ($a->isInit == true && !(isset($fields[$a->name])))
            {
              $fields->push(new Field(name: $a->name, kind: FieldKind::FVar(name: $a->name, type: $a->type, initializer: null), type: $a->type, access: new \Std\PhaseArray([FieldAccess::APublic]), attributes: new \Std\PhaseArray([])));
            }
          }
        }
        else {
          null;
        };
        $fields->push($field);
      }
      $this->ignoreNewlines();
      $this->consume(TokenType::TokRightBrace, "Expect '}' at end of class body");
      $this->ignoreNewlines();
      return new Stmt(stmt: StmtDef::SClass(new ClassDecl(name: $name->lexeme, kind: ClassKind::KindClass, superclass: $superclass, interfaces: $interfaces, fields: $fields, attributes: $attributes)), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function interfaceDeclaration(\Std\PhaseArray $attributes):Stmt
    {
      $start = $this->previous();
      $name = $this->consume(TokenType::TokTypeIdentifier, "Expect a class name. Must start uppercase.");
      $interfaces = new \Std\PhaseArray([]);
      $fields = new \Std\PhaseArray([]);
      $this->ignoreNewlines();
      while ($this->matches(new \Std\PhaseArray([TokenType::TokExtends])) && !$this->isAtEnd())
      {
        $interfaces->push($this->parseTypePath(false));
        $this->ignoreNewlines();
      }
      $this->consume(TokenType::TokLeftBrace, "Expect '{' before interface body.");
      $this->ignoreNewlines();
      while (!$this->check(TokenType::TokRightBrace) && !$this->isAtEnd())
      {
        $this->ignoreNewlines();
        $field = $this->fieldDeclaration();
        if (!$field->access->contains(FieldAccess::AAbstract))
        {
          $field->access->push(FieldAccess::AAbstract);
        }
        $fields->push($field);
      }
      $this->ignoreNewlines();
      $this->consume(TokenType::TokRightBrace, "Expect '}' at end of interface body");
      $this->ignoreNewlines();
      return new Stmt(stmt: StmtDef::SClass(new ClassDecl(name: $name->lexeme, kind: ClassKind::KindInterface, superclass: null, interfaces: $interfaces, fields: $fields, attributes: $attributes)), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function traitDeclaration(\Std\PhaseArray $attributes):Stmt
    {
      $start = $this->previous();
      $name = $this->consume(TokenType::TokTypeIdentifier, "Expect a trait name. Must start uppercase.");
      $fields = new \Std\PhaseArray([]);
      $this->consume(TokenType::TokLeftBrace, "Expect '{' before trait body.");
      $this->ignoreNewlines();
      while (!$this->check(TokenType::TokRightBrace) && !$this->isAtEnd())
      {
        $this->ignoreNewlines();
        $fields->push($this->fieldDeclaration());
      }
      $this->ignoreNewlines();
      $this->consume(TokenType::TokRightBrace, "Expect '}' at end of trait body");
      $this->ignoreNewlines();
      return new Stmt(stmt: StmtDef::SClass(new ClassDecl(name: $name->lexeme, kind: ClassKind::KindTrait, superclass: null, interfaces: new \Std\PhaseArray([]), fields: $fields, attributes: $attributes)), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function enumDeclaration(\Std\PhaseArray $attribute):Stmt
    {
      $start = $this->previous();
      $enumName = $this->consume(TokenType::TokTypeIdentifier, "Expect an enum name. Must start uppercase.");
      $fields = new \Std\PhaseArray([]);
      if ($this->matches(new \Std\PhaseArray([TokenType::TokAs])))
      {
        $superClass = $this->consume(TokenType::TokTypeIdentifier, "Expect a wrapped type name");
        $this->consume(TokenType::TokLeftBrace, "Expect '{' before enum body.");
        $this->ignoreNewlines();
        $index = 0;
        while (!$this->check(TokenType::TokRightBrace) && !$this->isAtEnd())
        {
          $this->ignoreNewlines();
          $fieldName = $this->consume(TokenType::TokTypeIdentifier, "Expect an uppercase identifier");
          $value = null;
          if ($this->matches(new \Std\PhaseArray([TokenType::TokEqual])))
          {
            $value = $this->expression();
          }
          else
          {
            switch ($superClass->lexeme)
            {
              case "String":
                $value = new Expr(expr: ExprDef::ELiteral(new LString($fieldName->lexeme)), pos: $fieldName->pos);
                break;
              case "Int":
                $value = new Expr(expr: ExprDef::ELiteral(new LNumber($index)), pos: $fieldName->pos);
                break;
              case _:
                throw $this->error($superClass, "Unknown type -- currently enums may only be Strings or Ints");
                break;
            }
          }
          $index++;
          $this->expectEndOfStatement();
          $fields->push(new Field(name: $fieldName->lexeme, type: null, kind: FieldKind::FVar($fieldName->lexeme, null, $value, null), access: new \Std\PhaseArray([FieldAccess::AConst]), attributes: new \Std\PhaseArray([])));
        }
        $this->ignoreNewlines();
        $this->consume(TokenType::TokRightBrace, "Expect '}' at end of enum body");
        $this->ignoreNewlines();
        return new Stmt(stmt: StmtDef::SClass(new ClassDecl(name: $enumName->lexeme, kind: ClassKind::KindClass, superclass: null, interfaces: new \Std\PhaseArray([]), fields: $fields, attributes: attributes)), pos: $start->pos->merge($this->previous()->pos));
      }
      $this->consume(TokenType::TokLeftBrace, "Expect '{' before enum body.");
      $this->ignoreNewlines();
      $index = 0;
      while (!$this->check(TokenType::TokRightBrace) && !$this->isAtEnd())
      {
        $this->ignoreNewlines();
        $name = $this->consume(TokenType::TokTypeIdentifier, "Expect an uppercase identifier");
        $params = new \Std\PhaseArray([]);
        $ret = new TypePath(ns: new \Std\PhaseArray([]), name: $enumName->lexeme, params: new \Std\PhaseArray([]), isAbsolute: false, isNullable: false);
        if (!$this->check(TokenType::TokNewline))
        {
          $this->consume(TokenType::TokLeftParen, "Expect '(' after function name.");
          $params = $this->functionArgs(false);
        }
        $body = CodeGenerator::generateStmt("{ return " . ($enumName->lexeme) . "(
        " . ($index++) . ",
        \"" . ($name->lexeme) . "\",
        [ " . ($params->map(function ($it = null)
        {
          return $it->name->lexeme;
        })->join(", ")) . " ]
      ) }", $enumName->pos, reporter);
        $this->expectEndOfStatement();
        $fields->push(new Field(name: $name->lexeme, type: $ret, kind: FieldKind::FFun(new FunctionDecl($name, $params, $body, $ret, new \Std\PhaseArray([]))), access: new \Std\PhaseArray([FieldAccess::APublic, FieldAccess::AStatic]), attributes: new \Std\PhaseArray([])));
      }
      $this->ignoreNewlines();
      $this->consume(TokenType::TokRightBrace, "Expect '}' at end of enum body");
      $this->ignoreNewlines();
      return new Stmt(stmt: StmtDef::SClass(new ClassDecl(name: $enumName->lexeme, kind: ClassKind::KindClass, superclass: new TypePath(ns: new \Std\PhaseArray(["Std"]), name: "PhaseEnum", params: new \Std\PhaseArray([]), isAbsolute: true, isNullable: false), interfaces: new \Std\PhaseArray([]), fields: $fields, attributes: attributes)), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function fieldDeclaration():Field
    {
      if ($this->matches(new \Std\PhaseArray([TokenType::TokUse])))
      {
        $out = new Field(name: "", kind: FieldKind::FUse(path: $this->parseTypePath(false)), access: new \Std\PhaseArray([]), attributes: new \Std\PhaseArray([]));
        $this->expectEndOfStatement();
        return $out;
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokConst])))
      {
        $name = $this->consume(TokenType::TokTypeIdentifier, "Expect uppercase identifier");
        $type = null;
        if ($this->matches(new \Std\PhaseArray([TokenType::TokColon])))
        {
          $type = $this->parseTypePath();
        }
        $this->consume(TokenType::TokEqual, "Expect assignment for consts");
        $this->ignoreNewlines();
        $value = $this->expression();
        $out = new Field(name: $name->lexeme, kind: FieldKind::FVar(name: $name->lexeme, type: $type, initializer: $value), access: new \Std\PhaseArray([AConst]), attributes: new \Std\PhaseArray([]));
        $this->expectEndOfStatement();
        return $out;
      }
      $access = new \Std\PhaseArray([]);
      $attributes = new \Std\PhaseArray([]);
      $addAccess = function ($it = null) use ($access)
      {
        if ($access->contains($it))
        {
          throw $this->error($this->previous(), "Only one " . ($it) . " declaration is allowed per field");
        }
        $access->push($it);
      };
      if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBracket])))
      {
        $attributes = $this->attributeList();
      }
      while ($this->matches(new \Std\PhaseArray([TokenType::TokStatic, TokenType::TokPublic, TokenType::TokPrivate, TokenType::TokAbstract])) && !$this->isAtEnd())
      {
        switch ($this->previous()->type)
        {
          case TokenType::TokStatic:
            $addAccess(FieldAccess::AStatic);
            break;
          case TokenType::TokPrivate:
            $addAccess(FieldAccess::APrivate);
            break;
          case TokenType::TokPublic:
            $addAccess(FieldAccess::APublic);
            break;
          case TokenType::TokAbstract:
            $addAccess(FieldAccess::AAbstract);
            break;
          default:

            break;
        }
      }
      if ($access->length == 0 || (!$access->contains(FieldAccess::APublic) && !$access->contains(FieldAccess::APrivate)))
      {
        $access->push(FieldAccess::APublic);
      }
      $name = $this->consume(TokenType::TokIdentifier, "Expected an identifier");
      $type = null;
      if ($this->matches(new \Std\PhaseArray([TokenType::TokColon])))
      {
        $type = $this->parseTypePath();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokNewline])))
      {
        $this->ignoreNewlines();
        return new Field(name: $name->lexeme, type: $type, access: $access, kind: FieldKind::FVar(name: $name->lexeme, type: $type, initializer: null), attributes: $attributes);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBrace])))
      {
        $getter = null;
        $setter = null;
        while (!$this->check(TokenType::TokRightBrace) && !$this->isAtEnd())
        {
          $this->ignoreNewlines();
          $mode = $this->consume(TokenType::TokIdentifier, "Expect an identifier");
          switch ($mode->lexeme)
          {
            case "get":
              if ($getter != null)
              {
                throw $this->error($mode, "`get` already defined");
              }
              $this->consume(TokenType::TokLeftBrace, "Expect a '{'");
              $body = $this->functionBody();
              $this->expectEndOfStatement();
              $getter = new FunctionDecl(name: $mode->lexeme, args: new \Std\PhaseArray([]), body: $body, ret: $type, attributes: new \Std\PhaseArray([]));
              break;
            case "set":
              if ($setter != null)
              {
                throw $this->error($mode, "`set` already defined");
              }
              $this->consume(TokenType::TokLeftBrace, "Expect a '{'");
              $body = $this->functionBody();
              $this->expectEndOfStatement();
              $setter = new FunctionDecl(name: $mode->lexeme, args: new \Std\PhaseArray([new FunctionArg(name: "value", type: $type, expr: null)]), body: $body, ret: $type, attributes: new \Std\PhaseArray([]));
              break;
            default:
              throw $this->error($mode, "Expected `get` or `set`");
              break;
          }
        }
        $this->ignoreNewlines();
        $this->consume(TokenType::TokRightBrace, "Expected a `}`");
        return new Field(name: $name->lexeme, type: $type, access: $access, kind: FieldKind::FProp(getter: $getter, setter: $setter, type: $type), attributes: $attributes);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokEqual])))
      {
        if ($access->contains(FieldAccess::AAbstract))
        {
          throw $this->error($this->previous(), "No assignment allowed");
        }
        $this->ignoreNewlines();
        $expr = $this->expression();
        $this->expectEndOfStatement();
        return new Field(name: $name->lexeme, type: $type, access: $access, kind: FieldKind::FVar(name: $name->lexeme, type: $type, initializer: $expr), attributes: $attributes);
      }
      $this->consume(TokenType::TokLeftParen, "Expect '(' after function name.");
      $args = $this->functionArgs($name->lexeme == "new");
      $type = null;
      $body = null;
      if ($this->matches(new \Std\PhaseArray([TokenType::TokColon])))
      {
        $type = $this->parseTypePath();
      }
      if ($access->contains(FieldAccess::AAbstract))
      {
        $this->expectEndOfStatement();
      }
      else
      {
        $this->consume(TokenType::TokLeftBrace, "Expect '{' before function body");
        $body = $this->functionBody();
        $this->expectEndOfStatement();
      }
      return new Field(name: $name->lexeme, access: $access, type: $type, kind: FieldKind::FFun(new FunctionDecl(name: $name->lexeme, args: $args, body: $body, ret: $type, attributes: new \Std\PhaseArray([]))), attributes: $attributes);
    }

    protected function attributeList():\Std\PhaseArray
    {
      $attributes = new \Std\PhaseArray([]);
      $start = $this->previous();
      do
      {
        $path = $this->parseList(TokenType::TokScopeResolutionOperator, function ($it = null)
        {
          return $this->consume(TokenType::TokTypeIdentifier, "Expect a package name seperated by '::'");
        });
        $args = new \Std\PhaseArray([]);
        if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftParen])))
        {
          if (!$this->matches(new \Std\PhaseArray([TokenType::TokRightParen])))
          {
            $args = $this->parseArguments();
          }
          $this->ignoreNewlines();
          $this->consume(TokenType::TokRightParen, "Expect ')' at the end of an attribute");
        }
        $this->ignoreNewlines();
        $attributes->push(new Expr(expr: ExprDef::EAttribute(path: $path, args: $args), pos: $start->pos->merge($this->previous()->pos)));
      }
      while ($this->matches(new \Std\PhaseArray([TokenType::TokComma])));
      $this->consume(TokenType::TokRightBracket, "Expect a ']' at the end of an attribute");
      $this->ignoreNewlines();
      if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBracket])))
      {
        $attributes = $attributes->concat(attributeList());
      }
      return $attributes;
    }

    protected function expressionStatement()
    {
      $expr = $this->expression();
      $this->expressionStatement();
      return new Stmt(stmt: StmtDef::SExpr($expr), pos: $expr->pos);
    }

    protected function block():\Std\PhaseArray
    {
      $statements = new \Std\PhaseArray([]);
      $this->ignoreNewlines();
      while (!$this->check(TokenType::TokRightBrace) && !$this->isAtEnd())
      {
        $statements->push($this->declaration());
      }
      $this->ignoreNewlines();
      $this->consume(TokenType::TokRightBrace, "Expect '}' at the end of a block.");
      return $statements;
    }

    protected function blockStatement():Stmt
    {
      $start = $this->previous();
      $statements = $this->block();
      $this->ignoreNewlines();
      return new Stmt(stmt: StmtDef::SBlock($statements), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function ifStatement():Stmt
    {
      $start = $this->previous();
      $this->consume(TokenType::TokLeftParen, "Expect '(' after 'if'.");
      $this->ignoreNewlines();
      $condition = $this->expression();
      $this->ignoreNewlines();
      $this->consume(TokenType::TokRightParen, "Expect ')' after if condition.");
      $thenBranch = $this->statement();
      $def = $thenBranch->def;
      $__matcher_3 = $def;
      if ($__matcher_3->tag == "SBlock") { 
        $_ = $__matcher_3->params[0];
        null;
      }
      else {
        $thenBranch = new Stmt(stmt: StmtDef::SBlock($thenBranch), pos: $thenBranch->pos);
      };
      $elseBranch = null;
      if ($this->matches(new \Std\PhaseArray([TokenType::TokElse])))
      {
        $elseBranch = $this->statement();
        $def = $elseBranch->def;
        $__matcher_4 = $def;
        if ($__matcher_4->tag == "SBlock") { 
          $_ = $__matcher_4->params[0];
          null;
        }
        else {
          $elseBranch = new Stmt(stmt: StmtDef::SBlock($elseBranch), pos: $elseBranch->pos);
        };
      }
      return new Stmt(stmt: StmtDef::SIf($condition, $thenBranch, $elseBranch), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function whileStatement():Stmt
    {
      $start = $this->previous();
      $this->consume(TokenType::TokLeftParen, "Expect '(' after 'while'.");
      $this->ignoreNewlines();
      $condition = $this->expression();
      $this->ignoreNewlines();
      $this->consume(TokenType::TokRightParen, "Expect ')' after 'while' condition.");
      $body = $this->statement();
      return new Stmt(stmt: StmtDef::SWhile(condition: $condition, body: $body, inverted: false), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function doStatement():Stmt
    {
      $start = $this->previous();
      $body = $this->statement();
      $this->consume(TokenType::TokWhile, "Expect 'while' after a 'do' statment.");
      $this->consume(TokenType::TokLeftParen, "Expect '(' after 'while'.");
      $condition = $this->expression();
      $this->consume(TokenType::TokRightParen, "Expect ')' after 'while' condition.");
      $this->expectEndOfStatement();
      return new Stmt(stmt: StmtDef::SWhile(condition: $condition, body: $body, inverted: true), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function forStatement():Stmt
    {
      $start = $this->previous();
      $this->consume(TokenType::TokLeftParen, "Expect '(' after 'for'.");
      $key = $this->consume(TokenType::TokIdentifier, "Expect an identifier")->lexeme;
      $value = null;
      if ($this->check(TokenType::TokColon))
      {
        $this->advance();
        $value = $this->consume(TokenType::TokIdentifier, "Expect an identifier after a colon")->lexeme;
      }
      $this->consume(TokenType::TokIn, "Expect `in` after destructuring");
      $target = $this->expression();
      $this->consume(TokenType::TokRightParen, "Expect ')'");
      $body = $this->statement();
      return new Stmt(stmt: StmtDef::SFor(key: $key, value: $value, target: $target, body: $body), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function switchStatement():Stmt
    {
      $start = $this->previous();
      $this->consume(TokenType::TokLeftParen, "Expect '(' after 'switch'.");
      $this->ignoreNewlines();
      $target = $this->expression();
      $this->ignoreNewlines();
      $this->consume(TokenType::TokRightParen, "Expect ')' after switch target");
      $this->ignoreNewlines();
      $this->consume(TokenType::TokLeftBrace, "Expect '{'");
      $this->ignoreNewlines();
      $cases = new \Std\PhaseArray([]);
      while (!$this->isAtEnd() && $this->matches(new \Std\PhaseArray([TokenType::TokCase, TokenType::TokDefault])))
      {
        $this->ignoreNewlines();
        $condition = null;
        $isDefault = false;
        if ($this->previous()->type == TokenType::TokDefault)
        {
          $isDefault = true;
        }
        else
        {
          $condition = $this->expression();
        }
        $this->consume(TokenType::TokColon, "Expect a ':' after case condition");
        $this->ignoreNewlines();
        $body = new \Std\PhaseArray([]);
        while (!$this->isAtEnd() && !$this->check(TokenType::TokCase) && !$this->check(TokenType::TokDefault) && !$this->check(TokenType::TokRightBrace))
        {
          $body->push($this->statement());
        }
        $cases->push(new MatchCase(condition: $condition, body: $body, isDefault: $isDefault));
      }
      $this->ignoreNewlines();
      $this->consume(TokenType::TokRightBrace, "Expect a '}' at the end of a switch statement");
      $this->ignoreNewlines();
      return new Stmt(stmt: StmtDef::Switch($target, $cases), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function throwStatement():Stmt
    {
      $start = $this->previous();
      $value = $this->expression();
      $this->expectEndOfStatement();
      return new Stmt(stmt: StmtDef::SThrow($value), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function tryStatement():Stmt
    {
      $start = $this->previous();
      $this->ignoreNewlines();
      $this->consume(TokenType::TokLeftBrace, "Expect '{' after 'try'");
      $this->ignoreNewlines();
      $body = blockStatement();
      $catches = new \Std\PhaseArray([]);
      $this->ignoreNewlines();
      while ($this->matches(new \Std\PhaseArray([TokenType::TokCatch])) && !$this->isAtEnd())
      {
        $start = $this->previous();
        $this->consume(TokenType::TokLeftParen, "Expect '('");
        $name = $this->consume(TokenType::TokIdentifier, "Expect an identifier");
        $type = null;
        if ($this->matches(new \Std\PhaseArray([TokenType::TokColon])))
        {
          $type = $this->parseTypePath(false);
        }
        $this->consume(TokenType::TokRightParen, "Expect ')'");
        $this->consume(TokenType::TokLeftBrace, "Expect '{'");
        $this->ignoreNewlines();
        $body = $this->blockStatement();
        $catches->push(new Stmt(stmt: StmtDef::SCatch(name: $name, type: $type, body: $body), pos: $start->pos->merge($this->previous()->pos)));
      }
      return new Stmt(stmt: StmtDef::STry($body, $catches), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function returnStatement():Stmt
    {
      $start = $this->previous();
      $value = null;
      if (!$this->check(TokenType::TokSemicolon) && !$this->check(TokenType::TokNewline))
      {
        $value = $this->expression();
      }
      $this->expectEndOfStatement();
      return new Stmt(stmt: StmtDef::SReturn($value), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function matchExpr():Expr
    {
      $start = $this->previous();
      $this->consume(TokenType::TokLeftParen, "Expect '(' after 'match'.");
      $this->ignoreNewlines();
      $target = $this->expression();
      $this->ignoreNewlines();
      $this->consume(TokenType::TokRightParen, "Expect ')' after match target");
      $this->ignoreNewlines();
      $this->consume(TokenType::TokLeftBrace, "Expect '{'");
      $this->ignoreNewlines();
      $cases = new \Std\PhaseArray([]);
      while (!$this->isAtEnd() && !$this->check(TokenType::TokRightBrace))
      {
        $this->ignoreNewlines();
        $isDefault = false;
        $condition = null;
        if ($this->matches(new \Std\PhaseArray([TokDefault])))
        {
          $isDefault = true;
        }
        else
        {
          $condition = $this->expression();
        }
        $this->consume(TokenType::TokArrow, "Expect a -> after matches");
        $this->ignoreNewlines();
        $body = $this->statement();
        $cases->push(new MatchCase(condition: $condition, body: new \Std\PhaseArray([$body]), isDefault: $isDefault));
      }
      $this->ignoreNewlines();
      $this->consume(TokenType::TokRightBrace, "Expect a '}' at the end of a match statement");
      return new Expr(expr: ExprDef::EMatch($target, $cases), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function expression():Expr
    {
      return $this->assignment();
    }

    protected function assignment():Expr
    {
      $expr = $this->or();
      if ($this->matches(new \Std\PhaseArray([TokenType::TokEqual])))
      {
        $this->ignoreNewlines();
        $value = $this->assignment();
        $def = $expr->expr;
        $__matcher_5 = $def;
        if ($__matcher_5->tag == "EVariable") { 
          $name = $__matcher_5->params[0];
          return new Expr(expr: ExprDef::EAssign($name, $value), pos: $expr->pos->merge($value->pos));
        }
        else if ($__matcher_5->tag == "EGet") { 
          $target = $__matcher_5->params[0];
          $field = $__matcher_5->params[1];
          return new Expr(expr: ExprDef::ESet($target, $field, $value), pos: $expr->pos->merge($value->pos));
        }
        else if ($__matcher_5->tag == "EArrayGet") { 
          $target = $__matcher_5->params[0];
          $field = $__matcher_5->params[1];
          return new Expr(expr: ExprDef::EArraySet($target, $field, $value), pos: $expr->pos->merge($value->pos));
        }
        else {
          throw $this->error($this->previous(), "Invalid assignment target");
        };
      }
      return $expr;
    }

    protected function or():Expr
    {
      $expr = $this->and();
      while ($this->matches(new \Std\PhaseArray([TokenType::TokBoolOr])))
      {
        $op = $this->previous();
        $this->ignoreNewlines();
        $right = $this->and();
        $expr = new Expr(expr: ExprDef::ELogical($expr, $op->lexeme, $right), pos: $expr->pos->merge($right->pos));
      }
      return $expr;
    }

    protected function and():Expr
    {
      $expr = $this->equality();
      while ($this->matches(new \Std\PhaseArray([TokenType::TokBoolAnd])))
      {
        $op = $this->previous();
        $this->ignoreNewlines();
        $right = $this->equality();
        $expr = new Expr(expr: ExprDef::ELogical($expr, $op->lexeme, $right), pos: $expr->pos->merge($right->pos));
      }
      return $expr;
    }

    protected function equality():Expr
    {
      $expr = $this->comparison();
      while ($this->matches(new \Std\PhaseArray([TokenType::TokBangEqual, TokenType::TokBoolEqual])))
      {
        $op = $this->previous();
        $this->ignoreNewlines();
        $right = $this->comparison();
        $expr = new Expr(expr: ExprDef::EBinary($expr, $op->lexeme, $right), pos: $expr->pos->merge($right->pos));
      }
      return $expr;
    }

    protected function comparison():Expr
    {
      $expr = $this->addition();
      if ($this->matches(new \Std\PhaseArray([TokenType::TokIs])))
      {
        $type = $this->parseTypePath();
        $pos = $expr->pos->merge($this->previous()->pos);
        $this->ignoreNewlines();
        return new Expr(expr: ExprDef::EIs($expr, $type), pos: $pos);
      }
      while ($this->matches(new \Std\PhaseArray([TokenType::TokGreater, TokenType::TokGreaterEqual, TokenType::TokLess, TokenType::TokLessEqual])))
      {
        $op = $this->previous();
        $this->ignoreNewlines();
        $right = $this->addition();
        $expr = new Expr(expr: ExprDef::EBinary($expr, $op->lexeme, $right), pos: $expr->pos->merge($right->pos));
      }
      return $expr;
    }

    protected function addition():Expr
    {
      $expr = $this->multiplication();
      while ($this->matches(new \Std\PhaseArray([TokenType::TokMinus, TokenType::TokPlus])))
      {
        $op = $this->previous();
        $this->ignoreNewlines();
        $right = $this->multiplication();
        $expr = new Expr(expr: ExprDef::EBinary($expr, $op->lexeme, $right), pos: $expr->pos->merge($right->pos));
      }
      return $expr;
    }

    protected function multiplication():Expr
    {
      $expr = $this->range();
      while ($this->matches(new \Std\PhaseArray([TokenType::TokSlash, TokenType::TokStar])))
      {
        $op = $this->previous();
        $this->ignoreNewlines();
        $right = $this->range();
        $expr = new Expr(expr: ExprDef::EBinary($expr, $op->lexeme, $right), pos: $expr->pos->merge($right->pos));
      }
      return $expr;
    }

    protected function range():Expr
    {
      $expr = $this->pipe();
      while ($this->matches(new \Std\PhaseArray([TokenType::TokRange])))
      {
        $this->ignoreNewlines();
        $to = $this->pipe();
        $expr = new Expr(expr: ExprDef::ERange($expr, $to), pos: $expr->pos->merge($to->pos));
      }
      return $expr;
    }

    protected function pipe():Expr
    {
      $expr = $this->unary();
      while ($this->matches(new \Std\PhaseArray([TokenType::TokPipe])))
      {
        $op = $this->previous();
        $target = $this->unary();
        $def = $target->expr;
        $__matcher_6 = $def;
        if ($__matcher_6->tag == "ECall") { 
          $callee = $__matcher_6->params[0];
          $args = $__matcher_6->params[1];
          {
            $expr = new Expr(expr: ExprDef::ECall($callee, $args->concat(new \Std\PhaseArray([CallArgument::Positional($expr)]))), pos: $target->pos);
          }
        }
        else if ($__matcher_6->tag == "ELambda") { 
          $func = $__matcher_6->params[0];
          {
            $expr = new Expr(expr: ExprDef::ECall(new Expr(expr: ExprDef::EGrouping($target), pos: $target->pos), new \Std\PhaseArray([CallArgument::Positional($expr)])), pos: $target->pos);
          }
        }
        else {
          throw error($op, "Expected a function/method call or a lambda");
        };
      }
      return $expr;
    }

    protected function unary():Expr
    {
      if ($this->matches(new \Std\PhaseArray([TokenType::TokBang, TokenType::TokMinus, TokenType::TokPlusPlus, TokenType::TokMinusMinus])))
      {
        $op = $this->previous();
        $this->ignoreNewlines();
        $target = $this->unary();
        return new Expr(expr: ExprDef::EUnary($op->lexeme, $target, true), pos: $op->pos->merge($target->pos));
      }
      $expr = $this->call();
      if ($this->matches(new \Std\PhaseArray([TokenType::TokPlusPlus, TokenType::TokMinusMinus])))
      {
        $op = $this->previous();
        return new Expr(expr: ExprDef::EUnary($op->lexeme, $expr, false), pos: $expr->pos->merge($op->pos));
      }
      return $expr;
    }

    protected function call():Expr
    {
      $expr = $this->primary();
      while (!$this->isAtEnd())
      {
        $this->conditionalIgnoreNewlines();
        if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftParen])))
        {
          $expr = $this->finishCall($expr);
        }
        else
        {
          if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBrace])))
          {
            $arg = $this->shortLambda(!$this->check(TokenType::TokNewline));
            $expr = new Expr(expr: ExprDef::ECall($expr, new \Std\PhaseArray([CallArgument::Positional($arg)])), pos: $expr->pos->merge($arg->pos));
          }
          else
          {
            if ($this->matches(new \Std\PhaseArray([TokenType::TokDot])))
            {
              $this->ignoreNewlines();
              $name = null;
              if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBrace])))
              {
                $this->ignoreNewlines();
                $ret = $this->expression();
                $def = $ret->expr;
                $__matcher_7 = $def;
                if ($__matcher_7->tag == "EVariable") { 
                  $_ = $__matcher_7->params[0];
                  {
                    $ret = ExprDef::Expr(expr: new EGrouping($ret), pos: $ret->pos);
                  }
                }
                else {
                  null;
                };
                $this->ignoreNewlines();
                $this->consume(TokenType::TokRightBrace, "Expect a '}'");
                $name = $ret;
              }
              else
              {
                if ($this->matches(new \Std\PhaseArray([TokenType::TokTypeIdentifier, TokenType::TokClass])))
                {
                  $name = new Expr(expr: ExprDef::EVariable($this->previous()->lexeme), pos: $this->previous()->pos);
                }
                else
                {
                  $tok = $this->consume(TokenType::TokIdentifier, "Expect property name after '.'.");
                  $name = new Expr(expr: ExprDef::EVariable($tok->lexeme), pos: $tok->pos);
                }
              }
              $expr = new Expr(expr: ExprDef::EGet($expr, $name), pos: $expr->pos->merge($name->pos));
            }
            else
            {
              if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBracket])))
              {
                if ($this->matches(new \Std\PhaseArray([TokenType::TokRightBracket])))
                {
                  $expr = new Expr(expr: ExprDef::EArrayGet($expr, null), pos: $expr->pos->merge($this->previous()->pos));
                }
                else
                {
                  $this->ignoreNewlines();
                  $index = $this->expression();
                  $tok = $this->consume(TokRightBracket, "Expect ']' after expression");
                  $expr = new Expr(expr: ExprDef::EArrayGet($expr, $index), pos: $expr->pos->merge($tok->pos));
                }
              }
              else
              {
                break;
              }
            }
          }
        }
      }
      return $expr;
    }

    protected function ternary():Expr
    {
      $start = $this->previous();
      $this->consume(TokenType::TokLeftParen, "Expect '(' after 'if'.");
      $condition = $this->expression();
      $this->consume(TokenType::TokRightParen, "Expect ')' after if condition.");
      $this->ignoreNewlines();
      $thenBranch = $this->expression();
      $this->ignoreNewlines();
      $this->consume(TokenType::TokElse, "Expected an 'else' branch");
      $this->ignoreNewlines();
      $elseBranch = $this->expression();
      return new Expr(expr: ExprDef::ETernary($condition, $thenBranch, $elseBranch), pos: $start->pos->merge($this->previous->pos()));
    }

    protected function arrayOrMapLiteral(Bool $isNative = false):Expr
    {
      $this->ignoreNewlines();
      if ($this->checkNext(TokenType::TokColon))
      {
        return $this->mapLiteral($isNative);
      }
      return $this->arrayLiteral($isNative);
    }

    protected function arrayLiteral(Bool $isNative):Expr
    {
      $start = $this->previous();
      $rewindPoint = $this->current;
      if (!$this->check(TokenType::TokRightBracket))
      {
        $values = $this->parseList(TokenType::TokComma, function ($it = null)
        {
          return $this->expression();
        });
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokColon])))
      {
        $this->current = $rewindPoint;
        return $this->mapLiteral($isNative);
      }
      $this->ignoreNewlines();
      $end = $this->consume(TokenType::TokRightBracket, "Expect ']' after values");
      return new Expr(expr: ExprDef::EArrayLiteral(values: values, isNative: $isNative), pos: $start->pos->merge($end->pos));
    }

    protected function mapLiteral(Bool $isNative):Expr
    {
      $start = $this->previous();
      $keys = new \Std\PhaseArray([]);
      $values = new \Std\PhaseArray([]);
      if (!$this->check(TokenType::TokRightBracket))
      {
        do
        {
          $this->ignoreNewlines();
          $keys->push($this->expression());
          $this->consume(TokenType::TokColon, "Expect ':' after map keys");
          $this->ignoreNewlines();
          $values->push($this->expression());
        }
        while ($this->matches(new \Std\PhaseArray([TokenType::TokComma])));
        $this->ignoreNewlines();
      }
      $end = $this->consume(TokenType::TokRightBracket, "Expect ']' at the end of a mapliteral");
      return new Expr(expr: ExprDef::EMapLiteral(keys: $keys, values: $values, isNative: $isNative), pos: $start->pos->merge($end->pos));
    }

    protected function taggedTemplate(Expr $callee):Expr
    {
      $parts = new \Std\PhaseArray([]);
      $placeholders = new \Std\PhaseArray([]);
      if (!$this->check(TokenType::TokString))
      {
        do
        {
          if ($this->matches(new \Std\PhaseArray([TokenType::TokInterpolation])))
          {
            $parts->push(new Expr(expr: ExprDef::ELiteral(Literal::LString($this->previous()->literal)), pos: $this->previous()->pos));
          }
          else
          {
            $placeholders->push($this->expression());
          }
        }
        while (!$this->check(TokenType::TokString) && !$this->isAtEnd());
      }
      $parts->push($this->primary());
      $partsPos = $parts->length > 0 ? $parts[0]->pos->merge($parts[$parts->length - 1]->pos) : $this->previous()->pos;
      $placeholdersPos = $placeholders->length > 0 ? $placeholders[0]->pos->merge($placeholders[$placeholders->length - 1]->pos) : $this->previous()->pos;
      return new Expr(expr: ExprDef::ECall($callee, new \Std\PhaseArray([CallArgument::Positional(new Expr(expr: ExprDef::EArrayLiteral($parts, false), pos: $partsPos)), CallArgument::Positional(new Expr(expr: ExprDef::EArrayLiteral($placeholders, false), pos: $placeholdersPos))])), pos: $callee->pos->merge($this->previous()->pos));
    }

    protected function interpolation(Expr $expr):Expr
    {
      while (!$this->isAtEnd())
      {
        $next = null;
        if ($this->check(TokenType::TokString))
        {
          return new Expr(expr: ExprDef::EBinary($expr, "+", $this->primary()), pos: $expr->pos->merge($this->previous()->pos));
        }
        else
        {
          if ($this->matches(new \Std\PhaseArray([TokenType::TokInterpolation])))
          {
            $next = new Expr(expr: ExprDef::ELiteral(Literal::LString($this->previous()->literal)), pos: $this->previous()->pos);
          }
          else
          {
            $expr = $this->expression();
            $next = new Expr(expr: new EGrouping($expr), pos: $expr->pos);
          }
        }
        $expr = new Expr(expr: ExprDef::EBinary($expr, "+", $next), pos: $expr->pos->merge($next->pos));
      }
      throw $this->error($this->peek(), "Unexpected end of interpolated string");
    }

    protected function pathExpr()
    {
      $start = $this->peek();
      $path = $this->parseTypePath(false);
      return new Expr(expr: ExprDef::EPath($path), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function primary():Expr
    {
      if ($this->matches(new \Std\PhaseArray([TokenType::TokFalse])))
      {
        return new Expr(expr: ExprDef::ELiteral(Literal::LFalse()), pos: $this->previous()->pos);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokTrue])))
      {
        return new Expr(expr: ExprDef::ELiteral(Literal::LTrue()), pos: $this->previous()->pos);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokNull])))
      {
        return new Expr(expr: ExprDef::ELiteral(Literal::LNull()), pos: $this->previous()->pos);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokNumber])))
      {
        $literal = $this->previous();
        return new Expr(expr: ExprDef::ELiteral(Literal::LNumber($literal->literal)), pos: $literal->pos);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokString])))
      {
        $literal = $this->previous();
        return new Expr(expr: ExprDef::ELiteral(Literal::LString($literal->literal)), pos: $literal->pos);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokInterpolation])))
      {
        $literal = $this->previous();
        return $this->interpolation(new Expr(expr: ExprDef::ELiteral(Literal::LString($literal->literal)), pos: $literal->pos));
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokSuper])))
      {
        $keyword = $this->previous();
        $this->consume(TokenType::TokDot, "Expect '.' after 'super'.");
        $this->ignoreNewlines();
        $method = $this->consume(TokenType::TokIdentifier, "Expect superclass method name.");
        return new Expr(expr: ExprDef::ESuper($method->lexeme), pos: $keyword->pos->merge($method->pos));
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokThis])))
      {
        $keyword = $this->previous();
        return new Expr(expr: ExprDef::EThis(), pos: $keyword->pos);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokStatic])))
      {
        $keyword = $this->previous();
        return new Expr(expr: ExprDef::EStatic(), pos: $keyword->pos);
      }
      if ($this->check(TokenType::TokScopeResolutionOperator) || $this->check(TokenType::TokTypeIdentifier))
      {
        return $this->pathExpr();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokIdentifier])))
      {
        $variable = $this->previous();
        return new Expr(expr: ExprDef::EVariable($variable->lexeme), pos: $variable->pos);
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokTemplateTag])))
      {
        $variable = $this->previous();
        return $this->taggedTemplate(new Expr(expr: ExprDef::EVariable($variable->lexeme), pos: $variable->pos));
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftParen])))
      {
        $start = $this->previous();
        $this->ignoreNewlines();
        $expr = $this->expression();
        $this->ignoreNewlines();
        $end = $this->consume(TokenType::TokRightParen, "Expect ')' after expression.");
        return new Expr(expr: ExprDef::EGrouping($expr), pos: $start->pos->merge($end->pos));
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokDollar])))
      {
        if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBracket])))
        {
          return $this->arrayOrMapLiteral(true);
        }
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBracket])))
      {
        return $this->arrayOrMapLiteral();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokLeftBrace])))
      {
        return $this->shortLambda(!$this->check(TokenType::TokNewline));
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokFunction])))
      {
        $start = $this->previous();
        return new Expr(expr: ExprDef::ELambda($this->functionDecl(true)), pos: $start->pos->merge($this->previous()->pos));
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokIf])))
      {
        return $this->ternary();
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokMatch])))
      {
        return $this->matchExpr();
      }
      $tok = $this->peek();
      throw $this->error($tok, "Unexpected " . ($tok->type) . "");
    }

    protected function shortLambda(Bool $isInline = false):Expr
    {
      $this->ignoreNewlines();
      $start = $this->previous();
      $args = new \Std\PhaseArray([]);
      $body = new \Std\PhaseArray([]);
      if ($this->matches(new \Std\PhaseArray([TokenType::TokBar])))
      {
        if (!$this->check(TokenType::TokBar))
        {
          do
          {
            $name = $this->consume(TokenType::TokIdentifier, "Expect an argument name");
            $args->push(new FunctionArg(name: $name->lexeme, type: null, expr: null));
          }
          while ($this->matches(new \Std\PhaseArray([TokenType::TokComma])));
        }
        $this->consume(TokenType::TokBar, "Expect '|' after lambda arguments");
        $isInline = !$this->check(TokenType::TokNewline);
      }
      else
      {
        $args = new \Std\PhaseArray([new FunctionArg(name: "it", type: null, expr: new Expr(expr: new ELiteral(null), pos: previous()->pos))]);
      }
      if ($isInline && !$this->check(TokenType::TokReturn))
      {
        $expr = $this->expression();
        $body->push(new Stmt(stmt: StmtDef::SReturn($expr), pos: $expr->pos));
        $this->ignoreNewlines();
        $this->consume(TokenType::TokRightBrace, "Inline lambdas must contain only one expression.");
      }
      else
      {
        $body = $this->block();
      }
      return new Expr(expr: ExprDef::ELambda(new FunctionDecl(name: "", args: $args, body: new Stmt(stmt: StmtDef::SBlock($body), pos: $body->length > 0 ? $body[0]->pos->merge($this->previous()->pos) : $start->pos->merge($this->previous()->pos)), ret: null, attributes: new \Std\PhaseArray([]))), pos: $start->pos->merge($this->previous()->pos));
    }

    protected function expectEndOfStatement():Bool
    {
      if ($this->check(TokenType::TokRightBrace))
      {
        return true;
      }
      if ($this->matches(new \Std\PhaseArray([TokenType::TokNewline, TokenType::TokEof])))
      {
        $this->ignoreNewlines();
        return true;
      }
      $this->consume(TokenType::TokSemicolon, "Expect newline or semicolon after statement");
      $this->ignoreNewlines();
      return false;
    }

    protected function parseTypePath(Bool $allowNullable = true):?TypePath
    {
      $nullable = $this->matches(new \Std\PhaseArray([TokenType::TokQuestion]));
      if ($nullable && !$allowNullable)
      {
        throw $this->error($this->previous(), "Nullable types are not allowed here");
      }
      $absolute = $this->matches(new \Std\PhaseArray([TokenType::TokScopeResolutionOperator]));
      $parts = $this->parseList(TokenType::TokScopeResolutionOperator, function ($it = null)
      {
        return $this->consume(TokenType::TokTypeIdentifier, "Expect a package name seperated by '::'");
      });
      $name = $parts->pop();
      $params = new \Std\PhaseArray([]);
      if ($this->matches(new \Std\PhaseArray([TokenType::TokLess])))
      {
        $params = $this->parseList(TokenType::TokComma, function ($it = null)
        {
          return $this->parseTypePath();
        });
        $this->consume(new \Std\PhaseArray([TokenType::TokGreater]), "Expect a '>' at the end of a type parameter list");
      }
      return new TypePath(ns: $parts->map(function ($it = null)
      {
        return $it->lexeme;
      }), name: $name->lexeme, params: $params, isAbsolute: $absolute, isNullable: $nullable);
    }

    protected function parseList(string $sep, $parser):\Std\PhaseArray
    {
      $items = new \Std\PhaseArray([]);
      do
      {
        $this->ignoreNewlines();
        $items->push($parser());
      }
      while ($this->matches(new \Std\PhaseArray([$sep])) && !$this->isAtEnd());
      return $items;
    }

    protected function ignoreNewlines()
    {
      while (!$this->isAtEnd())
      {
        if (!$this->matches(new \Std\PhaseArray([TokenType::TokNewline])))
        {
          return;
        }
      }
    }

    protected function conditionalIgnoreNewlines()
    {
      if ($this->check(TokenType::TokNewline))
      {
        while (!$this->isAtEnd())
        {
          if ($this->checkNext(TokenType::TokNewline))
          {
            $this->advance();
          }
          foreach (static::$continuationTokens as $token)
          {
            if ($this->checkNext($token))
            {
              $this->advance();
              return;
            }
          }
          if (!$this->checkNext(TokenType::TokNewline))
          {
            return;
          }
        }
      }
    }

    protected function matches(\Std\PhaseArray $types):Bool
    {
      foreach ($types as $type)
      {
        if ($this->check($type))
        {
          $this->advance();
          return true;
        }
      }
      return false;
    }

    protected function consume(string $type, string $message):Token
    {
      if ($this->check($type))
      {
        return $this->advance();
      }
      throw $this->error($this->peek(), $message);
    }

    protected function check(string $type):Bool
    {
      if ($this->isAtEnd())
      {
        return false;
      }
      return $this->peek()->type == $type;
    }

    protected function checkNext(string $type):Bool
    {
      if ($this->isAtEnd())
      {
        return false;
      }
      return $this->peekNext()->type == $type;
    }

    protected function advance():?Token
    {
      if (!$this->isAtEnd())
      {
        return $this->tokens[$this->current++];
      }
      return null;
    }

    protected function peek():?Token
    {
      return $this->tokens[$this->current];
    }

    protected function peekNext():?Token
    {
      return $this->tokens[$this->current + 1];
    }

    protected function previous():?Token
    {
      return $this->tokens[$this->current - 1];
    }

    protected function isAtEnd():Bool
    {
      return $this->peek()->type == TokenType::TokEof;
    }

    protected function error(Token $token, string $message)
    {
      $this->reporter->report($token->pos, $message);
      return new ParserException();
    }

  }
  Parser::$continuationTokens = new \Std\PhaseArray([TokenType::TokDot, TokenType::TokPlus, TokenType::TokConcat, TokenType::TokPipe, TokenType::TokMinus, TokenType::TokBoolEqual, TokenType::TokBangEqual, TokenType::TokBoolAnd, TokenType::TokBoolOr]);

}