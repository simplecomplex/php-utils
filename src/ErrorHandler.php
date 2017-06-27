<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use Psr\Log\LoggerInterface;

/**
 * Pretty incapable (throwable) error handler.
 *
 * @package SimpleComplex\Utils
 */
class ErrorHandler
{
    /**
     * @var string
     */
    const CLASS_CONFIG = '\\SimpleComplex\\Config\\Config';

    /**
     * @var string
     */
    const CLASS_JSON_LOG = '\\SimpleComplex\\JsonLog\\JsonLog';

    /**
     * @var string
     */
    const CLASS_INSPECT = '\\SimpleComplex\\Inspect\\Inspect';

    /**
     * @code
     * set_error_handler([\SimpleComplex\Utils\ErrorHandler::class, 'handle']);
     * @endcode
     *
     * @param \Throwable $throwable
     *
     * @throws \Throwable
     *      May re-throw arg throwable.
     */
    public static function handle(\Throwable $throwable)
    {
        global $config, $logger, $config, $inspect;
        $cnf = $config;
        $lgr = $logger;
        $nspct = $inspect;

        if ($cnf)




        if (!$lgr || !($lgr instanceof LoggerInterface)) {



            $class_json_log = static::CLASS_JSON_LOG;
            if (class_exists($class_json_log)) {
                /** @var \SimpleComplex\JsonLog\JsonLog $logger */
                $logger = new $class_json_log();
            }
        }
        $trace = null;
        if ($logger) {
            $class_inspect = static::CLASS_INSPECT;
            if (class_exists($class_inspect)) {
                /** @var \SimpleComplex\Inspect\Inspect $inspect */
                $inspect = new $class_inspect();
                $trace = '' . $inspect->trace($throwable);
            }
        }
        if ($logger) {
            $logger->error($trace ? $trace : ('' . $throwable), [
                'code' => $throwable->getCode(),
            ]);
        }

        if (!CliEnvironment::cli()) {
            header('HTTP/1.1 500 Internal Server Error');
            exit;
        } else {
            if ($trace) {
                echo $trace . "\n";
                exit;
            }
        }
        throw $throwable;
    }
}
