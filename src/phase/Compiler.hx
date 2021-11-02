package phase;

import sys.io.File;

using sys.FileSystem;
using haxe.io.Path;
using Lambda;

typedef Module = {
  name:String,
  build:()->String
};

class Compiler {

  final src:String;
  final dst:String;
  final libs:Array<String>;
  final reporterFactory:(source:String)->ErrorReporter;
  final extensions = [ 'phs', 'phase' ];
  final onComplete:Null<(modules:Array<Module>)->Void>;

  public function new(src, dst, libs, reporterFactory, ?onComplete) {
    this.src = src;
    this.dst = dst;
    this.libs = libs;
    this.reporterFactory = reporterFactory;
    this.onComplete = onComplete;
  }

  public function compile() {
    try {
      var server = new Server(new Io(libs.concat([ src ])), reporterFactory);
      var modules = compileDir(null, null, server);
      writeModules(modules); 
      Sys.println('Compiled:\n' + [ for (m in modules) '- ' + m.name].join('\n'));
      if (onComplete != null) {
        onComplete(modules);
      }
    } catch (e:PhpGenerator.GeneratorError) {
      Sys.println('Compiling failed');
    }
  }
  
  function compileDir(?dir:String, ?modules:Array<Module>, server:Server):Array<Module> {
    var fullPath = dir != null ? Path.join([ src, dir ]) : src;
    if (modules == null) modules = [];

    for (name in FileSystem.readDirectory(fullPath)) {
      var file = Path.join([ dir, name ]);
      var fullFilePath = Path.join([src, file]);
      if (fullFilePath.isDirectory()) {
        compileDir(file, modules, server);
      } else if (extensions.has(file.extension())) {
        var relName = file.withoutExtension();
        modules.push({
          name: relName, 
          build: () -> compileFile(fullFilePath, server)
        }); 
      }
    }

    return modules;
  }

  function compileFile(path:String, server:Server) {
    var source = load(path);
    var reporter = reporterFactory(source);
    var scanner = new Scanner(source, path, reporter);
    var parser = new Parser(scanner.scan(), reporter);
    var generator = new PhpGenerator(parser.parse(), reporter, server);
    
    var data = generator.generate();
    if (reporter.hadError()) {
      throw new PhpGenerator.GeneratorError();
      return '';
    }
    return data;
  }

  function writeModules(modules:Array<Module>) {
    for (module in modules) {
      var name = module.name;
      var source = Path.join([ src, name ]).withExtension('phs');
      var dist = Path.join([ dst, name ]).withExtension('php');
      var dir = dist.directory();
      if (!dir.exists()) {
        FileSystem.createDirectory(dir);
      }
      // if (
      //   !FileSystem.exists(dist)
      //   || (FileSystem.stat(source).mtime.getTime() > FileSystem.stat(dist).mtime.getTime())
      // ) {
        var value = module.build();
        File.saveContent(dist, value);
      // }
    }
  }

  function load(path:String):String {
    return File.getContent(path);
  }

}
