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

Usage: php ./type-util.php add|remove [--no-strict-types --php71] dir1 dir2 ...

NOTE: Will directly modify files, assumes that you're using VCS.

HELP;
    exit(1);
}

// Config
$strictTypes = true;
$php71 = false;

if ($argc <= 2) {
    error('At least two arguments are required.');
}

$mode = $argv[1];
if ($mode !== 'add' && $mode !== 'remove') {
    error('Mode must be one of "add" or "remove".');
}

$dirs = [];
foreach (array_slice($argv, 2) as $arg) {
    if ($arg === '--no-strict-types') {
        $strictTypes = false;
        continue;
    }

    if ($arg === '--php71') {
        $php71 = true;
        continue;
    }

    if (!is_dir($arg)) {
        error("$arg is not a directory.");
    }
    $dirs[] = $arg;
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
    modifyFiles($asts, getAddModifier($nameResolver, $extractor, $context, $strictTypes, $php71));
} else if ('remove' === $mode) {
    $asts = toFileContexts($parser, $fileProvider());
    modifyFiles($asts, getRemoveModifier());
}

$endTime = microtime(true);
echo "Took: ", $endTime - $startTime, "\n";
