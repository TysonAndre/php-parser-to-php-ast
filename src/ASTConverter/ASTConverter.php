<?php declare(strict_types=1);
namespace ASTConverter;

use PhpParser\ParserFactory;

class ASTConverter {
    // The latest stable version of php-ast.
    // For something > 40, update the library's release.
    // For something < 40, there are no releases.
    const AST_VERSION = 40;

    public static function ast_parse_code_fallback(string $source, int $version) {
        if ($version !== self::AST_VERSION) {
            throw new \InvalidArgumentException(sprintf("Unexpected version: want %d, got %d", self::AST_VERSION, $version));
        }
        // Aside: this can be implemented as a stub.
        $parserNode = self::phpparser_parse($source);
        return self::phpparser_to_phpast($parserNode, $version);
    }

    public static function phpparser_parse(string $source) {
        $parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        // $nodeDumper = new PhpParser\NodeDumper();
        return $parser->parse($source);
    }


    /**
     * @param \PHPParser\Node|\PHPParser\Node[] $parserNode
     * @param int $version
     */
    public static function phpparser_to_phpast($parserNode, int $version) {
        if ($version !== self::AST_VERSION) {
            throw new \InvalidArgumentException(sprintf("Unexpected version: want %d, got %d", self::AST_VERSION, $version));
        }
        if (is_array($parserNode)) {
            return self::_phpparser_stmtlist_to_ast_node($parserNode, 1);
        }
        return self::_phpparser_node_to_ast_node($parserNode);
    }

    private static function _phpparser_stmtlist_to_ast_node(array $parserNodes, int $lineno = null) {
        $stmts = new \ast\Node();
        $stmts->kind = \ast\AST_STMT_LIST;
        $stmts->flags = 0;
        $children = [];
        foreach ($parserNodes as $parserNode) {
            $childNode = self::_phpparser_node_to_ast_node($parserNode);
            $children[] = $childNode;
        }
        if (!is_int($lineno)) {
            foreach ($parserNodes as $parserNode) {
                $childNodeLine = $parserNode->getAttribute('startLine');
                if ($childNodeLine > 0) {
                    $lineno = $childNodeLine;
                    break;
                }
            }
        }
        $stmts->lineno = $lineno ?? 0;
        $stmts->children = $children;
        return $stmts;
    }

