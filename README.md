## Utility for adding return types and scalar types

> **WARNING**: Utility directly modifies files under the assumption that you're using git.

To add return types and scalar types based on doc comments, run:

    php-7.0 type-util.php add dir1/ dir2/ ...

To remove all PHP 5 incompatible type information, run:

    php-7.0 type-util.php remove dir1/ dir2/ ...

Notes:

 * Not well tested - probably doesn't work in many cases
 * Uses only doc comments, so requires good doc comment coverage
 * Requires PHP 7 to run
 * The main job of this utility is dealing with the fact that PHP return types are semi-invariant
   and argument types are fully invariant

