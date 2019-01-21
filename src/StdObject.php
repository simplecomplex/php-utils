<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

/**
 * Standard object class which supports setting properties during initialization
 * and is stringable.
 *
 * @package SimpleComplex\Utils
 */
class StdObject
{
    /**
     * @param array|null $properties
     */
    public function __construct(?array $properties)
    {
        if ($properties) {
            foreach ($properties as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Returns class name.
     *
     * @return string
     */
    public function __toString() : string
    {
        return get_class($this);
    }
}
