<?php

namespace Loco;

class LocoSystem {

  public $format;

  /** @var LocoEnv */
  public $default_environment;

  /** @var LocoEnv */
  public $global_environment;

  /** @var LocoEnv */
  public $environment;

  /** @var array */
  public $services;

  public static function create($settings) {
    $system = new self();
    $system->format = $settings['format'] ?: 'loco-0.1';
    $system->default_environment = LocoEnv::create($settings['default_environment'] ?: []);
    $system->environment = LocoEnv::create($settings['environment'] ?: []);
    $system->environment->set('LOCO_PRJ', getcwd(), FALSE);
    $system->environment->set('LOCO_CFG', '$LOCO_PRJ/.loco/config', TRUE);
    $system->environment->set('LOCO_VAR', '$LOCO_PRJ/.loco/var', TRUE);
    $system->global_environment = LocoEnv::create([]); // FIXME
    $system->services = [];
    if (!empty($settings['services'])) {
      foreach ($settings['services'] as $serviceName => $serviceSettings) {
        $system->services[$serviceName] = LocoService::create($system, $serviceName, $serviceSettings);
      }
    }
    return $system;
  }

  /**
   * @return LocoEnv
   */
  public function createEnv() {
    return LocoEnv::merge([
      $this->default_environment,
      $this->global_environment,
      $this->environment,
    ]);
  }

}