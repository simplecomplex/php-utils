<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use Psr\Log\LogLevel;
use Psr\Log\InvalidArgumentException;
use SimpleComplex\Utils\Exception\ConfigurationException;
use SimpleComplex\Utils\Exception\KeyNonUniqueException;
use SimpleComplex\Utils\Exception\ParseIniException;
use SimpleComplex\Utils\Exception\ParseJsonException;

/**
 * Various helpers that do not deserve a class of their own.
 *
 * @package SimpleComplex\Utils
 */
class Utils
{
    /**
     * Posix compliant (non-Windows) file system.
     *
     * @var bool
     */
    const FILE_SYSTEM_POSIX = DIRECTORY_SEPARATOR == '/';

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
     * Get subject class name or (non-object) type.
     *
     * Counter to native gettype() this method returns:
     * - class name instead of 'object'
     * - 'float' instead of 'double'
     * - 'null' instead of 'NULL'
     *
     * Like native gettype() this method returns:
     * - 'boolean' not 'bool'
     * - 'integer' not 'int'
     * - 'unknown type' for unknown type
     *
     * @param mixed $subject
     *
     * @return string
     */
    public static function getType($subject)
    {
        if (!is_object($subject)) {
            $type = gettype($subject);
            switch ($type) {
                case 'double':
                    return 'float';
                case 'NULL':
                    return 'null';
                default:
                    return $type;
            }
        }
        return get_class($subject);
    }

    /**
     * Cast object or array to a class.
     *
     * Does not handle protected properties of neither arg (object) subject
     * nor final class instance.
     *
     * Does not support toClassName class whose constructor
     * has required parameter(s).
     *
     * @param object|array &$subject
     *      By reference.
     * @param string $toClassName
     *
     * @throws \TypeError
     *      Arg subject is not object|array.
     *      Arg subject is object whose class isn't a parent of arg toClassName.
     *      Propagated; toClassName class constructor has required parameter(s).
     */
    public static function cast(&$subject, string $toClassName) /*:void*/
    {
        if (is_object($subject)) {
            $from_class_name = get_class($subject);
            if ($from_class_name != \stdClass::class && !in_array($from_class_name, class_parents($toClassName))) {
                throw new \TypeError(
                    'Can\'t cast arg subject, class[' . $from_class_name
                    . '] is not parent class of arg toClassName[' . $toClassName . '].'
                );
            }
            $source_props = get_object_vars($subject);
        } elseif (!is_array($subject)) {
            throw new \TypeError('Arg subject type[' . static::getType($subject) . '] is not object or array.');
        } else {
            // Copy.
            $source_props = $subject;
        }
        // would break if subject is array and argument wasn't by reference.
        $subject = new $toClassName();
        foreach ($source_props as $key => &$value) {
            $subject->{$key} = $value;
        }
        // Iteration ref.
        unset($value);
    }

    /**
     * @param string $action
     *      Values: has|set|remove.
     * @param int $mask
     * @param int $subject
     *
     * @return int
     *      If action 'has': subject if tests positive, otherwise zero.
     *      Otherwise final mask.
     *
     * @throws \InvalidArgumentException
     *      Arg action not supported.
     *      Arg mask or subject negative.
     */
    public static function bitMask(string $action, int $mask, int $subject) : int
    {
        if ($mask < 0) {
            throw new \InvalidArgumentException('Arg mask[' . $mask . '] cannot be negative.');
        }
        if ($subject < 0) {
            throw new \InvalidArgumentException('Arg subject[' . $subject . '] cannot be negative.');
        }
        switch ($action) {
            case 'has':
                // Testing for == subject is probably redundant, but anyway.
                return ($mask & $subject) == $subject ? $subject : 0;
            case 'set':
                return ($mask | $subject);
            case 'remove':
                return ($mask & ~$subject);
        }
        throw new \InvalidArgumentException('Arg action[' . $action . '] not supported.');
    }

