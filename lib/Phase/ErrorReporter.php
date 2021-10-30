<?php
namespace Phase {

  use Phase\Language\Position;

  interface ErrorReporter
  {

    public function report(Position $pos, string $message);

  }

}