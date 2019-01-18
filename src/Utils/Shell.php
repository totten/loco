<?php
namespace Loco\Utils;

class Shell {

  /**
   * @param string $value
   * @return string
   */
  public static function lazyEscape($value) {
    if (preg_match('/^[a-zA-Z0-9_\.\-\/]*$/', $value)) {
      return $value;
    }
    else {
      return escapeshellarg($value);
    }
  }

  public static function applyEnv($env) {
    foreach ($env->getAllValues() as $key => $value) {
      putenv("$key=$value");
    }
  }

  /**
   * @param string $cmd
   * @param int $pollInterval
   *   Poll interval in micro seconds. A micro second is one millionth of a
   *   second.
   * @return int
   */
  public static function runInteractively($cmd, $pollInterval = 10000) {
    $proc = proc_open($cmd, array(STDIN, STDOUT, STDERR), $pipes);
    if (is_resource($proc)) {
      do {
        $status = proc_get_status($proc);
        usleep($pollInterval);
      } while ($status['running']);
      proc_close($proc);
      return $status['exitcode'];
    }
    else {
      throw new \RuntimeException("Failed to open command: $cmd");
    }
  }

}
