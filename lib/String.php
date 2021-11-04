<?php

#[Std\Compiler\Extern()]
interface String
{

  public function toLowerCase():string;

  public function toUpperCase():string;

  public function split(string $sep):\Std\PhaseArray;

  public function substr(int $pos, ?int $end = null):string;

  public function substring(int $startIndex, ?int $endIndex = null):string;

}