<?php
namespace Phase\Reporter {

  use Phase\ErrorReporter;
  use Phase\Source;
  use Phase\Language\Position;

  class DefaultErrorReporter implements ErrorReporter
  {

    public function __construct(Source $source)
    {
      $this->source = $source;
    }

    public Source $source;

    public function report(Position $pos, string $message)
    {
      $content = $this->source->content;
      $start = $pos->start;
      $end = $pos->end;
      $text = (new \Std\PhaseString($content))->substring($start, $end);
      $arrows = "^";
      while ($start > 0)
      {
        $t = $content[--$start];
        if ($t === "\n")
        {
          break;
        }
        else
        {
          $text = $t . $text;
          $arrows = " " . $arrows;
        }
      }
      while ($end <= (new \Std\PhaseString($content))->length)
      {
        $t = $content[$end++];
        if ($t === "\n")
        {
          break;
        }
        else
        {
          $text = $text . $t;
        }
      }
      $line = (new \Std\PhaseString((new \Std\PhaseString($content))->substring(0, $end)))->split("\n")->length - 1;
      $textLines = (new \Std\PhaseString($text))->split("\n");
      $begin = 0;
      $this->print("");
      $this->print("ERROR: " . ($pos->file) . ":" . ($line) . " [" . ($start) . " " . ($end) . "]");
      foreach ($textLines as $t)
      {
        $this->print($t);
      }
      $this->print($message);
      $this->print("");
    }

    public function print(string $str)
    {
      echo("" . ($str) . "" . (PHP_EOL) . "");
    }

  }

}