<?php
namespace Loco\Utils;

use Loco\LocoEnv;
use Symfony\Component\Console\Output\OutputInterface;

class Shell {

  /**
   * @param string $value
   * @return string
   */
  public static function lazyEscape($value) {
    if (preg_match('/^[a-zA-Z0-9_\.\-\/]*$/', $value)) {
      return $value;
    }
    else {
      return escapeshellarg($value);
    }
  }

  public static function applyEnv($env) {
    foreach ($env->getAllValues() as $key => $value) {
      putenv("$key=$value");
    }
  }

  /**
   * Temporarily load environment variables and run some code.
   *
   * @param \Loco\LocoEnv $env
   * @param callable $callable
   */
  public static function withEnv($env, $callable) {
    $backups = [];
    foreach ($env->getAllValues() as $key => $value) {
      $oldValue = getenv($key);
      if ($oldValue !== $value) {
        $backups[$key] = $oldValue;
        putenv("$key=$value");
      }
    }

    try {
      $callable();
    }
    finally {
      foreach ($backups as $key => $value) {
        if ($value === FALSE) {
          putenv("$key");
        }
        else {
          putenv("$key=$value");
        }
      }
    }
  }

  /**
   * @param string $cmd
   * @param int $pollInterval
   *   Poll interval in micro seconds. A micro second is one millionth of a
   *   second.
   * @return int
   */
  public static function runInteractively($cmd, $pollInterval = 10000) {
    $proc = proc_open($cmd, array(STDIN, STDOUT, STDERR), $pipes);
    if (is_resource($proc)) {
      do {
        $status = proc_get_status($proc);
        usleep($pollInterval);
      } while ($status['running']);
      proc_close($proc);
      return $status['exitcode'];
    }
    else {
      throw new \RuntimeException("Failed to open command: $cmd");
    }
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \Loco\LocoEnv $env
   * @param array $cmds
   * @param string $name
   */
  public static function runAll(OutputInterface $output, LocoEnv $env, $cmds, $name = 'loco') {
    Shell::withEnv($env, function() use ($env, $output, $cmds, $name) {
      foreach ($cmds as $cmd) {
        $cmdPrintable = $env->evaluate($cmd, 'keep');
        $output->writeln("<info>[<comment>$name</comment>] Run command: <comment>$cmdPrintable</comment></info>", OutputInterface::VERBOSITY_VERBOSE);
        // passthru($cmd, $ret);
        $ret = static::runInteractively($cmd);
        if ($ret !== 0) {
          throw new \RuntimeException("[$name] Command failed: \"$cmdPrintable\"");
        }
      }
    });
  }

  /**
   * Run a command and monitor the output.
   *
   * @param string $cmd
   * @param callable $onData
   *   function(string $data, string $medium).
   *   Ex: `$onData('hello world', 'STDOUT')`
   * @return int
   */
  public static function runWatch(string $cmd, callable $onData): int {
    $maxLine = 2048;
    $idleWait = 100 * 1000;

    $pipeSpec = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];
    $process = proc_open($cmd, $pipeSpec, $pipes);

    $isStdoutOpen = TRUE;
    $isStderrOpen = TRUE;
    stream_set_blocking($pipes[1], 0);
    stream_set_blocking($pipes[2], 0);

    while ($isStderrOpen || $isStdoutOpen) {
      $isStreaming = FALSE;

      if ($isStdoutOpen) {
        if (feof($pipes[1])) {
          fclose($pipes[1]);
          $isStdoutOpen = FALSE;
        }
        else {
          $str = fgets($pipes[1], $maxLine);
          $len = strlen($str);
          if ($len) {
            $onData($str, 'STDOUT');
            $isStreaming = TRUE;
          }
        }
      }

      if ($isStderrOpen) {
        if (feof($pipes[2])) {
          fclose($pipes[2]);
          $isStderrOpen = FALSE;
        }
        else {
          $str = fgets($pipes[2], $maxLine);
          $len = strlen($str);
          if ($len) {
            $onData($str, 'STDERR');
            $isStreaming = TRUE;
          }
        }
      }

      if (!$isStreaming) {
        usleep($idleWait);
      }
    }

    return proc_close($process);
  }

  /**
   * @return string
   *   Absolute path of the current working directory.
   *   To the extent possible, we try to *preserve* symlinks (i.e. *not*
   *   de-referencing).
   */
  public static function getcwd() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      return getcwd();
    }
    else {
      exec('pwd', $output);
      return trim(implode("\n", $output));
    }
  }

}
