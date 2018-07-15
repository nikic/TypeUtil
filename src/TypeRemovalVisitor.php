<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

class TypeRemovalVisitor extends MutatingVisitor {
    public function enterNode(Node $node) {
        if (!$node instanceof Stmt\Function_
            && !$node instanceof Stmt\ClassMethod
            && !$node instanceof Expr\Closure
        ) {
            return;
        }

        if (null !== $node->returnType) {
            $startPos = $this->getReturnTypeHintPos($node);
            $this->code->remove($startPos, $this->getTypeHintLength($startPos, false));
        }

        foreach ($node->params as $param) {
            if (null === $param->type) {
                continue;
            }

            // Handle special case of nullable type that has null default
            // In this case it's enough to remove the explicit "?"
            if ($param->type instanceof Node\NullableType
                && $param->default && $this->isNullConstant($param->default)
                && !$this->isScalarType($param->type->type)
            ) {
                $startPos = $param->getStartFilePos();
                $result = preg_match('/\?\s*/', $this->code->getOrigString(), $matches, 0, $startPos);
                assert($result === 1);
                $this->code->remove($startPos, strlen($matches[0]));
                continue;
            }

            if ($param->type instanceof Node\NullableType || $this->isScalarType($param->type)) {
                $startPos = $param->getStartFilePos();
                $this->code->remove($startPos, $this->getTypeHintLength($startPos, true));
            }
        }
    }

    private function isScalarType($type) : bool {
        return $type instanceof Node\Identifier
            && in_array((string) $type, ['bool', 'int', 'float', 'string']);
    }

    private function getTypeHintLength(int $startPos, bool $withTrailingWhitespace) : int {
        $code = $this->code->getOrigString();
        // Capture typehint, skipping characters at the start
        $trailing = $withTrailingWhitespace ? '\s*' : '';
        $result = preg_match(
            '/.*?(?:\?\s*)?[a-zA-Z_\x7f-\xff\\\\][a-zA-Z0-9_\x7f-\xff\\\\]*' . $trailing . '/',
            $code, $matches, 0, $startPos
        );
        assert($result === 1);
        return strlen($matches[0]);
    }

    private function isNullConstant(Node\Expr $node) : bool {
        return $node instanceof Node\Expr\ConstFetch
        && strtolower($node->name->toString()) === 'null';
    }
}

