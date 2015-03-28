<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser;
use PhpParser\Node;
use PhpParser\Node\Stmt;

error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

class Type {
    use NoDynamicProperties;
    public $name;
    public $isNullable;

    public function __construct(string $name, bool $isNullable) {
        $this->name = $name;
        $this->isNullable = $isNullable;
    }

    public function isClassHint() : bool {
        switch ($this->name) {
            case 'bool':
            case 'int':
            case 'float':
            case 'string':
            case 'array':
                return false;
            default:
                return true;
        }
    }
}

class TypeInfo {
    use NoDynamicProperties;
    public $paramTypes;
    public $returnType;

    public function __construct(array $paramTypes, $returnType) {
        $this->paramTypes = $paramTypes;
        $this->returnType = $returnType;
    }
}

// Modify a string without invalidating offsets
class MutableString {
    use NoDynamicProperties;

    private $string;
    // [[pos, len, newString]]
    private $modifications = [];

    public function __construct(string $string) {
        $this->string = $string;
    }

    public function insert(int $pos, string $newString) {
        $this->modifications[] = [$pos, 0, $newString];
    }

    public function remove(int $pos, int $len) {
        $this->modifications[] = [$pos, $len, ''];
    }

    public function indexOf(string $str, int $startPos) {
        return strpos($this->string, $str, $startPos);
    }

    public function getOrigString() : string {
        return $this->string;
    }

    public function getModifiedString() : string {
        // Sort by position
        usort($this->modifications, function($a, $b) {
            return $a[0] <=> $b[0];
        });

        $result = '';
        $startPos = 0;
        foreach ($this->modifications as list($pos, $len, $newString)) {
            $result .= substr($this->string, $startPos, $pos - $startPos);
            $result .= $newString;
            $startPos = $pos + $len;
        }
        $result .= substr($this->string, $startPos);
        return $result;
    }
}

// TODO Upstream this
class NameResolver extends PhpParser\NodeVisitor\NameResolver {
    public function doResolveClassName($name) {
        return $this->resolveClassName($name);
    }
}

class Context {
    use NoDynamicProperties;

    // [ClassName => [ClassName]]
    public $parents = [];
    // [ClassName => [MethodName => TypeInfo]]
    public $typeInfo;

    public function getTypeInfoForMethod(string $class, string $method) /* : ?TypeInfo */ {
        $lowerMethod = strtolower($method);
        $typeInfo = $this->typeInfo[strtolower($class)][$lowerMethod] ?? null;
        if ($lowerMethod === '__construct') {
            // __construct is excluded from LSP
            return $typeInfo;
        }

        $inheritedTypeInfo = $this->getInheritedTypeInfo($class, $method);
        if (null === $typeInfo) {
            return $inheritedTypeInfo;
        }

        if (null === $inheritedTypeInfo) {
            return $typeInfo;
        }

        return $this->mergeTypeInfo($typeInfo, $inheritedTypeInfo);
    }

    private function getInheritedTypeInfo(string $class, string $method) /* : ?TypeInfo */ {
        $parents = $this->parents[strtolower($class)] ?? [];
        foreach ($parents as $parent) {
            if (!$this->isKnownClass($parent)) {
                $typeInfo = $this->getReflectionTypeInfo($parent, $method);
                if (null !== $typeInfo) {
                    return $typeInfo;
                }
            }

            $typeInfo = $this->getTypeInfoForMethod($parent, $method);
            if (null !== $typeInfo) {
                return $typeInfo;
            }
        }
        return null;
    }

    private function getReflectionTypeInfo(string $class, string $method) /* : ?TypeInfo */ {
        try {
            $r = new \ReflectionMethod($class, $method);
        } catch (\Exception $e) {
            return null;
        }

        $paramTypes = [];
        foreach ($r->getParameters() as $param) {
            if ($param->isArray()) {
                $type = 'array';
            } else if ($param->isCallable()) {
                $type = 'callable';
            } else if (null !== $class = $param->getClass()) {
                $type = $class->name;
            } else {
                $type = null;
            }
            $paramTypes[] = $type;
        }

        return new TypeInfo($paramTypes, null);
    }

    private function mergeTypeInfo(TypeInfo $child, TypeInfo $parent) {
        $paramTypes = $parent->paramTypes + $child->paramTypes;
        $returnType = $parent->returnType ?? $child->returnType;

        return new TypeInfo($paramTypes, $returnType);
    }

    private function isKnownClass(string $name) {
        return isset($this->parents[strtolower($name)]);
    }
}

class ContextCollector extends PhpParser\NodeVisitorAbstract {
    use NoDynamicProperties;

    private $extractor;
    private $context;
    private $classNode;

    public function __construct(TypeExtractor $extractor) {
        $this->extractor = $extractor;
        $this->context = new Context();
    }

