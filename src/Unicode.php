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
 * Unicode string methods.
 *
 * @package SimpleComplex\Utils
 */
class Unicode
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var Unicode
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return Unicode
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        // Unsure about null ternary ?? for class and instance vars.
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }

    /**
     * @see Unicode::getInstance()
     */
    public function __construct()
    {
        // Init class-wide.
        static::nativeSupport();
    }

    /**
     * @var bool[] {
     *      @var bool $mbstring
     *      @var bool $intl
     * }
     */
    protected static $nativeSupport = [];

    /**
     * @param string $ext
     *      Values: mbstring|intl. Default: empty.
     *
     * @return bool|bool[]
     *      Array: on empty arg ext.
     */
    public static function nativeSupport(string $ext = '')
    {
        $support = static::$nativeSupport;
        if (!$support) {
            $support['mbstring'] = function_exists('mb_strlen');
            $support['intl'] = function_exists('intl_error_name');
            static::$nativeSupport = $support;
        }
        if ($ext) {
            return !empty($support[$ext]);
        }
        return $support;
    }

    /**
     * Multibyte-safe string length.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return int
     */
    public function strlen($var) : int
    {
        $v = '' . $var;
        if ($v === '') {
            return 0;
        }
        if (static::$nativeSupport['mbstring']) {
            return mb_strlen($v);
        }

        $n = 0;
        $le = strlen($v);
        $leading = false;
        for ($i = 0; $i < $le; $i++) {
            // ASCII.
            if (($ord = ord($v{$i})) < 128) {
                ++$n;
                $leading = false;
            }
            // Continuation char.
            elseif ($ord < 192) {
                $leading = false;
            }
            // Leading char.
            else {
                // A sequence of leadings only counts as a single.
                if (!$leading) {
                    ++$n;
                }
                $leading = true;
            }
        }
        return $n;
    }

    /**
     * Multibyte-safe sub string.
     *
     * Does not check if arg $v is valid UTF-8.
     *
     * @param mixed $var
     *      Gets stringified.
     * @param int $start
     * @param int|null $length
     *      Default: null; until end of arg str.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     *      Bad arg start or length.
     */
    public function substr($var, int $start, /*?int*/ $length = null) : string
    {
        if ($start < 0) {
            throw new \InvalidArgumentException('Arg start is not non-negative integer.');
        }
        if ($length !== null && (!is_int($length) || $length < 0)) {
            throw new \InvalidArgumentException('Arg length is not non-negative integer or null.');
        }
        $v = '' . $var;
        if (!$length || $v === '') {
            return '';
        }
        if (static::$nativeSupport['mbstring']) {
            return !$length ? mb_substr($v, $start) : mb_substr($v, $start, $length);
        }

        // The actual algo (further down) only works when start is zero.
        if ($start > 0) {
            // Trim off chars before start.
            $v = substr($v,
                strlen(
                    // Offsets multibyte string length.
                    $this->substr($v, 0, $start)
                )
            );
        }
        // And the algo needs a length.
        if (!$length) {
            $length = $this->strlen($v);
        }

        $n = 0;
        $le = strlen($v);
        $leading = false;
        for ($i = 0; $i < $le; $i++) {
            // ASCII.
            if (($ord = ord($v{$i})) < 128) {
                if ((++$n) > $length) {
                    return substr($v, 0, $i);
                }
                $leading = false;
            }
            // Continuation char.
            elseif ($ord < 192) { // continuation char
                $leading = false;
            }
            // Leading char.
            else {
                // A sequence of leadings only counts as a single.
                if (!$leading) {
                    if ((++$n) > $length) {
                        return substr($v, 0, $i);
                    }
                }
                $leading = true;
            }
        }
        return $v;
    }

    /**
     * Truncate multibyte safe until ~ASCII length is equal to/less than arg
     * length.
     *
     * Does not check if arg $v is valid UTF-8.
     *
     * @param mixed $var
     *      Gets stringified.
     * @param int $length
     *      Byte length (~ ASCII char length).
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     *      Bad arg length.
     */
    public function truncateToByteLength($var, int $length)
    {
        if ($length < 0) {
            throw new \InvalidArgumentException('Arg length is not non-negative integer.');
        }

        $v = '' . $var;
        if (strlen($v) <= $length) {
            return $v;
        }

        // Truncate to UTF-8 char length (>= byte length).
        $v = $this->substr($v, 0, $length);
        // If all ASCII.
        if (($le = strlen($v)) == $length) {
            return $v;
        }

        // This algo will truncate one UTF-8 char too many,
        // if the string ends with a UTF-8 char, because it doesn't check
        // if a sequence of continuation bytes is complete.
        // Thus the check preceding this algo (actual byte length matches
        // required max length) is vital.
        do {
            --$le;
            // String not valid UTF-8, because never found an ASCII or leading UTF-8
            // byte to break before.
            if ($le < 0) {
                return '';
            }
            // An ASCII byte.
            elseif (($ord = ord($v{$le})) < 128) {
                // We can break before an ASCII byte.
                $ascii = true;
                $leading = false;
            }
            // A UTF-8 continuation byte.
            elseif ($ord < 192) {
                $ascii = $leading = false;
            }
            // A UTF-8 leading byte.
            else {
                $ascii = false;
                // We can break before a leading UTF-8 byte.
                $leading = true;
            }
        } while($le > $length || (!$ascii && !$leading));

        return substr($v, 0, $le);
    }

    /**
     * @param string $haystack
     *      Gets stringified.
     * @param string $needle
     *      Gets stringified.
     *
     * @return bool|int
     *      False: if needle not found, or if either arg evaluates to empty string.
     */
    public function strpos($haystack, $needle)
    {
        $hstck = '' . $haystack;
        $ndl = '' . $needle;
        if ($hstck === '' || $ndl === '') {
            return false;
        }
        if (static::$nativeSupport['mbstring']) {
            return mb_strpos($hstck, $ndl);
        }

        $pos = strpos($hstck, $ndl);
        if (!$pos) {
            return $pos;
        }
        return count(
            preg_split('//u', substr($hstck, 0, $pos), null, PREG_SPLIT_NO_EMPTY)
        );
    }

    /**
     * Does nothing (except stringifying) if no mb_string support.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     */
    public function toUpperCase($var)
    {
        $v = '' . $var;
        if ($v === '') {
            return '';
        }
        if (static::$nativeSupport['mbstring']) {
            return mb_strtoupper($v);
        }
        return $v;
    }

    /**
     * Does nothing (except stringifying) if no mb_string support.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     */
    public function toLowerCase($var)
    {
        $v = '' . $var;
        if ($v === '') {
            return '';
        }
        if (static::$nativeSupport['mbstring']) {
            return mb_strtolower($v);
        }
        return $v;
    }

    /**
     * Does nothing (except stringifying) if no mb_string support.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     */
    public function toUpperCaseFirst($var)
    {
        $v = '' . $var;
        $len = $this->strlen($var);
        if (!$len) {
            return '';
        }
        if (static::$nativeSupport['mbstring']) {
            if ($len > 1) {
                return mb_strtoupper(mb_substr($v, 0, 1)) . mb_substr($v, 1);
            }
            return mb_strtoupper($v);
        }
        return $v;
    }
    /**
     * Does nothing (except stringifying) if no mb_string support.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     */
    public function toUpperCaseLast($var)
    {
        $v = '' . $var;
        $len = $this->strlen($var);
        if (!$len) {
            return '';
        }
        if (static::$nativeSupport['mbstring']) {
            if ($len > 1) {
                return mb_substr($v, 0, $len - 2) . mb_strtoupper(mb_substr($v, $len - 2));
            }
            return mb_strtoupper($v);
        }
        return $v;
    }
}
