<?php

namespace Loco\Export;

use Loco\LocoService;
use Loco\LocoSystem;
use Loco\Utils\SystemdUtil;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait SystemdExportTrait {

  /**
   * @var LocoSystem
   */
  protected $system;

  /**
   * @var LocoService
   */
  protected $service;

  /**
   * @var InputInterface
   */
  protected $input;

  /**
   * @var OutputInterface
   */
  protected $output;

  public abstract function buildSystemdIni();
  public abstract function buildFilename();

  public static function create(LocoService $svc, InputInterface $input, OutputInterface $output) {
    $self = new static();
    $self->service = $svc;
    $self->system = $svc->system;
    $self->input = $input;
    $self->output = $output;
    return $self;
  }

  public function export() {
    $filename = rtrim($this->input->getOption('out'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->buildFilename();
    $svc = $this->service;

    $this->output->writeln("<info>[<comment>{$svc->name}</comment>] Generate file <comment>{$filename}</comment></info>");

    $ini = $this->buildSystemdIni();

    $buf = '';
    foreach ($ini as $section => $lines) {
      $buf .= "[" . $section . "]\n";
      $buf .= implode("\n", $lines);
      $buf .= "\n\n";
    }
    file_put_contents($filename, $buf);
  }

  /**
   * Determine the name for a "mount" service for the given path.
   *
   * @param string $dir
   *   Ex: '/mnt/stuff'
   * @return string
   *   Ex: 'mnt-stuff.mount'
   */
  protected function mountServiceName($dir) {
    return SystemdUtil::escapePath($dir) . '.mount';
  }

  /**
   * Determine the systemd unit name
   *
   * @param string $prefix
   * @param string $svc
   *   Loco service name
   *   Ex: 'apache-vdr'
   * @return string
   *   Systemd unit name
   *   Ex: 'loco-apache-vdr.service'
   */
  protected function serviceName($prefix, $svc) {
    $name = $prefix . $svc;
    if (!preg_match('/^[ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_\-]+/', $name)) {
      // This test may be stricter than necessary. Easier to start conservative and relax as necessary.
      throw new \RuntimeException("Malformed service name [$name]");
    }

    return $name . ".service";
  }

}