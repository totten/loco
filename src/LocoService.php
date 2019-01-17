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

  public static function create($system, $name, $settings) {
    $svc = new LocoService();
    $svc->system = $system;
    $svc->name = $name;
    $svc->environment = LocoEnv::create($settings['environment'] ?: []);
    $svc->environment->set('LOCO_SVC', $name, FALSE);
    $svc->environment->set('LOCO_SVC_VAR', '$LOCO_VAR/$LOCO_SVC', TRUE);
    $svc->environment->set('LOCO_SVC_CFG', '$LOCO_CFG/$LOCO_SVC', TRUE);
    $svc->default_environment = LocoEnv::create($settings['default_environment'] ?: []);
    $svc->init = $settings['init'] ?: [];
    $svc->run = $settings['run'] ?: NULL;
    $svc->pid_file = $settings['pid_file'] ?: NULL;
    $svc->depends = $settings['pid_file'] ?: [];
    return $svc;

//    throw new \Exception("TODO");
    // copy variuos properties
    // add 'LOCO_SVC', 'LOCO_SVC_VAR', 'LOCO_SVC_CFG'
  }

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
