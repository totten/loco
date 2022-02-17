<?php

namespace Loco\Utils;

/**
 * Bridge for implementing ArrayAccessTrait on both PHP 7.x and 8.x.
 *
 * Specifically, PHP 8.1 makes changes that break compatibility with PHP 7.2 (and possibly others).
 *
 * There are two nearly identical files, the head `BasicArrayAccessTrait.php` and the
 * backward-compatible overload `BasicArrayAccessTrait.php7`.
 *
 * Whenever you change one, you may need to change the other. It's good to keep the files small...
 */
trait BasicArrayAccessTrait {

  /**
   * @var array
   */
  protected $arguments;

  /**
   * ArrayAccess for argument getter.
   *
   * @param string $key Array key
   * @return mixed
   * @throws \InvalidArgumentException if key does not exist in $this->args
   */
  public function &offsetGet($key): mixed {
    return $this->arguments[$key];
  }

  /**
   * ArrayAccess for argument setter.
   *
   * @param string $key Array key to set
   * @param mixed $value Value
   */
  public function offsetSet($key, $value): void {
    $this->setArgument($key, $value);
  }

  /**
   * ArrayAccess for unset argument.
   *
   * @param string $key Array key
   */
  public function offsetUnset($key): void {
    if ($this->hasArgument($key)) {
      unset($this->arguments[$key]);
    }
  }

  /**
   * ArrayAccess has argument.
   *
   * @param string $key Array key
   * @return bool
   */
  public function offsetExists($key): bool {
    return $this->hasArgument($key);
  }

}
