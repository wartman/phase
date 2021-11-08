<?php
namespace Phase\Generator {

  use Std\StringBuf;
  use Phase\ErrorReporter;
  use Phase\Generator;
  use Phase\Context;
  use Phase\CodeGenerator;
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
  use Phase\Language\Type;

  class PhpGenerator implements Generator
  {

    public function __construct(PhpGeneratorConfig $config, Context $context, \Std\PhaseArray $statements, ErrorReporter $reporter)
    {
      $this->reporter = $reporter;
      $this->statements = $statements;
      $this->context = $context;
      $this->config = $config;
      $this->mode = PhpGeneratorMode::GeneratingRoot;
      $this->uid = 0;
      $this->indentLevel = 0;
    }

    static public \Std\PhaseMap $phaseToPhpTypes;

    protected Context $context;

    protected PhpGeneratorConfig $config;

    protected \Std\PhaseArray $statements;

    protected ErrorReporter $reporter;

    protected int $indentLevel;

    protected int $uid;

    protected string $mode;

    public function generate():string
    {
      $out = new StringBuf();
      $out->add("<?php\n");
      foreach ($this->statements as $stmt)
      {
        $out->add($this->generateStmt($stmt));
      }
      return $out->toString();
    }

    protected function generateStmt(?Stmt $stmt):?string
    {
      if ($stmt === null)
      {
        return "";
      }
      $def = $stmt->stmt;
      $__matcher_1 = $def;
      if ($__matcher_1->tag === "SNamespace") { 
        $path = $__matcher_1->params[0];
        $decls = $__matcher_1->params[1];
        $attrs = $__matcher_1->params[2];
        return $this->generateNamespace($path, $decls, $attrs);
      }
      else if ($__matcher_1->tag === "SUse") { 
        $path = $__matcher_1->params[0];
        $kind = $__matcher_1->params[1];
        $attrs = $__matcher_1->params[2];
        return null;
      }
      else if ($__matcher_1->tag === "SVar") { 
        $name = $__matcher_1->params[0];
        $type = $__matcher_1->params[1];
        $init = $__matcher_1->params[2];
        return $this->generateVar($name, $type, $init);
      }
      else if ($__matcher_1->tag === "SGlobal") { 
        $name = $__matcher_1->params[0];
        return $this->stmtOutput()->add("global $")->add($this->safeVar($name))->add(";")->toString();
      }
      else if ($__matcher_1->tag === "SThrow") { 
        $expr = $__matcher_1->params[0];
        return $this->stmtOutput()->add("throw ")->add($this->generateExpr($expr))->add(";")->toString();
      }
      else if ($__matcher_1->tag === "STry") { 
        $body = $__matcher_1->params[0];
        $catches = $__matcher_1->params[1];
        return $this->generateTry($body, $catches);
      }
      else if ($__matcher_1->tag === "SCatch") { 
        $name = $__matcher_1->params[0];
        $type = $__matcher_1->params[1];
        $body = $__matcher_1->params[2];
        return $this->generateCatch($name, $type, $body);
      }
      else if ($__matcher_1->tag === "SWhile") { 
        $condition = $__matcher_1->params[0];
        $body = $__matcher_1->params[1];
        $inverted = $__matcher_1->params[2];
        return $this->generateWhile($condition, $body, $inverted);
      }
      else if ($__matcher_1->tag === "SFor") { 
        $key = $__matcher_1->params[0];
        $value = $__matcher_1->params[1];
        $target = $__matcher_1->params[2];
        $body = $__matcher_1->params[3];
        return $this->generateFor($key, $value, $target, $body);
      }
      else if ($__matcher_1->tag === "SIf") { 
        $condition = $__matcher_1->params[0];
        $thenBranch = $__matcher_1->params[1];
        $elseBranch = $__matcher_1->params[2];
        return $this->generateIf($condition, $thenBranch, $elseBranch);
      }
      else if ($__matcher_1->tag === "SSwitch") { 
        $target = $__matcher_1->params[0];
        $cases = $__matcher_1->params[1];
        return $this->generateSwitch($target, $cases);
      }
      else if ($__matcher_1->tag === "SBlock") { 
        $stmts = $__matcher_1->params[0];
        return $this->getIndent() . $this->generateBlock($stmts);
      }
      else if ($__matcher_1->tag === "SReturn") { 
        $value = $__matcher_1->params[0];
        return $this->stmtOutput()->add("return ")->add($this->generateExpr($value))->add(";")->toString();
      }
      else if ($__matcher_1->tag === "SExpr") { 
        $expr = $__matcher_1->params[0];
        return $this->stmtOutput()->add($this->generateExpr($expr))->add(";")->toString();
      }
      else if ($__matcher_1->tag === "SFunction") { 
        $decl = $__matcher_1->params[0];
        return $this->generateFunction($decl);
      }
      else if ($__matcher_1->tag === "SClass") { 
        $decl = $__matcher_1->params[0];
        return $this->generateClass($decl);
      }
    }

