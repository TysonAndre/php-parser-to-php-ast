<?php declare(strict_types = 1);
namespace ASTConverter\Tests;
use ASTConverter\ASTConverter;

require_once __DIR__ . '/../../src/util.php';

class ErrorTolerantConversionTest extends \PHPUnit\Framework\TestCase {

    public function setUp() {
        parent::setUp();
        ASTConverter::setShouldAddPlaceholders(false);
    }

    public function testIncompleteVar() {
        ASTConverter::setShouldAddPlaceholders(false);
        $incompleteContents = <<<'EOT'
<?php
function foo() {
  $a = $
}
EOT;
        $validContents = <<<'EOT'
<?php
function foo() {

}
EOT;
        $this->_testFallbackFromParser($incompleteContents, $validContents);
    }

    public function testIncompleteVarWithPlaceholder() {
        ASTConverter::setShouldAddPlaceholders(true);
        $incompleteContents = <<<'EOT'
<?php
function foo() {
  $a = $
}
EOT;
        $validContents = <<<'EOT'
<?php
function foo() {
  $a = $__INCOMPLETE_VARIABLE__;
}
EOT;
        $this->_testFallbackFromParser($incompleteContents, $validContents);
    }

    public function testIncompleteProperty() {
        ASTConverter::setShouldAddPlaceholders(false);
        $incompleteContents = <<<'EOT'
<?php
function foo() {
  $c;
  $a = $b->
}
EOT;
        $validContents = <<<'EOT'
<?php
function foo() {
  $c;

}
EOT;
        $this->_testFallbackFromParser($incompleteContents, $validContents);
    }

    public function testIncompletePropertyWithPlaceholder() {
        ASTConverter::setShouldAddPlaceholders(true);
        $incompleteContents = <<<'EOT'
<?php
function foo() {
  $c;
  $a = $b->
}
EOT;
        $validContents = <<<'EOT'
<?php
function foo() {
  $c;
  $a = $b->__INCOMPLETE_PROPERTY__;
}
EOT;
        $this->_testFallbackFromParser($incompleteContents, $validContents);
    }

    public function testIncompleteMethod() {
        ASTConverter::setShouldAddPlaceholders(false);
        $incompleteContents = <<<'EOT'
<?php
function foo() {
  $b;
  $a = Bar::
}
EOT;
        $validContents = <<<'EOT'
<?php
function foo() {
  $b;

}
EOT;
        $this->_testFallbackFromParser($incompleteContents, $validContents);
    }

    public function testIncompleteMethodWithPlaceholder() {
        ASTConverter::setShouldAddPlaceholders(true);
        $incompleteContents = <<<'EOT'
<?php
function foo() {
  $b;
  $a = Bar::
}
EOT;
        $validContents = <<<'EOT'
<?php
function foo() {
  $b;
  $a = Bar::__INCOMPLETE_CLASS_CONST__;
}
EOT;
        $this->_testFallbackFromParser($incompleteContents, $validContents);
    }

    public function testMiscNoise() {
        ASTConverter::setShouldAddPlaceholders(false);
        $incompleteContents = <<<'EOT'
<?php
function foo() {
  $b;
  |
}
EOT;
        $validContents = <<<'EOT'
<?php
function foo() {
  $b;

}
EOT;
        $this->_testFallbackFromParser($incompleteContents, $validContents);
    }

    public function testMiscNoiseWithPlaceholders() {
        ASTConverter::setShouldAddPlaceholders(true);
        $incompleteContents = <<<'EOT'
<?php
function foo() {
  $b;
  |
}
EOT;
        $validContents = <<<'EOT'
<?php
function foo() {
  $b;

}
EOT;
        $this->_testFallbackFromParser($incompleteContents, $validContents);
    }

    public function testIncompleteArithmeticWithPlaceholders() {
        ASTConverter::setShouldAddPlaceholders(true);
        $incompleteContents = <<<'EOT'
<?php
function foo() {
  ($b * $c) +
}
EOT;
        $validContents = <<<'EOT'
<?php
function foo() {
  $b * $c;
}
EOT;
        $this->_testFallbackFromParser($incompleteContents, $validContents);
    }

    public function testMissingSemicolon() {
        ASTConverter::setShouldAddPlaceholders(false);
        $incompleteContents = <<<'EOT'
<?php
function foo() {
    $y = 3
    $x = intdiv(3, 2);
}
EOT;
        $validContents = <<<'EOT'
<?php
function foo() {
    $y = 3;
    $x = intdiv(3, 2);
}
EOT;
        $this->_testFallbackFromParser($incompleteContents, $validContents);
    }

// Another test (Won't work with php-parser, might work with tolerant-php-parser
/**
        $incompleteContents = <<<'EOT'
<?php
class C{
    public function foo() {
        $x = 3;


    public function bar() {
    }
}
EOT;
        $validContents = <<<'EOT'
<?php
class C{
    public function foo() {
        $x = 3;
    }

    public function bar() {
    }
}
EOT;
 */

    private function _testFallbackFromParser(string $incompleteContents, string $validContents) {
        $supports40 = ConversionTest::hasNativeASTSupport(40);
        $supports50 = ConversionTest::hasNativeASTSupport(50);
        if (!($supports40 || $supports50)) {
            $this->fail('No supported AST versions to test');
        }
        if ($supports40) {
            $this->_testFallbackFromParserForASTVersion($incompleteContents, $validContents, 40);
        }
        if ($supports50) {
            $this->_testFallbackFromParserForASTVersion($incompleteContents, $validContents, 50);
        }
    }

    private function _testFallbackFromParserForASTVersion(string $incompleteContents, string $validContents, int $astVersion) {
        $ast = \ast\parse_code($validContents, $astVersion);
        $this->assertInstanceOf('\ast\Node', $ast, 'Examples(for validContents) must be syntactically valid PHP parseable by php-ast');
        $errors = [];
        $phpParserNode = ASTConverter::phpparserParse($incompleteContents, true, $errors);
        $fallback_ast = ASTConverter::phpparserToPhpAst($phpParserNode, $astVersion);
        $this->assertInstanceOf('\ast\Node', $fallback_ast, 'The fallback must also return a tree of php-ast nodes');
        $fallbackASTRepr = var_export($fallback_ast, true);
        $originalASTRepr = var_export($ast, true);

        if ($fallbackASTRepr !== $originalASTRepr) {
            $dump = 'could not dump';
            $nodeDumper = new \PhpParser\NodeDumper([
                'dumpComments' => true,
                'dumpPositions' => true,
            ]);
            try {
                $dump = $nodeDumper->dump($phpParserNode);
            } catch (\PhpParser\Error $e) {
            }
            $original_ast_dump = \ast_dump($ast);
            // $parser_export = var_export($phpParserNode, true);
            $this->assertSame($originalASTRepr, $fallbackASTRepr,  <<<EOT
The fallback must return the same tree of php-ast nodes
Code:
$incompleteContents

Closest Valid Code:
$validContents

Original AST:
$original_ast_dump

PHP-Parser(simplified):
$dump
EOT

            /*
PHP-Parser(unsimplified):
$parser_export
             */
);
        }
    }
}
