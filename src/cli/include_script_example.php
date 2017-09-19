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

use \SimpleComplex\Validate\Validate;

/**
 * Include script example, for 'utils-execute' CLI command.
 *
 * @code
 * # CLI
 * cd vendor/simplecomplex/utils/src/cli
 * php cli.phpsh utils-execute vendor/simplecomplex/utils/src/cli/include_script_example.php
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

    $logger->debug(
        "2770\n"
        . $inspect->variable(mkdir('/www/0_angular/sites/seb-my-cases.source/private/lib/simplecomplex/file-cache/stores/2770', 2770))
    );
    $logger->debug(
        "770\n"
        . $inspect->variable(mkdir('/www/0_angular/sites/seb-my-cases.source/private/lib/simplecomplex/file-cache/stores/770', 0770))
    );


    $environment->echoMessage('It worked :-)', 'success');
}
simplecomplex_utils_include_script_example();
