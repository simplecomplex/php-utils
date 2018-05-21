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
 * Immutable upon freeze()'ing.
 *
 * Extend to expose a set list of freezable protected properties.
 *
 * Setting an ad hoc property spells error.
 * @see Freezable::__set()
 *
 * @package SimpleComplex\Utils
 */
abstract class Freezable extends Explorable
{
    /**
     * List of names of protected freezable properties.
     *
     * @var string[]
     */
    protected $explorableIndex = [];

    /**
     * @var bool
     */
    protected $frozen = false;

    /**
     * Builds index of all explorable and freezable properties.
     */
    public function __construct()
    {
        $properties = get_object_vars($this);
        unset(
            $properties['explorableIndex'],
            $properties['frozen']
        );
        $this->explorableIndex = array_keys($properties);
    }

    /**
     * Make all properties read-only.
     *
     * @return void
     */
    public function freeze() /*: void */
    {
        $this->frozen = true;
    }

    /**
     * @return bool
     */
    public function isFrozen() : bool
    {
        return $this->frozen;
    }

    /**
     * Get a protected explorable property.
     *
     * @param string $name
     *
     * @return mixed
     *
     * @throws \OutOfBoundsException
     *      If no such instance property.
     */
    public function __get(string $name)
    {
        if (in_array($name, $this->explorableIndex, true)) {
            return $this->{$name};
        }
        throw new \OutOfBoundsException(get_class($this) . ' instance exposes no property[' . $name . '].');
    }

    /**
     * Setting a property upon freezing spells error.
     *
     * Setting an ad hoc property always spells error,
     * because it isn't in the explorable index.
     *
     * @param string $name
     * @param mixed|null $value
     *
     * @return void
     *
     * @throws \OutOfBoundsException
     *      If no such instance property.
     * @throws \RuntimeException
     *      If that instance property is read-only; frozen.
     */
    public function __set(string $name, $value) /*: void*/
    {
        if (in_array($name, $this->explorableIndex, true)) {
            if (!$this->frozen) {
                $this->{$name} = $value;
                return;
            }
            throw new \RuntimeException(get_class($this) . ' instance property[' . $name . '] is read-only, frozen.');
        }
        throw new \OutOfBoundsException(get_class($this) . ' instance exposes no property[' . $name . '].');
    }
}
