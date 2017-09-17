<?php declare(strict_types = 1);
namespace ASTConverter\Tests;
use ASTConverter\ASTConverter;

require_once __DIR__ . '/../../src/util.php';

class ErrorTolerantConversionTest extends \PHPUnit\Framework\TestCase {
    public function testIncompleteVar() {
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
        $this->_testFallbackFromParser($incompleteContents, $validContents, true);
    }

    public function testIncompleteProperty() {
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
        $this->_testFallbackFromParser($incompleteContents, $validContents, true);
    }

    public function testIncompleteMethod() {
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
        $this->_testFallbackFromParser($incompleteContents, $validContents, true);
    }

    public function testMiscNoise() {
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
        $this->_testFallbackFromParser($incompleteContents, $validContents, true);
    }

    public function testIncompleteArithmeticWithPlaceholders() {
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
        $this->_testFallbackFromParser($incompleteContents, $validContents, true);
    }

    public function testMissingSemicolon() {
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

    private function _testFallbackFromParser(string $incompleteContents, string $validContents, bool $should_add_placeholders = false) {
        $supports40 = ConversionTest::hasNativeASTSupport(40);
        $supports50 = ConversionTest::hasNativeASTSupport(50);
        if (!($supports40 || $supports50)) {
            $this->fail('No supported AST versions to test');
        }
        if ($supports40) {
            $this->_testFallbackFromParserForASTVersion($incompleteContents, $validContents, 40, $should_add_placeholders);
        }
        if ($supports50) {
            $this->_testFallbackFromParserForASTVersion($incompleteContents, $validContents, 50, $should_add_placeholders);
        }
    }

    private function _testFallbackFromParserForASTVersion(string $incompleteContents, string $validContents, int $astVersion, bool $should_add_placeholders) {
        $ast = \ast\parse_code($validContents, $astVersion);
        $this->assertInstanceOf('\ast\Node', $ast, 'Examples(for validContents) must be syntactically valid PHP parseable by php-ast');
        $errors = [];
        $converter = new ASTConverter();
        $converter->setShouldAddPlaceholders($should_add_placeholders);
        $phpParserNode = $converter->phpParserParse($incompleteContents, true, $errors);
        $fallback_ast = $converter->phpParserToPhpAst($phpParserNode, $astVersion);
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
