<?php

namespace SimpleComplex\Utils\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * @package SimpleComplex\Utils
 */
class ContainerInvalidArgumentException extends \InvalidArgumentException implements ContainerExceptionInterface
{

}
