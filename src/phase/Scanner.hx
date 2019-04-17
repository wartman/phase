package phase;

import phase.TokenType;

class Scanner {

  static final keywords:Map<String, TokenType> = [
    TokClass => TokClass,
    TokInterface => TokInterface,
    TokTrait => TokTrait,
    TokEnum => TokEnum,
    TokExtends => TokExtends,
    TokImplements => TokImplements,
    TokEnum => TokEnum,
    TokStatic => TokStatic,
    TokPrivate => TokPrivate,
    TokPublic => TokPublic,
    TokAbstract => TokAbstract,
    TokConst => TokConst,
    TokFalse => TokFalse,
    TokTrue => TokTrue,
    TokElse => TokElse,
    TokFunction => TokFunction,
    TokFor => TokFor,
    TokIf => TokIf,
    TokNull => TokNull,
    TokReturn => TokReturn,
    TokSuper => TokSuper,
    TokThis => TokThis,
    TokVar => TokVar,
    TokWhile => TokWhile,
    TokUse => TokUse,
    TokPackage => TokPackage,
    TokAs => TokAs,
    TokIn => TokIn,
    TokThrow => TokThrow,
    TokTry => TokTry,
    TokCatch => TokCatch,
    TokSwitch => TokSwitch,
    TokCase => TokCase,
    TokDefault => TokDefault
  ];

  var file:String;
  var source:String;
  var start:Int;
  var current:Int;
  var line:Int;
  var tokens:Array<Token>;
  var reporter:ErrorReporter;

  public function new(source:String, file:String, reporter:ErrorReporter) {
    this.source = source;
    this.file = file;
    this.reporter = reporter;
  }

  public function scan():Array<Token> {
    tokens = [];
    start = 0;
    current = 0;
    line = 1;

    while (!isAtEnd()) {
      start = current;
      scanToken();
    }
    
    tokens.push(new Token(TokEof, '', null, {line: line, offset: current, file: file}));
    return tokens;
  }

  function scanToken() {
    var c = advance();
    switch c {
      case '(': addToken(TokLeftParen);
      case ')': addToken(TokRightParen);
      case '{': addToken(TokLeftBrace);
      case '}': addToken(TokRightBrace);
      case '[': addToken(TokLeftBracket);
      case ']': addToken(TokRightBracket);
      case '|': addToken(match('|') ? TokBoolOr : match('>') ? TokPipe : TokBar);
      case '&': addToken(match('&') ? TokBoolAnd : TokAnd);
      case ',': addToken(TokComma);
      case '.': addToken(match('.') ? TokRange : TokDot);
      case '-': addToken(match('-') ? TokMinusMinus : TokMinus);
      case '+': 
        if (match('=')) {
          addToken(TokPlusEqual);
        } else if(match('+')) {
          addToken(match('+') ? TokConcat : TokPlusPlus);
        } else {
          addToken(TokPlus);
        }
      case ';': addToken(TokSemicolon);
      case ':': addToken(match(':') ? TokScopeResolutionOperator : TokColon);
      case '*': addToken(TokStar);
      case '@': addToken(TokAt);
      case '#': addToken(TokSharp);
      case '!': addToken(match('=') ? TokBangEqual : TokBang);
      case '=': addToken(match('=') ? TokBoolEqual : TokEqual);
      case '<': addToken(match('=') ? TokLessEqual : TokLess);
      case '>': addToken(match('=') ? TokGreaterEqual : TokGreater);
      case '/' if (match('/')):
        // Comment
        while (peek() != '\n' && !isAtEnd()) advance();
        if (peek() == '\n') {
          line++;
          advance(); // Consume the newline too.
        }
      case '/': addToken(TokSlash);
      case '"': string();
      case "'": string("'");
      case ' ' | '\r' | '\t': null; // ignore
      case '\n': newline(); // Might be a valid statement end -- checked by the parser.
      default:
        if (isDigit(c)) {
          number();
        } else if (isUcAlpha(c)) {
          typeIdentifier();
        } else if (isAlpha(c)) {
          identifier();
        } else {
          reporter.report({
            line: line,
            offset: current,
            file: file
          }, c, 'Unexpected character: $c');
        }
    }
  }

