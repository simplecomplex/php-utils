<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

/**
 * Sanitize and convert strings, numbers et al.
 *
 * @package SimpleComplex\Utils
 */
class Sanitize
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var Sanitize
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return Sanitize
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
     * @see Sanitize::getInstance()
     */
    public function __construct()
    {
    }

    /**
     * Remove tags, escape HTML entities, and remove invalid UTF-8 sequences.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     */
    public function plainText($var) : string
    {
        return htmlspecialchars(strip_tags('' . $var), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    /**
     * Full ASCII; 0-127.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     *
     * @throws \RuntimeException
     *      If native regex function fails.
     */
    public function ascii($var) : string
    {
        // preg_replace() emits old-school warning on error; we want exception.
        $s = preg_replace('/[^[:ascii:]]/', '', '' . $var);
        if (!$s && $s === null) {
            throw new \RuntimeException('PHP native regex function failed.');
        }
        return $s;
    }

    /**
     * ASCII except lower ASCII and DEL.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     */
    public function asciiPrintable($var) : string
    {
        return str_replace(
            chr(127),
            '',
            filter_var('' . $var, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH)
        );
    }

    /**
     * ASCII printable that allows newline and (default) carriage return.
     *
     * @param mixed $var
     *      Gets stringified.
     * @param bool $noCarriageReturn
     *
     * @return string
     *
     * @throws \RuntimeException
     *      If native regex function fails.
     */
    public function asciiMultiLine($var, $noCarriageReturn = false) : string
    {
        // Remove lower ASCII except newline \x0A and CR \x0D,
        // and remove DEL and upper range.
        $s = preg_replace(
            !$noCarriageReturn ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x09\x0B-\x1F\x7F]/',
            '',
            filter_var('' . $var, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH)
        );
        // preg_replace() emits old-school warning on error; we want exception.
        if (!$s && $s === null) {
            throw new \RuntimeException('PHP native regex function failed.');
        }
        return $s;
    }

    /**
     * Allows anything but lower ASCII and DEL.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     */
    public function unicodePrintable($var) : string
    {
        return str_replace(
            chr(127),
            '',
            filter_var('' . $var, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW)
        );
    }

    /**
     * Unicode printable that allows newline and (default) carriage return.
     *
     * @throws \RuntimeException
     *      If native regex function fails.
     *
     * @param mixed $var
     *      Gets stringified.
     * @param bool $noCarriageReturn
     *
     * @return string
     */
    public function unicodeMultiline($var, $noCarriageReturn = false) : string
    {
        // Remove lower ASCII except newline \x0A and CR \x0D, and remove DEL.
        $s = preg_replace(
            !$noCarriageReturn ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x09\x0B-\x1F\x7F]/',
            '',
            '' . $var
        );
        // preg_replace() emits old-school warning on error; we want exception.
        if (!$s && $s === null) {
            throw new \RuntimeException('PHP native regex function failed.');
        }
        return $s;
    }

    /**
     * Convert number to string avoiding E-notation for numbers outside system
     * precision range.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     *      If arg var isn't integer/float nor number-like when stringified.
     */
    public function numberToString($var) : string
    {
        static $precision;
        if (!$precision) {
            $precision = pow(10, (int)ini_get('precision'));
        }
        $v = '' . $var;
        if (!is_numeric($v)) {
            throw new \InvalidArgumentException('Arg var is not integer/float nor number-like when stringified.');
        }

        // If within system precision, just string it.
        return ($v > -$precision && $v < $precision) ? $v : number_format((float) $v, 0, '.', '');
    }

    /**
     * Sanitize var to be printed in CLI console.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     */
    public function cli($var) : string {
        return str_replace('`', '´', '' . $var);
    }

    /**
     * Unicode not supported.
     *
     * @param mixed $var
     *      Gets stringified.
     * @param bool $upperFirst
     *
     * @return string
     */
    public function toCamelCase($var, $upperFirst = false)
    {
        $v = '' . $var;
        $le = strlen($v);
        if (!$le) {
            return '';
        }
        $arr = preg_split('/[_\- ]/', $v);
        for ($i = 0; $i < $le; ++$i) {
            if ($i || $upperFirst) {
                $arr[$i] = ucfirst($arr[$i]{0}) . substr($arr[$i], 1);
            }
        }
        return join($arr);
    }
}
