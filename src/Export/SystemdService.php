<?php

namespace Loco\Export;

class SystemdService {

  use SystemdExportTrait;

  public function buildFilename() {
    return $this->serviceName($this->input->getOption('app'), $this->service->name);
  }

  /**
   * @return array
   */
  public function buildSystemdIni() {
    $svc = $this->service;
    $env = $svc->createEnv();
    $appSvcName = $this->input->getOption('app') . '.service';
    $iniTemplate = empty($svc->run)
      ? __DIR__ . '/SystemdOneshotTemplate.json'
      : __DIR__ . '/SystemdServiceTemplate.json';

    $significantVars = array_merge(
      $this->system->default_environment->getKeys(),
      $svc->default_environment->getKeys(),
      // NOT: $this->system->global_environment->getKeys(),
      $this->system->environment->getKeys(),
      $svc->environment->getKeys()
    );

    $exportOptions = $svc->createExportOptions();

    $ini = json_decode(file_get_contents($iniTemplate), 1);

    $ini['Unit'][] = "Description=" . $this->input->getOption('app') . '-' . $svc->name;

    $ini['Unit'][] = "PartOf=$appSvcName";
    $ini['Unit'][] = "After=$appSvcName";
    $ini['Install'][] = "WantedBy=$appSvcName";

    if (isset($this->system->services['VOLUME'])) {
      $ini['Unit'][] = "Requires=" . $this->mountServiceName($env->getValue('LOCO_VAR'));
    }
    foreach ($svc->depends as $depend) {
      // Uppercase service ("VOLUME") is special; not mapped automatically.
      if (strtoupper($depend) !== $depend) {
        $ini['Unit'][] = "Requires=" . $this->serviceName($this->input->getOption('app'), $depend);
      }
    }

    if ($svc->run) {
      $ini['Service'][] = "Type=" . $exportOptions['type'];
      if ($svc->pid_file) {
        $ini['Service'][] = "PIDFile=" . $env->evaluate($svc->pid_file);
      }

      $locoRun = sprintf('loco run -X -v -c %s %s', escapeshellarg($env->getValue('LOCO_CFG_YML')), escapeshellarg($svc->name));
      $ini['Service'][] = "ExecStart=/bin/bash -c " . escapeshellarg($locoRun);
    }
    else {
      $ini['Service'][] = "Type=" . 'oneshot';
      $locoRun = sprintf('loco init -v -c %s %s', escapeshellarg($env->getValue('LOCO_CFG_YML')), escapeshellarg($svc->name));
      $ini['Service'][] = "ExecStart=/bin/bash -c " . escapeshellarg($locoRun);
    }


    $ini['Service'][] = "User=" . $this->input->getOption('user');
    $ini['Service'][] = "Group=" . $this->input->getOption('group');
    $ini['Service'][] = "WorkingDirectory=" . $env->getValue('LOCO_PRJ');

    // When 'loco run -X' runs, it will re-compute defaults+mandatory values. However, we may want to reproduce
    // some of the original environment.
    $envValues = array_filter($this->system->global_environment->getAllValues(), function($value, $key) use ($significantVars, $exportOptions) {
      // Include any vars referenced/customized by loco config.
      if (in_array($key, $significantVars)) {
        return TRUE;
      }

      // Include any vars whitelisted by the loco config (`export: include_env: REGEXP`).
      if (preg_match($exportOptions['include_env'], $key)) {
        return TRUE;
      }

      return FALSE;
    }, ARRAY_FILTER_USE_BOTH);
    ksort($envValues);
    foreach ($envValues as $key => $value) {
      $ini['Service'][] = sprintf('Environment=%s=%s', $key, $value);
    }

    return $ini;
  }

}
