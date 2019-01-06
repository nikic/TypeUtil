#!add --property-types
<?php

class Test {
    /** @var int */
    public $foo;
    /** @var Foo|null */
    public $bar;
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

?>