    protected function generateExpr(?Expr $expr):string
    {
      if ($expr === null)
      {
        return "";
      }
      $def = $expr->expr;
      $__matcher_2 = $def;
      if ($__matcher_2->tag === "EAttribute") { 
        $path = $__matcher_2->params[0];
        $args = $__matcher_2->params[1];
        return "";
      }
      else if ($__matcher_2->tag === "EAssign") { 
        $name = $__matcher_2->params[0];
        $value = $__matcher_2->params[1];
        return "$" . ($this->safeVar($name)) . " = " . ($this->generateExpr($value)) . "";
      }
      else if ($__matcher_2->tag === "EBinary") { 
        $left = $__matcher_2->params[0];
        $op = $__matcher_2->params[1];
        $right = $__matcher_2->params[2];
        return $this->generateBinary($left, $op, $right);
      }
      else if ($__matcher_2->tag === "EUnary") { 
        $op = $__matcher_2->params[0];
        $expr = $__matcher_2->params[1];
        $right = $__matcher_2->params[2];
        return $this->generateUnary($op, $expr, $right);
      }
      else if ($__matcher_2->tag === "EIs") { 
        $left = $__matcher_2->params[0];
        $type = $__matcher_2->params[1];
        return "" . ($this->generateExpr($left)) . " instanceof " . ($this->phpTypePath($type)) . "";
      }
      else if ($__matcher_2->tag === "ELogical") { 
        $left = $__matcher_2->params[0];
        $op = $__matcher_2->params[1];
        $right = $__matcher_2->params[2];
        return "" . ($this->generateExpr($left)) . " " . ($op) . " " . ($this->generateExpr($right)) . "";
      }
      else if ($__matcher_2->tag === "ERange") { 
        $from = $__matcher_2->params[0];
        $to = $__matcher_2->params[1];
        return "";
      }
      else if ($__matcher_2->tag === "ECall") { 
        $callee = $__matcher_2->params[0];
        $args = $__matcher_2->params[1];
        return $this->generateCall($callee, $args);
      }
      else if ($__matcher_2->tag === "EGet") { 
        $target = $__matcher_2->params[0];
        $field = $__matcher_2->params[1];
        return $this->generateGet($target, $field);
      }
      else if ($__matcher_2->tag === "ESet") { 
        $target = $__matcher_2->params[0];
        $field = $__matcher_2->params[1];
        $value = $__matcher_2->params[2];
        return $this->generateSet($target, $field, $value);
      }
      else if ($__matcher_2->tag === "EArrayGet") { 
        $target = $__matcher_2->params[0];
        $field = $__matcher_2->params[1];
        return $this->generateArrayGet($target, $field);
      }
      else if ($__matcher_2->tag === "EArraySet") { 
        $target = $__matcher_2->params[0];
        $field = $__matcher_2->params[1];
        $value = $__matcher_2->params[2];
        return $this->generateArraySet($target, $field, $value);
      }
      else if ($__matcher_2->tag === "ETernary") { 
        $condition = $__matcher_2->params[0];
        $thenBranch = $__matcher_2->params[1];
        $elseBranch = $__matcher_2->params[2];
        return $this->generateTernary($condition, $thenBranch, $elseBranch);
      }
      else if ($__matcher_2->tag === "ESuper") { 
        $method = $__matcher_2->params[0];
        return "";
      }
      else if ($__matcher_2->tag === "EPath") { 
        $path = $__matcher_2->params[0];
        return $this->phpTypePath($path);
      }
      else if ($__matcher_2->tag === "EThis") { 
        return "\$this";
      }
      else if ($__matcher_2->tag === "EStatic") { 
        return "static";
      }
      else if ($__matcher_2->tag === "EGrouping") { 
        $expr = $__matcher_2->params[0];
        return "(" . ($this->generateExpr($expr)) . ")";
      }
      else if ($__matcher_2->tag === "ELiteral") { 
        $lit = $__matcher_2->params[0];
        return $this->generateLiteral($lit);
      }
      else if ($__matcher_2->tag === "EArrayLiteral") { 
        $values = $__matcher_2->params[0];
        $isNative = $__matcher_2->params[1];
        return $this->generateArrayLiteral($values, $isNative);
      }
      else if ($__matcher_2->tag === "EMapLiteral") { 
        $keys = $__matcher_2->params[0];
        $values = $__matcher_2->params[1];
        $isNative = $__matcher_2->params[2];
        return $this->generateMapLiteral($keys, $values, $isNative);
      }
      else if ($__matcher_2->tag === "ELambda") { 
        $func = $__matcher_2->params[0];
        return $this->generateLambda($func);
      }
      else if ($__matcher_2->tag === "EVariable") { 
        $name = $__matcher_2->params[0];
        return $this->generateVariable($name);
      }
      else if ($__matcher_2->tag === "EMatch") { 
        $target = $__matcher_2->params[0];
        $cases = $__matcher_2->params[1];
        return "";
      }
      else {
        return "";
      }
    }

