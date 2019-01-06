#!add --property-types
<?php

class Test {
    /** @var int */
    public $foo;
    /** @var Foo|null */
    public $bar;
}

class A {
    /** @var int */
    public $foo;
}
class B extends A {
    public $foo;
}

?>
-----
<?php

class Test {
    /** @var int */
    public int $foo;
    /** @var Foo|null */
    public ?Foo $bar;
}

class A {
    /** @var int */
    public int $foo;
}
class B extends A {
    public int $foo;
}

?>