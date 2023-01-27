<?php

namespace Loco\Utils;

/**
 */
class ShellStringTestTest extends \PHPUnit\Framework\TestCase {

  public function getSplitExamples() {
    $es = [];
    $es[] = ['a', ['a']];
    $es[] = ['ab cd ef', ['ab', 'cd', 'ef']];
    $es[] = ['ab "cd ef" gh', ['ab', 'cd ef', 'gh']];
    $es[] = ['ab "cd $VAR ef" gh', ['ab', 'cd $VAR ef', 'gh']];
    $es[] = ['ab \'cd ef\' gh', ['ab', 'cd ef', 'gh']];
    $es[] = ['ab\ cd ef', ['ab cd', 'ef']];
    $es[] = ['ab\"cd ef', ['ab"cd', 'ef']];
    $es[] = ['$xx $yy $zz', ['$xx', '$yy', '$zz']];
    return $es;
  }

  /**
   * @param string $input
   * @param array $expect
   * @return void
   * @dataProvider getSplitExamples
   */
  public function testSplit(string $input, array $expect): void {
    $enc = function($x) {
      return json_encode($x, JSON_UNESCAPED_SLASHES);
    };
    $actual = ShellString::split($input);
    $this->assertEquals($enc($expect), $enc($actual), "Split \"$input\"");
  }

}
