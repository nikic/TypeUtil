<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\{ClassLike, ClassMethod, Class_, Interface_, Property, TraitUse};

class ContextCollector extends NodeVisitorAbstract {
    use NoDynamicProperties;

    private $extractor;
    private $context;
    private $classNode;
    /** @var ClassInfo */
    private $classInfo;

    public function __construct(TypeExtractor $extractor, Context $context) {
        $this->extractor = $extractor;
        $this->context = $context;
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
        } else if ($node instanceof Property) {
            $this->handleProperty($node);
        }
    }

    private function handleClassLike(ClassLike $node) {
        $parent = null;
        if ($node instanceof Class_) {
            $parent = $node->extends ? $node->extends->toString() : null;
        }

        $key = $this->context->getClassKey($node);
        $this->classNode = $node;
        $this->classInfo = new ClassInfo(
            isset($node->namespacedName) ? $node->namespacedName->toString() : '',
            $parent
        );
        $this->context->addClassInfo($key, $this->classInfo);

        $this->context->parents[$key] = $this->getParentsOf($node);
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
            = $this->extractor->extractFunctionInfo($node->params, $docComment->getText(), $this->classInfo);
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

    private function handleProperty(Property $node) {
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return;
        }

        if (count($node->props) !== 1) {
            return;
        }

        $prop = $node->props[0];
        $this->classInfo->propTypes[$prop->name->toString()]
            = $this->extractor->getPropertyType($docComment->getText(), $this->classInfo);
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
