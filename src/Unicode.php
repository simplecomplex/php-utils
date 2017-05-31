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
 * Class Unicode
 *
 * @package SimpleComplex\Utils
 */
class Unicode
{
    /**
     * @see GetInstanceTrait
     *
     * Reference to last instantiated instance of this class.
     * @protected
     * @static
     * @var static $instanceByClass
     *
     * Get previously instantiated object or create new.
     * @public
     * @static
     * @see GetInstanceTrait::getInstance()
     */
    use GetInstanceTrait;

    /**
     * For logger 'type' context; like syslog RFC 5424 'facility code'.
     *
     * @var string
     */
    const LOG_TYPE = 'unicode';

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @see Unicode::getInstance()
     * @see Unicode::setLogger()
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
     * @var int
     */
    protected static $mbString = -1;

    /**
     * @return int
     *      0|1.
     */
    public static function nativeSupport() : int
    {
        $support = static::$mbString;
        if ($support == -1) {
            static::$mbString = $support = (int) function_exists('mb_strlen');
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
        if (static::nativeSupport()) {
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
     * @throws InvalidArgumentException
     *      Bad arg start or length.
     *
     * @param mixed $var
     *      Gets stringified.
     * @param int $start
     * @param int|null $length
     *      Default: null; until end of arg str.
     *
     * @return string
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
        if (static::nativeSupport()) {
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
     * @throws InvalidArgumentException
     *      Bad arg length.
     *
     * @param mixed $var
     *      Gets stringified.
     * @param int $length
     *      Byte length (~ ASCII char length).
     *
     * @return string
     */
    public function truncateToByteLength($var, int $length)
    {
        if ($length < 0) {
            $msg = 'length is not non-negative integer.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'length' => $length,
                    ],
                ]);
            }
            throw new InvalidArgumentException('Arg ' . $msg);
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
        if (static::nativeSupport()) {
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

}
