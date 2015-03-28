<?php declare(strict_types=1);

namespace TypeUtil;

class Context {
    use NoDynamicProperties;

    // [ClassName => [ClassName]]
    public $parents = [];
    // [ClassName => [MethodName => TypeInfo]]
    public $typeInfo;

    public function getTypeInfoForMethod(string $class, string $method) /* : ?TypeInfo */ {
        $lowerMethod = strtolower($method);
        $typeInfo = $this->typeInfo[strtolower($class)][$lowerMethod] ?? null;
        if ($lowerMethod === '__construct') {
            // __construct is excluded from LSP
            return $typeInfo;
        }

        $inheritedTypeInfo = $this->getInheritedTypeInfo($class, $method);
        if (null === $typeInfo) {
            return $inheritedTypeInfo;
        }

        if (null === $inheritedTypeInfo) {
            return $typeInfo;
        }

        return $this->mergeTypeInfo($typeInfo, $inheritedTypeInfo);
    }

    private function getInheritedTypeInfo(string $class, string $method) /* : ?TypeInfo */ {
        $parents = $this->parents[strtolower($class)] ?? [];
        foreach ($parents as $parent) {
            if (!$this->isKnownClass($parent)) {
                $typeInfo = $this->getReflectionTypeInfo($parent, $method);
                if (null !== $typeInfo) {
                    return $typeInfo;
                }
            }

            $typeInfo = $this->getTypeInfoForMethod($parent, $method);
            if (null !== $typeInfo) {
                return $typeInfo;
            }
        }
        return null;
    }

    private function getReflectionTypeInfo(string $class, string $method) /* : ?TypeInfo */ {
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

        return new TypeInfo($paramTypes, null);
    }

    private function mergeTypeInfo(TypeInfo $child, TypeInfo $parent) {
        $paramTypes = $parent->paramTypes + $child->paramTypes;
        $returnType = $parent->returnType ?? $child->returnType;

        return new TypeInfo($paramTypes, $returnType);
    }

    private function isKnownClass(string $name) {
        return isset($this->parents[strtolower($name)]);
    }
}
