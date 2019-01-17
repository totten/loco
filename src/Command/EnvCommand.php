<?php
namespace Loco\Command;

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
      ->addArgument('svc', InputArgument::OPTIONAL, 'Service name')
      ->setHelp('Display the environment variables for a service');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);

    /** @var LocoEnv $env */
    $env = $input->getArgument('svc')
      ? $system->services[$input->getArgument('svc')]->createEnv()
      : $system->createEnv();

    foreach ($env->getAllValues() as $key => $value) {
      $output->writeln("$key=$value");
    }

    return 0;
  }

}
