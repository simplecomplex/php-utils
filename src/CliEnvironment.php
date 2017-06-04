<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

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
 * @property-read CliCommand|null $command
 * @property-read array $inputArguments
 * @property-read array $inputOptions
 * @property-read array $inputOptionsShort
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


    // General stuff.-----------------------------------------------------------

    /**
     * @var Sanitize
     */
    protected $sanitize;

    /**
     * @var string
     */
    const CLASS_CLI_COMMAND = CliCommand::class;

    /**
     * Is current execution context CLI?
     *
     * @return boolean
     */
    public static function cli() : bool
    {
        return PHP_SAPI == 'cli';
    }

    /**
     * @var array
     */
    const MESSAGE_STATUS = [
        'error' => '',
        'warning' => '',
        'notice' => '',
        'info' => '',
        'success' => '',
    ];

    /**
     * @param mixed $message
     *      Gets stringified, and sanitized.
     *
     * @param string $status
     *
     * @return void
     *      Will echo arg message.
     */
    public function echoMessage($message, string $status = '') /*: void*/
    {
        if ($status) {
            if (!isset(static::MESSAGE_STATUS[$status])) {
                $status = 'error';
            }
            echo '[' . static::MESSAGE_STATUS[$status] . '] ';
        }
        echo $this->sanitize->cli('' . $message) . "\n";
    }


    // Explorable.--------------------------------------------------------------

    /**
     * @var CliCommand|null
     */
    protected $command;

    /**
     * Read-only.
     * @var array|null
     */
    protected $inputArguments;

    /**
     * Read-only.
     * @var array|null
     */
    protected $inputOptions;

    /**
     * Read-only.
     * @var array|null
     */
    protected $inputOptionsShort;

    /**
     * Read-only.
     * @var string
     */
    protected $documentRoot;

    /**
     * @var array
     */
    protected $explorableIndex = [
        'command',
        'inputArguments',
        'inputOptions',
        'inputOptionsShort',
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
            case 'command':
                if (!isset($this->inputArguments)) {
                    $this->resolveArgsNOpts();
                }
                if (!isset($this->command)) {
                    $this->resolveArgsNOpts();
                }
                return $this->command;
            case 'inputArguments':
            case 'inputOptions':
            case 'inputOptionsShort':
                if (!isset($this->{$name})) {
                    $this->resolveArgsNOpts();
                }
                return $this->{$name};
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
     * All accessible members are read-only.
     *
     * @throws RuntimeException
     *      Every time.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return void
     */
    public function __set(string $name, $value) /*: void*/
    {
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


    // Commands.----------------------------------------------------------------

    /**
     * @var CliCommand[]|null
     */
    protected $commandsAvailable;

    /**
     * @var string
     */
    protected $help;

    /**
     * @see CliEnvironment::getInstance()
     * @see CliEnvironment::setCommandsAvailable()
     *
     * @param CliCommand[] ...$commandsAvailable
     */
    public function __construct(CliCommand ...$commandsAvailable)
    {
        $this->sanitize = Sanitize::getInstance();

        if ($commandsAvailable) {
            $this->setCommandsAvailable(...$commandsAvailable);
        }
    }

    /**
     * Only allowed once, throws exception on second call.
     *
     * @param CliCommand[] ...$commandsAvailable
     *
     * @return void
     */
    public function setCommandsAvailable(CliCommand ...$commandsAvailable) /*: void*/
    {
        foreach ($commandsAvailable as $command) {
            $this->commandsAvailable[$command->name] = $command;
        }
    }

    // @todo: add section about document root.

    /**
     * Print 'help' to console.
     *
     * @return void
     */
    public function help() /*: void*/
    {
        if ($this->help) {
            $help = $this->help;
        }
        else {
            $class_command = static::CLASS_CLI_COMMAND;
            $help = '' . new $class_command(
                    'help',
                    'Lists commands available. First argument is always the command name.',
                    [],
                    ['help' => ' '],
                    ['h' => ' ']
                );
        }
        $this->echoMessage(
            __NAMESPACE__ . '\\' . get_class($this) . "\n" . $help
        );
    }

    /**
     * Resolve console input arguments and options.
     *
     * @return void
     */
    protected function resolveArgsNOpts() /*: void*/
    {
        if (empty($GLOBALS['argv'])) {
            return;
        }
        // No need; shortOptToLongOpt has not been altered since last call.
        if (isset($this->inputArguments)) {
            return;
        }
        // Init args and opts.
        $this->inputArguments = $this->inputOptions = $this->inputOptionsShort = [];

        global $argv;
        $n_args = count($argv);
        if ($n_args < 2) {
            return;
        }
        $regex = constant(static::CLASS_CLI_COMMAND . '::REGEX');

        for ($i_arg = 1; $i_arg < $n_args; ++$i_arg) {
            $item = $argv[$i_arg];
            $le = strlen($item);
            if (!$le) {
                continue;
            }
            // Options - long and short - starts with a dash.
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
                    if (preg_match($regex['option'], $key)) {
                        $this->inputOptions[str_replace('-', '_', $key)] = $value;
                    }
                    // Otherwise ignore.
                }
                // Short option(s).
                else {
                    $item = substr($item, 1);
                    --$le;
                    // ctype_alpha() would actually do unless 'word' poluted
                    // by locale non-ASCIIs.
                    if (preg_match($regex['shortOpts'], $item)) {
                        for ($i = 0; $i < $le; ++$i) {
                            $this->inputOptionsShort[$item{$i}] = true;
                        }
                    }
                    // Otherwise ignore.
                }
            }
            // Arguments are anything that doesn't start with dash.
            else {
                // No leading dash.
                $this->inputArguments[] = $item;
            }
        }
    }

    /**
     * Map input arguments and options to a configured command.
     *
     * @return void
     */
    protected function mapInputToCommand() /*: void*/
    {
        if (isset($this->inputArguments)) {
            $this->resolveArgsNOpts();
        }

        $any_commands = !!$this->commandsAvailable;
        // Prepend general 'help' option.
        if ($any_commands && isset($this->commandsAvailable['help'])) {
            $help = $this->commandsAvailable['help'];
        } else {
            $class_command = static::CLASS_CLI_COMMAND;
            $help = new $class_command(
                'help',
                'Lists commands available. First argument is always the command name.',
                [],
                ['help' => ' '],
                ['h' => ' ']
            );
            if (!$this->commandsAvailable) {
                $this->commandsAvailable['help'] = $help;
            } else {
                $this->commandsAvailable = [$help] + $this->commandsAvailable;
            }

        }
        // Do input options indicate (general or specific) 'help'?
        $do_help = isset($this->inputOptions['help']) || isset($this->inputOptions['h']);

        if (!$any_commands) {
            if ($do_help) {
                $this->command = $help;
            }
            return;
        }

        if ($this->inputArguments) {
            $command_arg = reset($this->inputArguments);
            if (isset($this->commandsAvailable[$command_arg])) {
                $command = $this->commandsAvailable[$command_arg];
                // Save 'help' output, in case user decides to print that
                // instead of acting on the command.
                $this->help = '' . $command;

                if ($command->arguments) {
                    $args_input = $this->inputArguments;
                    array_shift($args_input);
                    $n_args_input = count($this->inputArguments);
                    $n_args_available = count($command->arguments);
                    $le = min($n_args_available, $n_args_input);
                    for ($i = 0; $i < $le; ++$i) {
                        $command->arguments[$i] = $args_input[$i];
                    }
                    if ($n_args_available > $n_args_input) {
                        array_splice($command->arguments, $n_args_input);
                    }
                }

                $options_selected = [];
                if ($command->options) {
                    if ($this->inputOptions) {
                        $opt_keys = array_keys($command->options);
                        foreach ($opt_keys as $opt) {
                            if (isset($this->inputOptions[$opt])) {
                                $options_selected[$opt] = $this->inputOptions[$opt];
                            }
                        }
                    }
                    if ($command->shortToLongOption && $this->inputOptionsShort) {
                        $opt_keys = array_keys($this->inputOptionsShort);
                        foreach ($opt_keys as $opt_short) {
                            if (isset($command->shortToLongOption[$opt_short])) {
                                $opt = $command->shortToLongOption[$opt_short];
                                if (!isset($options_selected[$opt])) {
                                    $options_selected[$opt] = true;
                                }
                            }
                        }
                    }
                }
                $command->options = $options_selected;
                // Not useful any more.
                $command->shortToLongOption = [];
                return;
            }
        }

        if ($do_help) {
            $this->command = $help;
        }
    }


    // Document root.-----------------------------------------------------------

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
}
