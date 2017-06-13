<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitor\NameResolver;

class TypeExtractor {
    use NoDynamicProperties;

    private $nameResolver;

    public function __construct(NameResolver $nameResolver) {
        $this->nameResolver = $nameResolver;
    }

    public function extractFunctionInfo(array $params, string $docComment) : FunctionInfo {
        $namedParamTypes = $this->getNamedParamTypes($docComment);
        return new FunctionInfo(
            $this->getParamTypes($params, $namedParamTypes),
            $this->getReturnType($docComment)
        );
    }

    public function getTypeDisplayName(Type $type) : string {
        $prefix = $type->isNullable ? '?' : '';
        return $prefix . $this->getBaseTypeDisplayName($type);
    }

    private function getBaseTypeDisplayName(Type $type) : string {
        if (!$type->isClassHint()) {
            return $type->name;
        }

        $nameContext = $this->nameResolver->getNameContext();
        return $nameContext->getShortName($type->name, Use_::TYPE_NORMAL)->toCodeString();
    }

    /**
     * @param Param[] $params
     * @param Type[] $namedParamTypes
     * @return Type[]
     */
    private function getParamTypes(array $params, array $namedParamTypes) : array {
        $paramTypes = [];
        foreach ($params as $param) {
            $paramTypes[] = $namedParamTypes[$param->var->name] ?? null;
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

        if (null === $resultType) {
            // Happens if type string is "null"
            return null;
        }
        return new Type($resultType, $isNullable);
    }

    private function isSupportedTypeName(string $name) : bool {
        return preg_match('/^[a-zA-Z_\x7f-\xff\\\\][a-zA-Z0-9_\x7f-\xff\\\\]*$/', $name) === 1
            && $name !== 'mixed' && $name !== 'void' && $name !== 'static';
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

                $nameContext = $this->nameResolver->getNameContext();
                $name = new Name($name);
                return $nameContext->getResolvedClassName($name)->toString();
        }
    }
}
