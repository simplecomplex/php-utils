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
 * DateTime with getters almost like Javascript Date.
 *
 * @package SimpleComplex\Utils
 */
class DateTime extends \DateTime
{
    /**
     * @param \DateTimeInterface $dateTime
     *
     * @return DateTime
     */
    public static function createFromDateTime(\DateTimeInterface $dateTime) : DateTime
    {
        //return (new static())->setTimestamp($dateTime->getTimestamp());
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
     * @param bool $utc
     *      True: Z, no timezone marker, YYYY-...Z.
     *      False: YYYY-...+/-HH:II
     *
     * @return string
     */
    public function getDateTimeIso8601(bool $utc = false) : string
    {
        if (!$utc) {
            return $this->format('c');
        }
        $str = (new \DateTime())->setTimestamp($this->getTimestamp())
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('c');
        if (($pos = strpos($str, '+'))) {
            return substr($str, 0, $pos) . 'Z';
        }
        return '' . preg_replace('/\-[\d:]+$/', '', $str) . 'Z';
    }
}
