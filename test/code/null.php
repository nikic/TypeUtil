#!add
<?php

/** @return null */
function foo() {
    return null;
}

/** @param null $a */
function foo2($a) {}

/** @param null|A $a */
function foo3($a = null) {}

/** @param null|A $a */
function foo4($a) {}

?>
-----
<?php

/** @return null */
function foo() {
    return null;
}

/** @param null $a */
function foo2($a) {}

/** @param null|A $a */
function foo3(A $a = null) {}

/** @param null|A $a */
function foo4($a) {}

?>
