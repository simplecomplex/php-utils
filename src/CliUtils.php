<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use SimpleComplex\Utils\Interfaces\CliCommandInterface;

/**
 * CLI only.
 *
 * Expose/execute Utils commands.
 *
 * @see simple_complex_utils_cli()
 *
 * @code
 * # CLI
 * cd vendor/simplecomplex/utils/src/cli
 * php cli.php utils -h
 * @endcode
 *
 * @package SimpleComplex\Utils
 */
class CliUtils implements CliCommandInterface
{
    /**
     * @var string
     */
    const COMMAND_PROVIDER_ALIAS = 'utils';

    /**
     * Registers Utils' CliCommands at CliEnvironment.
     *
     * @throws \LogicException
     *      If executed in non-CLI mode.
     */
    public function __construct()
    {
        if (!CliEnvironment::cli()) {
            throw new \LogicException('Cli mode only.');
        }

        // Declare provided commands.
        (CliEnvironment::getInstance())->registerCommands(
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-execute',
                '(RISKY) Execute included PHP script.',
                [
                    'include-file' => 'Relative or absolute path and filename.'
                ],
                [
                    'force' => 'With -y/--yes execute without interactive confirmation despite risk.',
                ],
                [
                    'f' => 'force',
                ]
            )
        );
    }

    /**
     * @var CliCommand
     */
    protected $command;

    /**
     * @var CliEnvironment
     */
    protected $environment;

    /**
     * Executes include script.
     *
     * @see simplecomplex_utils_include_script_example()
     *
     * Ignores pre-confirmation --yes/-y option.
     * And 'silent' is neither respected by this command.
     *
     * @return void
     *      Exits.
     *
     * @throws \Throwable
     */
    protected function cmdExecute() /*: void*/
    {
        /**
         * @see simplecomplex_utils_cli()
         */
        $container = Dependency::container();

        $utils = Utils::getInstance();

        // Validate input. ---------------------------------------------
        $path = $file = $path_file = '';
        if (empty($this->command->arguments['include-file'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['include-file']) ?
                'Missing \'include-file\' argument.' : 'Empty \'include-file\' argument.';
        } else {
            $include_file = $this->command->arguments['include-file'];
            $path = dirname($include_file);
            $file = basename($include_file);
            try {
                $path = $utils->resolvePath($path);
                $path_file = $path . '/' . $file;
                if (!file_exists($path_file) || !is_file($path_file)) {
                    $this->command->inputErrors[] = 'Arg include-file resolved to[' . $path_file . ']'
                        . (!file_exists($path_file) ? ' doesn\'t exist' : ' is not a file') . '.';
                }
            } catch (\Throwable $xcptn) {
                $this->command->inputErrors[] = 'Arg include-file[' . $include_file . '] is not a valid path+filename.'
                    . "\n" . $container->get('inspect')->variable($xcptn)->toString(false);
            }
        }
        $force = !empty($this->command->options['force']);
        // Pre-confirmation --yes/-y ignored for this command,
        // unless combined with --force/-f.
        // And 'silent' is neither allowed.
        if ($this->command->preConfirmed && !$force) {
            $this->command->inputErrors[] = 'Pre-confirmation \'yes\'/-y option not supported for this command,'
                . ' unless combined with \'force\'/-f option.';
        }
        if ($this->command->inputErrors) {
            foreach ($this->command->inputErrors as $msg) {
                $this->environment->echoMessage(
                    $this->environment->format($msg, 'hangingIndent'),
                    'notice'
                );
            }
            // This command's help text.
            $this->environment->echoMessage("\n" . $this->command);
            exit;
        }
        // Display command and the arg values used.---------------------
        if (!$this->command->preConfirmed || !$force) {
            $this->environment->echoMessage(
                $this->environment->format(
                    $this->environment->format($this->command->name, 'emphasize')
                    . "\n" . 'include-file (resolved): '
                    . $utils->pathReplaceDocumentRoot($path) . '/' . $this->environment->format($file, 'emphasize'),
                    'hangingIndent'
                )
            );
        }
        // Request confirmation, ignore --yes/-y pre-confirmation option;
        // ignore .risky_command_skip_confirm file placed in document root
        // ...unless combined with --force/-f option.
        if (
            (!$this->command->preConfirmed || !$force)
            && !$this->environment->confirm(
                '(RISKY) Execute that include script? Type \'yes\' to continue:',
                ['yes'],
                '',
                'Aborted executing include script.'
            )
        ) {
            exit;
        }
        // Check if the command is doable.------------------------------
        // Nothing to check here.
        try {
            // Prevent scope bleed.
            (function($pathFile) {
                include($pathFile);
            })($path_file);
            // Apparant success, at least no exception thrown.
            $this->environment->echoMessage(
                'Executed include script '
                . $utils->pathReplaceDocumentRoot($path) . '/' . $this->environment->format($file, 'emphasize'),
                'notice'
            );
        } catch (\Throwable $xcptn) {
            $this->environment->echoMessage(
                'Executing include script ' . $path . '/' . $this->environment->format($file, 'emphasize')
                . "\n" . ' produced error.',
                'warning'
            );
            /**
             * Pass on to
             * @see Bootstrap::setExceptionHandler()
             */
            throw $xcptn;
        }
        exit;
    }


    // CliCommandInterface.-----------------------------------------------------

    /**
     * @return string
     */
    public function commandProviderAlias(): string
    {
        return static::COMMAND_PROVIDER_ALIAS;
    }

    /**
     * @param CliCommand $command
     *
     * @return mixed
     *      Return value of the executed command, if any.
     *      May well exit.
     *
     * @throws \LogicException
     *      If the command mapped by CliEnvironment
     *      isn't this provider's command.
     */
    public function executeCommand(CliCommand $command)
    {
        $this->command = $command;
        $this->environment = CliEnvironment::getInstance();

        switch ($command->name) {
            case static::COMMAND_PROVIDER_ALIAS . '-execute':
                $this->cmdExecute();
                exit;
            default:
                throw new \LogicException(
                    'Command named[' . $command->name . '] is not provided by class[' . get_class($this) . '].'
                );
        }
    }
}