    protected function generateNamespace(TypePath $path, \Std\PhaseArray $decls, \Std\PhaseArray $attrs)
    {
      $out = new StringBuf();
      $name = $path->ns->concat(new \Std\PhaseArray([$path->name]))->join("\\");
      $out->add("namespace " . ($name) . " {\n");
      $this->indent();
      foreach ($decls as $decl)
      {
        $out->add($this->generateStmt($decl));
      }
      $this->outdent();
      return $out->add("\n")->add($this->getIndent())->add("}")->toString();
    }

    protected function generateVar(string $name, ?TypePath $type, Expr $init):string
    {
      $out = new StringBuf();
      return $out->add($this->getIndent())->add("$")->add($this->safeVar($name))->add(" = ")->add($this->generateExpr($init))->add(";")->toString();
    }

    protected function generateWhile(Expr $condition, Stmt $body, Bool $inverted):string
    {
      if ($inverted)
      {
        return $this->stmtOutput()->add("do\n")->add($this->generateStmt($body))->add("\n")->add($this->getIndent())->add("while (")->add($this->generateExpr($condition))->add(");")->toString();
      }
      return $this->stmtOutput()->add("while (")->add($this->generateExpr($condition))->add(")\n")->add($this->generateStmt($body))->toString();
    }

    protected function generateFor(string $key, ?string $value, Expr $target, Stmt $body):string
    {
      $def = $target->expr;
      $__matcher_3 = $def;
      if ($__matcher_3->tag === "ERange") { 
        $from = $__matcher_3->params[0];
        $to = $__matcher_3->params[1];
        {
          $key = $this->safeVar($key);
          $init = $this->generateExpr($from);
          $limit = $this->generateExpr($to);
          return $this->stmtOutput()->add("for ($" . ($key) . " = " . ($init) . "; $" . ($key) . " < " . ($limit) . "; $" . ($key) . "++)")->add($this->generateStmt($body))->toString();
        }
      }
      else {
        {
          $key = $this->safeVar($key);
          $out = $this->stmtOutput()->add("foreach (")->add($this->generateExpr($target))->add(" as $" . ($key) . "");
          if ($value !== null)
          {
            $out->add(" => $" . ($this->safeVar($value)) . "");
          }
          return $out->add(")\n")->add($this->generateStmt($body))->toString();
        }
      };
    }

