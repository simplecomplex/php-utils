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
 * PathFileList which uses filenames as keys and require that filenames
 * are unique across directories.
 *
 * Array-like object, associative keys.
 *
 * @package SimpleComplex\Utils
 */
class PathFileListUnique extends PathFileList
{
    /**
     * Uses filenames as keys and require that filenames are unique
     * across directories.
     *
     * @var bool
     */
    const FILENAMES_UNIQUE = true;
}
