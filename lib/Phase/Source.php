<?php
namespace Phase {


  class Source
  {

    public function __construct(string $file, string $content)
    {
      $this->content = $content;
      $this->file = $file;
    }

    public string $file;

    public string $content;

  }

}