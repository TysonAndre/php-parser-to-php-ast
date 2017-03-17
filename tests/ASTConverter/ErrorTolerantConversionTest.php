<?php declare(strict_types = 1);
namespace ASTConverter\Tests;
use ASTConverter\ASTConverter;

require_once __DIR__ . '/../../src/util.php';

class ErrorTolerantConversionTest extends \PHPUnit\Framework\TestCase {

    public function setUp() {
        parent::setUp();
        ASTConverter::set_should_add_placeholders(false);
    }

    public function testIncompleteVar() {
        ASTConverter::set_should_add_placeholders(false);
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
        ASTConverter::set_should_add_placeholders(true);
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
        ASTConverter::set_should_add_placeholders(false);
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
        ASTConverter::set_should_add_placeholders(true);
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
        ASTConverter::set_should_add_placeholders(false);
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
        ASTConverter::set_should_add_placeholders(true);
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
        ASTConverter::set_should_add_placeholders(false);
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
        ASTConverter::set_should_add_placeholders(true);
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
        ASTConverter::set_should_add_placeholders(true);
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

    private function _testFallbackFromParser(string $incompleteContents, string $validContents) {
        $ast = \ast\parse_code($validContents, ASTConverter::AST_VERSION);
        $this->assertInstanceOf('\ast\Node', $ast, 'Examples(for validContents) must be syntactically valid PHP parseable by php-ast');
        $errors = [];
        $phpParserNode = ASTConverter::phpparser_parse($incompleteContents, true, $errors);
        $fallback_ast = ASTConverter::phpparser_to_phpast($phpParserNode, ASTConverter::AST_VERSION);
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
            $parser_export = var_export($phpParserNode, true);
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
