<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use Psr\Log\LogLevel;
use Psr\Log\InvalidArgumentException;
use SimpleComplex\Utils\Exception\ConfigurationException;
use SimpleComplex\Utils\Exception\ParseIniException;

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
                $path = $doc_root . '/' . $path;
            }
        }
        if (strpos($path, '/./') || strpos($path, '/../')) {
            throw new \RuntimeException('Path doesn\'t resolve to an absolute path[' . $path . ']');
        }

        return $path;
    }

    /**
     * Check if a (file or directory) file mode is group-write.
     *
     * Group-write apparantly requires chmod'ing upon dir/file creation.
     *
     * Does not check if user- or other-write.
     *
     * @param int $fileMode
     *      Use leading zero.
     *
     * @return bool
     */
    public function isFileGroupWrite(int $fileMode)
    {
        $mode_str = decoct($fileMode);
        $group = $mode_str{strlen($mode_str) - 2};
        switch ($group) {
            case '2':
                // write.
            case '3':
                // write + execute.
            case '6':
                // write + read.
            case '7':
                // all.
                return true;
        }
        return false;
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
        $group_write = $this->isFileGroupWrite($mode);

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
     * Ini parsing: regex of 'special' keys that must be escaped before parsing.
     *
     * @see Utils::escapeIniKeys()
     *
     * @var array
     */
    const PARSE_INI_ESCAPE_KEYS = [
        '/\n(null|yes|no|true|false|on|off|none) =/m',
        "\n-" . '$1- =',
    ];

    /**
     * Ini parsing: list of 'special' keys that must be unescaped after parsing.
     *
     * @see Utils::unescapeIniKeys()
     *
     * @var array
     */
    const PARSE_INI_UNESCAPE_KEYS = [
        '-null-',
        '-yes-',
        '-no-',
        '-true-',
        '-false-',
        '-on-',
        '-off-',
        '-none-',
    ];

    /**
     * Escape .ini 'special' keys.
     *
     * @param string $ini
     *
     * @return string
     */
    public function escapeIniKeys(string $ini)
    {
        if ($ini) {
            return '' . preg_replace(static::PARSE_INI_ESCAPE_KEYS[0], static::PARSE_INI_ESCAPE_KEYS[1], "\n" . $ini);
        }
        return '';
    }

    /**
     * Unescapce .ini 'special' keys.
     *
     * @param array $ini
     * @param bool $sectioned
     */
    public function unescapeIniKeys(array &$ini, $sectioned = false)
    {
        if ($ini) {
            $unescapes = static::PARSE_INI_UNESCAPE_KEYS;
            if (!$sectioned) {
                foreach ($ini as $key => $value) {
                    if (in_array($key, $unescapes)) {
                        unset($ini[$key]);
                        $ini[str_replace('-', '', $key)] = $value;
                    }
                }
            } else {
                foreach ($ini as &$section) {
                    foreach ($section as $key => $value) {
                        if (in_array($key, $unescapes)) {
                            unset($section[$key]);
                            $section[str_replace('-', '', $key)] = $value;
                        }
                    }
                }
                unset($section);
            }
        }
    }

    /**
     * List of needles and replacers for parseIniString/parseIniFile().
     *
     * @see Utils::parseIniString()
     *
     * @var array
     */
    const PARSE_INI_REPLACE = [
        '\\n' => "\n",
    ];

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
     * @return array
     *
     * @throws ParseIniException
     *      On parser failure.
     */
    public function parseIniString(string $ini, bool $processSections = false, bool $typed = false) : array
    {
        if (!$ini) {
            return [];
        }
        // Suppress PHP error; wrongly reported as syntax warning.
        $arr = @parse_ini_string($ini, $processSections, INI_SCANNER_RAW);
        if (!is_array($arr)) {
            // Check if there's an unescaped 'special' key. We cannot know here
            // if the content was escaped; via Utils::escapeIniKeys().
            /**
             * @see Utils::escapeIniKeys()
             */
            if (preg_match(static::PARSE_INI_ESCAPE_KEYS[0], "\n" . $ini)) {
                throw new ParseIniException(
                    'Ini content contains unescaped \'special\' key name, one of: '
                    . str_replace('-', '', join('|', static::PARSE_INI_UNESCAPE_KEYS)) . '.'
                );
            }
            throw new ParseIniException('Failed parsing ini content.');
        }
        if ($arr && $typed) {
            $this->typeArrayValues($arr, static::PARSE_INI_REPLACE);
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
     * @return array
     *
     * @throws \RuntimeException
     *      If the file non-existent or not file, or reading the file fails.
     * @throws ParseIniException
     *      Propagated, see parseIniString().
     */
    public function parseIniFile(string $filename, bool $processSections = false, bool $typed = false) : array
    {
        $ini = file_get_contents($filename);
        if (!$ini) {
            if ($ini === false) {
                if (!file_exists($filename)) {
                    throw new \RuntimeException('File not found, file[' . $filename . '].');
                }
                if (!is_file($filename)) {
                    throw new \RuntimeException('Not a file, file[' . $filename . '].');
                }
                throw new \RuntimeException('Failed reading file[' . $filename . '].');
            }
            return [];
        }
        return $this->parseIniString($ini, $processSections, $typed);
    }

    /**
     * @var int
     */
    const ARRAY_RECURSION_LIMIT = 10;

    /**
     * Casts bucket values that are 'null', 'true', 'false', '...numeric',
     * and replaces by arg stringReplace in strings; recursively.
     *
     * @param array &$arr
     *      By reference.
     * @param array $stringReplace
     *      List of needles and replacers; for str_replace().
     * @param int $depth
     *
     * @return void
     *
     * @throws \OutOfBoundsException
     *      Exceeded recursion limit.
     */
    protected function typeArrayValues(array &$arr, array $stringReplace = [], int $depth = 0) /*: void*/
    {
        if ($depth > static::ARRAY_RECURSION_LIMIT) {
            throw new \OutOfBoundsException(
                'Stopped recursive typing of array values at limit[' . static::ARRAY_RECURSION_LIMIT . '].'
            );
        }
        foreach ($arr as &$val) {
            if ($val !== '') {
                if (is_array($val)) {
                    $this->typeArrayValues($val, $stringReplace, $depth + 1);
                } else {
                    switch ('' . $val) {
                        case 'null':
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
                            } elseif ($stringReplace) {
                                foreach ($stringReplace as $needle => $replacer) {
                                    $val = str_replace($needle, $replacer, $val);
                                }
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
