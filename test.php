<?php

function autoload($class)
{
    if (!class_exists($class))
    {
        $file = __DIR__ . '/dist/test/' . $class . '.php';
        include($file);
    } 
}

function listDir($path) {
  return array_diff(scandir($path), array('.', '..'));
}

spl_autoload_register('autoload');

require __DIR__ . '/dist/test/Expect.php';

foreach (listDir(__DIR__ . '/dist/test/Test/Language') as $folder) {
  foreach (listDir(__DIR__ . '/dist/test/Test/Language/' . $folder) as $file) {
    echo 'Running test: Test::Language::' . $folder . '::' . $file . PHP_EOL;
    include __DIR__ . '/dist/test/Test/Language/' . $folder . '/' . $file;
    echo '  PASSED' . PHP_EOL;
  }
}

foreach (listDir(__DIR__ . '/dist/test/Test/Phase') as $file) {
    echo 'Running test: Test::Phase::' . $file . PHP_EOL;
    include __DIR__ . '/dist/test/Test/Phase/' . $file;
    echo '  PASSED' . PHP_EOL;
}
