<?php

namespace Loco;

/**
 * The `\Loco` facade is a primary entry-point for plugins.
 * This just provides some basic sanity-checks to ensure that it's available.
 */
class LocoFacadeTest extends \PHPUnit\Framework\TestCase {

  public function testCache(): void {
    $c = Loco::cache('phpunit-cache-test');
    $this->assertTrue($c instanceof \Psr\SimpleCache\CacheInterface);
    $c->clear();
    $this->assertEquals(NULL, $c->get('foo'));
    $c->set('foo', 'bar');
    $this->assertEquals('bar', $c->get('foo'));
  }

  public function testDispatcher() {
    $called = FALSE;
    Loco::dispatcher()->addListener('my-test', function() use (&$called) {
      $called = TRUE;
    });
    Loco::dispatcher()->dispatch('my-test', new LocoEvent());
    $this->assertEquals(TRUE, $called);
  }

  public function testFilter() {
    Loco::dispatcher()->addListener('alterFoo', function($e) {
      $e['num'] += 10;
    });
    $data = ['num' => 5];
    $outData = Loco::filter('alterFoo', $data);
    $this->assertEquals(15, $outData['num']);
  }

}
