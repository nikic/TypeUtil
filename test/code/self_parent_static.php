#!add
<?php

namespace NS;

class P {}
class A extends P {
    /** @return self */
    function foo() {}

    /** @return parent */
    function bar() {}

    /** @return static */
    function baz() {}
}
?>
-----
<?php

namespace NS;

class P {}
class A extends P {
    /** @return self */
    function foo(): self {}

    /** @return parent */
    function bar(): parent {}

    /** @return static */
    function baz() {}
}
?>
