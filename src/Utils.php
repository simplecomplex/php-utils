<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;
use Psr\Log\InvalidArgumentException;
use SimpleComplex\Utils\Exception\ConfigurationException;

/**
 * Various helpers that do not deserve a class of their own.
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
        // Unsure about null ternary ?? for class and instance vars.
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }

    /**
     * Attempts to fetch an object from a dependency container referred
     * in the global scope (as $container or $c).
     *
     * If you want to use Pimple you'll have to extend it so it implements
     * PSR-11 ContainerInterface.
     *
     * @see \Psr\Container\ContainerInterface
     *
     * @param string $id
     * @param string $class
     *
     * @return object|null
     *
     * @throws \InvalidArgumentException
     *      If arg id or class is empty/falsy.
     */
    public static function fetchFromDependencyContainer(string $id, string $class)
    {
        if (!$id || !$class) {
            throw new \InvalidArgumentException('Falsy arg id or class.');
        }
        global $container, $c;
        $cntnr = $container ?? $c;
        if ($cntnr && $cntnr instanceof ContainerInterface && $cntnr->has($id)) {
            $o = $cntnr->get($id);
            if ($o instanceof $class) {
                return $o;
            }
        }
        return null;
    }

    /**
     * Class name without namespace.
     *
     * @param string $className
     *
     * @return string
     */
    public function classUnqualified(string $className) : string
    {
        $pos = strrpos($className, '\\');
        return $pos || $pos === 0 ? substr($className, $pos + 1) : $className;
    }

    /**
     * Handles that one cannot know whether to access a property of container
     * in an array- or object-like manner.
     *
     * At your own risk, does not check arg container's type unless required.
     *
     * @param array|object $container
     * @param int|string $key
     * @param string $containerType
     *      Empty: arg container's type will be checked or guessed.
     *
     * @return bool
     */
    public function containerIsset($container, $key, string $containerType = '') : bool
    {
        $type = $containerType;
        if (!$type) {
            if (is_array($container)) {
                $type = 'array';
            } elseif ($container instanceof \ArrayAccess) {
                $type = 'arrayAccess';
            } else {
                $type = 'object';
            }
        }
        switch ($type) {
            case 'array':
            case 'arrayAccess':
            case \ArrayAccess::class:
                return isset($container[$key]);
        }
        return isset($container->{$key});
    }

    /**
     * Handles that one cannot know whether to access a property of container
     * in an array- or object-like manner.
     *
     * At your own risk, does not check arg container's type unless required.
     *
     * @param array|object $container
     * @param int|string $key
     * @param string $containerType
     *      Empty: arg container's type will be checked or inferred by exclusion.
     *
     * @return mixed|null
     */
    public function containerGetIfSet(/*container*/ $container, $key, string $containerType = '') /*: ?mixed*/
    {
        $type = $containerType;
        if (!$type) {
            if (is_array($container)) {
                $type = 'array';
            } elseif ($container instanceof \ArrayAccess) {
                $type = 'arrayAccess';
            } else {
                $type = 'object';
            }
        }
        switch ($type) {
            case 'array':
            case 'arrayAccess':
            case \ArrayAccess::class:
                return $container[$key] ?? null;
        }
        return $container->{$key} ?? null;
    }

    /**
     * PSR-3 LogLevel doesn't define numeric values of levels,
     * but RFC 5424 'emergency' is 0 and 'debug' is 7.
     *
     * @see \Psr\Log\LogLevel
     *
     * @var array
     */
    const LOG_LEVEL_BY_SEVERITY = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    /**
     * LogLevel word.
     *
     * @param mixed $level
     *      String (word): value as defined by Psr\Log\LogLevel class constants.
     *      Integer|stringed integer: between zero and seven; RFC 5424.
     *
     * @return string
     *      Equivalent to a Psr\Log\LogLevel class constant.
     *
     * @throws \Psr\Log\InvalidArgumentException
     *      Invalid level argument; as proscribed by PSR-3.
     */
    public function logLevelToString($level) : string
    {
        // Support RFC 5424 integer as well as words defined by PSR-3.
        $lvl = '' . $level;

        // RFC 5424 integer.
        if (ctype_digit($lvl)) {
            if ($lvl >= 0 && $lvl < count(static::LOG_LEVEL_BY_SEVERITY)) {
                return static::LOG_LEVEL_BY_SEVERITY[$lvl];
            }
        }
        // Word defined by PSR-3.
        elseif (in_array($lvl, static::LOG_LEVEL_BY_SEVERITY)) {
            return $lvl;
        }

        throw new InvalidArgumentException('Invalid log level argument [' . $level . '].');
    }

    /**
     * RFC 5424 integer.
     *
     * @param mixed $level
     *      String (word): value as defined by Psr\Log\LogLevel class constants.
     *      Integer|stringed integer: between zero and seven; RFC 5424.
     *
     * @return int
     *
     * @throws \Psr\Log\InvalidArgumentException
     *      Invalid level argument; as proscribed by PSR-3.
     */
    public function logLevelToInteger($level) : int
    {
        // Support RFC 5424 integer as well as words defined by PSR-3.
        $lvl = '' . $level;

        if (ctype_digit($lvl)) {
            if ($lvl >= 0 && $lvl < count(static::LOG_LEVEL_BY_SEVERITY)) {
                return (int) $lvl;
            }
        } else {
            $index = array_search($lvl, static::LOG_LEVEL_BY_SEVERITY);
            if ($index !== false) {
                return $index;
            }
        }

        throw new InvalidArgumentException('Invalid log level argument [' . $level . '].');
    }

    /**
     * Resolve path, convert relative (to document root) to absolute path.
     *
     * NB: doesn't check if the resolved absolute path exists and is directory.
     *
     * @param string $relativePath
     *
     * @return string
     *      Absolute path.
     *
     * @throws ConfigurationException
     *      If document root cannot be determined.
     * @throws \LogicException
     *      Algo or configuration error, can't determine whether path is
     *      absolute or relative.
     * @throws \RuntimeException
     *      Path not resolvable to absolute path.
     */
    public function resolvePath(string $relativePath) : string
    {
        $path = $relativePath;
        // Absolute.
        if (
            strpos($path, '/') !== 0
            && (DIRECTORY_SEPARATOR === '/' || strpos($path, ':') !== 1)
        ) {
            // Document root.
            if (!empty($_SERVER['DOCUMENT_ROOT'])) {
                $doc_root = $_SERVER['DOCUMENT_ROOT'];
                if (DIRECTORY_SEPARATOR == '/') {
                    $doc_root = str_replace('\\', '/', $doc_root);
                }
            } elseif (CliEnvironment::cli()) {
                $doc_root = (new CliEnvironment())->documentRoot;
                if (!$doc_root) {
                    throw new ConfigurationException(
                        'Cannot resolve document root, probably no .document_root file in document root.');
                }
            } else {
                throw new ConfigurationException(
                    'Cannot resolve document root, _SERVER[DOCUMENT_ROOT] non-existent or empty.');
            }
            // Relative above document root.
            if (strpos($path, '../') === 0) {
                $path = dirname($doc_root) . substr($path, 2);
            }
            // Relative to self of document root.
            elseif (strpos($path, './') === 0) {
                $path = $doc_root . substr($path, 1);
            }
            else {
                throw new \LogicException(
                    'Algo or configuration error, failed to determine whether path[' . $path
                    . '] is absolute or relative.'
                );
            }
        }
        if (strpos($path, '/./') || strpos($path, '/../')) {
            throw new \RuntimeException('Path doesn\'t resolve to an absolute path[' . $path . ']');
        }

        return $path;
    }

    /**
     * Recursion limiter for ensurePath().
     *
     * @var int
     */
    const ENSURE_PATH_LIMIT = 20;

    /**
     * Recursively ensure that a directory path exists, create if non-existent.
     *
     * Does not resolve /./ nor /../.
     *
     * @param string $absolutePath
     * @param int $mode
     *      Default: user-only read/write/execute.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     *      If arg absolutePath isn't absolute.
     *      If a directory part is . or ..
     *      If arg mode isn't at least 0100.
     * @throws \RuntimeException
     *      If an existing path part is file, not directory.
     *      Failing to create directory.
     *      Failing to chmod directory.
     * @throws \OutOfBoundsException
     *      Exceeded maximum recursion limit, possibly due to too many dir parts
     *      in arg absolutePath.
     * @throws \LogicException
     *      Exceeded maximum recursion limit, positively due to algo error.
     */
    public function ensurePath(string $absolutePath, int $mode = 0700) /*: void*/
    {
        if (strlen($absolutePath) < 2) {
            throw new \InvalidArgumentException(
                'Arg absolutePath cannot be shorter than 2 chars, path[' . $absolutePath . '].'
            );
        }
        // 0100 ~ 64.
        if (!$mode || $mode < 64) {
            throw new \InvalidArgumentException(
                'Arg mode must be positive and consist of leading zero plus minimum 3 digits, mode[' . $mode . '].'
            );
        }
        // Setting mode - chmod'ing - upon directory creation only seems to be
        // necessary when mode is group-write.
        // Group-write is second-to-last octal digit is 7.
        $mode_str = decoct($mode);
        $group_write = $mode_str{strlen($mode_str) - 2} == '7';

        if (!file_exists($absolutePath)) {
            if (DIRECTORY_SEPARATOR == '\\') {
                $path = str_replace('\\', '/', $absolutePath);
                if ($path{1} !== ':') {
                    throw new \InvalidArgumentException(
                        'Arg absolutePath is not absolute, path[' . $absolutePath . '].'
                    );
                }
                $parts = explode('/', $path);
            } else {
                if ($absolutePath{0} !== '/') {
                    throw new \InvalidArgumentException(
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
                    throw new \OutOfBoundsException(
                        'Exceeded maximum path recursion limit[' . static::ENSURE_PATH_LIMIT . '].'
                    );
                }
                $trailing[] = array_pop($parts);
                $existing = join('/', $parts);
            } while (!file_exists($existing));

            if (!is_dir($existing)) {
                throw new \RuntimeException('Ancestor path exists but is not directory[' . $existing . '].');
            }
            do {
                $limit = 0;
                if ((++$limit) > static::ENSURE_PATH_LIMIT) {
                    throw new \LogicException(
                        'Exceeded maximum path recursion limit[' . static::ENSURE_PATH_LIMIT . '].'
                    );
                }
                $dir = array_pop($trailing);
                $existing .= '/' . $dir;
                if ($dir == '.' || $dir == '..') {
                    throw new \InvalidArgumentException(
                        'Arg absolutePath contains . or .. directory part[' . $existing . '].'
                    );
                }
                if (!mkdir($existing, $mode)) {
                    throw new \RuntimeException('Failed to create dir[' . $existing . '].');
                }
                if ($group_write && !chmod($existing, $mode)) {
                    throw new \RuntimeException('Failed to chmod dir[' . $existing . '] to mode[' . $mode . '].');
                }
            } while ($trailing);
        }
        elseif (!is_dir($absolutePath)) {
            throw new \RuntimeException('Path exists but is not directory[' . $absolutePath . '].');
        }
    }

    /**
     * Fixes that native parse_ini_string() doesn't support raw + typed scanning.
     *
     * Inserting values of arbitrary constants into arbitrary variables seem
     * anything but safe; typical old-school PHP idio##.
     *
     * Using native parse_ini_string/parse_ini_file() with anything but
     * INI_SCANNER_RAW also do weird things to characters
     * ?{}|&~![()^"
     * Those characters have - undocumented - 'special meaning'.
     *
     * @see parse_ini_string()
     *
     * @param string $ini
     * @param bool $processSections
     * @param bool $typed
     *      False: like INI_SCANNER_RAW; default.
     *      True: like INI_SCANNER_RAW | INI_SCANNER_TYPED; but without failure.
     *
     * @return array|bool
     *      False on error.
     */
    public function parseIniString(string $ini, bool $processSections = false, bool $typed = false)
    {
        $arr = parse_ini_string($ini, $processSections, INI_SCANNER_RAW);
        if (!$arr && !is_array($arr)) {
            return false;
        }
        if ($typed) {
            $this->typeArrayValues($arr);
        }
        return $arr;
    }

    /**
     * Fixes that native parse_ini_file() doesn't support raw + typed scanning.
     *
     * @see Utils::parseIniString()
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
     * Casts bucket values that are 'null', 'NULL', 'true', 'false', '...numeric',
     * recursively.
     *
     * @param array &$arr
     *      By reference.
     * @param int $depth
     *
     * @return void
     *
     * @throws \OutOfBoundsException
     *      Exceeded recursion limit.
     */
    protected function typeArrayValues(array &$arr, int $depth = 0) /*: void*/
    {
        if ($depth > static::ARRAY_RECURSION_LIMIT) {
            throw new \OutOfBoundsException(
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
                                $sign = 1;
                                if ($val < 0) {
                                    $sign = -1;
                                    $val = substr('' . $val, 1);
                                }
                                $val = $sign * (ctype_digit('' . $val) ? (int) $val : (float) $val);
                            }
                    }
                }
            }
        }
        unset($val);
    }

    /**
     * Convert array or object to ini-file formatted string.
     *
     * @param array|object $container
     * @param bool $useSections
     *
     * @return string
     *
     * @throws \TypeError
     *      Arg container isn't array|object.
     */
    public function containerToIniString($container, bool $useSections = false) : string
    {
        // PHP <7.1.
        if (!is_array($container) && !is_object($container)) {
            throw new \TypeError(
                'Arg container type[' . (!is_object($container) ? gettype($container) : get_class($container))
                . '] is not array|object.'
            );
        }

        if (!$useSections) {
            return $this->containerToIniRecursive($container);
        }
        $buffer = '';
        foreach ($container as $section => $children) {
            $buffer .= '[' . $section . ']' . "\n";
            foreach ($children as $values) {
                $buffer .= $this->containerToIniRecursive($values);
            }
            $buffer .= "\n";
        }
        return $buffer;
    }

    /**
     * @param array|object $container
     * @param string|int|null $parentKey
     *
     * @return string
     *
     * @throws \OutOfBoundsException
     *      The ini format only supports two layers below sections.
     * @throws \InvalidArgumentException
     *      A bucket value isn't scalar, array|object or null.
     */
    protected function containerToIniRecursive($container, $parentKey = null) : string
    {
        $already_child = $parentKey !== null;
        $buffer = '';
        foreach ($container as $key => $val) {
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
                        throw new \OutOfBoundsException(
                            'Ini format only supports two layers below section, container bucket['
                            . $key . '] type[' . $type . '] should be scalar or null.'
                        );
                    }
                    $buffer .= $this->containerToIniRecursive($val, $key);
                    continue 2;
                case 'object':
                    if (!is_a($val, \Traversable::class)) {
                        throw new \InvalidArgumentException(
                            'Container bucket[' . $key . '] type[' . get_class($val) . '] is not supported.'
                        );
                    }
                    if ($already_child) {
                        throw new \OutOfBoundsException(
                            'Ini format only supports two layers below section, container bucket['
                            . $key . '] type[' . get_class($val) . '] should be scalar or null.'
                        );
                    }
                    $buffer .= $this->containerToIniRecursive($val, $key);
                    continue 2;
                default:
                    throw new \InvalidArgumentException(
                        'Container bucket[' . $key . '] type[' . $type . '] is not supported.'
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
