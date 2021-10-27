package phase;

import sys.io.File;
import sys.FileSystem;

using haxe.io.Path;

class Io {

  final roots:Array<String>;
  final sources:Map<String, String> = [];

  public function new(roots) {
    this.roots = roots;
  }

  public function getSource(path:String) {
    for (root in roots) {
      if (sources.exists(path)) return sources.get(path);
      var fullPath = Path.join([ root, path ]);
      if (FileSystem.exists(fullPath)) {
        var contents = File.getContent(fullPath);
        sources.set(path, contents);
        return contents;
      }
    }
    throw 'Not found: $path';
  }

}
