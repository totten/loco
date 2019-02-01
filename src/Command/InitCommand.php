<?php
namespace Loco\Command;

use Loco\LocoEnv;
use Loco\LocoService;
use Loco\LocoSystem;
use Loco\Utils\Shell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;


class InitCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  protected function configure() {
    $this
      ->setName('init')
      ->setDescription('Init the service(s)')
      ->addArgument('service', InputArgument::IS_ARRAY, 'Service name(s). Separated by commas or spaces. (Default: all)')
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force initialization, overwriting an existing data folder')
      ->setHelp('Initialize the service

This supports a mix of convention and configuration:

- Using a convention, any files named ".loco/config/SERVICE/FILE.loco.tpl" will
  be automatically mapped to ".loco/var/SERVICE/FILE".
- Using configuration, you may list a series of bash steps in the loco.yml.

      ');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);
    $svcs = $this->pickServices($system, $input->getArgument('service'));
    $forceables = $this->pickForceables($input->getOption('force'), $input->getArgument('service'), $svcs);

    $output->writeln("<info>[<comment>loco</comment>] Initialize services: " . $this->formatList(array_keys($svcs)) . "</info>", OutputInterface::VERBOSITY_VERBOSE);
    foreach ($svcs as $svc) {
      static::doInit($svc, in_array($svc->name, $forceables), $output);
    }
    return 0;
  }

  public static function doInit(LocoService $svc, $isForceable, OutputInterface $output) {
    $env = $svc->createEnv();

    if ($svc->isInitialized($env)) {
      if ($isForceable) {
        $svc->cleanup($output, $env);
      }
      else {
        $output->writeln("<info>[<comment>$svc->name</comment>] Initialization is not required</info>", OutputInterface::VERBOSITY_VERBOSE);
        return;
      }
    }

    $svc->init($output, $env);
  }

}
