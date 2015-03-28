<?php declare(strict_types=1);

namespace TypeUtil;

class FunctionInfo {
    use NoDynamicProperties;

    public $paramTypes;
    public $returnType;

    public function __construct(array $paramTypes, $returnType) {
        $this->paramTypes = $paramTypes;
        $this->returnType = $returnType;
    }
}

