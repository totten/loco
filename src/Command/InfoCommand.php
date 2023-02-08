<?php
namespace Loco\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InfoCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  private $displayOptions = ['run' => NULL, 'message' => 'm', 'pid_file' => NULL, 'log_file' => NULL];

  protected function configure() {
    $this
      ->setName('info')
      ->setAliases(array())
      ->setDescription('Describe the services')
      ->addArgument('service', InputArgument::IS_ARRAY, 'Service name(s). Separated by commas or spaces. (Default: all)')
      ->setHelp('Describe the services');
    foreach ($this->displayOptions as $option => $shortcut) {
      $this->addOption($option, $shortcut, InputOption::VALUE_NONE, "Display property \"$option\"");
    }
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);
    $svcNames = $input->getArgument('service')
      ? $input->getArgument('service')
      : array_keys($system->services);

    sort($svcNames);

    $showAll = !array_reduce(array_keys($this->displayOptions), function($carry, $item) use ($input) {
      return $carry || $input->getOption($item);
    }, FALSE);
    $showProp = function(string $name) use ($input, $showAll) {
      return $showAll || ($input->hasOption($name) && $input->getOption($name));
    };

    $this->applyDaemonDefaults($system->services);

    $rows = [];
    foreach ($svcNames as $svcName) {
      /** @var \Loco\LocoService $svc */
      $svc = $system->services[$svcName];
      $env = $svc->createEnv();

      foreach (['enabled'] as $prop) {
        if ($showProp($prop) && $svc->{$prop}) {
          $rows[] = [$svc->name, $prop, $svc->{$prop}];
        }
      }
      foreach (['run', 'message', 'pid_file', 'log_file'] as $prop) {
        if ($showProp($prop) && $svc->{$prop}) {
          $rows[] = [$svc->name, $prop, $env->evaluate($svc->{$prop})];
        }
      }
    }
    $table = new Table($output);
    $table
      ->setHeaders(['Service', 'Key', 'Value'])
      ->setRows($rows);
    $table->render();

    return 0;
  }

}
