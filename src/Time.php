<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
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
     * Local (default) timezone object.
     *
     * Gets established once; first time a Time object is constructed.
     * @see Time::__construct()
     *
     * Beware of changing default timezone after using a Time object.
     * @see date_default_timezone_set()
     *
     * @var \DateTimeZone
     */
    protected static $timezoneLocal;

    /**
     * Offset of local (default) timezone.
     *
     * Gets established once; first time a Time object is constructed.
     * @see Time::__construct()
     *
     * Beware of changing default timezone after using a Time object.
     * @see date_default_timezone_set()
     *
     * Have to memorize the offset separately from the DateTimeZone object,
     * because the offset cannot (easily) be established from that object
     * directly; DateTimeZone::getTransitions() or via construction
     * of a DateTime using the DateTimeZone object.
     *
     * @var int
     */
    protected static $timezoneLocalOffset;

    /**
     * Whether this object's timezone offset matches local (default) offset.
     *
     * @see Time::offsetIsLocal()
     * @see Time::__construct()
     * @see Time::setTimezone()
     *
     * @var bool|null
     */
    protected $timezoneOffsetIsLocal;

    /**
     * Values: (empty)|milliseconds|microseconds; default empty.
     *
     * @see Time::jsonSerialize()
     * @see Time::setJsonSerializePrecision()
     *
     * @var string
     */
    protected $jsonSerializePrecision = '';

    /**
     * Get the local (default) timezone which gets memorized first time
     * the Time constructor gets called.
     *
     * @see Time::$timezoneLocal
     * @see Time::$timezoneLocalOffset
     *
     * @param bool $offset
     *      True: get offset, not the timezone object.
     *
     * @return \DateTimeZone|int
     */
    public static function getTimezoneLocalInternal(bool $offset = false)
    {
        if ($offset) {
            return static::$timezoneLocalOffset;
        }
        return static::$timezoneLocal;
    }

    /**
     * Check that default timezone has offset equivalent of arg timezoneAllowed.
     *
     * Call to ensure that local default timezone is set, and accords with what
     * is expected.
     *
     * Does NOT rely on (use) the internally memorized local (default) timezone
     * object which get established first time the Time constructor gets called.
     * @see Time::$timezoneLocal
     *
     * @param string $timezoneAllowed
     *      Examples: 'Z', 'UTC', 'Europe/Copenhagen'.
     *      Z and UTC seem to be the same.
     * @param bool $errOnMisMatch
     *      True: throws exception on timezone mismatch.
     *
     * @return bool
     *
     * @throws \LogicException
     *      If mismatch and arg errOnMisMatch; logic exception because
     *      considered a configuration error.
     */
    public static function checkTimezoneDefault(string $timezoneAllowed, bool $errOnMisMatch = false) : bool
    {
        // Have to create \DateTimes because \DateTimeZone constructor
        // requires a timezone argument, and \DateTimeZone::getOffset()
        // requires a \DateTime argument.
        $time_default = new \DateTime();
        $offset_default = $time_default->getOffset();
        $time_allowed = new \DateTime('now', new \DateTimeZone($timezoneAllowed));
        $offset_allowed = $time_allowed->getOffset();
        if ($offset_default !== $offset_allowed) {
            if ($errOnMisMatch) {
                throw new \LogicException(
                    'Illegal timezone[' . $time_default->getTimezone()->getName() . '] offset[' . $offset_default . ']'
                    . ', must be equivalent of timezone[' . $timezoneAllowed . '] with offset[' . $offset_allowed
                    . '], date.timezone ' . (!ini_get('date.timezone') ? 'not set in php.ini.' :
                        'of php.ini is \'' . ini_get('date.timezone') . '\'.'
                    )
                );
            }
            return false;
        }
        return true;
    }

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
        // NB: Argument type hinting (\DateTimeZone $timezone)
        // would provoke E_WARNING.
        // Catch 22: Specs say that native method's arg $timezone is type hinted
        // \DateTimeZone, but warning when calling says it isn't.

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
     * Checks whether the new object's timezone matches local (default) offset.
     * @see Time::offsetIsLocal()
     *
     * Memorizes local (default) timezone first time called.
     * @see Time::$timezoneLocal
     * @see Time::$timezoneLocalOffset
     *
     * @param string $time
     * @param \DateTimeZone $timezone
     */
    public function __construct($time = 'now', /*\DateTimeZone*/ $timezone = null)
    {
        parent::__construct($time, $timezone);
        // Memorize local (default) timezone name and offset once and for all.
        if (!static::$timezoneLocal) {
            $time_default = new \DateTime();
            static::$timezoneLocal = $time_default->getTimezone();
            static::$timezoneLocalOffset = $time_default->getOffset();
        }
        // Flag whether this object's timezone offset matches local (default).
        $this->timezoneOffsetIsLocal = $this->getOffset() == static::$timezoneLocalOffset;
    }

    /**
     * Checks whether the object's new timezone matches local (default) offset.
     * @see Time::offsetIsLocal()
     *
     * @param \DateTimeZone $timezone
     *
     * @return $this|\DateTime
     *
     * @throws \Exception
     *      Propagated from \DateTime::setTimezone().
     */
    public function setTimezone($timezone) : \DateTime /*self invariant*/
    {
        parent::setTimezone($timezone);
        // Flag whether this object's timezone offset matches local (default).
        $this->timezoneOffsetIsLocal = $this->getOffset() == static::$timezoneLocalOffset;
        return $this;
    }

    /**
     * Set the object's timezone to local (default).
     *
     * @return $this|\DateTime
     */
    public function setTimezoneToLocal() : \DateTime /*self invariant*/
    {
        parent::setTimezone(static::$timezoneLocal);
        return $this;
    }

    /**
     * Whether this object's timezone offset matches local (default) offset.
     *
     * @return bool
     */
    public function offsetIsLocal() : bool
    {
        return $this->timezoneOffsetIsLocal;
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
     * a wrapped DateInterval with user-friendy methods for getting signed total.
     *
     * Fixes that native diff()|\DateInterval calculation doesn't work correctly
     * with other timezone than UTC.
     * @see https://bugs.php.net/bug.php?id=52480
     * @see \DateTime::diff()
     * @see \DateInterval
     *
     * @param \DateTimeInterface $dateTime
     *      Supposedly equal to or later than this time.
     *
     * @return TimeIntervalConstant
     */
    public function diffConstant(\DateTimeInterface $dateTime) : TimeIntervalConstant
    {
        // Use UTC equivalent DateTimes.
        $baseline = $this;
        $deviant = $dateTime;
        $tz_utc = null;
        if ($baseline->getOffset()) {
            $tz_utc = new \DateTimeZone('UTC');
            $baseline = new \DateTime($this->format('Y-m-d H:i:s.u'), $tz_utc);
        }
        if ($deviant->getOffset()) {
            $deviant = new \DateTime($deviant->format('Y-m-d H:i:s.u'), $tz_utc ?? new \DateTimeZone('UTC'));
        }
        return new TimeIntervalConstant($baseline->diff($deviant));
    }

    /**
     * Unlike \Datetime::format() this throws exception on failure.
     *
     * \Datetime::format() emits warning and returns false on failure.
     *
     * Can unfortunately not simply override Datetime::format() because that
     * sends native \DateTime operations into perpetual loop.
     *
     * @param string $format
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     *      Arg format invalid.
     */
    public function formatSafely(string $format) : string
    {
        $v = $this->format($format);
        if (is_string($v)) {
            return $v;
        }
        throw new \InvalidArgumentException('Arg format[' . $format . '] is invalid.');
    }

    /**
     * Unlike \Datetime::modify() this throws exception on failure.
     *
     * \Datetime::modify() emits warning and returns false on failure.
     *
     * @param string $modify
     *
     * @return $this|Time
     *
     * @throws \InvalidArgumentException
     *      Arg format invalid.
     */
    public function modifySafely(string $modify) : Time
    {
        $modified = $this->modify($modify);
        if (($modified instanceof \DateTime)) {
            return $this;
        }
        throw new \InvalidArgumentException('Arg modify[' . $modify . '] is invalid.');
    }

    /**
     * PHP 7.0 support for arg $microseconds, though ignored when PHP <7.1.
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
    public function setTime($hour, $minute, $second = 0, $microseconds = 0) : \DateTime /*self invariant*/
    {
        if (PHP_MAJOR_VERSION == 7 && !PHP_MINOR_VERSION) {
            return parent::setTime($hour, $minute, $second);
        }
        return parent::setTime($hour, $minute, $second, $microseconds);
    }

    /**
     * Convenience method; set to midnight 00:00:00.000000.
     *
     * @return $this|Time
     */
    public function setToDateStart() : Time
    {
        return $this->setTime(0, 0, 0, 0);
    }

    /**
     * Set to first day of a month.
     *
     * @param int|null $month
     *      Null: month of this object.
     *
     * @return Time
     *
     * @throws \InvalidArgumentException
     *      Arg month not null or 1 through 12.
     */
    public function setToFirstDayOfMonth(int $month = null) : Time
    {
        if ($month !== null) {
            if ($month < 1 || $month > 12) {
                throw new \InvalidArgumentException('Arg month[' . $month . '] isn\'t null or 1 through 12.');
            }
            $mnth = $month;
        } else {
            $mnth = (int) $this->format('m');
        }

        return $this->setDate(
            (int) $this->format('Y'),
            $mnth,
            1
        );
    }

    /**
     * Set to last day of a month.
     *
     * @param int|null $month
     *      Null: month of this object.
     *
     * @return Time
     *
     * @throws \InvalidArgumentException
     *      Arg month not null or 1 through 12.
     */
    public function setToLastDayOfMonth(int $month = null) : Time
    {
        if ($month !== null) {
            if ($month < 1 || $month > 12) {
                throw new \InvalidArgumentException('Arg month[' . $month . '] isn\'t null or 1 through 12.');
            }
            $mnth = $month;
        } else {
            $mnth = (int) $this->format('m');
        }

        return $this->setDate(
            (int) $this->format('Y'),
            $mnth,
            $this->monthLengthDays($mnth)
        );
    }

    /**
     * Add to or subtract from one or more date parts.
     *
     * Validity adjustment when arg years:
     * If current date is February 29th and target year isn't a leap year,
     * then target date becomes February 28th.
     *
     * Validity adjustment when arg months:
     * If current month is longer than target month and current day
     * doesn't exist in target month, then target day becomes last day
     * of target month.
     *
     * These validity adjustments are equivalent with database adjustments
     * like MySQL::date_add() and MSSQL::dateadd().
     *
     * Native \DateTime::modify():
     * - is difficult to use and it's format argument isn't documented
     * - makes nonsensical year|month addition/subtraction
     * - doesn't throw exception on failure
     *
     * @see \DateTime::modify()
     *
     * @param int $years
     *      Subtracts if negative.
     * @param int $months
     *      Subtracts if negative.
     * @param int $days
     *      Subtracts if negative.
     *
     * @return $this|Time
     */
    public function modifyDate(int $years, int $months = 0, int $days = 0) : Time
    {
        if ($years) {
            $year = (int) $this->format('Y');
            $month = (int) $this->format('m');
            $day = (int) $this->format('d');
            // Validity adjustment when part is year:
            // If current date is February 29th and target year isn't
            // a leap year, then target date becomes February 28th.
            // Target date is February 29th and target year isn't leap year.
            if ($month == 2 && $day == 29 && !date('L', mktime(1, 1, 1, 2, 1, $year + $years))) {
                $day = 28;
            }
            $this->setDate($year + $years, $month, $day);
        }

        if ($months) {
            $target_year = $year = (int) $this->format('Y');
            $month = (int) $this->format('m');
            $day = (int) $this->format('d');
            $target_month = $month + $months;
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
            $this->setDate($target_year, $target_month, $day);
        }

        if ($days) {
            $this->modify(($days > 0 ? '+' : '-') . abs($days) . ' ' . (abs($days) > 1 ? 'days' : 'day'));
        }

        return $this;
    }

    /**
     * Add to or subtract from one or more time parts.
     *
     * Native \DateTime::modify():
     * - is difficult to use and it's format argument isn't documented
     * - doesn't throw exception on failure
     *
     * @param int $hours
     *      Subtracts if negative.
     * @param int $minutes
     *      Subtracts if negative.
     * @param int $seconds
     *      Subtracts if negative.
     * @param int $microseconds
     *      Subtracts if negative.
     *      Ignored when PHP 7.0 (<7.1).
     *
     * @return $this|Time
     */
    public function modifyTime(int $hours, int $minutes = 0, int $seconds = 0, int $microseconds = 0) : Time
    {
        $modifiers = [];
        if ($hours) {
            $modifiers[] = ($hours > 0 ? '+' : '-') . abs($hours) . ' ' . (abs($hours) > 1 ? 'hours' : 'hour');
        }
        if ($minutes) {
            $modifiers[] = ($minutes > 0 ? '+' : '-') . abs($minutes) . ' ' . (abs($minutes) > 1 ? 'minutes' : 'minute');
        }
        if ($seconds) {
            $modifiers[] = ($seconds > 0 ? '+' : '-') . abs($seconds) . ' ' . (abs($seconds) > 1 ? 'seconds' : 'second');
        }
        if ($microseconds && (PHP_MAJOR_VERSION != 7 || PHP_MINOR_VERSION)) {
            $modifiers[] = ($microseconds > 0 ? '+' : '-') . abs($microseconds)
                . ' ' . (abs($microseconds) > 1 ? 'microseconds' : 'microsecond');
        }
        if ($modifiers) {
            $this->modify(join(' ', $modifiers));
        }
        return $this;
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
     * Number of days in a month of year.
     *
     * @param int $month
     * @param int|null $year
     *      Null: year of this object.
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
     * Get full (YYYY) year.
     *
     * Beware that timezone (unlike Javascript) may not be local.
     * @see TimeLocal
     * @see Time::getDateISOlocal()
     * @see Time::getTimeISOlocal()
     * @see Time::getDateTimeISOlocal()
     *
     * @return int
     */
    public function getYear() : int
    {
        return (int) $this->format('Y');
    }

    /**
     * Beware that timezone (unlike Javascript) may not be local.
     * @see TimeLocal
     * @see Time::getDateISOlocal()
     * @see Time::getTimeISOlocal()
     * @see Time::getDateTimeISOlocal()
     *
     * @return int
     */
    public function getMonth() : int
    {
        return (int) $this->format('m');
    }

    /**
     * Beware that timezone (unlike Javascript) may not be local.
     * @see TimeLocal
     * @see Time::getDateISOlocal()
     * @see Time::getTimeISOlocal()
     * @see Time::getDateTimeISOlocal()
     *
     * @return int
     */
    public function getDate() : int
    {
        return (int) $this->format('d');
    }

    /**
     * Beware that timezone (unlike Javascript) may not be local.
     * @see TimeLocal
     * @see Time::getDateISOlocal()
     * @see Time::getTimeISOlocal()
     * @see Time::getDateTimeISOlocal()
     *
     * @return int
     */
    public function getHours() : int
    {
        return (int) $this->format('H');
    }

    /**
     * Beware that timezone (unlike Javascript) may not be local.
     * @see TimeLocal
     * @see Time::getDateISOlocal()
     * @see Time::getTimeISOlocal()
     * @see Time::getDateTimeISOlocal()
     *
     * @return int
     */
    public function getMinutes() : int
    {
        return (int) $this->format('i');
    }

    /**
     * Timezone independent.
     *
     * @return int
     */
    public function getSeconds() : int
    {
        return (int) $this->format('s');
    }

    /**
     * Timezone independent.
     *
     * @return int
     */
    public function getMilliseconds() : int
    {
        return (int) $this->format('v');
    }

    /**
     * Timezone independent.
     *
     * @return int
     */
    public function getMicroseconds() : int
    {
        return (int) $this->format('u');
    }

    /**
     * Format to Y-m-d.
     *
     * Deprecated because doesn't ensure local timezone despite it's name.
     * @deprecated
     * @see Time::getDateISO()
     * @see Time::toDateISOLocal()
     *
     * @return string
     */
    public function getDateISOlocal() : string
    {
        trigger_error(
            'Method Time::' . __FUNCTION__ . '() is deprecated because doesn\'t deliver as it\'s name promises. Use instead '
            . 'getDateISO() or toDateISOLocal().',
            E_USER_DEPRECATED
        );
        return $this->format('Y-m-d');
    }

    /**
     * Format to Y-m-d, using the object's timezone.
     *
     * Beware that timezone (unlike Javascript) may not be local.
     * @see Time::toDateISOLocal()
     *
     * @return string
     */
    public function getDateISO() : string
    {
        return $this->format('Y-m-d');
    }

    /**
     * Format to H:i:s|H:i.
     *
     * Deprecated because doesn't ensure local timezone despite it's name.
     * @deprecated
     * @see Time::getTimeISO()
     * @see Time::toTimeISOLocal()
     *
     * @param bool $noSeconds
     *
     * @return string
     */
    public function getTimeISOlocal(bool $noSeconds = false) : string
    {
        trigger_error(
            'Method Time::' . __FUNCTION__ . '() is deprecated because doesn\'t deliver as it\'s name promises. Use instead '
            . 'getTimeISO() or toTimeISOLocal().',
            E_USER_DEPRECATED
        );
        return $this->format(!$noSeconds ? 'H:i:s' : 'H:i');
    }

    /**
     * Format to H:i:s|H:i, using the object's timezone.
     *
     * Beware that timezone (unlike Javascript) may not be local.
     * @see Time::toTimeISOLocal()
     *
     * @param bool $noSeconds
     *
     * @return string
     */
    public function getTimeISO(bool $noSeconds = false) : string
    {
        return $this->format(!$noSeconds ? 'H:i:s' : 'H:i');
    }

    /**
     * Format to Y-m-d H:i:s|Y-m-d H:i.
     *
     * Deprecated because doesn't ensure local timezone despite it's name.
     * @deprecated
     * @see Time::getDateTimeISO()
     * @see Time::toDateTimeISOLocal()
     *
     * @param bool $noSeconds
     *
     * @return string
     */
    public function getDateTimeISOlocal(bool $noSeconds = false) : string
    {
        trigger_error(
            'Method Time::' . __FUNCTION__ . '() is deprecated because doesn\'t deliver as it\'s name promises. Use instead '
            . 'getDateTimeISO() or toDateTimeISOLocal().',
            E_USER_DEPRECATED
        );
        return $this->format(!$noSeconds ? 'Y-m-d H:i:s' : 'Y-m-d H:i');
    }

    /**
     * Format to Y-m-d H:i:s|Y-m-d H:i, using the object's timezone.
     *
     * Beware that timezone (unlike Javascript) may not be local.
     * @see Time::toTimeISOLocal()
     *
     * @param bool $noSeconds
     *
     * @return string
     */
    public function getDateTimeISO(bool $noSeconds = false) : string
    {
        return $this->format(!$noSeconds ? 'Y-m-d H:i:s' : 'Y-m-d H:i');
    }

    /**
     * Format to Y-m-d, using local (default) timezone.
     *
     * Does not alter the object's own timezone.
     *
     * @return string
     */
    public function toDateISOLocal() : string
    {
        if ($this->timezoneOffsetIsLocal) {
            $that = $this;
        } else {
            $that = (clone $this)->setTimezone(static::$timezoneLocal);
        }
        return $that->format('Y-m-d');
    }

    /**
     * Format to H:i:s|H:i, using local (default) timezone.
     *
     * Does not alter the object's own timezone.
     *
     * @param bool $noSeconds
     *
     * @return string
     */
    public function toTimeISOLocal(bool $noSeconds = false) : string
    {
        if ($this->timezoneOffsetIsLocal) {
            $that = $this;
        } else {
            $that = (clone $this)->setTimezone(static::$timezoneLocal);
        }
        return $that->format(!$noSeconds ? 'H:i:s' : 'H:i');
    }

    /**
     * Format to Y-m-d H:i:s|Y-m-d H:i, using local (default) timezone.
     *
     * Does not alter the object's own timezone.
     *
     * @param bool $noSeconds
     *
     * @return string
     */
    public function toDateTimeISOLocal(bool $noSeconds = false) : string
    {
        if ($this->timezoneOffsetIsLocal) {
            $that = $this;
        } else {
            $that = (clone $this)->setTimezone(static::$timezoneLocal);
        }
        return $that->format(!$noSeconds ? 'Y-m-d H:i:s' : 'Y-m-d H:i');
    }

    /**
     * To ISO-8601 with timezone marker, optionally with milli- or microseconds
     * precision.
     *
     * Formats:
     * YYYY-MM-DDTHH:ii:ss+HH:II
     * YYYY-MM-DDTHH:ii:ss.mmm+HH:II
     * YYYY-MM-DDTHH:ii:ss.mmmmmm+HH:II
     *
     * Same as:
     * @see Time::__toString().
     *
     * @param string $precision
     *      Values: (empty)|milliseconds|microseconds.
     *      Default: empty; with neither milli- nor microseconds.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     *      Arg precision not empty or milliseconds|microseconds.
     */
    public function toISOZonal(string $precision = '') : string
    {
        switch ($precision) {
            case '':
                $minor = '';
                break;
            case 'milliseconds':
                $minor = '.' . $this->format('v');
                break;
            case 'microseconds':
                $minor = '.' . $this->format('u');
                break;
            default:
                throw new \InvalidArgumentException(
                    'Arg precision[' . $precision . '] isn\'t empty or value milliseconds|microseconds.'
                );
        }
        if (!$precision) {
            return $this->format('c');
        }
        $str = $this->format('c');
        return substr($str, 0, -6) . $minor . substr($str, -6);
    }

    /**
     * To ISO-8601 UTC, optionally with milli- or microseconds precision.
     *
     * Formats:
     * YYYY-MM-DDTHH:ii:ssZ
     * YYYY-MM-DDTHH:ii:ss.mmmZ
     * YYYY-MM-DDTHH:ii:ss.mmmmmmZ
     *
     * Like Javascript Date.toISOString(); when milliseconds precision.
     *
     * @param string $precision
     *      Values: (empty)|milliseconds|microseconds.
     *      Default: empty; with neither milli- nor microseconds.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     *      Arg precision not empty or milliseconds|microseconds.
     */
    public function toISOUTC(string $precision = '') : string
    {
        switch ($precision) {
            case '':
                $minor = '';
                break;
            case 'milliseconds':
                $minor = '.' . $this->format('v');
                break;
            case 'microseconds':
                $minor = '.' . $this->format('u');
                break;
            default:
                throw new \InvalidArgumentException(
                    'Arg precision[' . $precision . '] isn\'t empty or value milliseconds|microseconds.'
                );
        }
        return substr(
                (clone $this)->setTimezone(new \DateTimeZone('UTC'))->format('c'),
                0,
                -6
            ) . $minor . 'Z';
    }

    /**
     * Format to YYYY-MM-DDTHH:ii:ss+HH:II
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
     * Set precision of JSON serialized representation.
     *
     * @see Time::jsonSerialize()
     * @see \JsonSerializable
     *
     * @param string $precision
     *      Values: (empty)|milliseconds|microseconds.
     *      Default: empty; with neither milli- nor microseconds.
     *
     * @return $this|Time
     *
     * @throws \InvalidArgumentException
     *      Arg precision not empty or milliseconds|microseconds.
     */
    public function setJsonSerializePrecision(string $precision) : Time
    {
        switch ($precision) {
            case '':
            case 'milliseconds':
            case 'microseconds':
                $this->jsonSerializePrecision = $precision;
                return $this;
        }
        throw new \InvalidArgumentException(
            'Arg precision[' . $precision . '] isn\'t empty or value milliseconds|microseconds.'
        );
    }

    /**
     * JSON serializes to string ISO-8601 with timezone marker.
     *
     * Unlike native \DateTime which JSON serializes to object;
     * which is great when communicating with other PHP base node,
     * but a nuisance when communicating with anything else.
     *
     * @see Time::setJsonSerializePrecision()
     * @see \JsonSerializable
     *
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->toISOZonal($this->jsonSerializePrecision);
    }
}
