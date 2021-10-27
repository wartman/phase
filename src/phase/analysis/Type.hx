package phase.analysis;

typedef TypePath = {
  public final namespace:Array<String>;
  public final name:String;
}

typedef ClassType = {
  public final name:String;
  public final namespace:Array<String>;
  public final superclass:Null<Type>;
  public final interfaces:Array<Type>;
  public final fields:Array<Field>;
}

typedef FunctionType = {
  public final name:String;
  public final args:Array<{ name:String, type:Null<Type> }>;
  public final ret:Null<Type>;
}

typedef Field = {
  public final name:String;
  public final kind:FieldKind;
}

enum FieldKind {
  TVar(type:Type);
  TProp(type:Type, getter:Null<String>, setter:Null<String>);
  TMethod(fun:FunctionType);
}

enum abstract ScalarType(String) to String {
  var TArray = 'array';
  var TString = 'string';
  var TInt = 'int';
  var TBool = 'bool';
}

enum Type {
  TUnknown;
  TVoid;
  TPhpScalar(kind:ScalarType);
  TPath(tp:TypePath);
  TFun(fun:FunctionType);
  TClass(cls:ClassType);
  TInstance(cls:ClassType);
}
