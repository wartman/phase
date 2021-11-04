package phase;

import phase.analysis.StaticAnalyzer;
import phase.analysis.Type;

using haxe.io.Path;

class Server {
  
  final io:Io;
  final reporterFactory:(source:String)->ErrorReporter;
  final types:Map<String, Type> = [];

  public function new(io, reporterFactory) {
    this.io = io;
    this.reporterFactory = reporterFactory;
  }

  public function locateType(path:String):Type {
    if (types.exists(path)) {
      return types.get(path);
    }
    
    trace('    finding: $path');
    var ns = path.split('::');
    var name = ns.pop();
    var filePath = ns.concat([ name ]).join('/').normalize().withExtension('phs');
    var source = io.getSource(filePath);
    var reporter = reporterFactory(source);
    trace('       scanning: $path');
    var scanner = new Scanner(source, filePath, reporter);
    trace('       parsing: $path');
    var parser = new Parser(scanner.scan(), reporter); // this is the choke point
    trace('       analyzing: $path');
    var analyzer = new StaticAnalyzer(parser.parse(), this, reporter);
    var loaded = analyzer.analyzeSurface();

    for (name => type in loaded) {
      types.set(name, type);
    }

    if (!types.exists(path)) {
      throw 'The module $path does not define the type $path';
    }

    trace('    done: $path');
    return types.get(name);
  }

}
