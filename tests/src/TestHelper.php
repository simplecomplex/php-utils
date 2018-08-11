<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Tests\Utils;

use SimpleComplex\Utils\Dependency;
use SimpleComplex\Utils\Utils;

/**
 * phpunit test helper.
 *
 * @package SimpleComplex\Tests\Utils
 */
class TestHelper
{
    /**
     * Expected path to tests' src dir, relative to the vendor dir.
     *
     * @var string
     */
    const PATH_TESTS = 'simplecomplex/utils/tests/src';

    /**
     * @var string
     */
    const LOG_LEVEL = 'debug';

    /**
     * @param string|mixed $message
     *      Non-string gets stringified; if possible.
     *
     * @return void
     */
    public static function log($message) /*: void*/
    {
        try {
            $msg = '' . $message;
            $container = Dependency::container();
            if (!$container->has('logger')) {
                return;
            }
            /** @var \Psr\Log\LoggerInterface $logger */
            $logger = $container->get('logger');
            $logger->log(
                static::LOG_LEVEL,
                $msg
            );
        }
        catch (\Throwable $ignore) {
        }
    }

    /**
     * @param string|mixed $message
     *      Non-string gets stringified; if possible.
     * @param mixed $subject
     *
     * @return void
     */
    public static function logVariable($message, $subject) /*: void*/
    {
        try {
            $msg = '' . $message;
            $container = Dependency::container();
            if (!$container->has('logger')) {
                return;
            }
            /** @var \Psr\Log\LoggerInterface $logger */
            $logger = $container->get('logger');
            if ($container->has('inspect')) {
                /** @var \SimpleComplex\Inspect\Inspect $inspect */
                $inspect = $container->get('inspect');
                $logger->log(
                    static::LOG_LEVEL,
                    $msg . (!$msg ? '' : "\n") . $inspect->variable(
                        $subject,
                        [
                            'wrappers' => 1,
                        ]
                    )
                );
            }
            else {
                $logger->log(
                    static::LOG_LEVEL,
                    $msg . (!$msg ? '' : "\n") . str_replace("\n", '', var_export($subject, true))
                );
            }
        }
        catch (\Throwable $ignore) {
        }
    }

    /**
     * @param string|mixed $message
     *      Non-string gets stringified; if possible.
     * @param \Throwable|null $xcptn
     *      Null: do backtrace.
     * @param array $options
     *      For Inspect.
     *
     * @return void
     */
    public static function logTrace($message = '', \Throwable $xcptn = null, array $options = []) /*: void*/
    {
        try {
            $msg = '' . $message;
            $container = Dependency::container();
            if (!$container->has('logger')) {
                return;
            }
            /** @var \Psr\Log\LoggerInterface $logger */
            $logger = $container->get('logger');
            if ($container->has('inspect')) {
                /** @var \SimpleComplex\Inspect\Inspect $inspect */
                $inspect = $container->get('inspect');
                if (!$options) {
                    $opts = [
                        'wrappers' => 1,
                        'limit' => 3,
                    ];
                } else {
                    $opts = $options;
                    if (!isset($opts['wrappers'])) {
                        $opts['wrappers'] = 1;
                    } else {
                        $opts['wrappers'] += 1;
                    }
                    if (!isset($opts['limit'])) {
                        $opts['limit'] = 3;
                    }
                }
                $logger->log(
                    static::LOG_LEVEL,
                    $msg . (!$msg ? '' : "\n") . $inspect->trace($xcptn, $opts)
                );
            }
            elseif ($xcptn) {
                $logger->log(static::LOG_LEVEL, $msg ? $msg : '%exception', [
                    'exception' => $xcptn,
                ]);
            }
        }
        catch (\Throwable $ignore) {
        }
    }

    /**
     * @deprecated Use Utils::getInstance()->documentRoot() instead.
     *
     * @return string
     *
     * @throws \SimpleComplex\Utils\Exception\ConfigurationException
     *      Propagated; from Utils::documentRoot().
     */
    public static function documentRoot() : string
    {
        return Utils::getInstance()->documentRoot();
    }

    /**
     * Find file relative to document root, vendor dir, or the tests dir.
     *
     * @see TestHelper::PATH_TESTS
     *
     * @param string $path
     * @param string $relativeTo
     *      'document_root': relative to document root; default and fallback.
     *      'vendor': relative to the vendor dir.
     *      'tests': relative to PATH_TESTS.
     *
     * @throws \RuntimeException
     *      Propagated; from Utils::resolvePath()
     * @throws \LogicException
     *      Propagated.
     *
     * @return string
     *      Empty: not found.
     */
    public static function fileFind(string $path, string $relativeTo = 'document_root') : string
    {
        $utils = Utils::getInstance();
        switch ($relativeTo) {
            case 'tests':
            case 'test':
                $absolute_path = $utils->resolvePath(
                    $utils->vendorDir() . '/' . trim(static::PATH_TESTS, '/\\') . '/' . trim($path, '/\\')
                );
                if (file_exists($absolute_path)) {
                    return $absolute_path;
                }
                return '';
            case 'vendor':
                $absolute_path = $utils->resolvePath($utils->vendorDir() . '/' . trim($path, '/\\'));
                if (file_exists($absolute_path)) {
                    return $absolute_path;
                }
                return '';
        }
        $absolute_path = $utils->resolvePath(trim($path, '/\\'));
        return file_exists($absolute_path) ? $absolute_path : '';
    }
}
