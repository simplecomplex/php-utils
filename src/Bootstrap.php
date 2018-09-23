<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use Psr\Container\ContainerInterface;

/**
 * Bootstrapping methods.
 *
 * NB: prepareDependencies() requires packages
 * not listed among PHP composer requirements:
 * - simplecomplex/cache
 * - simplecomplex/config
 * - simplecomplex/json-log; unless prepareDependencies() arg logger
 * - simplecomplex/inspect
 * - simplecomplex/locale
 * - simplecomplex/validate
 * @see Bootstrap::prepareDependencies()
 *
 * @package SimpleComplex\Utils
 */
class Bootstrap
{
    /**
     * @var string
     */
    const CLASS_CACHE_BROKER = '\\SimpleComplex\\Cache\\CacheBroker';

    /**
     * @var string
     */
    const CLASS_CONFIG = '\\SimpleComplex\\Config\\Config';

    /**
     * @var string
     */
    const CLASS_JSONLOG = '\\SimpleComplex\\JsonLog\\JsonLog';

    /**
     * @var string
     */
    const CLASS_INSPECT = '\\SimpleComplex\\Inspect\\Inspect';

    /**
     * @var string
     */
    const CLASS_LOCALE = '\\SimpleComplex\\Locale\\Locale';

    /**
     * @var string
     */
    const CLASS_VALIDATE = '\\SimpleComplex\\Validate\\Validate';

    /**
     * @var string
     */
    const CLASS_DATABASE_BROKER = '\\SimpleComplex\\Database\\DatabaseBroker';

    /**
     * @var bool
     */
    protected static $booted = false;

    /**
     * Set base dependencies for any kind of application; using all/most
     * SimpleComplex packages.
     *
     * Prepares container buckets:
     * - \SimpleComplex\Cache\CacheBroker 'cache-broker'
     * - \SimpleComplex\Config\IniSectionedConfig 'config'
     * - \SimpleComplex\JsonLog\JsonLog 'logger' (or one passed by argument)
     * - \SimpleComplex\Inspect\Inspect 'inspect'
     * - \SimpleComplex\Locale\AbstractLocale 'locale'
     * - \SimpleComplex\Validate\Validate 'validate'
     * - \SimpleComplex\Database\DatabaseBroker 'database-broker' (if exists)
     *
     * Only prepares dependencies at first call, later calls are ignored.
     *
     * @param ContainerInterface|null $container
     *      Like \Slim\Container; default is Dependency instance.
     * @param Callable|null $logger
     *      Custom logger; default is JsonLog.
     */
    public static function prepareDependencies(
        /*?ContainerInterface*/ $container = null,
        /*?Callable*/ $logger = null
    ) {
        if (static::$booted) {
            return;
        }
        static::$booted = true;

        if ($container) {
            Dependency::injectExternalContainer($container);
        }
        $container = Dependency::container();

        $cache_broker = static::CLASS_CACHE_BROKER;
        $config = static::CLASS_CONFIG;
        $json_log = static::CLASS_JSONLOG;
        $inspect = static::CLASS_INSPECT;
        $locale = static::CLASS_LOCALE;
        $validate = static::CLASS_VALIDATE;
        $database_broker = static::CLASS_DATABASE_BROKER;

        Dependency::genericSetMultiple(
            [
                'cache-broker' => function() use ($cache_broker) {
                    return new $cache_broker();
                },
                'config' => function() use ($config) {
                    /**
                     * @var \SimpleComplex\Config\IniSectionedConfig
                     */
                    return new $config('global');
                },
                'logger' => function() use ($container, $logger, $json_log) {
                    return $logger ? $logger() :
                        new $json_log($container->get('config'));
                },
                'inspect' => function() use ($container, $inspect) {
                    return new $inspect($container->get('config'));
                },
                'locale' => function() use ($container, $locale) {
                    $make = $locale . '::create';
                    return $make($container->get('config'));
                },
                'validate' => function() use ($validate) {
                    return new $validate();
                },
            ]
        );
        if (class_exists($database_broker)) {
            Dependency::genericSet('database-broker', function() use ($database_broker) {
                return new $database_broker();
            });
        }
    }

