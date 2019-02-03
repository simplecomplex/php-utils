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
 * Freezable Time which uses local (default) timezone, or secures that timezone
 * matches local timezone.
 *
 * Works like TimeLocal but cannot extend that class, because of PHP extension
 * restriction (only one parent).
 *
 * @see \SimpleComplex\Utils\TimeFreezable
 * @see \SimpleComplex\Utils\TimeLocal
 *
 * @package SimpleComplex\Utils
 */
class TimeLocalFreezable extends TimeFreezable
{
    /**
     * Sets timezone to local (default) upon initial construction, if the
     * timezone doesn't match local timezone.
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