    /**
     * @param \PHPParser\Node $parserNode
     * @return \ast\Node|string|int|float|bool|null - whatever \ast\parse_code would return as the equivalent.
     * @suppress PhanUndeclaredProperty
     */
    private static function _phpparser_node_to_ast_node($parserNode) {
        if (!($parserNode instanceof \PHPParser\Node)) {
            throw new \InvalidArgumentException("Invalid type for node: " . (is_object($parserNode) ? get_class($parserNode) : gettype($parserNode)));
        }
        $startLine = $parserNode->getAttribute('startLine');

        // in alphabetical order
        switch (get_class($parserNode)) {
        case 'PhpParser\Node\Expr\ErrorSuppress':
            return self::_ast_node_unary_op(\ast\flags\UNARY_SILENCE, self::_phpparser_node_to_ast_node($parserNode->expr), $startLine);
        case 'PhpParser\Node\Expr\UnaryMinus':
            return self::_ast_node_unary_op(\ast\flags\UNARY_MINUS, self::_phpparser_node_to_ast_node($parserNode->expr), $startLine);
        case 'PhpParser\Node\Expr\UnaryPlus':
            return self::_ast_node_unary_op(\ast\flags\UNARY_PLUS, self::_phpparser_node_to_ast_node($parserNode->expr), $startLine);
        case 'PhpParser\Node\Expr\Variable':
            return self::_ast_node_variable($parserNode->name, $startLine);
        case 'PhpParser\Node\Scalar\LNumber':
            return (int)$parserNode->value;
        case 'PhpParser\Node\Stmt\Class_':
            return self::_ast_stmt_class($parserNode->name, $parserNode->extends, $parserNode->implements, $parserNode->stmts, $startLine);
        case 'PhpParser\Node\Stmt\Function_':
            $startLine = $startLine;
            return self::_ast_decl_function(
                $parserNode->byRef,
                $parserNode->name,
                self::_phpparser_params_to_ast_params($parserNode->params, $startLine),
                null,  // uses
                $parserNode->returnType,
                self::_phpparser_stmtlist_to_ast_node($parserNode->stmts, $startLine),
                $startLine
            );
        case 'PhpParser\Node\Stmt\GroupUse':
            return self::_ast_stmt_group_use(
                $parserNode->type,
                implode('\\', $parserNode->prefix->parts ?? []),
                self::_phpparser_use_list_to_ast_use_list($parserNode->uses),
                $startLine
            );
        case 'PhpParser\Node\Stmt\Return_':
            return self::_ast_stmt_return(self::_phpparser_node_to_ast_node($parserNode->expr), $startLine);
        case 'PhpParser\Node\Stmt\TryCatch':
            if (!is_array($parserNode->catches)) {
                throw new \Error(sprintf("Unsupported type %s\n%s", get_class($parserNode), var_export($parserNode->catches, true)));
            }
            return self::_ast_node_try(
                self::_phpparser_stmtlist_to_ast_node([]), // $parserNode->try
                self::_phpparser_catchlist_to_ast_catchlist($parserNode->catches),
                isset($parserNode->finally) ? self::_phpparser_stmtlist_to_ast_node([$parserNode->finally]) : null,
                $startLine
            );
        case 'PhpParser\Node\Stmt\Use_':
            return self::_ast_stmt_use(
                $parserNode->type,
                self::_phpparser_use_list_to_ast_use_list($parserNode->uses),
                $startLine
            );
        default:
            return self::_ast_stub($parserNode);
            // throw new \InvalidArgumentException("Unsupported subclass " . get_class($parserNode));
        }
    }

    private static function _ast_node_try(
        $tryNode,
        $catchesNode,
        $finallyNode,
        int $startLine
    ) : \ast\Node {
        $node = new \ast\Node();
        $node->kind = \ast\AST_TRY;
        $node->flags = 0;
        $node->lineno = $startLine;
        $children = [
            'try' => $tryNode,
        ];
        if ($catchesNode !== null) {
            $children['catches'] = $catchesNode;
        }
        if ($finallyNode !== null) {
            $children['finally'] = $finallyNode;
        }
        $node->children = $children;
        return $node;
    }

    private static function _phpparser_catchlist_to_ast_catchlist(array $catches) : \ast\Node {
        $node = new \ast\Node();
        $node->kind = \ast\AST_CATCH_LIST;
        $node->lineno = 0;
        $node->flags = 0;
        $children = [];
        foreach ($catches as $parserCatch) {
            $children[] = self::_ast_stub($parserCatch);
        }
        $node->children = $children;
        return $node;
    }

    private static function _ast_node_unary_op(int $flags, $expr, int $line) : \ast\Node {
        $node = new \ast\Node();
        $node->kind = \ast\AST_UNARY_OP;
        $node->flags = $flags;
        $node->children = ['expr' => $expr];
        $node->lineno = $line;
        return $node;
    }

    private static function _ast_node_variable($expr, int $line) : \ast\Node {
        if ($expr instanceof \PhpParser\Node) {
            $expr = self::_phpparser_node_to_ast_node($expr);
        }
        $node = new \ast\Node;
        $node->kind = \ast\AST_VAR;
        $node->flags = 0;
        $node->children = ['name' => $expr];
        $node->lineno = $line;
        return $node;
    }

    private static function _phpparser_params_to_ast_params(array $parserParams, int $line) : \ast\Node {
        $newParams = [];
        foreach ($parserParams as $parserNode) {
            $newParams[] = self::_ast_stub($parserNode);
        }
        $newParamsNode = new \ast\Node();
        $newParamsNode->kind = \ast\AST_PARAM_LIST;
        $newParamsNode->flags = 0;
        $newParamsNode->children = $newParams;
        $newParamsNode->lineno = $line;
        return $newParamsNode;
    }

