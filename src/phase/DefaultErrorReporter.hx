package phase;

class DefaultErrorReporter implements ErrorReporter {

  public function new() {}

  var errorsReported:Int = 0;

  public function hadError():Bool {
    return errorsReported > 0;
  }

  public function report(pos:Position, where:String, message:String) {
    errorsReported++;
    Sys.println('ERROR: ${pos.file} [line ${pos.line}]:');
    Sys.println('   ${message}');
  }

}
