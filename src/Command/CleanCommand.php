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


class CleanCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  protected function configure() {
    $this
      ->setName('clean')
      ->setDescription('Clean out the service data folders')
      ->addOption('service', 's', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Service name')
      ->setHelp('Clean out the service data folders');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);
    $svcNames = explode(',', implode(',', $input->getOption('service')));
    foreach ($svcNames as $svcName) {
      if (empty($system->services[$svcName])) {
        throw new \Exception("Unknown service: $svcName");
      }
      static::doClean($system, $system->services[$svcName], $input, $output);
    }
    return 0;
  }

  public static function doClean(LocoSystem $sys, LocoService $svc, InputInterface $input, OutputInterface $output) {
    $env = $svc->createEnv();
    $svcVar = $env->getValue('LOCO_SVC_VAR');
    if (file_exists($svcVar)) {
      $output->writeln("<info>[<comment>$svc->name</comment>] Remove existing data folder \"<comment>$svcVar</comment>\"</info>");
      File::removeAll($svcVar);
    }
  }

}
