<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use SimpleComplex\Utils\Interfaces\CliCommandInterface;
use SimpleComplex\Utils\Exception\ConfigurationException;
use SimpleComplex\Utils\Exception\ParseIniException;

/**
 * CLI PHP utility.
 *
 * Provides means for:
 * - mapping command line input to one or more predefined commands
 * - finding/navigating to a site's document root
 *
 * Behaves as a foreachable and 'overloaded' container;
 * dynamic getters and setters for protected members.
 *
 * Example, getting document root:
 * @code
 * $doc_root = CliEnvironment::getInstance()->documentRoot;
 * @endcode
 *
 * Example, CLI command using this command mapping interface:
 * @see \SimpleComplex\JsonLog\CliJsonLog
 *
 * @see Explorable
 *
 * @property-read CliCommand|null $command
 * @property-read array $inputArguments
 * @property-read array $inputOptions
 * @property-read array $inputOptionsShort
 * @property-read string[] $inputErrors
 * @property-read string $currentWorkingDir
 * @property-read string $documentRoot
 * @property-read int $documentRootDistance
 * @property-read string $vendorDir
 * @property-read bool $riskyCommandRequireConfirm
 *
 * Intended as singleton - ::getInstance() - but constructor not protected.
 *
 * @package SimpleComplex\Utils
 */
