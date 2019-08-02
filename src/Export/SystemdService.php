<?php

namespace Loco\Export;

class SystemdService {

  use SystemdExportTrait;

  public function buildFilename() {
    return $this->serviceName($this->input->getOption('prefix'), $this->service->name);
  }

  /**
   * @param \Loco\LocoService $svc
   * @return array
   */
  public function buildSystemdIni() {
    $svc = $this->service;
    $env = $svc->createEnv();
    // FIXME: Prettier way to address near-duplication btwn Svc::createEnv() and this.
    $activeVars = array_merge(
      $this->system->default_environment->getKeys(),
      $svc->default_environment->getKeys(),
      // NOT: $this->system->global_environment->getKeys(),
      $this->system->environment->getKeys(),
      $svc->environment->getKeys()
    );

    $ini = ['Unit' => [], 'Service' => [], 'Install' => []];

    $ini['Unit'][] = "Description=" . $this->input->getOption('prefix') . $svc->name;
    $ini['Unit'][] = "After=syslog.target";
    $ini['Unit'][] = "After=network.target";
    if (isset($this->system->services['VOLUME'])) {
      $ini['Unit'][] = "Requires=" . $this->mountServiceName($env->getValue('LOCO_VAR'));
    }
    foreach ($svc->depends as $depend) {
      // Uppercase service ("VOLUME") is special; not mapped automatically.
      if (strtoupper($depend) !== $depend) {
        $ini['Unit'][] = "Requires=" . $this->serviceName($this->input->getOption('prefix'), $depend);
      }
    }

    if ($svc->pid_file) {
      $ini['Service'][] = "Type=forking";
      $ini['Service'][] = "PIDFile=" . $env->evaluate($svc->pid_file);
    }
    else {
      $ini['Service'][] = "Type=simple";
    }
    $ini['Service'][] = "PermissionsStartOnly=true";
    $ini['Service'][] = "ExecStartPre=/bin/bash -c " . escapeshellarg(/*LOCO_BIN*/ 'loco' . ' init -v ' . $svc->name);
    $ini['Service'][] = "ExecStart=/bin/bash -c " . escapeshellarg($svc->run);
    $ini['Service'][] = "TimeoutSec=300";
    $ini['Service'][] = "PrivateTmp=true";
    $ini['Service'][] = "LimitNOFILE=500000";
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

    $ini['Install'][] = 'WantedBy=multi-user.target';

    return $ini;
  }

}
