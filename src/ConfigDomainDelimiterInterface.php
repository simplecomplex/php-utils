<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */

namespace SimpleComplex\Utils;

/**
 * For domain:key namespaced use.
 *
 * @package SimpleComplex\Utils
 */
interface ConfigDomainDelimiterInterface
{
    /**
     * For domain:key namespaced use. Delimiter between domain and key.
     *
     * @return string
     */
    public function keyDomainDelimiter() : string;
}
