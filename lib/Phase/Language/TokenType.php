<?php
namespace Phase\Language {


  class TokenType
  {

    const TokAt = "@";

    const TokSharp = "#";

    const TokDollar = "$";

    const TokLeftParen = "(";

    const TokRightParen = ")";

    const TokLeftBrace = "{";

    const TokRightBrace = "}";

    const TokLeftBracket = "[";

    const TokRightBracket = "]";

    const TokBar = "|";

    const TokComma = ",";

    const TokDot = ".";

    const TokMinus = "-";

    const TokMinusMinus = "--";

    const TokPlus = "+";

    const TokPlusPlus = "++";

    const TokPlusEqual = "+=";

    const TokColon = ":";

    const TokSemicolon = ";";

    const TokNewline = "[newline]";

    const TokSlash = "\\";

    const TokStar = "*";

    const TokAnd = "&";

    const TokQuestion = "?";

    const TokBoolAnd = "&&";

    const TokBoolOr = "||";

    const TokScopeResolutionOperator = "::";

    const TokBang = "!";

    const TokBangEqual = "!==";

    const TokEqual = "=";

    const TokBoolEqual = "===";

    const TokGreater = ">";

    const TokGreaterEqual = ">=";

    const TokLess = "<";

    const TokLessEqual = "<=";

    const TokRange = "...";

    const TokConcat = "+++";

    const TokPipe = "|>";

    const TokArrow = "->";

    const TokClass = "class";

    const TokInterface = "interface";

    const TokTrait = "trait";

    const TokEnum = "enum";

    const TokNamespace = "namespace";

    const TokExtends = "extends";

    const TokImplements = "implements";

    const TokStatic = "static";

    const TokPrivate = "private";

    const TokPublic = "public";

    const TokAbstract = "abstract";

    const TokConst = "const";

    const TokFalse = "false";

    const TokTrue = "true";

    const TokElse = "else";

    const TokFunction = "function";

    const TokFor = "for";

    const TokIf = "if";

    const TokNull = "null";

    const TokReturn = "return";

    const TokSuper = "super";

    const TokThis = "this";

    const TokVar = "var";

    const TokGlobal = "global";

    const TokDo = "do";

    const TokWhile = "while";

    const TokUse = "use";

    const TokPackage = "namespace";

    const TokAs = "as";

    const TokIn = "in";

    const TokIs = "is";

    const TokThrow = "throw";

    const TokTry = "try";

    const TokCatch = "catch";

    const TokSwitch = "switch";

    const TokMatch = "match";

    const TokCase = "case";

    const TokDefault = "default";

    const TokIdentifier = "[identifier]";

    const TokTypeIdentifier = "[type-identifier]";

    const TokString = "[string]";

    const TokNumber = "[number]";

    const TokTemplateTag = "[template]";

    const TokInterpolation = "[interpolation]";

    const TokEof = "[eof]";

  }

}