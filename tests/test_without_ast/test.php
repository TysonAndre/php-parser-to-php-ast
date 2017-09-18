<?php
use ASTConverter\ASTConverter;
require_once __DIR__ . '/../../vendor/autoload.php';
function test_without_native_ast() {
    error_reporting(E_ALL);
    $x = new ASTConverter();
    $result = $x->parseCodeAsPHPAST('<'.'?php function foo(int $x) { return $x * 2;}', 40);
    var_export($result);
    echo "\n";
    $result = $x->parseCodeAsPHPAST('<'.'?php function foo(int $x) { return $x * 2;}', 50);
    var_export($result);
    echo "\n";
    if (!((new ReflectionClass('ast\Node'))->isUserDefined())) {
        echo "Error: ast\Node is native, the fallback wasn't being properly tested. Disable the php-ast extension and run this test again\n";
        exit(1);
    }
}
test_without_native_ast();
