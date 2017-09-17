<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils\Traits;

/**
 * Provides static getInstance() method for reusing instance by class family.
 *
 * Facilitates loosely coupled singleton pattern.
 * First instantiation sets the base for all later requests for an instance.
 *
 * NB: IDE may very well not be able to resolve return type class;
 * then copy to class instead of using this trait.
 *
 * @package SimpleComplex\Utils
 */
trait GetInstanceOfFamilyTrait
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var object
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return object
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        // Unsure about null ternary ?? for class and instance vars.
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }
}
