<?php

namespace Loco\E2E;

use Loco\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 */
class EnvComputationTest extends \PHPUnit\Framework\TestCase {

  protected static $exampleFile;

  public static function setUpBeforeClass(): void {
    static::$exampleFile = sprintf("%s/loco-test-%s.yml", sys_get_temp_dir(), rand(0, 1000));
  }

  public static function tearDownAfterClass(): void {
    if (static::$exampleFile && file_exists(static::$exampleFile)) {
      unlink(static::$exampleFile);
    }
  }

  public function testBasic() {
    $this->setExample([
      'environment' => [
        'NAME=fred',
      ],
      'services' => [
        'hello' => ['run' => 'echo hello $NAME'],
      ],
    ]);

    $vars = $this->computeEnvironment(['service' => 'hello']);
    $this->assertEquals('fred', $vars['NAME']);

    $vars = $this->computeEnvironment();
    $this->assertEquals('fred', $vars['NAME']);
  }

  public function testRecursiveList() {
    $this->setExample([
      'environment' => [
        'FRUITS=banana:$FRUITS',
      ],
      'services' => [
        'hello' => [
          'run' => 'echo hello',
          'environment' => [
            'FRUITS=apple:$FRUITS',
          ],
        ],
        'juicer' => [
          'run' => 'echo juice',
          'environment' => [
            'FRUITS=apricot:$FRUITS',
          ],
        ],
      ],
    ]);

    $vars = $this->computeEnvironment(['service' => 'hello'], ['FRUITS' => 'cherry:date']);
    $this->assertEquals("'apple:banana:cherry:date'", $vars['FRUITS']);
    $vars = $this->computeEnvironment(['service' => 'hello']);
    $this->assertEquals("'apple:banana:'", $vars['FRUITS']);
    $vars = $this->computeEnvironment(['service' => 'hello'], ['FRUITS' => 'cantaloupe']);
    $this->assertEquals("'apple:banana:cantaloupe'", $vars['FRUITS']);

    $vars = $this->computeEnvironment(['service' => 'juicer'], ['FRUITS' => 'cherry:date']);
    $this->assertEquals("'apricot:banana:cherry:date'", $vars['FRUITS']);
    $vars = $this->computeEnvironment(['service' => 'juicer']);
    $this->assertEquals("'apricot:banana:'", $vars['FRUITS']);
    $vars = $this->computeEnvironment(['service' => 'juicer'], ['FRUITS' => 'cantaloupe']);
    $this->assertEquals("'apricot:banana:cantaloupe'", $vars['FRUITS']);

    $vars = $this->computeEnvironment([], ['FRUITS' => 'cherry:date']);
    $this->assertEquals("'banana:cherry:date'", $vars['FRUITS']);
    $vars = $this->computeEnvironment([]);
    $this->assertEquals("'banana:'", $vars['FRUITS']);
    $vars = $this->computeEnvironment([], ['FRUITS' => 'cantaloupe']);
    $this->assertEquals("'banana:cantaloupe'", $vars['FRUITS']);
  }