    protected function generateIf(Expr $condition, Stmt $thenBranch, ?Stmt $elseBranch):string
    {
      $out = $this->stmtOutput()->add("if (" . ($this->generateExpr($condition)) . ")\n")->add($this->generateStmt($thenBranch));
      if ($elseBranch !== null)
      {
        $out->add("\n")->add($this->getIndent())->add("else\n")->add($this->generateStmt($elseBranch));
      }
      return $out->toString();
    }

    protected function generateSwitch(Expr $target, \Std\PhaseArray $cases):string
    {
      return "";
    }

    protected function generateAttributes(\Std\PhaseArray $attrs):string
    {
      return "";
    }

    protected function generateFunction(FunctionDecl $decl):string
    {
      return "";
    }

    protected function generateFuncitionArgs(\Std\PhaseArray $args):string
    {
      return $args->map(function (FunctionArg $arg)
      {
        $out = new StringBuf();
        if ($arg->type !== null)
        {
          $out->add($this->phpTypePath($arg->type))->add(" ");
        }
        $out->add("$")->add($this->safeVar($arg->name));
        if ($arg->expr !== null)
        {
          $out->add(" = ")->add($this->generateExpr($arg->expr));
        }
        return $out->toString();
      })->join(", ");
    }

    protected function generateClass(ClassDecl $decl):string
    {
      $out = new StringBuf();
      $props = new \Std\PhaseArray([]);
      $body = new StringBuf();
      if ($decl->attributes->length > 0)
      {
        $out->add($this->generateAttributes($decl->attributes));
        $out->add("\n");
      }
      $keyword = "class";
      switch ($decl->kind)
      {
        case ClassKind::KindTrait:
          $keyword = "trait";
          break;
        case ClassKind::KindInterface:
          $keyword = "interface";
          break;
        default:

          break;
      }
      $out->add($this->getIndent())->add($keyword)->add(" ")->add($decl->name);
      if ($decl->superclass !== null)
      {
        $out->add(" extends ")->add($decl->superclass->toString());
      }
      if ($decl->interfaces->length > 0)
      {
        $keyword = $decl->kind === ClassKind::KindInterface ? " extends " : " implements ";
        $out->add($keyword)->add($decl->interfaces->map(function ($it = null)
        {
          return $it->toString();
        })->join(", "));
      }
      $out->add("\n")->add($this->getIndent())->add("{");
      $this->indent();
      $prevMode = $this->mode;
      $classLocalInits = new \Std\PhaseArray([]);
      $classStaticInits = new \Std\PhaseArray([]);
      $constructor = $decl->fields->find(function ($it = null)
      {
        return $it->name === "new";
      });
      switch ($decl->kind)
      {
        case ClassKind::KindInterface:
          $this->mode = PhpGeneratorMode::GeneratingInterKindInterface;
          break;
        case ClassKind::KindTrait:
          $this->mode = PhpGeneratorMode::GeneratingTrait;
          break;
        default:
          $this->mode = PhpGeneratorMode::GeneratingClass;
          break;
      }
      foreach ($decl->fields as $field)
      {
        $kind = $field->kind;
        if ($field === $constructor)
        {
          continue;
        }
        if ($this->mode === PhpGeneratorMode::GeneratingInterface)
        {
          $__matcher_4 = $kind;
          if ($__matcher_4->tag === "FVar") { 
            $_ = $__matcher_4->params[0];
            $_ = $__matcher_4->params[1];
            $_ = $__matcher_4->params[2];
            continue;
          }
          else if ($__matcher_4->tag === "FProp") { 
            $_ = $__matcher_4->params[0];
            $_ = $__matcher_4->params[1];
            $_ = $__matcher_4->params[2];
            continue;
          }
          else {
            null;
          };
        }
        if ($field->attributes->length > 0)
        {
          $body->add($this->generateAttributes($field->attributes));
        }
        $__matcher_5 = $kind;
        if ($__matcher_5->tag === "FProp") { 
          $_ = $__matcher_5->params[0];
          $_ = $__matcher_5->params[1];
          $_ = $__matcher_5->params[2];
          $props->push($this->safeVar($field->name));
        }
        else {
          null;
        };
        $body->add($this->generateField($field, $classLocalInits, $classStaticInits));
      }
      if ($constructor !== null)
      {
        if ($classLocalInits->length > 0)
        {

        }
      }
      if ($constructor !== null)
      {
        $out->add($this->generateField($constructor, new \Std\PhaseArray([]), new \Std\PhaseArray([])));
      }
      $out->add($body->toString());
      $this->outdent();
      $this->mode = $prevMode;
      $out->add("\n")->add($this->getIndent())->add("}");
      return $out->toString();
    }

