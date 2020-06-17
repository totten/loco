<?php

namespace Loco;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Simple\Psr6Cache;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class Loco
 * @package Loco
 *
 * This facade provides access to high-level services.
 */
class Loco {

  protected static $instances = [];

  /**
   * Get a PSR-16 cache service (`SimpleCache`).
   *
   * This is cache is file-based and user-scoped (e.g. `~/.cache/loco`).
   * Don't expect it to be high-performance...
   *
   * NOTE: At time of writing, this is not used internally - but can be used by a plugin.
   *
   * @return \Psr\SimpleCache\CacheInterface
   */
  public static function cache() {
    if (!isset(self::$instances["cache"])) {
      if (getenv('XDG_CACHE_HOME')) {
        $dir = getenv('XDG_CACHE_HOME');
      }
      elseif (getenv('HOME')) {
        $dir = getenv('HOME') . '/.cache';
      }
      else {
        throw new \RuntimeException("Failed to determine cache location");
      }
      $fsCache = new FilesystemAdapter('loco', 600, $dir);
      // In symfony/cache~3.x, the class name is weird.
      self::$instances["cache"] = new Psr6Cache($fsCache);
    }
    return self::$instances["cache"];
  }

  /**
   * Get the system-wide event-dispatcher.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcher
   */
  public static function dispatcher() {
    if (!isset(self::$instances['dispatcher'])) {
      self::$instances['dispatcher'] = new EventDispatcher();
    }
    return self::$instances['dispatcher'];
  }

  /**
   * Filter a set of data through an event.
   *
   * @param string $eventName
   * @param array $data
   *   Open-ended set of data.
   * @return array
   *   Filtered $data
   */
  public static function filter($eventName, $data) {
    $event = new LocoEvent($data);
    self::dispatcher()->dispatch($eventName, $event);
    return $event->getArguments();
  }

  /**
   * Get the plugin manager.
   *
   * @return \Loco\LocoPlugins
   */
  public static function plugins() {
    if (!isset(self::$instances['plugins'])) {
      self::$instances['plugins'] = new LocoPlugins();
    }
    return self::$instances['plugins'];
  }

}
