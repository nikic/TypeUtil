<?php declare(strict_types=1);

namespace TypeUtil;

class Type {
    use NoDynamicProperties;

    public $name;
    public $isNullable;

    public function __construct(string $name, bool $isNullable) {
        $this->name = $name;
        $this->isNullable = $isNullable;
    }

    public function isClassHint() : bool {
        switch ($this->name) {
            case 'bool':
            case 'int':
            case 'float':
            case 'string':
            case 'array':
            case 'iterable':
                return false;
            default:
                return true;
        }
    }

    public function asNotNullable() : self {
        return new self($this->name, false);
    }
}
