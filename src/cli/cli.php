<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

// Include Composer autoloader - re-usable snippet.-----------------------------
(function()
{
    $path = getcwd();
    $dir = dirname($path);
    // Executed in [package] dir.
    if (basename(dirname($dir)) == 'vendor') {
        require '../../autoload.php';
        return;
    }
    // Executed in [package]/src/cli.
    elseif (basename($path) == 'cli' && basename($dir) == 'src') {
        require '../../../../autoload.php';
        return;
    }
    // Executed in document root; one or two stops above 'vendor' dir.
    $vendor_dir = 'vendor';
    $vendor_path = '';
    if (file_exists($vendor_dir) && is_dir($vendor_dir)) {
        $vendor_path = $vendor_dir;
    } else {
        $iter = new \FilesystemIterator($path, \FilesystemIterator::FOLLOW_SYMLINKS);
        foreach ($iter as $item) {
            if ($item->isDir()) {
                $path = $item->getPathName();
                $sub_iter = new \FilesystemIterator($path, \FilesystemIterator::FOLLOW_SYMLINKS);
                foreach ($sub_iter as $sub_item) {
                    if ($sub_item->isDir() && $sub_item->getFilename() == $vendor_dir) {
                        $vendor_path = $path . '/' . $vendor_dir;
                        break 2;
                    }
                }
            }
        }
    }
    if ($vendor_path) {
        require $vendor_path . '/autoload.php';
        return;
    }
    echo "\033[01;31m[error]\033[0m Can't locate composer autoload.\nChange dir to this script's dir, and try again.\n";
    exit;
})();
// /Include Composer autoloader - re-usable snippet.----------------------------


// Work.------------------------------------------------------------------------
use SimpleComplex\Utils\CliEnvironment;
use SimpleComplex\Utils\Dependency;
use SimpleComplex\Utils\Bootstrap;

/**
 * Exposes CLI commands of several packages, if they exist.
 *
 * Only function for IDE to find this script.
 * This file is unknown to Composer autoloader.
 *
 * @code
 * # CLI
 * php vendor/simplecomplex/utils/src/cli/cli.php -h
 * @endcode
 *
 * @return mixed
 *      Return value of the executed command, if any.
 *      May well exit.
 */
function simple_complex_utils_cli()
{
    $container = Dependency::container();

    Bootstrap::prepareDependenciesIfExist();

    Bootstrap::setExceptionHandler($container, 'cli');
    Bootstrap::setErrorHandler($container, 'cli');

    // Let CliEnvironment:
    // - find command providers in [doc_root]/.utils_cli_command_providers.ini
    // - listen to input command name, arguments and options
    // - forward matched command to it's provider
    return CliEnvironment::getInstance()
        ->commandProvidersLoad()
        ->forwardMatchedCommand();
}
simple_complex_utils_cli();
