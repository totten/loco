<?php

namespace Loco;

use MJS\TopSort\Implementations\StringSort;

class LocoSystem {

  public $format;

  /**
   * @var LocoEnv */
  public $default_environment;

  /**
   * @var LocoEnv */
  public $global_environment;

  /**
   * @var LocoEnv */
  public $environment;

  /**
   * @var array */
  public $services;

  /**
   * @param array $settings
   * @return \Loco\LocoSystem
   */
  public static function create($prjDir, $settings) {
    $system = new self();
    $system->format = isset($settings['format']) ? $settings['format'] : 'loco-0.1';
    $system->default_environment = LocoEnv::create(isset($settings['default_environment']) ? $settings['default_environment'] : []);
    $system->environment = LocoEnv::create(isset($settings['environment']) ? $settings['environment'] : []);
    $system->environment->set('LOCO_PRJ', $prjDir, FALSE);
    $system->environment->set('LOCO_CFG', '$LOCO_PRJ/.loco/config', TRUE);
    $system->environment->set('LOCO_VAR', '$LOCO_PRJ/.loco/var', TRUE);
    if (file_exists($binDir = "$prjDir/.loco/bin")) {
      $system->environment->set('PATH', $binDir . PATH_SEPARATOR . getenv('PATH'), FALSE);
    }
    $system->global_environment = LocoEnv::create([]);
    foreach ($_ENV as $k => $v) {
      $system->global_environment->set($k, $v, FALSE);
    }
    $system->services = [];
    if (!empty($settings['services'])) {
      foreach ($settings['services'] as $serviceName => $serviceSettings) {
        $system->services[$serviceName] = LocoService::create($system, $serviceName, $serviceSettings);
      }
    }

    if (!empty($settings['volume'])) {
      $volume = LocoVolume::create($system, LocoVolume::DEFAULT_NAME, $settings['volume']);
      foreach ($system->services as $svcName => $svc) {
        $svc->depends[] = $volume->name;
      }
      $system->services[$volume->name] = $volume;
    }

    return $system;
  }

  /**
   * Determine the full set of dependencies for a service.
   *
   * @param string|array $svcNames
   *   List of desired service names.
   * @return array
   *   List of all required service names (with topological sorting),
   *   including the original services and any recurisve dependencies.
   */
  public function findDeps($svcNames) {
    $svcNames = (array) $svcNames;

    $deps = [];
    while (!empty($svcNames)) {
      $todo = array_shift($svcNames);
      if (!isset($this->services[$todo])) {
        throw new \RuntimeException("Cannot resolve service dependency: $todo");
      }
      $deps[$todo] = $this->services[$todo]->depends;
      $svcNames = array_diff(array_merge($svcNames, $this->services[$todo]->depends), array_keys($deps));
    }

    $sorter = new StringSort();
    $sorter->set($deps);
    return (array) $sorter->sort();
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