    /**
     * Set base dependencies for any kind of application; using all/most
     * SimpleComplex packages if they exist.
     *
     * Prepares container buckets:
     * - \SimpleComplex\Cache\CacheBroker 'cache-broker'
     * - \SimpleComplex\Config\IniSectionedConfig 'config'
     * - \SimpleComplex\JsonLog\JsonLog 'logger' (or one passed by argument)
     * - \SimpleComplex\Inspect\Inspect 'inspect'
     * - \SimpleComplex\Locale\AbstractLocale 'locale'
     * - \SimpleComplex\Validate\Validate 'validate'
     * - \SimpleComplex\Database\DatabaseBroker 'database-broker'
     *
     * Only prepares dependencies at first call, later calls are ignored.
     *
     * @param ContainerInterface|null $container
     *      Like \Slim\Container; default is Dependency instance.
     * @param Callable|null $logger
     *      Custom logger; default is JsonLog.
     */
    public static function prepareDependenciesIfExist(
        /*?ContainerInterface*/ $container = null,
        /*?Callable*/ $logger = null
    ) {
        if (static::$booted) {
            return;
        }
        static::$booted = true;

        if ($container) {
            Dependency::injectExternalContainer($container);
        }
        $container = Dependency::container();

        $cache_broker = static::CLASS_CACHE_BROKER;
        $config = static::CLASS_CONFIG;
        $json_log = static::CLASS_JSONLOG;
        $inspect = static::CLASS_INSPECT;
        $locale = static::CLASS_LOCALE;
        $validate = static::CLASS_VALIDATE;
        $database_broker = static::CLASS_DATABASE_BROKER;

        if (class_exists($cache_broker)) {
            Dependency::genericSet('cache-broker', function() use ($cache_broker) {
                return new $cache_broker();
            });
        }
        if (class_exists($config)) {
            Dependency::genericSet('config', function() use ($config) {
                /**
                 * @var \SimpleComplex\Config\IniSectionedConfig
                 */
                return new $config('global');
            });
        }

        if ($logger) {
            Dependency::genericSet('logger', function() use ($container, $logger) {
                return $logger;
            });
        } elseif (class_exists($json_log)) {
            Dependency::genericSet('logger', function() use ($container, $json_log) {
                return new $json_log($container->get('config'));
            });
        }

        if (class_exists($inspect)) {
            Dependency::genericSet('inspect', function() use ($container, $inspect) {
                return new $inspect($container->get('config'));
            });
        }
        if (class_exists('locale')) {
            Dependency::genericSet('locale', function() use ($container, $locale) {
                $make = $locale . '::create';
                return $make($container->get('config'));
            });
        }
        if (class_exists($validate)) {
            Dependency::genericSet('validate', function() use ($validate) {
                return new $validate();
            });
        }
        if (class_exists($database_broker)) {
            Dependency::genericSet('database-broker', function() use ($database_broker) {
                return new $database_broker();
            });
        }
    }

