package phase.analysis;

class Scope {
  public final values:Map<String, Type> = new Map();
  public final parent:Null<Scope>;
  public final children:Array<Scope> = [];

  public function new(?parent) {
    this.parent = parent;
  }

  public function declare(name:String, type:Type) {
    values.set(name, type);
  }

  public function isDeclared(name:String) {
    if (!values.exists(name) && parent != null) { 
      return parent.isDeclared(name);
    }
    return values.exists(name);
  }

  public function resolve(name:String):Type {
    if (values.exists(name)) {
      return values.get(name);
    }
    if (parent != null) { 
      return parent.resolve(name);
    }
    return TUnknown;
  }

  public function addChild(child:Scope) {
    children.push(child);
  }

  public function pushChild() {
    var child = new Scope(this);
    addChild(child);
    return child;
  }
}
