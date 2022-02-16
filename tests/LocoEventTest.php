<?php

namespace Loco;

class LocoEventTest extends \PHPUnit\Framework\TestCase {

  public function testBasicMethods() {
    $e = new LocoEvent(['constructor' => 100]);
    $e->setArgument('setter', 200);
    $e['offset'] = 300;

    $this->assertEquals(100, $e->getArgument('constructor'));
    $this->assertEquals(200, $e->getArgument('setter'));
    $this->assertEquals(300, $e->getArgument('offset'));

    $this->assertEquals(100, $e['constructor']);
    $this->assertEquals(200, $e['setter']);
    $this->assertEquals(300, $e['offset']);
  }

}
