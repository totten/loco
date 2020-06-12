<?php
namespace Loco\Utils;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class File {

  public static function mkdir($dir, $mode = 0777) {
    if (!file_exists($dir)) {
      mkdir($dir, $mode, TRUE);
    }
  }

  /**
   * @param string $path
   * @return string updated $path
   */
  public static function toAbsolutePath($path) {
    $fs = new Filesystem();
    if (empty($path)) {
      $res = getcwd();
    }
    elseif ($fs->isAbsolutePath($path)) {
      $res = $path;
    }
    elseif (getenv('PWD')) {
      $res = getenv('PWD') . DIRECTORY_SEPARATOR . $path;
    }
    else {
      $res = getcwd() . DIRECTORY_SEPARATOR . $path;
    }
    if (is_dir($res)) {
      return realpath($res);
    }
    else {
      return $res;
    }
  }


  /**
   * @param string $base
   */
  public static function removeAll($base) {
    if (!file_exists($base)) {
      return;
    }

    $finder = new Finder();

    foreach ($finder->in($base)->files() as $file) {
      // echo "unlink($file)\n";
      if (file_exists((string) $file)) {
        unlink((string) $file);
      }
    }

    $dirs = [];
    foreach ($finder->in($base)->directories() as $dir) {
      $dirs[] = (string) $dir;
    }
    usort($dirs, function($a, $b) {
      return strlen($b) - strlen($a);
    });
    foreach ($dirs as $dir) {

      if (!file_exists($dir)) {
        // skip
      }
      elseif (is_link($dir)) {
        unlink($dir);
      }
      elseif (is_dir($dir)) {
        rmdir($dir);
      }
    }

    rmdir($base);
  }

}