    protected function generateField(Field $field, \Std\PhaseArray $localInits, \Std\PhaseArray $staticInits):string
    {
      $isConst = $field->access->contains(FieldAccess::AConst);
      $out = (new StringBuf())->add("\n")->add($this->getIndent());
      $kind = $field->kind;
      $access = $field->access->map(function ($it = null)
      {
        switch ($it)
        {
          case FieldAccess::AStatic:
            return "static";
            break;
          case FieldAccess::APublic:
            return "public";
            break;
          case FieldAccess::APrivate:
            return "protected";
            break;
          case FieldAccess::AConst:
            return "const";
            break;
          case FieldAccess::AAbstract:
            return $this->mode === PhpGeneratorMode::GeneratingInterface ? null : "abstract";
            break;
        }
      })->filter(function ($it = null)
      {
        return $it !== null;
      })->join(" ");
      $__matcher_6 = $kind;
      if ($__matcher_6->tag === "FVar") { 
        $name = $__matcher_6->params[0];
        $type = $__matcher_6->params[1];
        $init = $__matcher_6->params[2];
        {
          $ret = new StringBuf();
          $name = $this->safeVar($field->name);
          $ret->add($access);
          if ($this->config->phpVersion >= 8)
          {
            if ($type !== null && !$isConst)
            {
              $ret->add(" ")->add($type->toString());
            }
          }
          $ret->add($isConst ? " " : " $")->add($name);
          if ($init !== null)
          {
            if ($isConst)
            {
              $ret->add(" = ")->add($this->generateExpr($init));
            }
          }
          else
          {
            if ($field->access->contains(FieldAccess::AStatic))
            {
              $staticInits->push("$" . ($name) . " = " . ($this->generateExpr($init)) . "");
            }
            else
            {
              $localInits->push("" . ($name) . " = " . ($this->generateExpr($init)) . "");
            }
          }
          $ret->add(";");
          $out->add($ret->toString());
        }
      }
      else if ($__matcher_6->tag === "FUse") { 
        $type = $__matcher_6->params[0];
        $out->add("use ")->add($type->toString())->add(";");
      }
      else if ($__matcher_6->tag === "FFun") { 
        $func = $__matcher_6->params[0];
        {
          $name = $field->name === "new" ? "__construct" : $this->safeVar($field->name);
          $out->add($access)->add(" function ")->add($name)->add("(")->add($this->generateFuncitionArgs($func->args))->add(")");
          if ($func->ret !== null && $this->config->phpVersion >= 8)
          {
            $out->add(":")->add($func->ret->toString());
          }
          if (!$field->access->contains(FieldAccess::AAbstract))
          {
            foreach ($func->args as $p)
            {
              if ($p->isInit)
              {
                $p->isInit = false;
                $init = CodeGenerator::generateStmt("this." . ($p->name) . " = " . ($p->name) . ";", $field->pos, $this->reporter);
                $body = $func->body->stmt;
                $__matcher_7 = $body;
                if ($__matcher_7->tag === "SBlock") { 
                  $stmts = $__matcher_7->params[0];
                  {
                    $stmts->unshift($init);
                    $func->body->stmt = StmtDef::SBlock($stmts);
                  }
                }
                else {
                  {
                    $body = $func->body;
                    $func->body = new Stmt(stmt: StmtDef::SBlock(new \Std\PhaseArray([$init, $body])), pos: $body->pos);
                  }
                };
              }
            }
            $out->add("\n")->add($this->generateStmt($func->body));
          }
          else
          {
            $out->add(";");
          }
        }
      };
      return $out->toString();
    }

