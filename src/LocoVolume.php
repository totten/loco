<?php
namespace Loco;

use Symfony\Component\Console\Output\OutputInterface;

class LocoVolume extends LocoService {

  /**
   * @param LocoSystem $system
   * @param string $name
   * @param array $settings
   * @return \Loco\LocoVolume
   */
  public static function create($system, $name, $settings) {
    $svc = parent::create($system, $name, $settings);
    $svc->environment->set('LOCO_SVC_VAR', '$LOCO_VAR', TRUE);
    // $svc->environment->set('LOCO_SVC_CFG', '', FALSE);
    $svc->init[] = 'touch "$LOCO_VAR/.loco-volume"';
    return $svc;
  }

  public function isRunning($env = NULL) {
    $env = $env ?: $this->createEnv();
    return file_exists($env->evaluate('$LOCO_VAR/.loco-volume'));
    // return file_exists($env->evaluate('$LOCO_SVC_VAR'));
  }

  public function getPid($env = NULL) {
    return NULL;
  }

  protected function doInitFileTpl(OutputInterface $output, LocoEnv $env = NULL) {
    // Not supported on volumes -- note the order of init() steps.
  }


}
