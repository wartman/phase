<?php
namespace Phase\Reporter {

  use Phase\ErrorReporter;
  use Phase\Language\Position;

  class DefaultErrorReporter implements ErrorReporter
  {

    public function __construct()
    {

    }

    public function report(Position $pos, string $message)
    {
      print($message);
    }

  }

}