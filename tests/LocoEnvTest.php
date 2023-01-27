<?php

namespace Loco;

/**
 */
class LocoEnvTest extends \PHPUnit\Framework\TestCase {

  public function getExamples() {
    $es = [];
    $es[] = ['rides a $COLOR bike', 'rides a red bike'];
    $es[] = ['rides a ${COLOR} bike', 'rides a red bike'];
    $es[] = ['go $TRANSPORT', 'go red bike'];
    $es[] = ['Loaded $(basename $FILE)!', 'Loaded LocoEnvTest.php!'];
    $es[] = ['Loaded from $(dirname $FILE)', 'Loaded from ' . __DIR__];
    $es[] = ['Move to $(basename $FILE)bak', 'Move to LocoEnvTest.phpbak'];
    $es[] = ['Move to $(dirname $FILE)bak', 'Move to ' . __DIR__ . 'bak'];
    $es[] = ['1 + $NUM', '1 + 1234'];
    return $es;
  }

  /**
   * @param string $input
   * @param string $expect
   * @return void
   * @dataProvider getExamples
   */
  public function testEvaluate(string $input, string $expect) {
    $env = new LocoEnv();
    $env->set('FILE', __FILE__);
    $env->set('COLOR', 'red');
    $env->set('NUM', '1234');
    $env->set('TRANSPORT', '$COLOR bike', TRUE);

    $this->assertEquals($expect, $env->evaluate($input, 'null'), "Evaluate \"$input\"");
  }

}
