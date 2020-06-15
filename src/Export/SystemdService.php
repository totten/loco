<?php

namespace Loco\Export;

class SystemdService {

  use SystemdExportTrait;

  /**
   * Specifies a white list of pre-existing/global environment variables that
   * can be inherited and propagated to the service.
   */
  const INCULDE_ENV_DEFAULT = '/^(PATH|NIX_SSL_.*)$/';

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
    $iniTemplate = __DIR__ . '/SystemdServiceTemplate.json';

    $significantVars = array_merge(
      $this->system->default_environment->getKeys(),
      $svc->default_environment->getKeys(),
      // NOT: $this->system->global_environment->getKeys(),
      $this->system->environment->getKeys(),
      $svc->environment->getKeys()
    );

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

    $ini['Service'][] = "Type=" . ($svc->systemd['Service']['Type'] ?? 'exec');
    if ($svc->pid_file) {
      $ini['Service'][] = "PIDFile=" . $env->evaluate($svc->pid_file);
    }

    // LOCO_BIN ?
    $locoRun = sprintf('loco run -X -v -c %s %s', escapeshellarg($env->getValue('LOCO_CFG_YML')), escapeshellarg($svc->name));
    $ini['Service'][] = "ExecStart=/bin/bash -c " . escapeshellarg($locoRun);

    $ini['Service'][] = "User=" . $this->input->getOption('user');
    $ini['Service'][] = "Group=" . $this->input->getOption('group');
    $ini['Service'][] = "WorkingDirectory=" . $env->getValue('LOCO_PRJ');

    $includeEnvPat = $svc->config['export']['include_env']
      ?? $svc->system->config['export']['include_env']
      ?? self::INCULDE_ENV_DEFAULT;

    // When 'loco run -X' runs, it will re-compute defaults+mandatory values. However, we may want to reproduce
    // some of the original environment.
    $envValues = array_filter($this->system->global_environment->getAllValues(), function($value, $key) use ($significantVars, $includeEnvPat) {
      // Include any vars referenced/customized by loco config.
      if (in_array($key, $significantVars)) {
        return TRUE;
      }

      // Include any vars whitelisted by the loco config (`export: include_env: REGEXP`).
      if (preg_match($includeEnvPat, $key)) {
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
