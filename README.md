PHP-Parser to php-ast
=====================

[![Build Status](https://travis-ci.org/TysonAndre/php-parser-to-php-ast.svg?branch=master)](https://travis-ci.org/TysonAndre/php-parser-to-php-ast)

8 out of 41 tests are failing, and only due to namespace support and line numbers being slightly different in php-ast.

- The test suite is not yet comprehensive.

[Current Issues](https://github.com/TysonAndre/php-parser-to-php-ast/issues/)

- The test suite this is based off of is not comprehensive, and edge cases still remain.
  See https://github.com/TysonAndre/php-parser-to-php-ast/issues/4

Usage
-----

Using it as a slow substitute for php-ast

- [tests/ASTConverter/ConversionTest.php](https://github.com/TysonAndre/php-parser-to-php-ast/blob/master/tests/ASTConverter/ConversionTest.php)

Using it as an error-tolerant substitute for php-ast (e.g. for use in IDEs)

- There are currently two modes: omitting errors and adding placeholders (e.g. `__INCOMPLETE_VARIABLE__`).
- Omitting errors only handles some common cases that come up while editing a file.
- Placeholders may change in the future.
- [tests/ASTConverter/ErrorTolerantConversionTest.php](https://github.com/TysonAndre/php-parser-to-php-ast/blob/master/tests/ASTConverter/ErrorTolerantConversionTest.php)

Running unit tests
------------------

To run unit tests, you must install [nikic/php-ast](https://github.com/nikic/php-ast)

- Then run `vendor/bin/phpunit`

Possible Future Enhancements
----------------------------

- https://github.com/Microsoft/tolerant-php-parser/issues/113
