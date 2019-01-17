<?php
namespace Loco\Command;

use Loco\LocoEnv;
use Loco\LocoService;
use Loco\LocoSystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

trait LocoCommandTrait {
  public function configureSystemOptions() {
    $this->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Name of the loco.yml file', '.loco/loco.yml');
    return $this;
  }

  public function initSystem(InputInterface $input, OutputInterface $output) {
    if (!file_exists($input->getOption('config'))) {
      throw new \Exception("Failed to find loco config file: " . $input->getOption('config'));
    }

    $settings = Yaml::parse(file_get_contents($input->getOption('config')));
    return LocoSystem::create($settings);
  }

  /**
   * Determine the environment which applies for a given service-name.
   *
   * @param LocoSystem $system
   * @param string|NULL $svcName
   *   The name of the service whose environment we want.
   *   If blank, then fallback to the shared system environment.
   * @return LocoEnv
   */
  public function pickEnv($system, $svcName) {
    if (empty($svcName)) {
      return $system->createEnv();
    }
    elseif (isset($system->services[$svcName])) {
      return $system->services[$svcName]->createEnv();
    }
    else {
      throw new \RuntimeException("Unrecognized service: $svcName");
    }
  }

  /**
   * Get an ordered listed of services.
   *
   * @param LocoSystem $system
   * @param array|NULL $svcNames
   *   List of services requested by the user.
   *   If blank, then all services.
   * @return array
   *   Array(string $name => LocoService $svc).
   *   List of service-objects, including both directly-requested
   *   services and indirect dependencies.
   *   The list is sorted based on dependencies.
   */
  public function pickServices($system, $svcNames) {
    if (empty($svcNames)) {
      $todos = array_keys($system->services);
    }
    else {
      $todos = array_values($svcNames);
    }

    $svcs = [];
    while (!empty($todos)) {
      $retries = [];
      foreach ($todos as $todo) {
        if (!isset($system->services[$todo])) {
          throw new \RuntimeException("Unrecognized service name: $todo");
        }
        $missingDeps = array_diff($system->services[$todo]->depends ?: [], array_keys($svcs));
        if (empty($missingDeps)) {
          $svcs[$todo] = $system->services[$todo];
        }
        else {
          $retries = array_unique(array_merge($retries, $missingDeps, [$todo]));
        }
      }

      if (count($retries) > 0 && $this->isEqualArray($retries, $todos)) {
        throw new \RuntimeException("Service sequencing cannot be completed. The following services are involved with a dependency loop: "
          . implode(' ', $todos));
      }
      $todos = $retries;
    }

    return $svcs;
  }

  protected function isEqualArray($a, $b) {
    sort($a);
    sort($b);
    return $a == $b;
  }

}
