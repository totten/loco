<?php

namespace Loco;

use Loco\Utils\File;
use MJS\TopSort\Implementations\StringSort;

class LocoSystem {

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
   * @var string
   */
  public $format;

  /**
   * @var LocoEvaluator
   */
  public $evaluator;

  /**
   * Default values for environment variables (defined at system-level in loco.yml).
   *
   * @var LocoEnv
   */
  public $default_environment;

  /**
   * Environment variables inherited from the parent shell/process.
   *
   * @var LocoEnv
   */
  public $global_environment;

  /**
   * Environment variables (defined at system-level in loco.yml).
   *
   * @var LocoEnv
   */
  public $environment;

  /**
   * @var array
   */
  public $services;

  /**
   * @var array
   */
  public $export;

  /**
   * @param string $configFile
   *   Ex: /srv/foo/.loco/loco.yml
   * @param string $prjDir
   * @param array $settings
   * @return \Loco\LocoSystem
   */
  public static function create($configFile, $prjDir, $settings) {
    $prjDir = File::toAbsolutePath($prjDir);

    $system = new self();
    $system->config = $settings;
    $system->format = isset($settings['format']) ? $settings['format'] : 'loco-0.1';

    $filtered = Loco::filter('loco.expr.create', ['settings' => $settings, 'evaluator' => new LocoEvaluator()]);
    $system->evaluator = $filtered['evaluator'];

    $system->default_environment = LocoEnv::create(isset($settings['default_environment']) ? $settings['default_environment'] : [], $system->evaluator);
    $system->environment = LocoEnv::create(isset($settings['environment']) ? $settings['environment'] : [], $system->evaluator);
    $system->environment->set('LOCO_CFG_YML', $configFile, FALSE);
    if ($system->environment->getSpec('LOCO_PRJ') === NULL) {
      $system->environment->set('LOCO_PRJ', $prjDir, FALSE);
    }
    if ($system->environment->getSpec('LOCO_CFG') === NULL) {
      $system->environment->set('LOCO_CFG', '$LOCO_PRJ/.loco/config', TRUE);
    }
    if ($system->environment->getSpec('LOCO_VAR') === NULL) {
      $system->environment->set('LOCO_VAR', '$LOCO_PRJ/.loco/var', TRUE);
    }
    if ($system->environment->getSpec('PATH') === NULL && file_exists($binDir = "$prjDir/.loco/bin")) {
      $system->environment->set('PATH', $binDir . PATH_SEPARATOR . '${PATH}', TRUE);
    }
    $system->global_environment = LocoEnv::create([], $system->evaluator);
    $globalEnv = version_compare(PHP_VERSION, '7.1.alpha', '>=') ? getenv() : $_ENV;
    foreach ($globalEnv as $k => $v) {
      $system->global_environment->set($k, $v, FALSE);
    }
    Loco::filter('loco.system.create', ['system' => $system]);

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
    $srcs = [
      $this->default_environment,
      $this->global_environment,
      $this->environment,
    ];
    $env = LocoEnv::merge($srcs);
    Loco::filter('loco.system.mergeEnv', ['system' => $this, 'srcs' => $srcs, 'env' => $env]);
    return $env;
  }

}
