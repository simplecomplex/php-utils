<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

/**
 * Cli command specification.
 *
 * Behaves as a foreachable and 'overloaded' container;
 * dynamic getters and setters for protected members.
 *
 * @see Explorable
 *
 * @package SimpleComplex\Utils
 */
class CliCommand extends Explorable
{
    /**
     * @var array
     */
    protected $explorableIndex = [
        'provider',
        'name',
        'description',
        'arguments',
        'argumentDescriptions',
        'options',
        'shortToLongOption',
        'preConfirmed',
        'inputErrors',
    ];

    /**
     * @var CliCommandInterface
     */
    public $provider;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $description;

    /**
     * @var array
     */
    public $arguments = [];

    /**
     * @var array|null
     */
    public $argumentDescriptions;

    /**
     * @var array
     */
    public $options = [];

    /**
     * @var array|null
     */
    public $optionDescriptions;

    /**
     * @var array|null
     */
    public $shortToLongOption = [];

    /**
     * @var array|null
     */
    public $shortToLongDescriptions;

    /**
     * Whether user flagged 'do not ask for confirmation' via input.
     *
     * @var bool
     */
    public $preConfirmed = false;

    /**
     * @see CliEnvironment::mapInputToCommand()
     *
     * @var string[]
     */
    public $inputErrors = [];

    /**
     * @var array
     */
    const REGEX = [
        'name' => '/^[a-z][a-z\d\-]*$/',
        'argument' => '/^[^\-].*$/',
        'option' => '/^[a-z][a-z\d_\-]*$/',
        'shortOpts' => '/^[a-zA-Z]+$/',
    ];

    /**
     * Options yes, no, y, n are reserved as generic confirm/cancel.
     * Option help is furthermore reserved for the general help command.
     *
     * @var string[]
     */
    const RESERVED_OPTIONS = [
        'yes',
        'no',
        'y',
        'n',
    ];

    /**
     * Short option y reserved as generic confirm.
     * Short option h is furthermore reserved for the general help command.
     *
     * @var string[]
     */
    const RESERVED_SHORTOPTS = [
        'y',
    ];

    /**
     * Specify a cli command.
     *
     * @param CliCommandInterface $provider
     * @param string $name
     * @param string $description
     * @param string[] $arguments
     *      Key: argument name.
     *      Value: argument description.
     * @param array $options
     *      Key: option name. 'help' is not allowed.
     *      Value: option description.
     * @param array $shortToLongOption
     *      Key: short option. 'h' and 'H' are not allowed.
     *      Value: option name.
     *
     * @throws \LogicException
     * @throws \InvalidArgumentException
     */
    public function __construct(
        CliCommandInterface $provider, string $name, string $description,
        array $arguments = [], array $options = [], array $shortToLongOption = []
    ) {
        $provider_alias = $provider->commandProviderAlias();
        if (!$provider_alias || !preg_match(static::REGEX['name'], $provider_alias)) {
            throw new \LogicException(
                'Alias of command provider class[' . get_class($provider) . '] is not a valid lisp-cased name, regex '
                . static::REGEX['name'] . '.'
            );
        }
        $this->provider = $provider;
        if (!$name || !preg_match(static::REGEX['name'], $name)) {
            throw new \InvalidArgumentException(
                'Arg name is not a valid lisp-cased name, regex ' . static::REGEX['name'] . '.'
            );
        }
        $this->name = $name;
        if (!$description) {
            throw new \InvalidArgumentException('Arg description is not non-empty.');
        }
        $this->description = $description;

        $i = -1;
        foreach ($arguments as $k => $v) {
            ++$i;
            $arg_name = '' . $k;
            $arg_dscrptn = '' . $v;
            if (!$arg_name || !preg_match(static::REGEX['argument'], $arg_name)) {
                throw new \InvalidArgumentException('Arg arguments element ' . $i
                    . ' key (argument name) is not valid; regex ' . static::REGEX['argument'] . '.');
            }
            if (!$arg_dscrptn) {
                throw new \InvalidArgumentException('Arg arguments element ' . $i
                    . ' value (argument description) is not non-empty.');
            }
            $this->arguments[$arg_name] = $arg_dscrptn;
        }

        $i = -1;
        foreach ($options as $k => $v) {
            ++$i;
            $opt_name = '' . $k;
            $opt_dscrptn = '' . $v;
            if (!$opt_name || !preg_match(static::REGEX['option'], $opt_name)) {
                throw new \InvalidArgumentException('Arg options element ' . $i
                    . ' key[' . $opt_name . '] is not valid; regex ' . static::REGEX['option'] . '.');
            }
            if (in_array($opt_name, static::RESERVED_OPTIONS)) {
                throw new \InvalidArgumentException('Arg options element ' . $i
                    . ' key[' . $opt_name . '] is reserved for generic purposes, option[' . $opt_name . '].');
            }
            if ($name != 'help' && $opt_name == 'help') {
                throw new \InvalidArgumentException('Arg options element ' . $i
                    . ' key[' . $opt_name . '] is reserved by general help command, option[' . $opt_name . '].');
            }
            if (!$opt_dscrptn) {
                throw new \InvalidArgumentException('Arg options element ' . $i
                    . ' value (option description) is not non-empty.');
            }
            $this->options[$opt_name] = $opt_dscrptn;
        }

        $i = -1;
        foreach ($shortToLongOption as $k => $v) {
            ++$i;
            $short = '' . $k;
            $opt_name = '' . $v;
            if (strlen($short) != 1 || !preg_match(static::REGEX['shortOpts'], $short)) {
                throw new \InvalidArgumentException('Arg shortToLongOption element ' . $i
                    . ' key[' . $short . '] is not a single ASCII letter.');
            }
            if (!$opt_name || !preg_match(static::REGEX['option'], $opt_name)) {
                throw new \InvalidArgumentException('Arg shortToLongOption element ' . $i
                    . ' value[' . $opt_name . '] is not valid; regex ' . static::REGEX['option'] . '.');
            }
            if (in_array($short, static::RESERVED_OPTIONS)) {
                throw new \InvalidArgumentException('Arg shortToLongOption element ' . $i
                    . ' key[' . $short . '] is reserved for generic purposes, short[' . $short . '].');
            }
            if ($name != 'help' && $short == 'h') {
                throw new \InvalidArgumentException('Arg shortToLongOption element ' . $i
                    . ' key[' . $short . '] is reserved by general help command, short[' . $short . '].');
            }
            if (!isset($this->options[$opt_name])) {
                throw new \InvalidArgumentException('Arg shortToLongOption element ' . $i
                    . ' value[' . $opt_name . '] is not declared as option in the (previous) options arg.');
            }
            $this->shortToLongOption[$short] = $opt_name;
        }
    }

