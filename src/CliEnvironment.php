<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use Psr\Log\LoggerInterface;
use SimpleComplex\Utils\Exception\InvalidArgumentException;
use SimpleComplex\Utils\Exception\ConfigurationException;
use SimpleComplex\Utils\Exception\RuntimeException;
use SimpleComplex\Utils\Exception\OutOfBoundsException;

/**
 * CLI PHP utility.
 *
 * Behaves as a foreachable and 'overloaded' collection;
 * dynamic getters and setters for protected members.
 *
 * Intended as singleton - ::getInstance() - but constructor not protected.
 *
 * @see Explorable
 *
 * @property array $shortOptToLongOpt
 *
 * @property-read array $options
 * @property-read array $arguments
 * @property-read string $currentWorkingDir
 * @property-read string $documentRoot
 * @property-read int $documentRootDistance
 *
 * @package SimpleComplex\Utils
 */
class CliEnvironment extends Explorable
{
    /**
     * @see GetInstanceOfFamilyTrait
     *
     * First object instantiated via this method, disregarding class called on.
     * @public
     * @static
     * @see GetInstanceOfFamilyTrait::getInstance()
     */
    use Traits\GetInstanceOfFamilyTrait;

    /**
     * @see Cli::__construct()
     *
     * @var array
     */
    protected $shortOptToLongOpt;

    /**
     * Read-only.
     * @var array|null
     */
    protected $options;

    /**
     * Read-only.
     * @var array|null
     */
    protected $arguments;

    /**
     * Read-only.
     * @var string
     */
    protected $documentRoot;

    /**
     * For logger 'type' context; like syslog RFC 5424 'facility code'.
     *
     * @var string
     */
    const LOG_TYPE = 'unicode';

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @see CliEnvironment::getInstance()
     * @see CliEnvironment::setLogger()
     *
     * @param LoggerInterface|null
     *      PSR-3 logger, if any.
     * @param Cli[] $commands
     */
    public function __construct(/*?LoggerInterface*/ $logger = null, $commands = [])
    {
        $this->logger = $logger;
    }

    /**
     * Overcome mutual dependency, provide a logger after instantiation.
     *
     * This class does not need a logger at all. But errors are slightly more
     * debuggable provided a logger.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger) /*: void*/
    {
        $this->logger = $logger;
    }

    /**
     * @var array
     */
    protected $explorableIndex = [
        'shortOptToLongOpt',
        'options',
        'arguments',
        'currentWorkingDir',
        'documentRoot',
        'documentRootDistance',
    ];

    /**
     * @throws OutOfBoundsException
     *      If no such instance property.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'shortOptToLongOpt':
                return $this->shortOptToLongOpt ?? [];
            case 'options':
                if (!isset($this->options)) {
                    $this->resolveArgsNOpts();
                }
                return $this->options;
            case 'arguments':
                if (!isset($this->arguments)) {
                    $this->resolveArgsNOpts();
                }
                return $this->arguments;
            case 'currentWorkingDir':
                return $this->getCurrentWorkingDir();
            case 'documentRoot':
                return $this->getDocumentRoot();
            case 'documentRootDistance':
                return $this->getDocumentRootDistance();
        }
        throw new OutOfBoundsException(get_class($this) . ' instance has no property[' . $name . '].');
    }

    /**
     * @throws OutOfBoundsException
     *      If no such instance property.
     * @throws RuntimeException
     *      If that instance property is read-only.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return void
     */
    public function __set(string $name, $value) /*: void*/
    {
        switch ($name) {
            case 'shortOptToLongOpt':
                if ($value) {
                    if (!is_array($value)) {
                        throw new InvalidArgumentException(get_class($this) . '->' . $name . ' must be array.');
                    }
                    $this->shortOptToLongOpt = [];
                    // Nullify these two, to indicate for optsNArgs() that it
                    // must update arguments and options on call.
                    $this->arguments = null;
                    $this->options = null;
                    foreach ($value as $k => $v) {
                        if (
                            strlen($k) == 1 && ctype_alpha($k)
                            && preg_match('/[a-z][a-z\d_\-]*/', $v)
                        ) {
                            $this->shortOptToLongOpt[$k] = str_replace('-', '_', $v);
                        }
                    }
                }
                return;
        }
        if (!in_array($name, $this->explorableIndex, true)) {
            throw new OutOfBoundsException(get_class($this) . ' instance has no property[' . $name . '].');
        }
        throw new RuntimeException(get_class($this) . ' instance property[' . $name . '] is read-only.');
    }

