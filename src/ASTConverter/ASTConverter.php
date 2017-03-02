<?php declare(strict_types=1);
namespace ASTConverter;

class ASTConverter {
    // The latest stable version of php-ast.
    // For something > 40, update the library's release.
    // For something < 40, there are no releases.
    const AST_VERSION = 40;

    public static function ast_parse_code_fallback(string $source, int $version) {
        if ($version !== self::AST_VERSION) {
            throw new InvalidArgumentException(sprintf("Unexpected version: want %d, got %d", self::AST_VERSION, $version));
        }
        // Aside: this can be implemented as a stub.
        $node = new \ast\Node();
        return $node;
    }
}
