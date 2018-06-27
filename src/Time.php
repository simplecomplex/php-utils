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
class Time extends \DateTime implements \JsonSerializable
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
     * Create from native \DateTime.
     *
     * @param \DateTimeInterface $dateTime
     *
     * @return static|Time
     */
    public static function createFromDateTime(\DateTimeInterface $dateTime) : Time
    {
        return new static($dateTime->format('Y-m-d H:i:s.u'), $dateTime->getTimezone());
    }

    /**
     * Get as native \DateTime.
     *
     * @return \DateTime
     */
    public function toDatetime() : \DateTime
    {
        return new \DateTime($this->format('Y-m-d H:i:s.u'), $this->getTimezone());
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
     * Add to a date or time part.
     *
     * Validity adjustment when part is year:
     * If current date is February 29th and target year isn't a leap year,
     * then target date becomes February 28th.
     *
     * Validity adjustment when part is month:
     * If current month is longer than target month and current day
     * doesn't exist in target month, then target day becomes last day
     * of target month.
     *
     * These validity adjustments are equivalent with database adjustments
     * like MySQL::date_add() and MSSQL::dateadd().
     *
     * @param string $part
     *      Values: year|month|day|hour|minute|second.
     * @param int $amount
     *      Subtracts if negative.
     *
     * @return $this|\DateTime|Time
     */
    public function addPart(string $part, int $amount)
    {
        if ($amount) {
            switch ($part) {
                case 'year':
                    $year = (int) $this->format('Y');
                    $month = (int) $this->format('m');
                    $day = (int) $this->format('d');
                    // Validity adjustment when part is year:
                    // If current date is February 29th and target year isn't
                    // a leap year, then target date becomes February 28th.
                    // Target date is February 29th and target year isn't leap year.
                    if ($month == 2 && $day == 29 && !date('L', mktime(1, 1, 1, 2, 1, $year + $amount))) {
                        $day = 28;
                    }
                    return $this->setDate($year + $amount, $month, $day);
                case 'month':
                    $target_year = $year = (int) $this->format('Y');
                    $month = (int) $this->format('m');
                    $day = (int) $this->format('d');
                    $target_month = $month + $amount;
                    if ($target_month > 12) {
                        $add_years = (int) floor($target_month / 12);
                        $target_month -= $add_years * 12;
                        $target_year += $add_years;
                    }
                    elseif ($target_month < 1) {
                        $subtract_years = (int) ceil(-$target_month / 12);
                        $target_month += $subtract_years * 12;
                        $target_year -= $subtract_years;
                    }
                    if (!$target_month) {
                        $target_month = 12;
                        --$target_year;
                    }
                    // Validity adjustment when part is month:
                    // If current month is longer than target month and current
                    // day doesn't exist in target month, then target day
                    // becomes last day of target month.
                    if ($day > 28) {
                        $max_day = $target_year == $year ? $this->monthLengthDays($target_month) :
                            $this->monthLengthDays($target_month, $target_year);
                        if ($day > $max_day) {
                            $day = $max_day;
                        }
                    }
                    return $this->setDate($target_year, $target_month, $day);
                case 'day':
                case 'hour':
                case 'minute':
                case 'second':
                    if ($amount < 0) {
                        $sign = '-';
                        $num = $amount * -1;
                    } else {
                        $sign = '+';
                        $num = $amount;
                    }
                    return $this->modify($sign . $num . ' ' . $part . ($num > 1 ? 's' : ''));
                default:
                    throw new \InvalidArgumentException('Arg part[' . $part . '] is not a supported a date part.');
            }
        }
        return $this;
    }

    /**
     * Subtract from a date or time part.
     *
     * See addPart() for year and month handling.
     * @see Time::addPart()
     *
     * @param string $part
     *      Values: year|month|day|hour|minute|second.
     * @param int $amount
     *      Adds if negative.
     *
     * @return $this|\DateTime|Time
     */
    public function subPart(string $part, int $amount)
    {
        return $this->addPart($part, $amount * -1);
    }

    /**
     * @param int $year
     *      Default: year of this object.
     *
     * @return bool
     */
    public function isLeapYear(int $year = null) : bool
    {
        return !!date(
            'L',
            $year === null ? $this->getTimestamp() : mktime(1, 1, 1, 2, 1, $year)
        );
    }

    /**
     * @param int $month
     * @param int $year
     *      Default: year of this object.
     *
     * @return int
     *
     * @throws \InvalidArgumentException
     *      Arg month not 1 through 12.
     */
    public function monthLengthDays(int $month, int $year = null) : int
    {
        switch ($month) {
            case 1:
            case 3:
            case 5:
            case 7:
            case 8:
            case 10:
            case 12:
                return 31;
            case 4:
            case 6:
            case 9:
            case 11:
                return 30;
            case 2:
                return !date('L', $year === null ? $this->getTimestamp() : mktime(1, 1, 1, 2, 1, $year)) ? 28 : 29;
        }
        throw new \InvalidArgumentException('Arg month[' . $month . '] is not 1 through 12.');
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

    /**
     * JSON serializes to string ISO-8601 with timezone marker.
     *
     * Unlike native \DateTime which JSON serializes to object;
     * which isgreat when communicating with other PHP base node,
     * but a nuisance when communicating with anything else.
     *
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->toISOZonal();
    }
}
