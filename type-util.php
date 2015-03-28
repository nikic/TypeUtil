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
    return phpFilesInDirs($dirs);
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
    $traverser = new PhpParser\NodeTraverser(false);
    $traverser->addVisitor($nameResolver);

    $visitor = new TypeAnnotationVisitor($context, $extractor);
    $traverser->addVisitor($visitor);

    foreach (astsForFiles($parser, $fileProvider()) as $path => list($code, $stmts)) {
        $mutableCode = new MutableString($code);
        $visitor->setCode($mutableCode);
        $traverser->traverse($stmts);

        $newCode = $mutableCode->getModifiedString();

        if ($strictTypes) {
            $newCode = preg_replace(
                '/^<\?php(?! declare)/', '<?php declare(strict_types=1);', $newCode
            );
        }

        if ($newCode !== $code) {
            file_put_contents($path, $newCode);
        }
    }
} else if ('remove' === $mode) {
    $traverser = new PhpParser\NodeTraverser(false);
    $visitor = new TypeRemovalVisitor();
    $traverser->addVisitor($visitor);

    foreach (astsForFiles($parser, $fileProvider()) as $path => list($code, $stmts)) {
        $mutableCode = new MutableString($code);
        $visitor->setCode($mutableCode);
        $traverser->traverse($stmts);

        $newCode = $mutableCode->getModifiedString();
        $newCode = preg_replace('/^<\?php declare\(strict_types=1\);/', '<?php', $newCode);

        if ($newCode !== $code) {
            file_put_contents($path, $newCode);
        }
    }
}

$endTime = microtime(true);
echo "Took: ", $endTime - $startTime, "\n";
