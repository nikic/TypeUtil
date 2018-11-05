## Utility for adding return types and scalar types

> **WARNING**: Utility directly modifies files under the assumption that you're using version control.

To add return types and scalar types based on doc comments, run:

    php-7.1 type-util.php add dir1/ dir2/ ...

To remove all PHP 5 incompatible type information, run:

    php-7.1 type-util.php remove dir1/ dir2/ ...

Notes:

 * Not well tested - probably doesn't work in many cases
 * Uses only doc comments, so requires good doc comment coverage
 * Requires PHP 7.1 to run
 * The main job of this utility is dealing with the fact that PHP return types are semi-invariant
   and argument types are fully invariant

### Help Text

```
Usage: php ./type-util.php add|remove [--options] dir1 dir2 ...

Options:
    --php VERSION         Enable all features supported up to VERSION
                          E.g. --php 7.1
    --[no-]nullable-types Toggle generation of nullable types (PHP 7.1)
    --[no-]iterable       Toggle generation of iterable type  (PHP 7.1)
    --[no-]strict-types   Toggle use of strict_types          (PHP 7.0)

Examples:
    # Add everything that's possible!
    php ./type-utils.php add path/to/dir
    
    # Only add features available in PHP 7.0
    php ./type-utils.php --php 7.0
    
    # Add everything available in PHP 7.1, apart from strict types
    php ./type-utils.php --php 7.1 --no-strict-types

NOTE: Will directly modify files, assumes that you're using VCS.
```