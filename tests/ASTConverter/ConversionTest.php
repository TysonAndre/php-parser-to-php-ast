<?php declare(strict_types = 1);
namespace ASTConverter\Tests;

class TestConversion extends \PHPUnit\Framework\TestCase {
    const CURRENT_VERSION = 40;

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
    public function testSomething(string $fileName) {
        $contents = file_get_contents($fileName);
        if ($contents === false) {
            $this->fail("Failed to read $fileName");
        }
        $ast = \ast\parse_code($contents, self::CURRENT_VERSION);
        $this->assertInstanceOf('\ast\Node', $ast, 'Examples must be syntactically valid PHP parseable by php-ast');
        $fallback_ast = \ASTConverter\ASTConverter::ast_parse_code_fallback($contents, self::CURRENT_VERSION);
        $this->assertInstanceOf('\ast\Node', $fallback_ast, 'The fallback must also return a tree of php-ast nodes');
        $this->assertSame(var_export($fallback_ast, true), var_export($ast, true), 'The fallback must also return a tree of php-ast nodes');
    }
}
