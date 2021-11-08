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
  use Phase\Language\Type;
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

  class Typer
  {

    public function __construct(\Std\PhaseArray $stmts, ErrorReporter $reporter)
    {
      $this->reporter = $reporter;
      $this->stmts = $stmts;
      $this->ns = new \Std\PhaseArray([]);
      $this->scope = null;
    }

    protected \Std\PhaseArray $stmts;

    protected \Std\PhaseMap $types;

    protected ErrorReporter $reporter;

    protected ?Scope $scope;

    protected \Std\PhaseArray $ns;

    public function typeSurface():\Std\PhaseMap
    {
      $this->types = new \Std\PhaseMap();
      $this->scope = new Scope();
      foreach ($this->stmts as $stmt)
      {
        $this->typeSurfaceDecl($stmt);
      }
      return $this->types;
    }

    protected function typeSurfaceDecl(Stmt $stmt):\Std\PhaseMap
    {
      $def = $stmt->stmt;
      $__matcher_1 = $def;
      if ($__matcher_1->tag === "SNamespace") { 
        $path = $__matcher_1->params[0];
        $decls = $__matcher_1->params[1];
        $attrs = $__matcher_1->params[2];
        {
          $this->ns = $path->ns->concat(new \Std\PhaseArray([$path->name]));
          foreach ($decls as $decl)
          $this->typeSurfaceDecl($decl);
        }
      }
      else if ($__matcher_1->tag === "SUse") { 
        $path = $__matcher_1->params[0];
        $kind = $__matcher_1->params[1];
        $attrs = $__matcher_1->params[2];
        $this->typeUse($path, $kind);
      }
      else if ($__matcher_1->tag === "SClass") { 
        $cls = $__matcher_1->params[0];
        {
          $tp = new TypePath(ns: $this->ns, name: $cls->name, params: $cls->params);
          $type = Type::TInstance($cls);
          $this->types->set($tp->toString(), $type);
          if ($this->scope !== null)
          {
            $this->scope->declare($cls->name, $type);
          }
        }
      }
      else if ($__matcher_1->tag === "SFunction") { 
        $func = $__matcher_1->params[0];
        {
          $tp = new TypePath(ns: $this->ns, name: $func->name, params: $func->params);
          $type = Type::TFun($func);
          $this->types->set($tp->toString(), $type);
          if ($this->scope !== null)
          {
            $this->scope->declare($func->name, $type);
          }
        }
      }
      else {
        null;
      };
      return $this->types;
    }

    public function type():\Std\PhaseMap
    {
      $this->scope = new Scope();
      $this->typeSurface();
      foreach ($this->stmts as $stmt)
      {
        $this->typeStmt($stmt);
      }
      $this->scope = null;
      return $this->types;
    }

    public function typeStmt(?Stmt $stmt)
    {
      if ($stmt === null)
      {
        return;
      }
      $def = $stmt->stmt;
      $__matcher_2 = $def;
      if ($__matcher_2->tag === "SNamespace") { 
        $path = $__matcher_2->params[0];
        $decls = $__matcher_2->params[1];
        $attrs = $__matcher_2->params[2];
        {
          $this->ns = $path->ns->concat(new \Std\PhaseArray([$path->name]));
          foreach ($decls as $decl)
          $this->typeStmt($decl);
        }
      }
      else if ($__matcher_2->tag === "SExpr") { 
        $expr = $__matcher_2->params[0];
        $this->typeExpr($expr);
      }
      else if ($__matcher_2->tag === "SUse") { 
        $path = $__matcher_2->params[0];
        $kind = $__matcher_2->params[1];
        $attributes = $__matcher_2->params[2];
        $this->typeUse($path, $kind);
      }
      else if ($__matcher_2->tag === "SVar") { 
        $name = $__matcher_2->params[0];
        $type = $__matcher_2->params[1];
        $init = $__matcher_2->params[2];
        {
          $t = Type::TAny();
          if ($type !== null)
          {
            $t = $this->resolve($type);
          }
          if ($init !== null)
          {
            $this->typeExpr($init);
            $__matcher_3 = $t;
            if ($__matcher_3->tag === "TAny") { 
              {
                if ($type === null && $init->type !== null)
                {
                  $t = $init->type;
                }
              }
            }
            else {
              null;
            };
          }
          $this->scope->declare($name, $t);
        }
      }
      else if ($__matcher_2->tag === "SGlobal") { 
        $name = $__matcher_2->params[0];
        $this->scope->declare($name, Type::TAny());
      }
      else if ($__matcher_2->tag === "SThrow") { 
        $expr = $__matcher_2->params[0];
        $this->typeExpr($expr);
      }
      else if ($__matcher_2->tag === "STry") { 
        $body = $__matcher_2->params[0];
        $catches = $__matcher_2->params[1];
        $this->wrapScope(function ($it = null) use ($body, $catches)
        {
          $this->typeStmt($body);
          foreach ($catches as $item)
          $this->typeStmt($item);
        });
      }
      else if ($__matcher_2->tag === "SCatch") { 
        $name = $__matcher_2->params[0];
        $type = $__matcher_2->params[1];
        $body = $__matcher_2->params[2];
        $this->wrapScope(function ($it = null) use ($type, $name, $body)
        {
          if ($type !== null)
          {
            $this->scope->declare($name, $this->resolve($type));
          }
          $this->typeStmt($body);
        });
      }
      else if ($__matcher_2->tag === "SWhile") { 
        $condition = $__matcher_2->params[0];
        $body = $__matcher_2->params[1];
        $inverted = $__matcher_2->params[2];
        $this->wrapScope(function ($it = null) use ($condition, $body)
        {
          $this->typeExpr($condition);
          $this->typeStmt($body);
        });
      }
      else if ($__matcher_2->tag === "SFor") { 
        $key = $__matcher_2->params[0];
        $value = $__matcher_2->params[1];
        $target = $__matcher_2->params[2];
        $body = $__matcher_2->params[3];
        $this->wrapScope(function ($it = null) use ($target)
        {
          $this->typeExpr($target);
          $type = $this->resolve($target->type);
        });
      }
      else if ($__matcher_2->tag === "SIf") { 
        $condition = $__matcher_2->params[0];
        $thenBranch = $__matcher_2->params[1];
        $elseBranch = $__matcher_2->params[2];
        $this->wrapScope(function ($it = null) use ($condition, $thenBranch, $elseBranch)
        {
          $this->typeExpr($condition);
          $this->wrapScope(function ($it = null) use ($condition, $thenBranch)
          {
            return $this->typeStmt($thenBranch);
          });
          $this->wrapScope(function ($it = null) use ($condition, $thenBranch, $elseBranch)
          {
            return $this->typeStmt($elseBranch);
          });
        });
      }
      else if ($__matcher_2->tag === "SSwitch") { 
        $target = $__matcher_2->params[0];
        $cases = $__matcher_2->params[1];
        $this->wrapScope(function ($it = null) use ($target, $cases)
        {
          $this->typeExpr($target);
          foreach ($cases as $item)
          $this->wrapScope(function ($it = null) use ($target, $cases, $item)
          {
            $this->typeExpr($item->condition);
            $item->body->map(function ($it = null) use ($target, $cases, $item)
            {
              return $this->typeStmt($it);
            });
          });
        });
      }
      else if ($__matcher_2->tag === "SBlock") { 
        $statements = $__matcher_2->params[0];
        $this->wrapScope(function ($it = null) use ($statements)
        {
          foreach ($statements as $item)
          $this->typeStmt($item);
        });
      }
      else if ($__matcher_2->tag === "SReturn") { 
        $expr = $__matcher_2->params[0];
        $this->typeExpr($expr);
      }
      else if ($__matcher_2->tag === "SFunction") { 
        $decl = $__matcher_2->params[0];
        $this->typeFunction($decl);
      }
      else if ($__matcher_2->tag === "SClass") { 
        $cls = $__matcher_2->params[0];
        {
          $this->scope->declare($cls->name, Type::TInstance($cls));
          $this->wrapScope(function ($it = null) use ($cls)
          {
            $this->scope->declare("static", Type::TInstance($cls));
            $this->scope->declare("this", Type::TInstance($cls));
            foreach ($cls->fields as $field)
            $this->typeField($field, $cls);
          });
        }
      };
    }

    public function typeExpr(?Expr $expr)
    {
      if ($expr === null)
      {
        return;
      }
      $def = $expr->expr;
      $__matcher_4 = $def;
      if ($__matcher_4->tag === "EAttribute") { 
        $path = $__matcher_4->params[0];
        $args = $__matcher_4->params[1];
        null;
      }
      else if ($__matcher_4->tag === "EAssign") { 
        $name = $__matcher_4->params[0];
        $value = $__matcher_4->params[1];
        {
          $this->typeExpr($value);
          $expr->type = $value->type;
        }
      }
      else if ($__matcher_4->tag === "EBinary") { 
        $left = $__matcher_4->params[0];
        $op = $__matcher_4->params[1];
        $right = $__matcher_4->params[2];
        {
          $this->typeExpr($left);
          $this->typeExpr($right);
        }
      }
      else if ($__matcher_4->tag === "EUnary") { 
        $op = $__matcher_4->params[0];
        $target = $__matcher_4->params[1];
        $isRight = $__matcher_4->params[2];
        {
          $this->typeExpr($target);
          $expr->type = $target->type;
        }
      }
      else if ($__matcher_4->tag === "EIs") { 
        $left = $__matcher_4->params[0];
        $type = $__matcher_4->params[1];
        {
          $this->typeExpr($left);
          $expr->type = $this->resolve($type);
        }
      }
      else if ($__matcher_4->tag === "ELogical") { 
        $left = $__matcher_4->params[0];
        $op = $__matcher_4->params[1];
        $right = $__matcher_4->params[2];
        {
          $this->typeExpr($left);
          $this->typeExpr($right);
          $expr->type = $this->resolve(new TypePath(ns: new \Std\PhaseArray([]), name: "Bool", isAbsolute: true));
        }
      }
      else if ($__matcher_4->tag === "ERange") { 
        $from = $__matcher_4->params[0];
        $to = $__matcher_4->params[1];
        {
          $this->typeExpr($from);
          $this->typeExpr($to);
          $expr->type = $this->resolve(new TypePath(ns: new \Std\PhaseArray([]), name: "Int", isAbsolute: true));
        }
      }
      else if ($__matcher_4->tag === "ECall") { 
        $callee = $__matcher_4->params[0];
        $args = $__matcher_4->params[1];
        {
          $this->typeExpr($callee);
          foreach ($args as $arg)
          {
            $a = $arg;
            $__matcher_5 = $a;
            if ($__matcher_5->tag === "Positional") { 
              $e = $__matcher_5->params[0];
              $this->typeExpr($e);
            }
            else if ($__matcher_5->tag === "Named") { 
              $name = $__matcher_5->params[0];
              $e = $__matcher_5->params[1];
              $this->typeExpr($e);
            };
          }
          $type = $callee->type;
          $__matcher_6 = $type;
          if ($__matcher_6->tag === "TFun") { 
            $func = $__matcher_6->params[0];
            $expr->type = $this->resolve($func->ret);
          }
          else if ($__matcher_6->tag === "TInstance") { 
            $cls = $__matcher_6->params[0];
            $expr->type = Type::TInstance($cls);
          }
          else if ($__matcher_6->tag === "TUnknown") { 
            $path = $__matcher_6->params[0];
            {
              $type = $this->resolve($path);
              $__matcher_7 = $type;
              if ($__matcher_7->tag === "TFun") { 
                $func = $__matcher_7->params[0];
                $expr->type = $this->resolve($func->ret);
              }
              else if ($__matcher_7->tag === "TInstance") { 
                $cls = $__matcher_7->params[0];
                $expr->type = Type::TInstance($cls);
              }
              else {
                null;
              };
            }
          }
          else {
            null;
          };
        }
      }
      else if ($__matcher_4->tag === "EGet") { 
        $target = $__matcher_4->params[0];
        $field = $__matcher_4->params[1];
        {
          $this->typeExpr($target);
          $type = $target->type;
          $def = $field->expr;
          $expr->type = Type::TAny();
          $__matcher_8 = $def;
          if ($__matcher_8->tag === "EVariable") { 
            $name = $__matcher_8->params[0];
            $__matcher_9 = $type;
            if ($__matcher_9->tag === "TInstance") { 
              $cls = $__matcher_9->params[0];
              {
                $field = $cls->fields->find(function ($it = null) use ($name)
                {
                  return $it->name === $name;
                });
                if ($field === null)
                {
                  $this->reporter->report($field->pos, "The class " . ($cls->name) . " does not have a field " . ($name) . "");
                  $expr->type = Type::TAny();
                }
                else
                {
                  $__matcher_10 = $field->kind;
                  if ($__matcher_10->tag === "FUse") { 
                    $_ = $__matcher_10->params[0];
                    $expr->type = Type::TVoid();
                  }
                  else if ($__matcher_10->tag === "FVar") { 
                    $_ = $__matcher_10->params[0];
                    $type = $__matcher_10->params[1];
                    $_ = $__matcher_10->params[2];
                    $expr->type = $this->resolve($type);
                  }
                  else if ($__matcher_10->tag === "FProp") { 
                    $_ = $__matcher_10->params[0];
                    $_ = $__matcher_10->params[1];
                    $type = $__matcher_10->params[2];
                    $expr->type = $this->resolve($type);
                  }
                  else if ($__matcher_10->tag === "FFun") { 
                    $func = $__matcher_10->params[0];
                    $expr->type = Type::TFun($func);
                  };
                }
              }
            }
            else {
              null;
            };
          }
          else {
            $this->typeExpr($field);
          };
        }
      }
      else if ($__matcher_4->tag === "ESet") { 
        $target = $__matcher_4->params[0];
        $field = $__matcher_4->params[1];
        $value = $__matcher_4->params[2];
        {
          $this->typeExpr($target);
          $this->typeExpr($value);
          $def = $field->expr;
          $__matcher_11 = $def;
          if ($__matcher_11->tag === "EVariable") { 
            $name = $__matcher_11->params[0];
            null;
          }
          else {
            $this->typeExpr($field);
          };
          $expr->type = Type::TVoid();
        }
      }
      else if ($__matcher_4->tag === "EArrayGet") { 
        $target = $__matcher_4->params[0];
        $field = $__matcher_4->params[1];
        {
          $this->typeExpr($target);
          $this->typeExpr($field);
        }
      }
      else if ($__matcher_4->tag === "EArraySet") { 
        $target = $__matcher_4->params[0];
        $field = $__matcher_4->params[1];
        $value = $__matcher_4->params[2];
        {
          $this->typeExpr($target);
          $this->typeExpr($field);
          $this->typeExpr($value);
        }
      }
      else if ($__matcher_4->tag === "ETernary") { 
        $condition = $__matcher_4->params[0];
        $thenBranch = $__matcher_4->params[1];
        $elseBranch = $__matcher_4->params[2];
        {
          $this->typeExpr($condition);
          $this->typeExpr($thenBranch);
          $this->typeExpr($elseBranch);
        }
      }
      else if ($__matcher_4->tag === "ESuper") { 
        $method = $__matcher_4->params[0];
        {

        }
      }
      else if ($__matcher_4->tag === "EPath") { 
        $path = $__matcher_4->params[0];
        {
          $expr->type = $this->resolve($path);
        }
      }
      else if ($__matcher_4->tag === "EThis") { 
        {
          $type = $this->scope->resolve("this");
          if ($type === null)
          {
            $this->reporter->report($expr->pos, "Used 'this' outside of a class declaration");
          }
          $expr->type = $type;
        }
      }
      else if ($__matcher_4->tag === "EStatic") { 
        {
          $type = $this->scope->resolve("static");
          if ($type === null)
          {
            $this->reporter->report($expr->pos, "Used 'static' outside of a class declaration");
          }
          $expr->type = $type;
        }
      }
      else if ($__matcher_4->tag === "EGrouping") { 
        $target = $__matcher_4->params[0];
        {
          $this->typeExpr($target);
          $expr->type = $target->type;
        }
      }
      else if ($__matcher_4->tag === "ELiteral") { 
        $value = $__matcher_4->params[0];
        {
          $t = $value;
          $__matcher_12 = $t;
          if ($__matcher_12->tag === "LString") { 
            $str = $__matcher_12->params[0];
            $expr->type = $this->resolve(new TypePath(ns: new \Std\PhaseArray([]), name: "String", isAbsolute: true));
          }
          else if ($__matcher_12->tag === "LNumber") { 
            $value = $__matcher_12->params[0];
            $expr->type = $this->resolve(new TypePath(ns: new \Std\PhaseArray([]), name: "Int", isAbsolute: true));
          }
          else if ($__matcher_12->tag === "LTrue") { 
            $expr->type = $this->resolve(new TypePath(ns: new \Std\PhaseArray([]), name: "Bool", isAbsolute: true));
          }
          else if ($__matcher_12->tag === "LFalse") { 
            $expr->type = $this->resolve(new TypePath(ns: new \Std\PhaseArray([]), name: "Bool", isAbsolute: true));
          }
          else if ($__matcher_12->tag === "LNull") { 
            $expr->type = $this->resolve(new TypePath(ns: new \Std\PhaseArray([]), name: "Null", isAbsolute: true));
          };
        }
      }
      else if ($__matcher_4->tag === "EArrayLiteral") { 
        $values = $__matcher_4->params[0];
        $isNative = $__matcher_4->params[1];
        {
          foreach ($values as $value)
          $this->typeExpr($value);
          $name = $isNative ? "$Array" : "Array";
          $expr->type = $this->resolve(new TypePath(ns: new \Std\PhaseArray([]), name: $name, isAbsolute: true));
        }
      }
      else if ($__matcher_4->tag === "EMapLiteral") { 
        $keys = $__matcher_4->params[0];
        $values = $__matcher_4->params[1];
        $isNative = $__matcher_4->params[2];
        {
          foreach ($keys as $key)
          $this->typeExpr($key);
          foreach ($values as $value)
          $this->typeExpr($value);
          $name = $isNative ? "$Array" : "Map";
          $expr->type = $this->resolve(new TypePath(ns: new \Std\PhaseArray([]), name: $name, isAbsolute: true));
        }
      }
      else if ($__matcher_4->tag === "ELambda") { 
        $func = $__matcher_4->params[0];
        {
          $this->typeFunction($func);
          $expr->type = Type::TFun($func);
        }
      }
      else if ($__matcher_4->tag === "EVariable") { 
        $name = $__matcher_4->params[0];
        {
          if (!$this->scope->isDeclared($name))
          {
            $this->reporter->report($expr->pos, "Undeclared variable: " . ($name) . "");
          }
          $expr->type = $this->scope->resolve($name);
        }
      }
      else if ($__matcher_4->tag === "EMatch") { 
        $target = $__matcher_4->params[0];
        $cases = $__matcher_4->params[1];
        {
          $this->typeExpr($target);
          foreach (catches as $c)
          {
            if (!$c->isDefault)
            {
              $this->typeExpr($c->condition);
            }
            foreach ($c->body as $s)
            $this->typeStmt($s);
          }
        }
      };
    }

    public function typeUse(\Std\PhaseArray $path, UseKind $kind)
    {
      $__matcher_13 = $kind;
      if ($__matcher_13->tag === "UseAlias") { 
        $alias = $__matcher_13->params[0];
        {
          $target = $alias;
          $ns = $path->copy();
          $name = $ns->pop();
          $type = $this->resolve(new TypePath(ns: $ns, name: $name, isAbsolute: true));
          $__matcher_14 = $target;
          if ($__matcher_14->tag === "TargetType") { 
            $name = $__matcher_14->params[0];
            $this->scope->declare($name, $type);
          }
          else if ($__matcher_14->tag === "TargetFunction") { 
            $name = $__matcher_14->params[0];
            {

            }
          };
        }
      }
      else if ($__matcher_13->tag === "UseSub") { 
        $items = $__matcher_13->params[0];
        foreach ($items as $item)
        {
          $target = $item;
          $__matcher_15 = $target;
          if ($__matcher_15->tag === "TargetType") { 
            $name = $__matcher_15->params[0];
            $this->scope->declare($name, Type::TUnknown(new TypePath(ns: $path, name: $name, isAbsolute: true)));
          }
          else if ($__matcher_15->tag === "TargetFunction") { 
            $name = $__matcher_15->params[0];
            {

            }
          };
        }
      };
    }

    public function typeFunction(FunctionDecl $decl)
    {
      $this->scope->declare($decl->name, Type::TFun($decl));
      $this->wrapScope(function ($it = null) use ($decl)
      {
        foreach ($decl->args as $arg)
        {
          $t = $arg->type !== null ? $this->resolve($arg->type) : Type::TAny();
          if ($arg->expr !== null)
          {
            $this->typeExpr($arg->expr);
            if ($arg->expr->type !== null)
            {
              $__matcher_16 = $t;
              if ($__matcher_16->tag === "TAny") { 
                $t = $arg->expr->type;
              }
              else {
                null;
              };
            }
          }
          $this->scope->declare($arg->name, $t);
        }
        $this->typeStmt($decl->body);
      });
    }

    public function typeField(Field $field, ClassDecl $cls)
    {
      $kind = $field->kind;
      $__matcher_17 = $kind;
      if ($__matcher_17->tag === "FUse") { 
        $type = $__matcher_17->params[0];
        null;
      }
      else if ($__matcher_17->tag === "FVar") { 
        $name = $__matcher_17->params[0];
        $type = $__matcher_17->params[1];
        $initializer = $__matcher_17->params[2];
        {
          if ($initializer !== null)
          {
            $this->typeExpr($initializer);
          }
        }
      }
      else if ($__matcher_17->tag === "FProp") { 
        $getter = $__matcher_17->params[0];
        $setter = $__matcher_17->params[1];
        $type = $__matcher_17->params[2];
        {
          $this->typeFunction($getter);
          $this->typeFunction($setter);
        }
      }
      else if ($__matcher_17->tag === "FFun") { 
        $fun = $__matcher_17->params[0];
        $this->typeFunction($fun);
      };
    }

    protected function resolve(?TypePath $path):Type
    {
      if ($path === null)
      {
        return Type::TAny();
      }
      $name = $path->toString();
      $type = $this->scope->resolve($name);
      if ($type !== null)
      {
        return $type;
      }
      if (!$path->isAbsolute)
      {
        $local = new TypePath(ns: $this->ns->concat($path->ns), name: $path->name, isAbsolute: true);
        return resolve($local);
      }
      return Type::TUnknown($path);
    }

    protected function wrapScope(callable $cb)
    {
      $prev = $this->scope;
      $this->scope = $this->scope->pushChild();
      $cb();
      $this->scope = $prev;
    }

  }

}