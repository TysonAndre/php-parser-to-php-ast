<?php declare(strict_types = 1);
namespace ASTConverter\Tests;
use ASTConverter\ASTConverter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ast;

require_once __DIR__ . '/../../src/util.php';

class ConversionTest extends \PHPUnit\Framework\TestCase {
    protected function _scanSourceDirForPHP(string $sourceDir) : array {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir)) as $filePath => $fileInfo) {
            $filename = $fileInfo->getFilename();
            if ($filename &&
                !in_array($filename, ['.', '..'], true) &&
                substr($filename, 0, 1) !== '.' &&
                strpos($filename, '.') !== false &&
                pathinfo($filename)['extension'] === 'php') {
                $files[] = $filePath;
            }
        }
        if (!$files) {
            throw new InvalidArgumentException("No files in %s: scandir returned %s\n", [$files, $sourceDir]);
        }
        return $files;
    }

    /**
     * @return bool
     */
    public static function hasNativeASTSupport(int $astVersion) {
        try {
            $x = ast\parse_code('', $astVersion);
            return true;
        } catch (\LogicException $e) {
            return false;
        }
    }

    /**
     * @return string[]|int[] [string $filePath, int $astVersion]
     */
    public function astValidFileExampleProvider() {
        $tests = [];
        $sourceDir = dirname(dirname(realpath(__DIR__))) . '/test_files/src';
        $paths = $this->_scanSourceDirForPHP($sourceDir);
        $supports40 = self::hasNativeASTSupport(40);
        $supports50 = self::hasNativeASTSupport(50);
        if (!($supports40 || $supports50)) {
            throw new RuntimeException("Neither AST version 40 nor 50 are natively supported");
        }
        foreach ($paths as $path) {
            if ($supports40) {
                $tests[] = [$path, 40];
            }
            if ($supports50) {
                $tests[] = [$path, 50];
            }
        }
        return $tests;
    }

    /** @return void */
    private static function normalizeOriginalAST($node) {
        if ($node instanceof ast\Node) {
            $kind = $node->kind;
            if ($kind === ast\AST_FUNC_DECL || $kind === ast\AST_METHOD) {
                // https://github.com/nikic/php-ast/issues/64
                $node->flags &= ~(0x800000);
            }
            foreach ($node->children as $c) {
                self::normalizeOriginalAST($c);
            }
            return;
        } else if (\is_array($node)) {
            foreach ($node as $c) {
                self::normalizeOriginalAST($c);
            }
        }
    }

    public static function normalizeLineNumbers(ast\Node $node) : ast\Node {
        $node = clone($node);
        if (is_array($node->children)) {
            foreach ($node->children as $k => $v) {
                if ($v instanceof ast\Node) {
                    $node->children[$k] = self::normalizeLineNumbers($v);
                }
            }
        }
        $node->lineno = 1;
        return $node;
    }

    /** @dataProvider astValidFileExampleProvider */
    public function testFallbackFromParser(string $fileName, int $astVersion) {
        $contents = file_get_contents($fileName);
        if ($contents === false) {
            $this->fail("Failed to read $fileName");
        }
        $ast = ast\parse_code($contents, $astVersion, $fileName);
        self::normalizeOriginalAST($ast);
        $this->assertInstanceOf('\ast\Node', $ast, 'Examples must be syntactically valid PHP parseable by php-ast');
        $fallback_ast = \ASTConverter\ASTConverter::ast_parse_code_fallback($contents, $astVersion);
        $this->assertInstanceOf('\ast\Node', $fallback_ast, 'The fallback must also return a tree of php-ast nodes');
        if (stripos($fileName, 'phan_test_files') !== false) {
            $fallback_ast = self::normalizeLineNumbers($fallback_ast);
            $ast          = self::normalizeLineNumbers($ast);
        }
        $fallbackASTRepr = var_export($fallback_ast, true);
        $originalASTRepr = var_export($ast, true);

        if ($fallbackASTRepr !== $originalASTRepr) {
            $nodeDumper = new \PhpParser\NodeDumper([
                'dumpComments' => true,
                'dumpPositions' => true,
            ]);
            $phpParserNode = ASTConverter::phpparser_parse($contents);
            try {
                $dump = $nodeDumper->dump($phpParserNode);
            } catch (\PhpParser\Error $e) {
                $dump = 'could not dump PhpParser Node: ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString();
            }
            $original_ast_dump = \ast_dump($ast, AST_DUMP_LINENOS);
            try {
                $fallback_ast_dump = \ast_dump($fallback_ast, AST_DUMP_LINENOS);
            } catch(\Throwable $e) {
                $fallback_ast_dump = 'could not dump php-ast Node: ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString();
            }
            $parser_export = var_export($phpParserNode, true);
            $this->assertSame($originalASTRepr, $fallbackASTRepr,  <<<EOT
The fallback must return the same tree of php-ast nodes
File: $fileName
Code:
$contents

Original AST:
$original_ast_dump

Fallback AST:
$fallback_ast_dump
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
