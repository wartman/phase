package phase;

interface ErrorReporter {
  public function hadError():Bool;
  public function report(pos:Position, where:String, message:String):Void;
}
