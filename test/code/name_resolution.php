#!add
<?php

namespace Foo;

use ABC\Baz;

/** @return \Bar */
function test1() {}

/** @return Bar */
function test2() {}

/** @return Bar\Baz */
function test3() {}

/** @return Baz */
function test4() {}

/** @return Baz\Foo */
function test5() {}

?>
-----
<?php

namespace Foo;

use ABC\Baz;

/** @return \Bar */
function test1() : \Bar {}

/** @return Bar */
function test2() : Bar {}

/** @return Bar\Baz */
function test3() : Bar\Baz {}

/** @return Baz */
function test4() : \ABC\Baz {}

/** @return Baz\Foo */
function test5() : \ABC\Baz\Foo {}

?>