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
use SimpleComplex\Utils\Exception\ContainerLogicException;
use SimpleComplex\Utils\Exception\ContainerNotFoundException;
use SimpleComplex\Utils\Exception\ContainerRuntimeException;

/**
 * Interface to an external or internal dependency injection container.
 *
 * Can only refer a single container.
 *
 * External container must have a set() method or be \ArrayAccess.
 *
 * The internal container is very simple implementation.
 *
 * @package SimpleComplex\Utils
 */
class Dependency implements ContainerInterface
{
    /**
     * @var ContainerInterface|null
     */
    protected static $externalContainer;

    /**
     * @var Dependency|null
     */
    protected static $internalContainer;

    /**
     * Get the external or internal dependency injection container.
     *
     * Creates internal container if no container exists yet.
     *
     * @return ContainerInterface|Dependency|\Pimple\Container
     *      ContainerInterface; \Pimple\Container is for IDE.
     */
    public static function container() : ContainerInterface
    {
        return static::$externalContainer ?? static::$internalContainer ??
            (static::$internalContainer = new static());
    }

    /**
     * Inject external dependency injection container, an tell that that's
     * the container that'll be used from now on.
     *
     * @param ContainerInterface|\Pimple\Container $container
     *      ContainerInterface; \Pimple\Container is for IDE.
     *
     * @throws ContainerLogicException
     *      \LogicException + \Psr\Container\ContainerExceptionInterface
     *      If there already is an external or internal container.
     */
    public static function injectExternalContainer(ContainerInterface $container)
    {
        if (static::$externalContainer) {
            throw new ContainerLogicException('Can\'t inject external container when external container already exists.');
        }
        if (static::$internalContainer) {
            throw new ContainerLogicException('Can\'t inject external container when internal container already exists.');
        }
        static::$externalContainer = $container;
    }

    /**
     * Set item on the container, disregarding whether the container's setter
     * is an \ArrayAccess setter (Pimple/Slim container) or an explicit set()
     * method (like PHP-DI and this internal container).
     *
     * @param string $id
     * @param mixed $value
     *
     * @throws ContainerLogicException
     *      \LogicException + \Psr\Container\ContainerExceptionInterface
     *      If the container neither has a set() method nor is \ArrayAccess.
     */
    public static function setItem($id, $value)
    {
        static $has_set;
        $container = static::container();
        if (static::$internalContainer || $has_set) {
            $container->set($id, $value);
        } elseif ($container instanceof \ArrayAccess) {
            $container->offsetSet($id, $value);
        } else {
            $has_set = method_exists($container, 'set');
            if ($has_set) {
                $container->set($id, $value);
            }
        }
        throw new ContainerLogicException('External container type['
            . get_class($container) . '] is not ArrayAccess and has no set() method.');
    }

    /**
     * Not to be instantiated from the outside.
     */
    protected function __construct()
    {
    }

    /**
     * @var array
     */
    protected $keys = [];

    /**
     * @var array
     */
    protected $values = [];

    /**
     * @var array
     */
    protected $callables = [];

    /**
     * @var array
     */
    protected $requested = [];

    /**
     * @param string $id
     *
     * @return mixed
     *
     * @throws ContainerNotFoundException
     *      \RuntimeException + \Psr\Container\NotFoundExceptionInterface
     *      If the internal container has no item by arg id.
     */
    public function get($id)
    {
        if (isset($this->keys[$id])) {
            $this->requested[$id] = true;
            if (isset($this->callables[$id])) {
                $this->values[$id] = $this->callables[$id]();
                unset($this->callables[$id]);
            }
            return $this->values[$id];
        }
        throw new ContainerNotFoundException('Container identifier[' . $id . '] not found.');
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function has($id) : bool
    {
        return isset($this->keys[$id]);
    }

    /**
     * Convenience method for set().
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * Convenience method for has().
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return $this->has($name);
    }

    /**
     * Beware that arg value as string will be considered a callable
     * if that string value matches a declared function.
     *
     * @param string $id
     * @param callable|mixed $value
     *      If callable, if will be called on first request.
     *
     * @throws ContainerRuntimeException
     *      \RuntimeException + \Psr\Container\ContainerExceptionInterface
     *      If item by that id has been set previously and requested previously.
     */
    public function set($id, $value)
    {
        if (isset($this->requested[$id])) {
            throw new ContainerRuntimeException(
                'Container identifier[' . $id . '] is already set and has been requested.'
            );
        }
        if (is_callable($value)) {
            $this->callables[$id] = $value;
        } else {
            $this->values[$id] = $value;
        }
    }
}