    /**
     * Class name without namespace.
     *
     * @param string $className
     *
     * @return string
     */
    public static function classUnqualified(string $className) : string
    {
        $pos = strrpos($className, '\\');
        return $pos || $pos === 0 ? substr($className, $pos + 1) : $className;
    }

    /**
     * Merge arrays recursively, letting numerically indexed key values append
     * and dupe associative key values overwrite.
     *
     * Native array_replace[_recursive]():
     * - values of dupe keys always overwrite;
     *   no matter if numeric or accociative keys
     *
     * Native array_merge[_recursive]():
     * - values of dupe numeric keys get appended
     * - values of dupe accociative keys get dumped into sub array;
     *   in effect converting duplicate key values to array (sic!)
     *
     * @see array_replace_recursive()
     * @see array_merge_recursive()
     *
     * @param array $array0
     * @param array ...$arrayN
     *      Any number of arguments.
     *
     * @return array
     */
    function arrayMergeRecursive(array $array0, array ...$arrayN)
    {
        foreach ($arrayN as $arr) {
            if (!$array0) {
                // If first arg empty, use first of later args.
                $array0 = $arr;
            }
            elseif ($arr) {
                foreach ($arr as $key => $value) {
                    // Dupe key.
                    if (array_key_exists($key, $array0)) {
                        // Both values array: recurse.
                        if (is_array($value) && is_array($array0[$key])) {
                            $array0[$key] = $this->arrayMergeRecursive($array0[$key], $value);
                        }
                        // Numeric key: append.
                        elseif (!$key || ctype_digit('' . $key)) {
                            $array0[] = $value;
                        }
                        // Associative key: overwrite.
                        else {
                            $array0[$key] = $value;
                        }
                    }
                    // Non-dupe key: append.
                    else {
                        $array0[$key] = $value;
                    }
                }
            }
        }
        return $array0;
    }

    /**
     * Merge arrays recursively, letting numerically indexed key values append
     * and err on dupe associative key where original and candidate both have
     * non-array value.
     *
     * @see Utils::arrayMergeRecursive()
     *
     * @param array $array0
     * @param array ...$arrayN
     *      Any number of arguments.
     *
     * @return array
     *
     * @throws KeyNonUniqueException
     *      On non-unique associative key and both values aren't array.
     */
    function arrayMergeUniqueRecursive(array $array0, array ...$arrayN)
    {
        foreach ($arrayN as $arr) {
            if (!$array0) {
                // If first arg empty, use first of later args.
                $array0 = $arr;
            }
            elseif ($arr) {
                foreach ($arr as $key => $value) {
                    // Dupe key.
                    if (array_key_exists($key, $array0)) {
                        // Both values array: recurse.
                        if (is_array($value) && is_array($array0[$key])) {
                            $array0[$key] = $this->arrayMergeUniqueRecursive($array0[$key], $value);
                        }
                        // Numeric key: append.
                        elseif (!$key || ctype_digit('' . $key)) {
                            $array0[] = $value;
                        }
                        // Associative key and both non-array: err.
                        else {
                            throw new KeyNonUniqueException(
                                'Non-unique associative key[' . $key . '] having non-array value found, first is type['
                                . static::getType($array0[$key]) . ']'
                                . (
                                    $array0[$key] === null ? ' value[null]' : (
                                        is_scalar($array0[$key]) ? (' value[' . $array0[$key] . ']') : ''
                                    )
                                )
                                . ', second is type[' . static::getType($value) . ']'
                                . (
                                    $value === null ? ' value[null]' : (
                                        is_scalar($value) ? (' value[' . $value . ']') : ''
                                    )
                                )
                                . '.'
                            );
                        }
                    }
                    // Non-dupe key: append.
                    else {
                        $array0[$key] = $value;
                    }
                }
            }
        }
        return $array0;
    }

