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
      ->addArgument('service', InputArgument::OPTIONAL, 'Service name. For an empty-service (common variables only), use "."', '.')
      ->addOption('export', NULL, InputOption::VALUE_NONE, 'Mark all variables for example')
      ->addOption('all', NULL, InputOption::VALUE_NONE, 'Show all variables (even runtime inherited)')
      ->setHelp('Display the environment variables for a service');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);

    /** @var \Loco\LocoEnv $env */
    $env = $this->pickEnv($system, $input->getArgument('service'));
    $allValues = $env->getAllValues();

    // Pithiness: only show things that have changed
    if (!$input->getOption('all')) {
      $globalValues = $system->global_environment->getAllValues();
      foreach (array_keys($allValues) as $k) {
        if (isset($globalValues[$k]) && $globalValues[$k] === $allValues[$k]) {
          unset($allValues[$k]);
        }
      }
    }

    foreach ($allValues as $key => $value) {
      $output->writeln("$key=" . \Loco\Utils\Shell::lazyEscape($value));
    }

    if ($input->getOption('export')) {
      $output->writeln("export " . implode(' ', array_keys($allValues)));
    }

    return 0;
  }

}