    protected function generateTry(Stmt $body, \Std\PhaseArray $catches)
    {
      $out = $this->stmtOutput()->add("try\n")->add($this->generateStmt($body));
      foreach ($catches as $c)
      $out->add($this->generateStmt($c));
      return $out->toString();
    }

    protected function generateCatch(string $name, ?TypePath $type, Stmt $body)
    {
      $out = new StringBuf();
      $name = $this->safeVar($name);
      $out->add("\n")->add($this->getIndent())->add("catch (");
      if ($type !== null)
      {
        $out->add($this->phpTypePath($type))->add(" $")->add($name)->add(")\n");
      }
      else
      {
        $out->add("$")->add($name)->add(")\n");
      }
      $out->add($this->generateStmt($body));
      return $out->toString();
    }

    protected function generateBlock(\Std\PhaseArray $stmts)
    {
      $out = new StringBuf();
      $out->add("{\n");
      $this->indent();
      return $out->add($stmts->map(function ($it = null)
      {
        return $this->generateStmt($it);
      })->join("\n"))->add("\n")->add($this->outdent()->getIndent())->add("}")->toString();
    }

    protected function generateVariable(string $name):string
    {
      return "$" . ($this->safeVar($name)) . "";
    }

    protected function generateBinary(Expr $left, string $op, Expr $right):string
    {
      $out = new StringBuf();
      $stringType = $this->context->getType("String");
      $out->add($this->generateExpr($left));
      switch ($op)
      {
        case "+":
          if ($left->type !== null && $this->context->unify($left->type, $stringType))
          {
            return $out->add(".")->add($this->generateExpr($right));
          }
          return $out->add("+")->add($this->generateExpr($right));
          break;
        default:
          return $out->add($op)->add($this->generateExpr($right));
          break;
      }
    }

    protected function generateUnary(string $op, Expr $expr, Bool $isRight):string
    {
      return $isRight ? "" . ($op) . "" . ($this->generateExpr($expr)) . "" : "" . ($this->generateExpr($expr)) . "" . ($op) . "";
    }

    protected function generateCall(Expr $callee, \Std\PhaseArray $args):string
    {
      $def = $callee->expr;
      $type = $callee->type;
      $out = new StringBuf();
      if ($type === null)
      {
        $type = Type::TAny();
      }
      $__matcher_8 = $type;
      if ($__matcher_8->tag === "TInstance") { 
        $_ = $__matcher_8->params[0];
        $out->add("new " . ($this->generateExpr($callee)) . "");
      }
      else {
        $__matcher_9 = $def;
        if ($__matcher_9->tag === "EPath") { 
          $_ = $__matcher_9->params[0];
          $out->add("new " . ($this->generateExpr($callee)) . "");
        }
        else {
          $out->add($this->generateExpr($callee));
        };
      };
      return $out->add("(")->add($args->map(function (CallArgument $arg)
      {
        $__matcher_10 = $arg;
        if ($__matcher_10->tag === "Positional") { 
          $expr = $__matcher_10->params[0];
          return $this->generateExpr($expr);
        }
        else if ($__matcher_10->tag === "Named") { 
          $name = $__matcher_10->params[0];
          $expr = $__matcher_10->params[1];
          return "" . ($name) . ": " . ($this->generateExpr($expr)) . "";
        }
      })->join(", "))->add(")")->toString();
    }