  function typeIdentifier() {
    while (isAlphaNumeric(peek())) advance();
    addToken(TokTypeIdentifier);
  }

  function identifier() {
    while (isAlphaNumeric(peek())) advance();

    var text = source.substring(start, current);
    var type = keywords.get(text);

    if ((peek() == '"' || peek() == "'") && type == null) {
      type = TokTemplateTag;
    }
    
    if (type != null) {
      addToken(type);
    } else {
      addToken(TokIdentifier);
    }
  }

  function newline() {
    line++;
    // todo: may need to handle windows newline too :P
    while (peek() == '\n' && !isAtEnd()) {
      line++;
      advance();
    }
    addToken(TokNewline);
  }

  function string(quote:String = '"', depth:Int = 0) {
    while (peek() != quote && !isAtEnd()) {
      if (peek() == '\n') {
        line++;
      }
      if (peek() == '$' && peekNext() == '{') {
        addToken(TokInterpolation, source.substring(start + 1, current));
        // consume `${`
        advance();
        advance();
        interpolatedString(quote, depth);
        return;
      }
      advance();
    }
    if (isAtEnd()) {
      // Note: this error is particularly useless
      reporter.report({
        line: line,
        offset: current,
        file: file
      }, '<EOF>', 'Unterminated string.');
      return;
    }

    // The closing "
    advance();

    var value = source.substring(start + 1, current - 1);
    addToken(TokString, value);
  }

  function interpolatedString(quote:String = '"', depth:Int) {
    depth = depth + 1;
    var brackets = 1;

    if (depth > 6) {
      reporter.report({
        line: line,
        offset: current,
        file: file
      }, '', 'Interpolation too deep: only 5 levels allowed');
    }

    while (brackets > 0 && !isAtEnd()) {
      start = current;
      scanToken();
      // Need to do this so that we can have lambdas and
      // object literals inside interpolations. Otherwise,
      // ANY `}` will stop this loop. 
      if (peek() == '{') {
        brackets += 1;
      }
      if (peek() == '}') {
        brackets -= 1;
      }
    }

    start = current;
    depth = depth - 1;
    
    // Continue parsing.
    string(quote, depth);
  }

  function number() {
    while(isDigit(peek())) advance();
    if (peek() == '.' && isDigit(peekNext())) {
      advance();
      while (isDigit(peek())) advance();
    }
    addToken(TokNumber, Std.parseFloat(source.substring(start, current)));
  }

  function isAtEnd():Bool {
    return current >= source.length;
  }

  function isDigit(c:String):Bool {
    return c >= '0' && c <= '9';
  }

  function isUcAlpha(c:String):Bool {
    return (c >= 'A' && c <= 'Z');
  }

  function isAlpha(c:String):Bool {
    return (c >= 'a' && c <= 'z') ||
           (c >= 'A' && c <= 'Z') ||
            c == '_';
  }

  function isAlphaNumeric(c:String) {
    return isAlpha(c) || isDigit(c);
  }

  function match(expected:String):Bool {
    if (isAtEnd()) {
      return false;
    }
    if (source.charAt(current) != expected) {
      return false;
    }
    current++;
    return true;
  }

  function peek():String {
    if (isAtEnd()) {
      return '';
    }
    return source.charAt(current);
  }

  function peekNext():String {
    if (isAtEnd()) {
      return '';
    }
    return source.charAt(current + 1);
  }
  
  function advance() {
    current++;
    return source.charAt(current - 1);
  }

  function addToken(type:TokenType, ?literal:Dynamic) {
    var text = source.substring(start, current);
    var pos:Position = { line: line, offset: current, file: file };
    tokens.push(new Token(type, text, literal, pos));
  }

}