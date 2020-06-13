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
    $iniTemplate = __DIR__ . '/SystemdServiceTemplate.json';

    // FIXME: Prettier way to address near-duplication btwn Svc::createEnv() and this.
    $activeVars = array_merge(
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

    $ini['Service'][] = "ExecStartPre=/bin/bash -c " . escapeshellarg(/*LOCO_BIN*/ 'loco' . ' init -v ' . $svc->name);
    $ini['Service'][] = "ExecStart=/bin/bash -c " . escapeshellarg($svc->run);
    $ini['Service'][] = "User=" . $this->input->getOption('user');
    $ini['Service'][] = "Group=" . $this->input->getOption('group');
    $ini['Service'][] = "WorkingDirectory=" . $env->getValue('LOCO_PRJ');

    $envValues = array_filter($env->getAllValues(), function($value, $key) use ($activeVars) {
      // Include any vars referenced/customized by loco config.
      if (in_array($key, $activeVars)) {
        return TRUE;
      }

      $pat = sprintf('/^(' . $this->input->getOption('include-env') . ')$/');
      if (preg_match($pat, $key)) {
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
