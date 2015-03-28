<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

class TypeAnnotationVisitor extends MutatingVisitor {
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
            && !$node instanceof Expr\Closure
        ) {
            return;
        }

        $typeInfo = $this->getFunctionInfo($node);
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
            $this->code->insert($startPos, $this->extractor->getTypeDisplayName($type) . ' ');
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
        $this->code->insert($pos, ' : ' . $this->extractor->getTypeDisplayName($returnType));
    }

    private function getFunctionInfo(Node $node) /* : ?FunctionInfo */ {
        if ($node instanceof Stmt\ClassMethod) {
            return $this->context->getFunctionInfoForMethod($this->className, $node->name);
        }

        $docComment = $node->getDocComment();
        if (null !== $docComment) {
            return $this->extractor->extractFunctionInfo($node->params, $docComment->getText());
        }

        return null;
    }

    private function isNullConstant(Node\Expr $node) : bool {
        return $node instanceof Node\Expr\ConstFetch
            && strtolower($node->name->toString()) === 'null';
    }
}

