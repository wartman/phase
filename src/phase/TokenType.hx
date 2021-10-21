package phase;

enum abstract TokenType(String) to String {

  var TokAt = '@';
  var TokSharp = '#';
  var TokDollar = '$';
  var TokLeftParen = '(';
  var TokRightParen = ')';
  var TokLeftBrace = '{';
  var TokRightBrace = '}';
  var TokLeftBracket = '[';
  var TokRightBracket = ']';
  var TokBar = '|';
  var TokComma = ',';
  var TokDot = '.';
  var TokMinus = '-';
  var TokMinusMinus = '--';
  var TokPlus = '+';
  var TokPlusPlus = '++';
  var TokPlusEqual = '+=';
  var TokColon = ':';
  var TokSemicolon = ';';
  var TokNewline = '[newline]';
  var TokSlash = '\\';
  var TokStar = '*';
  var TokAnd = '&';
  var TokBoolAnd = '&&';
  var TokBoolOr = '||';
  var TokScopeResolutionOperator = '::';
  var TokBang = '!';
  var TokBangEqual = '!=';
  var TokEqual = '=';
  var TokBoolEqual = '==';
  var TokGreater = '>';
  var TokGreaterEqual = '>=';
  var TokLess = '<';
  var TokLessEqual = '<=';
  var TokRange = '...';
  var TokConcat = '+++';
  var TokPipe = '|>';

  var TokClass = 'class';
  var TokInterface = 'interface';
  var TokTrait = 'trait';
  var TokExtends = 'extends';
  var TokImplements = 'implements';
  var TokEnum = 'enum';
  var TokStatic = 'static';
  var TokPrivate = 'private';
  var TokPublic = 'public';
  var TokAbstract = 'abstract';
  var TokConst = 'const';
  var TokFalse = 'false';
  var TokTrue = 'true';
  var TokElse = 'else';
  var TokFunction = 'function';
  var TokFor = 'for';
  var TokIf = 'if';
  var TokNull = 'null';
  var TokReturn = 'return';
  var TokSuper = 'super';
  var TokThis = 'this';
  var TokVar = 'var';
  var TokWhile = 'while';
  var TokUse = 'use';
  var TokNamespace = 'namespace';
  var TokAs = 'as';
  var TokIn = 'in';
  var TokIs = 'is';
  var TokThrow = 'throw';
  var TokTry = 'try';
  var TokCatch = 'catch';
  var TokSwitch = 'switch';
  var TokCase = 'case';
  var TokDefault = 'default';
  var TokGlobal = 'global';

  var TokIdentifier = '[identifier]';
  var TokTypeIdentifier = '[type-identifier]'; // An identifier that starts upper-case.
  var TokString = '[string]';
  var TokNumber = '[number]';
  var TokTemplateTag = '[template]';
  
  // Interpolated strings, like `"foo ${bar}"`, are parsed
  // as if they were written `"foo" ++ bar`. For example, the
  // token stream for `"foo ${bar} bin"` would look like:
  // 
  //  TokInterpolation // -> "foo"
  //  TokIdentifier // -> bar
  //  TokString // -> "bin"
  //
  var TokInterpolation = '[interpolation]';

  var TokEof = '[eof]';

}