class CliEnvironment extends Explorable implements CliCommandInterface
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
        // Unsure about null ternary ?? for class and instance vars.
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        } elseif (!empty($constructorParams)) {
            static::$instance->registerCommands(...$constructorParams);
        }

        return static::$instance;
    }


    // General stuff.-----------------------------------------------------------

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
     * CLI confirm.
     *
     * @param string $question
     * @param string[] $confirmWords
     *      Default: [ 'yes', 'y' ].
     * @param string $continueMessage
     *      Empty: don't echo any 'Continuing...' message.
     * @param string $cancelMessage
     *
     * @return bool
     */
    public function confirm(
        string $question = 'Are you sure you want to do this? Type \'yes\' or \'y\' to continue:',
        array $confirmWords = ['yes', 'y'],
        string $continueMessage = '',
        string $cancelMessage = 'Aborted.'
    )
    {
        $this->echoMessage($question . ' ', '', true);
        $handle = fopen('php://stdin', 'r');
        $line = fgets($handle);
        $response = trim($line);
        fclose($handle);
        if (!in_array($response, $confirmWords)) {
            $this->echoMessage('');
            $this->echoMessage($cancelMessage, 'info');
            return false;
        }
        if ($continueMessage) {
            $this->echoMessage($continueMessage . "\n");
        } else {
            $this->echoMessage('');
        }
        return true;
    }

    /**
     * @var array
     */
    const MESSAGE_STATUS = [
        // Light red.
        'failure' => "\033[01;31m[error]\033[0m",
        // Syslog levels.
        'emergency' => "\033[01;31m[error]\033[0m",
        'alert' => "\033[01;31m[error]\033[0m",
        'critical' => "\033[01;31m[error]\033[0m",
        'error' => "\033[01;31m[error]\033[0m",
        // Light yellow.
        'warning' => "\033[01;33m[warning]\033[0m",
        // Cyan.
        'notice' => "\033[01;36m[notice]\033[0m",
        // White.
        'info' => "\033[01;37m[info]\033[0m",
        'debug' => "\033[01;37m[info]\033[0m",
        // /Syslog levels.
        // Light green.
        'success' => "\033[01;32m[success]\033[0m",
    ];

    /**
     * @uses Sanitize::cli()
     *
     * @param mixed $message
     *      Gets stringified, and sanitized.
     * @param string $status
     * @param bool $noTrailingNewline
     *
     * @return void
     *      Will echo arg message.
     */
    public function echoMessage($message, string $status = '', $noTrailingNewline = false) /*: void*/
    {
        if ($status) {
            if (!isset(static::MESSAGE_STATUS[$status])) {
                $status = 'error';
            }
            echo static::MESSAGE_STATUS[$status] . ' ';
        }
        echo Sanitize::getInstance()->cli('' . $message) . ($noTrailingNewline ? '' : "\n");
    }

    /**
     * @var array
     */
    const FORMAT = [
        'indent' => '  ',
    ];

    /**
     * @param string $str
     * @param mixed|string ...$formats
     *      String only, IDE stupid.
     *      Values: emphasize|hangingIndent
     *
     * @return string
     */
    public function format(string $str, string ...$formats) : string
    {
        foreach ($formats as $format) {
            switch ($format) {
                case 'indent':
                    $str = static::FORMAT['indent'] . str_replace("\n", "\n" . static::FORMAT['indent'], $str);
                    break;
                case 'hangingIndent':
                    $str = str_replace("\n", "\n" . static::FORMAT['indent'], $str);
                    break;
                case 'emphasize':
                    $str = "\033[01;37m" . $str . "\033[0m";
                    break;
                case 'italics':
                    $str = "\033[03m" . $str . "\033[0m";
                    break;
                default:
                    throw new \InvalidArgumentException('Unsupported format[' . $format . '].');
            }
        }
        return $str;
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
     * @var string[]
     */
    protected $inputErrors = [];

    /**
     * Read-only.
     * @var string
     */
    protected $documentRoot;

    /**
     * Read-only.
     * @var string
     */
    protected $vendorDir;

    /**
     * Read-only.
     * @var bool
     */
    protected $riskyCommandRequireConfirm;


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
        'vendorDir',
        'riskyCommandRequireConfirm',
    ];

    /**
     * @param string $name
     *
     * @return mixed
     *
     * @throws \OutOfBoundsException
     *      If no such instance property.
     */
    public function __get($name)
    {
        switch ('' . $name) {
            case 'command':
                if (!$this->inputResolved) {
                    $this->resolveInput();
                }
                if (!$this->command) {
                    $this->mapInputToCommand();
                }
                return $this->command;
            case 'inputArguments':
            case 'inputOptions':
            case 'inputOptionsShort':
                if (!$this->inputResolved) {
                    $this->resolveInput();
                }
                return $this->{'' . $name};
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
            case 'vendorDir':
                if (!$this->vendorDir) {
                    $this->vendorDir = Utils::getInstance()->vendorDir();
                }
                return $this->vendorDir;
            case 'riskyCommandRequireConfirm':
                if ($this->riskyCommandRequireConfirm === null) {
                    if (getenv('PHP_LIB_SIMPLECOMPLEX_UTILS_CLI_SKIP_CONFIRM')) {
                        $this->riskyCommandRequireConfirm = false;
                    } else {
                        if (!$this->documentRoot) {
                            $this->getDocumentRoot();
                        }
                        $this->riskyCommandRequireConfirm =
                            !file_exists($this->documentRoot . '/.risky_command_skip_confirm');
                    }
                }
                return $this->riskyCommandRequireConfirm;
        }
        throw new \OutOfBoundsException(get_class($this) . ' instance exposes no property[' . $name . '].');
    }

    /**
     * All accessible members are read-only.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return void
     *
     * @throws \OutOfBoundsException
     *      If no such instance property.
     * @throws \RuntimeException
     *      If that instance property is read-only.
     */
    public function __set($name, $value) /*: void*/
    {
        if (isset($this->explorableIndex['' . $name])) {
            throw new \OutOfBoundsException(get_class($this) . ' instance has no property[' . $name . '].');
        }
        throw new \RuntimeException(get_class($this) . ' instance property[' . $name . '] is read-only.');
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
    protected $commandRegistry = [];

    /**
     * @see CliEnvironment::getInstance()
     *
     * @throws \LogicException
     *      If not in cli mode.
     */
    public function __construct()
    {
        if (!static::cli()) {
            throw new \LogicException('This class is for cli mode only.');
        }

        // Dependencies.--------------------------------------------------------
        /**
         * @uses Sanitize
         * @see CliEnvironment::echoMessage
         */

        // Business.------------------------------------------------------------

        // Register general 'help' command, as first registered command.
        // Obsolete when this class is used for non-command purposes, but
        // the cost is negligible compared to the benefits when using commands.
        $this->registerCommands(
            new CliCommand(
                $this,
                'help',
                'Lists commands available. Example:' . "\n"
                . 'php script.phpsh command-name \'first arg value\' --some-option=\'whatever\' -x',
                [
                    'provider-or-command' =>
                        '(optional) Help for all that provider\'s commands. Or help for that command.',
                ],
                ['help' => ' '],
                ['h' => 'help']
            )
        );
    }

    /**
     * @param CliCommand[] ...$cliCommands
     *
     * @return void
     *
     * @throws \RuntimeException
     *      If a command name already registered; not unique.
     */
    public function registerCommands(CliCommand ...$cliCommands) /*: void*/
    {
        foreach ($cliCommands as $command) {
            if (isset($this->commandRegistry[$command->name])) {
                throw new \RuntimeException(
                    'Command named[' . $command->name . '] is not unique, already registered by class['
                    . get_class($this->commandRegistry[$command->name]->provider) . '].'
                );
            }
            $this->commandRegistry[$command->name] = $command;
        }
    }

    /**
     * Resolve console input arguments and options.
     *
     * Casts values of options:
     * - 'true'|'false': bool
     * - stringed number: int|float
     *
     * @throww \RuntimeException
     *      If globals argv is empty or non-existent.
     *
     * @return void
     */
    protected function resolveInput() /*: void*/
    {
        if (empty($GLOBALS['argv'])) {
            throw new \RuntimeException(
                'Global argv '
                . (isset($GLOBALS['argv']) ?
                    ' is empty, should at least contain a bucket holding name of executed script file.' :
                    ' does not exist.'
                )
            );
        }
        $this->inputResolved = true;

        // No need; shortOptToLongOpt has not been altered since last call.
        if ($this->inputArguments !== null) {
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
                                // Yes, ctype_... returns false on ''.
                                if (ctype_digit('' . $value)) {
                                    $value = (int) $value;
                                } elseif (is_numeric($value)) {
                                    $value = (float) $value;
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
     * Maps input arguments and options to a registered command.
     *
     * If fitting CliCommand found, the command's arguments and options will be
     * alterd (filtered) according to input arguments and options.
     *
     * @return void
     */
    protected function mapInputToCommand() /*: void*/
    {
        if ($this->command) {
            return;
        }
        if (!$this->inputResolved) {
            $this->resolveInput();
        }

        // General 'help' of this class owns the --help -h option.
        if (isset($this->inputOptions['help']) || isset($this->inputOptionsShort['h'])) {
            ($this->command = $command = $this->commandRegistry['help'])->setMapped();
            if ($this->inputArguments) {
                // Provider alias or command name, really.
                $command->arguments['provider-or-command'] = reset($this->inputArguments);
            } else {
                unset($command->arguments['provider-or-command']);
            }
        }
        elseif ($this->inputArguments) {
            $command_arg = reset($this->inputArguments);
            if (isset($this->commandRegistry[$command_arg])) {
                // Remove (the first) command argument.
                array_shift($this->inputArguments);
                ($this->command = $command = $this->commandRegistry[$command_arg])->setMapped();
                $n_args_supported = count($command->arguments);
                $n_args_input = count($this->inputArguments);
                if ($this->inputArguments) {
                    if ($command->arguments) {
                        reset($command->arguments);
                        reset($this->inputArguments);
                        do {
                            // Overwrite the CliCommand argument's description with
                            // input value.
                            $command->arguments[key($command->arguments)] = current($this->inputArguments);
                        } while (next($command->arguments) !== false && next($this->inputArguments) !== false);

                        if ($n_args_input > $n_args_supported) {
                            $this->inputErrors[] = $command->inputErrors[] = 'Command \'' . $command_arg . '\' only accepts '
                                . $n_args_supported . ' arguments, saw ' . $n_args_input . ' args.';
                        }
                    } else {
                        $this->inputErrors[] = $command->inputErrors[] = 'Command \'' . $command_arg
                            . '\' accepts no arguments, saw ' . ($n_args_input - 1) . ' args.';
                    }
                }
                // Remove surplus supported arguments.
                if ($n_args_supported > $n_args_input) {
                    $arg_keys = array_keys($command->arguments);
                    for ($i = $n_args_input; $i < $n_args_supported; ++$i) {
                        unset($command->arguments[$arg_keys[$i]]);
                    }
                }

                // preConfirmed?
                if (isset($this->inputOptions['yes'])) {
                    $command->preConfirmed = true;
                    unset($this->inputOptions['yes']);
                }
                // preConfirmed?
                if (isset($this->inputOptionsShort['y'])) {
                    $command->preConfirmed = true;
                    unset($this->inputOptionsShort['y']);
                }
                // Pre-confirmed by environment variable?
                if (!$command->preConfirmed && getenv('PHP_LIB_SIMPLECOMPLEX_UTILS_CLI_SKIP_CONFIRM')) {
                    $command->preConfirmed = true;
                }
                // Silent; no echo unless error?
                if (getenv('PHP_LIB_SIMPLECOMPLEX_UTILS_CLI_SILENT')) {
                    $command->silent = true;
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
                                $options_selected[$opt] = $this->inputOptions[$opt] ?? null;
                            }
                            unset($input_opts_rest[$opt]);
                        }
                        if ($input_opts_rest) {
                            $this->inputErrors[] = $command->inputErrors[] = 'Command '
                                . $this->format($command_arg, 'emphasize')
                                . ' doesn\'t support option(s): ' . join(', ', array_keys($input_opts_rest)) . '.';
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
                            $this->inputErrors[] = $command->inputErrors[] = 'Command '
                                . $this->format($command_arg, 'emphasize')
                                . ' doesn\'t support short options(s): ' . join(', ', $input_opts_rest) . '.';
                        }
                        unset($input_opts_rest);
                    }
                } else {
                    if ($this->inputOptions) {
                        $this->inputErrors[] = $command->inputErrors[] = 'Command '
                            . $this->format($command_arg, 'emphasize') . ' doesn\'t support options(s): '
                            . join(', ', array_keys($this->inputOptions)) . '.';
                    }
                    if ($this->inputOptionsShort) {
                        $this->inputErrors[] = $command->inputErrors[] = 'Command '
                            . $this->format($command_arg, 'emphasize') . ' doesn\'t support short options(s): '
                            . join(', ', array_keys($this->inputOptionsShort)) . '.';
                    }
                }

                if (!$this->inputErrors) {
                    $command->options = $options_selected;
                    // Not useful any more.
                    $command->shortToLongOption = null;
                }
            }
        }

        // No command matched: use 'help' command.
        if (!$this->command) {
            ($this->command = $command = $this->commandRegistry['help'])->setMapped();
            unset($command->arguments['provider-or-command']);
            if ($this->inputArguments) {
                $this->inputErrors[] = $command->inputErrors[] = 'Unknown command \''
                    . reset($this->inputArguments) . '\'.';
            }
        }
    }


    // CliCommandInterface.-----------------------------------------------------

    /**
     * @var string
     */
    const COMMAND_PROVIDER_ALIAS = 'cli-environment';

    /**
     * @return string
     */
    public function commandProviderAlias(): string
    {
        return static::COMMAND_PROVIDER_ALIAS;
    }

    /**
     * Finds command providers registered via .ini file placed in document root.
     *
     * File: [doc_root]/.utils_cli_command_providers.ini
     *
     * Format:
     * PSR-4 path to CliCommandInterface implementation, by command provider alias.
     *
     * @code
     * # Declare hook implementation in bash setup script:
     * echo 'command-provider-alias = \VendorName\PackageName\CliPackageName' >> ${doc_root}'/.utils_cli_command_providers.ini'
     * @endcode
     *
     * @return $this|CliEnvironment
     */
    public function commandProvidersLoad() : CliEnvironment
    {
        $file = $this->getDocumentRoot() . '/.utils_cli_command_providers.ini';
        if (file_exists($file)) {
            $name = '';
            try {
                $qualified_class_names = Utils::getInstance()->parseIniFile($file);
                foreach ($qualified_class_names as $name) {
                    // Namespaces apparantly shan't be double backslashed
                    // in this context.
                    $name_fixed = str_replace('\\\\', '\\', $name);
                    if (class_exists($name_fixed)) {
                        $interfaces = class_implements($name_fixed);
                        if ($interfaces && in_array(CliCommandInterface::class, $interfaces)) {
                            new $name_fixed();
                        } else {
                            $this->echoMessage(
                                'CLI command provider class doesn\'t implement CliCommandInterface: ' . $name . "\n",
                                'warning'
                            );
                        }
                    } else {
                        $this->echoMessage('CLI command provider class not found: ' . $name . "\n", 'warning');
                    }
                }
            }
            catch (ParseIniException $xcptn) {
                $this->echoMessage(
                    'CLI environment failed to parse CLI command provider .ini file: ' . $file
                    . "\n" . get_class($xcptn) . '@' . $file . ':' . $xcptn->getLine()
                    . ': ' . addcslashes($xcptn->getMessage(), "\0..\37") . "\n",
                    'error'
                );
            }
            catch (\Throwable $xcptn) {
                $this->echoMessage(
                    'CLI command provider class instantiation failure: ' . $name
                    . "\n" . get_class($xcptn) . '@' . $file . ':' . $xcptn->getLine()
                    . ': ' . addcslashes($xcptn->getMessage(), "\0..\37") . "\n",
                    'error'
                );
            }
        }
        return $this;
    }

    /**
     * Listens to input and forwards matched command
     * to it's provider.
     *
     * @return mixed
     *      Return value of the executed command, if any.
     */
    public function forwardMatchedCommand()
    {
        if (!$this->command) {
            $this->mapInputToCommand();
        }
        $provider_class = $this->command->provider;
        return $provider_class->executeCommand($this->command);
    }

    /**
     * This command provider (probably) only has a single command; 'help'.
     *
     * @see CliCommandInterface
     *
     * @param CliCommand $command
     *
     * @return void
     *      Must exit.
     *
     * @throws \LogicException
     *      If the command mapped by CliEnvironment
     *      isn't this provider's command.
     */
    public function executeCommand(CliCommand $command) /*: void*/
    {
        switch ($command->name) {
            case 'help':
                if ($this->inputErrors) {
                    foreach ($this->inputErrors as $msg) {
                        $this->echoMessage(
                            $this->format($msg, 'hangingIndent'),
                            'notice'
                        );
                    }
                    // Vertical space; newline.
                    $this->echoMessage('');
                } elseif (isset($command->arguments['provider-or-command'])) {
                    $provider_or_name = $command->arguments['provider-or-command'];
                    if (isset($this->commandRegistry[$provider_or_name])) {
                        // Print that command's help.
                        $this->echoMessage('' . $this->commandRegistry[$provider_or_name] . "\n");
                        exit;
                    }
                    $commands = [];
                    foreach ($this->commandRegistry as $cmd) {
                        if ($cmd->provider->commandProviderAlias() == $provider_or_name) {
                            // Get the command's help text.
                            $commands[] = '' . $cmd;
                        }
                    }
                    if ($commands) {
                        $this->echoMessage(
                            $this->format($provider_or_name, 'emphasize')
                            . ' commands:' . "\n\n" . join("\n\n", $commands) . "\n"
                        );
                        exit;
                    } else {
                        $this->echoMessage('Unkwown provider or command \''
                            . $provider_or_name . '\'.' . "\n", 'notice');
                    }
                }
                // Print 'help' command's help.
                $this->echoMessage('' . $command);
                // Print other commands' help.
                // Do not print the --help command twice.
                unset($this->commandRegistry['help']);
                if ($this->commandRegistry) {
                    $this->echoMessage("\n" . 'Commands:');
                    $providers = [];
                    foreach ($this->commandRegistry as $cmd) {
                        $providers[$cmd->provider->commandProviderAlias()] = true;
                        // Only echo first line of the each command's help output.
                        if (($pos = strpos('' . $cmd, "\n"))) {
                            $truncated = substr('' . $cmd, 0, $pos);
                            if ($truncated{strlen($truncated) - 1} !== '.') {
                                $truncated .= '...';
                            }
                            $this->echoMessage("\n" . $truncated);
                        } else {
                            $this->echoMessage("\n" . $cmd);
                        }
                    }
                    $this->echoMessage("\n" . 'Providers: ' . join(' ', array_keys($providers)) . "\n");
                }
                exit;
            default:
                throw new \LogicException(
                    'Command named[' . $command->name . '] is not provided by class[' . get_class($this) . '].'
                );
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
     * @return string
     *
     * @throws ConfigurationException
     *      If current working dir cannot be resolved.
     *      Citing http://php.net/manual/en/function.getcwd.php:
     *      On some Unix variants, getcwd() will return false if any one of the
     *      parent directories does not have the readable or search mode set,
     *      even if the current directory does.
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
        $path = realpath($path);
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
     * @see Cli::getCurrentWorkingDir()
     *
     * @param bool $noTaintEnvironment
     *      False: do set $_SERVER['DOCUMENT_ROOT'], if successful.
     *
     * @return string
     *      Empty: document root cannot be resolved.
     *
     * @throws ConfigurationException
     *      Propagated.
     */
    protected function getDocumentRoot($noTaintEnvironment = false) : string
    {
        if ($this->documentRoot) {
            return $this->documentRoot;
        }

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $this->documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);
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
     * @see Cli::getCurrentWorkingDir()
     *
     * @param bool $noTaintEnvironment
     *      False: do set $_SERVER['DOCUMENT_ROOT'], if successful.
     *
     * @return int|null
     *      Null: document root can't be determined, or you're not in the same
     *          file system branch as document root.
     *
     * @throws ConfigurationException
     *      Propagated.
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
     * @see Cli::getCurrentWorkingDir()
     *
     * @param bool $noTaintEnvironment
     *      False: do set $_SERVER['DOCUMENT_ROOT'], if successful.
     *
     * @return bool
     *      False: document root can't be determined, or you're not in the same
     *          file system branch as document root, or a chdir() call fails.
     *
     * @throws ConfigurationException
     *      If document root cannot be resolved; that is:
     *      'DOCUMENT_ROOT' environment var is non-existent/empty and there
     *      hasn't been placed a .document_root file in document root.
     * @throws ConfigurationException
     *      Propagated.
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
