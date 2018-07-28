<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

use SimpleComplex\Utils\Bootstrap;
use SimpleComplex\Utils\Dependency;
use SimpleComplex\Utils\CliEnvironment;

/**
 * Include script example, for 'utils-execute' CLI command.
 *
 * @code
 * # CLI
 * cd vendor/simplecomplex/utils/src/cli
 * php cli.phpsh utils-execute backend/vendor/simplecomplex/utils/src/cli/utils_execute_example.php -yf
 * @endcode
 */

Bootstrap::prepareDependenciesIfExist();

use SimpleComplex\Utils\Time;

$environment = CliEnvironment::getInstance();

(function($environment) {
    $container = Dependency::container();
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger');
    /** @var \SimpleComplex\Inspect\Inspect $inspect */
    $inspect = $container->get('inspect');

    $original = new Time('2018-06-30T09:05:11.123456+02:00');

    $logger->debug(
        "Time\n"
        . $inspect->variable([
            '' . $original->toISOUTC('microseconds'),
            '' . $original->toISOZonal('microseconds'),
            '' . $original->modifySafely('+59 seconds')->toISOUTC('microseconds'),
            $original->toISOZonal('microseconds')
        ])
    );

})($environment);

$environment->echoMessage('It worked :-)', 'success');
