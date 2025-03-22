<?php

namespace Loco\Utils;

/**
 * A collection fork-related helpers.
 */
class Fork {

  /**
   * Spawn a child process within the same terminal (STDOUT/STDERR/etc).
   * Execute $run().
   *
   * @param callable $run
   *   Main function we want to run in the child process.
   *   If this returns a numerical or boolean value, it will determine the exit-code for the child process.
   * @return int
   *   The PID of the new child process.
   */
  public static function child(callable $run): int {
    $pid = pcntl_fork();
    if ($pid == -1) {
      die("Failed to fork");
    }
    elseif ($pid) {
      return $pid;
    }
    else {
      $result = $run();
      exit(static::castToExitCode($result));
    }
  }

  /**
   * Spawn an independent process. Detach from terminal (STDOUT/STDERR/etc).
   * Execute $run().
   *
   * @param callable $run
   *   Main function we want to run in the child process.
   *   If this returns a numerical or boolean value, it will determine the exit-code for the child process.
   * @param string|null $pidFile
   *   Optionally, record the daemon's PID in a file.
   *   Note that it may take a moment for the file to be written.
   */
  public static function daemon(callable $run, ?string $pidFile = NULL): void {
    if ($pidFile !== NULL && file_exists($pidFile)) {
      unlink($pidFile);
    }

    // We proceed through a sequence of 3 processes: Parent => Intermediate => Child

    // Fork from parent to intermediate
    $intermediatePid = pcntl_fork();
    if ($intermediatePid === -1) {
      die('Failed to fork (intermediate process)');
    }
    elseif ($intermediatePid) {
      // We're the parent, and we've done our job.
      return;
    }

    // Intermediate does its setup
    posix_setsid();

    // Fork from intermediate to child
    $childPid = pcntl_fork();
    if ($childPid === -1) {
      die('Failed to fork (child process)');
    }
    elseif ($childPid) {
      // We're the intermediate, and we've done our job.
      exit();
    }

    // Some like https://theworld.com/~swmcd/steven/tech/daemon.html suggest doing a 'umask()' and 'chdir()' here.
    // I'm not sure -- need to assess the significance of PWD for existing service definitions.

    // Finally, the child can do its thing.
    if ($pidFile !== NULL) {
      file_put_contents($pidFile, posix_getpid());
    }
    $result = $run();
    exit(static::castToExitCode($result));
  }

  /**
   * Given the return value from a $run() function, determine the corresponding exit code.
   *
   * Numbers are passed-through directly. NULL is considered success. All other values are treated as boolean-ish (success/failure).
   *
   * @param mixed $result
   * @return int
   *   Exit code
   */
  protected static function castToExitCode($result): int {
    if (is_numeric($result)) {
      return (int) $result;
    }
    elseif ($result === NULL) {
      return 0;
    }
    else {
      return $result ? 0 : 1;
    }
  }

  // The following are little experiments and may not necessarily be used...

  /**
   * Forcibly close STDIN, STDOUT, STDERR.
   */
  public static function resetStdio(string $ioMode): void {
    switch ($ioMode) {
      case 'open':
        // Do nothing
        break;

      // case 'redirect':
      //   $pipes = Fork::redirectStdIo($svc->log_file);
      //   $output = new StreamOutput($pipes[1]);
      //   break;

      case 'close-output':
        // Ex: "loco start | cat" - closing ensures that parent can exit normally.
        fclose(STDOUT);
        fclose(STDERR);
        break;

      case 'close-all':
        // Ex: "loco start | cat" - closing ensures that parent can exit normally.
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);
        break;

      default:
        throw new \RuntimeException("Unknown io_mode '$ioMode'");
    }

  }

  /**
   * Close and reopen STDIN, STDOUT, STDERR.
   *
   * @link https://stackoverflow.com/questions/32329436/how-do-i-call-linux-dup2-from-php
   * @param string $logFile
   * @return array
   */
  public static function redirectStdIo(string $logFile): array {
    fclose(STDIN);
    fclose(STDOUT);
    fclose(STDERR);
    $STDIN = fopen("/dev/null", 'r');
    $STDOUT = fopen($logFile, 'ab');
    $STDERR = fopen($logFile, 'ab');
    return [$STDIN, $STDOUT, $STDERR];
  }

}
