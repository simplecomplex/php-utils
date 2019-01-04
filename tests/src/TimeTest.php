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
        $this->assertInstanceOf(ContainerInterface::class, $container);
    }

    /**
     * @see \SimpleComplex\Utils\Time::validateTimezoneDefault()
     *
     * @expectedException \LogicException
     */
    public function testValidateTimezoneDefault()
    {
        $timezone_default = date_default_timezone_get();
        $this->assertTrue(Time::checkTimezoneDefault($timezone_default));

        $offset_default = (new Time())->getOffset();
        $this->assertInternalType('int', $offset_default);
        if (!$offset_default) {
            $this->assertTrue(Time::checkTimezoneDefault('UTC'));
            $this->assertFalse(Time::checkTimezoneDefault('Europe/Copenhagen'));
            /**
             * @throws \LogicException
             */
            Time::checkTimezoneDefault('Europe/Copenhagen', true);
        } else {
            $this->assertFalse(Time::checkTimezoneDefault('UTC'));
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
        $this->assertSame('2018-01-01', (clone $time)->modifyDate(0, 0)->getDateISO());
        $this->assertSame('2019-02-02', (clone $time)->modifyDate($years, $months, $days)->getDateISO());
        // 2017-01-01
        // 2016-12-01
        // 2016-11-30
        $this->assertSame('2016-11-30', (clone $time)->modifyDate(-$years, -$months, -$days)->getDateISO());

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
            $this->assertSame(
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
            $this->assertSame(
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
            $this->assertSame(
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
            $this->assertSame(
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
        $this->assertSame('2018-01-02', (clone $time)->modifyDate(0, 0, 1)->getDateISO());

        // Last day of February.
        $time = new Time('2018-01-31');
        $this->assertSame('2018-02-28', (clone $time)->modifyDate(0, 1)->getDateISO());
        // Leap year last day of February.
        $this->assertSame('2020-02-29', (clone $time)->modifyDate(2, 1)->getDateISO());

        // Last day of February.
        $time = new Time('2018-01-01');
        $this->assertSame('2018-02-28', (clone $time)->modifyDate(0, 1)->setToLastDayOfMonth()->getDateISO());
        $time = new Time('2018-03-31');
        $this->assertSame('2018-02-28', (clone $time)->modifyDate(0, -1)->getDateISO());


        $time = new Time('2018-01-01');
        $this->assertSame('2018-02-20', (clone $time)->modifyDate(0, 0, 50)->getDateISO());
    }

    /**
     * @see \SimpleComplex\Utils\Time::modifyTime()
     */
    public function testModifyTime()
    {
        $time = new Time('2018-01-01 15:37:13');
        $this->assertSame('2018-01-01 16:38:14', (clone $time)->modifyTime(1, 1, 1)->getDateTimeISO());
        $this->assertSame('2018-01-02 16:38:14', (clone $time)->modifyTime(25, 1, 1)->getDateTimeISO());
        $this->assertSame('2017-12-31 14:36:12', (clone $time)->modifyTime(-25, -1, -1)->getDateTimeISO());
    }
}
