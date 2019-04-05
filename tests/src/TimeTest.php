<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Tests\Utils;

use PHPUnit\Framework\TestCase;

use Psr\Container\ContainerInterface;
use SimpleComplex\Utils\Time;

/**
 * @code
 * // CLI, in document root:
 * backend/vendor/bin/phpunit backend/vendor/simplecomplex/utils/tests/src/TimeTest.php
 * @endcode
 *
 * @package SimpleComplex\Tests\Utils
 */
class TimeTest extends TestCase
{
    /**
     * @see BootstrapTest::testDependencies()
     */
    public function testInstantiation()
    {
        $container = (new BootstrapTest())->testDependencies();
        static::assertInstanceOf(ContainerInterface::class, $container);
    }

    /**
     * @see \SimpleComplex\Utils\Time::validateTimezoneDefault()
     *
     * @expectedException \LogicException
     */
    public function testValidateTimezoneDefault()
    {
        $timezone_default = date_default_timezone_get();
        static::assertTrue(Time::checkTimezoneDefault($timezone_default));

        $tz_default = (new Time())->getTimezone()->getName();
        if ($tz_default == 'UTC' || $tz_default == 'Z') {
            static::assertTrue(Time::checkTimezoneDefault('UTC'));
            static::assertFalse(Time::checkTimezoneDefault(BootstrapTest::TIMEZONE));
            /**
             * @throws \LogicException
             */
            Time::checkTimezoneDefault(BootstrapTest::TIMEZONE, true);
        } else {
            static::assertFalse(Time::checkTimezoneDefault('UTC'));
            /**
             * @throws \LogicException
             */
            Time::checkTimezoneDefault('UTC', true);
        }
    }

