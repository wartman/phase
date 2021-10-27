package phase.analysis;

class TypeTools {
  public static function getTypeName(type:Type) {
    return switch type {
      case TPath(tp): 
        tp.namespace.concat([ tp.name ]).join('::');
      case TClass(cls) | TInstance(cls): 
        cls.namespace.concat([ cls.name ]).join('::');
      case TFun(fun): 
        fun.name;
      case TUnknown: 
        '<unknown>';
      case TVoid:
        '<void>';
      case TPhpScalar(kind):
        '<$$$kind>';
    }
  }
}
