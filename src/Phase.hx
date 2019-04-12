import phase.*;

using haxe.io.Path;

@:allow(phase)
class Phase {

  public static final corePaths:Map<String, String> = [
    'Phase' => Path.join([ Sys.programPath(), '../std' ]).normalize()
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

  static function compile(src:String, dist:String) {
    src = Path.join([Sys.getCwd(), src]);
    dist = Path.join([Sys.getCwd(), dist]);
    var compiler = new Compiler(src, dist, source -> new VisualErrorReporter(source));
    compiler.compile();
  }

}
