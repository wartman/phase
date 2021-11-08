<?php

function autoload($class)
{
    if (!class_exists($class))
    {
        $file = __DIR__ . '/lib/' . $class . '.php';
        include($file);
    }
}

spl_autoload_register('autoload');

function listDir($path) {
  return array_diff(scandir($path), array('.', '..'));
}

$file = file_get_contents(__DIR__.'/std/Phase/Scanner.phs');
$source = new \Phase\Source(
  content: $file,
  file: __DIR__.'/std/Phase/Scanner.phs'
);
$reporter = new \Phase\Reporter\DefaultErrorReporter($source);
$scanner = new \Phase\Scanner($source, $reporter);
$parser = new \Phase\Parser($scanner->scan(), $reporter);
$stmts = $parser->parse();
$typer = new \Phase\Typer($stmts, $reporter);
$types = $typer->type();
$generator = new \Phase\Generator\PhpGenerator(
  new \Phase\Generator\PhpGeneratorConfig(phpVersion: 8),
  new \Phase\Context(new \Phase\TypeLoader(__DIR__.'/std'), $types),
  $stmts,
  $reporter
);
file_put_contents(
  __DIR__ . '/dist/Scanner.php',
  $generator->generate()
);
// print_r($generator->generate());
