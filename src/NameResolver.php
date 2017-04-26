<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeVisitor;
use PhpParser\Node\Stmt;

class NameResolver extends NodeVisitor\NameResolver {
    use NoDynamicProperties;

    /** @var Name[] */
    private $origAliases;

    public function beforeTraverse(array $nodes) {
        parent::beforeTraverse($nodes);
        $this->origAliases = [];
    }

    /*
     * The name resolver only collects lower-cased alias names, as these are necessary
     * for name resolution. However, we want to preserve name case, so we manually
     * collect aliases with original casing here.
     */
    public function enterNode(Node $node) {
        parent::enterNode($node);

        if ($node instanceof Stmt\Use_) {
            foreach ($node->uses as $use) {
                $this->addOrigAlias($use, $node->type, null);
            }
        } elseif ($node instanceof Stmt\GroupUse) {
            foreach ($node->uses as $use) {
                $this->addOrigAlias($use, $node->type, $node->prefix);
            }
        }
    }

    protected function addOrigAlias(Stmt\UseUse $use, $type, Name $prefix = null) {
        // Add prefix for group uses
        $name = $prefix ? Name::concat($prefix, $use->name) : $use->name;
        // Type is determined either by individual element or whole use declaration
        $type |= $use->type;

        if ($type !== Stmt\Use_::TYPE_NORMAL) {
            // We're only interested in class / namespace uses
            return;
        }

        $this->origAliases[$use->alias] = $name;
    }

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

        // Check if there are any relevant use statements
        $lcName = strtolower($name);
        foreach ($this->origAliases as $alias => $orig) {
            $lcOrig = strtolower((string) $orig);
            if ($lcName === $lcOrig || 0 === strpos($lcName, $lcOrig . '\\')) {
                $possibleNames[] = $alias . substr($name, strlen($lcOrig));
            }
        }

        // Return shortest possible name
        usort($possibleNames, function($a, $b) {
            return strlen($a) <=> strlen($b);
        });
        return $possibleNames[0];
    }
}

