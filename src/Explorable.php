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
 * Extend to expose a set list of protected/public properties for counting
 * and foreach'ing.
 *
 * @package SimpleComplex\Utils
 */
abstract class Explorable implements \Countable, \Iterator /*~ Traversable*/, \JsonSerializable
{
    /**
     * List of names of properties (private, protected or public) which should
     * be exposed as accessibles in count()'ing and foreach'ing.
     *
     * Private/protected properties may also be readable via 'magic' __get(),
     * if the __get() uses explorableIndex.
     *
     * @var string[]
     */
    protected $explorableIndex = [];

    /**
     * Generates index of explorable properties based on the class' declared
     * instance vars except $explorableIndex and arg $nonExplorables.
     *
     * For extendings class' constructor (typically).
     *
     * @param string[] $nonExplorables
     *      Optional list of more non-explorable properties.
     */
    protected function explorablesAutoDefine(array $nonExplorables = [])
    {
        // get_object_vars() also includes unitialized (no default) vars.
        $property_names = array_keys(get_object_vars($this));
        $this->explorableIndex = array_diff($property_names, ['explorableIndex'], $nonExplorables);
    }

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

    /**
     * Dumps publicly readable properties to standard object.
     *
     * @param bool $recursive
     *
     * @return \stdClass
     */
    public function toObject(bool $recursive = false) : \stdClass
    {
        $o = new \stdClass();
        if (!$recursive) {
            foreach ($this->explorableIndex as $property) {
                $o->{$property} = $this->{$property};
            }
        } else {
            foreach ($this->explorableIndex as $property) {
                $value = $this->{$property};
                $o->{$property} = !($value instanceof Explorable) ? $value : $value->toObject(true);
            }
        }
        return $o;
    }

    /**
     * Dumps publicly readable properties to array.
     *
     * @param bool $recursive
     *
     * @return array
     */
    public function toArray(bool $recursive = false) : array
    {
        $a = [];
        if (!$recursive) {
            foreach ($this->explorableIndex as $property) {
                $a[$property] = $this->{$property};
            }
        } else {
            foreach ($this->explorableIndex as $property) {
                $value = $this->{$property};
                $a[$property] = !($value instanceof Explorable) ? $value : $value->toArray(true);
            }
        }
        return $a;
    }

    /**
     * JSON serializes to object listing all publicly readable properties.
     *
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->toObject(true);
    }

    /**
     * Do implement magic getter and setter if any exposed property is protected.
     *
     * @see \SimpleComplex\Utils\Traits\ExplorableGetSetTrait
     */

    /**
     * Get a read-only property.
     *
     * @param string $name
     *
     * @return mixed
     *
     * @throws \OutOfBoundsException
     *      If no such instance property.
     *
    public function __get(string $name)
    {
        if (in_array($name, $this->explorableIndex, true)) {
            return $this->{$name};
        }
        throw new \OutOfBoundsException(get_class($this) . ' instance exposes no property[' . $name . '].');
    }*/

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
    public function __set(string $name, $value)
    {
        if (in_array($name, $this->explorableIndex, true)) {
            throw new \RuntimeException(get_class($this) . ' instance property[' . $name . '] is read-only.');
        }
        throw new \OutOfBoundsException(get_class($this) . ' instance exposes no property[' . $name . '].');
    }*/
}