    /**
     * Check if array or object has a key.
     *
     * @param array|object $container
     * @param int|string $key
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     *      Arg container isn't array|object.
     */
    public function containerIsset($container, $key) : bool
    {
        if (is_array($container)) {
            return isset($container[$key]);
        }
        if (is_object($container)) {
            return $container instanceof \ArrayAccess ? $container->offsetExists($key) : isset($container->key);
        }
        throw new \InvalidArgumentException(
            'Arg container type[' . static::getType($container) . '] is not array or object.'
        );
    }

    /**
     * Get value by key of an array or object.
     *
     * @param array|object $container
     * @param int|string $key
     *
     * @return mixed|null
     *
     * @throws \InvalidArgumentException
     *      Arg container isn't array|object.
     */
    public function containerGetIfSet($container, $key) /*: ?mixed*/
    {
        if (is_array($container)) {
            return $container[$key] ?? null;
        }
        if (is_object($container)) {
            return $container instanceof \ArrayAccess ? ($container->offsetExists($key) ? $container[$key] : null) :
                ($container->key ?? null);
        }
        throw new \InvalidArgumentException(
            'Arg container type[' . static::getType($container) . '] is not array or object.'
        );
    }

    /**
     * Get list of keys of an array or object.
     *
     * Non-Traversable ArrayAccess is not a valid container in this context.
     *
     * @param array|object $container
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     *      Arg container isn't array|object or is non-Traversable ArrayAccess.
     */
    public function containerKeys($container)
    {
        if (is_array($container)) {
            return array_keys($container);
        }
        if (is_object($container)) {
            if ($container instanceof \Traversable) {
                if ($container instanceof \ArrayObject || $container instanceof \ArrayIterator) {
                    return array_keys($container->getArrayCopy());
                } else {
                    // Have to iterate; horrible.
                    $keys = [];
                    foreach ($container as $k => $ignore) {
                        $keys[] = $k;
                    }
                    return $keys;
                }
            }
            if (!($container instanceof \ArrayAccess)) {
                return array_keys(get_object_vars($container));
            }
        }
        throw new \InvalidArgumentException(
            'Arg container type[' . static::getType($container)
            . (!is_object($container) ? '] is not array or object.' : '] is non-Traversable ArrayAccess.')
        );
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
     * Get all or a single received request header(s) in any server environment.
     *
     * Case-sensitive; fixes case inconsistencies in actual header names,
     * so that 'content-length' gets listed as 'Content-Length'.
     *
     * Compatible with nginx (and other non-Apache webserver); then uses
     * HTTP_ and CONTENT_ $_SERVER variables.
     *
     * @param string $name
     *      Empty: get all headers.
     *
     * @return array|string|null
     *      String: non-empty arg name, and that header exists.
     *      Null: non-empty arg name, and that header doesn't exist.
     */
    public function getRequestHeader(string $name = '')
    {
        // Save headers, because costly to build if no getallheaders() function.
        static $headers;
        if ($headers === null) {
            $headers = [];
            // Apache.
            if (function_exists('getallheaders')) {
                $hdrs = getallheaders();
                // getallheaders() may return false; for un-documented reason.
                if ($hdrs) {
                    foreach ($hdrs as $key => $val) {
                        $headers[ucwords(strtolower(str_replace('_', '-', $key)), '-')] = $val;
                    }
                }
            }
            // nginx (non-Apache).
            if (!$headers
                && !empty($_SERVER)
            ) {
                foreach ($_SERVER as $key => $val) {
                    if (strpos($key, 'HTTP_') === 0) {
                        $headers[ucwords(strtolower(str_replace('_', '-', substr($key, 5))), '-')] = $val;
                    }
                    // Content-Type, Content-Length, Content-Md5 (and possibly more)
                    // don't get HTTP_ prefixed.
                    // Those headers are btw empty unless request method POST/PUT.
                    elseif (strpos($key, 'CONTENT_') === 0) {
                        $headers[ucwords(strtolower(str_replace('_', '-', $key)), '-')] = $val;
                    }
                }
            }
        }
        return !$name ? $headers : ($headers[$name] ?? null);
    }

    /**
     * @var string|null
     */
    protected $documentRoot;

    /**
     * @var int|null
     */
    protected $documentRootsMinLength;

    /**
     * @var array|null
     */
    protected $documentRootsAll;

    /**
     * @var string
     */
    protected $vendorDir;

    /**
     * Real path document root.
     *
     * @return string
     */
    public function documentRoot() : string
    {
        if (!$this->documentRoot) {
            if (!empty($_SERVER['DOCUMENT_ROOT'])) {
                $this->documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);
                if (DIRECTORY_SEPARATOR == '\\') {
                    $this->documentRoot = str_replace('\\', '/', $this->documentRoot);
                }
            } elseif (CliEnvironment::cli()) {
                if (!($this->documentRoot = CliEnvironment::getInstance()->documentRoot)) {
                    throw new ConfigurationException(
                        'Cannot resolve document root, probably no .document_root file in document root.'
                    );
                }
            } else {
                throw new ConfigurationException(
                    'Cannot resolve document root, _SERVER[DOCUMENT_ROOT] non-existent or empty.');
            }
        }
        return $this->documentRoot;
    }

