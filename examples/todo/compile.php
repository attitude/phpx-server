<?php declare(strict_types=1);

// Compile every .phpx server component in src/ to a sibling .php file.
require __DIR__ . '/../../vendor/autoload.php';

use Attitude\PHPX\Compiler\Compiler;

$compiler = new Compiler();

foreach (glob(__DIR__ . '/src/*.phpx') as $file) {
    $out = substr($file, 0, -1); // .phpx -> .php
    file_put_contents($out, $compiler->compile((string) file_get_contents($file)));
    echo 'compiled ' . basename($file) . ' -> ' . basename($out) . "\n";
}
