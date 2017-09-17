<?php

function print_usage_and_exit(string $msg = '') {
    global $argv;
    fprintf(STDERR, "%sUsage: %s pathname.php\nConverts fooBar to foo_bar in variable names\n", $msg ? $msg . "\n" : '', $argv[0]);
    exit(1);
}

function convert_codestyle() {
    global $argv;
    if (count($argv) !== 2) {
        print_usage_and_exit();
    }
    $path = $argv[1];
    if (!file_exists($path)) {
        print_usage_and_exit("File '$path' does not exist");
    }
    $contents = file_get_contents($path);
    // Conservatively replace fooBar, but not fooBAR or fooB
    $new_contents = preg_replace_callback('@(\$)([a-z][A-Za-z0-9]*([A-Z][a-z0-9]+)+)\b@', function(array $args) : string {
        list($prefix, $msg) = $args;
        return $args[1] . preg_replace_callback('@[A-Z][a-z0-9]@', function(array $inner_args) {

            $msg = $inner_args[0];
            assert(strlen($msg) === 2);
            return '_' . strtolower($msg[0]) . $msg[1];
        }, $args[2]);
    }, $contents);
    echo $new_contents;
}

convert_codestyle();
