<?php
namespace Loco\Command;

use Loco\LocoEnv;
use Loco\LocoService;
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
      ->addOption('service', 's', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Service name')
      ->setHelp('Display service status');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);
    $svcNames = empty($input->getOption('service'))
      ? array_keys($system->services)
      : explode(',', implode(',', $input->getOption('service')));

    sort($svcNames);

    $rows = [];
    foreach ($svcNames as $svcName) {
      /** @var LocoService $svc */
      $svc = $system->services[$svcName];
      $env = $svc->createEnv();

      if (empty($svc->pid_file)) {
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
