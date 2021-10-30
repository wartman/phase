<?php
namespace Phase\Language {


  class Position
  {

    public function __construct(int $start, int $end, string $file)
    {
      $this->file = $file;
      $this->end = $end;
      $this->start = $start;
    }

    public int $start;

    public int $end;

    public string $file;

    public function merge(Position $other)
    {
      return new Position(start: $this->start, end: $other->end, file: $this->file);
    }

  }

}