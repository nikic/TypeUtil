<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\NodeVisitorAbstract;

class MutatingVisitor extends NodeVisitorAbstract {
    use NoDynamicProperties;

    protected $code;

    public function setCode(MutableString $code) {
        $this->code = $code;
    }

    // TODO: Move this somewhere more appropriate
    protected function getReturnTypeHintPos(int $funcStartPos) : int {
        $pos = $this->code->indexOf(')', $funcStartPos);
        assert(false !== $pos);
        return $pos + 1;
    }
}

