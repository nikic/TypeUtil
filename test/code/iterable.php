#!add-php71
<?php
namespace NS;
class A {
    /** @return iterable */
    public function getIterable() {}
}
class B extends A {
    /** @return array */
    public function getIterable() {}
}
?>
-----
<?php
namespace NS;
class A {
    /** @return iterable */
    public function getIterable() : iterable {}
}
class B extends A {
    /** @return array */
    public function getIterable() : array {}
}
?>