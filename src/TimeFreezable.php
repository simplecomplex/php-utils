<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use SimpleComplex\Utils\Interfaces\FreezableInterface;

/**
 * Freezable (DateTime) Time.
 *
 * @see \SimpleComplex\Utils\Time
 *
 * @package SimpleComplex\Utils
 */
class TimeFreezable extends Time implements FreezableInterface
{
    /**
     * @var bool
     */
    protected $frozen = false;

    /**
     * The clone will be unfrozen.
     *
     * @return void
     */
    public function __clone() /*: void*/
    {
        $this->frozen = false;
        // \DateTime has no __clone() method in PHP 7.0.
        //parent::__clone();
    }

    /**
     * Chainable.
     *
     * @return $this|TimeFreezable
     */
    public function freeze() /*: object*/
    {
        $this->frozen = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function isFrozen() : bool
    {
        return $this->frozen;
    }

    /**
     * @param \DateInterval $interval
     *
     * @return $this|\DateTime|TimeFreezable
     *
     * @throws \RuntimeException
     *      Frozen.
     * @throws \Exception
     *      Propagated.
     */
    public function add(/*\DateInterval*/ $interval)
    {
        // NB: Type hinting (\DateInterval $interval)
        // would provoke E_WARNING when cloning.
        // Catch 22: Specs say that native \DateTime method is type hinted,
        // but warning when cloning says it isn't.

        if ($this->frozen) {
            throw new \RuntimeException(get_class($this) . ' is read-only, frozen.');
        }
        return parent::add($interval);
    }

    /**
     * @param string $modify
     *
     * @return $this|\DateTime|TimeFreezable
     *
     * @throws \RuntimeException
     *      Frozen.
     * @throws \Exception
     *      Propagated.
     */
    public function modify($modify)
    {
        if ($this->frozen) {
            throw new \RuntimeException(get_class($this) . ' is read-only, frozen.');
        }
        return parent::modify($modify);
    }

    /**
     * @param int $year
     * @param int $month
     * @param int $day
     *
     * @return $this|\DateTime|TimeFreezable
     *
     * @throws \RuntimeException
     *      Frozen.
     * @throws \Exception
     *      Propagated.
     */
    public function setDate($year, $month, $day)
    {
        if ($this->frozen) {
            throw new \RuntimeException(get_class($this) . ' is read-only, frozen.');
        }
        return parent::setDate($year, $month, $day);
    }

    /**
     * @param int $year
     * @param int $week
     * @param int $day
     *
     * @return $this|\DateTime|TimeFreezable
     *
     * @throws \RuntimeException
     *      Frozen.
     * @throws \Exception
     *      Propagated.
     */
    public function setISODate($year, $week, $day = 1)
    {
        if ($this->frozen) {
            throw new \RuntimeException(get_class($this) . ' is read-only, frozen.');
        }
        return parent::setIsoDate($year, $week, $day);
    }

    /**
     * @param int $hour
     * @param int $minute
     * @param int $second
     * @param int $microseconds
     *
     * @return $this|\DateTime|TimeFreezable
     *
     * @throws \RuntimeException
     *      Frozen.
     * @throws \Exception
     *      Propagated.
     */
    public function setTime($hour, $minute, $second = 0, $microseconds = 0)
    {
        if ($this->frozen) {
            throw new \RuntimeException(get_class($this) . ' is read-only, frozen.');
        }
        return parent::setTime($hour, $minute, $second, $microseconds);
    }

    /**
     * @return $this|\DateTime|TimeFreezable
     *
     * @throws \RuntimeException
     *      Frozen.
     * @throws \Exception
     */
    public function setToDateStart()
    {
        if ($this->frozen) {
            throw new \RuntimeException(get_class($this) . ' is read-only, frozen.');
        }
        return parent::setTime(0, 0, 0, 0);
    }

    /**
     * @param int $unixtimestamp
     *
     * @return $this|\DateTime|TimeFreezable
     *
     * @throws \RuntimeException
     *      Frozen.
     * @throws \Exception
     *      Propagated.
     */
    public function setTimestamp($unixtimestamp)
    {
        if ($this->frozen) {
            throw new \RuntimeException(get_class($this) . ' is read-only, frozen.');
        }
        return parent::setTimestamp($unixtimestamp);
    }

    /**
     * @param \DateTimeZone $timezone
     *
     * @return $this|\DateTime|TimeFreezable
     *
     * @throws \RuntimeException
     *      Frozen.
     * @throws \Exception
     *      Propagated.
     */
    public function setTimezone($timezone)
    {
        if ($this->frozen) {
            throw new \RuntimeException(get_class($this) . ' is read-only, frozen.');
        }
        return parent::setTimezone($timezone);
    }

    /**
     * @param \DateInterval $interval
     *
     * @return $this|\DateTime|TimeFreezable
     *
     * @throws \RuntimeException
     *      Frozen.
     * @throws \Exception
     *      Propagated.
     */
    public function sub(/*\DateInterval*/ $interval)
    {
        // NB: Type hinting (\DateInterval $interval)
        // would provoke E_WARNING when cloning.
        // Catch 22: Specs say that native \DateTime method is type hinted,
        // but warning when cloning says it isn't.

        if ($this->frozen) {
            throw new \RuntimeException(get_class($this) . ' is read-only, frozen.');
        }
        return parent::sub($interval);
    }
}