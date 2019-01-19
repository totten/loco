<?php
namespace Loco\Command;

use Loco\LocoEnv;
use Loco\LocoService;
use Loco\LocoSystem;
use Loco\Utils\File;
use Loco\Utils\Shell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;


class StopCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  protected $pollInterval = 200;

  protected function configure() {
    $this
      ->setName('stop')
      ->setDescription('Stop any running services')
      ->addArgument('service', InputArgument::IS_ARRAY, 'Service name(s). (Default: all)')
      ->addOption('sig', 'k', InputOption::VALUE_REQUIRED, 'Kill signal', SIGTERM)
      ->setHelp('Stop any running services');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);
    $svcs = $this->pickServices($system, $input->getArgument('service'));
    foreach (array_reverse($svcs) as $svc) {
      static::doStop($system, $svc, $input->getOption('sig'), $input, $output);
    }
    return 0;
  }

  public static function doStop(LocoSystem $sys, LocoService $svc, $sig, InputInterface $input, OutputInterface $output) {
    declare(ticks = 1);

    if (empty($svc->pid_file)) {
      return;
    }
    $env = $svc->createEnv();

    switch ($svc->isRunning($env)) {
      case TRUE:
        $pid = $svc->getPid($env);
        $output->writeln("<info>[<comment>$svc->name</comment>] Kill process (pid=<comment>$pid</comment>, sig=<comment>$sig</comment>)</info>", OutputInterface::VERBOSITY_VERBOSE);
        posix_kill($pid, $sig);
        break;

      case FALSE:
        $output->writeln("<info>[<comment>{$svc->name}</comment>] Already stopped</info>", OutputInterface::VERBOSITY_VERBOSE);
        break;

      case NULL:
        $output->writeln("<info>[<comment>{$svc->name}</comment>] Does not have a PID file. Stop not supported right now.</info>", OutputInterface::VERBOSITY_VERBOSE);
        break;
    }
  }

}
