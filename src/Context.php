<?php declare(strict_types=1);

namespace TypeUtil;

class Context {
    use NoDynamicProperties;

    /** @var ClassInfo[] */
    private $classInfos = [];

    // [ClassName => [ClassName]]
    // The reason why this is separate from ClassInfo is that we need to record trait
    // pseudo-parents possibly prior to seeing the trait.
    public $parents = [];

    public function addClassInfo(ClassInfo $info) {
        $this->classInfos[strtolower($info->name)] = $info;
    }

    public function getFunctionInfoForMethod(string $class, string $method) : ?FunctionInfo {
        $lowerClass = strtolower($class);
        $lowerMethod = strtolower($method);
        if (!isset($this->classInfos[$lowerClass])) {
            return null;
        }

        $classInfo = $this->classInfos[$lowerClass];
        $typeInfo = $classInfo->funcInfos[$lowerMethod] ?? null;
        if ($lowerMethod === '__construct') {
            // __construct is excluded from LSP
            return $typeInfo;
        }

        $inheritedFunctionInfo = $this->getInheritedFunctionInfo($class, $method);
        if (null === $typeInfo) {
            return $inheritedFunctionInfo;
        }

        if (null === $inheritedFunctionInfo) {
            return $typeInfo;
        }

        return $this->mergeFunctionInfo($typeInfo, $inheritedFunctionInfo);
    }

    private function getInheritedFunctionInfo(string $class, string $method) : ?FunctionInfo {
        $parents = $this->parents[strtolower($class)] ?? [];
        foreach ($parents as $parent) {
            if (!$this->isKnownClass($parent)) {
                $typeInfo = $this->getReflectionFunctionInfo($parent, $method);
                if (null !== $typeInfo) {
                    return $typeInfo;
                }
            }

            $typeInfo = $this->getFunctionInfoForMethod($parent, $method);
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
            if ($param->isArray()) {
                $type = 'array';
            } else if ($param->isCallable()) {
                $type = 'callable';
            } else if (null !== $class = $param->getClass()) {
                $type = $class->name;
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

        // We don't check for class subtypes, as PHP does not support this in LSP checks
        return false;
    }

    private function isKnownClass(string $name) : bool {
        return isset($this->parents[strtolower($name)]);
    }
}
