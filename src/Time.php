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
 * DateTime with getters almost like Javascript Date, and stringable.
 *
 * @package SimpleComplex\Utils
 */
class Time extends \DateTime
{
    /**
     * For formats, see:
     * @see http://php.net/manual/en/function.date.php
     *
     * @param string $format
     * @param string $time
     * @param \DateTimeZone|null $timezone
     *      Default: local timezone.
     *
     * @return static|Time
     *
     * @throws \Exception
     *      Propagated; from \DateTime::createFromFormat().
     */
    public static function createFromFormat($format, $time, /*\DateTimeZone*/ $timezone = null) : Time
    {
        // NB: Type hinting (\DateTimeZone $timezone) would provoke E_WARNING.
        // Catch 22: Specs say that native method's arg $timezone is type hinted
        // \DateTimeZone, but warning when calling says it isn't.

        // but warning when calling says.

        return static::createFromDateTime(
            parent::createFromFormat($format, $time, $timezone)
        );
    }

    /**
     * @param \DateTimeInterface $dateTime
     *
     * @return static|Time
     */
    public static function createFromDateTime(\DateTimeInterface $dateTime) : Time
    {
        return new static($dateTime->format('Y-m-d H:i:s.u'), $dateTime->getTimezone());
    }

    /**
     * Get interval as constant immutable object,
     * a wrapped DateInterval with user-friendy methods for getting signed total
     *
     * @param \DateTimeInterface $dateTime
     *      Supposedly equal to or later than this time.
     *
     * @return TimeIntervalConstant
     */
    public function diffConstant(\DateTimeInterface $dateTime) : TimeIntervalConstant
    {
        return new TimeIntervalConstant($this->diff($dateTime));
    }

    /**
     * PHP 7.0 support for arg $microseconds, though ignored.
     *
     * Fairly safe to ignore because \DateInterval PHP <7.1 doesn't record
     * microseconds difference.
     *
     * @param int $hour
     * @param int $minute
     * @param int $second
     * @param int $microseconds
     *      Ignored when PHP 7.0 (<7.1).
     *
     * @return $this|\DateTime|Time
     *
     * @throws \RuntimeException
     *      Frozen.
     * @throws \Exception
     *      Propagated.
     */
    public function setTime($hour, $minute, $second = 0, $microseconds = 0)
    {
        if (PHP_MAJOR_VERSION == 7 && !PHP_MINOR_VERSION) {
            return parent::setTime($hour, $minute, $second);
        }
        return parent::setTime($hour, $minute, $second, $microseconds);
    }

    /**
     * Convenience method; set to midnight 00:00:00.000000.
     *
     * @return $this|\DateTime|Time
     */
    public function setToDateStart()
    {
        return $this->setTime(0, 0, 0, 0);
    }

    /**
     * Get full year.
     *
     * @return int
     */
    public function getYear() : int
    {
        return (int) $this->format('Y');
    }

    /**
     * @return int
     */
    public function getMonth() : int
    {
        return (int) $this->format('m');
    }

    /**
     * @return int
     */
    public function getDate() : int
    {
        return (int) $this->format('d');
    }

    /**
     * @return int
     */
    public function getHours() : int
    {
        return (int) $this->format('H');
    }

    /**
     * @return int
     */
    public function getMinutes() : int
    {
        return (int) $this->format('i');
    }

    /**
     * @return int
     */
    public function getSeconds() : int
    {
        return (int) $this->format('s');
    }

    /**
     * @return int
     */
    public function getMilliseconds() : int
    {
        return (int) $this->format('v');
    }

    /**
     * @return int
     */
    public function getMicroseconds() : int
    {
        return (int) $this->format('u');
    }

    /**
     * @return string
     */
    public function getDateISOlocal() : string
    {
        return $this->format('Y-m-d');
    }

    /**
     * @param bool $noSeconds
     *
     * @return string
     */
    public function getTimeISOlocal(bool $noSeconds = false) : string
    {
        return $this->format(!$noSeconds ? 'H:i:s' : 'H:i');
    }

    /**
     * @param bool $noSeconds
     *
     * @return string
     */
    public function getDateTimeISOlocal(bool $noSeconds = false) : string
    {
        return $this->format(!$noSeconds ? 'Y-m-d H:i:s' : 'Y-m-d H:i');
    }

    /**
     * To ISO-8601 with timezone marker.
     *
     * YYYY-MM-DDTHH:ii:ss.mmmmmm+HH:II
     *
     * Same as:
     * @see Time::__toString().
     *
     * @return string
     */
    public function toISOZonal() : string
    {
        return $this->format('c');
    }

    /**
     * To ISO-8601 UTC.
     *
     * YYYY-MM-DDTHH:ii:ss.mmmmmmZ
     *
     * Like Javascript Date.toISOString().
     *
     * @return string
     */
    public function toISOUTC() : string
    {
        $str = (clone $this)->setTimezone(new \DateTimeZone('UTC'))
            ->format('c');
        if (($pos = strpos($str, '+'))) {
            return substr($str, 0, $pos) . 'Z';
        }
        return '' . preg_replace('/\-[\d:]+$/', '', $str) . 'Z';
    }

    /**
     * YYYY-MM-DDTHH:ii:ss.mmmmmm+HH:II
     *
     * Same as:
     * @see Time::toISOZonal().
     *
     * @return string
     */
    public function __toString() : string
    {
        return $this->format('c');
    }
}
