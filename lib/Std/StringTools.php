<?php
namespace Std {


  class StringTools
  {

    static public function charAt(string $subject, int $index):string
    {
      if ($index < 0)
      {
        return "";
      }
      return mb_substr($subject, $index, 1);
    }

    static public function indexOf(string $subject, string $search, ?int $startIndex = null):int
    {
      if ($startIndex === null)
      {
        $startIndex = 0;
      }
      else
      {
        $length = (new \Std\PhaseString($subject))->length;
        if ($startIndex < 0)
        {
          $startIndex = $startIndex + $length;
          if ($startIndex < 0)
          {
            $startIndex = 0;
          }
        }
        if ($startIndex >= $length && $search === "")
        {
          return -1;
        }
      }
      $index = $search === "" ? $startIndex > (new \Std\PhaseString($subject))->length ? (new \Std\PhaseString($subject))->length : $startIndex : mb_strpos($subject, $search, $startIndex);
      return $index === false ? -1 : $index;
    }

    static public function lastIndexOf(string $subject, string $search, ?int $startIndex = null):int
    {
      $start = $startIndex;
      if ($start === null)
      {
        $start = 0;
      }
      else
      {
        $length = (new \Std\PhaseString($subject))->length;
        if ($start >= 0)
        {
          $start = $start - $length;
          if ($start > 0)
          {
            $start = 0;
          }
        }
        else
        {
          if ($start < -$length)
          {
            $start = -$length;
          }
        }
      }
      $index = $search === "" ? $startIndex === null || $startIndex > (new \Std\PhaseString($subject))->length ? (new \Std\PhaseString($subject))->length : $startIndex : mb_strpos($subject, $search, $start);
      if ($index === false)
      {
        return -1;
      }
      else
      {
        return $index;
      }
    }

  }

}