    /**
     * Attempts to log trace, or just exception details.
     *
     * Behaviour by arg $context:
     * - http: sends 500 Internal Server Error and exits
     * - cli: prints error message
     * - empty or other: no behaviour apart from logging
     *
     * @param ContainerInterface $container
     * @param string $context
     *      Values: http|cli, or empty.
     */
    public static function setExceptionHandler(ContainerInterface $container, string $context = '') /*: void*/
    {
        set_exception_handler(function(\Throwable $throwable) use ($container, $context) {
            $trace = $msg = null;
            try {
                $msg = rtrim(
                        get_class($throwable) . '(' . $throwable->getCode() . ')@' . $throwable->getFile() . ':'
                        . $throwable->getLine() . ': ' . addcslashes($throwable->getMessage(), "\0..\37"),
                        '.'
                    ) . '.';
                if ($container->has('inspect')) {
                    $trace = '' . $container->get('inspect')->trace($throwable);
                }
                if ($container->has('logger')) {
                    $container->get('logger')->error($trace ?? $throwable);
                }
            } catch (\Throwable $xcptn) {
                // Log original exception.
                if ($msg) {
                    error_log($msg);
                }
                // Log this exception handler's own exception.
                error_log(
                    get_class($xcptn) . '(' . $xcptn->getCode() . ')@' . $xcptn->getFile() . ':'
                    . $xcptn->getLine() . ': ' . addcslashes($xcptn->getMessage(), "\0..\37")
                );
            }
            switch ($context) {
                case 'http':
                    header('HTTP/1.1 500 Internal Server Error');
                    exit;
                case 'cli':
                    echo "\033[01;31m[error]\033[0m " . ($trace ? ($msg . "\n- Check log.") : $throwable) . "\n";
                    break;
            }
        });
    }

    /**
     * Attempts to log trace, or just error details.
     *
     * Behaviour by arg $context + error level:
     * - http + notice: pass-thru (ignore, but log)
     * - http + other: sends 500 Internal Server Error and exits
     * - cli + any: prints error message
     * - empty or other: no behaviour apart from logging
     *
     * @param ContainerInterface $container
     * @param string $context
     *      Values: http|cli, or empty.
     */
    public static function setErrorHandler(ContainerInterface $container, string $context = '') /*: void*/
    {
        set_error_handler(function($severity, $message, $file, $line) use ($container, $context) {
            if (!(error_reporting() & $severity)) {
                // Pass-thru.
                return false;
            }
            try {
                $name = 'Unknown PHP error type';
                $level = 'alert';
                $types = [
                    E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
                    E_PARSE => 'E_PARSE',
                ];
                if (isset($types[$severity])) {
                    $name = $types[$severity];
                    $level = 'error';
                }
                else {
                    $types = [
                        E_WARNING => 'E_WARNING',
                        E_CORE_WARNING => 'E_CORE_WARNING',
                        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
                        E_USER_WARNING => 'E_USER_WARNING',
                    ];
                    if (isset($types[$severity])) {
                        $name = $types[$severity];
                        $level = 'warning';
                    }
                    else {
                        $types = [
                            E_NOTICE => 'E_NOTICE',
                            E_USER_NOTICE => 'E_USER_NOTICE',
                            E_STRICT => 'E_STRICT',
                            E_DEPRECATED => 'E_DEPRECATED',
                            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
                        ];
                        if (isset($types[$severity])) {
                            $name = $types[$severity];
                            $level = 'notice';
                        }
                    }
                }
                $msg = rtrim(
                        $name . '(' . $severity . ')@' . $file . ':' . $line . ': '
                        . addcslashes($message, "\0..\37"),
                        '.'
                    ) . '.';
                $trace = '';
                if ($container->has('logger')) {
                    if ($container->has('inspect')) {
                        $trace = "\n" . $container->get('inspect')->trace(null, ['wrappers' => 1]);
                    }
                    $container->get('logger')->log($level, $msg . $trace);
                } else {
                    error_log($msg);
                }
                switch ($context) {
                    case 'http':
                        if ($level == 'notice') {
                            return true;
                        }
                        header('HTTP/1.1 500 Internal Server Error');
                        exit;
                    case 'cli':
                        switch ($level) {
                            case 'warning':
                                echo "\033[01;33m[warning]\033[0m ";
                                break;
                            case 'notice':
                                echo "\033[01;36m[notice]\033[0m ";
                                break;
                            default:
                                echo "\033[01;31m[error]\033[0m ";
                        }
                        echo $msg  . (!$trace ? '' : "\n- Check log.") . "\n";
                        break;
                }
                return true;
            } catch (\Throwable $ignore) {
            }
            // Pass-thru.
            return false;
        });
    }
}
