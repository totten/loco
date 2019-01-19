<?php
namespace Loco\Command;

use Loco\LocoEnv;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class EnvCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  protected function configure() {
    $this
      ->setName('env')
      ->setAliases(array())
      ->setDescription('Display the environment variables for a service')
      ->addArgument('service', InputArgument::OPTIONAL, 'Service name. For an empty-service (common variables only), use "."', '.')
      ->setHelp('Display the environment variables for a service');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);

    /** @var LocoEnv $env */
    $env = $this->pickEnv($system, $input->getArgument('service'));
    foreach ($env->getAllValues() as $key => $value) {
      $output->writeln("$key=" . \Loco\Utils\Shell::lazyEscape($value));
    }

    return 0;
  }

}
