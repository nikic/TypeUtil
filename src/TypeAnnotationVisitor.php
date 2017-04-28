<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

class TypeAnnotationVisitor extends MutatingVisitor {
    private $context;
    private $extractor;
    private $className;
    private $php71;

    public function __construct(Context $context, TypeExtractor $extractor, bool $php71) {
        $this->context = $context;
        $this->extractor = $extractor;
        $this->php71 = $php71;
    }

    public function enterNode(Node $node) {
        if ($node instanceof Stmt\ClassLike) {
            $this->className = $node->namespacedName->toString();
            return;
        }

        if (!$node instanceof Node\FunctionLike) {
            return;
        }

        $typeInfo = $this->getFunctionInfo($node);
        if (null === $typeInfo) {
            return;
        }

        $paramTypes = $typeInfo->paramTypes;
        foreach ($node->getParams() as $i => $param) {
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
                if ($default !== null && $this->isNullConstant($default)) {
                    // Type is nullable and has null default. We can add the type
                    // as a non-nullable type in this case, which is compatible with PHP 7.0
                    $type = $type->asNotNullable();
                } else if (!$this->php71) {
                    // No support for proper nullable types in PHP 7.0
                    continue;
                }
            }

            $startPos = $param->getAttribute('startFilePos');
            $this->code->insert($startPos, $this->extractor->getTypeDisplayName($type) . ' ');
        }

        $returnType = $typeInfo->returnType;
        if (null === $returnType || null !== $node->getReturnType()) {
            return;
        }

        if ($returnType->isNullable && !$this->php71) {
            // No nullable return types in PHP 7.0
            return;
        }

        $pos = $this->getReturnTypeHintPos($node);
        $this->code->insert($pos, ' : ' . $this->extractor->getTypeDisplayName($returnType));
    }

    private function getFunctionInfo(Node\FunctionLike $node) /* : ?FunctionInfo */ {
        if ($node instanceof Stmt\ClassMethod) {
            return $this->context->getFunctionInfoForMethod($this->className, (string) $node->name);
        }

        $docComment = $node->getDocComment();
        if (null !== $docComment) {
            return $this->extractor->extractFunctionInfo($node->getParams(), $docComment->getText());
        }

        return null;
    }

    private function isNullConstant(Node\Expr $node) : bool {
        return $node instanceof Node\Expr\ConstFetch
            && strtolower($node->name->toString()) === 'null';
    }
}

