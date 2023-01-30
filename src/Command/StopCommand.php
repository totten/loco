<?php
namespace Loco\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
    $output->writeln("<info>[<comment>loco</comment>] Stop services: " . $this->formatList(array_keys($svcs)) . "</info>", OutputInterface::VERBOSITY_VERBOSE);
    foreach (array_reverse($svcs) as $svc) {
      if (!empty($svc->pid_file)) {
        $svc->kill($output, $input->getOption('sig'));
      }
    }

    $this->awaitStopped($output, $svcs);

    return 0;
  }

}
