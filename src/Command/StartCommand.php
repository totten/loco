<?php

namespace Loco\Command;

use Loco\LocoVolume;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StartCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  protected function configure() {
    $this
      ->setName('start')
      ->setAliases(array())
      ->setDescription('Start the service(s) in the background (experimental)')
      ->addArgument('service', InputArgument::IS_ARRAY, 'Service name(s). Separated by commas or spaces. (Default: all)')
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force initialization, overwriting an existing data folder')
      ->setHelp(implode("\n", [
        "Limitations:",
        "- Services must generate their own pidFile",
        "- If a service dies, it does not try to re-start.",
      ]));
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    declare(ticks = 1);

    $system = $this->initSystem($input, $output);
    $services = $this->pickServices($system, $input->getArgument('service'));
    $forceables = $this->pickForceables($input->getOption('force'), $input->getArgument('service'), $services);

    $output->writeln("<info>[<comment>loco</comment>] Start services: " . $this->formatList(array_keys($services)) . "</info>", OutputInterface::VERBOSITY_VERBOSE);

    $this->output = $output;

    if (empty($services)) {
      $output->writeln("<error>No services to run</error>");
      return 1;
    }

    foreach ($services as $svcName => $svc) {
      /** @var \Loco\LocoService $svc */
      if (!($svc instanceof LocoVolume)) {
        if (!empty($svc->run) && empty($svc->pid_file)) {
          $output->writeln("<error>Service \"{$svcName}\" is not compatible with \"loco start\". Service must define \"run\" and \"pidFile\".</error>");
          return 1;
        }

        if (empty($svc->log_file)) {
          $svc->log_file = '${LOCO_SVC_VAR}/loco.log';
        }
      }
    }

    $postStartupMessages = [];

    foreach ($services as $svcName => $svc) {
      /** @var \Loco\LocoService $svc */
      if ($svc->isRunning()) {
        $this->output->writeln("<info>[<comment>$svcName</comment>] Service already running. Ignoring.</info>");
        continue;
      }

      /** @var \Loco\LocoEnv $env */
      $env = $svc->createEnv();

      InitCommand::doInit($svc, in_array($svc->name, $forceables), $output);
      if ($svc->run) {
        $svc->spawn($output);
      }
      if ($svc->message) {
        $postStartupMessages[] = $env->evaluate("<info>[<comment>$svcName</comment>] {$svc->message}</info>");
      }
    }

    if (!empty($postStartupMessages)) {
      $this->printStartupSummary($postStartupMessages);
    }

    return 0;
  }

  protected function printStartupSummary(array $postStartupMessages): void {
    $this->output->writeln("\n");
    $this->output->writeln("<info>======================[ Startup Summary ]======================</info>");
    foreach ($postStartupMessages as $message) {
      $this->output->writeln($message);
    }
    $this->output->writeln("\n<info>Services are starting. To shutdown, run <comment>loco stop</comment>.</info>");
    $this->output->writeln("<info>===============================================================</info>");
  }

}
