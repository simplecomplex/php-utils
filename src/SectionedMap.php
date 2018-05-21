<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use SimpleComplex\Utils\Interfaces\SectionedMapInterface;

/**
 * Exposes multi-dimensional hashmap items as children of sections,
 * having composite keys as 'section' + 'key'.
 *
 * @see SectionedMapInterface
 *
 * @package SimpleComplex\Utils
 */
class SectionedMap implements SectionedMapInterface
{
    /**
     * @var array
     */
    protected $map = [];

    /**
     * Key is section, value is whether the section is ArrayAccess instance.
     * @var array
     */
    protected $arrayAccess = [];

    /**
     * @param string $section
     * @param object|array $container
     *
     * @return $this|SectionedMap
     *
     * @throws \InvalidArgumentException
     *      Arg $section empty.
     * @throws \TypeError
     *      Arg $container not object|array.
     */
    public function setSection(string $section, $container) : SectionedMap
    {
        if ($section === '') {
            throw new \InvalidArgumentException('Arg \'section\' cannot be empty.');
        }
        if (is_array($container)) {
            $this->arrayAccess[$section] = true;
        } elseif (is_object($container)) {
            $this->arrayAccess[$section] = $container instanceof \ArrayAccess;
        } else {
            throw new \TypeError('Arg \'container\' type[' . gettype($container) . '] is not object|array.');
        }
        $this->map[$section] = $container;

        return $this;
    }

    /**
     * @param string $section
     *
     * @return $this|SectionedMap
     *
     * @throws \InvalidArgumentException
     *      Arg $section empty.
     */
    public function deleteSection(string $section) : SectionedMap
    {
        if ($section === '') {
            throw new \InvalidArgumentException('Arg \'section\' cannot be empty.');
        }
        unset($this->map[$section], $this->arrayAccess[$section]);

        return $this;
    }


    // SectionedMapInterface.---------------------------------------------------

    /**
     * Supports the wildcard * for arg key; returning (copy) of a section.
     *
     * @param string $section
     * @param string $key
     *      Wildcard *: (arr) the whole section or empty; ignores arg default.
     * @param mixed $default
     *
     * @return mixed|null
     *
     * @throws \InvalidArgumentException
     *      Arg $section empty.
     */
    public function get(string $section, string $key, $default = null)
    {
        if ($section === '') {
            throw new \InvalidArgumentException('Arg \'section\' cannot be empty.');
        }
        if (!isset($this->map[$section])) {
            return $default;
        }
        if ($key === '*') {
            return $this->map[$section];
        }
        return $this->arrayAccess[$section] ? ($this->map[$section][$key] ?? $default) :
            ($this->map[$section]->{$key} ?? $default);
    }

    /**
     * @param string $section
     * @param string $key
     * @param mixed $value
     *
     * @return bool
     *      Always true.
     *
     * @throws \InvalidArgumentException
     *      Arg $section empty.
     *      Arg $key wildcard '*'.
     */
    public function set(string $section, string $key, $value) : bool
    {
        if ($section === '') {
            throw new \InvalidArgumentException('Arg \'section\' cannot be empty.');
        }
        if ($key === '*') {
            throw new \InvalidArgumentException('Arg \'key\' cannot be wildcard \'*\'.');
        }
        if (!isset($this->map[$section])) {
            $this->arrayAccess[$section] = true;
            $this->map[$section] = [
                $key => $value
            ];
        }
        elseif ($this->arrayAccess[$section]) {
            $this->map[$section][$key] = $value;
        }
        else {
            $this->map[$section]->{$key} = $value;
        }
        return true;
    }

    /**
     * @param string $section
     * @param string $key
     *
     * @return bool
     *     Always true.
     *
     * @throws \InvalidArgumentException
     *      Arg $section empty.
     */
    public function delete(string $section, string $key) : bool
    {
        if ($section === '') {
            throw new \InvalidArgumentException('Arg \'section\' cannot be empty.');
        }
        if (isset($this->map[$section])) {
            if ($this->arrayAccess[$section]) {
                unset($this->map[$section][$key]);
            }
            else {
                unset($this->map[$section]->{$key});
            }
        }
        return true;
    }

    /**
     * Supports the wildcard * for arg key; checking if a section exists.
     *
     * @param string $section
     *      Wildcard *: (arr) the whole section.
     * @param string $key
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     *      Arg $section empty.
     */
    public function has(string $section, string $key) : bool
    {
        if ($section === '') {
            throw new \InvalidArgumentException('Arg \'section\' cannot be empty.');
        }
        if ($key == '*') {
            return isset($this->map[$section]);
        }
        if (isset($this->map[$section])) {
            if ($this->arrayAccess[$section]) {
                return isset($this->map[$section][$key]);
            }
            else {
                return isset($this->map[$section]->{$key});
            }
        }
        return false;
    }

    /**
     * Does not nothing, but required by SectionedMapInterface.
     *
     * @param string $section
     *
     * @return null
     *      Method does nothing.
     */
    public function remember(string $section)
    {
        return null;
    }

    /**
     * Does not nothing, but required by SectionedMapInterface.
     *
     * @param string $section
     *
     * @return void
     */
    public function forget(string $section) /*: void*/
    {
    }
}