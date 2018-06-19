<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2014-2017 Jacob Friis Mathiasen
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

$container = Dependency::container();
/** @var \Psr\Log\LoggerInterface $logger */
$logger = $container->get('logger');
/** @var \SimpleComplex\Inspect\Inspect $inspect */
$inspect = $container->get('inspect');

$environment = CliEnvironment::getInstance();

$utils = \SimpleComplex\Utils\Utils::getInstance();

use SimpleComplex\Utils\PathList;

//$list = (new PathList($utils->documentRoot() . '/backend/vendor/simplecomplex/utils'))
$list = (new PathList('/home/jacob/Documents'))
    //->includeExtensions(['php'])
    ->dirs()
    ->skipUnreadable()
    /*->customFilter(function(\SplFileInfo $item) {
        return $item->getSize() >= 5000;
    })*/
    ->itemValue(function(\SplFileInfo $item) {
        return is_readable($item->getPathname());
    })
    ->find();
$logger->debug(
    "PathList\n"
    . $inspect->variable($list->describe())
);

// Work...
$a = [
    'a' => 'alpha',
    'b' => 'beta',
    'gamma'
];
$b = [
    'a' => 'skrid',
    'b' => null,
    'omega',
    'c' => 'theta',
];
$c = [
];

$logger->debug(
    "array_replace_recursive\n"
    . $inspect->variable(array_replace_recursive($a, $b, $c))
);
$logger->debug(
    "array_replace_recursive\n"
    . $inspect->variable($utils->arrayMergeRecursive($a, $b, $c))
);
$logger->debug(
    "array_replace_recursive\n"
    . $inspect->variable(array_merge_recursive($a, $b, $c))
);

$environment->echoMessage('It worked :-)', 'success');
