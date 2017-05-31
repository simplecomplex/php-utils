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
 * CLI PHP utility.
 *
 * @package SimpleComplex\Utils
 */
class Cli {


  // @todo: Make argsNOptions method, which uses $argv.


  /**
   * Is current execution context CLI?
   *
   * @return boolean
   */
  public static function cli() : bool {
    return PHP_SAPI == 'cli';
  }

  /**
   * Alternative to native getcwd(), which throws exception on failure,
   * and secures forward slash directory separator.
   *
   * If document root is symlinked, this returns the resolved path,
   * not the symbolic link.
   *
   * @throws \RuntimeException
   *   If current working dir cannot be resolved.
   *   Citing http://php.net/manual/en/function.getcwd.php:
   *   On some Unix variants, getcwd() will return false if any one of the
   *   parent directories does not have the readable or search mode set,
   *   even if the current directory does.
   *
   * @return string
   */
  public static function getCurrentWorkingDir() : string {
    $path = getcwd();
    if ($path === false) {
      throw new \RuntimeException('You are not in CLI mode.');
    }
    // Symlinked path cannot be detected because $_SERVER['SCRIPT_FILENAME']
    // in cli mode only returns the filename; not path + filename.

    if (DIRECTORY_SEPARATOR == '\\') {
      $path = str_replace('\\', '/', $path);
    }
    return $path;
  }

  /**
   * @var integer
   */
  const DIRECTORY_TRAVERSAL_LIMIT = 100;

  /**
   * @var string
   */
  protected static $documentRoot = '';

  /**
   * Find document root.
   *
   * PROBLEM
   * A PHP CLI script has rarely any reliable means of discovering a site's
   * document root.
   * $_SERVER['DOCUMENT_ROOT'] will usually be empty, because that var is set
   * (non-empty) by a webserver - and CLI PHP is not executed in the context
   * of a webserver.
   * And getcwd() will only tell the script's current position in the file
   * system; only useful if the CLI script is placed right in (a) document root.
   *
   * SOLUTION
   * Place a .document_root file in the site's document root - but only after
   * checking that the webserver (or an Apache .htaccess file) is configured
   * to hide .hidden files.
   * See the files in this library's doc/.document-root-files dir.
   *
   * Document root in the root of the file system is not supported.
   *
   * @param boolean $noTaintEnvironment
   *   False: do set $_SERVER['DOCUMENT_ROOT'], if successful.
   *
   * @return string
   */
  public static function documentRoot($noTaintEnvironment = FALSE) : string {
    if (static::$documentRoot) {
      return static::$documentRoot;
    }
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
      static::$documentRoot = $root = $_SERVER['DOCUMENT_ROOT'];
      // We don't expect DIRECTORY_SEPARATOR=\ issues for that server var.
      return $root;
    }

    $path = static::getCurrentWorkingDir();
    if (DIRECTORY_SEPARATOR == '\\') {
      $path = str_replace('\\', '/', $path);
    }

    // Go up/left.
    $limit = static::DIRECTORY_TRAVERSAL_LIMIT;
    do {
      if (file_exists($path . '/.document_root') && is_file($path . '/.document_root')) {
        static::$documentRoot = $path;
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
   * @param boolean $noTaintEnvironment
   *   False: do set $_SERVER['DOCUMENT_ROOT'], if successful.
   *
   * @return integer|null
   *   Null: document root can't be determined, or you're not in the same
   *     file system branch as document root.
   */
  public static function documentRootDistance($noTaintEnvironment = FALSE) /*: ?int*/ {
    $root = static::$documentRoot;
    if (!$root) {
      $root = static::documentRoot($noTaintEnvironment);
    }
    if (!$root) {
      return null;
    }

    $path = static::getCurrentWorkingDir();
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
   * @param boolean $noTaintEnvironment
   *   False: do set $_SERVER['DOCUMENT_ROOT'], if successful.
   *
   * @return boolean
   *   False: document root can't be determined, or you're not in the same
   *     file system branch as document root, or a chdir() call fails.
   */
  public static function documentRootChangeDirTo($noTaintEnvironment = FALSE) : bool {
    $distance = static::documentRootDistance($noTaintEnvironment);
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
      }
      // Above/to left.
      else {
        // Document root contains current path.
        $intermediates = explode(
          '/',
          substr(static::$documentRoot, strlen(static::getCurrentWorkingDir()) + 1)
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
    return static::getCurrentWorkingDir() == static::$documentRoot;
  }

  /**
   * @var array
   */
  protected static $outputSanitizeNeedles = [
    '`',
  ];

  /**
   * @var array
   */
  protected static $outputSanitizeReplace = [
    "'",
  ];

  /**
   * Sanitize string to be printed to console.
   *
   * @param mixed $output
   *   Gets stringified.
   *
   * @return string
   */
  public static function outputSanitize($output) : string {
    return str_replace(static::$outputSanitizeNeedles, static::$outputSanitizeReplace, '' . $output);
  }

  /**
   * @param mixed $message
   *   Gets stringified, and sanitized.
   *
   * @param boolean $skipTrailingNewline
   *   Truthy: do no append newline.
   *
   * @return boolean
   *   Will echo arg message.
   */
  public static function outputMessage($message, $skipTrailingNewline = false) : bool {
    echo static::outputSanitize('' . $message) . (!$skipTrailingNewline ? "\n" : '');
    return true;
  }
}
