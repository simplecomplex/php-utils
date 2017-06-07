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
use SimpleComplex\Utils\Exception\LogicException;
use SimpleComplex\Utils\Exception\RuntimeException;
use SimpleComplex\Utils\Exception\OutOfBoundsException;

/**
 * CLI PHP utility.
 *
 * Provides means for:
 * - mapping command line input to one or more predefined commands
 * - finding/navigating to a site's document root
 *
 * Behaves as a foreachable and 'overloaded' collection;
 * dynamic getters and setters for protected members.
 *
 * Example, getting document root:
 * @code
 * $doc_root = CliEnvironment::getInstance()->documentRoot;
 * @endcode
 *
 * Example, CLI command using this command mapping interface:
 * @see \SimpleComplex\JsonLog\Cli\JsonLogCli
 *
 * @see Explorable
 *
 * @property-read CliCommand|null $command
 * @property-read array $inputArguments
 * @property-read array $inputOptions
 * @property-read array $inputOptionsShort
 * @property-read array $inputErrors
 * @property-read string $currentWorkingDir
 * @property-read string $documentRoot
 * @property-read int $documentRootDistance
 *
 * Intended as singleton - ::getInstance() - but constructor not protected.
 *
 * @package SimpleComplex\Utils
 */
class CliEnvironment extends Explorable
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var CliEnvironment
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return CliEnvironment
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }


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
        // Light red.
        'error' => "\033[01;31m[error]\033[0m",
        // Light yellow.
        'warning' => "\033[01;33m[warning]\033[0m",
        // Light blue.
        'notice' => "\033[01;34m[notice]\033[0m",
        // White.
        'info' => "\033[01;37m[info]\033[0m",
        // Light green.
        'success' => "\033[01;32m[success]\033[0m",
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
            echo static::MESSAGE_STATUS[$status] . ' ';
        }
        echo $this->sanitize->cli('' . $message) . "\n";
    }


    // Explorable.--------------------------------------------------------------

    /**
     * @var CliCommand|null
     *      Null if no command mapped.
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
     * @var array
     */
    protected $inputErrors = [];

    /**
     * Read-only.
     * @var string
     */
    protected $documentRoot;


    // Explorable.--------------------------------------------------------------

    /**
     * @var array
     */
    protected $explorableIndex = [
        'command',
        'inputArguments',
        'inputOptions',
        'inputOptionsShort',
        'inputErrors',
        'currentWorkingDir',
        'documentRoot',
        'documentRootDistance',
    ];

    /**
     * @param string $name
     *
     * @return mixed
     *
     * @throws OutOfBoundsException
     *      If no such instance property.
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'command':
                if (!$this->inputResolved) {
                    $this->resolveInput();
                }
                if (!isset($this->command)) {
                    $this->mapInputToCommand();
                }
                return $this->command;
            case 'inputArguments':
            case 'inputOptions':
            case 'inputOptionsShort':
                if (!$this->inputResolved) {
                    $this->resolveInput();
                }
                return $this->{$name};
            case 'inputErrors':
                // Copy.
                $input_errors = $this->inputErrors;
                return $input_errors;
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
     * @param string $name
     * @param mixed $value
     *
     * @return void
     *
     * @throws OutOfBoundsException
     *      If no such instance property.
     * @throws RuntimeException
     *      If that instance property is read-only.
     */
    public function __set(string $name, $value) /*: void*/
    {
        if (isset($this->explorableIndex[$name])) {
            throw new OutOfBoundsException(get_class($this) . ' instance has no property[' . $name . '].');
        }
        throw new RuntimeException(get_class($this) . ' instance property[' . $name . '] is read-only.');
    }

    /**
     * @see \Iterator::current()
     * @see Explorable::current()
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
     * @var bool|null
     */
    protected $inputResolved;

    /**
     * @var CliCommand[]
     */
    protected $commandsAvailable = [];

    /**
     * @var string
     */
    protected $commandHelp;

    /**
     * @throws LogicException
     *      If not in cli mode.
     *
     * @see CliEnvironment::getInstance()
     * @see CliEnvironment::setCommandsAvailable()
     *
     * @param CliCommand[] ...$commandsAvailable
     *      Any number of, also none.
     */
    public function __construct(CliCommand ...$commandsAvailable)
    {
        if (!static::cli()) {
            throw new LogicException('This class is for cli mode only.');
        }

        // Dependencies.--------------------------------------------------------
        // Extending class' constructor might provide instance by other means.
        if (!$this->sanitize) {
            $this->sanitize = Sanitize::getInstance();
        }

        // Business.------------------------------------------------------------
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

    /**
     * Get commmand help. Will echo error message if input errors detected.
     *
     * @param string $preface
     *      Use 'none' for no preface.
     *
     * @return string
     */
    public function commandHelp(string $preface = '') : string
    {
        // Important.
        if (!$this->inputResolved) {
            $this->resolveInput();
        }

        if ($this->inputErrors) {
            foreach ($this->inputErrors as $msg) {
                $this->echoMessage($msg, 'warning');
            }
            echo "\n";
        }

        if ($preface) {
            if ($preface == 'none') {
                $preface = '';
            }
            else {
                $preface = rtrim($preface) . "\n";
            }
        } else {
            $preface = get_class($this) . "\n";
        }
        $preface .= 'Commands:';

        // There's a mapped command?
        if ($this->commandHelp) {
            $help = "\n\n" . $this->commandHelp;
        }
        // Concat all commands' help.
        else {
            $help = '';
            $n_availables = count($this->commandsAvailable);
            // Skip general 'help' if any other (specific) command is
            // available.
            if ($n_availables) {
                foreach ($this->commandsAvailable as $command) {
                    if ($n_availables == 1 || $command->name != 'help') {
                        $help .= "\n\n" . $command;
                    }
                }
            }
            // Print general 'help' if none.
            else {
                $class_command = static::CLASS_CLI_COMMAND;
                $help = "\n\n" . new $class_command(
                        'help',
                        'Lists commands available.' . "\n"
                        . 'Example: php script.phpsh command_name --some-option=\'whatever\' \'first arg value\' -x',
                        [],
                        ['help' => ' '],
                        ['h' => 'help']
                    );
            }
        }
        return $preface . $help . "\n";
    }

    /**
     * Resolve console input arguments and options.
     *
     * Casts values of options:
     * - 'true'|'false': bool
     * - stringed int: int
     *
     * @throww RuntimeException
     *      If globals argv is empty or non-existent.
     *
     * @return void
     */
    protected function resolveInput() /*: void*/
    {
        if (empty($GLOBALS['argv'])) {
            throw new RuntimeException(
                'Global argv '
                . (isset($GLOBALS['argv']) ?
                    ' is empty, should at least contain a bucket holding name of executed script file.' :
                    ' does not exist.'
                )
            );
        }
        $this->inputResolved = true;

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
                        // Prevent weird option value errors.
                        switch ($value) {
                            case 'true':
                                $value = true;
                                break;
                            case 'false':
                                $value = false;
                                break;
                            default:
                                if (ctype_digit($value)) {
                                    $value = (int) $value;
                                }
                        }
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
     * If fitting CliCommand found, the command's arguments and options will be
     * alterd (filtered) according to input arguments and options.
     *
     * @return void
     */
    protected function mapInputToCommand() /*: void*/
    {
        if (!$this->inputResolved) {
            $this->resolveInput();
        }

        $any_commands = !!$this->commandsAvailable;
        // Prepend general 'help' option.
        if ($any_commands && isset($this->commandsAvailable['help'])) {
            $help = $this->commandsAvailable['help'];
        } else {
            $class_command = static::CLASS_CLI_COMMAND;
            $help = new $class_command(
                'help',
                'Lists commands available.' . "\n"
                . 'Example: php script.phpsh command_name --some-option=\'whatever\' \'first arg value\' -x',
                [],
                ['help' => ' '],
                ['h' => 'help']
            );
            if (!$this->commandsAvailable) {
                $this->commandsAvailable['help'] = $help;
            } else {
                $this->commandsAvailable = ['help' => $help] + $this->commandsAvailable;
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

        // Get out if previously recorded input error(s).
        if ($this->inputErrors) {
            return;
        }

        if ($this->inputArguments) {
            $command_arg = reset($this->inputArguments);
            if (isset($this->commandsAvailable[$command_arg])) {
                $command = $this->commandsAvailable[$command_arg];
                // Save 'help' output, in case user decides to print that
                // instead of acting on the command.
                $this->commandHelp = '' . $command;
                if ($command->arguments) {
                    $args_input = $this->inputArguments;
                    array_shift($args_input);
                    $n_args_input = count($this->inputArguments);
                    $n_args_available = count($command->arguments);
                    $le = min($n_args_available, $n_args_input);
                    for ($i = 0; $i < $le; ++$i) {
                        // Overwrite the CliCommand argument's description with
                        // input value.
                        $command->arguments[$i] = $args_input[$i];
                    }
                    if ($n_args_available > $n_args_input) {
                        array_splice($command->arguments, $n_args_input);
                    }
                    if ($n_args_input > $n_args_available) {
                        $this->inputErrors[] = 'Command \'' . $command_arg . '\' only accepts '
                            . $n_args_available . ' arguments, saw ' . $n_args_input . '.';
                    }
                } else {
                    $n_args_input = count($this->inputArguments);
                    if ($n_args_input > 1) {
                        $this->inputErrors[] = 'Command \'' . $command_arg . '\' accepts no arguments, saw '
                            . ($n_args_input - 1) . '.';
                    }
                }

                $options_selected = [];
                if ($command->options) {
                    if ($this->inputOptions) {
                        $input_opts_rest = $this->inputOptions;
                        $opt_keys = array_keys($command->options);
                        foreach ($opt_keys as $opt) {
                            if (isset($this->inputOptions[$opt])) {
                                // Overwrite the CliCommand option's description
                                // with input value.
                                $options_selected[$opt] = $this->inputOptions[$opt];
                                unset($input_opts_rest[$opt]);
                            }
                        }
                        if ($input_opts_rest) {
                            $this->inputErrors[] = 'Command \'' . $command_arg . '\' doesn\'t support option(s): '
                                . join(', ', array_keys($input_opts_rest)) . '.';
                        }
                        unset($input_opts_rest);
                    }
                    if ($command->shortToLongOption && $this->inputOptionsShort) {
                        $input_opts_rest = [];
                        $opt_keys = array_keys($this->inputOptionsShort);
                        foreach ($opt_keys as $opt_short) {
                            if (isset($command->shortToLongOption[$opt_short])) {
                                $opt = $command->shortToLongOption[$opt_short];
                                if (!isset($options_selected[$opt])) {
                                    // Overwrite the CliCommand option's
                                    // description with true.
                                    $options_selected[$opt] = true;
                                }
                            } else {
                                $input_opts_rest[] = $opt_short;
                            }
                        }
                        if ($input_opts_rest) {
                            $this->inputErrors[] = 'Command \'' . $command_arg . '\' doesn\'t support short options(s): '
                                . join(', ', $input_opts_rest) . '.';
                        }
                        unset($input_opts_rest);
                    }
                }

                if ($this->inputErrors) {
                    return;
                }

                $command->options = $options_selected;
                // Not useful any more.
                $command->shortToLongOption = null;

                $command->setMapped();
                $this->command = $command;
                return;
            }
            else {
                $this->inputErrors[] = 'Command \'' . $command_arg . '\' not defined.';
                return;
            }
        }

        if ($do_help) {
            $this->command = $help;
        }
    }


    // Document root et al.-----------------------------------------------------

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
