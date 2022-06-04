#!add
<?php

class P {}

class A extends P {
    /** @return self */
    public function foo() {}

    /** @return parent */
    public function bar() {}
}

class B extends A {
    public function foo() {}

    public function bar() {}
}

?>
-----
<?php

class P {}

class A extends P {
    /** @return self */
    public function foo(): self {}

    /** @return parent */
    public function bar(): parent {}
}

class B extends A {
    public function foo(): A {}

    public function bar(): P {}
}

?>
