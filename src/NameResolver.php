<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\NodeVisitor;
use PhpParser\Node\Stmt;

class NameResolver extends NodeVisitor\NameResolver {
    use NoDynamicProperties;

    // TODO Upstream this
    public function doResolveClassName($name) {
        return $this->resolveClassName($name);
    }

    /** Returns shortest version of name according to current import list */
    public function getShortestName(string $name) : string {
        // Start off with the FQCN
        $possibleNames = ['\\' . $name];

        if (null !== $this->namespace) {
            // If class is part of current namespace, we can drop the namespace prefix
            $namespacePrefix = $this->namespace . '\\';
            if (strStartsWith($name, $namespacePrefix)) {
                $possibleNames[] = substr($name, strlen($namespacePrefix));
            }
        } else {
            // Outside namespace the leading \ is not necessary
            $possibleNames[] = $name;
        }

        // TODO damn, we only have lowercased alias info here...
        /*$aliases = $this->aliases[Stmt\Use_::TYPE_NORMAL];
        foreach ($aliases as $alias => $orig) {
        }*/

        // Return shortest possible name
        usort($possibleNames, function($a, $b) {
            return strlen($a) <=> strlen($b);
        });
        return $possibleNames[0];
    }
}

