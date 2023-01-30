<?php
namespace Loco;

use LesserEvil\ShellVerbosityIsEvil;
use Loco\Command\CleanCommand;
use Loco\Command\EnvCommand;
use Loco\Command\ExportCommand;
use Loco\Command\InitCommand;
use Loco\Command\RunCommand;
use Loco\Command\ShellCommand;
use Loco\Command\StatusCommand;
use Loco\Command\StopCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends \Symfony\Component\Console\Application {

  /**
   * Primary entry point for execution of the standalone command.
   */
  public static function main($binDir) {
    Loco::plugins()->init();
    Loco::filter('loco.app.boot', []);
    $application = new Application('loco', '@package_version@');
    $application->run();
  }

  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN') {
    parent::__construct($name, $version);
    $this->setCatchExceptions(TRUE);
    $this->addCommands($this->createCommands());
    // $this->setDefaultCommand('run');
  }

  protected function configureIO(InputInterface $input, OutputInterface $output) {
    ShellVerbosityIsEvil::doWithoutEvil(function() use ($input, $output) {
      parent::configureIO($input, $output);
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultInputDefinition() {
    $definition = parent::getDefaultInputDefinition();
    $definition->addOption(new InputOption('cwd', NULL, InputOption::VALUE_REQUIRED, 'If specified, use the given directory as working directory.'));
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function doRun(InputInterface $input, OutputInterface $output) {
    $workingDir = $input->getParameterOption(array('--cwd'));
    if (empty($workingDir) && getenv('LOCO_PRJ')) {
      $workingDir = getenv('LOCO_PRJ');
    }

    if (FALSE !== $workingDir && '' !== $workingDir) {
      if (!is_dir($workingDir)) {
        throw new \RuntimeException("Invalid working directory specified, $workingDir does not exist.");
      }
      if (!chdir($workingDir)) {
        throw new \RuntimeException("Failed to use directory specified, $workingDir as working directory.");
      }
    }
    Loco::filter('loco.app.run', []);
    return parent::doRun($input, $output);
  }

  /**
   * Construct command objects
   *
   * @return array of Symfony Command objects
   */
  public function createCommands() {
    $commands = array();
    $commands[] = new EnvCommand();
    $commands[] = new ShellCommand();
    $commands[] = new RunCommand();
    $commands[] = new InitCommand();
    $commands[] = new CleanCommand();
    $commands[] = new StopCommand();
    $commands[] = new StatusCommand();
    $commands[] = new ExportCommand();
    $commands = Loco::filter('loco.app.commands', ['commands' => $commands])['commands'];
    return $commands;
  }

}
