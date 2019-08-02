<?php
namespace Loco\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  protected function configure() {
    $this
      ->setName('clean')
      ->setDescription('Clean out the service data folders')
      ->addArgument('service', InputArgument::IS_ARRAY, 'Service name(s). (Default: all)')
      ->setHelp('Clean out the service data folders');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);
    $svcNames = $input->getArgument('service')
      ? $input->getArgument('service')
      : array_keys($system->services);

    $output->writeln("<info>[<comment>loco</comment>] Cleanup services: " . $this->formatList($svcNames) . "</info>", OutputInterface::VERBOSITY_VERBOSE);

    foreach ($svcNames as $svcName) {
      if (empty($system->services[$svcName])) {
        throw new \Exception("Unknown service: $svcName");
      }
      $system->services[$svcName]->cleanup($output);
    }
    return 0;
  }

}