  public function testDefaultsInheritance() {
    $this->setExample([
      'default_environment' => [
        'GLOBAL_OPTION=automatic',
        'MIXED_OPTION=automatically',
      ],
      'environment' => [
        'GLOBAL_ASSIGN=earth',
        'MIXED_ASSIGN=milquetoast',
      ],
      'services' => [
        'hello' => [
          'run' => 'echo hello $NAME',
          'default_environment' => [
            'LOCAL_OPTION=magic',
            'MIXED_OPTION=magically',
          ],
          'environment' => [
            'LOCAL_ASSIGN=fred',
            'MIXED_ASSIGN=opinionated',
          ],
        ],
      ],
    ]);

    // POV for the "hello" service -- called in a clean environment
    $vars = $this->computeEnvironment(['service' => 'hello']);
    $this->assertEnvVars([
      'GLOBAL_OPTION' => 'automatic',
      'GLOBAL_ASSIGN' => 'earth',
      'LOCAL_OPTION' => 'magic',
      'LOCAL_ASSIGN' => 'fred',
      'MIXED_OPTION' => 'magically',
      'MIXED_ASSIGN' => 'opinionated',
    ], $vars);

    // POV for the top-level -- called in a clean environment
    $vars = $this->computeEnvironment();
    $this->assertEnvVars([
      'GLOBAL_OPTION' => 'automatic',
      'GLOBAL_ASSIGN' => 'earth',
      'LOCAL_OPTION' => NULL,
      'LOCAL_ASSIGN' => NULL,
      'MIXED_OPTION' => 'automatically',
      'MIXED_ASSIGN' => 'milquetoast',
    ], $vars);

    // For the rest of the tests, we will pretend that the user has setup their own environment
    // variables before calling 'loco env'. Some of these will be respected; others, ignored.

    $funnyUserEnv = [
      'GLOBAL_OPTION' => 'user global option live',
      'GLOBAL_ASSIGN' => 'user global assign pointless',
      'LOCAL_OPTION' => 'user local option live',
      'LOCAL_ASSIGN' => 'user local assign pointless',
      'MIXED_OPTION' => 'user mixed option live',
      'MIXED_ASSIGN' => 'user mixed assign pointless',
    ];

    // POV for the "hello" service -- called in a pre-configured environment
    $vars = $this->computeEnvironment(['service' => 'hello', '--all' => TRUE], $funnyUserEnv);
    $this->assertEnvVars([
      'GLOBAL_OPTION' => 'user global option live',
      'GLOBAL_ASSIGN' => 'earth',
      'LOCAL_OPTION' => 'user local option live',
      'LOCAL_ASSIGN' => 'fred',
      'MIXED_OPTION' => 'user mixed option live',
      'MIXED_ASSIGN' => 'opinionated',
    ], $vars);

    // POV for the "hello" service -- called in a pre-configured environment
    $vars = $this->computeEnvironment(['service' => 'hello'], $funnyUserEnv);
    $this->assertEnvVars([
      'GLOBAL_OPTION' => NULL,
      'GLOBAL_ASSIGN' => 'earth',
      'LOCAL_OPTION' => NULL,
      'LOCAL_ASSIGN' => 'fred',
      'MIXED_OPTION' => NULL,
      'MIXED_ASSIGN' => 'opinionated',
    ], $vars);
  }

  protected function setExample(array $data) {
    if (!isset($data['format'])) {
      $data['format'] = 'loco-0.1';
    }
    file_put_contents(static::$exampleFile, Yaml::dump($data));
  }

  /**
   * Create a helper for executing command-tests in our application.
   *
   * @param array $args must include key "command"
   *
   * @return \Symfony\Component\Console\Tester\CommandTester
   */
  protected function createCommandTester($args) {
    if (!isset($args['command'])) {
      throw new \RuntimeException("Missing mandatory argument: command");
    }
    $application = new Application();
    $command = $application->find($args['command']);
    $commandTester = new CommandTester($command);
    $commandTester->execute($args);
    return $commandTester;
  }

  /**
   * @param array $args
   *   Additional arguments to send to 'loco env' command
   * @param array $putEnvs
   *   Pre-set some environment variables, before running 'loco env'.
   * @return string[]
   *   List of variables and their computed values.
   */
  protected function computeEnvironment(array $args = [], array $putEnvs = []): array {
    $this->assertFileExists(static::$exampleFile);

    $origEnv = [];
    try {
      foreach ($putEnvs as $key => $value) {
        $origEnv[$key] = getenv($key);
        putenv($key . '=' . $value);
      }

      $tester = $this->createCommandTester([
        'command' => 'env',
        '--config' => static::$exampleFile,
      ] + $args);
    }
    finally {
      foreach ($origEnv as $key => $value) {
        putenv($value === NULL ? "$key" : "$key=$value");
      }
    }

    $this->assertEquals(0, $tester->getStatusCode());
    $out = $tester->getDisplay();

    $lines = explode("\n", $out);
    $vars = [];
    foreach ($lines as $line) {
      [$key, $value] = explode('=', $line, 2);
      $vars[$key] = $value;
    }
    $this->assertEquals(count($lines), count($vars));
    return $vars;
  }

  protected function assertEnvVars(array $expecteds, array $actuals): void {
    $esc = function($v) {
      return ($v === NULL) ? $v : \Loco\Utils\Shell::lazyEscape($v);
    };

    $errors = [];
    foreach (array_keys($expecteds) as $key) {
      $actualValue = $actuals[$key] ?? NULL;
      $expectedValue = $esc($expecteds[$key] ?? NULL);
      if ($actualValue !== $expectedValue) {
        $errors[] = sprintf("%s has wrong value (expected=%s, actual=%s})", $key, json_encode($expectedValue), json_encode($actualValue));
      }
    }
    $this->assertTrue(empty($errors), "Found errors:\n" . implode("\n", $errors));
  }

}
