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
    // trace('loading: $path');
    if (types.exists(path)) {
      // trace('$path ready');
      return types.get(path);
    }
    
    // trace('$path needs typing');

    var ns = path.split('::');
    var name = ns.pop();
    var filePath = ns.concat([ name ]).join('/').normalize().withExtension('phs');
    var source = io.getSource(filePath);
    var reporter = reporterFactory(source);
    var scanner = new Scanner(source, filePath, reporter);
    var parser = new Parser(scanner.scan(), reporter);
    var analyzer = new StaticAnalyzer(parser.parse(), this, reporter);
    var context = analyzer.analyze();

    for (name => type in context.getTypes()) {
      types.set(ns.concat([ name ]).join('::'), type);
    }

    if (!types.exists(path)) {
      throw 'The module $path does not define the type $path';
    }

    return types.get(name);
  }

}