    /**
     * Shortest document root, in effect symlinked or real path.
     *
     * @return int
     */
    public function documentRootsMinLength() : int
    {
        if (!$this->documentRootsMinLength) {
            $this->documentRootsAll();
        }
        return $this->documentRootsMinLength;
    }

    /**
     * List of real path and symlinked path (if any).
     *
     * On Windows furthermore ditto two with forward slash directory separator.
     *
     * @return array
     */
    public function documentRootsAll() : array
    {
        if (!$this->documentRootsAll) {
            $roots[] = $real_root = $this->documentRoot();
            $min_length = strlen($real_root);
            $symlink_root = dirname($_SERVER['SCRIPT_FILENAME']);
            // In cli mode a symlinked path is empty, because SCRIPT_FILENAME
            // is the filename only; no path.
            if (
                $symlink_root && $symlink_root != '.'
                && $symlink_root != $real_root
            ) {
                // The symlink must be a subset of the real path, so for
                // replacers it works swell with symlink after real path.
                $roots[] = $symlink_root;
                if (($length = strlen($symlink_root)) < $min_length) {
                    // Likely.
                    $min_length = $length;
                }
            }
            if (DIRECTORY_SEPARATOR == '\\') {
                $forward_slash_root = str_replace('\\', '/', $real_root);
                if ($forward_slash_root != $real_root) {
                    $roots[] = $forward_slash_root;
                }
                if ($symlink_root != $real_root) {
                    $forward_slash_root = str_replace('\\', '/', $symlink_root);
                    if ($forward_slash_root != $symlink_root) {
                        $roots[] = $forward_slash_root;
                    }
                }
            }
            $this->documentRootsAll =& $roots;
            $this->documentRootsMinLength = $min_length;
        }
        return $this->documentRootsAll;
    }

    /**
     * @see Utils::pathReplaceDocumentRoot()
     *
     * @var string
     */
    const DOCUMENT_ROOT_REPLACER = '[doc_root]';

    /**
     * Replaces all variants of document root in a path with a substitute.
     *
     * The variants are real path document root and symlinked document root.
     *
     * @param string $path
     * @param string $substitute
     * @param bool $leading
     *      True: only if document root found from beginning of path,
     *          and then only once.
     *
     * @return string
     */
    public function pathReplaceDocumentRoot(
        string $path,
        string $substitute = Utils::DOCUMENT_ROOT_REPLACER,
        bool $leading = false
    ) : string {
        if (!$this->documentRootsMinLength) {
            $this->documentRootsAll();
        }
        if (strlen($path) >= $this->documentRootsMinLength) {
            foreach ($this->documentRootsAll as $root) {
                if (!$leading) {
                    $path = str_replace($root, $substitute, $path);
                }
                elseif (strpos($path, $root) === 0) {
                    return $substitute . substr($path, strlen($root));
                }
            }
        }
        return $path;
    }

