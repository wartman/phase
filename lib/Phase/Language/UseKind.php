<?php
namespace Phase\Language {


  class UseKind extends \Std\PhaseEnum
  {

    public static function UseNormal():UseKind
    {
      return new UseKind(0, "UseNormal", new \Std\PhaseArray([]));
    }

    public static function UseAlias(UseTarget $alias):UseKind
    {
      return new UseKind(1, "UseAlias", new \Std\PhaseArray([$alias]));
    }

    public static function UseSub(\Std\PhaseArray $items):UseKind
    {
      return new UseKind(2, "UseSub", new \Std\PhaseArray([$items]));
    }

  }

}