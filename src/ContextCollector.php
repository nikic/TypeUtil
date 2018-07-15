<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\{ClassLike, ClassMethod, Class_, Interface_, TraitUse};

class ContextCollector extends NodeVisitorAbstract {
    use NoDynamicProperties;

    private $extractor;
    private $context;
    private $classNode;
    /** @var ClassInfo */
    private $classInfo;

    public function __construct(TypeExtractor $extractor) {
        $this->extractor = $extractor;
        $this->context = new Context();
    }

    public function getContext() : Context {
        return $this->context;
    }

    public function enterNode(Node $node) {
        if ($node instanceof ClassLike) {
            $this->handleClassLike($node);
        } else if ($node instanceof ClassMethod) {
            $this->handleClassMethod($node);
        } else if ($node instanceof TraitUse) {
            foreach ($node->traits as $trait) {
                $this->handleTraitUse($trait);
            }
        }
    }

    private function handleClassLike(ClassLike $node) {
        $this->classNode = $node;
        $this->classInfo = new ClassInfo($node->namespacedName->toString());
        $this->context->addClassInfo($this->classInfo);

        $this->context->parents[$node->namespacedName->toLowerString()]
            = $this->getParentsOf($node);
    }

    private function handleClassMethod(ClassMethod $node) {
        $docComment = $node->getDocComment();
        if ($docComment === null
            || strContains($docComment->getText(), '{@inheritDoc}')
        ) {
            // For our purposes an inherited comment is the same as no comment
            return;
        }

        $this->classInfo->funcInfos[$node->name->toLowerString()]
            = $this->extractor->extractFunctionInfo($node->params, $docComment->getText());
    }

    private function handleTraitUse(Name $name) {
        $lowerName = $name->toLowerString();

        // Treat parents of the using class as parents of the trait, because it
        // will have to satisfy signatures of those parent methods
        $this->context->parents[$lowerName] = array_unique(array_merge(
            $this->context->parents[$lowerName] ?? [],
            $this->getParentsOf($this->classNode)
        ));
    }

    private function getParentsOf(ClassLike $node) : array {
        $parents = [];
        if ($node instanceof Class_) {
            if (null !== $node->extends) {
                $parents[] = $node->extends->toString();
            }
            foreach ($node->implements as $interface) {
                $parents[] = $interface->toString();
            }
        } else if ($node instanceof Interface_) {
            foreach ($node->extends as $interface) {
                $parents[] = $interface->toString();
            }
        }
        return $parents;
    }
}
