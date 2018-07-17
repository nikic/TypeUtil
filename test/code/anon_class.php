#!add
<?php

new class {
    /** @param int $a */
    public function test($a) {}
};

?>
-----
<?php

new class {
    /** @param int $a */
    public function test(int $a) {}
};

?>