    /**
     * @see \Iterator::current()
     *
     * @return mixed
     */
    public function current()
    {
        // Override to facilitate direct call to __get(); cheaper.
        return $this->__get(current($this->explorableIndex));
    }

    /**
     * @var array
     */
    const REGEX = [
        'name' => '/^[a-z][a-z\d_\-]*$/',
        'argument' => '/^[^\-].*$/',
        'shortOpts' => '/^[a-zA-Z]+$/',
    ];

    /**
     * Resolve console arguments and options.
     */
    protected function resolveArgsNOpts() /*: void*/
    {
        if (empty($GLOBALS['argv'])) {
            return;
        }
        // No need; shortOptToLongOpt has not been altered since last call.
        if (isset($this->arguments)) {
            return;
        }
        // Init args and opts.
        $this->arguments = $this->options = [];

        global $argv;
        $n_args = count($argv);
        $opts_long = $opts_short = array();
        if ($n_args < 2) {
            return;
        }
        for ($i_arg = 1; $i_arg < $n_args; ++$i_arg) {
            $item = $argv[$i_arg];
            $le = strlen($item);
            if (!$le) {
                continue;
            }
            if ($item{0} === '-') {
                if ($le == 1) {
                    // Ignore dash only.
                    continue;
                }
                // Long option?
                if ($item{1} === '-') {
                    // Long option: require --[a-z\d_\-].*
                    $item = substr($item, 2);
                    $pos_equal = strpos($item, '=');
                    if ($pos_equal === false) {
                        $key = $item;
                        $value = true;
                    } else {
                        $key = substr($item, 0, $pos_equal);
                        $value = substr($item, $pos_equal + 1);
                    }
                    if (preg_match(static::REGEX['name'], $key)) {
                        $opts_long[str_replace('-', '_', $key)] = $value;
                    }
                    // Otherwise ignore.
                }
                // Short option(s).
                else {
                    $item = substr($item, 1);
                    --$le;
                    // ctype_alpha() would actually do unless 'word' poluted
                    // by locale non-ASCIIs.
                    if (preg_match(static::REGEX['shortOpts'], $item)) {
                        for ($i = 0; $i < $le; ++$i) {
                            $opts_short[$item{$i}] = true;
                        }
                    }
                    // Otherwise ignore.
                }
            } else {
                // No leading dash.
                $this->arguments[] = $item;
            }
        }
        // 'Translate' short option to long.
        if ($opts_short && $this->shortOptToLongOpt) {
            $shorts = array_keys($opts_short);
            foreach ($shorts as $k) {
                if (isset($this->shortOptToLongOpt[$k])) {
                    $long = $this->shortOptToLongOpt[$k];
                    if (!isset($opts_long[$long])) {
                        $opts_long[$long] = true;
                    }
                    // else ignore; long option also set.
                    // Remove short option, since it's long counterpart is set.
                    unset($opts_short[$k]);
                }
            }
        }
        // Append 'untranslated' short to long.
        if ($opts_long || $opts_short) {
            if ($opts_long) {
                if ($opts_short) {
                    $this->options = array_merge($opts_long, $opts_short);
                }
                else {
                    $this->options =& $opts_long;
                }
            }
            else {
                $this->options =& $opts_short;
            }
        }
    }

