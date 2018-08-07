<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

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

use SimpleComplex\Utils\Utils;
use SimpleComplex\Utils\Time;

$environment = CliEnvironment::getInstance();

(function($environment) {
    $container = Dependency::container();
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger');
    /** @var \SimpleComplex\Inspect\Inspect $inspect */
    $inspect = $container->get('inspect');

    $d = 0;
    $a = 2;
    $b = 6;
    $c = 14;

    $a = 1;
    $b = 8;
    $c = 127;

    $a_c = Utils::bitMaskSet($a, $c);
    $b_c = Utils::bitMaskSet($b, $c);
    $a_b = Utils::bitMaskSet($a, $b);
    $a_b_c = Utils::bitMaskSet(Utils::bitMaskSet($a, $b), $c);

    echo $inspect->variable([
            'a' => $a,
            'b' => $b,
            'c' => $c,
            'c hasnt a' => !Utils::bitMaskHas($c, $a),
            'c hasnt b' => !Utils::bitMaskHas($c, $b),
            'b hasnt a' => !Utils::bitMaskHas($b, $a),
            'c without c' => ($c & $c),
            'c without a' => ($c & $a),
            'c without b' => ($c & $b),
            'b without a' => ($b & $a),
            'a + c' => $a_c,
            'a + c has a' => Utils::bitMaskHas($a_c, $a),
            'a + c has c' => Utils::bitMaskHas($a_c, $c),
            'a + c hasnt b' => ($a_c & $b),
            'a + c remove a' => Utils::bitMaskRemove($a_c, $a) == $c,
            'a + c remove c' => Utils::bitMaskRemove($a_c, $c) == $a,
            'a + c remove b' => Utils::bitMaskRemove($a_c, $b),
            'b + c' => $b_c,
            'b + c has b' => Utils::bitMaskHas($b_c, $b),
            'b + c has c' => Utils::bitMaskHas($b_c, $c),
            'b + c hasnt a' => ($b_c & $a),
            'b + c remove b' => Utils::bitMaskRemove($b_c, $b) == $c,
            'b + c remove c' => Utils::bitMaskRemove($b_c, $c) == $b,
            'b + c remove a' => Utils::bitMaskRemove($b_c, $a),
            'a + b' => $a_b,
            'a + b has a' => Utils::bitMaskHas($a_b, $a),
            'a + b has b' => Utils::bitMaskHas($a_b, $b),
            'a + b hasnt c' => ($a_b & $c),
            'a + b remove a' => Utils::bitMaskRemove($a_b, $a) == $b,
            'a + b remove b' => Utils::bitMaskRemove($a_b, $b) == $a,
            'a + b remove c' => Utils::bitMaskRemove($a_b, $c),
            'a + b + c' => $a_b_c,
            'a + b + c has a' => Utils::bitMaskHas($a_b_c, $a),
            'a + b + c has b' => Utils::bitMaskHas($a_b_c, $b),
            'a + b + c has c' => Utils::bitMaskHas($a_b_c, $c),
            'a + b + c remove a' => Utils::bitMaskRemove($a_b_c, $a) == Utils::bitMaskSet($b, $c),
            'a + b + c remove b' => Utils::bitMaskRemove($a_b_c, $b) == Utils::bitMaskSet($a, $c),
            'a + b + c remove c' => Utils::bitMaskRemove($a_b_c, $c) == Utils::bitMaskSet($a, $b),
            (1 | 2 | 4 | 8 | 16 | 32 | 64),
        ]) . "\n";
    return;



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
