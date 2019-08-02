<?php
namespace Loco;

use Symfony\Component\Console\Output\OutputInterface;

class LocoVolume extends LocoService {

  const DEFAULT_NAME = 'VOLUME';

  /**
   * @param LocoSystem $system
   * @param string $name
   * @param array $settings
   * @return \Loco\LocoVolume
   */
  public static function create($system, $name, $settings) {
    if (!empty($settings['ramdisk'])) {
      $defaults = [
        'init' => ['ramdisk start "$LOCO_VAR" "$LOCO_RAMDISK"'],
        'cleanup' => ['ramdisk stop "$LOCO_VAR"'],
        'message' => 'Loco data volume is a ram disk "<comment>$LOCO_VAR</comment>".',
      ];
      $settings = array_merge($defaults, $settings);
    }

    $svc = parent::create($system, $name, $settings);
    $svc->environment->set('LOCO_SVC_VAR', '$LOCO_VAR', TRUE);
    // $svc->environment->set('LOCO_SVC_CFG', '', FALSE);

    if (!empty($settings['ramdisk'])) {
      $svc->environment->set('LOCO_RAMDISK', $settings['ramdisk'], TRUE);
    }

    $svc->init[] = 'touch "$LOCO_VAR/.loco-volume"';
    return $svc;
  }

  public function isInitialized(LocoEnv $env = NULL) {
    $env = $env ?: $this->createEnv();
    return file_exists($env->evaluate('$LOCO_VAR/.loco-volume'));
    // return file_exists($env->evaluate('$LOCO_SVC_VAR'));
  }

  public function getPid(LocoEnv $env = NULL) {
    return NULL;
  }

  protected function doInitFileTpl(OutputInterface $output, LocoEnv $env = NULL) {
    // Not supported on volumes -- note the order of init() steps.
  }

}
