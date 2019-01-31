<?php
namespace Loco;

use Loco\Utils\Shell;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class LocoService {

  /** @var LocoSystem */
  public $system;

  /** @var string */
  public $name;

  /** @var bool */
  public $enabled;

  /** @var LocoEnv */
  public $environment;

  /** @var LocoEnv */
  public $default_environment;

  /** @var string */
  public $init;

  /** @var string|NULL */
  public $run;

  /** @var string|NULL */
  public $pid_file;

  /** @var string|NULL */
  public $message;

  /** @var array */
  public $depends;

  /**
   * @param LocoSystem $system
   * @param string $name
   * @param array $settings
   * @return \Loco\LocoService
   */
  public static function create($system, $name, $settings) {
    $svc = new static();
    $svc->system = $system;
    $svc->name = $name;
    $svc->enabled = isset($settings['enabled']) ? $settings['enabled'] : TRUE;
    $svc->environment = LocoEnv::create(isset($settings['environment']) ? $settings['environment'] : []);
    $svc->environment->set('LOCO_SVC', $name, FALSE);
    $svc->environment->set('LOCO_SVC_VAR', '$LOCO_VAR/$LOCO_SVC', TRUE);
    $svc->environment->set('LOCO_SVC_CFG', '$LOCO_CFG/$LOCO_SVC', TRUE);
    $svc->default_environment = LocoEnv::create(isset($settings['default_environment']) ? $settings['default_environment'] : []);
    $svc->init = isset($settings['init']) ? ((array) $settings['init']) : [];
    $svc->run = isset($settings['run']) ? $settings['run'] : NULL;
    $svc->message = isset($settings['message']) ? $settings['message'] : NULL;
    $svc->pid_file = isset($settings['pid_file']) ? $settings['pid_file'] : NULL;
    $svc->depends = isset($settings['depends']) ? ((array) $settings['depends']) : [];
    return $svc;
  }

  /**
   * @return \Loco\LocoEnv
   */
  public function createEnv() {
    return LocoEnv::merge([
      $this->system->default_environment,
      $this->default_environment,
      $this->system->global_environment,
      $this->system->environment,
      $this->environment,
    ]);
  }

  /**
   * @param LocoEnv $env
   * @return bool
   */
  public function isInitialized(LocoEnv $env = NULL) {
    $env = $env ?: $this->createEnv();
    return file_exists($env->getValue('LOCO_SVC_VAR'));
  }

  /**
   * @return bool|NULL
   *   - TRUE: The service is running
   *   - FALSE: The service is not running
   *   - NULL: The service is not assessable.
   */
  public function isRunning(LocoEnv $env = NULL) {
    $env = $env ?: $this->createEnv();

    $file = $env->evaluate($this->pid_file);
    if (empty($file) || !file_exists($file)) {
      return NULL;
    }

    $pid = $this->getPid($env);
    return $pid ? ((bool) posix_kill(rtrim($pid),0)) : NULL;
  }

  /**
   * @return int|NULL
   *   - int:
   *   - NULL: The service is not assessable or not running.
   */
  public function getPid(LocoEnv $env = NULL) {
    $env = $env ?: $this->createEnv();

    $file = $env->evaluate($this->pid_file);
    if (empty($file) || !file_exists($file)) {
      return NULL;
    }

    $pid = trim(file_get_contents($file));
    if (!is_numeric($pid)) {
      throw new \RuntimeException("PID file ($file) contains invalid PID");
    }
    return $pid;
  }

  public function init(OutputInterface $output, LocoEnv $env = NULL) {
    $env = $env ?: $this->createEnv();

    $svcVar = $env->getValue('LOCO_SVC_VAR');
    if (!empty($svcVar)) {
      $output->writeln("<info>[<comment>$this->name</comment>] Initialize folder: <comment>$svcVar</comment></info>");
      \Loco\Utils\File::mkdir($svcVar);
    }

    $this->doInitFileTpl($output, $env);
    Shell::runAll($output, $env, $this->init, $this->name);
  }

  /**
   * @param \Loco\LocoEnv $env
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  protected function doInitFileTpl(OutputInterface $output, LocoEnv $env = NULL) {
    $env = $env ?: $this->createEnv();
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

      $output->writeln("<info>[<comment>$this->name</comment>] Generate file: <comment>$destFile</comment></info>", OutputInterface::VERBOSITY_VERBOSE);
      \Loco\Utils\File::mkdir(dirname($destFile));
      file_put_contents($destFile, strtr(file_get_contents($srcFile), $envTokens));
    }

    // TODO: Add options for more robust template -- e.g. loco.php; loco.twig
  }

}
