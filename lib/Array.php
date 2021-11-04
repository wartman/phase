<?php

#[Std\Compiler\Extern()]
interface Array
{

  public function insert(int $index, $value):int;

  public function push($value):int;

  public function copy():\Std\PhaseArray;

  public function filter($f):\Std\PhaseArray;

  public function find($elt):mixed;

  public function map($transform):\Std\PhaseArray;

  public function contains($item):Bool;

  public function indexOf($item):int;

  public function remove($item):Bool;

  public function reverse();

  public function pop();

  public function shift();

  public function sort($f);

  public function join(string $sep):string;

  public function slice(int $pos, int $end = null):\Std\PhaseArray;

  public function concat(\Std\PhaseArray $other);

  public function splice(int $pos, int $len):\Std\PhaseArray;

  public function unshift($item);

  public function count():int;

}