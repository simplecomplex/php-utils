<?php

namespace SimpleComplex\Utils\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * @package SimpleComplex\Utils
 */
class ContainerNotFoundException extends \RuntimeException implements NotFoundExceptionInterface
{

}
