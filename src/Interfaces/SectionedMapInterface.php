<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils\Interfaces;

/**
 * Exposes multi-dimensional hashmap items as children of sections,
 * having composite keys as 'section' + 'key'.
 *
 * Usable in itself, as general purpose multi-dimensional container.
 * But also needed as a means of eliminating other packages
 * depending unnecessarily on the Config package.
 *
 * @see SectionedMap
 * @see \SimpleComplex\Config\Interfaces\SectionedConfigInterface
 *
 * @package SimpleComplex\Utils
 */
interface SectionedMapInterface
{
    /**
     * An implementation may support wildcard * for the get() method's key
     * argument, and thus return the whole section as an array|object.
     *
     * @param string $section
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function get(string $section, string $key, $default = null);

    /**
     * It is allowed that this method does nothing.
     *
     * @param string $section
     * @param string $key
     * @param mixed $value
     *
     * @return bool
     */
    public function set(string $section, string $key, $value) : bool;

    /**
     * It is allowed that this method does nothing.
     *
     * @param string $section
     * @param string $key
     *
     * @return bool
     */
    public function delete(string $section, string $key) : bool;

    /**
     * An implementation may support wildcard * for the has() method's key
     * argument, and thus check if the section (as an array|object) exists.
     *
     * @param string $section
     * @param string $key
     *
     * @return bool
     */
    public function has(string $section, string $key) : bool;

    /**
     * Load section into memory, to make subsequent getter calls read
     * from memory instead of some physical store.
     *
     * A subsequent call to a setting or deleting method using arg section
     * _must_ (for integrity reasons) immediately clear the section from memory.
     *
     * An implementation which internally can't/won't arrange items
     * multi-dimensionally (and thus cannot load a section into memory)
     * must return null.
     *
     * It is allowed that this method does nothing.
     *
     * @param string $section
     *
     * @return bool|null
     *      False: section doesn't exist.
     *      Null: Not applicable.
     */
    public function remember(string $section) /*: ?bool*/;

    /**
     * Flush section from memory, to relieve memory usage; and make subsequent
     * getter calls read from physical store.
     *
     * Implementations which cannot do this, must ignore call.
     *
     * It is allowed that this method does nothing.
     *
     * @param string $section
     *
     * @return void
     */
    public function forget(string $section) /*: void*/;
}
