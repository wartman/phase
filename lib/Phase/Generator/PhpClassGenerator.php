<?php
namespace Phase\Generator {

  use Std\StringBuf;

  trait PhpClassGenerator
  {

    protected ?\Std\PhaseArray $classLocalInits;

    protected ?\Std\PhaseArray $classStaticInits;

    abstract protected string $mode;

    abstract protected function generateAttributes(\Std\PhaseArray $attrs):string;

    abstract protected function indent():Generator;

    abstract protected function outdent():Generator;

    abstract protected function getIndent():string;

    protected function generateClass(ClassDecl $decl):string
    {
      $out = new StringBuf();
      $props = new \Std\PhaseArray([]);
      $body = "";
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
      if ($decl->superclass != null)
      {
        $out->add(" extends ")->add($decl->superclass->toString());
      }
      if ($decl->interfaces->length > 0)
      {
        $keyword = $decl->kind == ClassKind::KindInterface ? " extends " : " implements ";
        $out->add($keyword)->add($decl->interfaces->map(function ($it = null)
        {
          return $it->toString();
        })->join(", "));
      }
      $out->add("\n")->add($this->getIndent())->add("{\n");
      $this->indent();
      $prevMode = $this->mode;
      $constructor = $decl->fields->find(function ($it = null)
      {
        return $it->name == "new";
      });
      switch ($decl->kind)
      {
        case ClassKind::KindInterface:
          $this->mode = GeneratorMode::GeneratingInterKindInterface;
          break;
        case ClassKind::KindTrait:
          $this->mode = GeneratorMode::GeneratingTrait;
          break;
        default:
          $this->mode = GeneratorMode::GeneratingClass;
          break;
      }
      $this->classLocalInits = new \Std\PhaseArray([]);
      $this->classStaticInits = new \Std\PhaseArray([]);
      $this->outdent();
      $out->add("\n")->add($this->getIndent())->add("}");
      return $out->toString();
    }

  }

}