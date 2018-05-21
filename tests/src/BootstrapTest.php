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
use SimpleComplex\Utils\Dependency;
use SimpleComplex\Utils\Bootstrap;

/**
 * @code
 * // CLI, in document root:
 * vendor/bin/phpunit vendor/simplecomplex/utils/tests/src/BootstrapTest.php
 * @endcode
 *
 * @package SimpleComplex\Tests\Database
 */
class BootstrapTest extends TestCase
{
    protected static $booted = false;

    /**
     * Only prepares dependencies at first call.
     *
     * @return ContainerInterface
     */
    public function testDependencies()
    {
        if (!static::$booted) {
            static::$booted = true;
            Bootstrap::prepareDependenciesIfExist();
        }

        $container = Dependency::container();

        $this->assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }
}
