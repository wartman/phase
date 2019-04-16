package phase;

using StringTools;

class VisualErrorReporter implements ErrorReporter {

  // final loader:ModuleLoader;
  final source:String;

  public function new(source) {
    this.source = source;
  }

  var errorsReported:Int = 0;

  public function hadError():Bool {
    return errorsReported > 0;
  }

  public function report(pos:Position, where:String, message:String) {
    errorsReported++;
    
    // var source = loader.load(pos.file);
    var lines = source.split('\n');
    var limit = lines.length > 5 ? 5 : lines.length;
    var line = pos.line;
    // var offset = pos.offset;

    var start = source.substring(0, pos.offset).split('\n')[line - 1].length - where.length;
    var end = where.length;
    var out = [
      '',
      'ERROR: ${pos.file} [line ${pos.line} column ${start}]:',
      ''
    ];
    var curLine = line - 3;
    if (curLine < 0) curLine = 0;

    do {
      out.push(formatLine(curLine) +  lines[curLine]);
      curLine++;
    } while (curLine < line);

    var spaces = '      | ' + [for (i in 0...start) ' '].join('');
    var markers = spaces + [for (i in 0...end) '^'].join('');
    out.push(markers);
    out.push(spaces + message);

    do {
      var content = lines[curLine];
      if (content == null) content = '';
      out.push(formatLine(curLine) + content);
      curLine++;
    } while (curLine < limit);

    out.push('');

    Sys.println(out.join('\n'));
  }

  function formatLine(curLine:Int) {
    var line = Std.string(curLine + 1);
    if (line.length == 1) return '    ${line} | ';
    if (line.length == 2) return '   ${line} | ';
    if (line.length == 3) return '  ${line} | ';
    if (line.length == 4) return ' ${line} | ';
    return '${line} | ';
  }

}
