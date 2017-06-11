<?php declare(strict_types=1);
namespace ASTConverter;

use PhpParser\ParserFactory;

/**
 * Source: https://github.com/TysonAndre/php-parser-to-php-ast
 * Uses PhpParser to create an instance of \ast\Node.
 * Useful if the php-ast extension isn't actually installed.
 * @author Tyson Andre
 */
class ASTConverter {
    // The latest stable version of php-ast.
    // For something > 40, update the library's release.
    // For something < 40, there are no releases.
    const AST_VERSION = 40;

    private static $should_add_placeholders = false;

    public static function set_should_add_placeholders(bool $value) : void {
        self::$should_add_placeholders = $value;
    }

    public static function ast_parse_code_fallback(string $source, int $version, bool $suppressErrors = false, array &$errors = null) {
        if ($version !== self::AST_VERSION) {
            throw new \InvalidArgumentException(sprintf("Unexpected version: want %d, got %d", self::AST_VERSION, $version));
        }
        // Aside: this can be implemented as a stub.
        $parserNode = self::phpparser_parse($source, $suppressErrors, $errors);
        return self::phpparser_to_phpast($parserNode, $version);
    }

    public static function phpparser_parse(string $source, bool $suppressErrors = false, array &$errors = null) {
        $parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $errorHandler = $suppressErrors ? new \PhpParser\ErrorHandler\Collecting() : null;
        // $nodeDumper = new PhpParser\NodeDumper();
        $result = $parser->parse($source, $errorHandler);
        if ($suppressErrors) {
            $errors = $errorHandler->getErrors();
        }
        return $result;
    }


