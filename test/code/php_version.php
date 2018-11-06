#!add --php 7.0
<?php
/**
 * @param Foo|null $a
 * @return Foo|null
 */
function a($a) {}
/**
 * @param array|Traversable $a
 * @return array|Traversable
 */
function b($a) {}
/**
 * @param object $a
 * @return object
 */
function c($a) {}
?>
-----
<?php
/**
 * @param Foo|null $a
 * @return Foo|null
 */
function a($a) {}
/**
 * @param array|Traversable $a
 * @return array|Traversable
 */
function b($a) {}
/**
 * @param object $a
 * @return object
 */
function c($a) {}
?>