    protected function generateGet(Expr $target, Expr $field):string
    {
      $targetType = $this->resolveType($target->type);
      $stringType = $this->context->getType("String");
      $fieldDef = $field->expr;
      if ($targetType !== null && $this->context->unify($targetType, $stringType))
      {
        $name = $this->safeVar($field);
        $expr = $this->generateExpr($target);
        return "\\Std\\PhaseString::wrap(" . ($expr) . ")->" . ($name) . "";
      }
      $out = new StringBuf();
      $out->add($this->generateExpr($target));
      $name = "";
      $__matcher_11 = $fieldDef;
      if ($__matcher_11->tag === "EVariable") { 
        $str = $__matcher_11->params[0];
        $name = $str;
      }
      else {
        $name = "{" . ($this->generateExpr($field)) . "}";
      };
      if ($targetType === null)
      {
        return $out->add("->")->add($name)->toString();
      }
      $__matcher_12 = $targetType;
      if ($__matcher_12->tag === "TInstance") { 
        $cls = $__matcher_12->params[0];
        {
          $f = $cls->fields->find(function ($it = null) use ($name)
          {
            return $it->name === $name;
          });
          $kind = $f->kind;
          if ($f->access->contains(FieldAccess::AConst))
          {
            $out->add("::")->add($name);
          }
          else
          {
            if ($f->access->contains(FieldAccess::AStatic))
            {
              $out->add("::");
              $__matcher_13 = $kind;
              if ($__matcher_13->tag === "FVar") { 
                $_ = $__matcher_13->params[0];
                $_ = $__matcher_13->params[1];
                $_ = $__matcher_13->params[2];
                $out->add("$")->add($name);
              }
              else {
                $out->add($name);
              };
            }
            else
            {
              $out->add("->")->add($name);
            }
          }
          return $out->toString();
        }
      }
      else {
        return $out->add("->")->add($name)->toString();
      };
    }

    protected function generateSet(Expr $target, Expr $field, Expr $value):string
    {
      $out = new StringBuf();
      $def = $field->expr;
      return $out->add($this->generateGet($target, $field))->add(" = ")->add($this->generateExpr($value))->toString();
    }

    protected function generateArrayGet(Expr $target, ?Expr $field):string
    {
      $out = new StringBuf();
      $out->add($this->generateExpr($target));
      if ($field === null)
      {
        return $out->add("[]")->toString();
      }
      return $out->add("[")->add($this->generateExpr($field))->add("]")->toString();
    }

    protected function generateArraySet(Expr $target, ?Expr $field, Expr $value):string
    {
      $out = new StringBuf();
      return $out->add($this->generateArraySet($target, $field))->add(" = ")->add($this->generateExpr($value))->toString();
    }

    protected function generateTernary(Expr $condition, Expr $thenBranch, Expr $elseBranch):string
    {
      $out = new StringBuf();
      return $out->add($this->generateExpr($condition))->add(" ? ")->add($this->generateExpr($thenBranch))->add(" : ")->add($this->generateExpr($elseBranch))->toString();
    }

