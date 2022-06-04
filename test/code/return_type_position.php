#!add
<?php

/** @return A */
function test($a = array()) {}

?>
-----
<?php

/** @return A */
function test($a = array()): A {}

?>
