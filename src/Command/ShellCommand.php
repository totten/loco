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
      ->setDescription('Execute a shell command in the service\'s environment')
      ->addOption('escape', 'e', InputOption::VALUE_REQUIRED, 'Strategy for (re)escaping args to subcommand: (s)trict, (w)eak, (n)one', 's')
      ->addArgument('service', InputArgument::OPTIONAL, 'Service name. For an empty-service (common variables only), use "."', '.')
      ->addArgument('cmd', InputArgument::IS_ARRAY, 'Command to execute', [])
      ->setHelp('Execute a shell command in the service\'s environment

Example: Open common/base environment - with interactive shell
$ loco sh

Example: Open mysql environment - with interactive shell
$ loco sh mysql

Example: Open mysql environment - and run "mysqldump foo"
$ loco sh mysql -- mysqldump foo | gzip > foo.tar.gz

Example: Open mysql environment -- let the subshell evaluate variables
$ loco sh mysql -en \'echo "Data is in $LOCO_SVC_VAR"\'

Example: Open common/base environment - and run "mysqldump foo"
$ loco sh . -- mysqldump foo | gzip > foo.tar.gz

Note: To call a specific command in the common/base environment, you MUST specify
the placeholder service "."
');
//    $this->addUsage('asdf');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);

    /** @var LocoEnv $env */
    $env = $this->pickEnv($system, $input->getArgument('service'));
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

    if ($input->getArgument('cmd')) {
      $cmd = implode(' ', array_map($escaper, $input->getArgument('cmd')));
    }
    else {
      $cmd = 'bash --rcfile ' . escapeshellarg($this->findCreateRcFile($input));
      // $cmd = 'bash -i';
    }

    $output->getErrorOutput()->writeln("RUN: $cmd", OutputInterface::VERBOSITY_VERBOSE);
    if ($input->getOption('no-interaction')) {
      passthru($cmd, $retVal);
      return $retVal;
    }
    else {
      return Shell::runInteractively($cmd);
    }
  }

  protected function renderRc($svcName = NULL) {
    $ps1 = $svcName && $svcName !== '.' ? "loco($svcName)" : 'loco';
    $template = '[ -n "$PS1" ] && [ -e ~/.bashrc ] && source ~/.bashrc
[ -n "$PS1" ] && PS1=\'\n\033[1;32m[%%PS1TXT%%:\w]\$\033[0m \'
shopt -u nullglob';
    return strtr($template, [
      '%%PS1TXT%%' => $ps1,
    ]);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return string
   */
  protected function findCreateRcFile(InputInterface $input) {
    $rcContent = $this->renderRc($input->getArgument('service'));
    $md5 = md5($rcContent . ';;' . posix_getuid());
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'loco-' . $md5 . '.sh';
    if (!file_exists($file)) {
      file_put_contents($file, $rcContent);
      chmod($file, 0600);
    }
    return $file;
  }

}
