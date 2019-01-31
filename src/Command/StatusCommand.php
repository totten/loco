<?php
namespace Loco\Command;

use Loco\LocoEnv;
use Loco\LocoService;
use Loco\LocoVolume;
use Loco\Utils\Shell;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class StatusCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  protected function configure() {
    $this
      ->setName('status')
      ->setAliases(array())
      ->setDescription('Display service status')
      ->addArgument('service', InputArgument::IS_ARRAY, 'Service name(s). Separated by commas or spaces. (Default: all)')
      ->setHelp('Display service status');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);
    $svcNames = $input->getArgument('service')
      ? $input->getArgument('service')
      : array_keys($system->services);

    sort($svcNames);

    $rows = [];
    foreach ($svcNames as $svcName) {
      /** @var LocoService $svc */
      $svc = $system->services[$svcName];
      $env = $svc->createEnv();

      if (empty($svc->pid_file) && !($svc instanceof LocoVolume)) {
        $name = $svc->name;
        $process = '?';
      }
      elseif ($svc->isRunning($env)) {
        $name = "<info>$svc->name</info>";
        $process = "<info>" . $svc->getPid($env) . "</info>";
      }
      else {
        $name = "<comment>$svc->name</comment>";
        $process = '<comment>-</comment>';
      }

      if (file_exists($env->getValue('LOCO_SVC_VAR'))) {
        $dataDir = '<info>+ ' . $env->getValue('LOCO_SVC_VAR') . '</info>';
      }
      else {
        $dataDir = '<comment>- ' . $env->getValue('LOCO_SVC_VAR') . '</comment>';
      }

      $rows[] = [$name, $process, $dataDir];

    }

    $table = new Table($output);
    $table
      ->setHeaders(['Name', 'Process', 'Data Dir'])
      ->setRows($rows);
    $table->render();

    return 0;
  }

}
