<?php declare(strict_types = 1);
// namespace astconverter\Tests;

class TestConversion extends PHPUnit\Framework\TestCase {
    const CURRENT_VERSION = 40;

    protected function _scanSourceDirForPHP(string $sourceDir) : array {
        $files = array_filter(
            array_filter(
                scandir($sourceDir),
                function($filename) {
                    return !in_array($filename, ['.', '..'], true) && substr($filename, 0, 1) !== '.' && pathinfo($filename)['extension'] === 'php';
                }
            )
        );
        return array_values($files);
    }

    public function astValidFileExampleProvider() {
        $tests = [];
        $sourceDir = dirname(__DIR__) . '/test_files/src';
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
        $this->assertNotNull($ast, "Examples must be syntactically valid PHP parseable by php-ast");
    }
}
