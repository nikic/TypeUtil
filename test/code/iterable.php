#!add --iterable --nullable-types
<?php
namespace NS;
class A {
    /** @return iterable */
    public function getIterable() {}
}
class B extends A {
    /** @return array */
    public function getIterable() {}
}

/** @param \Traversable|array $a */
function test($a) {}

/** @param array|\Traversable|null $a */
function test2($a) {}
?>
-----
<?php
namespace NS;
class A {
    /** @return iterable */
    public function getIterable(): iterable {}
}
class B extends A {
    /** @return array */
    public function getIterable(): array {}
}

/** @param \Traversable|array $a */
function test(iterable $a) {}

/** @param array|\Traversable|null $a */
function test2(?iterable $a) {}
?>
