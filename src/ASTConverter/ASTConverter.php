<?php declare(strict_types=1);
namespace ASTConverter;

use PhpParser\ParserFactory;

/**
 * @suppress PhanTypeMismatchProperty https://github.com/etsy/phan/issues/609
 * NOTE: this may be removed in the future.
 */
function astnode(int $kind, int $flags, ?array $children, int $lineno) : \ast\Node {
    $node = new \ast\Node();
    $node->kind = $kind;
    $node->flags = $flags;
    $node->lineno = $lineno;
    $node->children = $children;
    return $node;
}

function sl($node) : ?int {
    if ($node instanceof \PhpParser\Node) {
        return $node->getAttribute('startLine');
    }
    return null;
}

function el($node) : ?int {
    if ($node instanceof \PhpParser\Node) {
        return $node->getAttribute('endLine');
    }
    return null;
}

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
                $childNodeLine = sl($parserNode);
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
     * @param \PHPParser\Node $n - The node from PHP-Parser
     * @return \ast\Node|string|int|float|bool|null - whatever \ast\parse_code would return as the equivalent.
     * @suppress PhanUndeclaredProperty
     */
    private static function _phpparser_node_to_ast_node($n) {
        if (!($n instanceof \PHPParser\Node)) {
            throw new \InvalidArgumentException("Invalid type for node: " . (is_object($n) ? get_class($n) : gettype($n)));
        }
        $startLine = $n->getAttribute('startLine');

        // in alphabetical order
        switch (get_class($n)) {
        case 'PhpParser\Node\Expr\Assign':
            return self::_ast_node_assign(
                self::_phpparser_node_to_ast_node($n->var),
                self::_phpparser_node_to_ast_node($n->expr),
                $startLine
            );
        case 'PhpParser\Node\Expr\AssignOp\ShiftLeft':
            return self::_ast_node_assignop(\ast\flags\BINARY_SHIFT_LEFT, $n, $startLine);
        case 'PhpParser\Node\Expr\AssignOp\BitwiseAnd':
            return self::_ast_node_assignop(\ast\flags\BINARY_BITWISE_AND, $n, $startLine);
        case 'PhpParser\Node\Expr\AssignOp\BitwiseOr':
            return self::_ast_node_assignop(\ast\flags\BINARY_BITWISE_OR, $n, $startLine);
        case 'PhpParser\Node\Expr\AssignOp\BitwiseXor':
            return self::_ast_node_assignop(\ast\flags\BINARY_BITWISE_XOR, $n, $startLine);
        case 'PhpParser\Node\Expr\AssignOp\Concat':
            return self::_ast_node_assignop(\ast\flags\BINARY_CONCAT, $n, $startLine);
        case 'PhpParser\Node\Expr\AssignOp\Div':
            return self::_ast_node_assignop(\ast\flags\BINARY_DIV, $n, $startLine);
        case 'PhpParser\Node\Expr\AssignOp\Mod':
            return self::_ast_node_assignop(\ast\flags\BINARY_MOD, $n, $startLine);
        case 'PhpParser\Node\Expr\AssignOp\Mul':
            return self::_ast_node_assignop(\ast\flags\BINARY_MUL, $n, $startLine);
        case 'PhpParser\Node\Expr\AssignOp\Minus':
            return self::_ast_node_assignop(\ast\flags\BINARY_SUB, $n, $startLine);
        case 'PhpParser\Node\Expr\AssignOp\Plus':
            return self::_ast_node_assignop(\ast\flags\BINARY_ADD, $n, $startLine);
        case 'PhpParser\Node\Expr\AssignOp\Pow':
            return self::_ast_node_assignop(\ast\flags\BINARY_POW, $n, $startLine);
        case 'PhpParser\Node\Expr\AssignOp\ShiftLeft':
            return self::_ast_node_assignop(\ast\flags\BINARY_SHIFT_LEFT, $n, $startLine);
        case 'PhpParser\Node\Expr\AssignOp\ShiftRight':
            return self::_ast_node_assignop(\ast\flags\BINARY_SHIFT_RIGHT, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\Coalesce':
            return astnode(
                \ast\AST_BINARY_OP,
                \ast\flags\BINARY_COALESCE,
                self::_phpparser_nodes_to_left_right_children($n->left, $n->right),
                $startLine
            );
        case 'PhpParser\Node\Expr\BinaryOp\Greater':
            return astnode(
                \ast\AST_BINARY_OP,
                \ast\flags\BINARY_IS_GREATER,
                self::_phpparser_nodes_to_left_right_children($n->left, $n->right),
                $startLine
            );
        case 'PhpParser\Node\Expr\BinaryOp\GreaterOrEqual':
            return astnode(
                \ast\AST_BINARY_OP,
                \ast\flags\BINARY_IS_GREATER_OR_EQUAL,
                self::_phpparser_nodes_to_left_right_children($n->left, $n->right),
                $startLine
            );
        case 'PhpParser\Node\Expr\BinaryOp\LogicalAnd':
            return astnode(
                \ast\AST_BINARY_OP,
                \ast\flags\BINARY_BOOL_AND,
                self::_phpparser_nodes_to_left_right_children($n->left, $n->right),
                $startLine
            );
        case 'PhpParser\Node\Expr\BinaryOp\LogicalOr':
            return astnode(
                \ast\AST_BINARY_OP,
                \ast\flags\BINARY_BOOL_OR,
                self::_phpparser_nodes_to_left_right_children($n->left, $n->right),
                $startLine
            );
        case 'PhpParser\Node\Expr\Closure':
            // TODO: is there a corresponding flag for $n->static? $n->byRef?
            return self::_ast_decl_closure(
                $n->byRef,
                self::_phpparser_params_to_ast_params($n->params, $startLine),
                self::_phpparser_closure_uses_to_ast_closure_uses($n->uses, $startLine),
                self::_phpparser_stmtlist_to_ast_node($n->stmts),
                self::_phpparser_type_to_ast_node($n->returnType, $startLine),
                0,
                $startLine,
                $n->getAttribute('endLine'),
                ''
            );
        case 'PhpParser\Node\Expr\ConstFetch':
            return astnode(\ast\AST_CONST, 0, ['name' => self::_phpparser_node_to_ast_node($n->name)], $startLine);
        case 'PhpParser\Node\Expr\ErrorSuppress':
            return self::_ast_node_unary_op(\ast\flags\UNARY_SILENCE, self::_phpparser_node_to_ast_node($n->expr), $startLine);
        case 'PhpParser\Node\Expr\Eval_':
            return self::_ast_node_eval(
                self::_phpparser_node_to_ast_node($n->expr),
                $startLine
            );
        /*case 'PhpParser\Node\Expr\FuncCall':
            return self::_ast_node_call(
                self::_phpparser_node_to_ast_node($n->expr),
                self::_phpparser_arg_list_to_ast_arg_list($n->args, $startLine),
                $startLine
            );*/
        case 'PhpParser\Node\Expr\Include_':
            return self::_ast_node_include(
                self::_phpparser_node_to_ast_node($n->expr),
                $startLine
            );
        case 'PhpParser\Node\Expr\UnaryMinus':
            return self::_ast_node_unary_op(\ast\flags\UNARY_MINUS, self::_phpparser_node_to_ast_node($n->expr), $startLine);
        case 'PhpParser\Node\Expr\UnaryPlus':
            return self::_ast_node_unary_op(\ast\flags\UNARY_PLUS, self::_phpparser_node_to_ast_node($n->expr), $startLine);
        case 'PhpParser\Node\Expr\Variable':
            return self::_ast_node_variable($n->name, $startLine);
        case 'PhpParser\Node\Name':
            return self::_ast_node_name(
                implode('\\', $n->parts),
                $startLine
            );
        case 'PhpParser\Node\NullableType':
            return self::_ast_node_nullable_type(
                self::_phpparser_type_to_ast_node($n->type, $startLine),
                $startLine
            );
        case 'PhpParser\Node\Param':
            $typeLine = sl($n->type) ?: $startLine;
            $defaultLine = sl($n->default) ?: $typeLine;
            return self::_ast_node_param(
                $n->byRef,
                $n->variadic,
                self::_phpparser_type_to_ast_node($n->type, $typeLine),
                $n->name,
                self::_phpparser_type_to_ast_node($n->default, $defaultLine),
                $startLine
            );
        case 'PhpParser\Node\Scalar\LNumber':
            return (int)$n->value;
        case 'PhpParser\Node\Scalar\String':
            return (string)$n->value;
        case 'PhpParser\Node\Scalar\MagicConst\Class_':
            return self::_ast_magic_const(\ast\flags\MAGIC_CLASS, $startLine);
        case 'PhpParser\Node\Scalar\MagicConst\Dir':
            return self::_ast_magic_const(\ast\flags\MAGIC_DIR, $startLine);
        case 'PhpParser\Node\Scalar\MagicConst\File':
            return self::_ast_magic_const(\ast\flags\MAGIC_FILE, $startLine);
        case 'PhpParser\Node\Scalar\MagicConst\Function':
            return self::_ast_magic_const(\ast\flags\MAGIC_FUNCTION, $startLine);
        case 'PhpParser\Node\Scalar\MagicConst\Line':
            return self::_ast_magic_const(\ast\flags\MAGIC_LINE, $startLine);
        case 'PhpParser\Node\Scalar\MagicConst\Method':
            return self::_ast_magic_const(\ast\flags\MAGIC_METHOD, $startLine);
        case 'PhpParser\Node\Scalar\MagicConst\Namespace_':
            return self::_ast_magic_const(\ast\flags\MAGIC_NAMESPACE, $startLine);
        case 'PhpParser\Node\Scalar\MagicConst\Trait_':
            return self::_ast_magic_const(\ast\flags\MAGIC_TRAIT, $startLine);
        case 'PhpParser\Node\Stmt\Catch_':
            return self::_ast_stmt_catch(
                $n->types,
                $n->var,
                self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine),
                $startLine
            );
        case 'PhpParser\Node\Stmt\Class_':
            $endLine = $n->getAttribute('endLine') ?: $startLine;
            return self::_ast_stmt_class(
                $n->name,
                $n->extends,
                $n->implements ?: null,
                self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine),
                $startLine,
                $endLine
            );
        /*case 'PhpParser\Node\Stmt\Declare_':
            return self::_ast_stmt_declare(
                $n->types,
                $n->var,
                self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine),
                $startLine
            );*/
        case 'PhpParser\Node\Stmt\Echo_':
            return self::_ast_stmt_echo(
                isset($n->expr) ? self::_phpparser_node_to_ast_node($n->expr) : null,
                $startLine
            );
        case 'PhpParser\Node\Stmt\Finally_':
            return self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine);
        case 'PhpParser\Node\Stmt\Function_':
            $endLine = $n->getAttribute('endLine') ?: $startLine;
            $returnType = $n->returnType;
            $returnTypeLine = sl($returnType) ?: $endLine;
            $astReturnType = self::_phpparser_type_to_ast_node($returnType, $returnTypeLine);

            return self::_ast_decl_function(
                $n->byRef,
                $n->name,
                self::_phpparser_params_to_ast_params($n->params, $startLine),
                null,  // uses
                self::_phpparser_type_to_ast_node($returnType, $returnTypeLine),
                self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine),
                $startLine,
                $endLine
                // $n->getAttribute('comments') - extract last PhpParser\Comment\Doc instance?
            );
        /*case 'PhpParser\Node\Stmt\If_': */

        case 'PhpParser\Node\Stmt\GroupUse':
            return self::_ast_stmt_group_use(
                $n->type,
                implode('\\', $n->prefix->parts ?? []),
                self::_phpparser_use_list_to_ast_use_list($n->uses),
                $startLine
            );
        case 'PhpParser\Node\Stmt\Return_':
            return self::_ast_stmt_return(self::_phpparser_node_to_ast_node($n->expr), $startLine);
        case 'PhpParser\Node\Stmt\TryCatch':
            if (!is_array($n->catches)) {
                throw new \Error(sprintf("Unsupported type %s\n%s", get_class($n), var_export($n->catches, true)));
            }
            return self::_ast_node_try(
                self::_phpparser_stmtlist_to_ast_node([]), // $n->try
                self::_phpparser_catchlist_to_ast_catchlist($n->catches),
                isset($n->finally) ? self::_phpparser_stmtlist_to_ast_node([$n->finally]) : null,
                $startLine
            );
        case 'PhpParser\Node\Stmt\Use_':
            return self::_ast_stmt_use(
                $n->type,
                self::_phpparser_use_list_to_ast_use_list($n->uses),
                $startLine
            );
        case 'PhpParser\Node\Stmt\While_':
            return self::_ast_node_while(
                self::_phpparser_node_to_ast_node($n->cond),
                self::_phpparser_stmtlist_to_ast_node($n->stmts),
                $startLine
            );
        default:
            return self::_ast_stub($n);
            // throw new \InvalidArgumentException("Unsupported subclass " . get_class($n));
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

    // FIXME types
    private static function _ast_stmt_catch($types, string $var, $stmts, int $lineno) : \ast\Node {
        $node = new \ast\Node();
        $node->kind = \ast\AST_CATCH_LIST;
        $node->lineno = $lineno;
        $node->flags = 0;
        $node->children = [
            'class' => $types,
            'var' => $var,  // FIXME AST_VAR
            'stmts' => $stmts,
        ];
        return $node;
    }

    private static function _phpparser_catchlist_to_ast_catchlist(array $catches) : \ast\Node {
        $node = new \ast\Node();
        $node->kind = \ast\AST_CATCH_LIST;
        $node->lineno = 0;
        $node->flags = 0;
        $children = [];
        foreach ($catches as $parserCatch) {
            $children[] = self::_phpparser_node_to_ast_node($parserCatch);
        }
        $node->children = $children;
        return $node;
    }

    private static function _ast_node_while($cond, $stmts, int $startLine) : \ast\Node {
        $node = new \ast\Node();
        $node->kind = \ast\AST_WHILE;
        $node->lineno = $startLine;
        $node->flags = 0;
        $node->children = [
            'cond' => $cond,
            'stmts' => $stmts,
        ];
        return $node;
    }

    private static function _ast_node_assign($var, $expr, int $line) : \ast\Node {
        $node = new \ast\Node();
        $node->kind = \ast\AST_ASSIGN;
        $node->flags = 0;
        $node->children = [
            'var'  => $var,
            'expr' => $expr,
        ];
        $node->lineno = $line;
        return $node;
    }

    private static function _ast_node_unary_op(int $flags, $expr, int $line) : \ast\Node {
        return astnode(\ast\AST_UNARY_OP, $flags, ['expr' => $expr], $line);
    }

    private static function _ast_node_eval($expr, int $line) : \ast\Node {
        return astnode(\ast\AST_INCLUDE_OR_EVAL, \ast\flags\EXEC_EVAL, ['expr' => $expr], $line);
    }

    private static function _ast_node_include($expr, int $line) : \ast\Node {
        return astnode(\ast\AST_INCLUDE_OR_EVAL, \ast\flags\EXEC_INCLUDE, ['expr' => $expr], $line);
    }

    /**
     * @param \PHPParser\Node\Name|string|null $type
     * @return \ast\Node|null
     */
    private static function _phpparser_type_to_ast_node($type, int $line) {
        if (is_null($type)) {
            return $type;
        }
        if (is_string($type)) {
            switch(strtolower($type)) {
            case 'null':
                $flags = \ast\flags\TYPE_NULL; break;
            case 'bool':
                $flags = \ast\flags\TYPE_BOOL; break;
            case 'int':
                $flags = \ast\flags\TYPE_LONG; break;
            case 'float':
                $flags = \ast\flags\TYPE_DOUBLE; break;
            case 'string':
                $flags = \ast\flags\TYPE_STRING; break;
            case 'array':
                $flags = \ast\flags\TYPE_ARRAY; break;
            case 'object':
                $flags = \ast\flags\TYPE_OBJECT; break;
            case 'callable':
                $flags = \ast\flags\TYPE_CALLABLE; break;
            case 'void':
                $flags = \ast\flags\TYPE_VOID; break;
            case 'iterable':
                $flags = \ast\flags\TYPE_ITERABLE; break;
            default:
                $node = new \ast\Node();
                $node->kind = \ast\AST_NAME;
                $node->flags = substr($type, 0, 1) === '\\' ? \ast\flags\NAME_FQ : \ast\flags\NAME_NOT_FQ;  // FIXME wrong.
                $node->lineno = $line;
                $node->children = [
                    'name' => $type,
                ];
                return $node;
            }
            $node = new \ast\Node();
            $node->kind = \ast\AST_TYPE;
            $node->flags = $flags;
            $node->lineno = $line;
            $node->children = [];
            return $node;
        }
        return self::_phpparser_node_to_ast_node($type);
    }

    /**
     * @param bool $byRef
     * @param \ast\Node|null $type
     */
    private static function _ast_node_param(bool $byRef, $variadic, $type, $name, $default, int $line) : \ast\Node{
        $node = new \ast\Node;
        $node->kind = \ast\AST_PARAM;
        $node->flags = 0;
        $node->lineno = $line;
        $node->children = [
            'type' => $type,
            'name' => $name,
            'default' => $default,
        ];

        return $node;
    }

    private static function _ast_node_nullable_type(\ast\Node $type, int $line) {
        $node = new \ast\Node;
        $node->kind = \ast\AST_NULLABLE_TYPE;
        $node->flags = 0;
        $node->lineno = $line;
        $node->children = ['type' => $type];
        return $node;
    }

    private static function _ast_node_name(string $name, int $line) : \ast\Node {
        $node = new \ast\Node;
        $node->kind = \ast\AST_NAME;
        $node->flags = 0;
        $node->lineno = $line;
        $node->children = ['name' => $name];
        return $node;
    }

    private static function _ast_node_variable($expr, int $line) : \ast\Node {
        if ($expr instanceof \PhpParser\Node) {
            $expr = self::_phpparser_node_to_ast_node($expr);
        }
        $node = new \ast\Node;
        $node->kind = \ast\AST_VAR;
        $node->flags = 0;
        $node->lineno = $line;
        $node->children = ['name' => $expr];
        return $node;
    }

    private static function _ast_magic_const(int $flags, int $line) {
        return astnode(\ast\AST_MAGIC_CONST, $flags, [], $line);
    }

    private static function _phpparser_params_to_ast_params(array $parserParams, int $line) : \ast\Node {
        $newParams = [];
        foreach ($parserParams as $parserNode) {
            $newParams[] = self::_phpparser_node_to_ast_node($parserNode);
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

    private static function _phpparser_closure_uses_to_ast_closure_uses(
        array $uses,
        int $line
    ) : \ast\Node {
        $astUses = [];
        foreach ($uses as $use) {
            $astUses[] = astnode(\ast\AST_CLOSURE_VAR, $use->byRef ? 1 : 0, ['name' => 'TODO'], $use->getAttribute('startLine'));
        }
        return astnode(\ast\AST_CLOSURE_USES, 0, $astUses, $astUses[0]->lineno ?? $line);

    }

    private static function _ast_decl_closure(
        bool $byRef,
        \ast\Node $params,
        $uses,
        $stmts,
        $returnType,
        int $flags,
        int $startLine,
        int $endLine,
        ?string $docComment
    ) : \ast\Node\Decl {
        $node = new \ast\Node\Decl;
        $node->kind = \ast\AST_CLOSURE;
        $node->flags = 0;
        $node->lineno = $startLine;
        $node->endLineno = $endLine;
        if ($docComment) { $node->docComment = $docComment; }
        $node->children = [
            'params' => $params,
            'uses' => $uses,
            'stmts' => $stmts,
            'returnType' => $returnType,
        ];
        $node->name = '';
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
        int $endLine = 0,
        string $docComment = ""
    ) : \ast\Node\Decl {
        $node = new \ast\Node\Decl;
        $node->kind = \ast\AST_FUNC_DECL;
        $node->flags = 0;
        $node->lineno = $line;
        $node->endLineno = $endLine;
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
     * @param \ast\Node|null $stmts
     * @param int $line
     * @param int $endLine
     */
    private static function _ast_stmt_class($name, $extends, ?array $implements, $stmts, int $line, int $endLine) : \ast\Node\Decl {
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

    private static function _phpparser_arg_list_to_ast_arg_list(array $args, int $line) : \ast\Node {
        $node = new \ast\Node();
        $node->kind = \ast\AST_ARG_LIST;
        $node->lineno = $line;
        $node->flags = 0;
        $astArgs = [];
        foreach ($args as $arg) {
            $astArgs[] = self::_phpparser_node_to_ast_node($arg);
        }
        $node->children = $astArgs;
        return $node;
    }

    private static function _phpparser_use_list_to_ast_use_list(?array $uses) : array {
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

    private static function _ast_stmt_echo($expr, int $line) : \ast\Node {
        $node = new \ast\Node();
        $node->kind = \ast\AST_ECHO;
        $node->flags = 0;
        $node->lineno = $line;
        $node->children = ['expr' => $expr];
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

    /**
     * @suppress PhanUndeclaredProperty
     */
    private static function _ast_node_assignop(int $flags, \PHPParser\Node $node, int $startLine) {
        return astnode(
            \ast\AST_ASSIGN_OP,
            $flags,
            [
                'var' => self::_phpparser_node_to_ast_node($node->var),
                'expr' => self::_phpparser_node_to_ast_node($node->expr),
            ],
            $startLine
        );
    }

    private static function _phpparser_nodes_to_left_right_children($left, $right) : array {
        return [
            'left' => self::_phpparser_node_to_ast_node($left),
            'right' => self::_phpparser_node_to_ast_node($right),
        ];
    }
}
