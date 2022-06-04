<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

class TypeAnnotationVisitor extends MutatingVisitor {
    private $context;
    private $extractor;
    /** @var Options */
    private $options;
    /** @var Stmt\ClassLike */
    private $classNode;

    public function __construct(Context $context, TypeExtractor $extractor, Options $options) {
        $this->context = $context;
        $this->extractor = $extractor;
        $this->options = $options;
    }

    public function enterNode(Node $node) {
        if ($node instanceof Stmt\ClassLike) {
            $this->classNode = $node;
            return;
        }

        if ($node instanceof Node\FunctionLike) {
            $this->enterFunctionLike($node);
            return;
        }

        if ($node instanceof Stmt\Property) {
            $this->enterProperty($node);
            return;
        }
    }

    private function enterFunctionLike(Node\FunctionLike $node): void {
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
                } else if (!$this->options->nullableTypes) {
                    continue;
                }
            }

            if (!$this->isTypeSupported($type)) {
                continue;
            }

            $startPos = $param->getStartFilePos();
            $this->code->insert($startPos, $this->extractor->getTypeDisplayName($type) . ' ');
        }

        $returnType = $typeInfo->returnType;
        if (null === $returnType || null !== $node->getReturnType()) {
            return;
        }

        if ($returnType->isNullable && !$this->options->nullableTypes) {
            return;
        }

        if (!$this->isTypeSupported($returnType)) {
            return;
        }

        $pos = $this->getReturnTypeHintPos($node);
        $this->code->insert($pos, ' : ' . $this->extractor->getTypeDisplayName($returnType));
    }

    private function enterProperty(Stmt\Property $node) {
        if ($node->type !== null || !$this->options->propertyTypes) {
            // Already has a type or property types not desired.
            return;
        }

        if (count($node->props) !== 1) {
            // We don't handle multi-properties
            return;
        }

        $prop = $node->props[0];
        $type = $this->context->getPropertyType(
            $this->context->getClassKey($this->classNode), $prop->name->toString());

        // callable is not allowed in property types
        if ($type === null || !$this->isTypeSupported($type) || $type->name === 'callable') {
            return;
        }

        $pos = $prop->getStartFilePos();
        $this->code->insert($pos, $this->extractor->getTypeDisplayName($type) . ' ');
    }

    private function getFunctionInfo(Node\FunctionLike $node) : ?FunctionInfo {
        if ($node instanceof Stmt\ClassMethod) {
            return $this->context->getFunctionInfoForMethod(
                $this->context->getClassKey($this->classNode), (string) $node->name);
        }

        $docComment = $node->getDocComment();
        if (null !== $docComment) {
            return $this->extractor->extractFunctionInfo($node->getParams(), $docComment->getText(), null);
        }

        return null;
    }

    private function isNullConstant(Node\Expr $node) : bool {
        return $node instanceof Node\Expr\ConstFetch
            && strtolower($node->name->toString()) === 'null';
    }

    private function isTypeSupported(Type $type) {
        if ($type->name === 'iterable' && !$this->options->iterable) {
            return false;
        }
        if ($type->name === 'object' && !$this->options->object) {
            return false;
        }
        return true;
    }
}