    /**
     * @see \SimpleComplex\Utils\Time::modifyDate()
     */
    public function testModifyDate()
    {
        $time = new Time('2018-01-01');

        $years = $months = $days = 1;
        static::assertSame('2018-01-01', (clone $time)->modifyDate(0, 0)->getDateISO());
        static::assertSame('2019-02-02', (clone $time)->modifyDate($years, $months, $days)->getDateISO());
        // 2017-01-01
        // 2016-12-01
        // 2016-11-30
        static::assertSame('2016-11-30', (clone $time)->modifyDate(-$years, -$months, -$days)->getDateISO());

        // Modifying month only.------------------------------------------------
        $log = [];

        $year = 2018;
        $month = 1;
        $day = 1;
        $time = (new Time())->setDate($year, $month, $day);
        $limit = 25;
        $log[] = '';
        $log[] = '     ' . $time->getDateISO();
        for ($months = 1; $months <= $limit; ++$months) {
            $yr = $year;
            $mnth = $month;
            if ($months < 12) {
                $mnth += $months;
            }
            elseif ($months < 24) {
                $yr += 1;
                $mnth += ($months - 12);
            }
            else {
                $yr += 2;
                $mnth += ($months - 24);
            }
            static::assertSame(
                ($yr)
                . '-' . str_pad('' . $mnth, 2, '0', STR_PAD_LEFT)
                . '-' . str_pad('' . ($day), 2, '0', STR_PAD_LEFT),
                $result = (clone $time)->modifyDate(0, $months)->getDateISO()
            );
            $log[] = str_pad('' . $months, 3, ' ', STR_PAD_LEFT) . ': ' . $result;
        }

        $year = 2018;
        $month = 12;
        $day = 1;
        $time = (new Time())->setDate($year, $month, $day);
        $limit = -25;
        $log[] = '';
        $log[] = '     ' . $time->getDateISO();
        for ($months = -1; $months >= $limit; --$months) {
            $yr = $year;
            $mnth = $month;
            if ($months > -12) {
                $mnth += $months;
            }
            elseif ($months > -24) {
                $yr -= 1;
                $mnth += ($months + 12);
            }
            else {
                $yr -= 2;
                $mnth += ($months + 24);
            }
            static::assertSame(
                ($yr)
                . '-' . str_pad('' . $mnth, 2, '0', STR_PAD_LEFT)
                . '-' . str_pad('' . ($day), 2, '0', STR_PAD_LEFT),
                $result = (clone $time)->modifyDate(0, $months)->getDateISO()
            );
            $log[] = str_pad('' . $months, 3, ' ', STR_PAD_LEFT) . ': ' . $result;
        }

        $year = 2018;
        $month = 7;
        $day = 1;
        $time = (new Time())->setDate($year, $month, $day);
        $limit = 25;
        $log[] = '';
        $log[] = '     ' . $time->getDateISO();
        for ($months = 1; $months <= $limit; ++$months) {
            $yr = $year;
            $mnth = $month;
            if ($months < 6) {
                $mnth += $months;
            }
            elseif ($months < 18) {
                $yr += 1;
                $mnth += ($months - 12);
            }
            else {
                $yr += 2;
                $mnth += ($months - 24);
            }
            static::assertSame(
                ($yr)
                . '-' . str_pad('' . $mnth, 2, '0', STR_PAD_LEFT)
                . '-' . str_pad('' . ($day), 2, '0', STR_PAD_LEFT),
                $result = (clone $time)->modifyDate(0, $months)->getDateISO()
            );
            $log[] = str_pad('' . $months, 3, ' ', STR_PAD_LEFT) . ': ' . $result;
        }

        $year = 2018;
        $month = 7;
        $day = 1;
        $time = (new Time())->setDate($year, $month, $day);
        $limit = -25;
        $log[] = '';
        $log[] = '     ' . $time->getDateISO();
        for ($months = -1; $months >= $limit; --$months) {
            $yr = $year;
            $mnth = $month;
            if ($months >= -6) {
                $mnth += $months;
            }
            elseif ($months >= -18) {
                $yr -= 1;
                $mnth += ($months + 12);
            }
            else {
                $yr -= 2;
                $mnth += ($months + 24);
            }
            static::assertSame(
                ($yr)
                . '-' . str_pad('' . $mnth, 2, '0', STR_PAD_LEFT)
                . '-' . str_pad('' . ($day), 2, '0', STR_PAD_LEFT),
                $result = (clone $time)->modifyDate(0, $months)->getDateISO()
            );
            $log[] = str_pad('' . $months, 3, ' ', STR_PAD_LEFT) . ': ' . $result;
        }

        TestHelper::log("Time::modifyDate()\n" . join("\n", $log));

        // /Modifying month only.-----------------------------------------------

        // Days only.
        $time = new Time('2018-01-01');
        static::assertSame('2018-01-02', (clone $time)->modifyDate(0, 0, 1)->getDateISO());

        // Last day of February.
        $time = new Time('2018-01-31');
        static::assertSame('2018-02-28', (clone $time)->modifyDate(0, 1)->getDateISO());
        // Leap year last day of February.
        static::assertSame('2020-02-29', (clone $time)->modifyDate(2, 1)->getDateISO());

        // Last day of February.
        $time = new Time('2018-01-01');
        static::assertSame('2018-02-28', (clone $time)->modifyDate(0, 1)->setToLastDayOfMonth()->getDateISO());
        $time = new Time('2018-03-31');
        static::assertSame('2018-02-28', (clone $time)->modifyDate(0, -1)->getDateISO());


        $time = new Time('2018-01-01');
        static::assertSame('2018-02-20', (clone $time)->modifyDate(0, 0, 50)->getDateISO());
    }

    /**
     * @see \SimpleComplex\Utils\Time::modifyTime()
     */
    public function testModifyTime()
    {
        $time = new Time('2018-01-01 15:37:13');
        static::assertSame('2018-01-01 16:38:14', (clone $time)->modifyTime(1, 1, 1)->getDateTimeISO());
        static::assertSame('2018-01-02 16:38:14', (clone $time)->modifyTime(25, 1, 1)->getDateTimeISO());
        static::assertSame('2017-12-31 14:36:12', (clone $time)->modifyTime(-25, -1, -1)->getDateTimeISO());
    }

