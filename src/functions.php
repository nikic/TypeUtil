<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser;
use PhpParser\NodeTraverser;

function strContains(string $haystack, string $needle) : bool {
    return strpos($haystack, $needle) !== false;
}

function strStartsWith(string $haystack, string $needle) : bool {
    return strpos($haystack, $needle) === 0;
}

function strEndsWith(string $haystack, string $needle) : bool {
    return substr($haystack, -strlen($needle)) === $needle;
}

function filesInDirs(array $dirs, string $extension) : \Generator {
    foreach ($dirs as $dir) {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (!strEndsWith($file->getPathName(), '.' . $extension)) {
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
    TypeExtractor $extractor, PhpParser\NodeVisitor\NameResolver $nameResolver, \Traversable $asts
) : Context {
    $traverser = new NodeTraverser();
    $traverser->addVisitor($nameResolver);

    $visitor = new ContextCollector($extractor);
    $traverser->addVisitor($visitor);
    
    foreach ($asts as list(, $stmts)) {
        $traverser->traverse($stmts);
    }

    return $visitor->getContext();
}

function getAddModifier(
    PhpParser\NodeVisitor\NameResolver $nameResolver, TypeExtractor $extractor, Context $context,
    bool $strictTypes, bool $php71
) : callable {
    $traverser = new NodeTraverser();
    $traverser->addVisitor($nameResolver);

    $visitor = new TypeAnnotationVisitor($context, $extractor, $php71);
    $traverser->addVisitor($visitor);

    return function(string $code, array $stmts) use($visitor, $traverser, $strictTypes) {
        $mutableCode = new MutableString($code);
        $visitor->setCode($mutableCode);
        $traverser->traverse($stmts);

        $newCode = $mutableCode->getModifiedString();
        if (!$strictTypes) {
            return $newCode;
        }

        return preg_replace(
            '/^<\?php(?!\s+declare)/', '<?php declare(strict_types=1);', $newCode
        );
    };
}

function getRemoveModifier() : callable {
    $traverser = new PhpParser\NodeTraverser();
    $visitor = new TypeRemovalVisitor();
    $traverser->addVisitor($visitor);

    return function(string $code, array $stmts) use($visitor, $traverser) {
        $mutableCode = new MutableString($code);
        $visitor->setCode($mutableCode);
        $traverser->traverse($stmts);

        $newCode = $mutableCode->getModifiedString();
        return preg_replace('/^<\?php declare\(strict_types=1\);/', '<?php', $newCode);
    };
}

function modifyFiles(\Traversable $asts, callable $modifier) {
    foreach ($asts as $path => list($code, $stmts)) {
        $newCode = $modifier($code, $stmts);
        if ($code !== $newCode) {
            file_put_contents($path, $newCode);
        }
    }
}

