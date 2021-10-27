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

  public function locateType(name:String):Type {
    if (types.exists(name)) return types.get(name);

    var path = name.split('::').join('/').normalize().withExtension('phs');
    var source = io.getSource(path);
    var reporter = reporterFactory(source);
    var scanner = new Scanner(source, path, reporter);
    var parser = new Parser(scanner.scan(), reporter);
    var analyzer = new StaticAnalyzer(parser.parse(), this, reporter);
    var context = analyzer.analyze();

    for (name => type in context.getTypes()) {
      types.set(name, type);
    }

    if (!types.exists(name)) {
      throw 'The module $name does not define the type $name';
    }

    return types.get(name);
  }

}
