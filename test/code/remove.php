#!remove
<?php

function test(
    int  $a,
    array $b,
    ?Foo$c,
    ? Bar $d = null,
    ?int $e = null
) : bool {
}

function test2() : void {}

?>
-----
<?php

function test(
    $a,
    array $b,
    $c,
    Bar $d = null,
    $e = null
) {
}

function test2() {}

?>