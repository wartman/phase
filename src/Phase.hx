import phase.*;

using haxe.io.Path;

@:allow(phase)
class Phase {

  public static final corePaths:Array<String> = [
    Path.join([ Sys.programPath().directory().directory().directory(), 'std' ]).normalize()
  ];

  public static function main() {
    var args = Sys.args();
    switch args.length {
      case 2: 
        compile(args[0], args[1]);
      default:
        Sys.print('Usage: phase [src] [dst]');
    }
  }

  public static function compile(src:String, dist:String, ?onComplete) {
    src = Path.join([Sys.getCwd(), src]);
    dist = Path.join([Sys.getCwd(), dist]);
    var compiler = new Compiler(
      src,
      dist,
      corePaths,
      source -> new VisualErrorReporter(source),
      onComplete
    );
    compiler.compile();
  }

}
