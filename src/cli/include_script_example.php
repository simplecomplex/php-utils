<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2014-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

use \SimpleComplex\Utils\Dependency;
use \SimpleComplex\Utils\CliEnvironment;
use \SimpleComplex\Utils\Utils;

/**
 * Include script example, for 'utils-execute' CLI command.
 *
 * @code
 * # CLI
 * cd vendor/simplecomplex/utils/src/cli
 * php cli.phpsh utils-execute include_script_example.php
 * @endcode
 *
 * @return void
 */
function simplecomplex_utils_include_script_example() /*: void*/
{
    $container = Dependency::container();
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger');
    /** @var \SimpleComplex\Inspect\Inspect $inspect */
    $inspect = $container->get('inspect');

    $environment = CliEnvironment::getInstance();

    // Work...

    $environment->echoMessage('It worked :-)', 'success');
}
simplecomplex_utils_include_script_example();
