package phase;

import sys.io.File;
import sys.FileSystem;

using haxe.io.Path;

class DefaultModuleLoader implements ModuleLoader {

  final root:String;
  final mappings:Map<String, String>;
  final extensions:Array<String> = [ 'phs', 'phase' ];

  public function new(?root:String, ?mappings:Map<String, String>) {
    this.root = root != null ? root : Sys.getCwd();
    this.mappings = mappings != null? mappings : new Map();
  }

  public function load(path:String):String {
    for (pattern in mappings.keys()) {
      var re = new EReg('^' + pattern, 'i');
      if (re.match(path)) {
        path = resolve(re.replace(path, mappings.get(pattern)).normalize());
        return File.getBytes(path).toString();
      }
    }

    path = resolve(Path.join([ root, path ]).normalize());
    return File.getBytes(path).toString();
  }

  function resolve(path:String) {
    for (ext in extensions) {
      var resolved = path.withExtension(ext);
      if (FileSystem.exists(resolved)) return resolved;
    }
    // Todo: replace with an error class
    throw 'The file [${path}] does not exist';
    return null;
  }

}
