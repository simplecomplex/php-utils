<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use SimpleComplex\Utils\Exception\InvalidArgumentException;

// @todo: check if out-package libs have to include (use) Unicode _and_ UnicodeNative to use instance of UnicodeNative.

/**
 * Unicode string methods based on PHP native Unicode support (mbstring + intl).
 *
 * Beware that calls to methods of this class will err fatally if the PHP
 * extensions mbstring and intl aren't available.
 *
 * @see Unicode::nativeSupport()
 *
 * @code
 * // Recommended way to instantiate.
 * use \SimpleComplex\Utils\Unicode;
 * use \SimpleComplex\Utils\UnicodeNative;
 *
 * $unicode = Unicode::nativeSupport() > 2 ?
 *     UnicodeNative::getInstance() : Unicode::getInstance();
 * @endcode
 *
 * @package SimpleComplex\Utils
 */
class UnicodeNative extends Unicode
{
    /**
     * @see Unicode::strlen();
     *
     * @inheritdoc
     */
    public function strlen($var) : int
    {
        return mb_strlen('' . $var);
    }

    /**
     * @see Unicode::substr();
     *
     * @inheritdoc
     */
    public function substr($var, int $start, /*?int*/ $length = null) : string
    {
        if ($start < 0) {
            $msg = 'start is not non-negative integer.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'start' => $start,
                        'length' => $length,
                    ],
                ]);
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }
        if ($length !== null && (!is_int($length) || $length < 0)) {
            $msg = 'length is not non-negative integer or null.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'start' => $start,
                        'length' => $length,
                    ],
                ]);
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }
        $v = '' . $var;
        if (!$length || $v === '') {
            return '';
        }
        return !$length ? mb_substr($v, $start) : mb_substr($v, $start, $length);
    }

    /**
     * @see Unicode::strpos();
     *
     * @inheritdoc
     */
    public function strpos($haystack, $needle)
    {
        $hstck = '' . $haystack;
        $ndl = '' . $needle;
        if ($hstck === '' || $ndl === '') {
            return false;
        }
        return mb_strpos($hstck, $ndl);
    }
}
