<?php
namespace Phase {

  use Phase\Language\Token;
  use Phase\Language\TokenType;
  use Phase\Language\Position;

  class Scanner
  {

    public function __construct(Source $source, ErrorReporter $reporter)
    {
      $this->reporter = $reporter;
      $this->source = $source;
      $this->tokens = new \Std\PhaseArray([]);
    }

    static public $keywords;

    protected Source $source;

    protected \Std\PhaseArray $tokens;

    protected ErrorReporter $reporter;

    protected int $start;

    protected int $end;

    public function scan()
    {
      $this->tokens = new \Std\PhaseArray([]);
      $this->start = 0;
      $this->end = 0;
      while (!$this->isAtEnd())
      {
        $this->start = $this->end;
        $this->scanToken();
      }
      $this->addToken(TokenType::TokEof);
      return $this->tokens;
    }

    public function scanToken()
    {
      $c = $this->advance();
      switch ($c)
      {
        case "(":
          $this->addToken(TokenType::TokLeftParen);
          break;
        case ")":
          $this->addToken(TokenType::TokRightParen);
          break;
        case "{":
          $this->addToken(TokenType::TokLeftBrace);
          break;
        case "}":
          $this->addToken(TokenType::TokRightBrace);
          break;
        case "[":
          $this->addToken(TokenType::TokLeftBracket);
          break;
        case "]":
          $this->addToken(TokenType::TokRightBracket);
          break;
        case "|":
          if ($this->matches("|"))
          {
            $this->addToken(TokenType::TokBoolOr);
          }
          else
          {
            if ($this->matches(">"))
            {
              $this->addToken(TokenType::TokPipe);
            }
            else
            {
              $this->addToken(TokenType::TokBar);
            }
          }
          break;
        case "&":
          if ($this->matches("&"))
          {
            $this->addToken(TokenType::TokBoolAnd);
          }
          else
          {
            $this->addToken(TokenType::TokAnd);
          }
          break;
        case ",":
          $this->addToken(TokenType::TokComma);
          break;
        case ".":
          $this->addToken(TokenType::TokDot);
          break;
        case "-":
          if ($this->matches(">"))
          {
            $this->addToken(TokenType::TokArrow);
          }
          else
          {
            if ($this->matches("-"))
            {
              $this->addToken(TokenType::TokMinusMinus);
            }
            else
            {
              $this->addToken(TokenType::TokMinus);
            }
          }
          break;
        case "+":
          if ($this->matches("="))
          {
            $this->addToken(TokenType::TokPlusEqual);
          }
          else
          {
            if ($this->matches("+"))
            {
              $this->addToken(TokenType::TokPlusPlus);
            }
            else
            {
              $this->addToken(TokenType::TokPlus);
            }
          }
          break;
        case ";":
          $this->addToken(TokenType::TokSemicolon);
          break;
        case ":":
          if ($this->matches(":"))
          {
            $this->addToken(TokenType::TokScopeResolutionOperator);
          }
          else
          {
            $this->addToken(TokenType::TokColon);
          }
          break;
        case "*":
          $this->addToken(TokenType::TokStar);
          break;
        case "@":
          $this->addToken(TokenType::TokAt);
          break;
        case "#":
          $this->addToken(TokenType::TokSharp);
          break;
        case "$":
          $this->addToken(TokenType::TokDollar);
          break;
        case "!":
          $this->addToken($this->matches("=") ? TokenType::TokBangEqual : TokenType::TokBang);
          break;
        case "?":
          $this->addToken(TokenType::TokQuestion);
          break;
        case "=":
          $this->addToken($this->matches("=") ? TokenType::TokBoolEqual : TokenType::TokEqual);
          break;
        case "<":
          $this->addToken($this->matches("=") ? TokenType::TokLessEqual : TokenType::TokLess);
          break;
        case ">":
          $this->addToken($this->matches("=") ? TokenType::TokGreaterEqual : TokenType::TokGreater);
          break;
        case "/":
          if ($this->matches("/"))
          {
            while ($this->peek() != "\n" && !$this->isAtEnd())
            {
              $this->advance();
            }
          }
          else
          {
            $this->addToken(TokenType::TokSlash);
          }
          break;
        case "\"":
          $this->string();
          break;
        case "'":
          $this->string("'");
          break;
        case " ":

          break;
        case "\r":

          break;
        case "\t":

          break;
        case "\n":
          $this->newline();
          break;
        default:
          if ($this->isDigit($c))
          {
            $this->number();
          }
          else
          {
            if ($this->isUcAlpha($c))
            {
              $this->typeIdentifier();
            }
            else
            {
              if ($this->isAlpha($c))
              {
                $this->identifier();
              }
              else
              {
                $this->reporter->report(new Position(start: $this->start, end: $this->end, file: $this->source->file), "Unexpected character: $c");
              }
            }
          }
          break;
      }
    }

    protected function isAtEnd()
    {
      return $this->end >= (strlen($this->source->content));
    }

    protected function peek()
    {
      if ($this->isAtEnd())
      {
        return "";
      }
      return $this->source->content[$this->end];
    }

    protected function peekNext()
    {
      if ($this->isAtEnd())
      {
        return "";
      }
      return $this->source->content[$this->end + 1];
    }

    protected function newline()
    {
      while ($this->peek() == "\n" && !$this->isAtEnd())
      {
        $this->advance();
      }
      $this->addToken(TokenType::TokNewline);
    }

    protected function typeIdentifier()
    {
      while ($this->isAlphaNumeric($this->peek()))
      $this->advance();
      $this->addToken(TokenType::TokTypeIdentifier);
    }

