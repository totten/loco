<?php

namespace Loco\Export;

use Loco\Application;
use Loco\LocoService;
use Loco\Utils\SystemdUtil;

class SystemdService {

  use SystemdExportTrait;

  public function buildFilename() {
    return SystemdUtil::escape($this->input->getOption('prefix') . $this->service->name) . '.service';
  }

  /**
   * @param LocoService $svc
   * @return array
   */
  public function buildSystemdIni() {
    $svc = $this->service;
    $env = $svc->createEnv();

    $ini = ['Unit' => [], 'Service' => [], 'Install' => []];

    $ini['Unit'][] = "Description=" . $this->input->getOption('prefix') . $svc->name;
    $ini['Unit'][] = "After=syslog.target";
    $ini['Unit'][] = "After=network.target";
    if (isset($this->system->services['VOLUME'])) {
      $ini['Unit'][] = "Requires=" . $this->mountServiceName($env->getValue('LOCO_VAR'));
    }
    foreach ($svc->depends as $depend) {
      $ini['Unit'][] = "Requires=" . SystemdUtil::escape($this->input->getOption('prefix') . $depend) . ".target";
    }

    if ($svc->pid_file) {
      $ini['Service'][] = "Type=forking";
      $ini['Service'][] = "PIDFile=" . $env->evaluate($svc->pid_file);
    }
    else {
      $ini['Service'][] = "Type=simple";
    }
    $ini['Service'][] = "PermissionsStartOnly=true";
    $ini['Service'][] = "ExecStartPre=/bin/bash -c " .  escapeshellarg(LOCO_BIN . ' init ' . $svc->name);
    $ini['Service'][] = "ExecStart=/bin/bash -c " .  escapeshellarg($svc->run);
    $ini['Service'][] = "TimeoutSec=300";
    $ini['Service'][] = "PrivateTmp=true";
    $ini['Service'][] = "LimitNOFILE=500000";
    $ini['Service'][] = "User=" . $this->input->getOption('user');
    $ini['Service'][] = "Group=" . $this->input->getOption('group');
    $ini['Service'][] = "WorkingDirectory=" . $env->getValue('LOCO_PRJ');

    $envValues = array_filter($env->getAllValues(), function($value, $key) {
      // Include any vars customized by loco config.
      $g = $this->system->global_environment;
      if ($g->getValue($key, 'null') !== $value) {
        $gv = $g->getValue($key, 'null');
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
