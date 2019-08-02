<?php
namespace Loco\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ExportCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  protected function configure() {
    $currentUser = posix_getpwuid(posix_getuid());
    $currentGroup = posix_getgrgid(posix_getgid());

    $this
      ->setName('export')
      ->setAliases(array())
      ->setDescription('Export service definitions to systemd (EXPERIMENTAL)')
      ->addArgument('service', InputArgument::IS_ARRAY, 'Service name(s). Separated by commas or spaces. (Default: all)')
      ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output folder')
      ->addOption('fmt', NULL, InputOption::VALUE_REQUIRED, 'Output format', 'systemd')
      ->addOption('prefix', NULL, InputOption::VALUE_REQUIRED, 'Prefix to apply to exported service names', 'loco_')
      ->addOption('user', NULL, InputOption::VALUE_REQUIRED, 'User to execute the service as', $currentUser['name'])
      ->addOption('group', NULL, InputOption::VALUE_REQUIRED, 'User to execute the service as', $currentGroup['name'])
      ->addOption('include-env', NULL, InputOption::VALUE_REQUIRED, 'Env vars to exclude. Regex-enabled.', 'PATH|NIX_SSL_.*')
      ->setHelp('Export service definitions to another format');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);
    $svcs = $this->pickServices($system, $input->getArgument('service'));

    $out = $input->getOption('out');
    if (empty($out)) {
      throw new \Exception("Must specify output folder (--out)");
    }
    elseif (!is_dir($out) || !file_exists($out)) {
      if (!mkdir($out, 0777, TRUE)) {
        throw new \RuntimeException("Failed to make output folder: $out");
      }
    }

    $gens = [];
    $gens['systemd']['Loco\LocoVolume'] = 'Loco\Export\SystemdVolume::create';
    $gens['systemd']['Loco\LocoService'] = 'Loco\Export\SystemdService::create';

    foreach ($svcs as $svc) {
      if (isset($gens[$input->getOption('fmt')][get_class($svc)])) {
        $exporter = call_user_func($gens[$input->getOption('fmt')][get_class($svc)], $svc, $input, $output);
        $exporter->export();
      }
      else {
        throw new \RuntimeException(sprintf("Cannot export %s from class %s to format %s",
          $svc->name,
          get_class($svc),
          $input->getOption('fmt')
          ));
      }
    }
  }

}