    /**
     * Alternative to native getcwd(), which throws exception on failure,
     * and secures forward slash directory separator.
     *
     * If document root is symlinked, this returns the resolved path,
     * not the symbolic link.
     *
     * @throws ConfigurationException
     *      If current working dir cannot be resolved.
     *      Citing http://php.net/manual/en/function.getcwd.php:
     *      On some Unix variants, getcwd() will return false if any one of the
     *      parent directories does not have the readable or search mode set,
     *      even if the current directory does.
     *
     * @return string
     */
    protected function getCurrentWorkingDir() : string
    {
        $path = getcwd();
        if ($path === false) {
            $user_group = 'user';
            if (function_exists('posix_geteuid')) {
                $user_group .= '[' . posix_getpwuid(posix_geteuid())['name']
                    . '] or group[' . posix_getgrgid(posix_getgid())['name'] . ']';
            }
            throw new ConfigurationException(
                'Cannot resolve current working directory; do ensure that parent and all ancestor dirs are readable'
                . 'and executable (searchable) by current ' . $user_group . '.');
        }
        // Symlinked path cannot be detected because $_SERVER['SCRIPT_FILENAME']
        // in cli mode only returns the filename; not path + filename.

        if (DIRECTORY_SEPARATOR == '\\') {
            $path = str_replace('\\', '/', $path);
        }
        return $path;
    }

    /**
     * @var int
     */
    const DIRECTORY_TRAVERSAL_LIMIT = 100;

    /**
     * Find document root.
     *
     * PROBLEM
     * A PHP CLI script has rarely any reliable means of discovering a site's
     * document root.
     * $_SERVER['DOCUMENT_ROOT'] will usually be empty, because that var is set
     * by a webserver - and CLI PHP is not executed in context of a webserver.
     * And getcwd() will only tell the script's current position in the file
     * system; only useful if the CLI script is placed right in document root.
     *
     * SOLUTION
     * Place a .document_root file in the site's document root - but only after
     * checking that the webserver (or an Apache .htaccess file) is configured
     * to hide .hidden files.
     * See the files in this library's doc/.document-root-files dir.
     *
     * Document root in the root of the file system is not supported.
     *
     * @throws ConfigurationException
     *      Propagated.
     * @see Cli::getCurrentWorkingDir()
     *
     * @param bool $noTaintEnvironment
     *      False: do set $_SERVER['DOCUMENT_ROOT'], if successful.
     *
     * @return string
     *      Empty: document root cannot be resolved.
     */
    protected function getDocumentRoot($noTaintEnvironment = false) : string
    {
        if ($this->documentRoot) {
            return $this->documentRoot;
        }

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $this->documentRoot = $_SERVER['DOCUMENT_ROOT'];
            // We don't expect DIRECTORY_SEPARATOR=\ issues for that server var.
            return $this->documentRoot;
        }

        $path = $this->getCurrentWorkingDir();
        if (DIRECTORY_SEPARATOR == '\\') {
            $path = str_replace('\\', '/', $path);
        }

        // Go up/left.
        $limit = static::DIRECTORY_TRAVERSAL_LIMIT;
        do {
            if (file_exists($path . '/.document_root') && is_file($path . '/.document_root')) {
                $this->documentRoot = $path;
                if (!$noTaintEnvironment) {
                    $_SERVER['DOCUMENT_ROOT'] = $path;
                }
                return $path;
            }
        } while((--$limit) && ($path = dirname($path)) && $path != '/');

