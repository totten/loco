<?php

namespace Loco;

class LocoPlugins {

  /**
   * Load any plugins.
   *
   * This will scan any folders listed in LOCO_PLUGIN_PATH. If LOCO_PLUGIN_PATH
   * is undefined, then the default with will be `/etc/loco/plugin:/usr/share/loco:/usr/local/share/loco:$HOME/.config/loco/plugin`.
   */
  public function init() {
    if (getenv('LOCO_PLUGIN_PATH')) {
      $paths = explode(PATH_SEPARATOR, getenv('LOCO_PLUGIN_PATH'));
    }
    else {
      $paths = ['/etc/loco/plugin', '/usr/share/loco/plugin', '/usr/local/share/loco/plugin'];
      if (getenv('HOME')) {
        $paths[] = getenv('HOME') . '/.config/loco/plugin';
      }
      // getcwd() . '/.loco/plugin'
    }

    foreach ($paths as $path) {
      if (file_exists($path) && is_dir($path)) {
        $this->load("$path/*.php");
      }
    }
  }

  /**
   * @param string $globExpr
   *   File globbing expression
   *   Ex: "/etc/loco/plugins/*.php"
   * @return int
   *   The number of loaded plugins
   */
  public function load($globExpr) {
    $files = glob("$globExpr");
    if (empty($files)) {
      return 0;
    }

    $count = 0;
    sort($files);
    foreach ($files as $file) {
      include_once $file;
      $count++;
    }
    return $count;
  }

}
