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
class DateTimeSimple extends \DateTime
{
    /**
     * @param \DateTimeInterface $dateTime
     *
     * @return static|DateTimeSimple
     */
    public static function createFromDateTime(\DateTimeInterface $dateTime) : DateTimeSimple
    {
        return new static($dateTime->format('Y-m-d H:i:s.u'), $dateTime->getTimezone());
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
     * @return $this|\DateTime|DateTimeSimple
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
     * @return $this|\DateTime|DateTimeSimple
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
     * To ISO-8601 with timezone marker.
     *
     * YYYY-MM-DDTHH:ii:ss.mmmmmm+HH:II
     *
     * Same as:
     * @see DateTimeSimple::__toString().
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
     * @see DateTimeSimple::toISOZonal().
     *
     * @return string
     */
    public function __toString() : string
    {
        return $this->format('c');
    }
}
