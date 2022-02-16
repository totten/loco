<?php

namespace Loco;

use Symfony\Component\EventDispatcher\Event;

/**
 * Standard event class used for most Loco events.
 *
 * This is a variant of Symfony's GenericEvent. It supports return-by-reference
 * and does not use a "subject".
 *
 * @author Drak <drak@zikula.org>
 */
class LocoEvent extends Event implements \ArrayAccess, \IteratorAggregate {
  protected $arguments;

  /**
   * Encapsulate an event with $subject and $args.
   *
   * @param array $arguments Arguments to store in the event
   */
  public function __construct(array $arguments = []) {
    $this->arguments = $arguments;
  }

  /**
   * Get argument by key.
   *
   * @param string $key Key
   *
   * @return mixed Contents of array key
   *
   * @throws \InvalidArgumentException if key is not found
   */
  public function getArgument($key) {
    if ($this->hasArgument($key)) {
      return $this->arguments[$key];
    }

    throw new \InvalidArgumentException(sprintf('Argument "%s" not found.', $key));
  }

  /**
   * Add argument to event.
   *
   * @param string $key Argument name
   * @param mixed $value Value
   *
   * @return $this
   */
  public function setArgument($key, $value) {
    $this->arguments[$key] = $value;

    return $this;
  }

  /**
   * Getter for all arguments.
   *
   * @return array
   */
  public function getArguments() {
    return $this->arguments;
  }

  /**
   * Set args property.
   *
   * @param array $args Arguments
   *
   * @return $this
   */
  public function setArguments(array $args = []) {
    $this->arguments = $args;

    return $this;
  }

  /**
   * Has argument.
   *
   * @param string $key Key of arguments array
   *
   * @return bool
   */
  public function hasArgument($key) {
    return \array_key_exists($key, $this->arguments);
  }

  /**
   * ArrayAccess for argument getter.
   *
   * @param string $key Array key
   *
   * @return mixed
   *
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
   *
   * @return bool
   */
  public function offsetExists($key): bool {
    return $this->hasArgument($key);
  }

  /**
   * IteratorAggregate for iterating over the object like an array.
   *
   * @return \ArrayIterator
   */
  public function getIterator(): \Traversable {
    return new \ArrayIterator($this->arguments);
  }

}
