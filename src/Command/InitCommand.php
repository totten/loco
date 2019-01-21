<?php
namespace Loco\Command;

use Loco\LocoEnv;
use Loco\LocoService;
use Loco\LocoSystem;
use Loco\Utils\Shell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;


class InitCommand extends \Symfony\Component\Console\Command\Command {

  use LocoCommandTrait;

  protected function configure() {
    $this
      ->setName('init')
      ->setDescription('Init the service(s)')
      ->addArgument('service', InputArgument::IS_ARRAY, 'Service name(s). Separated by commas or spaces. (Default: all)')
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force initialization, overwriting an existing data folder')
      ->setHelp('Initialize the service

This supports a mix of convention and configuration:

- Using a convention, any files named ".loco/config/SERVICE/FILE.loco.tpl" will
  be automatically mapped to ".loco/var/SERVICE/FILE".
- Using configuration, you may list a series of bash steps in the loco.yml.

      ');
    $this->configureSystemOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $system = $this->initSystem($input, $output);
    $svcs = $this->pickServices($system, $input->getArgument('service'));
    foreach ($svcs as $svc) {
      static::doInit($system, $svc, $input, $output);
    }
    return 0;
  }

  public static function doInit(LocoSystem $sys, LocoService $svc, InputInterface $input, OutputInterface $output) {
    $env = $svc->createEnv();

    $svcVar = $env->getValue('LOCO_SVC_VAR');
    if (file_exists($svcVar)) {
      if ($input->hasOption('force') && $input->getOption('force')) {
        CleanCommand::doClean($sys, $svc, $input, $output);
      }
      else {
        $output->writeln("<info>[<comment>$svc->name</comment>] Initialization is not required</info>", OutputInterface::VERBOSITY_VERBOSE);
        return 0;
      }
    }

    $output->writeln("<info>[<comment>$svc->name</comment>] Initialize service with data folder \"<comment>$svcVar</comment>\"</info>");
    \Loco\Utils\File::mkdir($svcVar);

    // We fork so that we can call putenv()+passthru() with impunity.

    self::doInitFileTpl($svc, $env, $output);
    self::doInitBash($svc, $env, $output);
  }

  /**
   * @param \Loco\LocoService $svc
   * @param \Loco\LocoEnv $env
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  protected static function doInitFileTpl(LocoService $svc, LocoEnv $env, OutputInterface $output) {
    $cfgDir = $env->getValue('LOCO_SVC_CFG');
    $destDir = $env->getValue('LOCO_SVC_VAR');
    if (!file_exists($cfgDir)) {
      return;
    }

    $envTokens = [];
    foreach ($env->getAllValues() as $key => $value) {
      $envTokens['{{' . $key . '}}'] = $value;
    }

    $finder = new Finder();
    foreach ($finder->in($cfgDir)->name('*.loco.tpl') as $srcFile) {
      $destFile = preg_replace(
        ';^' . preg_quote($cfgDir, ';') . ';',
        $destDir,
        preg_replace(';\.loco\.tpl$;', '', $srcFile)
      );

      $output->writeln("<info>[<comment>$svc->name</comment>] Generate \"<comment>$destFile</comment>\"</info>", Output::VERBOSITY_VERBOSE);
      \Loco\Utils\File::mkdir(dirname($destFile));
      file_put_contents($destFile, strtr(file_get_contents($srcFile), $envTokens));
    }

    // TODO: Add options for more robust template -- e.g. loco.php; loco.twig

  }

  /**
   * @param \Loco\LocoService $svc
   * @param \Loco\LocoEnv $env
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  protected static function doInitBash(LocoService $svc, LocoEnv $env, OutputInterface $output) {
    $pid = pcntl_fork();
    if ($pid == -1) {
      die("($svc->name) Failed to fork");
    }
    elseif ($pid) {
      // Parent
      $res = pcntl_waitpid($pid, $pidStatus);
      $exitCode = pcntl_wexitstatus($pidStatus);
      if ($exitCode === 0) {
        $output->writeln("<info>[<comment>$svc->name</comment>] Initialization finished</info>", OutputInterface::VERBOSITY_VERBOSE);
      }
      else {
        $failedStep = $exitCode - 1;
        throw new \RuntimeException("[$svc->name] Initialization failed at step #$failedStep: " . $svc->init[$failedStep]);
      }
    }
    else {
      // Child
      Shell::applyEnv($env);
      foreach (array_values($svc->init) as $stepNum => $init) {
        $cmdPrintable = $env->evaluate($init, 'keep');
        $output->writeln("<info>[<comment>$svc->name</comment>] Run \"<comment>$cmdPrintable</comment>\"</info>", OutputInterface::VERBOSITY_VERBOSE);
        passthru($init, $ret);
        if ($ret !== 0) {
          $output->writeln("<error>[$svc->name] Initialization failed in command \"$cmdPrintable\"</error>");
          exit(1 + $stepNum);
        }
      }
      exit(0);
    }
  }

}