    protected function generateLiteral(Literal $literal):string
    {
      $__matcher_14 = $literal;
      if ($__matcher_14->tag === "LNull") { 
        return "null";
      }
      else if ($__matcher_14->tag === "LTrue") { 
        return "true";
      }
      else if ($__matcher_14->tag === "LFalse") { 
        return "false";
      }
      else if ($__matcher_14->tag === "LNumber") { 
        $num = $__matcher_14->params[0];
        return $num;
      }
      else if ($__matcher_14->tag === "LString") { 
        $str = $__matcher_14->params[0];
        return "\"" . (str_replace("\"", "\\\"", $str)) . "\"";
      }
    }

    protected function generateArrayLiteral(\Std\PhaseArray $exprs, Bool $isNative):string
    {
      $list = new StringBuf();
      $list->add("[")->add($exprs->map(function ($it = null)
      {
        return $this->generateExpr($it);
      })->join(", "))->add("]");
      if ($isNative)
      {
        return $list->toString();
      }
      $out = new StringBuf();
      $arr = static::$phaseToPhpTypes["Array"];
      return $out->add("new ")->add($arr)->add("(")->add($list->toString())->add(")")->toString();
    }

    protected function generateMapLiteral(\Std\PhaseArray $keys, \Std\PhaseArray $values, Bool $isNative):string
    {
      $out = new StringBuf();
      $list = new \Std\PhaseArray([]);
      $this->indent();
      for ($i = 0; $i < $keys->length; $i++)
      {
        $item = new StringBuf();
        $item->add($this->getIndent())->add($this->generateExpr($keys[$i]))->add(" => ")->add($this->generateExpr($values[$i]));
        $list->push($item->toString());
      }
      $this->outdent();
      $out->add("[")->add($list->join(",\n"))->add("\n")->add($this->getIndent())->add("]");
      if ($isNative)
      {
        return $out->toString();
      }
      return "new " . (static::$phaseToPhpTypes->get("Map")) . "(" . ($out->toString()) . ")";
    }

    protected function generateLambda(FunctionDecl $func):string
    {
      return "";
    }

    protected function indent()
    {
      $this->indentLevel++;
      return $this;
    }

    protected function outdent()
    {
      $this->indentLevel--;
      if ($this->indentLevel < 0)
      {
        $this->indentLevel = 0;
      }
      return $this;
    }

    protected function getIndent():string
    {
      $out = new StringBuf();
      for ($i = 0; $i < $this->indentLevel; $i++)
      {
        $out->add("    ");
      }
      return $out->toString();
    }

    protected function safeVar(string $name)
    {
      return $name;
    }

    protected function stmtOutput()
    {
      return (new StringBuf())->add($this->getIndent());
    }

    protected function phpTypePath(TypePath $type)
    {
      $out = $type->ns->concat(new \Std\PhaseArray([$type->name]))->join("//");
      if ($out === "Any")
      {
        return "mixed";
      }
      if (static::$phaseToPhpTypes->contains($out))
      {
        $out = static::$phaseToPhpTypes->get($out);
        return $type->isNullable ? "?" . ($out) . "" : $out;
      }
      if ($type->isAbsolute)
      {
        $out = "//" . ($out) . "";
      }
      if ($type->isNullable)
      {
        $out = "?" . ($out) . "";
      }
      return $out;
    }

    protected function resolveType(?Type $type):?Type
    {
      if ($type === null)
      {
        return null;
      }
      $__matcher_15 = $type;
      if ($__matcher_15->tag === "TUnknown") { 
        $path = $__matcher_15->params[0];
        return $path !== null ? $this->context->getType($path->toString()) : Type::TAny();
      }
      else {
        return $type;
      };
    }

  }
  PhpGenerator::$phaseToPhpTypes = new \Std\PhaseMap([
    "String" => "string",
    "Int" => "int",
    "Array" => "\\Std\\PhaseArray",
    "Map" => "\\Std\\PhaseMap",
    "Callable" => "callable",
    "Any" => "mixed",
    "Scalar" => "scalar",
    "Bool" => "bool"
  ]);

}