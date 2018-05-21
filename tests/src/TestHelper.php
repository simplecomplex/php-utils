<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
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
    const PATH_TESTS = '/simplecomplex/utils/tests/src';

    /**
     * Expected (PHP composer or like-wise) vendor dir.
     *
     * @var string[]
     */
    const PATH_VENDOR = [
        '/vendor',
        '/backend/vendor',
    ];

    /**
     * @var string
     */
    const LOG_LEVEL = 'debug';

    /**
     * @param string|mixed $message
     *      Non-string gets stringified; if possible.
     * @param mixed $subject
     *
     * @return void
     */
    public static function logVariable($message, $subject) /*: void*/
    {
        $msg = '' . $message;
        try {
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
     *
     * @return void
     */
    public static function logTrace($message = '', \Throwable $xcptn = null) /*: void*/
    {
        $msg = '' . $message;
        try {
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
                    $msg . (!$msg ? '' : "\n") . $inspect->trace(
                        $xcptn,
                        [
                            'wrappers' => 1,
                            'trace_limit' => 1,
                        ]
                    )
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
     * @see TestHelper::PATH_VENDOR
     * @see TestHelper::PATH_TESTS
     *
     * @param string $path
     * @param string $relativeTo
     *      'document_root': relative to document root; default and fallback.
     *      'vendor': relative to the vendor PATH_VENDOR.
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
        $document_root = static::documentRoot();
        $absolute_path = Utils::getInstance()->resolvePath($path);
        switch ($relativeTo) {
            case 'tests':
            case 'test':
                foreach (static::PATH_VENDOR as $vendor) {
                    if (file_exists($document_root . $vendor . static::PATH_TESTS . $absolute_path)) {
                        return $document_root . $vendor . static::PATH_TESTS . $absolute_path;
                    }
                }
                return '';
            case 'vendor':
                foreach (static::PATH_VENDOR as $vendor) {
                    if (file_exists($document_root . $vendor . $absolute_path)) {
                        return $document_root . $vendor . $absolute_path;
                    }
                }
                return '';
        }
        return file_exists($document_root . $absolute_path) ? ($document_root . $absolute_path) : '';
    }
}
