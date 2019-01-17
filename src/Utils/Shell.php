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

}