        // Can't go down/right without knowing document root beforehand.
        return '';
    }

    /**
     * Find distance from document root.
     *
     *  Values:
     *  - zero: you're at document root
     *  - positive: you're below (right of) document root
     *  - negative: you're above (left of) document root
     *  - null: not checked yet, or failed to find document root
     *
     * @throws ConfigurationException
     *      Propagated.
     * @see Cli::getCurrentWorkingDir()
     *
     * @param bool $noTaintEnvironment
     *      False: do set $_SERVER['DOCUMENT_ROOT'], if successful.
     *
     * @return int|null
     *      Null: document root can't be determined, or you're not in the same
     *          file system branch as document root.
     */
    protected function getDocumentRootDistance($noTaintEnvironment = false) /*: ?int*/
    {
        $root = $this->documentRoot;
        if (!$root) {
            $root = $this->getDocumentRoot($noTaintEnvironment);
        }
        if (!$root) {
            return null;
        }

        $path = $this->getCurrentWorkingDir();
        if (DIRECTORY_SEPARATOR == '\\') {
            $path = str_replace('\\', '/', $path);
        }

        if ($path == $root) {
            return 0;
        }
        // Current path contains document root; you're below/to right.
        if (strpos($path, $root) === 0) {
            // +1 is for trailing slash.
            $intermediates = substr($path, strlen($root) + 1);
            return count(explode('/', $intermediates));
        }
        // Document root contains current path; you're above/to left.
        if (strpos($root, $path) === 0) {
            // +1 is for trailing slash.
            $intermediates = substr($root, strlen($path) + 1);
            return -count(explode('/', $intermediates));
        }
        // You're in another branch than document root.
        // Can't determine distance.
        return null;
    }

    /**
     * Is current execution context CLI?
     *
     * @return boolean
     */
    public static function cli() : bool {
        return PHP_SAPI == 'cli';
    }

    /**
     * Change directory - chdir() - until at document root.
     *
     * @throws ConfigurationException
     *      If document root cannot be resolved; that is:
     *      'DOCUMENT_ROOT' environment var is non-existent/empty and there
     *      hasn't been placed a .document_root file in document root.
     * @throws ConfigurationException
     *      Propagated.
     * @see Cli::getCurrentWorkingDir()
     *
     * @param bool $noTaintEnvironment
     *      False: do set $_SERVER['DOCUMENT_ROOT'], if successful.
     *
     * @return bool
     *      False: document root can't be determined, or you're not in the same
     *          file system branch as document root, or a chdir() call fails.
     */
    public function changeDirDocumentRoot($noTaintEnvironment = false) : bool
    {
        $distance = $this->getDocumentRootDistance($noTaintEnvironment);
        $root = $this->documentRoot;
        if ($root === '') {
            throw new ConfigurationException(
                'Cannot resolve document root; empty \'DOCUMENT_ROOT\' environment var'
                . ' and no .document_root file found.'
            );
        }
        if ($distance === null) {
            return false;
        }
        if ($distance) {
            // Below/right.
            if ($distance > 0) {
                for ($i = 0; $i < $distance; ++$i) {
                    if (!chdir('../')) {
                        return false;
                    }
                }
            } else {
                // Above/to left.
                // Document root contains current path.
                $intermediates = explode(
                    '/',
                    substr($root, strlen(static::getCurrentWorkingDir()) + 1)
                );
                $distance *= -1;
                for ($i = 0; $i < $distance; ++$i) {
                    if (!chdir($intermediates[$i])) {
                        return false;
                    }
                }
            }
        }
        // Sanity check.
        return $this->getCurrentWorkingDir() === $root;
    }

    /**
     * @var array
     */
    protected static $outputSanitizeNeedles = [
        '`',
    ];

    /**
     * @var array
     */
    protected static $outputSanitizeReplace = [
        "'",
    ];

    /**
     * Sanitize string to be printed to console.
     *
     * @param mixed $output
     *   Gets stringified.
     *
     * @return string
     */
    public static function outputSanitize($output) : string
    {
        return str_replace(static::$outputSanitizeNeedles, static::$outputSanitizeReplace, '' . $output);
    }

    /**
     * @param mixed $message
     *      Gets stringified, and sanitized.
     *
     * @param bool $skipTrailingNewline
     *      Truthy: do no append newline.
     *
     * @return bool
     *      Will echo arg message.
     */
    public static function echo($message, $skipTrailingNewline = false) : bool
    {
        echo static::outputSanitize('' . $message) . (!$skipTrailingNewline ? "\n" : '');
        return true;
    }
}
