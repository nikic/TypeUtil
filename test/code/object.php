#!add --object
<?php
namespace NS;
class A {
    /** @return object */
    public function getObject() {}
}
class B extends A {
    /** @return Foo */
    public function getObject() {}
}
?>
-----
<?php
namespace NS;
class A {
    /** @return object */
    public function getObject() : object {}
}
class B extends A {
    /** @return Foo */
    public function getObject() : Foo {}
}
?>