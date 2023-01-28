<?php

namespace Loco;

/**
 */
class LocoEnvTest extends \PHPUnit\Framework\TestCase {

  public static function onRegisterFunctions(LocoEvent $e): void {
    // $(test_json ab cd) ==> ["ab","cd"]
    // This helps to write tests about the way parameters are passed.
    $e['functions']['test_json'] = function(...$argv) {
      return \json_encode($argv, \JSON_UNESCAPED_SLASHES);
    };
  }

  protected function setUp(): void {
    Loco::dispatcher()->addListener('loco.expr.functions', [__CLASS__, 'onRegisterFunctions']);
  }

  protected function tearDown(): void {
    Loco::dispatcher()->removeListener('loco.expr.functions', [__CLASS__, 'onRegisterFunctions']);
  }

  public function getExamples() {
    $es = [];
    $es[] = ['rides a $COLOR bike', 'rides a red bike'];
    $es[] = ['rides a ${COLOR} bike', 'rides a red bike'];
    $es[] = ['go $TRANSPORT', 'go red bike'];
    $es[] = ['Loaded $(basename $FILE)!', 'Loaded LocoEnvTest.php!'];
    $es[] = ['Loaded $(basename ${FILE})!', 'Loaded LocoEnvTest.php!'];
    $es[] = ['Loaded ($(basename $FILE))', 'Loaded (LocoEnvTest.php)'];
    $es[] = ['Loaded {$(basename $FILE)}', 'Loaded {LocoEnvTest.php}'];
    $es[] = ['Loaded from $(dirname $FILE)', 'Loaded from ' . __DIR__];
    $es[] = ['Loaded from $DIR', 'Loaded from ' . __DIR__];
    $es[] = ['Loaded from $GRAMPA', 'Loaded from ' . dirname(__DIR__)];
    $es[] = ['Move to $(basename $FILE)bak', 'Move to LocoEnvTest.phpbak'];
    $es[] = ['Move to $(dirname $FILE)bak', 'Move to ' . __DIR__ . 'bak'];
    $es[] = ['Copy $(basename $FILE) to $(basename $FILE.bak)', 'Copy LocoEnvTest.php to LocoEnvTest.php.bak'];
    $es[] = ['more ${COLOR}ish', 'more redish'];
    $es[] = ['more $COLORish', 'more '];
    $es[] = ['if{$COLOR}', 'if{red}'];
    $es[] = ['if{${COLOR}}', 'if{red}'];
    $es[] = ['if($COLOR)', 'if(red)'];
    $es[] = ['1 + $NUM', '1 + 1234'];
    $es[] = ['$SYMBOLOGY', 'the $COLOR of a $NUM'];
    $es[] = ['$', '$'];
    $es[] = ['()', '()'];
    $es[] = ['{}', '{}'];
    // $es[] = ['$()', ''];
    $es[] = ['apple $(echo red $COLOR rouge) pomegranate', 'apple red red rouge pomegranate'];
    $es[] = ['fruity $(echo "$COLOR apples" and "$COLOR pomegranates) for $(echo $NUM) people', 'fruity red apples and red pomegranates for 1234 people'];
    $es[] = ['json data $(test_json "$TRANSPORT" "go go" "go $TRANSPORT")', 'json data ["red bike","go go","go red bike"]'];
    $es[] = ['json data $(test_json $TRANSPORT "go go")', 'json data ["red bike","go go"]']; /* This is not necessarily good behavior. But when/if it changes, that should be clear. */
    return $es;
  }

  /**
   * @param string $input
   * @param string $expect
   * @return void
   * @dataProvider getExamples
   */
  public function testEvaluate(string $input, string $expect) {
    $sys = LocoSystem::create(NULL, NULL, []);
    $env = $sys->environment;

    $env->set('FILE', __FILE__);
    $env->set('DIR', '$(dirname $FILE)', TRUE);
    $env->set('GRAMPA', '$(dirname $DIR)', TRUE);
    $env->set('COLOR', 'red');
    $env->set('NUM', '1234');
    $env->set('TRANSPORT', '$COLOR bike', TRUE);
    $env->set('SYMBOLOGY', 'the $COLOR of a $NUM');

    $this->assertEquals($expect, $env->evaluate($input, 'null'), "Evaluate \"$input\"");
  }

}
