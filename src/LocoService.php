<?php
namespace Loco;

class LocoService {

  /** @var  LocoSystem */
  public $system;

  /** @var  string */
  public $name;

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
    $svc->environment = LocoEnv::create(isset($settings['environment']) ? $settings['environment'] : []);
    $svc->environment->set('LOCO_SVC', $name, FALSE);
    $svc->environment->set('LOCO_SVC_VAR', '$LOCO_VAR/$LOCO_SVC', TRUE);
    $svc->environment->set('LOCO_SVC_CFG', '$LOCO_CFG/$LOCO_SVC', TRUE);
    $svc->default_environment = LocoEnv::create(isset($settings['default_environment']) ? $settings['default_environment'] : []);
    $svc->init = isset($settings['init']) ? ((array) $settings['init']) : [];
    $svc->run = isset($settings['run']) ? $settings['run'] : NULL;
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

}
