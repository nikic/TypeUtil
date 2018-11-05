#!/usr/bin/env php
<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser;
use PhpParser\ParserFactory;

error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

function error(string $msg) {
    echo <<<HELP
$msg

Usage: php ./type-util.php add|remove [--options] dir1 dir2 ...

Options:
    --php VERSION         Enable all features supported up to VERSION
                          E.g. --php 7.1
    --[no-]object         Toggle generation of object type    (PHP 7.2)
    --[no-]nullable-types Toggle generation of nullable types (PHP 7.1)
    --[no-]iterable       Toggle generation of iterable type  (PHP 7.1)
    --[no-]strict-types   Toggle use of strict_types          (PHP 7.0)

Examples:
    # Add everything that's possible!
    php ./type-utils.php add path/to/dir
    
    # Only add features available in PHP 7.0
    php ./type-utils.php --php 7.0
    
    # Add everything available in PHP 7.1, apart from strict types
    php ./type-utils.php --php 7.1 --no-strict-types

NOTE: Will directly modify files, assumes that you're using VCS.

HELP;
    exit(1);
}

$cliParser = new CliParser();
[$options, $rest] = $cliParser->parseOptions($argv);
if (count($rest) < 2) {
    error('At least two arguments are required.');
}

$mode = $rest[0];
if ($mode !== 'add' && $mode !== 'remove') {
    error('Mode must be one of "add" or "remove".');
}

$dirs = array_slice($rest, 1);
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
        'comments', 'startLine', 'startFilePos', 'endFilePos',
    ]
]);
$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7, $lexer);

$startTime = microtime(true);
if ('add' === $mode) {
    $nameResolver = new PhpParser\NodeVisitor\NameResolver();
    $extractor = new TypeExtractor($nameResolver);

    echo "Collecting context...\n";
    $context = getContext($extractor, $nameResolver,
        toFileContexts($parser, $fileProvider()));

    echo "Adding type annotations...\n";
    $asts = toFileContexts($parser, $fileProvider());
    modifyFiles($asts, getAddModifier($nameResolver, $extractor, $context, $options));
} else if ('remove' === $mode) {
    $asts = toFileContexts($parser, $fileProvider());
    modifyFiles($asts, getRemoveModifier());
}

$endTime = microtime(true);
echo "Took: ", $endTime - $startTime, "\n";
