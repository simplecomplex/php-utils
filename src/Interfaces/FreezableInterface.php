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
 * Object is freezable; i.e. can become real immutable,
 * not immutable whose modifying methods return clone.
 *
 * We don't like the immutable pattern which says that modifying methods
 * should return clone.
 * We want exception on attempt to modify frozen object, and _deliberate_
 * cloning to return unfrozen clone.
 *
 * @package SimpleComplex\Utils\Interfaces
 */
interface FreezableInterface
{
    /**
     * Make all properties read-only.
     *
     * Implementation may work recursively, freeze()ing all properties
     * that implement this interface.
     *
     * @return void
     */
    public function freeze() /*: void*/;

    /**
     * @return bool
     */
    public function isFrozen() : bool;

    /**
     * Clone will be unfrozen.
     *
     * Implementation may work recursively, unfreeze()ing all properties
     * that implement this interface.
     *
     * @return void
     */
    public function __clone() /*: void*/;
}