    /**
     * @param \PhpParser\Node|\PhpParser\Node[] $parserNode
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

    private static function _phpparser_stmtlist_to_ast_node(array $parserNodes, ?int $lineno) {
        $stmts = new \ast\Node();
        $stmts->kind = \ast\AST_STMT_LIST;
        $stmts->flags = 0;
        $children = [];
        foreach ($parserNodes as $parserNode) {
            $childNode = self::_phpparser_node_to_ast_node($parserNode);
            if (is_array($childNode)) {
                // Echo_ returns multiple children.
                foreach ($childNode as $childNodePart) {
                    $children[] = $childNodePart;
                }
            } else if (!is_null($childNode)) {
                $children[] = $childNode;
            }
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
     * @param \PhpParser\Node $n - The node from PHP-Parser
     * @return \ast\Node|\ast\Node[]|string|int|float|bool|null - whatever \ast\parse_code would return as the equivalent.
     * @suppress PhanUndeclaredProperty
     */
    private static function _phpparser_node_to_ast_node($n) {
        if (!($n instanceof \PhpParser\Node)) {
            throw new \InvalidArgumentException("Invalid type for node: " . (is_object($n) ? get_class($n) : gettype($n)));
        }
        $startLine = $n->getAttribute('startLine');

        // in alphabetical order
        switch (get_class($n)) {
        case 'PhpParser\Node\Arg':
            // FIXME: handle unpack
            return self::_phpparser_node_to_ast_node($n->value);
        case 'PhpParser\Node\Expr\Array_':
            return self::_phpparser_array_to_ast_array($n, $startLine);
        case 'PhpParser\Node\Expr\Assign':
            return self::_ast_node_assign(
                self::_phpparser_node_to_ast_node($n->var),
                self::_phpparser_node_to_ast_node($n->expr),
                $startLine
            );
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
        case 'PhpParser\Node\Expr\BinaryOp\BitwiseAnd':
            return self::_ast_node_binaryop(\ast\flags\BINARY_BITWISE_AND, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\BitwiseOr':
            return self::_ast_node_binaryop(\ast\flags\BINARY_BITWISE_OR, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\BitwiseXor':
            return self::_ast_node_binaryop(\ast\flags\BINARY_BITWISE_XOR, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\Concat':
            return self::_ast_node_binaryop(\ast\flags\BINARY_CONCAT, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\Coalesce':
            return self::_ast_node_binaryop(\ast\flags\BINARY_COALESCE, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\Div':
            return self::_ast_node_binaryop(\ast\flags\BINARY_DIV, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\Greater':
            return self::_ast_node_binaryop(\ast\flags\BINARY_IS_GREATER, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\GreaterOrEqual':
            return self::_ast_node_binaryop(\ast\flags\BINARY_IS_GREATER_OR_EQUAL, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\LogicalAnd':
            return self::_ast_node_binaryop(\ast\flags\BINARY_BOOL_AND, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\LogicalOr':
            return self::_ast_node_binaryop(\ast\flags\BINARY_BOOL_OR, $n, $startLine);
            // FIXME: rest of binary operations.
        case 'PhpParser\Node\Expr\BinaryOp\Mod':
            return self::_ast_node_binaryop(\ast\flags\BINARY_MOD, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\Mul':
            return self::_ast_node_binaryop(\ast\flags\BINARY_MUL, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\Minus':
            return self::_ast_node_binaryop(\ast\flags\BINARY_SUB, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\Plus':
            return self::_ast_node_binaryop(\ast\flags\BINARY_ADD, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\Pow':
            return self::_ast_node_binaryop(\ast\flags\BINARY_POW, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\ShiftLeft':
            return self::_ast_node_binaryop(\ast\flags\BINARY_SHIFT_LEFT, $n, $startLine);
        case 'PhpParser\Node\Expr\BinaryOp\ShiftRight':
            return self::_ast_node_binaryop(\ast\flags\BINARY_SHIFT_RIGHT, $n, $startLine);
        case 'PhpParser\Node\Expr\Closure':
            // TODO: is there a corresponding flag for $n->static? $n->byRef?
            return self::_ast_decl_closure(
                $n->byRef,
                $n->static,
                self::_phpparser_params_to_ast_params($n->params, $startLine),
                self::_phpparser_closure_uses_to_ast_closure_uses($n->uses, $startLine),
                self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine),
                self::_phpparser_type_to_ast_node($n->returnType, sl($n->returnType) ?: $startLine),
                $startLine,
                $n->getAttribute('endLine'),
                self::_extract_phpdoc_comment($n->getAttribute('comments'))
            );
            // FIXME: add a test of ClassConstFetch to php-ast
        case 'PhpParser\Node\Expr\ClassConstFetch':
            return self::_phpparser_classconstfetch_to_ast_classconstfetch($n, $startLine);
        case 'PhpParser\Node\Expr\ConstFetch':
            return astnode(\ast\AST_CONST, 0, ['name' => self::_phpparser_node_to_ast_node($n->name)], $startLine);
        case 'PhpParser\Node\Expr\ErrorSuppress':
            return self::_ast_node_unary_op(\ast\flags\UNARY_SILENCE, self::_phpparser_node_to_ast_node($n->expr), $startLine);
        case 'PhpParser\Node\Expr\Eval_':
            return self::_ast_node_eval(
                self::_phpparser_node_to_ast_node($n->expr),
                $startLine
            );
        case 'PhpParser\Node\Expr\Error':
            // TODO: handle this.
            return null;
        case 'PhpParser\Node\Expr\FuncCall':
            return self::_ast_node_call(
                self::_phpparser_node_to_ast_node($n->name),
                self::_phpparser_arg_list_to_ast_arg_list($n->args, $startLine),
                $startLine
            );
        case 'PhpParser\Node\Expr\Include_':
            return self::_ast_node_include(
                self::_phpparser_node_to_ast_node($n->expr),
                $startLine,
                $n->type
            );
        case 'PhpParser\Node\Expr\Instanceof_':
            return astnode(\ast\AST_INSTANCEOF, 0, [
                'expr'  => self::_phpparser_node_to_ast_node($n->expr),
                'class' => self::_phpparser_node_to_ast_node($n->class),
            ], $startLine);
        case 'PhpParser\Node\Expr\List_':
            return self::_phpparser_list_to_ast_list($n, $startLine);
        case 'PhpParser\Node\Expr\MethodCall':
            return self::_ast_node_method_call(
                self::_phpparser_node_to_ast_node($n->var),
                is_string($n->name) ? $n->name : self::_phpparser_node_to_ast_node($n->name),
                self::_phpparser_arg_list_to_ast_arg_list($n->args, $startLine),
                $startLine
            );
        case 'PhpParser\Node\Expr\New_':
            return astnode(\ast\AST_NEW, 0, [
                'class' => self::_phpparser_node_to_ast_node($n->class),
                'args' => self::_phpparser_arg_list_to_ast_arg_list($n->args, $startLine),
            ], $startLine);
        case 'PhpParser\Node\Expr\PropertyFetch':
            return self::_phpparser_propertyfetch_to_ast_prop($n, $startLine);
        case 'PhpParser\Node\Expr\StaticCall':
            return self::_ast_node_static_call(
                self::_phpparser_node_to_ast_node($n->class),
                is_string($n->name) ? $n->name : self::_phpparser_node_to_ast_node($n->name),
                self::_phpparser_arg_list_to_ast_arg_list($n->args, $startLine),
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
        case 'PhpParser\Node\Name\FullyQualified':
            return self::_ast_node_name_fullyqualified(
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
        case 'PhpParser\Node\Scalar\String_':
            return (string)$n->value;
        case 'PhpParser\Node\Scalar\MagicConst\Class_':
            return self::_ast_magic_const(\ast\flags\MAGIC_CLASS, $startLine);
        case 'PhpParser\Node\Scalar\MagicConst\Dir':
            return self::_ast_magic_const(\ast\flags\MAGIC_DIR, $startLine);
        case 'PhpParser\Node\Scalar\MagicConst\File':
            return self::_ast_magic_const(\ast\flags\MAGIC_FILE, $startLine);
        case 'PhpParser\Node\Scalar\MagicConst\Function_':
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
                self::_phpparser_catch_types_to_ast_catch_types($n->types, $startLine),
                $n->var,
                self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine),
                $startLine
            );
        case 'PhpParser\Node\Stmt\Class_':
            $endLine = $n->getAttribute('endLine') ?: $startLine;
            return self::_ast_stmt_class(
                self::_phpparser_class_flags_to_ast_class_flags($n->flags),
                $n->name,
                $n->extends,
                $n->implements ?: null,
                self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine),
                $startLine,
                $endLine
            );
        case 'PhpParser\Node\Stmt\ClassConst':
            return self::_phpparser_class_const_to_ast_node($n, $startLine);
        case 'PhpParser\Node\Stmt\Declare_':
            return self::_ast_stmt_declare(
                self::_phpparser_declare_list_to_ast_declares($n->declares, $startLine),
                $n->stmts !== null ? self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine) : null,
                $startLine
            );
        case 'PhpParser\Node\Stmt\Echo_':
            $astEchos = [];
            foreach ($n->exprs as $expr) {
                $astEchos[] = self::_ast_stmt_echo(
                    self::_phpparser_node_to_ast_node($expr),
                    $startLine
                );
            }
            return count($astEchos) === 1 ? $astEchos[0] : $astEchos;
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
                $endLine,
                self::_extract_phpdoc_comment($n->getAttribute('comments'))
            );
        case 'PhpParser\Node\Stmt\If_':
            return self::_phpparser_if_stmt_to_ast_if_stmt($n);
        case 'PhpParser\Node\Stmt\Interface_':
            $endLine = $n->getAttribute('endLine') ?: $startLine;
            return self::_ast_stmt_class(
                \ast\flags\CLASS_INTERFACE,
                $n->name,
                null,
                null,
                self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine),
                $startLine,
                $endLine
            );
        case 'PhpParser\Node\Stmt\GroupUse':
            return self::_ast_stmt_group_use(
                $n->type,
                implode('\\', $n->prefix->parts ?? []),
                self::_phpparser_use_list_to_ast_use_list($n->uses),
                $startLine
            );
        case 'PhpParser\Node\Stmt\Property':
            return self::_phpparser_property_to_ast_node($n, $startLine);
        case 'PhpParser\Node\Stmt\Return_':
            return self::_ast_stmt_return(self::_phpparser_node_to_ast_node($n->expr), $startLine);
        case 'PhpParser\Node\Stmt\Trait_':
            $endLine = $n->getAttribute('endLine') ?: $startLine;
            return self::_ast_stmt_class(
                \ast\flags\CLASS_TRAIT,
                $n->name,
                null,
                null,
                self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine),
                $startLine,
                $endLine
            );
        case 'PhpParser\Node\Stmt\TryCatch':
            if (!is_array($n->catches)) {
                throw new \Error(sprintf("Unsupported type %s\n%s", get_class($n), var_export($n->catches, true)));
            }
            return self::_ast_node_try(
                self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine), // $n->try
                self::_phpparser_catchlist_to_ast_catchlist($n->catches),
                isset($n->finally) ? self::_phpparser_stmtlist_to_ast_node($n->finally->stmts, sl($n->finally)) : null,
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
                self::_phpparser_stmtlist_to_ast_node($n->stmts, $startLine),
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
        $children['finally'] = $finallyNode;
        $node->children = $children;
        return $node;
    }

    // FIXME types
    private static function _ast_stmt_catch($types, string $var, $stmts, int $lineno) : \ast\Node {
        $node = new \ast\Node();
        $node->kind = \ast\AST_CATCH;
        $node->lineno = $lineno;
        $node->flags = 0;
        $node->children = [
            'class' => $types,
            'var' => astnode(\ast\AST_VAR, 0, ['name' => $var], end($types->children)->lineno),  // FIXME AST_VAR
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

    private static function _phpparser_catch_types_to_ast_catch_types(array $types, int $line) : \ast\Node {
        $astTypes = [];
        foreach ($types as $type) {
            $astTypes[] = self::_phpparser_node_to_ast_node($type);
        }
        return astnode(\ast\AST_NAME_LIST, 0, $astTypes, $line);
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

    private static function _ast_node_assign($var, $expr, int $line) : ?\ast\Node {
        if ($expr === null) {
            if (self::$should_add_placeholders) {
                $expr = '__INCOMPLETE_EXPR__';
            } else {
                return null;
            }
        }
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

    private static function _phpparser_include_flags_to_ast_include_flags(int $type) : int {
        switch($type) {
        case \PhpParser\Node\Expr\Include_::TYPE_INCLUDE:
            return \ast\flags\EXEC_INCLUDE;
        case \PhpParser\Node\Expr\Include_::TYPE_INCLUDE_ONCE:
            return \ast\flags\EXEC_INCLUDE_ONCE;
        case \PhpParser\Node\Expr\Include_::TYPE_REQUIRE:
            return \ast\flags\EXEC_REQUIRE;
        case \PhpParser\Node\Expr\Include_::TYPE_REQUIRE_ONCE:
            return \ast\flags\EXEC_REQUIRE_ONCE;
        default:
            throw new \Error("Unrecognized PhpParser include/require type $type");
        }
    }
    private static function _ast_node_include($expr, int $line, int $type) : \ast\Node {
        $flags = self::_phpparser_include_flags_to_ast_include_flags($type);
        return astnode(\ast\AST_INCLUDE_OR_EVAL, $flags, ['expr' => $expr], $line);
    }

    /**
     * @param \PhpParser\Node\Name|string|null $type
     * @return \ast\Node|null
     */
    private static function _phpparser_type_to_ast_node($type, int $line) {
        if (is_null($type)) {
            return $type;
        }
        if (\is_string($type)) {
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
        $node->flags = $byRef ? \ast\flags\PARAM_REF : 0;
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
        // FIXME: Why is this a special case in php-ast? (e.g. nullable int has no flags on the nullable node)
        $node->flags = ($type->kind === \ast\AST_TYPE && $type->flags === \ast\flags\TYPE_ARRAY) ? $type->flags : 0;
        $node->lineno = $line;
        $node->children = ['type' => $type];
        return $node;
    }

    private static function _ast_node_name(string $name, int $line) : \ast\Node {
        return astnode(\ast\AST_NAME, \ast\flags\NAME_NOT_FQ, ['name' => $name], $line);
    }

    private static function _ast_node_name_fullyqualified(string $name, int $line) : \ast\Node {
        return astnode(\ast\AST_NAME, \ast\flags\NAME_FQ, ['name' => $name], $line);
    }

    private static function _ast_node_variable($expr, int $line) : ?\ast\Node {
        // TODO: 2 different ways to handle an Error. 1. Add a placeholder. 2. remove all of the statements in that tree.
        if ($expr instanceof \PhpParser\Node) {
            $expr = self::_phpparser_node_to_ast_node($expr);
            if ($expr === null) {
                if (self::$should_add_placeholders) {
                    $expr = '__INCOMPLETE_VARIABLE__';
                } else {
                    return null;
                }
            }
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

    /**
     * @param \PhpParser\Expr\ClosureUse[] $uses
     * @param int $line
     */
    private static function _phpparser_closure_uses_to_ast_closure_uses(
        array $uses,
        int $line
    ) : \ast\Node {
        $astUses = [];
        foreach ($uses as $use) {
            $astUses[] = astnode(\ast\AST_CLOSURE_VAR, $use->byRef ? 1 : 0, ['name' => $use->var], $use->getAttribute('startLine'));
        }
        return astnode(\ast\AST_CLOSURE_USES, 0, $astUses, $astUses[0]->lineno ?? $line);

    }

    private static function _ast_decl_closure(
        bool $byRef,
        bool $static,
        \ast\Node $params,
        $uses,
        $stmts,
        $returnType,
        int $startLine,
        int $endLine,
        ?string $docComment
    ) : \ast\Node\Decl {
        $node = new \ast\Node\Decl;
        $node->kind = \ast\AST_CLOSURE;
        $node->flags = ($byRef ? \ast\flags\RETURNS_REF : 0) | ($static ? \ast\flags\MODIFIER_STATIC : 0);
        $node->lineno = $startLine;
        $node->endLineno = $endLine;
        if ($docComment) { $node->docComment = $docComment; }
        $node->children = [
            'params' => $params,
            'uses' => $uses,
            'stmts' => $stmts,
            'returnType' => $returnType,
        ];
        $node->name = '{closure}';
        return $node;
    }

    /**
     * @suppress PhanTypeMismatchProperty
     */
    private static function _ast_decl_function(
        bool $byRef,
        string $name,
        \ast\Node $params,
        ?array $uses,
        $returnType,
        $stmts,
        int $line,
        int $endLine,
        ?string $docComment
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
        $node->docComment = $docComment;
        return $node;
    }

    private static function _phpparser_class_flags_to_ast_class_flags(int $flags) {
        $astFlags = 0;
        if ($flags & \PhpParser\Node\Stmt\Class_::MODIFIER_ABSTRACT) {
            $astFlags |= \ast\flags\CLASS_ABSTRACT;
        }
        if ($flags & \PhpParser\Node\Stmt\Class_::MODIFIER_FINAL) {
            $astFlags |= \ast\flags\CLASS_FINAL;
        }
        return $astFlags;
    }

    /**
     * @param int $flags
     * @param ?string $name
     * @param mixed|null $extends TODO
     * @param array $implements
     * @param \ast\Node|null $stmts
     * @param int $line
     * @param int $endLine
     * @suppress PhanTypeMismatchProperty (?string to string|null is incorrectly reported)
     */
    private static function _ast_stmt_class(
        int $flags,
        ?string $name,
        $extends,
        ?array $implements,
        ?\ast\Node $stmts,
        int $line,
        int $endLine
    ) : \ast\Node\Decl {
        if ($name === null) {
            $flags |= \ast\flags\CLASS_ANONYMOUS;
        }

        $node = new \ast\Node\Decl;
        $node->kind = \ast\AST_CLASS;
        $node->flags = $flags;
        $node->lineno = $line;
        $node->endLineno = $endLine;
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
        case \PhpParser\Node\Stmt\Use_::TYPE_NORMAL:
            return \ast\flags\USE_NORMAL;
        case \PhpParser\Node\Stmt\Use_::TYPE_FUNCTION:
            return \ast\flags\USE_FUNCTION;
        case \PhpParser\Node\Stmt\Use_::TYPE_CONSTANT:
            return \ast\flags\USE_CONST;
        case \PhpParser\Node\Stmt\Use_::TYPE_UNKNOWN:
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

    private static function _ast_if_elem($cond, $stmts, int $line) {
        return astnode(\ast\AST_IF_ELEM, 0, ['cond' => $cond, 'stmts' => $stmts], $line);
    }

    private static function _phpparser_if_stmt_to_ast_if_stmt(\PhpParser\Node $node) {
        assert($node instanceof \PhpParser\Node\Stmt\If_);
        $startLine = $node->getAttribute('startLine');
        $condLine = sl($node->cond) ?: $startLine;
        $ifElem = self::_ast_if_elem(
            self::_phpparser_node_to_ast_node($node->cond),
            self::_phpparser_stmtlist_to_ast_node($node->stmts, $condLine),
            $startLine
        );
        $ifElems = [$ifElem];
        foreach ($node->elseifs as $elseIf) {
            $ifElems[] = $elseIf; // FIXME
        }
        $parserElseNode = $node->else;
        if ($parserElseNode) {
            $parserElseLine = $parserElseNode->getAttribute('startLine');
            $ifElems[] = self::_ast_if_elem(
                null,
                self::_phpparser_stmtlist_to_ast_node($parserElseNode->stmts, $parserElseLine),
                $parserElseLine
            );
        }
        return astnode(\ast\AST_IF, 0, $ifElems, $startLine);

    }

    /**
     * @suppress PhanUndeclaredProperty
     */
    private static function _ast_node_assignop(int $flags, \PhpParser\Node $node, int $startLine) {
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

    /**
     * @suppress PhanUndeclaredProperty
     */
    private static function _ast_node_binaryop(int $flags, \PhpParser\Node $n, int $startLine) {
        return astnode(
            \ast\AST_BINARY_OP,
            $flags,
            self::_phpparser_nodes_to_left_right_children($n->left, $n->right),
            $startLine
        );
    }

    private static function _phpparser_nodes_to_left_right_children($left, $right) : array {
        return [
            'left' => self::_phpparser_node_to_ast_node($left),
            'right' => self::_phpparser_node_to_ast_node($right),
        ];
    }

    private static function _phpparser_propelem_to_ast_propelem(\PhpParser\Node\Stmt\PropertyProperty $n, ?string $docComment) : \ast\Node{
        $children = [
            'name' => $n->name,
            'default' => $n->default ? self::_phpparser_node_to_ast_node($n->default) : null,
        ];

        $startLine = $n->getAttribute('startLine');

        return astnode(\ast\AST_PROP_ELEM, 0, $children, $startLine, self::_extract_phpdoc_comment($n->getAttribute('comments') ?? $docComment));
    }

    private static function _phpparser_constelem_to_ast_constelem(\PhpParser\Node\Const_ $n, ?string $docComment) : \ast\Node{
        $children = [
            'name' => $n->name,
            'value' => self::_phpparser_node_to_ast_node($n->value),
        ];

        $startLine = $n->getAttribute('startLine');

        return astnode(\ast\AST_CONST_ELEM, 0, $children, $startLine, self::_extract_phpdoc_comment($n->getAttribute('comments') ?? $docComment));
    }
    private static function _phpparser_visibility_to_ast_visibility(int $visibility) : int {
        switch($visibility) {
        case \PHPParser\Node\Stmt\Class_::MODIFIER_PUBLIC:
            return \ast\flags\MODIFIER_PUBLIC;
        case \PHPParser\Node\Stmt\Class_::MODIFIER_PROTECTED:
            return \ast\flags\MODIFIER_PROTECTED;
        case \PHPParser\Node\Stmt\Class_::MODIFIER_PRIVATE:
            return \ast\flags\MODIFIER_PRIVATE;
        case 0:
            return \ast\flags\MODIFIER_PUBLIC;  // FIXME?
        default:
            throw new \Error("Invalid phpparser visibility " . $visibility);
        }
    }

    private static function _phpparser_property_to_ast_node(\PhpParser\Node $n, int $startLine) : \ast\Node {
        assert($n instanceof \PHPParser\Node\Stmt\Property);

        $propElems = [];
        $docComment = self::_extract_phpdoc_comment($n->getAttribute('comments'));
        foreach ($n->props as $i => $prop) {
            $propElems[] = self::_phpparser_propelem_to_ast_propelem($prop, $i === 0 ? $docComment : null);
        }
        $flags = self::_phpparser_visibility_to_ast_visibility($n->flags);

        return astnode(\ast\AST_PROP_DECL, $flags, $propElems, $propElems[0]->lineno ?: $startLine);
    }

    private static function _phpparser_class_const_to_ast_node(\PhpParser\Node $n, int $startLine) : \ast\Node {
        assert($n instanceof \PHPParser\Node\Stmt\ClassConst);

        $constElems = [];
        $docComment = self::_extract_phpdoc_comment($n->getAttribute('comments'));
        foreach ($n->consts as $i => $prop) {
            $constElems[] = self::_phpparser_constelem_to_ast_constelem($prop, $i === 0 ? $docComment : null);
        }
        $flags = self::_phpparser_visibility_to_ast_visibility($n->flags);

        return astnode(\ast\AST_CLASS_CONST_DECL, $flags, $constElems, $constElems[0]->lineno ?: $startLine);
    }

    private static function _phpparser_declare_list_to_ast_declares(array $declares, int $startLine) : \ast\Node {
        $astDeclareElements = [];
        foreach ($declares as $declare) {
            $children = [
                'name' => $declare->key,
                'value' => self::_phpparser_node_to_ast_node($declare->value),
            ];
            $astDeclareElements[] = astnode(\ast\AST_CONST_ELEM, 0, $children, $declare->getAttribute('startLine'));
        }
        return astnode(\ast\AST_CONST_DECL, 0, $astDeclareElements, $startLine);

    }

    private static function _ast_stmt_declare(\ast\Node $declares, ?\ast\Node $stmts, int $startLine) : \ast\Node{
        $children = [
            'declares' => $declares,
            'stmts' => $stmts,
        ];
        return astnode(\ast\AST_DECLARE, 0, $children, $startLine);
    }

    private static function _ast_node_call($expr, $args, int $startLine) : \ast\Node{
        if (\is_string($expr)) {
            if (substr($expr, 0, 1) === '\\') {
                $expr = substr($expr, 1);
            }
            $expr = astnode(\ast\AST_NAME, \ast\flags\NAME_FQ, ['name' => $expr], $startLine);
        }
        return astnode(\ast\AST_CALL, 0, ['expr' => $expr, 'args' => $args], $startLine);
    }

    private static function _ast_node_method_call($expr, $method, \ast\Node $args, int $startLine) : \ast\Node {
        return astnode(\ast\AST_METHOD_CALL, 0, ['expr' => $expr, 'method' => $method, 'args' => $args], $startLine);
    }

    private static function _ast_node_static_call($class, $method, \ast\Node $args, int $startLine) : \ast\Node {
        // TODO: is this applicable?
        if (\is_string($class)) {
            if (substr($class, 0, 1) === '\\') {
                $expr = substr($class, 1);
            }
            $class = astnode(\ast\AST_NAME, \ast\flags\NAME_FQ, ['name' => $class], $startLine);
        }
        return astnode(\ast\AST_STATIC_CALL, 0, ['class' => $class, 'method' => $method, 'args' => $args], $startLine);
    }

    private static function _extract_phpdoc_comment($comments) : ?string {
        if (\is_string($comments)) {
            return $comments;
        }
        if ($comments === null || count($comments) === 0) {
            return null;
        }
        for ($i = count($comments) - 1; $i >= 0; $i--) {
            if ($comments[$i] instanceof \PhpParser\Comment\Doc) {
                return $comments[$i]->getText();
            } else {
                var_dump($comments[$i]);
            }
        }
        return null;
        // return var_export($comments, true);
    }

    private static function _phpparser_list_to_ast_list(\PhpParser\Node $n, int $startLine) : \ast\Node {
        assert($n instanceof \PhpParser\Node\Expr\List_);
        $astItems = [];
        foreach ($n->items as $item) {
            if ($item === null) {
                $astItems[] = null;
            } else {
                $astItems[] = astnode(\ast\AST_ARRAY_ELEM, 0, [
                    'value' => self::_phpparser_node_to_ast_node($item->value),
                    'key' => $item->key !== null ? self::_phpparser_node_to_ast_node($item->key) : null,
                ], $item->getAttribute('startLine'));
            }
        }
        return astnode(\ast\AST_ARRAY, \ast\flags\ARRAY_SYNTAX_LIST, $astItems, $startLine);
    }

    private static function _phpparser_array_to_ast_array(\PhpParser\Node $n, int $startLine) : \ast\Node {
        assert($n instanceof \PhpParser\Node\Expr\Array_);
        $astItems = [];
        foreach ($n->items as $item) {
            if ($item === null) {
                $astItems[] = null;
            } else {
                $astItems[] = astnode(\ast\AST_ARRAY_ELEM, 0, [
                    'value' => self::_phpparser_node_to_ast_node($item->value),
                    'key' => $item->key !== null ? self::_phpparser_node_to_ast_node($item->key) : null,
                ], $item->getAttribute('startLine'));
            }
        }
        return astnode(\ast\AST_ARRAY, \ast\flags\ARRAY_SYNTAX_SHORT, $astItems, $startLine);
    }

    private static function _phpparser_propertyfetch_to_ast_prop(\PhpParser\Node $n, int $startLine) : ?\ast\Node {
        assert($n instanceof \PhpParser\Node\Expr\PropertyFetch);
        $name = $n->name;
        if (is_object($name)) {
            $name = self::_phpparser_node_to_ast_node($name);
        }
        if ($name === null) {
            if (self::$should_add_placeholders) {
                $name = '__INCOMPLETE_PROPERTY__';
            } else {
                return null;
            }
        }
        return astnode(\ast\AST_PROP, 0, [
            'expr'  => self::_phpparser_node_to_ast_node($n->var),
            'prop'  => is_object($name) ?  : $name,
        ], $startLine);
    }

    private static function _phpparser_classconstfetch_to_ast_classconstfetch(\PhpParser\Node $n, int $startLine) : ?\ast\Node {
        assert($n instanceof \PhpParser\Node\Expr\ClassConstFetch);
        $name = $n->name;
        if (is_object($name)) {
            $name = self::_phpparser_node_to_ast_node($name);
        }
        if ($name === null) {
            if (self::$should_add_placeholders) {
                $name = '__INCOMPLETE_CLASS_CONST__';
            } else {
                return null;
            }
        }
        return astnode(\ast\AST_CLASS_CONST, 0, [
            'class' => self::_phpparser_node_to_ast_node($n->class),
            'const' => $name,
        ], $startLine);
    }
}

/**
 * @suppress PhanTypeMismatchProperty https://github.com/etsy/phan/issues/609
 * @suppress PhanUndeclaredProperty - docComment really exists.
 * NOTE: this may be removed in the future.
 *
 * Phan was used while developing this. The asserts can be cleaned up in the future.
 */
function astnode(int $kind, int $flags, ?array $children, int $lineno, ?string $docComment = null) : \ast\Node {
    $node = new \ast\Node();
    $node->kind = $kind;
    $node->flags = $flags;
    $node->lineno = $lineno;
    $node->children = $children;
    if (\is_string($docComment)) {
        $node->docComment = $docComment;
    }
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
