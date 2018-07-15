<?php declare(strict_types=1);

namespace TypeUtil;

class FunctionInfo {
    use NoDynamicProperties;

    /** @var Type[] */
    public $paramTypes;
    /** @var Type|null */
    public $returnType;

    public function __construct(array $paramTypes, ?Type $returnType) {
        $this->paramTypes = $paramTypes;
        $this->returnType = $returnType;
    }
}

