<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\NodeVisitor;

// TODO Upstream this
class NameResolver extends NodeVisitor\NameResolver {
    public function doResolveClassName($name) {
        return $this->resolveClassName($name);
    }
}

