<?php
namespace Loco\Utils;

use Symfony\Component\Finder\Finder;

class File {

  public static function mkdir($dir, $mode = 0777) {
    if (!file_exists($dir)) {
      mkdir($dir, $mode, TRUE);
    }
  }

  /**
   * @param string $base
   */
  public static function removeAll($base) {
    $finder = new Finder();
    foreach ($finder->in($base)->files() as $file) {
      unlink($file);
    }
    foreach ($finder->in($base)->directories() as $dir) {
      rmdir($dir);
    }
  }

}