    /**
     * @suppress PhanTypeMismatchProperty - Deliberately wrong type of kind
     */
    private static function _ast_stub($parserNode) : \ast\Node{
        $node = new \ast\Node();
        $node->kind = "TODO:" . get_class($parserNode);
        $node->flags = 0;
        $node->lineno = $parserNode->getAttribute('startLine');
        $node->children = null;
        return $node;
    }

    private static function _ast_decl_function(
        bool $byRef,
        string $name,
        \ast\Node $params,
        array $uses = null,
        $returnType = null,
        $stmts = null,
        int $line = 0,
        string $docComment = ""
    ) : \ast\Node\Decl {
        $node = new \ast\Node\Decl;
        $node->kind = \ast\AST_FUNC_DECL;
        $node->flags = 0;
        $node->lineno = $line;
        $node->children = [
            'params' => $params,
            'uses' => $uses,
            'stmts' => $stmts,
            'returnType' => $returnType,
        ];
        $node->name = $name;
        return $node;
    }

    /**
     * @param string $name
     * @param mixed|null $extends TODO
     * @param array $implements
     * @param array $statements
     */
    private static function _ast_stmt_class($name, $extends, array $implements, array $stmts, int $line) : \ast\Node\Decl {
        $node = new \ast\Node\Decl;
        $node->kind = \ast\AST_CLASS;
        $node->flags = 0;
        $node->lineno = $line;
        $node->children = [
            'extends'    => $extends, // FIXME
            'implements' => $implements, // FIXME
            'stmts'      => $stmts,
        ];
        $node->name = $name;

        return $node;
    }

    private static function _phpparser_use_list_to_ast_use_list(array $uses) : array {
        $astUses = [];
        foreach ($uses as $use) {
            $astUse = new \ast\Node();
            $astUse->kind = \ast\AST_USE_ELEM;
            $astUse->flags = self::_phpparser_use_type_to_ast_flags($use->type);  // FIXME
            $astUse->lineno = $use->getAttribute('startLine');
            // ast doesn't fill in an alias if it's identical to the real name,
            // but phpparser does?
            $name = implode('\\', $use->name->parts);
            $alias = $use->alias;
            $astUse->children = [
                'name' => $name,
                'alias' => $alias !== $name ? $alias : null,
            ];
            $astUses[] = $astUse;
        }
        return $astUses;
    }

    /**
     * @param int $type
     */
    private static function _phpparser_use_type_to_ast_flags($type) : int {
        switch($type) {
        case \PHPParser\Node\Stmt\Use_::TYPE_NORMAL:
            return \ast\flags\USE_NORMAL;
        case \PHPParser\Node\Stmt\Use_::TYPE_FUNCTION:
            return \ast\flags\USE_FUNCTION;
        case \PHPParser\Node\Stmt\Use_::TYPE_CONSTANT:
            return \ast\flags\USE_CONST;
        case \PHPParser\Node\Stmt\Use_::TYPE_UNKNOWN:
        default:
            return 0;
        }
    }

    private static function _ast_stmt_use($type, array $uses, int $line) : \ast\Node{
        $node = new \ast\Node();
        $node->kind = \ast\AST_USE;
        $node->flags = self::_phpparser_use_type_to_ast_flags($type);
        $node->lineno = $line;
        $node->children = $uses;
        return $node;
    }

    private static function _ast_stmt_group_use($type, $prefix, array $uses, int $line) : \ast\Node{
        $node = new \ast\Node();
        $node->kind = \ast\AST_GROUP_USE;
        $node->flags = self::_phpparser_use_type_to_ast_flags($type);
        $node->lineno = $line;
        $node->children = [
            'prefix' => $prefix,
            'uses' => self::_ast_stmt_use(0, $uses, $line),
        ];
        return $node;
    }

    private static function _ast_stmt_return($expr, int $line) : \ast\Node {
        $node = new \ast\Node();
        $node->kind = \ast\AST_RETURN;
        $node->flags = 0;
        $node->lineno = $line;
        $node->children = ['expr' => $expr];
        return $node;
    }

}
