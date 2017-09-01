#!/usr/bin/env bash
# This has been tested with 0.1.5 but not any other versions
pecl install -f ast-0.1.5

# Disable xdebug, since we aren't currently gathering code coverage data and
# having xdebug slows down Composer a bit.
phpenv config-rm xdebug.ini
