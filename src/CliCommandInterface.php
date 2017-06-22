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
 * Implementing class must provide CLI commands and a means to execute them.
 *
 * For examples:
 * @see \SimpleComplex\Cache\CliCache
 * @see \SimpleComplex\Config\CliIniSectionedConfig
 * @see \SimpleComplex\JsonLog\CliJsonLog
 *
 * @see CliCommand
 *
 * @package SimpleComplex\Utils
 */
interface CliCommandInterface
{
    /**
     * @param CliCommand $command
     *
     * @return void
     *      Must exit.
     *
     * @throws \LogicException
     *      If the command mapped by CliEnvironment
     *      isn't this provider's command.
     */
    public function executeCommand(CliCommand $command);
}
