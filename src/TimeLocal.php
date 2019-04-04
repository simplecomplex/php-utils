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
 * Time which uses local (default) timezone, or secures that timezone
 * matches local timezone.
 *
 * Secures that presumable (but not actual) locally timezone'd getters
 * return values according to local timezone.
 * @see Time::getYear()
 * @see Time::getMonth()
 * @see Time::getDate()
 * @see Time::getMinutes()
 *
 * Safeguards against unexpected behaviour when creating datetime from non-PHP
 * source, which may serialize using UTC as timezone instead of local (like
 * Javascript).
 *
 * @see \SimpleComplex\Utils\Time
 *
 * @package SimpleComplex\Utils
 */
class TimeLocal extends Time
{
    /**
     * Sets timezone to local (default) upon initial construction,
     * if the timezone doesn't match local timezone.
     *
     * @param string $time
     * @param \DateTimeZone $timezone
     */
    public function __construct($time = 'now', /*\DateTimeZone*/ $timezone = null)
    {
        parent::__construct($time, $timezone);
        if (!$this->timezoneIsLocal) {
            $this->setTimezone(static::$timezoneLocal);
        }
    }
}
