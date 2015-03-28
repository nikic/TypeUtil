<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\Node\Name;

class TypeExtractor {
    use NoDynamicProperties;

    private $nameResolver;

    public function __construct(NameResolver $nameResolver) {
        $this->nameResolver = $nameResolver;
    }

    public function extractTypeInfo(array $params, string $docComment) : TypeInfo {
        $namedParamTypes = $this->getNamedParamTypes($docComment);
        return new TypeInfo(
            $this->getParamTypes($params, $namedParamTypes),
            $this->getReturnType($docComment)
        );
    }

    private function getParamTypes(array $params, array $namedParamTypes) : array {
        $paramTypes = [];
        foreach ($params as $param) {
            $paramTypes[] = $namedParamTypes[$param->name] ?? null;
        }
        return $paramTypes;
    }

    private function getNamedParamTypes(string $docComment) : array {
        if (!preg_match_all('/@param\s+(\S+)\s+\$(\S+)/', $docComment, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $paramTypes = [];
        foreach ($matches as list(, $typeString, $name)) {
            $typeInfo = $this->parseType($typeString);
            if (null !== $typeInfo) {
                $paramTypes[$name] = $typeInfo;
            }
        }

        return $paramTypes;
    }

    private function getReturnType(string $docComment) /* : ?Type */ {
        if (!preg_match('/@return\s+(\S+)/', $docComment, $matches)) {
            return null;
        }

        return $this->parseType($matches[1]);
    }

    private function parseType(string $typeString) /* : ?Type */ {
        $types = explode('|', $typeString);
        $resultType = null;
        $isNullable = false;

        foreach ($types as $type) {
            if ($type === 'null') {
                $isNullable = true;
                continue;
            }
            if ($resultType !== null) {
                // Don't support union types
                return null;
            }

            if (substr($type, -2) === '[]') {
                $resultType = 'array';
                continue;
            }
            if (!$this->isSupportedTypeName($type)) {
                // Generic types are not supported
                return null;
            }
            $resultType = $this->getCanonicalTypeName($type);
        }
        return new Type($resultType, $isNullable);
    }

    private function isSupportedTypeName(string $name) : bool {
        return preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $name) === 1
            && $name !== 'mixed' && $name !== 'void';
    }

    private function getCanonicalTypeName(string $name) : string {
        switch (strtolower($name)) {
            case 'bool':
            case 'boolean':
                return 'bool';
            case 'int':
            case 'integer':
                return 'int';
            case 'float':
            case 'double':
                return 'float';
            case 'string':
                return 'string';
            case 'array':
                return 'array';
            default:
                if ($name[0] === '\\') {
                    return substr($name, 1);
                }

                $name = new Name($name);
                return $this->nameResolver->doResolveClassName($name)->toString();
        }
    }
}
