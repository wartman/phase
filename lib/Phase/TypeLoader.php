<?php
namespace Phase {

  use Std\Io\Path;
  use Phase\Language\Type;
  use Phase\Language\TypePath;
  use Phase\Reporter\DefaultErrorReporter;

  class TypeLoader
  {

    public function __construct(string $root)
    {
      $this->root = new Path($root);
    }

    protected Path $root;

    public function load(TypePath $tp):?Type
    {
      $name = $tp->toFileName();
      $typeName = $tp->notAbsolute()->toString();
      $path = $this->root->with($name)->withExtension("phs");
      $content = file_get_contents($path->toString());
      $source = new Source(file: $path, content: $content);
      $reporter = new DefaultErrorReporter($source);
      $scanner = new Scanner($source, $reporter);
      $parser = new Parser($scanner->scan(), $reporter);
      $typer = new Typer($parser->parse(), $reporter);
      $types = $typer->typeSurface();
      if ($types->contains($typeName))
      {
        return $types->get($typeName);
      }
      return Type::TUnknown(null);
    }

  }

}