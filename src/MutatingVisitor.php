<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class MutatingVisitor extends NodeVisitorAbstract {
    use NoDynamicProperties;

    /** @var MutableString */
    protected $code;

    public function setCode(MutableString $code) {
        $this->code = $code;
    }

    // TODO: Move this somewhere more appropriate
    protected function getReturnTypeHintPos(Node\FunctionLike $funcNode) : int {
        // Start looking at maximum known position in function signature
        $maxPos = $funcNode->getAttribute('startFilePos');
        foreach ($funcNode->getParams() as $param) {
            $maxPos = $param->getAttribute('endFilePos') + 1;
        }

        // And find the closing parentheses of the signature
        $pos = $this->code->indexOf(')', $maxPos);
        assert(false !== $pos);
        return $pos + 1;
    }
}

