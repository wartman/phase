package phase;

import haxe.ds.Map;

class PhpScope {

  public final values:Map<String, PhpKind> = new Map();
  public final enclosing:Null<PhpScope>;
  public final depth:Int;
  public var next(default, null):Null<PhpScope>;

  public function new(?enclosing:PhpScope) {
    this.enclosing = enclosing;
    this.depth = enclosing != null ? enclosing.depth + 1 : 0;
  }

  public function getTop() {
    if (next != null) return next.getTop();
    return this;
  }

  public function push() {
    if (next != null) {
      next.push();
    } else {
      next = new PhpScope(this);
    }
  }

  public function pop():Null<PhpScope> {
    if (next != null && next.next != null) {
      return next.pop();
    }
    var scope = next;
    next = null;
    return scope;
  }

  public function get(key:String):PhpKind {
    var value:PhpKind = null;
    if (next != null) value = next.get(key);
    if (value == null) return values.get(key);
    return value;
  }

  public function define(key:String, value:PhpKind):Void {
    if (next != null) return next.define(key, value);
    values.set(key, value);
  }

  public function getAt(depth:Int) {
    if (depth == this.depth) return this;
    if (next != null) return next.getAt(depth);
    return null;
  }

}
