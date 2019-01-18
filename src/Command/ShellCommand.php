<?php
namespace Loco\Command;

use Loco\LocoEnv;
use Loco\Utils\Shell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ShellCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  protected function configure() {
    $this
      ->setName('shell')
      ->setAliases(['sh'])
      ->setDescription('Execute a shell command')
      ->addOption('service', 's', InputOption::VALUE_REQUIRED, 'Service name')
      ->addOption('escape', 'e', InputOption::VALUE_REQUIRED, 'Strategy for (re)escaping args to subcommand: (s)trict, (w)eak, (n)one', 's')
      ->addArgument('cmd', InputArgument::IS_ARRAY, 'Command to execute', ['bash'])
      ->setHelp('Display the environment variables for a service');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);

    /** @var LocoEnv $env */
    $env = $this->pickEnv($system, $input->getOption('service'));
    Shell::applyEnv($env);

    $escapers = [
      's' => function ($s) {
        return Shell::lazyEscape($s);
      },
      'w' => function ($s) {
        return "\"$s\"";
      },
      'n' => function ($s) {
        return $s;
      },
    ];
    $escaper = $escapers[$input->getOption('escape')] ?: NULL;
    if (!$escaper) {
      throw new \RuntimeException("Invalid escaping option: " . $input->getOption('escape'));
    }
    $cmd = implode(' ', array_map($escaper, $input->getArgument('cmd')));

    $output->getErrorOutput()->writeln("RUN: $cmd", OutputInterface::VERBOSITY_VERBOSE);
    if ($input->getOption('no-interaction')) {
      passthru($cmd, $retVal);
      return $retVal;
    }
    else {
      return Shell::runInteractively($cmd);
    }
  }

}
