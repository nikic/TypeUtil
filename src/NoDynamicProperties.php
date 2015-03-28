<?php declare(strict_types=1);

namespace TypeUtil;

trait NoDynamicProperties {
    public function __get($name) {
        $this->throwDynamicPropertyError($name);
    }
    public function __set($name, $value) {
        $this->throwDynamicPropertyError($name);
    }

    private function throwDynamicPropertyError(string $name) {
        throw new \RuntimeException("Property \"$name\" does not exist");
    }
}

