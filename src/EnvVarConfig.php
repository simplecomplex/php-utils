<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use Psr\SimpleCache\CacheInterface;
use SimpleComplex\Utils\Exception\ConfigInvalidArgumentException;

/**
 * Environment variable based configuration, implementing PSR Simple Cache
 * interface.
 *
 * @package SimpleComplex\Utils
 */
class EnvVarConfig implements CacheInterface, ConfigDomainDelimiterInterface
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var EnvVarConfig
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return EnvVarConfig
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }


    // CacheInterface.----------------------------------------------------------

    /**
     * Fetches an environment variable.
     *
     * @throws ConfigInvalidArgumentException
     *      Propagated. Implements \Psr\SimpleCache\InvalidArgumentException.
     *
     * @param mixed $key
     *      Gets stringified.
     * @param mixed $default
     *
     * @return mixed|null
     *      Environment vars are always string.
     *      The default may be of any type.
     */
    public function get($key, $default = null)
    {
        $k = $this->keyConvert($key);
        $v = getenv($k);
        return $v !== false ? $v : $default;
    }

    /**
     * Does nothing at all; setting/overwriting an environment var could have
     * security implications and/or result in peculiar errors.
     *
     * @param mixed $key
     * @param mixed $value
     * @param null|int|\DateInterval $ttl
     *
     * @return bool
     *      Always true.
     */
    public function set($key, $value, $ttl = null) : bool
    {
        return true;
    }

    /**
     * Does nothing at all; setting/overwriting an environment var could have
     * security implications and/or result in peculiar errors.
     *
     * @param mixed $key
     *
     * @return bool
     *      Always true.
     */
    public function delete($key) : bool
    {
        return true;
    }

    /**
     * Does nothing at all.
     *
     * @return bool
     *      Always true.
     */
    public function clear() : bool
    {
        return true;
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param array|\Traversable $keys
     * @param mixed $default
     *
     * @return array
     */
    public function getMultiple(/*iterable*/ $keys, $default = null) : array
    {
        if (!is_array($keys) && !is_a($keys, \Traversable::class)) {
            throw new ConfigInvalidArgumentException('Arg keys is not iterable.');
        }
        $values = [];
        foreach ($keys as $k) {
            $values[$k] = $this->get($k, $default);
        }
        return $values;
    }

    /**
     * Does nothing at all.
     *
     * @param array|\Traversable $values
     * @param null|int|\DateInterval $ttl
     *
     * @return bool
     *      Always true.
     */
    public function setMultiple(/*iterable*/ $values, $ttl = null) : bool
    {
        return true;
    }

    /**
     * Does nothing at all.
     *
     * @param array|\Traversable $keys
     *
     * @return bool
     *      Always true.
     */
    public function deleteMultiple($keys) : bool
    {
        return true;
    }

    /**
     * Check if an environment var is set.
     *
     * @param mixed $key
     *      Gets stringified
     *
     * @return bool
     */
    public function has($key) : bool
    {
        $k = $this->keyConvert($key);
        return getenv($k) !== false;
    }


    // Custom/business.---------------------------------------------------------

    /**
     * For domain:key namespaced use. Delimiter between domain and key.
     */
    const KEY_DOMAIN_DELIMITER = '__';

    /**
     * @return string
     */
    public function keyDomainDelimiter() : string {
        return static::KEY_DOMAIN_DELIMITER;
    }

    /**
     * Legal non-alphanumeric characters of a key.
     *
     * These keys are selected because they would work in the most basic cache
     * implementation; that is: file (dir names and filenames).
     */
    const KEY_VALID_NON_ALPHANUM = [
        '(',
        ')',
        '-',
        '.',
        ':',
        '[',
        ']',
        '_'
    ];

    /**
     * Checks that stringified key is non-empty and only contains legal chars.
     *
     * @param string $key
     *
     * @return bool
     */
    public function keyValidate(string $key) : bool
    {
        if (!$key && $key === '') {
            return false;
        }
        // Faster than a regular expression.
        return !!ctype_alnum('A' . str_replace(static::KEY_VALID_NON_ALPHANUM, '', $key));
    }

    /**
     * Replaces all legal non-alphanumeric chars with underscore.
     *
     * @throws ConfigInvalidArgumentException
     *
     * @param string $key
     *
     * @return string
     */
    public function keyConvert(string $key) : string
    {
        if (!$key && $key === '') {
            throw new ConfigInvalidArgumentException('Arg key is empty.');
        }
        $key = str_replace(static::KEY_VALID_NON_ALPHANUM, '_', $key);
        if (!ctype_alnum(str_replace('_', '', $key))) {
            throw new ConfigInvalidArgumentException('Arg key contains invalid character(s).');
        }
        return $key;
    }
}
