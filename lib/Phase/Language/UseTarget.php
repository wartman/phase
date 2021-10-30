<?php
namespace Phase\Language {


  class UseTarget extends \Std\PhaseEnum
  {

    public static function TargetType(string $name):UseTarget
    {
      return new UseTarget(0, "TargetType", new \Std\PhaseArray([$name]));
    }

    public static function TargetFunction(string $name):UseTarget
    {
      return new UseTarget(1, "TargetFunction", new \Std\PhaseArray([$name]));
    }

  }

}