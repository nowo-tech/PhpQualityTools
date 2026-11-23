<?php

declare(strict_types=1);

/*
 * Run by ConfigurationThreadStackTestCase in a PHP process whose stack is capped like a FrankenPHP thread on Alpine
 * (-d zend.max_allowed_stack_size=512K): it autoloads, and so compiles, the configuration class the way a request does,
 * builds the tree, processes the defaults and dumps both references.
 *
 * Arguments: <path of the host's vendor/autoload.php> <configuration class>
 */

use NowoTech\PhpQualityTools\Testing\ConfigurationThreadStack;

$autoload = $argv[1] ?? '';
$class = $argv[2] ?? '';
if ('' === $autoload || '' === $class || !is_file($autoload)) {
    fwrite(\STDERR, "Usage: php configuration-thread-stack.php <vendor/autoload.php> <configuration class>\n");
    // @igor-ignore - CLI usage error of a test subprocess script (php <script>), never run in a worker.
    exit(2);
}

require $autoload;

echo json_encode(ConfigurationThreadStack::inspect($class), \JSON_THROW_ON_ERROR);
