<?php
namespace Loco;

use Loco\Utils\Shell;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class LocoService {

  /**
   * The raw, unprocessed configuration data (per loco.yml).
   *
   * There is little point in modifying data once it gets into $config.
   * This is to allow extra reads of new/improvisational options.
   *
   * @var array
   */
  public $config;

  /**
   * @var LocoSystem
   */
  public $system;

  /**
   * @var string
   */
  public $name;

  /**
   * @var bool
   */
  public $enabled;

  /**
   * Environment variables (defined at service-level in loco.yml).
   *
   * @var LocoEnv
   */
  public $environment;

  /**
   * Default values for environment variables (defined at service-level in loco.yml).
   *
   * @var LocoEnv
   */
  public $default_environment;

  /**
   * @var array
   */
  public $init;

  /**
   * @var array
   */
  public $cleanup;

  /**
   * @var string|null
   */
  public $run;

  /**
   * @var string|null
   */
  public $pid_file;

  /**
   * @var string|null
   */
  public $message;

  /**
   * @var array
   */
  public $depends;

  /**
   * @var array
   *   Set of overrides for systemd-specific options.
   *   Ex: ['Service' => ['Type' => 'forking']]
   */
  public $systemd;

  /**
   * @param LocoSystem $system
   * @param string $name
   * @param array $settings
   * @return \Loco\LocoService
   */
  public static function create($system, $name, $settings) {
    $svc = new static();
    $svc->config = $settings;
    $svc->system = $system;
    $svc->name = $name;
    $svc->systemd = $settings['systemd'] ?? [];
    $svc->enabled = isset($settings['enabled']) ? $settings['enabled'] : TRUE;
    $svc->environment = LocoEnv::create(isset($settings['environment']) ? $settings['environment'] : []);
    $svc->environment->set('LOCO_SVC', $name, FALSE);
    $svc->environment->set('LOCO_SVC_VAR', '$LOCO_VAR/$LOCO_SVC', TRUE);
    $svc->environment->set('LOCO_SVC_CFG', '$LOCO_CFG/$LOCO_SVC', TRUE);
    $svc->default_environment = LocoEnv::create(isset($settings['default_environment']) ? $settings['default_environment'] : []);
    foreach (['init', 'cleanup', 'depends'] as $key) {
      $svc->{$key} = isset($settings[$key]) ? ((array) $settings[$key]) : [];
    }
    foreach (['run', 'message', 'pid_file'] as $key) {
      $svc->{$key} = isset($settings[$key]) ? $settings[$key] : NULL;
    }

    Loco::filter('loco.service.create', ['service' => $svc]);
    return $svc;
  }

  /**
   * @return \Loco\LocoEnv
   */
  public function createEnv() {
    $srcs = [
      $this->system->default_environment,
      $this->default_environment,
      $this->system->global_environment,
      $this->system->environment,
      $this->environment,
    ];
    $env = LocoEnv::merge($srcs);
    Loco::filter('loco.service.mergeEnv', ['service' => $this, 'srcs' => $srcs, 'env' => $env]);
    return $env;
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
    return $pid ? ((bool) posix_kill(rtrim($pid), 0)) : NULL;
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

    $this->doInitMkdir($output, $env);
    $this->doInitFileTpl($output, $env);
    Shell::runAll($output, $env, $this->init, $this->name);
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \Loco\LocoEnv $env
   */
  protected function doInitMkdir(OutputInterface $output, LocoEnv $env) {
    $svcVar = $env->getValue('LOCO_SVC_VAR');
    if (!empty($svcVar)) {
      $output->writeln("<info>[<comment>$this->name</comment>] Initialize folder: <comment>$svcVar</comment></info>");
      \Loco\Utils\File::mkdir($svcVar);
    }
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \Loco\LocoEnv $env
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

  public function cleanup(OutputInterface $output, LocoEnv $env = NULL) {
    $env = $env ?: $this->createEnv();

    $svcVar = $env->getValue('LOCO_SVC_VAR');
    if (file_exists($svcVar)) {
      Shell::runAll($output, $env, $this->cleanup, $this->name);
      $output->writeln("<info>[<comment>$this->name</comment>] Cleanup folder: <comment>$svcVar</comment></info>");
      \Loco\Utils\File::removeAll($svcVar);
    }
    else {
      $output->writeln("<info>[<comment>$this->name</comment>] Nothing to cleanup</info>", OutputInterface::VERBOSITY_VERBOSE);
    }
  }

}
