<?php declare(strict_types = 1);
namespace ASTConverter\Tests;
use ASTConverter\ASTConverter;

require_once __DIR__ . '/../../src/util.php';

class ConversionTest extends \PHPUnit\Framework\TestCase {
    protected function _scanSourceDirForPHP(string $sourceDir) : array {
        $files = scandir($sourceDir);
        if (!$files) {
            throw new InvalidArgumentException("No files in %s: scandir returned %s\n", [$files, $sourceDir]);
        }
        $files = array_filter(
            $files,
            function($filename) {
                return $filename &&
                    !in_array($filename, ['.', '..'], true) &&
                    substr($filename, 0, 1) !== '.' &&
                    pathinfo($filename)['extension'] === 'php';
            }
        );
        return array_values($files);
    }

    public function astValidFileExampleProvider() {
        $tests = [];
        $sourceDir = dirname(dirname(realpath(__DIR__))) . '/test_files/src';
        $files = $this->_scanSourceDirForPHP($sourceDir);
        foreach ($files as $file) {
            $tests[] = [$sourceDir . '/' . $file];
        }
        return $tests;
    }

    /** @dataProvider astValidFileExampleProvider */
    public function testFallbackFromParser(string $fileName) {
        $contents = file_get_contents($fileName);
        if ($contents === false) {
            $this->fail("Failed to read $fileName");
        }
        $ast = \ast\parse_code($contents, ASTConverter::AST_VERSION);
        $this->assertInstanceOf('\ast\Node', $ast, 'Examples must be syntactically valid PHP parseable by php-ast');
        $fallback_ast = \ASTConverter\ASTConverter::ast_parse_code_fallback($contents, ASTConverter::AST_VERSION);
        $this->assertInstanceOf('\ast\Node', $fallback_ast, 'The fallback must also return a tree of php-ast nodes');
        $fallbackASTRepr = var_export($fallback_ast, true);
        $originalASTRepr = var_export($ast, true);

        if ($fallbackASTRepr !== $originalASTRepr) {
            $dump = 'could not dump';
            $nodeDumper = new \PhpParser\NodeDumper([
                'dumpComments' => true,
                'dumpPositions' => true,
            ]);
            $phpParserNode = ASTConverter::phpparser_parse($contents);
            try {
                $dump = $nodeDumper->dump($phpParserNode);
            } catch (\PhpParser\Error $e) {
            }
            $original_ast_dump = \ast_dump($ast);
            $parser_export = var_export($phpParserNode, true);
            $this->assertSame($originalASTRepr, $fallbackASTRepr,  <<<EOT
The fallback must return the same tree of php-ast nodes
File: $fileName
Code:
$contents

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
