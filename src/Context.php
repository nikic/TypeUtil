<?php declare(strict_types=1);

namespace TypeUtil;

class Context {
    use NoDynamicProperties;

    // [ClassName => [ClassName]]
    public $parents = [];
    // [ClassName => [MethodName => FunctionInfo]]
    public $typeInfo;

    public function getFunctionInfoForMethod(string $class, string $method) /* : ?FunctionInfo */ {
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

    private function getInheritedFunctionInfo(string $class, string $method) /* : ?FunctionInfo */ {
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

    private function getReflectionFunctionInfo(string $class, string $method) /* : ?FunctionInfo */ {
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

    private function mergeFunctionInfo(FunctionInfo $child, FunctionInfo $parent) {
        $paramTypes = $parent->paramTypes + $child->paramTypes;
        $returnType = $parent->returnType ?? $child->returnType;

        return new FunctionInfo($paramTypes, $returnType);
    }

    private function isKnownClass(string $name) {
        return isset($this->parents[strtolower($name)]);
    }
}
