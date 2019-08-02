<?php
namespace Loco\Command;

use Loco\Utils\Shell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  /**
   * @var array
   *   Ex: $procs['redis'] = ['pid' => 123, 'pidFile' => '/path/to/redis.pid'];
   */
  public $procs;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  protected function configure() {
    $this
      ->setName('run')
      ->setAliases(array())
      ->setDescription('Run the service(s) in the foreground')
      ->addArgument('service', InputArgument::IS_ARRAY, 'Service name(s). Separated by commas or spaces. (Default: all)')
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force initialization, overwriting an existing data folder')
      ->setHelp('Display the environment variables for a service');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    declare(ticks = 1);
    $POLL_INTERVAL = 3;

    $system = $this->initSystem($input, $output);
    $services = $this->pickServices($system, $input->getArgument('service'));
    $forceables = $this->pickForceables($input->getOption('force'), $input->getArgument('service'), $services);

    $output->writeln("<info>[<comment>loco</comment>] Run services: " . $this->formatList(array_keys($services)) . "</info>", OutputInterface::VERBOSITY_VERBOSE);

    $this->output = $output;
    $this->procs = [];

    foreach ($services as $svcName => $svc) {
      /** @var \Loco\LocoService $svc */
      $this->procs[$svcName] = [];
      if (!empty($svc->pid_file)) {
        $env = $svc->createEnv();
        $this->procs[$svcName]['pidFile'] = $env->evaluate($svc->pid_file);
      }
    }

    foreach (array_keys($services) as $svcName) {
      if ($services[$svcName]->isRunning()) {
        $this->output->writeln("<info>[<comment>$svcName</comment>] Service already running. Ignoring.</info>");
        unset($services[$svcName]);
      }
    }

    if (empty($services)) {
      $output->writeln("<error>No services to run</error>");
      return 1;
    }

    pcntl_signal(SIGINT, [$this, 'onshutdown']);
    register_shutdown_function([$this, 'onshutdown']);

    // Track which thread is responsible for shutdown.
    global $shutdownPid;
    $shutdownPid = posix_getpid();

    $hasFirst = [];
    $blacklist = [];
    $postStartupMessages = [];

    while (TRUE) {
      foreach ($services as $name => $svc) {
        /** @var \Loco\LocoService $svc */
        if (isset($blacklist[$name])) {
          continue;
        }

        /** @var \Loco\LocoEnv $env */
        $env = $svc->createEnv();

        if (!isset($this->procs[$name]['pid'])) {
          if (!isset($hasFirst[$name])) {
            $hasFirst[$name] = 1;
            InitCommand::doInit($svc, in_array($svc->name, $forceables), $output);
          }

          if (empty($svc->run)) {
            // NOTE: It's handy to have some init-only/non-run services; e.g. populating DB content
            $this->output->writeln("<info>[<comment>$name</comment>] Service does not specify \"run\" option</info>");
            $blacklist[$name] = $name;
          }
          else {
            $pid = pcntl_fork();
            if ($pid == -1) {
              die("($name) Failed to fork");
            }
            elseif ($pid) {
              $this->procs[$name]['pid'] = $pid;
            }
            else {
              Shell::applyEnv($env);
              $cmd = $env->evaluate($svc->run);
              $this->output->writeln("<info>[<comment>$name</comment>] Start service: <comment>$cmd</comment></info>");
              passthru($svc->run, $ret);
              $this->output->writeln("<info>[<comment>$name</comment>] Exited (<comment>$ret</comment>)</info>");
              exit($ret);
            }
          }

          if ($svc->message) {
            $postStartupMessages[] = $env->evaluate("<info>[<comment>$name</comment>] {$svc->message}</info>");
          }
        }
        else {
          // Check status
          $res = pcntl_waitpid($this->procs[$name]['pid'], $pidStatus, WNOHANG);
          if ($res == -1 || $res > 0) {
            $this->output->writeln("<info>[<comment>$name</comment>] Process gone (<comment>" . $this->procs[$name]['pid'] . "</comment>)</info>");
            unset($this->procs[$name]['pid']);
          }
        }
      }

      sleep($POLL_INTERVAL);

      if (!empty($postStartupMessages)) {
        $this->output->writeln("\n");
        $this->output->writeln("<info>======================[ Startup Summary ]======================</info>");
        foreach ($postStartupMessages as $message) {
          $this->output->writeln($message);
        }
        $this->output->writeln("\n<info>Services have been started. To shutdown, press <comment>Ctrl-C</comment>.</info>");
        $this->output->writeln("<info>===============================================================</info>");

        $postStartupMessages = [];
      }
    }

    return 0;
  }

  public function onshutdown() {
    global $shutdownPid;
    static $started = FALSE;
    if ($started || $shutdownPid !== posix_getpid()) {
      return;
    }
    $started = 1;

    $this->output->writeln("<info>[<comment>loco</comment>] Shutdown started</info>");

    $allPids = array();

    foreach (array_keys($this->procs) as $name) {
      if (isset($this->procs[$name]['pidFile']) && file_exists($this->procs[$name]['pidFile'])) {
        $allPids[] = trim(file_get_contents($this->procs[$name]['pidFile']));
      }
      if (isset($this->procs[$name]['pid'])) {
        $allPids[] = $this->procs[$name]['pid'];
      }
    }

    // print_r(['onshutdown', 'pid' => posix_getpid(), '$shutdownPid' => $shutdownPid, 'allPids' => $allPids, 'procs' => $this->procs]);

    foreach ($allPids as $pid) {
      posix_kill($pid, SIGTERM);
    }
    sleep(2);
    foreach ($allPids as $pid) {
      posix_kill($pid, SIGKILL);
    }

    $this->output->writeln("<info>[<comment>loco</comment>] Shutdown finished</info>");
    exit(1);
  }

}