    /**
     * @see \SimpleComplex\Utils\Time::diffConstant()
     *
     * @expectedException \RuntimeException
     */
    public function testDiffConstant()
    {
        /**
         * \SimpleComplex\Utils\Time::diffConstant()
         *
         * Fixes that native diff()|\DateInterval calculation doesn't work correctly
         * with other timezone than UTC.
         * @see https://bugs.php.net/bug.php?id=52480
         */

        $tz_default = date_default_timezone_get();

        date_default_timezone_set('UTC');
        $first = (new Time('2019-02-01'))->setToDateStart();
        $last = (new Time('2019-03-01'))->setToDateStart();
        static::assertSame(1, $first->diffConstant($last)->totalMonths);

        // This would fail if that bug wasn't handled.
        date_default_timezone_set(BootstrapTest::TIMEZONE);
        $first = (new Time('2019-02-01'))->setToDateStart();
        $last = (new Time('2019-03-01'))->setToDateStart();
        static::assertSame(1, $first->diffConstant($last)->totalMonths);

        // Reset, for posterity.
        date_default_timezone_set($tz_default);

        // When baseline is non-UTC: use verbatim clone.
        $first = (new Time('2019-02-01', new \DateTimeZone(BootstrapTest::TIMEZONE)))->setToDateStart();
        $last = (new Time('2019-03-01', new \DateTimeZone('UTC')))->setToDateStart();
        static::assertSame(1, $first->diffConstant($last, true)->totalMonths);

        // When deviant is non-UTC (and base is UTC), move deviant into UTC.
        $first = (new Time('2019-02-01', new \DateTimeZone('UTC')))->setToDateStart();
        $last = (new Time('2019-03-01', new \DateTimeZone(BootstrapTest::TIMEZONE)))->setToDateStart();
        static::assertSame(0, $first->diffConstant($last, true)->totalMonths);

        /**
         * Throws exception because the two dates don't have the same timezone,
         * and falsy arg $allowUnEqualTimezones.
         * @see \SimpleComplex\Utils\Time::diffConstant()
         */
        static::assertSame(0, $first->diffConstant($last, false)->totalMonths);
    }

    public static function testResolve()
    {
        date_default_timezone_set(BootstrapTest::TIMEZONE);

        $datetime_local = new \DateTime();
        $datetime_utc = $datetime_local->setTimezone(new \DateTimeZone('UTC'));
        $time_local = Time::createFromDateTime($datetime_local);
        $time_utc = Time::createFromDateTime($datetime_utc);
        /*
        TestHelper::logVariable(__FUNCTION__, [
            'time local' => Time::resolve($time_local),
            'time utc, keep foreign' => Time::resolve($time_utc, true),
            'time utc' => Time::resolve($time_utc),
            'datetime local' => Time::resolve($datetime_local),
            'datetime utc, keep foreign' => Time::resolve($datetime_utc, true),
            'datetime utc' => Time::resolve($datetime_utc),
        ]);*/

        $t = Time::resolve(0);
        static::assertInstanceOf(Time::class, $t);
        static::assertSame('1970-01-01T00:00:00Z', $t->toISOUTC());

        $t = Time::resolve(0, true);
        static::assertInstanceOf(Time::class, $t);
        static::assertSame('1970-01-01T00:00:00Z', $t->toISOUTC());

        $t = Time::resolve(-1);
        static::assertInstanceOf(Time::class, $t);
        static::assertSame('1969-12-31T23:59:59Z', $t->toISOUTC());

        $t = Time::resolve($datetime_utc);
        //TestHelper::logVariable(__FUNCTION__, $t);
        static::assertInstanceOf(Time::class, $t);
        static::assertSame($time_local->toISOUTC(), $t->toISOUTC());
        static::assertTrue($t->timezoneIsLocal());

        // Bad URL encoding; + transformed to space.
        $t = Time::resolve('2019-04-05T10:14:47 02:00');
        static::assertInstanceOf(Time::class, $t);
        static::assertSame('2019-04-05T10:14:47+02:00', $t->toISOZonal());
    }
}
