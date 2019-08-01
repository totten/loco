<?php

namespace Loco\Utils;

class SystemdUtil {

  public static function escape($name) {
    $PASSTHRU = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_.';

    $buf = '';
    for ($i = 0; $i < strlen($name); $i++) {
      $c = $name{$i};

      if (strpos($PASSTHRU, $c) !== FALSE) {
        $buf .= $c;
      }
      elseif ($i !== 0 && $c === '.') {
        $buf .= $c;
      }
      else {
        $buf .= '\\x' . unpack("H*", $c)[1];
      }
    }
    return $buf;
  }

  public static function escapePath($name) {
    $name = trim($name, '/');
    $name = preg_replace(';/+;', '/', $name);
    $parts = explode('/', $name);
    return implode('-', array_map(function ($part) {
      return self::escape($part);
    }, $parts));
  }

}