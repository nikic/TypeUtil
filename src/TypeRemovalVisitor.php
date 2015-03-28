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

