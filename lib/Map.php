<?php

#[Std\Compiler\Extern()]
interface Map
{

  public function set(mixed $key, mixed $value);

  public function get(string $key):mixed;

  public function contains(string $key):Bool;

  public function remove(string $key):Bool;

  public function keys():\Std\PhaseArray;

  public function copy():\Std\PhaseMap;

  public function toString():string;

  public function clear();

  public function count():int;

}