<?php
namespace Loco\Command;

use Loco\LocoEnv;
use Loco\LocoService;
use Loco\LocoSystem;
use Loco\Utils\Shell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class InitCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  /**
   * @var array
   *   Ex: $procs['redis'] = ['pid' => 123, 'pidFile' => '/path/to/redis.pid'];
   */
  public $procs;

  /**
   * @var OutputInterface
   */
  protected $output;

  protected function configure() {
    $this
      ->setName('init')
      ->setDescription('Init the service(s)')
      ->addOption('service', 's', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Service name')
      ->setHelp('Display the environment variables for a service');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);
    $svcs = $this->pickServices($system, $input->getOption('service'));
    foreach ($svcs as $svc) {
      static::doInit($system, $svc, $input, $output);
    }
    return 0;
  }

  public static function doInit(LocoSystem $sys, LocoService $svc, InputInterface $input, OutputInterface $output) {
    $env = $svc->createEnv();

    if (is_dir($env->getValue('LOCO_SVC_VAR'))) {
      $output->writeln("<info>[<comment>$svc->name</comment>] Initialization is not required</info>", OutputInterface::VERBOSITY_VERBOSE);
      return 0;
    }

    $output->writeln("<info>[<comment>$svc->name</comment>] Initializing service</info>");
    mkdir($env->getValue('LOCO_SVC_VAR'), 0777, TRUE);

    // We fork so that we can call putenv()+passthru() with impunity.

    $pid = pcntl_fork();
    if ($pid == -1) {
      die("($svc->name) Failed to fork");
    }
    elseif ($pid) {
      // Parent
      $res = pcntl_waitpid($pid, $pidStatus);
      $exitCode = pcntl_wexitstatus($pidStatus);
      if ($exitCode === 0) {
        $output->writeln("<info>[<comment>$svc->name</comment>] Initialization finished</info>", OutputInterface::VERBOSITY_VERBOSE);
      }
      else {
        $failedStep = $exitCode - 1;
        throw new \RuntimeException("[$svc->name] Initialization failed at step #$failedStep: " . $svc->init[$failedStep]);
      }
    }
    else {
      // Child
      Shell::applyEnv($env);
      foreach (array_values($svc->init) as $stepNum => $init) {
        $cmd = $env->evaluate($init);
        passthru($init, $ret);
        if ($ret !== 0) {
          $output->writeln("<error>[$svc->name] Initialization failed in command \"$cmd\"</error>");
          exit(1 + $stepNum);
        }
      }
      exit(0);
    }

  }

}
