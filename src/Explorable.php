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
 * Extend to expose a set list of protected/public members for counting
 * and foreach'ing.
 *
 * @package SimpleComplex\Utils
 */
abstract class Explorable implements \Countable, \Iterator
{
    /**
     * List of names of members (private, protected or public) which should be
     * exposed as accessibles in count()'ing and foreach'ing.
     *
     * Private/protected members may also be readable via 'magic' __get(),
     * if the __get() uses explorableIndex.
     *
     * @var array
     */
    protected $explorableIndex = [];


    /**
     * @param string|int $name
     *
     * @return bool
     */
    public function __isset($name) : bool
    {
        return in_array($name, $this->explorableIndex, true) && isset($this->{$name});
    }

    // Countable.---------------------------------------------------------------

    /**
     * @see \Countable::count()
     *
     * @return int
     */
    public function count()
    {
        return count($this->explorableIndex);
    }


    // Foreachable (Iterator).--------------------------------------------------

    /**
     * @see \Iterator::rewind()
     *
     * @return void
     */
    public function rewind() /*: void*/
    {
        reset($this->explorableIndex);
    }

    /**
     * @see \Iterator::key()
     *
     * @return string
     */
    public function key() : string
    {
        return current($this->explorableIndex);
    }

    /**
     * @see \Iterator::current()
     *
     * @return mixed
     */
    public function current()
    {
        return $this->{current($this->explorableIndex)};
    }

    /**
     * @see \Iterator::next()
     *
     * @return void
     */
    public function next() /*: void*/
    {
        next($this->explorableIndex);
    }

    /**
     * @see \Iterator::valid()
     *
     * @return bool
     */
    public function valid() : bool
    {
        // The null check is cardinal; without it foreach runs out of bounds.
        $key = key($this->explorableIndex);
        return $key !== null && $key < count($this->explorableIndex);
    }


    // Do implement magic getter and setter if any exposed property is protected.

    /**
     * @param $name
     *
     * @return mixed|null
     *
     * @throws \OutOfBoundsException
     *      If no such instance property exposed.
     *
     * abstract function __get($name);
     */

    /**
     * @param string $name
     * @param mixed|null $value
     *
     * @return void
     *
     * @throws \OutOfBoundsException
     *      If no such instance property.
     * @throws \RuntimeException
     *      If that instance property is read-only.
     *
     * abstract function __set($name, $value);
     */
}
