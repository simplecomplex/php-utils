<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

/**
 * @see CliCommand
 *
 * @package SimpleComplex\Utils
 */
interface CliCommandInterface
{
    /**
     * @param CliCommand|null $command
     *
     * @return void
     *      Must exit on match.
     */
    public function executeCommandOnMatch(/*?CliCommand*/ $command);
}