    /**
     * Tell that this command has been mapped successfully.
     *
     * @see CliEnvironment::mapInputToCommand()
     *
     * @return void
     */
    public function setMapped() /*: void*/
    {
        // Copy; save argument and option descriptions for help.
        $this->argumentDescriptions = $this->arguments;
        $this->optionDescriptions = $this->options;
        $this->shortToLongDescriptions = $this->shortToLongOption;
        // Remove these now redundants from explorations.
        array_splice($this->explorableIndex, array_search('description', $this->explorableIndex), 1);
        array_splice($this->explorableIndex, array_search('shortToLongOption', $this->explorableIndex), 1);
    }

    /**
     * @var array
     */
    const FORMAT = [
        'newline' => "\n",
        'indent' => ' ',
        'midLine' => 30,
        'wrap' => 110,
    ];

    /**
     * @return string
     */
    public function __toString() : string {
        $nl = static::FORMAT['newline'];

        $output = static::FORMAT['indent'] . CliEnvironment::getInstance()->format($this->name, 'emphasize')
            . str_repeat(' ', static::FORMAT['midLine'] - strlen(static::FORMAT['indent'] . ($this->name)))
            . wordwrap(
                str_replace("\n", "\n" . str_repeat(' ', static::FORMAT['midLine']), $this->description),
                static::FORMAT['wrap'] - static::FORMAT['midLine'],
                $nl . str_repeat(' ', static::FORMAT['midLine'])
            );

        $output .= $nl . static::FORMAT['indent'] . static::FORMAT['indent'] . 'Arguments:';

        if (!empty($this->argumentDescriptions)) {
            $args =& $this->argumentDescriptions;
        } else {
            $args =& $this->arguments;
        }
        if (!count($args)) {
            $output .= ' none';
        } else {
            foreach ($args as $name => $dscrptn) {
                $line = str_repeat(static::FORMAT['indent'], 3) . $name;
                $output .= $nl . $line . str_repeat(' ', static::FORMAT['midLine'] - strlen($line))
                    . wordwrap(
                        str_replace("\n", "\n" . str_repeat(' ', static::FORMAT['midLine']), $dscrptn),
                        static::FORMAT['wrap'] - static::FORMAT['midLine'],
                        $nl . str_repeat(' ', static::FORMAT['midLine'])
                    );
            }
        }

        $output .= $nl . static::FORMAT['indent'] . static::FORMAT['indent'] . 'Options:';
        if (!empty($this->optionDescriptions)) {
            $opts =& $this->optionDescriptions;
        } else {
            $opts =& $this->options;
        }
        if (!empty($this->shortToLongDescriptions)) {
            $short_opts =& $this->shortToLongDescriptions;
        } else {
            $short_opts =& $this->shortToLongOption;
        }
        if (!count($opts)) {
            $output .= ' none';
        } else {
            foreach ($opts as $name => $dscrptn) {
                $line = str_repeat(static::FORMAT['indent'], 3) . '--' . $name;
                foreach ($short_opts as $short => $long) {
                    if ($long == $name) {
                        $line .= ' -' . $short;
                    }
                }
                $output .= $nl . $line . str_repeat(' ', static::FORMAT['midLine'] - strlen($line))
                    . wordwrap(
                        str_replace("\n", "\n" . str_repeat(' ', static::FORMAT['midLine']), $dscrptn),
                        static::FORMAT['wrap'] - static::FORMAT['midLine'],
                        $nl . str_repeat(' ', static::FORMAT['midLine'])
                    );
            }
        }

        return $output;
    }
}