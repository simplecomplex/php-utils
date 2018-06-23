<?php

namespace SimpleComplex\Utils\Exception;

/**
 * Non-unique array|object key - free to use within other packages.
 *
 * @see \SimpleComplex\Utils\Utils::arrayMergeUniqueRecursive()
 *
 * @package SimpleComplex\Utils
 */
class KeyNonUniqueException extends \RuntimeException
{
}
