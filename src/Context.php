<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\Node\Stmt\ClassLike;

class Context {
    use NoDynamicProperties;

    /** @var FileContext */
    private $currentFile;

    /** @var ClassInfo[] */
    private $classInfos = [];

    // [ClassName => [ClassName]]
    // The reason why this is separate from ClassInfo is that we need to record trait
    // pseudo-parents possibly prior to seeing the trait.
    public $parents = [];

    public function setFileContext(FileContext $file) {
        $this->currentFile = $file;
    }

    public function getClassKey(ClassLike $node): string {
        if (isset($node->namespacedName)) {
            return $node->namespacedName->toLowerString();
        }
        return "anon#{$this->currentFile->path}#{$node->getStartFilePos()}";
    }

    public function addClassInfo(string $key, ClassInfo $info) {
        $this->classInfos[$key] = $info;
    }

    public function getFunctionInfoForMethod(string $classKey, string $method) : ?FunctionInfo {
        $lowerMethod = strtolower($method);
        $classInfo = $this->classInfos[$classKey] ?? null;
        $typeInfo = $classInfo->funcInfos[$lowerMethod] ?? null;
        if ($lowerMethod === '__construct') {
            // __construct is excluded from LSP
            return $typeInfo;
        }

        $inheritedFunctionInfo = $this->getInheritedFunctionInfo($classKey, $method);
        if (null === $typeInfo) {
            return $inheritedFunctionInfo;
        }

        if (null === $inheritedFunctionInfo) {
            return $typeInfo;
        }

        return $this->mergeFunctionInfo($typeInfo, $inheritedFunctionInfo);
    }

    public function getPropertyType(string $classKey, string $property) : ?Type {
        $classInfo = $this->classInfos[$classKey] ?? null;
        $type = $classInfo->propTypes[$property] ?? null;
        $inheritedType = $this->getInheritedPropertyType($classKey, $property);
        if ($type === null) {
            return $inheritedType;
        }
        // TODO Handle invariance
        return $type;
    }

    private function getInheritedPropertyType(string $classKey, string $property) : ?Type {
        $parents = $this->parents[$classKey] ?? [];
        foreach ($parents as $parent) {
            if (!$this->isKnownClass($parent)) {
                // Could get property type from reflection here, but there are no internal classes using property types
                // right now...
                continue;
            }

            $type = $this->getPropertyType(strtolower($parent), $property);
            if (null !== $type) {
                return $type;
            }
        }
        return null;
    }

    private function getInheritedFunctionInfo(string $classKey, string $method) : ?FunctionInfo {
        $parents = $this->parents[$classKey] ?? [];
        foreach ($parents as $parent) {
            if (!$this->isKnownClass($parent)) {
                $typeInfo = $this->getReflectionFunctionInfo($parent, $method);
                if (null !== $typeInfo) {
                    return $typeInfo;
                }
            }

            $typeInfo = $this->getFunctionInfoForMethod(strtolower($parent), $method);
            if (null !== $typeInfo) {
                return $this->resolveFunctionInfoTypes($typeInfo);
            }
        }
        return null;
    }

    private function getReflectionFunctionInfo(string $class, string $method) : ?FunctionInfo {
        try {
            $r = new \ReflectionMethod($class, $method);
        } catch (\Exception $e) {
            return null;
        }

        $paramTypes = [];
        foreach ($r->getParameters() as $param) {
            $isNullable = $param->allowsNull();
            if ($param->isArray()) {
                $type = Type::fromString('array', $isNullable);
            } else if ($param->isCallable()) {
                $type = Type::fromString('callable', $isNullable);
            } else if (null !== $class = $param->getClass()) {
                $type = Type::fromString($class->name, $isNullable);
            } else {
                $type = null;
            }
            $paramTypes[] = $type;
        }

        return new FunctionInfo($paramTypes, null);
    }

    private function resolveFunctionInfoTypes(FunctionInfo $info) : FunctionInfo {
        return new FunctionInfo(
            array_map(function(?Type $type) {
                return $type !== null ? $type->asResolvedType() : null;
            }, $info->paramTypes),
            $info->returnType !== null ? $info->returnType->asResolvedType() : null
        );
    }

    private function mergeFunctionInfo(FunctionInfo $child, FunctionInfo $parent) : FunctionInfo {
        $paramTypes = $this->mergeParamTypesArray($parent->paramTypes, $child->paramTypes);
        $returnType = $this->mergeReturnTypes($parent->returnType, $child->returnType);

        return new FunctionInfo($paramTypes, $returnType);
    }

    private function mergeParamTypesArray(array $parentTypes, array $childTypes) : array {
        $resultTypes = [];
        foreach ($childTypes as $i => $childType) {
            if (isset($parentTypes[$i])) {
                $resultTypes[$i] = $this->mergeParamTypes($parentTypes[$i], $childType);
            } else {
                $resultTypes[$i] = $childType;
            }
        }
        return $resultTypes;
    }

    private function mergeParamTypes(?Type $parent, ?Type $child) : ?Type {
        if ($this->isSubtype($parent, $child) && $child !== null) {
            return $child;
        }
        return $parent;
    }

    private function mergeReturnTypes(?Type $parent, ?Type $child) : ?Type {
        if ($this->isSubtype($child, $parent)) {
            return $child;
        }
        return $parent;
    }

    private function isSubtype(?Type $a, ?Type $b) : bool {
        if ($b === null) {
            // No type means "mixed", of which everything is a subtype
            return true;
        }
        if ($a === null) {
            // "mixed" is not a subtype of anything but "mixed"
            return false;
        }

        if ($a->isNullable && !$b->isNullable) {
            // Nullable is not a subtype of non-nullable
            return false;
        }

        $a = $a->asNotNullable();
        $b = $b->asNotNullable();

        if ($a->name === $b->name) {
            return true;
        }

        if ($b->name === 'iterable') {
            return $a->name === 'array';
        }

        if ($b->name === 'object') {
            return $a->isClassHint();
        }

        // We don't check for class subtypes, as PHP does not support this in LSP checks
        return false;
    }

    private function isKnownClass(string $name) : bool {
        return isset($this->parents[strtolower($name)]);
    }
}
