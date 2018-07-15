<?php declare(strict_types=1);

namespace TypeUtil;

class Context {
    use NoDynamicProperties;

    // [ClassName => [ClassName]]
    public $parents = [];
    // [ClassName => [MethodName => FunctionInfo]]
    public $typeInfo;

    public function getFunctionInfoForMethod(string $class, string $method) : ?FunctionInfo {
        $lowerMethod = strtolower($method);
        $typeInfo = $this->typeInfo[strtolower($class)][$lowerMethod] ?? null;
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
                return $typeInfo;
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

        // As PHP doesn't support variance properly, only allow if it's exactly the same
        return $a->name === $b->name;
    }

    private function isKnownClass(string $name) : bool {
        return isset($this->parents[strtolower($name)]);
    }
}
