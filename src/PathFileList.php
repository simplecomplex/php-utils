<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

/**
 * List all files in path, recursively, optionally requiring specific
 * file extension(s).
 *
 * Array-like object, numerically indexed.
 * Skips . and ..
 * Converts backslash directory separator to forward slash.
 * Defaults to follow symbolic links.
 * Defaults to skip .hidden directories and files.
 *
 * - - -
 *
 * Without declaring a dedicated recursive method implementing this procedure
 * would get extremely complicated (and in-flexible).
 * See dev/PathFileList-via-4-iterators.php, which to accomplish the same  uses:
 * - a RecursiveIteratorIterator
 * - a custom RecursiveFilterIterator
 * - a RecursiveDirectoryIterator
 * - a custom FilterIterator
 *
 * @package SimpleComplex\Utils
 */
class PathFileList extends \ArrayObject
{
    /**
     * Flag: don't follow symbolic links.
     *
     * Default: do follow symlinks.
     */
    const SKIP_SYMLINKS = 1;

    /**
     * Flag: do include dot-files; .hidden.
     *
     * Default: skip hidden dirs and files.
     */
    const INCLUDE_HIDDEN = 2;

    /**
     * Does not use filenames as keys and do not require that filenames
     * are unique across directories.
     *
     * @var bool
     */
    const FILENAMES_UNIQUE = false;

    /**
     * @var string
     */
    public $path;

    /**
     * Example: ['ini'].
     *
     * @var array
     */
    public $requireExtensions;

    /**
     * Example: ['whatever.ini'].
     *
     * @var bool
     */
    public $requireLongExtensions = false;

    /**
     * @var int
     */
    public $maxDepth;

    /**
     * @var int
     */
    public $flags;

    /**
     * PathFileList constructor.
     *
     * @see PathFileListUnique::FILENAMES_UNIQUE
     *
     * @param string $path
     * @param array $requireExtensions
     *      Empty: list all files, disregarding extensions.
     * @param int $maxDepth
     *      Recursion limit.
     *      Zero: only immediate children.
     * @param int $flags
     *
     * @throws \InvalidArgumentException
     *      Arg path doesn't exist or isn't a directory.
     *      Arg maxDepth negative.
     * @throws \RuntimeException
     *      Propagated, if class constant FILENAMES_UNIQUE
     *      and non-unique filename found.
     */
    public function __construct(string $path, array $requireExtensions = [], int $maxDepth = 9, int $flags = 0)
    {
        if (!file_exists($path) || !is_dir($path)) {
            throw new \InvalidArgumentException(
                'Arg path doesn\'t exist, path[' . $path . '].'
            );
        }
        if (!is_dir($path)) {
            throw new \InvalidArgumentException(
                'Arg path is not a directory, path[' . $path . '].'
            );
        }
        $this->path = $path;

        if ($requireExtensions) {
            foreach ($requireExtensions as $ext) {
                if (strpos('.', $ext)) {
                    $this->requireLongExtensions = true;
                    break;
                }
            }
            $this->requireExtensions = $requireExtensions;
        }

        if ($maxDepth < 0) {
            throw new \InvalidArgumentException(
                'Arg maxDepth cannot be negative, maxDepth[' . $maxDepth . '].'
            );
        }
        $this->maxDepth = $maxDepth;
        if ($flags < 0) {
            throw new \InvalidArgumentException(
                'Arg flags cannot be negative, flags[' . $flags . '].'
            );
        }
        $this->flags = $flags;

        parent::__construct();

        $this->traverseRecursively($path);
    }

    /**
     * @see PathFileListUnique::FILENAMES_UNIQUE
     *
     * @param string $path
     * @param int $depth
     *
     * @throws \RuntimeException
     *      If class constant FILENAMES_UNIQUE and non-unique filename found.
     */
    protected function traverseRecursively(string $path, int $depth = 0)
    {
        if ($depth > $this->maxDepth) {
            return;
        }
        $iterator = new \FilesystemIterator(
            $path,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS
            | (($this->flags & self::SKIP_SYMLINKS) ? 0 : \FilesystemIterator::FOLLOW_SYMLINKS)
        );
        foreach ($iterator as $item) {
            $filename = $item->getFilename();
            if (!($this->flags & self::INCLUDE_HIDDEN) && $filename{0} == '.') {
                continue;
            }
            if ($item->isDir()) {
                $this->traverseRecursively($item->getPathname(), $depth + 1);
            } else {
                $matches = false;
                if (!$this->requireExtensions) {
                    $matches = true;
                } elseif (!$this->requireLongExtensions && in_array($item->getExtension(), $this->requireExtensions, true)) {
                    $matches = true;
                } else {
                    foreach ($this->requireExtensions as $ext) {
                        // Quick and dirty; if our long extension isn't at the end
                        // it will be a false positive.
                        if (strpos($filename, '.' . $ext) !== false) {
                            $matches = true;
                            break;
                        }
                    }
                }
                if ($matches) {
                    if (!static::FILENAMES_UNIQUE) {
                        $this->append($item->getPathname());
                    } elseif ($this->offsetExists($filename)) {
                        throw new \RuntimeException(
                            'Non-unique filename[' . $filename . '] found in paths[' . $this->offsetGet($filename) . ']'
                            . ' and[' . $item->getPathname() . '].'
                        );
                    } else {
                        $this->offsetSet($filename, $item->getPathname());
                    }
                }
            }
        }
    }
}