    protected function identifier()
    {
      while ($this->isAlphaNumeric($this->peek()))
      $this->advance();
      $text = $this->getText();
      $type = isset(static::$keywords[$text]) ? static::$keywords[$text] : null;
      if (($this->peek() == "\"" || $this->peek() == "'") && $type == null)
      {
        $type = TokenType::TokTemplateTag;
      }
      if ($type != null)
      {
        $this->addToken($type);
      }
      else
      {
        $this->addToken(TokenType::TokIdentifier);
      }
    }

    protected function string(string $quote = "\"", int $depth = 0)
    {
      while ($this->peek() != $quote && !$this->isAtEnd())
      {
        if ($this->peek() == "$" && $this->peekNext() == "{")
        {
          $this->addToken(TokenType::TokInterpolation, $this->getText());
          $this->advance();
          $this->advance();
          $this->interpolatedString($quote, $depth);
          return;
        }
        $this->advance();
      }
      if ($this->isAtEnd())
      {
        $this->reporter->report(new Position(start: $this->start, end: $this->end, file: $this->source->file), "Unterminated string");
        return;
      }
      $this->advance();
      $value = (function ($it = null)
      {
        return substr($it, 1, (strlen($it)) - 2);
      })($this->getText());
      $this->addToken(TokenType::TokString, $value);
    }

    protected function interpolatedString(string $quote, int $depth)
    {
      $depth = $depth + 1;
      $brackets = 1;
      if ($depth > 6)
      {
        $this->reporter->report(new Position(start: $this->start, end: $this->end, file: $this->source->file), "Interpolation too deep: only 5 levels allowed");
      }
      while ($brackets > 0 && !$this->isAtEnd())
      {
        $this->start = $this->end;
        $this->scanToken();
        if ($this->peek() == "{")
        {
          $brackets = $brackets + 1;
        }
        if ($this->peek() == "}")
        {
          $brackets = $brackets - 1;
        }
      }
      $this->start = $this->end;
      $depth = $depth - 1;
      $this->string($quote, $depth);
    }

    protected function number()
    {
      while ($this->isDigit($this->peek()) && !$this->isAtEnd())
      {
        $this->advance();
      }
      if ($this->peek() == "." && $this->isDigit($this->peekNext()))
      {
        $this->advance();
        while ($this->isDigit($this->peek()) && !$this->isAtEnd())
        {
          $this->advance();
        }
      }
      $this->addToken(TokenType::TokNumber, $this->getText());
    }

    protected function isDigit(string $c):Bool
    {
      return $c >= "0" && $c <= "9";
    }

    protected function isAlpha(string $c):Bool
    {
      return ($c >= "a" && $c <= "z") || ($c >= "A" && $c <= "Z") || $c == "_";
    }

    public function isUcAlpha(string $c):Bool
    {
      return ($c >= "A" && $c <= "Z");
    }

    protected function isAlphaNumeric(string $c):Bool
    {
      return $this->isAlpha($c) || $this->isDigit($c);
    }

    protected function advance()
    {
      return $this->source->content[$this->end++];
    }

    protected function matches(string $expected):Bool
    {
      if ($this->isAtEnd())
      {
        return false;
      }
      if ($this->source->content[$this->end] != $expected)
      {
        return false;
      }
      $this->end++;
      return true;
    }

    protected function addToken($type, $literal = "")
    {
      $text = $this->getText();
      $pos = new Position($this->start, $this->end, $this->source->file);
      $this->tokens->push(new Token($type, $text, $literal, $pos));
    }

    protected function getText()
    {
      return (function ($it = null)
      {
        return substr($it, $this->start, $this->end - $this->start);
      })($this->source->content);
    }

  }
  Scanner::$keywords = [
    TokenType::TokClass => TokenType::TokClass,
    TokenType::TokInterface => TokenType::TokInterface,
    TokenType::TokTrait => TokenType::TokTrait,
    TokenType::TokEnum => TokenType::TokEnum,
    TokenType::TokExtends => TokenType::TokExtends,
    TokenType::TokImplements => TokenType::TokImplements,
    TokenType::TokEnum => TokenType::TokEnum,
    TokenType::TokStatic => TokenType::TokStatic,
    TokenType::TokPrivate => TokenType::TokPrivate,
    TokenType::TokPublic => TokenType::TokPublic,
    TokenType::TokAbstract => TokenType::TokAbstract,
    TokenType::TokConst => TokenType::TokConst,
    TokenType::TokFalse => TokenType::TokFalse,
    TokenType::TokTrue => TokenType::TokTrue,
    TokenType::TokElse => TokenType::TokElse,
    TokenType::TokFunction => TokenType::TokFunction,
    TokenType::TokFor => TokenType::TokFor,
    TokenType::TokIf => TokenType::TokIf,
    TokenType::TokNull => TokenType::TokNull,
    TokenType::TokReturn => TokenType::TokReturn,
    TokenType::TokSuper => TokenType::TokSuper,
    TokenType::TokThis => TokenType::TokThis,
    TokenType::TokVar => TokenType::TokVar,
    TokenType::TokGlobal => TokenType::TokGlobal,
    TokenType::TokDo => TokenType::TokDo,
    TokenType::TokWhile => TokenType::TokWhile,
    TokenType::TokUse => TokenType::TokUse,
    TokenType::TokNamespace => TokenType::TokNamespace,
    TokenType::TokAs => TokenType::TokAs,
    TokenType::TokIn => TokenType::TokIn,
    TokenType::TokIs => TokenType::TokIs,
    TokenType::TokThrow => TokenType::TokThrow,
    TokenType::TokTry => TokenType::TokTry,
    TokenType::TokCatch => TokenType::TokCatch,
    TokenType::TokSwitch => TokenType::TokSwitch,
    TokenType::TokMatch => TokenType::TokMatch,
    TokenType::TokCase => TokenType::TokCase,
    TokenType::TokDefault => TokenType::TokDefault
  ];

}