<?php
namespace Phase\Language {


  class Token
  {

    public function __construct(string $type, string $lexeme, string $literal = null, Position $pos)
    {
      $this->pos = $pos;
      $this->literal = $literal;
      $this->lexeme = $lexeme;
      $this->type = $type;
    }

    public string $type;

    public string $lexeme;

    public string $literal;

    public Position $pos;

  }

}