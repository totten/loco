<?php

namespace Loco;

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
