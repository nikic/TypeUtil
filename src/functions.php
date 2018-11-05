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

function toFileContexts(PhpParser\Parser $parser, iterable $files) : \Generator {
    foreach ($files as $file) {
        $path = $file->getPathName();
        $code = file_get_contents($path);

        try {
            $stmts = $parser->parse($code);
        } catch (PhpParser\Error $e) {
            echo "$path: {$e->getMessage()}\n";
            continue;
        }

        yield new FileContext($path, $code, $stmts);
    }
}

function getContext(
    TypeExtractor $extractor, PhpParser\NodeVisitor\NameResolver $nameResolver, iterable $files
) : Context {
    $traverser = new NodeTraverser();
    $traverser->addVisitor($nameResolver);

    $context = new Context();
    $visitor = new ContextCollector($extractor, $context);
    $traverser->addVisitor($visitor);

    /** @var FileContext $file */
    foreach ($files as $file) {
        $context->setFileContext($file);
        $traverser->traverse($file->stmts);
    }

    return $context;
}

function getAddModifier(
    PhpParser\NodeVisitor\NameResolver $nameResolver, TypeExtractor $extractor, Context $context, Options $options
) : callable {
    $traverser = new NodeTraverser();
    $traverser->addVisitor($nameResolver);

    $visitor = new TypeAnnotationVisitor($context, $extractor, $options);
    $traverser->addVisitor($visitor);

    return function(FileContext $file) use($context, $visitor, $traverser, $options) {
        $mutableCode = new MutableString($file->code);
        $visitor->setCode($mutableCode);
        $context->setFileContext($file);
        $traverser->traverse($file->stmts);

        $newCode = $mutableCode->getModifiedString();
        if (!$options->strictTypes) {
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

    return function(FileContext $file) use($visitor, $traverser) {
        $mutableCode = new MutableString($file->code);
        $visitor->setCode($mutableCode);
        $traverser->traverse($file->stmts);

        $newCode = $mutableCode->getModifiedString();
        return preg_replace('/^<\?php declare\(strict_types=1\);/', '<?php', $newCode);
    };
}

function modifyFiles(iterable $files, callable $modifier) {
    /** @var FileContext $file */
    foreach ($files as $file) {
        $newCode = $modifier($file);
        if ($file->code !== $newCode) {
            file_put_contents($file->path, $newCode);
        }
    }
}

