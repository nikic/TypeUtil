<?php declare(strict_types=1);

namespace TypeUtil;

class ClassInfo {
    /** @var string */
    public $name;
    /** @var string|null */
    public $parent;
    /** @var FunctionInfo[] */
    public $funcInfos;

    public function __construct(string $name, ?string $parent) {
        $this->name = $name;
        $this->parent = $parent;
    }
}