<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use SimpleComplex\Utils\Exception\InvalidArgumentException;
use SimpleComplex\Utils\Exception\LogicException;
use SimpleComplex\Utils\Exception\OutOfBoundsException;
use SimpleComplex\Utils\Exception\RuntimeException;

/**
 * Various helpers that to not deserve a class of their own.
 *
 * @package SimpleComplex\Utils
 */
class Utils
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var Utils
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return Utils
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }

    /**
     * is_iterable() for PHP <7.1.
     *
     * @param $var
     *
     * @return bool
     */
    public function isIterable($var)
    {
        return is_array($var) || is_a($var, \Traversable::class);
    }

    /**
     * Recursion limiter for ensurePath().
     *
     * @var int
     */
    const ENSURE_PATH_LIMIT = 20;

    /**
     * Ensure that a directory path exists, create and set mode recursively
     * if non-existent.
     *
     * Does not resolve /./ nor /../.
     *
     * @param string $absolutePath
     * @param int $mode
     *      Default: user-only read/write/execute.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *      If arg absolutePath isn't absolute.
     *      If a directory part is . or ..
     * @throws RuntimeException
     *      If an existing path part is file, not directory.
     *      Failing to create directory.
     *      Failing to chmod directory.
     * @throws OutOfBoundsException
     *      Exceeded maximum recursion limit, possibly due to too many dir parts
     *      in arg absolutePath.
     * @throws LogicException
     *      Exceeded maximum recursion limit, positively due to algo error.
     */
    public function ensurePath(string $absolutePath, int $mode = 0700) /*:void*/
    {
        if (strlen($absolutePath) < 2) {
            throw new InvalidArgumentException(
                'Arg absolutePath cannot be shorter than 2 chars, path[' . $absolutePath . '].'
            );
        }
        if (!file_exists($absolutePath)) {
            if (DIRECTORY_SEPARATOR == '\\') {
                $path = str_replace('\\', '/', $absolutePath);
                if ($path{1} !== ':') {
                    throw new InvalidArgumentException(
                        'Arg absolutePath is not absolute, path[' . $absolutePath . '].'
                    );
                }
                $parts = explode('/', $path);
            } else {
                if ($absolutePath{0} !== '/') {
                    throw new InvalidArgumentException(
                        'Arg absolutePath is not absolute, path[' . $absolutePath . '].'
                    );
                }
                $parts = explode('/', $absolutePath);
                // Remove first empty bucket; before root slash.
                array_shift($parts);
                $parts[0] = '/' . $parts[0];
            }

            $trailing = [];
            $limit = 0;
            do {
                if ((++$limit) > static::ENSURE_PATH_LIMIT) {
                    throw new OutOfBoundsException(
                        'Exceeded maximum path recursion limit[' . static::ENSURE_PATH_LIMIT . '].'
                    );
                }
                $trailing[] = array_pop($parts);
                $existing = join('/', $parts);
            } while (!file_exists($existing));
            
            if (!is_dir($existing)) {
                throw new RuntimeException('Ancestor path exists but is not directory[' . $existing . '].');
            }
            do {
                $limit = 0;
                if ((++$limit) > static::ENSURE_PATH_LIMIT) {
                    throw new LogicException(
                        'Exceeded maximum path recursion limit[' . static::ENSURE_PATH_LIMIT . '].'
                    );
                }
                $dir = array_pop($trailing);
                $existing .= '/' . $dir;
                if ($dir == '.' || $dir == '..') {
                    throw new InvalidArgumentException(
                        'Arg absolutePath contains . or .. directory part[' . $existing . '].'
                    );
                }
                if (!mkdir($existing, $mode)) {
                    throw new RuntimeException('Failed to create dir[' . $existing . '].');
                }
                if (!chmod($existing, $mode)) {
                    throw new RuntimeException('Failed to chmod dir[' . $existing . '] to mode[' . $mode . '].');
                }
            } while ($trailing);
        }
        elseif (!is_dir($absolutePath)) {
            throw new RuntimeException('Path exists but is not directory[' . $absolutePath . '].');
        }
    }

    /**
     * Fixes that native parse_ini_file() doesn't support raw + typed scanning.
     *
     * Inserting values of arbitrary constants into arbitrary variables seem
     * anything but safe; typical old-school PHP idio##.
     *
     * Using native parse_ini_string/parse_ini_file() with anything but
     * INI_SCANNER_RAW also do weird things to characters
     * ?{}|&~![()^"
     * Those characters have - undocumented - 'special meaning'.
     *
     * @see parse_ini_file()
     *
     * @param string $filename
     * @param bool $processSections
     * @param bool $typed
     *      False: like INI_SCANNER_RAW; default.
     *      True: like INI_SCANNER_RAW | INI_SCANNER_TYPED; but without failure.
     *
     * @return array|bool
     *      False on error.
     */
    public function parseIniFile(string $filename, bool $processSections = false, bool $typed = false)
    {
        $arr = parse_ini_file($filename, $processSections, INI_SCANNER_RAW);
        if (!$arr && !is_array($arr)) {
            return false;
        }
        if ($typed) {
            $this->typeArrayValues($arr);
        }
        return $arr;
    }

    /**
     * @var int
     */
    const ARRAY_RECURSION_LIMIT = 10;

    /**
     * Casts bucket values that are 'null', 'NULL', 'true', 'false', 'numeric',
     * recursively.
     *
     * @param array &$arr
     *      By reference.
     * @param int $depth
     *
     * @return void
     *
     * @throws OutOfBoundsException
     *      Exceeded recursion limit.
     */
    protected function typeArrayValues(array &$arr, int $depth = 0) /*:void*/
    {
        if ($depth > static::ARRAY_RECURSION_LIMIT) {
            throw new OutOfBoundsException(
                'Stopped recursive typing of array values at limit[' . static::ARRAY_RECURSION_LIMIT . '].'
            );
        }
        foreach ($arr as &$val) {
            if ($val !== '') {
                if (is_array($val)) {
                    $this->typeArrayValues($val, $depth + 1);
                } else {
                    switch ('' . $val) {
                        case 'null':
                        case 'NULL':
                            $val = null;
                            break;
                        case 'true':
                            $val = true;
                            break;
                        case 'false':
                            $val = false;
                            break;
                        default:
                            if (is_numeric($val)) {
                                $val = ctype_digit($val) ? (int) $val : (float) $val;
                            }
                    }
                }
            }
        }
        unset($val);
    }

    /**
     * Convert array or iterable object to ini-file formatted string.
     *
     * @param iterable $collection
     * @param bool $useSections
     *
     * @return string
     *
     * @throws \TypeError
     *      Arg collection isn't iterable.
     */
    public function iterableToIniString(/*iterable*/ $collection, bool $useSections = false) : string
    {
        // PHP <7.1.
        if (!$this->isIterable($collection)) {
            throw new \TypeError(
                'Arg collection type[' . (!is_object($collection) ? gettype($collection) : get_class($collection))
                . '] is not iterable.'
            );
        }

        if (!$useSections) {
            return $this->iterableToIniRecursive($collection);
        }
        $buffer = '';
        foreach ($collection as $section => $children) {
            $buffer .= '[' . $section . ']' . "\n";
            foreach ($children as $values) {
                $buffer .= $this->iterableToIniRecursive($values);
            }
            $buffer .= "\n";
        }
        return $buffer;
    }

    /**
     * @param iterable $collection
     * @param string|int|null $parentKey
     *
     * @return string
     *
     * @throws OutOfBoundsException
     *      The ini format only supports two layers below sections.
     * @throws InvalidArgumentException
     *      A bucket value isn't scalar, iterable or null.
     */
    protected function iterableToIniRecursive(/*iterable*/ $collection, $parentKey = null) : string
    {
        $already_child = $parentKey !== null;
        $buffer = '';
        foreach ($collection as $key => $val) {
            $type = gettype($val);
            switch ($type) {
                case 'boolean':
                    $v = !$val ? 'false' : 'true';
                    break;
                case 'integer':
                case 'double':
                case 'float':
                    $v = '' . $val;
                    break;
                case 'string':
                    $v = $val;
                    break;
                case 'null':
                case 'NULL':
                    $v = 'null';
                    break;
                case 'array':
                    if ($already_child) {
                        throw new OutOfBoundsException(
                            'Ini format only supports two layers below section, iterable bucket['
                            . $key . '] type[' . $type . '] should be scalar or null.'
                        );
                    }
                    $buffer .= $this->iterableToIniRecursive($val, $key);
                    continue 2;
                case 'object':
                    if (!is_a($val, \Traversable::class)) {
                        throw new InvalidArgumentException(
                            'Iterable bucket[' . $key . '] type[' . get_class($val) . '] is not supported.'
                        );
                    }
                    if ($already_child) {
                        throw new OutOfBoundsException(
                            'Ini format only supports two layers below section, iterable bucket['
                            . $key . '] type[' . get_class($val) . '] should be scalar or null.'
                        );
                    }
                    $buffer .= $this->iterableToIniRecursive($val, $key);
                    continue 2;
                default:
                    throw new InvalidArgumentException(
                        'Iterable bucket[' . $key . '] type[' . $type . '] is not supported.'
                    );
            }
            if (!$already_child) {
                $buffer .= $key . ' = ' . $v . "\n";
            } else {
                $buffer .= $parentKey . '[' . $key . '] = ' . $v . "\n";
            }
        }
        return $buffer;
    }

}
