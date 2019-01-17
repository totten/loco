<?php
namespace Loco\Command;

use Loco\LocoSystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

trait LocoCommandTrait {
  public function configureSystemOptions() {
    $this->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Name of the loco.yml file', '.loco/loco.yml');
    return $this;
  }

  public function initSystem(InputInterface $input, OutputInterface $output) {
    if (!file_exists($input->getOption('file'))) {
      throw new \Exception("Failed to find loco config file: " . $input->getOption('file'));
    }

    $settings = Yaml::parse(file_get_contents($input->getOption('file')));
    print_r($settings);
    return LocoSystem::create($settings);
  }

  //  public function pickTargets($system, $input); // check $input->getArgument() and then topsort
}
