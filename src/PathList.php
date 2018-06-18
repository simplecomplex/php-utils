<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Utils;

use SimpleComplex\Utils\Exception\FileNonUniqueException;

/**
 * List all files or directories in a path recursively,
 * optionally requiring, including, excluding by certain criteria.
 *
 * Array-like object, numerically indexed unless requireUnique;
 * then assocative keyed by filenames (or dirnames).
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
class PathList extends \ArrayObject
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var int
     */
    protected $maxDepth;

    /**
     * @var bool
     */
    protected $skipSymLinks;

    /**
     * @var bool
     */
    protected $traverseHidden = false;

    /**
     * Values:
     * - zero: find files
     * - one: find directories
     * - two: find directories and traverse matching dirs too.
     *
     * @var int
     */
    protected $dirs = 0;

    /**
     * @var bool
     */
    protected $requireUnique = false;

    /**
     * @var bool
     */
    protected $includeHidden = false;

    /**
     * @var string[]
     */
    protected $includeNames = [];

    /**
     * @var string[]
     */
    protected $excludeNames = [];

    /**
     * @var string[]
     */
    protected $includeExtensions = [];

    /**
     * @var string[]
     */
    protected $excludeExtensions = [];

    /**
     * One of $requireExtensions has dot(s) within the extension.
     *
     * Example: ['whatever.ini'].
     *
     * @var bool
     */
    protected $longExtensions = false;

    /**
     * PathList constructor.
     *
     * @code
     * // Usage:
     * $files = (new PathList('/absolute/path'))->includeExtensions('ini')->find();
     * @endcode
     *
     * @see \FilesystemIterator
     *
     * @param string $path
     *      Allowed to be empty; then use path() before find().
     * @param int $maxDepth
     *      Recursion limit.
     *      Zero: only immediate children.
     * @param bool $skipSymLinks.
     *      True: don't follow symbolic links.
     *
     * @throws \InvalidArgumentException
     *      Propagated; arg path doesn't exist or isn't a directory.
     *      Arg maxDepth negative.
     * @throws FileNonUniqueException
     *      Propagated; if requireUnique and non-unique filename found.
     */
    public function __construct(string $path = '', int $maxDepth = 9, bool $skipSymLinks = false)
    {
        if ($path !== '') {
            $this->path($path);
        }

        if ($maxDepth < 0) {
            throw new \InvalidArgumentException(
                'Arg maxDepth cannot be negative, maxDepth[' . $maxDepth . '].'
            );
        }
        $this->maxDepth = $maxDepth;
        $this->skipSymLinks = $skipSymLinks;

        parent::__construct();
    }

    /**
     * Execute search.
     *
     * @return $this|PathList
     *
     * @throws \LogicException
     *      Instance var path is empty.
     * @throws FileNonUniqueException
     *      Propagated; if requireUnique and non-unique filename found.
     */
    public function find() : PathList
    {
        if ($this->path === null) {
            throw new \LogicException('Can\'t find() when var path is empty.');
        }
        $this->traverseRecursively($this->path);
        return $this;
    }

    /**
     * Clear list of items found.
     *
     * @return $this|PathList
     */
    public function reset() : PathList
    {
        $this->exchangeArray([]);
        return $this;
    }

    /**
     * Set path to look in.
     *
     * @param string $path
     *
     * @return $this|PathList
     */
    public function path(string $path) : PathList
    {
        if (!file_exists($path)) {
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
        return $this;
    }

    /**
     * Traverse .hidden dirs.
     *
     * If looking for dirs and set to travers-matching and a matching dir
     * is .hidden, then it will be traversed no matter this setting.
     * @see PathList::dirs()
     *
     * @return $this|PathList
     */
    public function traverseHidden()
    {
        $this->traverseHidden = true;
        return $this;
    }

    /**
     * Find directories, not files.
     *
     * @param bool $traverseMatches
     *      True: traverse matching dirs too.
     *
     * @return $this|PathList
     */
    public function dirs(bool $traverseMatches = false) : PathList
    {
        $this->dirs = !$traverseMatches ? 1 : 2;
        return $this;
    }

    /**
     * Require unique filenames (dirnames) and throw FileNonUniqueException
     * on first non-unique found.
     *
     * The list becomes associative array keyed by filenames (dirnames),
     * instead of numerically indexed array.
     *
     * @return $this|PathList
     */
    public function requireUnique() : PathList
    {
        $this->requireUnique = true;
        return $this;
    }

    /**
     * Include .dotted filenames (dirnames if dirs).
     *
     * The default is to exclude hiddens, disregarding all other filters.
     * Except if includeNames and a name is .dotted.
     * @see PathList::includeNames()
     *
     * @return $this|PathList
     */
    public function includeHidden() : PathList
    {
        $this->includeHidden = true;
        return $this;
    }

    /**
     * Require that filename (dirname if dirs) matches one of these names.
     *
     * Incompatible with excludeNames and includeExtensions.
     *
     * Sets includeHidden to true if any of the names has leading dot.
     *
     * @param string[] $names
     *      List of filenames; dirnames if dirs.
     *
     * @return $this|PathList
     *
     * @throws \LogicException
     *      If non-empty excludeNames and includeExtensions.
     */
    public function includeNames(array $names) : PathList
    {
        if ($this->excludeNames) {
            throw new \LogicException('Can\'t set includeNames when non-empty excludeNames.');
        }
        if ($this->includeExtensions) {
            throw new \LogicException('Can\'t set includeNames when non-empty includeExtensions.');
        }
        $this->includeNames = $names;

        // Include .hidden if a name is .hidden.
        if (strpos(',' . join(',', $names), ',.') !== false) {
            $this->includeHidden = true;
        }

        return $this;
    }

    /**
     * Require that filename (dirname if dirs) doesn't match one of these names.
     *
     * Incompatible with includeNames and excludeExtensions.
     *
     * @param string[] $names
     *      List of filenames; dirnames if dirs.
     *
     * @return $this|PathList
     *
     * @throws \LogicException
     *      If non-empty includeNames or excludeExtensions.
     */
    public function excludeNames(array $names) : PathList
    {
        if ($this->includeNames) {
            throw new \LogicException('Can\'t set excludeNames when non-empty includeNames.');
        }
        if ($this->excludeExtensions) {
            throw new \LogicException('Can\'t set excludeNames when non-empty excludeExtensions.');
        }
        $this->excludeNames = $names;
        return $this;
    }

    /**
     * Require that filename (dirname if dirs) doesn't match one of these extensions.
     *
     * Incompatible with excludeExtensions and includeNames.
     *
     * @param string[] $extensions
     *      List of extensions, with or without leading dot.
     *      Extensions are allowed to be 'long'; have inner dot(s).
     *
     * @return $this|PathList
     *
     * @throws \LogicException
     *      If non-empty excludeExtensions or includeNames.
     */
    public function includeExtensions(array $extensions) : PathList
    {
        if ($this->excludeExtensions) {
            throw new \LogicException('Can\'t set includeExtensions when non-empty excludeExtensions.');
        }
        if ($this->includeNames) {
            throw new \LogicException('Can\'t set includeExtensions when non-empty includeNames.');
        }

        $this->includeExtensions = [];
        foreach ($extensions as $ext) {
            $needle = ltrim($ext, '.');
            $this->includeExtensions[] = $needle;
            if (strpos($needle, '.')) {
                $this->longExtensions = true;
                break;
            }
        }

        return $this;
    }

    /**
     * Require that filename (dirname if dirs) matches one of these extensions.
     *
     *  Incompatible with includeExtensions and excludeNames.
     *
     * @param string[] $extensions
     *      List of extensions, with or without leading dot.
     *      Extensions are allowed to be 'long'; have inner dot(s).
     *
     * @return $this|PathList
     *
     * @throws \LogicException
     *      If non-empty includeExtensions or excludeNames.
     */
    public function excludeExtensions(array $extensions) : PathList
    {
        if ($this->includeExtensions) {
            throw new \LogicException('Can\'t set excludeExtensions when non-empty includeExtensions.');
        }
        if ($this->excludeNames) {
            throw new \LogicException('Can\'t set excludeExtensions when non-empty excludeNames.');
        }

        $this->excludeExtensions = [];
        foreach ($extensions as $ext) {
            $needle = ltrim($ext, '.');
            $this->excludeExtensions[] = $needle;
            if (strpos($needle, '.')) {
                $this->longExtensions = true;
                break;
            }
        }
        return $this;
    }

    /**
     * Dumps requirements, inclusions and exclusions to array.
     *
     * Adds bucket criteriaCount, counting actual filter criteria;
     * zero of no filters and truthy includeHidden.
     *
     * @return array
     */
    public function describe() : array
    {
        $info = [
            'path' => $this->path,
            'maxDepth' => $this->maxDepth,
            'skipSymLinks' => $this->skipSymLinks,
            'traverseHidden' => $this->traverseHidden,
            'dirs' => $this->dirs,
            'requireUnique' => $this->requireUnique,
            //
            'criteriaCount' => (int) !$this->includeHidden,
            // First real criteria.
            'includeHidden' => $this->includeHidden,
        ];
        $possible_empties = [
            'includeNames',
            'excludeNames',
            'includeExtensions',
            'excludeExtensions',
        ];
        foreach ($possible_empties as $key) {
            if ($this->{$key}) {
                ++$info['criteriaCount'];
                $info[$key] = $this->{$key};
            }
        }
        return $info;
    }

    /**
     * @param string $path
     * @param int $depth
     *
     * @throws FileNonUniqueException
     *      Propagated; if requireUnique and non-unique item found.
     */
    protected function traverseRecursively(string $path, int $depth = 0)
    {
        if ($depth > $this->maxDepth) {
            return;
        }

        $iterator = new \FilesystemIterator(
            $path,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS
            | ($this->skipSymLinks ? 0 : \FilesystemIterator::FOLLOW_SYMLINKS)
        );
        foreach ($iterator as $item) {
            $filename = $item->getFilename();

            $is_dir = $item->isDir();

            if ($is_dir == $this->dirs && $this->filter($item)) {
                if (!$this->requireUnique) {
                    $this->append($item->getPathname());
                }
                // Filenames are keys if requireUnique.
                elseif ($this->offsetExists($filename)) {
                    throw new FileNonUniqueException(
                        'Non-unique ' . (!$this->dirs ? 'filename' : 'dirname') . '[' . $filename
                        . '] found in paths [' . $this->offsetGet($filename) . ']'
                        . ' and [' . $item->getPathname() . '].'
                    );
                }
                else {
                    $this->offsetSet($filename, $item->getPathname());
                }

                // If directory and not set to traverse matching dir.
                if ($is_dir && $this->dirs == 1) {
                    continue;
                }
            }
            elseif ($is_dir && !$this->traverseHidden && $filename{0} == '.') {
                continue;
            }

            if ($is_dir) {
                $this->traverseRecursively($item->getPathname(), $depth + 1);
            }
        }
    }

    /**
     * @param \SplFileInfo $item
     *
     * @return bool
     */
    protected function filter(\SplFileInfo $item) : bool
    {
        $filename = $item->getFilename();

        if (!$this->includeHidden && $filename{0} == '.') {
            return false;
        }

        if ($this->includeNames) {
            if (!in_array($filename, $this->includeNames, true)) {
                return false;
            }
        }
        elseif ($this->excludeNames && in_array($filename, $this->excludeNames, true)) {
            return false;
        }

        if ($this->includeExtensions) {
            if (!$this->longExtensions) {
                if (!in_array($item->getExtension(), $this->includeExtensions, true)) {
                    return false;
                }
            } else {
                $matches = false;
                foreach ($this->includeExtensions as $ext) {
                    if (
                        ($pos = strpos($filename, '.' . $ext)) !== false
                        && $pos == strlen($filename) - strlen($ext) - 1
                    ) {
                        $matches = true;
                        break;
                    }
                }
                if (!$matches) {
                    return false;
                }
            }
        }
        elseif ($this->excludeExtensions) {
            if (!$this->longExtensions) {
                if (in_array($item->getExtension(), $this->excludeExtensions, true)) {
                    return false;
                }
            } else {
                foreach ($this->excludeExtensions as $ext) {
                    if (
                        ($pos = strpos($filename, '.' . $ext)) !== false
                        && $pos == strlen($filename) - strlen($ext) - 1
                    ) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
