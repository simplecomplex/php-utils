<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use Psr\Log\LoggerInterface;
use SimpleComplex\Utils\Exception\InvalidArgumentException;

/**
 * Class Sanitize
 *
 * @package SimpleComplex\Utils
 */
class Sanitize
{
    /**
     * @see GetInstanceOfFamilyTrait
     *
     * First object instantiated via this method, disregarding class called on.
     * @public
     * @static
     * @see GetInstanceOfFamilyTrait::getInstance()
     */
    use Traits\GetInstanceOfFamilyTrait;

    /**
     * For logger 'type' context; like syslog RFC 5424 'facility code'.
     *
     * @var string
     */
    const LOG_TYPE = 'sanitize';

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @see Sanitize::getInstance()
     * @see Sanitize::setLogger()
     *
     * @param LoggerInterface|null
     *      PSR-3 logger, if any.
     */
    public function __construct(/*?LoggerInterface*/ $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Overcome mutual dependency, provide a logger after instantiation.
     *
     * This class does not need a logger at all. But errors are slightly more
     * debuggable provided a logger.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger) /*: void*/
    {
        $this->logger = $logger;
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
     * @throws \RuntimeException
     *      If native regex function fails.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     */
    public function ascii($var) : string
    {
        // preg_replace() emits old-school warning on error; we want exception.
        $s = preg_replace('/[^[:ascii:]]/', '', '' . $var);
        if (!$s && $s === null) {
            $msg = 'var made native regex function fail.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'var' => $var,
                    ],
                ]);
            }
            throw new \RuntimeException('Arg ' . $msg);
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
     * @throws \RuntimeException
     *      If native regex function fails.
     *
     * @param mixed $var
     *      Gets stringified.
     * @param bool $noCarriageReturn
     *
     * @return string
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
            $msg = 'var made native regex function fail.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'var' => $var,
                    ],
                ]);
            }
            throw new \RuntimeException('Arg ' . $msg);
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
            $msg = 'var made native regex function fail.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'var' => $var,
                    ],
                ]);
            }
            throw new \RuntimeException('Arg ' . $msg);
        }
        return $s;
    }

    /**
     * Convert number to string avoiding E-notation for numbers outside system
     * precision range.
     *
     * @throws InvalidArgumentException
     *      If arg var isn't integer/float nor number-like when stringified.
     *
     * @param mixed $var
     *      Gets stringified.
     *
     * @return string
     */
    public function numberToString($var) : string
    {
        static $precision;
        if (!$precision) {
            $precision = pow(10, (int)ini_get('precision'));
        }
        $v = '' . $var;
        if (!is_numeric($v)) {
            $msg = 'var is not integer/float nor number-like when stringified.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'var' => $var,
                    ],
                ]);
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        // If within system precision, just string it.
        return ($v > -$precision && $v < $precision) ? $v : number_format($v, 0, '.', '');
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
}
