<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use SimpleComplex\Utils\Interfaces\FreezableInterface;

/**
 * Immutable upon freeze()'ing.
 *
 * Extend to expose a set list of freezable protected properties.
 *
 * Abstract because an implementation must declare it's protected properties.
 *
 * Setting an ad hoc property spells error.
 * @see ExplorableFreezable::__set()
 *
 * @package SimpleComplex\Utils
 */
abstract class ExplorableFreezable extends Explorable implements FreezableInterface
{
    /**
     * @var bool
     */
    protected $frozen = false;

    /**
     * Auto-generates index of explorable properties+freezable properties.
     *
     * @param string[] $nonExplorables
     *      Optional list of more non-explorable properties.
     */
    protected function explorablesAutoDefine(array $nonExplorables = [])
    {
        $nonExplorables[] = 'frozen';
        parent::explorablesAutoDefine($nonExplorables);
    }

    /**
     * Clone will be unfrozen, recursively.
     *
     * @return void
     */
    public function __clone() /*: void*/
    {
        $this->frozen = false;
        foreach ($this->explorableIndex as $name) {
            if ($this->{$name} instanceof FreezableInterface) {
                $this->{$name} = clone $this->{$name};
            }
        }
    }

    /**
     * Make all properties read-only, unless already frozen.
     *
     * Recursive, freeze()s all properties that are FreezableInterface.
     *
     * Chainable.
     *
     * @return $this|ExplorableFreezable
     */
    public function freeze() /*: object*/
    {
        if (!$this->frozen) {
            $this->frozen = true;
            foreach ($this->explorableIndex as $name) {
                if ($this->{$name} instanceof FreezableInterface) {
                    $this->{$name}->freeze();
                }
            }
        }
        return $this;
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
