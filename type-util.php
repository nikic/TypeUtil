<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser;
use PhpParser\Node;
use PhpParser\Node\Stmt;

error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

class TypeInfo {
    use NoDynamicProperties;

    public $paramTypes;
    public $returnType;

    public function __construct(array $paramTypes, $returnType) {
        $this->paramTypes = $paramTypes;
        $this->returnType = $returnType;
    }
}

// TODO Upstream this
class NameResolver extends PhpParser\NodeVisitor\NameResolver {
    public function doResolveClassName($name) {
        return $this->resolveClassName($name);
    }
}

class TypeModificationVisitor extends PhpParser\NodeVisitorAbstract {
    use NoDynamicProperties;

    protected $code;

    public function setCode(MutableString $code) {
        $this->code = $code;
    }

    protected function getReturnTypeHintPos(int $funcStartPos) : int {
        $pos = $this->code->indexOf(')', $funcStartPos);
        assert(false !== $pos);
        return $pos + 1;
    }
}

class TypeAnnotationVisitor extends TypeModificationVisitor {
    private $context;
    private $extractor;
    private $className;

    public function __construct(Context $context, TypeExtractor $extractor) {
        $this->context = $context;
        $this->extractor = $extractor;
    }

    public function enterNode(Node $node) {
        if ($node instanceof Stmt\ClassLike) {
            $this->className = $node->namespacedName->toString();
            return;
        }

        if (!$node instanceof Stmt\Function_
            && !$node instanceof Stmt\ClassMethod
            && !$node instanceof Node\Expr\Closure
        ) {
            return;
        }

        $typeInfo = $this->getTypeInfo($node);
        if (null === $typeInfo) {
            return;
        }

        $paramTypes = $typeInfo->paramTypes;
        foreach ($node->params as $i => $param) {
            if ($param->type !== null) {
                // Already has a typehint, leave it alone
                continue;
            }

            $type = $paramTypes[$i];
            if (null === $type) {
                // No type information for this param, or too complex type
                continue;
            }

            if ($type->isNullable) {
                $default = $param->default;
                if ($default === null || !$this->isNullConstant($default)) {
                    // Type is nullable, but no null default is specified.
                    // Leave it alone to avoid accidentially making something optional.
                    continue;
                }
            }

            $startPos = $param->getAttribute('startFilePos');
            $this->code->insert($startPos, $this->getTypeString($type) . ' ');
        }

        $returnType = $typeInfo->returnType;
        if (null === $returnType || null !== $node->returnType) {
            return;
        }

        if ($returnType->isNullable) {
            // No nullable return types yet
            return;
        }

        $pos = $this->getReturnTypeHintPos($node->getAttribute('startFilePos'));
        $this->code->insert($pos, ' : ' . $this->getTypeString($returnType));
    }

    private function getTypeInfo(Node $node) /* : ?TypeInfo */ {
        if ($node instanceof Stmt\ClassMethod) {
            return $this->context->getTypeInfoForMethod($this->className, $node->name);
        }

        $docComment = $node->getDocComment();
        if (null !== $docComment) {
            return $this->extractor->extractTypeInfo($node->params, $docComment->getText());
        }

        return null;
    }

    private function isNullConstant(Node\Expr $node) : bool {
        return $node instanceof Node\Expr\ConstFetch
            && strtolower($node->name->toString()) === 'null';
    }

    private function getTypeString(Type $type) : string {
        if (!$type->isClassHint()) {
            return $type->name;
        }

        // TODO
        return '\\' . $type->name;
    }
}

class TypeRemovalVisitor extends TypeModificationVisitor {
    public function enterNode(Node $node) {
        if (!$node instanceof Stmt\Function_
            && !$node instanceof Stmt\ClassMethod
            && !$node instanceof Node\Expr\Closure
        ) {
            return;
        }

        if (null !== $node->returnType) {
            $startPos = $this->getReturnTypeHintPos($node->getAttribute('startFilePos'));
            $this->code->remove($startPos, $this->getTypeHintLength($startPos));
        }

        foreach ($node->params as $param) {
            if (null !== $param->type && $this->isScalarType($param->type)) {
                $startPos = $param->getAttribute('startFilePos');
                $this->code->remove($startPos, $this->getTypeHintLength($startPos) + 1);
            }
        }
    }

    private function isScalarType($type) {
        return $type instanceof Node\Name
            && in_array($type->toString(), ['bool', 'int', 'float', 'string']);
    }

    private function getTypeHintLength($startPos) {
        $code = $this->code->getOrigString();
        // Capture typehint, skipping characters at the start
        $result = preg_match(
            '/.*?[a-zA-Z_\x7f-\xff\\\\][a-zA-Z0-9_\x7f-\xff\\\\]*/',
            $code, $matches, 0, $startPos
        );
        assert($result === 1);
        return strlen($matches[0]);
    }
}

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
