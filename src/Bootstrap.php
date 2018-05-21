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
}
