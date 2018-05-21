<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils\Interfaces;

use SimpleComplex\Utils\CliCommand;

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
     * Alias of this provider.
     *
     * Must be lisp-cased.
     *
     * @return string
     */
    public function commandProviderAlias() : string;

    /**
     * @param CliCommand $command
     *
     * @return mixed
     *      Return value of the executed command, if any.
     *      May well exit.
     *
     * @throws \LogicException
     *      If the command mapped by CliEnvironment
     *      isn't this provider's command.
     */
    public function executeCommand(CliCommand $command);
}
