<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use SimpleComplex\Utils\Exception\InvalidArgumentException;

/**
 * Cli command specification.
 *
 * Behaves as a foreachable and 'overloaded' collection;
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
     * @var array
     */
    public $shortToLongOption = [];

    /**
     * @var array
     */
    const REGEX = [
        'name' => '/^[a-z][a-z\d_\-]*$/',
        'argument' => '/^[^\-].*$/',
        // h an H are not allowed, because they are use generically for 'help'.
        'shortOpts' => '/^[a-gi-zA-GI-Z]+$/',
    ];

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
     */
    public function __construct(
        string $name, string $description, array $arguments = [], array $options = [], array $shortToLongOption = []
    ) {
        if (!$name || !preg_match(static::REGEX['name'], $name)) {
            throw new InvalidArgumentException('Arg name is not a valid name.');
        }
        $this->name = $name;
        if (!$description) {
            throw new InvalidArgumentException('Arg description is not non-empty.');
        }
        $this->description = $description;

        $i = -1;
        foreach ($arguments as $k => $v) {
            ++$i;
            $arg_name = '' . $k;
            $arg_dscrptn = '' . $v;
            if (!$arg_name || !preg_match(static::REGEX['argument'], $arg_name)) {
                throw new InvalidArgumentException('Arg arguments element . ' . $i
                    . ' key (argument name) is not a valid name.');
            }
            if (!$arg_dscrptn) {
                throw new InvalidArgumentException('Arg arguments element . ' . $i
                    . ' value (argument description) is not a non-empty.');
            }
            $this->arguments[$arg_name] = $arg_dscrptn;
        }

        $i = -1;
        foreach ($options as $k => $v) {
            ++$i;
            $opt_name = '' . $k;
            $opt_dscrptn = '' . $v;
            if (!$opt_name || !preg_match(static::REGEX['name'], $opt_name)) {
                throw new InvalidArgumentException('Arg options element . ' . $i
                    . ' key (option name) is not a valid name.');
            }
            if (!$opt_name == 'help') {
                throw new InvalidArgumentException('Arg options element . ' . $i
                    . ' key (option name) \'help\' is not allowed because used for generic purposes.');
            }
            if (!$opt_dscrptn) {
                throw new InvalidArgumentException('Arg options element . ' . $i
                    . ' value (option description) is not a non-empty.');
            }
            $this->options[$opt_name] = $opt_dscrptn;
        }

        $i = -1;
        foreach ($shortToLongOption as $k => $v) {
            ++$i;
            $short = '' . $k;
            $opt_name = '' . $v;
            if (strlen($short) != 1 || !preg_match(static::REGEX['shortOpts'], $short)) {
                throw new InvalidArgumentException('Arg shortToLongOption element . ' . $i
                    . ' key (short) is not a single ASCII letter.');
            }
            if (!$opt_name || !preg_match(static::REGEX['name'], $opt_name)) {
                throw new InvalidArgumentException('Arg shortToLongOption element . ' . $i
                    . ' value (option name) is not a valid name.');
            }
            if (!isset($this->options[$opt_name])) {
                throw new InvalidArgumentException('Arg shortToLongOption element . ' . $i
                    . ' value (option name) is not declared as option in the (previous) options arg.');
            }
            $this->shortToLongOption[$short] = $opt_name;
        }
    }

    const FORMAT = [
        'newline' => "\n",
        'indent' => ' ',
        'midLine' => 35,
        'wrap' => 90,
    ];

    /**
     * @param string $indent
     * @param int $descriptionStart
     * @param int $wrap
     *
     * @return string
     */
    public function help(string $indent = ' ', int $descriptionStart = 35, int $wrap = 90) : string {
        $line = $indent . $this->name;
        $output = "\n" . "\n" . $line . str_repeat(' ', $descriptionStart - strlen($line))
            . wordwrap($this->description, $wrap - $descriptionStart, "\n" . str_repeat(' ', $descriptionStart))
            . "\n" . $indent . $indent . 'Arguments:';
        if (!count($this->arguments)) {
            $output .= ' none';
        } else {
            foreach ($this->arguments as $name => $dscrptn) {
                $line = str_repeat($indent, 3) . $name;
                $output .= "\n" . $line . str_repeat(' ', $descriptionStart - strlen($line))
                    . wordwrap($dscrptn, $wrap - $descriptionStart, "\n" . str_repeat(' ', $descriptionStart));
            }
        }
        $output .= "\n" . $indent . $indent . 'Options:';
        if (!count($this->options)) {
            $output .= ' none';
        } else {
            foreach ($this->options as $name => $dscrptn) {
                $line = str_repeat($indent, 3) . '--' . $name;
                foreach ($this->shortToLongOption as $short => $long) {
                    if ($long == $name) {
                        $line .= ' -' . $short;
                    }
                }
                $output .= "\n" . $line . str_repeat(' ', $descriptionStart - strlen($line))
                    . wordwrap($dscrptn, $wrap - $descriptionStart, "\n" . str_repeat(' ', $descriptionStart));
            }
        }
        return $output;
    }
}