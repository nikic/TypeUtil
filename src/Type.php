<?php declare(strict_types=1);

namespace TypeUtil;

class Type {
    use NoDynamicProperties;

    /** @var string */
    public $name;
    /** @var string Resolved name, differs for "self" and "parent" types */
    public $resolvedName;
    /** @var bool */
    public $isNullable;

    public function __construct(string $name, string $resolvedName, bool $isNullable) {
        $this->name = $name;
        $this->resolvedName = $resolvedName;
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
            case 'object':
                return false;
            default:
                return true;
        }
    }

    public function asNotNullable() : self {
        if (!$this->isNullable) {
            return $this;
        }
        return new self($this->name, $this->resolvedName, false);
    }

    public function asResolvedType() : self {
        if ($this->name === $this->resolvedName) {
            return $this;
        }
        return new self($this->resolvedName, $this->resolvedName, $this->isNullable);
    }
}
