<?php

use \Phan\Issue;

/**
 * This configuration will be read and overlayed on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 *
 * @see src/Phan/Config.php
 * See Config for all configurable options.
 *
 * A Note About Paths
 * ==================
 *
 * Files referenced from this file should be defined as
 *
 * ```
 *   Config::projectPath('relative_path/to/file')
 * ```
 *
 * where the relative path is relative to the root of the
 * project which is defined as either the working directory
 * of the phan executable or a path passed in via the CLI
 * '-d' flag.
 */
return [

    // If true, missing properties will be created when
    // they are first seen. If false, we'll report an
    // error message.
    "allow_missing_properties" => false,

    // Allow null to be cast as any type and for any
    // type to be cast to null.
    "null_casts_as_any_type" => false,

    // If enabled, scalars (int, float, bool, string, null)
    // are treated as if they can cast to each other.
    'scalar_implicit_cast' => false,

    // If this has entries, scalars (int, float, bool, string, null)
    // are allowed to perform the casts listed.
    // E.g. ['int' => ['float', 'string'], 'float' => ['int'], 'string' => ['int'], 'null' => ['string']]
    // allows casting null to a string, but not vice versa.
    // (subset of scalar_implicit_cast)
    'scalar_implicit_partial' => [],

    // If true, seemingly undeclared variables in the global
    // scope will be ignored. This is useful for projects
    // with complicated cross-file globals that you have no
    // hope of fixing.
    'ignore_undeclared_variables_in_global_scope' => false,

    // Backwards Compatibility Checking
    'backward_compatibility_checks' => false,

    // If true, check to make sure the return type declared
    // in the doc-block (if any) matches the return type
    // declared in the method signature. This process is
    // slow.
    'check_docblock_signature_return_type_match' => true,

    // If true, check to make sure the return type declared
    // in the doc-block (if any) matches the return type
    // declared in the method signature. This process is
    // slow.
    'check_docblock_signature_param_type_match' => true,

    // (*Requires check_docblock_signature_param_type_match to be true*)
    // If true, make narrowed types from phpdoc params override
    // the real types from the signature, when real types exist.
    // (E.g. allows specifying desired lists of subclasses,
    //  or to indicate a preference for non-nullable types over nullable types)
    // Affects analysis of the body of the method and the param types passed in by callers.
    'prefer_narrowed_phpdoc_param_type' => true,

    // (*Requires check_docblock_signature_return_type_match to be true*)
    // If true, make narrowed types from phpdoc returns override
    // the real types from the signature, when real types exist.
    // (E.g. allows specifying desired lists of subclasses,
    //  or to indicate a preference for non-nullable types over nullable types)
    // Affects analysis of return statements in the body of the method and the return types passed in by callers.
    'prefer_narrowed_phpdoc_return_type' => true,

    // If enabled, check all methods that override a
    // parent method to make sure its signature is
    // compatible with the parent's. This check
    // can add quite a bit of time to the analysis.
    // This will also check if final methods are overridden, etc.
    'analyze_signature_compatibility' => true,

    // This setting maps case insensitive strings to union types.
    // This is useful if a project uses phpdoc that differs from the phpdoc2 standard.
    // If the corresponding value is the empty string, Phan will ignore that union type (E.g. can ignore 'the' in `@return the value`)
    // If the corresponding value is not empty, Phan will act as though it saw the corresponding unionTypes(s) when the keys show up in a UnionType of @param, @return, @var, @property, etc.
    //
    // This matches the **entire string**, not parts of the string.
    // (E.g. `@return the|null` will still look for a class with the name `the`, but `@return the` will be ignored with the below setting)
    //
    // (These are not aliases, this setting is ignored outside of doc comments).
    // (Phan does not check if classes with these names exist)
    //
    // Example setting: ['unknown' => '', 'number' => 'int|float', 'char' => 'string', 'long' => 'int', 'the' => '']
    'phpdoc_type_mapping' => [ ],

    // Set to true in order to attempt to detect dead
    // (unreferenced) code. Keep in mind that the
    // results will only be a guess given that classes,
    // properties, constants and methods can be referenced
    // as variables (like `$class->$property` or
    // `$class->$method()`) in ways that we're unable
    // to make sense of.
    'dead_code_detection' => false,

    // Run a quick version of checks that takes less
    // time
    "quick_mode" => false,

    // Enable or disable support for generic templated
    // class types.
    'generic_types_enabled' => true,

    // The minimum severity level to report on. This can be
    // set to Issue::SEVERITY_LOW, Issue::SEVERITY_NORMAL or
    // Issue::SEVERITY_CRITICAL.
    'minimum_severity' => Issue::SEVERITY_LOW,

    // Add any issue types (such as 'PhanUndeclaredMethod')
    // here to inhibit them from being reported
    'suppress_issue_types' => [
        // 'PhanUndeclaredMethod',
    ],

    // If empty, no filter against issues types will be applied.
    // If non-empty, only issues within the list will be emitted
    // by Phan.
    'whitelist_issue_types' => [
    ],

    // A list of files to include in analysis
    'file_list' => [
        // 'vendor/phpunit/phpunit/src/Framework/TestCase.php',
    ],

    // A regular expression to match files to be excluded
    // from parsing and analysis and will not be read at all.
    //
    // This is useful for excluding groups of test or example
    // directories/files, unanalyzable files, or files that
    // can't be removed for whatever reason.
    // (e.g. '@Test\.php$@', or '@vendor/.*/(tests|Tests)/@')
    'exclude_file_regex' => '@^vendor/.*/(tests|Tests)/@',

    // A file list that defines files that will be excluded
    // from parsing and analysis and will not be read at all.
    //
    // This is useful for excluding hopelessly unanalyzable
    // files that can't be removed for whatever reason.
    'exclude_file_list' => [],

    // The number of processes to fork off during the analysis
    // phase.
    'processes' => 1,

    // A list of directories that should be parsed for class and
    // method information. After excluding the directories
    // defined in exclude_analysis_directory_list, the remaining
    // files will be statically analyzed for errors.
    //
    // Thus, both first-party and third-party code being used by
    // your application should be included in this list.
    'directory_list' => [
        'src',
        'vendor/nikic/php-parser/lib',
    ],

    // A directory list that defines files that will be excluded
    // from static analysis, but whose class and method
    // information should be included.
    //
    // Generally, you'll want to include the directories for
    // third-party code (such as "vendor/") in this list.
    //
    // n.b.: If you'd like to parse but not analyze 3rd
    //       party code, directories containing that code
    //       should be added to the `directory_list` as
    //       to `exclude_analysis_directory_list`.
    "exclude_analysis_directory_list" => [
        'vendor',
    ],

    // By default, Phan will log error messages to stdout if PHP is using options that slow the analysis.
    // (e.g. PHP is compiled with --enable-debug or when using XDebug)
    'skip_slow_php_options_warning' => false,

    // A list of plugin files to execute
    'plugins' => [
        // NOTE: src/Phan/Language/Internal/FunctionSignatureMap.php mixes value without key as return type with values having keys deliberately.
        'vendor/phan/phan/.phan/plugins/AlwaysReturnPlugin.php',  // (TODO: make BlockExitStatus more reliable)
        'vendor/phan/phan/.phan/plugins/DollarDollarPlugin.php',
        'vendor/phan/phan/.phan/plugins/DuplicateArrayKeyPlugin.php',
        'vendor/phan/phan/.phan/plugins/UnreachableCodePlugin.php',  // (TODO: make BlockExitStatus more reliable)
        // NOTE: This plugin only produces correct results when
        //       Phan is run on a single core (-j1).
        // 'vendor/phan/phan/.phan/plugins/UnusedSuppressionPlugin.php',
    ],

];
