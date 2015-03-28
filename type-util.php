#!/usr/bin/env php
<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser;

error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

function error(string $msg) {
    echo <<<HELP
$msg

Usage: php ./type-util.php add|remove dir1 dir2 ...

NOTE: Will directly modify files, assumes that you're using VCS.

HELP;
    exit(1);
}

// Config
$strictTypes = true;

if ($argc <= 2) {
    error('At least two arguments are required.');
}

$mode = $argv[1];
if ($mode !== 'add' && $mode !== 'remove') {
    error('Mode must be one of "add" or "remove".');
}

$dirs = array_slice($argv, 2);
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        error("$dir is not a directory.");
    }
}
$fileProvider = function() use($dirs) : \Traversable {
    return filesInDirs($dirs, 'php');
};

$lexer = new PhpParser\Lexer\Emulative([
    'usedAttributes' => [
        'comments', 'startLine', 'startFilePos',
    ]
]);
$parser = new PhpParser\Parser($lexer);

$startTime = microtime(true);
if ('add' === $mode) {
    $nameResolver = new NameResolver();
    $extractor = new TypeExtractor($nameResolver);

    echo "Collecting context...\n";
    $context = getContext($extractor, $nameResolver,
        astsForFiles($parser, $fileProvider()));

    echo "Adding type annotations...\n";
    $asts = astsForFiles($parser, $fileProvider());
    modifyFiles($asts, getAddModifier($nameResolver, $extractor, $context, $strictTypes));
} else if ('remove' === $mode) {
    $asts = astsForFiles($parser, $fileProvider());
    modifyFiles($asts, getRemoveModifier());
}

$endTime = microtime(true);
echo "Took: ", $endTime - $startTime, "\n";
