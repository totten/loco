<?php

namespace Loco\Utils;

class SystemdUtilTest extends \PHPUnit\Framework\TestCase {

  public function getEscapePathExamples() {
    return [
      [
        '/home/myuser/src/foobar/.loco/var',
        'home-myuser-src-foobar-.loco-var'
      ],
      [
        '/home/my user/src/foobar/.loco/var',
        'home-my\x20user-src-foobar-.loco-var'
      ],
      [
        '/home/my-user/src/foo-bar/.loco/var',
        'home-my\x2duser-src-foo\x2dbar-.loco-var'
      ],
      [
        '/home/my.name/src/foo.bar/.loco/var',
        'home-my.name-src-foo.bar-.loco-var',
      ],
    ];
  }

  /**
   * @param $inputPath
   * @param $expectName
   * @dataProvider getEscapePathExamples
   */
  public function testEscapePath($inputPath, $expectName) {
    $actualName = SystemdUtil::escapePath($inputPath);
    $this->assertEquals($expectName, $actualName);
  }

}
