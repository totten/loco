<?php
namespace Loco;

use Psr\Log\NullLogger;

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
   * @param LocoService $system
   * @param string $name
   * @param array $settings
   * @return \Loco\LocoService
   */
  public static function create($system, $name, $settings) {
    $svc = new LocoService();
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
   * @return bool|NULL
   *   - TRUE: The service is running
   *   - FALSE: The service is not running
   *   - NULL: The service is not assessable.
   */
  public function isRunning($env = NULL) {
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
  public function getPid($env = NULL) {
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


}
