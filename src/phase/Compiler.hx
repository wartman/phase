package phase;

import sys.io.File;

using sys.FileSystem;
using haxe.io.Path;
using Lambda;

typedef Module = {
  name:String,
  content:String
};

class Compiler {

  final src:String;
  final dst:String;
  final reporterFactory:(source:String)->ErrorReporter;
  final extensions = [ 'phs', 'phase' ];
  final onComplete:Null<(modules:Array<Module>)->Void>;

  public function new(src, dst, reporterFactory, ?onComplete) {
    this.src = src;
    this.dst = dst;
    this.reporterFactory = reporterFactory;
    this.onComplete = onComplete;
  }

  public function compile() {
    try {
      var modules = compileDir();
      writeModules(modules); 
      Sys.println('Compiled:\n' + [ for (m in modules) '- ' + m.name].join('\n'));
      if (onComplete != null) {
        onComplete(modules);
      }
    } catch (e:PhpGenerator.GeneratorError) {
      Sys.println('Compiling failed');
    }
  }

  // Todo: this loding code is a mess. Come up with something less brittle.
  //       Mostly this is to do with the wild way i decided to iterate
  //       over files.
  function compileDir(?dir:String, ?modules:Array<Module>):Array<Module> {
    var fullPath = dir != null ? Path.join([ src, dir ]) : src;
    if (modules == null) modules = [];

    for (name in FileSystem.readDirectory(fullPath)) {
      var file = Path.join([ dir, name ]);
      var fullFilePath = Path.join([src, file]);
      if (fullFilePath.isDirectory()) {
        compileDir(file, modules);
      } else if (extensions.has(file.extension())) {
        var relName = file.withoutExtension();
        modules.push({
          name: relName, 
          content: compileFile(fullFilePath)
        }); 
      }
    }

    return modules;
  }

  function compileFile(path:String) {
    var source = load(path);
    var reporter = reporterFactory(source);
    var scanner = new Scanner(source, path, reporter);
    var parser = new Parser(scanner.scan(), reporter);
    var generator = new PhpGenerator(parser.parse(), reporter);
    
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
      var value = module.content;
      var dist = Path.join([ dst, name ]).withExtension('php');
      var dir = dist.directory();
      if (!dir.exists()) {
        FileSystem.createDirectory(dir);
      }
      File.saveContent(dist, value);
    }
  }

  function load(path:String):String {
    return File.getContent(path);
  }

}
