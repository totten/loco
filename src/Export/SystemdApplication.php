<?php

namespace Loco\Export;

/**
 * Class SystemdApplication
 * @package Loco\Export
 *
 * The "application" unit is a grouping which contains all the other services.
 *
 * See, for example,
 * http://alesnosek.com/blog/2016/12/04/controlling-a-multi-service-application-with-systemd/
 */
class SystemdApplication {

  use SystemdExportTrait;

  public function buildFilename() {
    return $this->input->getOption('app') . '.service';
  }

  /**
   * @return array
   */
  public function buildSystemdIni() {
    $ini = ['Unit' => [], 'Service' => [], 'Install' => []];

    $ini['Unit'][] = "Description=" . $this->input->getOption('app') . ' (overall application)';

    $ini['Unit'][] = "After=syslog.target";
    $ini['Unit'][] = "After=network.target";

    $ini['Service'][] = "Type=oneshot";
    $ini['Service'][] = "ExecStart=/bin/true";
    $ini['Service'][] = "RemainAfterExit=yes";

    $ini['Install'][] = 'WantedBy=multi-user.target';

    return $ini;
  }

}
