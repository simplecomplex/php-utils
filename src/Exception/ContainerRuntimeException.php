<?php

namespace SimpleComplex\Utils\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * @see \SimpleComplex\Utils\Dependency::set()
 *
 * @package SimpleComplex\Utils
 */
class ContainerRuntimeException extends \RuntimeException implements ContainerExceptionInterface
{

}
