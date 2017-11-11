<?php declare(strict_types = 1);

namespace ASTConverter\Tests;

use ASTConverter\ASTConverter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use ast;

require_once __DIR__ . '/../../src/util.php';

class ConversionTest extends \PHPUnit\Framework\TestCase {
    protected function _scanSourceDirForPHP(string $source_dir) : array {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source_dir)) as $file_path => $file_info) {
            $filename = $file_info->getFilename();
            if ($filename &&
                !in_array($filename, ['.', '..'], true) &&
                substr($filename, 0, 1) !== '.' &&
                strpos($filename, '.') !== false &&
                pathinfo($filename)['extension'] === 'php') {
                $files[] = $file_path;
            }
        }
        if (count($files) === 0) {
            throw new \InvalidArgumentException(sprintf("No files in %s: RecursiveDirectoryIterator iteration returned %s\n", $files, $sourceDir));
        }
        return $files;
    }

    /**
     * @return bool
     */
    public static function hasNativeASTSupport(int $ast_version) {
        try {
            $x = ast\parse_code('', $ast_version);
            return true;
        } catch (\LogicException $e) {
            return false;
        }
    }

    /**
     * @return string[]|int[] [string $file_path, int $ast_version]
     */
    public function astValidFileExampleProvider() {
        $tests = [];
        $source_dir = dirname(dirname(realpath(__DIR__))) . '/test_files/src';
        $paths = $this->_scanSourceDirForPHP($source_dir);
        $counts = [];
        foreach ($paths as $path) {
            $counts[$path] = \count(\token_get_all(\file_get_contents($path)));
        }
        usort($paths, function($a, $b) use ($counts) { return $counts[$a] <=> $counts[$b]; });
        $supports40 = self::hasNativeASTSupport(40);
        $supports45 = self::hasNativeASTSupport(45);
        $supports50 = self::hasNativeASTSupport(50);
        if (!($supports40 || $supports50)) {
            throw new RuntimeException("None of version 40, 45 or 50 are natively supported");
        }
        foreach ($paths as $path) {
            if ($supports40) {
                $tests[] = [$path, 40];
            }
            if ($supports45) {
                $tests[] = [$path, 45];
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
    public function testFallbackFromParser(string $file_name, int $ast_version) {
        if ($ast_version === 40) {
            file_put_contents('/tmp/files', "$file_name\n", FILE_APPEND);
        }
        $test_folder_name = basename(dirname($file_name));
        if (PHP_VERSION_ID < 70100 && $test_folder_name === 'php71_or_newer') {
            $this->markTestIncomplete('php-ast cannot parse php7.1 syntax when running in php7.0');
        }
        $contents = file_get_contents($file_name);
        if ($contents === false) {
            $this->fail("Failed to read $file_name");
        }
        $ast = ast\parse_code($contents, $ast_version, $file_name);
        self::normalizeOriginalAST($ast);
        $this->assertInstanceOf('\ast\Node', $ast, 'Examples must be syntactically valid PHP parseable by php-ast');
        $converter = new ASTConverter();
        try {
            $fallback_ast = $converter->parseCodeAsPHPAST($contents, $ast_version);
        } catch (\Throwable $e) {
            throw new RuntimeException("Error parsing $file_name with ast version $ast_version", $e->getCode(), $e);
        }
        $this->assertInstanceOf('\ast\Node', $fallback_ast, 'The fallback must also return a tree of php-ast nodes');

        if ($test_folder_name === 'phan_test_files' || $test_folder_name === 'php-src_tests' || $test_folder_name === 'php-src_tests2') {
            $fallback_ast = self::normalizeLineNumbers($fallback_ast);
            $ast          = self::normalizeLineNumbers($ast);
        }
        $fallback_ast_repr = var_export($fallback_ast, true);
        $original_ast_repr = var_export($ast, true);

        if ($fallback_ast_repr !== $original_ast_repr) {
            $node_dumper = new \PhpParser\NodeDumper([
                'dumpComments' => true,
                'dumpPositions' => true,
            ]);
            $php_parser_node = $converter->phpparserParse($contents);
            try {
                $dump = $node_dumper->dump($php_parser_node);
            } catch (\PhpParser\Error $e) {
                $dump = 'could not dump PhpParser Node: ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString();
            }
            $original_ast_dump = \ast_dump($ast, AST_DUMP_LINENOS);
            try {
                $fallback_ast_dump = \ast_dump($fallback_ast, AST_DUMP_LINENOS);
            } catch(\Throwable $e) {
                $fallback_ast_dump = 'could not dump php-ast Node: ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString();
            }
            $parser_export = var_export($php_parser_node, true);
            $this->assertSame($original_ast_repr, $fallback_ast_repr,  <<<EOT
The fallback must return the same tree of php-ast nodes
File: $file_name
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
