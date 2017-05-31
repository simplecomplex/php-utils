<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

/**
 * Provides static getInstance() method for reusing instance.
 *
 * @package SimpleComplex\Utils
 */
trait GetInstanceTrait
{
    /**
     * Reference to last instantiated instance of this class.
     *
     * Keeping the instance(s) in list by class name secures that parent/child
     * class' getInstance() returns new/the instance of the class getInstance()
     * is called on (not just any last instance).
     *
     * @var array {
     *      @var Object $className
     * }
     */
    protected static $instanceByClass = [];

    /**
     * Get previously instantiated object or create new.
     *
     * @param mixed ...$constructorParams
     *
     * @return static
     */
    public static function getInstance(...$constructorParams)
    {
        $class = get_called_class();
        if (isset(static::$instanceByClass[$class])) {
            return static::$instanceByClass[$class];
        }

        static::$instanceByClass[$class] = $nstnc = new static(...$constructorParams);

        return $nstnc;
    }
}
