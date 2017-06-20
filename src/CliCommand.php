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
        'name',
        'description',
        'arguments',
        'options',
        'shortToLongOption',
    ];

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
     * @var array
     */
    public $options = [];

    /**
     * @var array|null
     */
    public $shortToLongOption = [];

    /**
     * @var array
     */
    const REGEX = [
        'name' => '/^[a-z][a-z\d_\-]*$/',
        'argument' => '/^[^\-].*$/',
        'option' => '/^[a-z][a-z\d_\-]*$/',
        'shortOpts' => '/^[a-zA-Z]+$/',
    ];

    // @todo: command name must always be lisp-cased, not snake_cased; otherwise mapping gets ambibiguous.

    // @todo: classes exposing commands must implement (new) CliCommandInterface, requiring executeOnMatch() method.

    /**
     * Specify a cli command.
     *
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
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $name, string $description, array $arguments = [], array $options = [], array $shortToLongOption = []
    ) {
        if (!$name || !preg_match(static::REGEX['name'], $name)) {
            throw new \InvalidArgumentException('Arg name is not a valid command name, regex ' . static::REGEX['name'] . '.');
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
                throw new \InvalidArgumentException('Arg arguments element . ' . $i
                    . ' key (argument name) is not valid; regex ' . static::REGEX['argument'] . '.');
            }
            if (!$arg_dscrptn) {
                throw new \InvalidArgumentException('Arg arguments element . ' . $i
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
                throw new \InvalidArgumentException('Arg options element . ' . $i
                    . ' key (option name) is not valid; regex ' . static::REGEX['option'] . '.');
            }
            if (!$opt_dscrptn) {
                throw new \InvalidArgumentException('Arg options element . ' . $i
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
                throw new \InvalidArgumentException('Arg shortToLongOption element . ' . $i
                    . ' key (short) is not a single ASCII letter.');
            }
            if (!$opt_name || !preg_match(static::REGEX['option'], $opt_name)) {
                throw new \InvalidArgumentException('Arg shortToLongOption element . ' . $i
                    . ' value (option name) is not valid; regex ' . static::REGEX['option'] . '.');
            }
            if (!isset($this->options[$opt_name])) {
                throw new \InvalidArgumentException('Arg shortToLongOption element . ' . $i
                    . ' value (option name) is not declared as option in the (previous) options arg.');
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
        'midLine' => 40,
        'wrap' => 100,
    ];

    /**
     * @return string
     */
    public function __toString() : string {
        $nl = static::FORMAT['newline'];

        $line = static::FORMAT['indent'] . $this->name;
        $output = $line . str_repeat(' ', static::FORMAT['midLine'] - strlen($line))
            . wordwrap(
                str_replace("\n", "\n" . str_repeat(' ', static::FORMAT['midLine']), $this->description),
                static::FORMAT['wrap'] - static::FORMAT['midLine'],
                $nl . str_repeat(' ', static::FORMAT['midLine'])
            );

        $output .= $nl . static::FORMAT['indent'] . static::FORMAT['indent'] . 'Arguments:';
        if (!count($this->arguments)) {
            $output .= ' none';
        } else {
            foreach ($this->arguments as $name => $dscrptn) {
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
        if (!count($this->options)) {
            $output .= ' none';
        } else {
            foreach ($this->options as $name => $dscrptn) {
                $line = str_repeat(static::FORMAT['indent'], 3) . '--' . $name;
                foreach ($this->shortToLongOption as $short => $long) {
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