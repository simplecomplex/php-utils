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
     * @return DateTimeSimple
     */
    public static function createFromDateTime(\DateTimeInterface $dateTime) : DateTimeSimple
    {
        return new static($dateTime->format('Y-m-d H:i:s.u'), $dateTime->getTimezone());
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
    public function toIso8601Zoned() : string
    {
        return $this->format('c');
    }

    /**
     * To ISO-8601 UTC.
     *
     * YYYY-MM-DDTHH:ii:ss.mmmmmmZ
     *
     * @return string
     */
    public function toIso8601Utc() : string
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
     * @see DateTimeSimple::toIso8601Zoned().
     *
     * @return string
     */
    public function __toString() : string
    {
        return $this->format('c');
    }
}