    /**
     * Get vendor dir relative to document root.
     *
     * @return string
     */
    public function vendorDir() : string
    {
        if (!$this->vendorDir) {
            $this->vendorDir = trim(
                str_replace(
                    '/simplecomplex/utils/src',
                    '',
                    $this->pathReplaceDocumentRoot(
                        DIRECTORY_SEPARATOR == '\\' ? str_replace('\\', '/', __DIR__) : __DIR__,
                        '',
                        true
                    )
                ),
                '/'
            );
        }
        return $this->vendorDir;
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
     * @throws \RuntimeException
     *      Path not resolvable to absolute path.
     * @throws ConfigurationException
     *      Propagated; document root cannot be determined.
     */
    public function resolvePath(string $relativePath) : string
    {
        $path = $relativePath;
        // Unless absolute.
        if (
            strpos($path, '/') !== 0
            && (DIRECTORY_SEPARATOR == '/' || strpos($path, ':') !== 1)
        ) {
            $doc_root = $this->documentRoot();
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
     * Check if a - file or directory - file mode is group-write.
     *
     * Always returns false on non posix compliant file system; Windows.
     *
     * Group-write apparantly requires chmod'ing upon dir/file creation.
     *
     * @param int $fileMode
     *      Use leading zero.
     *
     * @return bool
     */
    public function isFileGroupWrite(int $fileMode)
    {
        if (static::FILE_SYSTEM_POSIX) {
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
        }
        return false;
    }

    /**
     * Handles that PHP native mkdir() might not be able to use a group-write
     * mode (0770 becames 0750) and might fail entirely to set uid or gid.
     *
     * Does not support recursive making - use ensurePath() for that.
     *
     * @see Utils::ensurePath()
     *
     * @param string $path
     * @param int $mode
     *      Default: 0700; user read-write-execute.
     *      Ignored on non posix compliant file system (Windows).
     *
     * @return void
     *
     * @throws \RuntimeException
     *      Failing to create directory.
     *      Failing to chmod directory.
     */
    public function mkdir(string $path, int $mode = 0700) /*: void*/
    {
        // No recursive making, because complex to implement recursive chmod'ing
        // and ensurePath() does it; effectively implements recursive chmod'ing.
        if (!file_exists($path)) {
            $safe_mode = 0700;
            if (!mkdir($path, $mode)) {
                throw new \RuntimeException(
                    'Failed to create dir[' . $path . '] with safe mode[' . decoct($safe_mode) . '].'
                );
            };
            if ($mode !== $safe_mode && static::FILE_SYSTEM_POSIX && !chmod($path, $mode)) {
                throw new \RuntimeException(
                    'Failed to chmod dir[' . $path . '] to mode[' . decoct($mode) . '].'
                );
            }
        }
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
     * NB: May fail if a directory isn't executable (seekable) by current user.
     * And may attempt to create a directory which does exist, because current
     * user can't 'see' the directory.
     *
     * @param string $absolutePath
     * @param int $mode
     *      Default: user-only read/write/execute.
     *
     * @return bool
     *      False: The directory didn't exist; throws exception on failure.
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
    public function ensurePath(string $absolutePath, int $mode = 0700) : bool
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
                $this->mkdir($existing, $mode);
            } while ($trailing);
            // Didn't exist.
            return false;
        }
        elseif (!is_dir($absolutePath)) {
            throw new \RuntimeException('Path exists but is not directory[' . $absolutePath . '].');
        }
        // Did exist.
        return true;
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
     * Even with INI_SCANNER_RAW parse_ini_*() substitute array keys with PHP
     * constants - this method also prevents that.
     * The downside of that fix is that an array key which is deliberately
     * single-quoted will be unquoted in the output.
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
     *      On parse failure.
     */
    public function parseIniString(string $ini, bool $processSections = false, bool $typed = false) : array
    {
        if (!$ini) {
            return [];
        }

        // Prevent array key substitution by PHP constant;
        // by single-quoting key; parse_ini_string() INI_SCANNER_RAW
        // doesn't turn off that substitution :-(
        $ini = preg_replace(
            '/\n([^;\n\[]+)\[([A-Z_][A-Z\d_]+)\][ ]?=/',
            "\n" . '$1[\'$2\'] =',
            str_replace("\r", '', $ini)
        );

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
        if ($arr) {
            // Remove single-quoting from array keys.
            $keys_unquoted = [];
            foreach ($arr as $key0 => $val0) {
                // Level 0.
                // Obsolete if it's a section (no substitution there),
                // but we cannot know if section or not.
                $le0 = strlen($key0);
                if ($le0 > 2 && $key0{0} === '\'' && $key0{$le0 - 1} === '\'') {
                    $k0 = substr($key0, 1, $le0 - 2);
                } else {
                    $k0 = $key0;
                }
                // Level 1.
                if ($processSections && is_array($val0)) {
                    // Sectional.
                    $keys_unquoted[$k0] = [];
                    foreach ($val0 as $key1 => $val1) {
                        if (
                            is_string($key1)
                            && ($le1 = strlen($key1)) > 2 && $key1{0} === '\'' && $key1{$le1 - 1} === '\''
                        ) {
                            $k1 = substr($key1, 1, $le1 - 2);
                        } else {
                            $k1 = $key1;
                        }
                        $keys_unquoted[$k0][$k1] = $val1;
                    }
                }
                else {
                    $keys_unquoted[$k0] = $val0;
                }
            }
            unset($arr);

            if ($typed) {
                $this->typeArrayValues($keys_unquoted, static::PARSE_INI_REPLACE);
            }
            return $keys_unquoted;
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
     * Removes line comments that begin at line start
     * or before any code in line.
     *
     * Also remove carriage return.
     *
     * @param string $json
     * @param bool $assoc
     *
     * @return mixed
     *
     * @throws ParseJsonException
     *      On parse failure.
     */
    public function parseJsonString(string $json, bool $assoc = false) {
        if ($json) {
            // Remove line comments that begin at line start
            // or before any code in line.
            $json = trim(
                preg_replace(
                    '/\n[ ]*\/\/[^\n]*/m',
                    '',
                    "\n" . str_replace("\r", '', $json)
                )
            );
        }
        $parsed = json_decode($json, $assoc);
        $error = json_last_error();
        if ($error) {
            switch ($error) {
                case JSON_ERROR_NONE: $name = 'NONE'; break;
                case JSON_ERROR_DEPTH: $name = 'DEPTH'; break;
                case JSON_ERROR_STATE_MISMATCH: $name = 'STATE_MISMATCH'; break;
                case JSON_ERROR_CTRL_CHAR: $name = 'CTRL_CHAR'; break;
                case JSON_ERROR_SYNTAX: $name = 'SYNTAX'; break;
                case JSON_ERROR_UTF8: $name = 'UTF8'; break;
                case JSON_ERROR_RECURSION: $name = 'RECURSION'; break;
                case JSON_ERROR_INF_OR_NAN: $name = 'INF_OR_NAN'; break;
                case JSON_ERROR_UNSUPPORTED_TYPE: $name = 'UNSUPPORTED_TYPE'; break;
                case JSON_ERROR_INVALID_PROPERTY_NAME: $name = 'INVALID_PROPERTY_NAME'; break;
                case JSON_ERROR_UTF16: $name = 'UTF16'; break;
                default: $name = 'unknown';
            }
            throw new ParseJsonException('Failed parsing JSON, error: (' . $name . ') ' . json_last_error_msg() . '.');
        }
        return $parsed;
    }

    /**
     * @param string $filename
     * @param bool $assoc
     *
     * @return mixed
     *
     * @throws \RuntimeException
     *      If the file non-existent or not file, or reading the file fails.
     * @throws ParseJsonException
     *      Propagated.
     */
    public function parseJsonFile(string $filename, bool $assoc = false)
    {
        $json = file_get_contents($filename);
        if ($json === false) {
            if (!file_exists($filename)) {
                throw new \RuntimeException('File not found, file[' . $filename . '].');
            }
            if (!is_file($filename)) {
                throw new \RuntimeException('Not a file, file[' . $filename . '].');
            }
            throw new \RuntimeException('Failed reading file[' . $filename . '].');
        }
        return $this->parseJsonString($json, $assoc);
    }

    /**
     * @var int
     */
    const ARRAY_RECURSION_LIMIT = 10;

    /**
     * Casts bucket string values that are 'null', 'true', 'false', numeric,
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
            throw new \TypeError('Arg container type[' . static::getType($container) . '] is not array|object.');
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

    /**
     * Convert number or hex string to any base through 2 and 62.
     *
     * Base 62 produces up to 35% shorter string than base 16.
     *
     * Like native base_convert() precision fails for numbers (somewhere) larger
     * than (tested on windows box with ini precision 14):
     * - base 10: 9007199254740991 (> 15 digits)
     * - base 16: 1fffffffffffff (> 14 digits)
     *
     * @param integer|float|string $num
     *      Negative becomes positive.
     * @param integer $fromBase
     *      Must be between 2 and 36, both included.
     *      Default: 10.
     * @param integer $toBase
     *      Must be between 2 and 62, both included.
     *      Default: 62.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     *      Arg fromBase not 2 thru 36, or arg toBase no 2 thru 62.
     */
    public function baseConvert($num, int $fromBase = 10, int $toBase = 62) : string
    {
        static $table = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $zero = $table{0};
        $remainder = $num;
        if ($num < 1) {
            if (!$num) {
                return '' . $zero;
            }
            $remainder *= -1;
        }
        if ($toBase == $fromBase) {
            return '' . $remainder;
        }
        if ($fromBase < 2 || $fromBase > 36) {
            throw new \InvalidArgumentException('Arg fromBase must 2 thru 36.');
        }
        if ($toBase < 2 || $toBase > 62) {
            throw new \InvalidArgumentException('Arg toBase must 2 thru 62.');
        }
        if ($toBase <= 36) {
            return base_convert($num, $fromBase, $toBase);
        }
        if ($fromBase != 10) {
            $remainder = base_convert($remainder, $fromBase, 10);
        }
        // Arg $num may be string, and base_convert() returns string.
        $remainder = (float) $remainder;
        $res = '';
        // Find power ~ required number of digits.
        $pow = 0;
        $divisor = 1;
        // Limit:100 probably way too high, havent seen more than 10 iterations
        // (1fffffffffffff, with precision 14)
        $limit = 100;
        while ($remainder >= ($test = pow($toBase, ++$pow)) && (--$limit)) {
            $divisor = $test;
            --$limit;
        }
        --$pow;
        // Do modulo procedure.
        // Limit:100 probably way too high, havent seen more than 14 iterations
        // (1fffffffffffff, with precision 14).
        $limit = 100;
        while ($remainder > 0 && (--$limit)) {
            if ($pow) {
                if ($divisor == $remainder) {
                    return $res . $table{1} . str_repeat($zero, $pow);
                }
                elseif ($divisor > $remainder) {
                    $res .= $zero;
                }
                else {
                    if ($remainder > $divisor) {
                        $res .= $table{ (int)floor($remainder / $divisor) };
                        $remainder = fmod($remainder, $divisor);
                    }
                    else {
                        $res .= $table{ $pow };
                        $remainder = fmod($remainder, $divisor);
                    }
                    if ($remainder < 1 && !floor($remainder)) {
                        return $res . str_repeat($zero, $pow);
                    }
                }
            }
            else {
                return $res . $table{ (int) $remainder };
            }
            $divisor = pow($toBase, --$pow);
        }
        return $res;
    }
}
