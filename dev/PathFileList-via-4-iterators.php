<?php
/**
 */
class DirectoryRecursiveFilterIterator extends RecursiveFilterIterator
{
    /**
     * @var bool
     */
    public $includeHidden;

    /**
     * @var array
     */
    public $fileExtensions;

    /**
     * @param RecursiveDirectoryIterator $iterator
     * @param array $fileExtensions
     * @param bool $includeHidden
     */
    public function __construct(
        RecursiveDirectoryIterator $iterator, array $fileExtensions = [], bool $includeHidden = false
    )
    {
        $this->fileExtensions = $fileExtensions;
        $this->includeHidden = $includeHidden;
        parent::__construct($iterator);
    }

    /**
     * Important: without this, child DirectoryRecursiveFilterIterators
     * do not get their instance vars set.
     */
    public function getChildren()
    {
        // Important: without this, child DirectoryRecursiveFilterIterators
        // do not get their instance vars set.
        return new self($this->getInnerIterator()->getChildren(), $this->fileExtensions, $this->includeHidden);
    }

    /**
     * Filters.
     *
     * @return bool
     */
    public function accept() : bool
    {
        $current = $this->current();

        if (!$this->includeHidden && $current->getFilename(){0} == '.') {
            return false;
        }

        if ($this->fileExtensions && $current->isFile() && !in_array($current->getExtension(), $this->fileExtensions)) {
            return false;
        }
        return true;
    }
}

/**
 */
class FileFilterIterator extends FilterIterator
{
    /**
     * Filters.
     *
     * @return bool
     */
    public function accept() : bool
    {
        return $this->current()->isFile();
    }
}

$path = '/var/www/some-site/conf/ini';

$iterator = new RecursiveIteratorIterator(
    new DirectoryRecursiveFilterIterator(
        new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::UNIX_PATHS | FilesystemIterator::FOLLOW_SYMLINKS | FilesystemIterator::SKIP_DOTS
        ),
        [
            'ini',
        ],
        false
    ),
    RecursiveIteratorIterator::SELF_FIRST
);
$iterator->setMaxDepth(10);
$iterator = new FileFilterIterator($iterator);
foreach ($iterator as $item) {
    $name = $item->getFilename();
    echo $item->getPath() . '/' . $name . "\n";
}
exit;
