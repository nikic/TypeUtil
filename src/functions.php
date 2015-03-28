<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser;

function strContains(string $haystack, string $needle) : bool {
    return strpos($haystack, $needle) !== false;
}

function phpFilesInDirs(array $dirs) : \Generator {
    foreach ($dirs as $dir) {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (!preg_match('/\.php$/', $file->getPathName())) {
                continue;
            }

            yield $file;
        }
    }
}

function astsForFiles(PhpParser\Parser $parser, \Traversable $files) : \Generator {
    foreach ($files as $file) {
        $path = $file->getPathName();
        $code = file_get_contents($path);

        try {
            $stmts = $parser->parse($code);
        } catch (PhpParser\Error $e) {
            echo "$path: {$e->getMessage()}\n";
            continue;
        }

        yield $path => [$code, $stmts];
    }
}

function getContext(
    TypeExtractor $extractor, NameResolver $nameResolver, \Traversable $asts
) : Context {
    $traverser = new PhpParser\NodeTraverser(false);
    $traverser->addVisitor($nameResolver);

    $visitor = new ContextCollector($extractor);
    $traverser->addVisitor($visitor);
    
    foreach ($asts as list(, $stmts)) {
        $traverser->traverse($stmts);
    }

    return $visitor->getContext();
}
