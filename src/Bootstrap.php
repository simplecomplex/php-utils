<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use Psr\Container\ContainerInterface;

/**
 * Bootstrapping methods.
 *
 * @package SimpleComplex\Utils
 */
class Bootstrap
{
    /**
     * @var string
     */
    const CLASS_CACHE_BROKER = \SimpleComplex\Cache\CacheBroker::class;

    /**
     * @var string
     */
    const CLASS_CONFIG = \SimpleComplex\Config\Config::class;

    /**
     * @var string
     */
    const CLASS_JSONLOG = \SimpleComplex\JsonLog\JsonLog::class;

    /**
     * @var string
     */
    const CLASS_INSPECT = \SimpleComplex\Inspect\Inspect::class;

    /**
     * @var string
     */
    const CLASS_LOCALE = \SimpleComplex\Locale\Locale::class;

    /**
     * @var string
     */
    const CLASS_VALIDATE = \SimpleComplex\Validate\Validate::class;

    /**
     * Set base dependencies for any kind of application; HTTP services or other.
     *
     * Bootstrapper utility.
     *
     * Prepares:
     * @var \SimpleComplex\Cache\CacheBroker 'cache-broker'
     * @var \SimpleComplex\Config\IniSectionedConfig 'config'
     * @var \SimpleComplex\JsonLog\JsonLog 'logger' (or one passed by argument)
     * @var \SimpleComplex\Inspect\Inspect 'inspect'
     * @var \SimpleComplex\Locale\AbstractLocale 'locale'
     * @var \SimpleComplex\Validate\Validate 'validate'
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
    }
}
