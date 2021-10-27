#!/bin/bash
php ./dist/phase/index.php ./test ./dist/test
php ./dist/phase/index.php ./std/Std ./dist/test/Std
php ./dist/phase/index.php ./std/Phase ./dist/test/Phase
php ./test.php
