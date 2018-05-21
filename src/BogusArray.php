<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

/**
 * Deprecated because use may lead to bad coding patterns.
 *
 * ArrayAccess probably provides no benefits at all.
 * An ArrayAccess instance (instance of class implementing, that is)
 * is minimally accessible as an array, but you _cannot_ use any of PHP's
 * vast number of convenient array manipulating functions on the instance.
 *
 * This implementation is primarily aimed at testing ArrayAccess behaviour.
 * Apart from the constructor, it is a direct copy of the Basic usage example
 * at php.net's documentation page.
 *
 * @deprecated
 *
 * @package SimpleComplex\Utils
 */
class BogusArray implements \ArrayAccess/*, \Countable*/ {
    /**
     * @var array
     */
    protected $container = array();

    /**
     * @param array|object|null $members
     *
     * @throws \TypeError
     */
    public function __construct($members = null) {
        if ($members !== null) {
            if (is_array($members) || is_object($members)) {
                // Copy.
                $this->container = $members;
            } elseif (is_object($members)) {
                $this->container = get_object_vars($members);
            } else {
                throw new \TypeError('Arg members type[' . Utils::getType($members) . ' is not array|object|null.');
            }
        }
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset) {
        return isset($this->container[$offset]);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset) {
        unset($this->container[$offset]);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed|null
     */
    public function offsetGet($offset) {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * @return int
     *
    public function count() {
        return count($this->container);
    }*/
}
