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
use SimpleComplex\Utils\Exception\ContainerInvalidArgumentException;
use SimpleComplex\Utils\Exception\ContainerNotFoundException;
use SimpleComplex\Utils\Exception\ContainerRuntimeException;

/**
 * Interface to an external or internal dependency injection container.
 *
 * Can only refer a single container.
 *
 * External container must have a set() method or be \ArrayAccess.
 *
 * The internal container is very a simple implementation:
 *
 *
 * @package SimpleComplex\Utils
 */
class Dependency implements ContainerInterface
{
    /**
     * @var ContainerInterface
     */
    protected static $externalContainer;

    /**
     * @var Dependency
     */
    protected static $internalContainer;

    /**
     * @var string
     */
    protected static $setMethod = 'set';

    /**
     * Get the external or internal dependency injection container.
     *
     * Creates internal container if no container exists yet.
     *
     * @return ContainerInterface
     */
    public static function container() : ContainerInterface
    {
        return static::$externalContainer ?? static::$internalContainer ??
            (static::$internalContainer = new static());
    }

    /**
     * Inject external dependency injection container, and tell that that's
     * the container that'll be used from now on.
     *
     * @param ContainerInterface $container
     * @param string $setMethod
     *      Default: empty; auto, uses set() or \ArrayAccess::offsetSet().
     *
     * @return void
     *
     * @throws ContainerLogicException
     *      \LogicException + \Psr\Container\ContainerExceptionInterface
     *      If there already is an external or internal container.
     * @throws ContainerInvalidArgumentException
     *      Empty arg setMethod and the container neither has a set() method
     *      nor is \ArrayAccess.
     *      Non-empty arg setMethod and container has no such method.
     */
    public static function injectExternalContainer(ContainerInterface $container, string $setMethod = '')
    {
        if (static::$externalContainer) {
            throw new ContainerLogicException(
                'Can\'t inject external container when external container already exists.'
            );
        }
        if (static::$internalContainer) {
            throw new ContainerLogicException(
                'Can\'t inject external container when internal container already exists.'
            );
        }
        if ($setMethod) {
            if (!method_exists($container, $setMethod)) {
                throw new ContainerInvalidArgumentException(
                    'External container type[' . get_class($container) . '] has no method[' . $setMethod . '].');
            }
            static::$setMethod = $setMethod;
        } elseif ($container instanceof \ArrayAccess) {
            static::$setMethod = 'offsetSet';
        } elseif (!method_exists($container, 'set')) {
            throw new ContainerInvalidArgumentException(
                'Empty arg setMethod and external container type['
                . get_class($container) . '] has no set() method and is not ArrayAccess.'
            );
        }
    }

    /**
     * Set item in the container, disregarding the name of the setter method.
     *
     * @param string $id
     * @param mixed $value
     *
     * @return void
     *
     * @throws ContainerRuntimeException
     *      Propagated.
     */
    public static function genericSet($id, $value)
    {
        static::container()->{static::$setMethod}($id, $value);
    }

    /**
     * Set multiple items in the container, disregarding the name of the setter
     * method.
     *
     * @param array $values
     *
     * @return void
     *
     * @throws ContainerRuntimeException
     *      Propagated.
     */
    public static function genericSetMultiple(array $values)
    {
        $container = static::container();
        foreach ($values as $id => $value) {
            $container->{static::$setMethod}($id, $value);
        }
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
     * @return void
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
        $this->keys[$id] = true;
    }
}
