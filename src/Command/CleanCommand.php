<?php
namespace Loco\Command;

use Loco\LocoSystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  protected function configure() {
    $this
      ->setName('clean')
      ->setDescription('Clean out the service data folders')
      ->addArgument('service', InputArgument::IS_ARRAY, 'Service name(s). (Default: all)')
      ->addOption('sig', 'k', InputOption::VALUE_REQUIRED, 'Kill signal', SIGTERM)
      ->setHelp('Clean out the service data folders');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);
    $svcNames = $input->getArgument('service')
      ? $input->getArgument('service')
      : array_keys($system->services);
    $svcs = $this->asServices($system, $svcNames);

    $output->writeln("<info>[<comment>loco</comment>] Stop services: " . $this->formatList($svcNames) . "</info>", OutputInterface::VERBOSITY_VERBOSE);
    foreach ($svcs as $svc) {
      if (!empty($svc->pid_file)) {
        $svc->kill($output, $input->getOption('sig'));
      }
    }

    $output->writeln("<info>[<comment>loco</comment>] Cleanup services: " . $this->formatList($svcNames) . "</info>", OutputInterface::VERBOSITY_VERBOSE);
    foreach ($svcs as $svc) {
      $svc->cleanup($output);
    }

    return 0;
  }

  /**
   * @param \Loco\LocoSystem $system
   * @param array $svcNames
   * @return \Loco\LocoService[]
   * @throws \Exception
   */
  protected function asServices(LocoSystem $system, array $svcNames): array {
    $svcs = [];
    foreach ($svcNames as $svcName) {
      if (empty($system->services[$svcName])) {
        throw new \Exception("Unknown service: $svcName");
      }
      else {
        $svcs[$svcName] = $system->services[$svcName];
      }
    }
    return $svcs;
  }

}
