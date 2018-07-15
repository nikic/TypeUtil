<?php declare(strict_types=1);

namespace TypeUtil;

class ClassInfo {
    /** @var string */
    public $name;
    /** @var FunctionInfo[] */
    public $funcInfos;

    public function __construct(string $name) {
        $this->name = $name;
    }
}