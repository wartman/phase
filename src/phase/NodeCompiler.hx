package phase;

import js.lib.Promise;
import phase.PhpGenerator;

using Lambda;
using haxe.io.Path; 
using sys.FileSystem;
using sys.io.File;

typedef PhaseModule = {
  realPath:String,
  name:String,
  source:String,
  generated:String
}

@:expose('Compiler')
class NodeCompiler {

  static final extensions = [ 'phs', 'phase' ];

  @:expose('compile')
  public static function compile(src:String, ?options:PhpGeneratorOptions, ?relative:String):Promise<Array<PhaseModule>> {
    if (relative == null) relative = src.normalize();
    if (options == null) options = { annotation: AnnotateOnClass };
    return Promise.all([ for (name in FileSystem.readDirectory(src)) {
      var path = Path.join([ src, name ]);
      if (path.isDirectory()) {
        compile(path, options, relative);
      } else if (extensions.has(path.extension())) {
        compileFile(path, options, relative);
      } else {
        Promise.resolve(null);
      }
    } ]).then(
      parts -> parts
        .filter(p -> p != null)
        .fold((value:Dynamic, result:Array<PhaseModule>) -> {
          if (Std.is(value, Array)) {
            return result.concat(value);
          }
          result.push(value);
          return result;
        }, [])
    );
  }

  @:expose('write')
  public static function write(dst:String, modules:Array<PhaseModule>):Promise<Array<PhaseModule>> {
    // todo: asyc filesystem?
    for (m in modules) {
      var path = Path.join([ dst, m.name ]).withExtension('php');
      var dir = path.directory();
      if (!dir.exists()) {
        FileSystem.createDirectory(dir);
      }
      File.saveContent(path, m.generated);
    }
    return Promise.resolve(modules);
  }

  static function compileFile(path:String, options:PhpGeneratorOptions, relative:String):Promise<PhaseModule> {
    var source = load(path);
    var reporter = new VisualErrorReporter(source);
    var scanner = new Scanner(source, path, reporter);
    var parser = new Parser(scanner.scan(), reporter);
    var generator = new PhpGenerator(parser.parse(), reporter, options);

    if (reporter.hadError()) {
      return Promise.reject('Parsing failed: ${path}');
    }

    return Promise.resolve({
      realPath: path,
      name: path.substr(relative.length + 1)
        .normalize()
        .withoutExtension(),
      source: source,
      generated: generator.generate()
    });
  }

  static function load(path:String):String {
    return File.getContent(path);
  }

}
