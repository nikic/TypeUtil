#!add
<?php

/**
 * @param int $a
 * @param int $b
 * @return int
 */
function test($a, $b) {
    return $a + $b;
}

?>
-----
<?php

/**
 * @param int $a
 * @param int $b
 * @return int
 */
function test(int $a, int $b) : int {
    return $a + $b;
}

?>

