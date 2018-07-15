#!add
<?php

class A {
    /** @return self */
    public function foo() {}
}

class B extends A {
    public function foo() {}
}

?>
-----
<?php

class A {
    /** @return self */
    public function foo() : self {}
}

class B extends A {
    public function foo() : A {}
}

?>