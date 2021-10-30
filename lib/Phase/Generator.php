<?php
namespace Phase {


  interface Generator
  {

    protected \Std\PhaseArray $statements;

    protected ErrorReporter $reporter;

    public function generate():string;

  }

}