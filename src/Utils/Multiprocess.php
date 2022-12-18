<?php

namespace Loco\Utils;

class Multiprocess {

  /**
   * @param string $name
   * @param $callback
   * @return int
   */
  public static function fork($name, $callback) {
    $pid = pcntl_fork();
    if ($pid == -1) {
      die("($name) Failed to fork");
    }
    elseif ($pid) {
      return $pid;
    }
    else {
      $ret = $callback();
      exit($ret);
    }
  }

  public static function isAlive(int $pid): bool {
    return (bool) posix_getpgid($pid);
  }

}
