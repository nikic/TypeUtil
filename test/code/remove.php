#!remove
<?php

function test(
    int $a,
    array $b
) : bool {
}

?>
-----
<?php

function test(
    $a,
    array $b
) {
}

?>