#!add
<?php

/**
 * @param int $a
 * @param int $b
 * @return int
 */
function add($a, $b) {
    return $a + $b;
}

class Number {
    /**
     * @param int $a
     * @param int $b
     * @return int
     */
    public function add($a, $b) {
        return $a + $b;
    }
}

?>
-----
<?php

/**
 * @param int $a
 * @param int $b
 * @return int
 */
function add(int $a, int $b): int {
    return $a + $b;
}

class Number {
    /**
     * @param int $a
     * @param int $b
     * @return int
     */
    public function add(int $a, int $b): int {
        return $a + $b;
    }
}

?>