    public function getContext() : Context {
        return $this->context;
    }

    public function enterNode(Node $node) {
        if ($node instanceof Stmt\ClassLike) {
            $this->handleClassLike($node);
        } else if ($node instanceof Stmt\ClassMethod) {
            $this->handleClassMethod($node);
        } else if ($node instanceof Stmt\TraitUse) {
            foreach ($node->traits as $trait) {
                $this->handleTraitUse($trait);
            }
        }
    }

    private function handleClassLike(Stmt\ClassLike $node) {
        $this->classNode = $node;

        $this->context->parents[$this->getLowerClassName()]
            = $this->getParentsOf($node);
    }

    private function handleClassMethod(Stmt\ClassMethod $node) {
        $docComment = $node->getDocComment();
        if ($docComment === null
            || strContains($docComment->getText(), '{@inheritDoc}')
        ) {
            // For our purposes an inherited comment is the same as no comment
            return;
        }

        $this->context->typeInfo[$this->getLowerClassName()][strtolower($node->name)]
            = $this->extractor->extractTypeInfo($node->params, $docComment->getText());
    }

    private function handleTraitUse(Node\Name $name) {
        $lowerName = strtolower($name->toString());

        // Treat parents of the using class as parents of the trait, because it
        // will have to satisfy signatures of those parent methods
        $this->context->parents[$lowerName] = array_unique(array_merge(
            $this->context->parents[$lowerName] ?? [],
            $this->getParentsOf($this->classNode)
        ));
    }

    private function getParentsOf(Stmt\ClassLike $node) : array {
        $parents = [];
        if ($node instanceof Stmt\Class_) {
            if (null !== $node->extends) {
                $parents[] = $node->extends->toString();
            }
            foreach ($node->implements as $interface) {
                $parents[] = $interface->toString();
            }
        } else if ($node instanceof Stmt\Interface_) {
            foreach ($node->extends as $interface) {
                $parents[] = $interface->toString();
            }
        }
        return $parents;
    }

    private function getLowerClassName() : string {
        assert($this->classNode !== null);
        return strtolower($this->classNode->namespacedName->toString());
    }
}

class TypeExtractor {
    use NoDynamicProperties;
    private $nameResolver;

    public function __construct(NameResolver $nameResolver) {
        $this->nameResolver = $nameResolver;
    }

    public function extractTypeInfo(array $params, string $docComment) : TypeInfo {
        $namedParamTypes = $this->getNamedParamTypes($docComment);
        return new TypeInfo(
            $this->getParamTypes($params, $namedParamTypes),
            $this->getReturnType($docComment)
        );
    }

    private function getParamTypes(array $params, array $namedParamTypes) : array {
        $paramTypes = [];
        foreach ($params as $param) {
            $paramTypes[] = $namedParamTypes[$param->name] ?? null;
        }
        return $paramTypes;
    }

    private function getNamedParamTypes(string $docComment) : array {
        if (!preg_match_all('/@param\s+(\S+)\s+\$(\S+)/', $docComment, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $paramTypes = [];
        foreach ($matches as list(, $typeString, $name)) {
            $typeInfo = $this->parseType($typeString);
            if (null !== $typeInfo) {
                $paramTypes[$name] = $typeInfo;
            }
        }

        return $paramTypes;
    }

    private function getReturnType(string $docComment) /* : ?Type */ {
        if (!preg_match('/@return\s+(\S+)/', $docComment, $matches)) {
            return null;
        }

        return $this->parseType($matches[1]);
    }

    private function parseType(string $typeString) /* : ?Type */ {
        $types = explode('|', $typeString);
        $resultType = null;
        $isNullable = false;

        foreach ($types as $type) {
            if ($type === 'null') {
                $isNullable = true;
                continue;
            }
            if ($resultType !== null) {
                // Don't support union types
                return null;
            }

            if (substr($type, -2) === '[]') {
                $resultType = 'array';
                continue;
            }
            if (!$this->isSupportedTypeName($type)) {
                // Generic types are not supported
                return null;
            }
            $resultType = $this->getCanonicalTypeName($type);
        }
        return new Type($resultType, $isNullable);
    }

    private function isSupportedTypeName(string $name) : bool {
        return preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $name) === 1
            && $name !== 'mixed' && $name !== 'void';
    }

    private function getCanonicalTypeName(string $name) : string {
        switch (strtolower($name)) {
            case 'bool':
            case 'boolean':
                return 'bool';
            case 'int':
            case 'integer':
                return 'int';
            case 'float':
            case 'double':
                return 'float';
            case 'string':
                return 'string';
            case 'array':
                return 'array';
            default:
                if ($name[0] === '\\') {
                    return substr($name, 1);
                }

                $name = new Node\Name($name);
                return $this->nameResolver->doResolveClassName($name)->toString();
        }
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
