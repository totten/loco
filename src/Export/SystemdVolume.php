<?php

namespace Loco\Export;

class SystemdVolume {

  use SystemdExportTrait;

  public function buildFilename() {
    $env = $this->service->createEnv();
    return $this->mountServiceName($env->getValue('LOCO_VAR'));
  }

  /**
   * @return array
   */
  public function buildSystemdIni() {
    $svc = $this->service;
    $env = $svc->createEnv();

    $ini = ['Unit' => [], 'Mount' => [], 'Install' => []];

    $ini['Unit'][] = "Description=" . $this->input->getOption('prefix') . $svc->name;

    $ini['Mount'][] = "What=tmpfs";
    $ini['Mount'][] = "Type=tmpfs";
    $ini['Mount'][] = "Where=" . $env->getValue('LOCO_VAR');
    $ini['Mount'][] = sprintf("Options=nosuid,size=%s,uid=%s,gid=%s",
      $env->getValue('LOCO_RAMDISK'),
      $this->input->getOption('user'),
      $this->input->getOption('group'));

    $ini['Install'][] = 'WantedBy=multi-user.target';

    return $ini;
  }

}
