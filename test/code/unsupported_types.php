#!add
<?php

/** @return void */
function test() {}

/** @return mixed */
function test2() {}

/** @return static */
function test3() {}

?>
-----
<?php

/** @return void */
function test() {}

/** @return mixed */
function test2() {}

/** @return static */
function test3() {}

?>
