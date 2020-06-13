<?php

namespace Loco\Export;

use Loco\Utils\SystemdUtil;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait SystemdExportTrait {

  /**
   * @var \Loco\LocoSystem
   */
  protected $system;

  /**
   * @var \Loco\LocoService
   */
  protected $service;

  /**
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  protected $input;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  abstract public function buildSystemdIni();

  abstract public function buildFilename();

  /**
   * @param \Loco\LocoService|NULL $svc
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return static
   */
  public static function create($svc, InputInterface $input, OutputInterface $output) {
    $self = new static();
    $self->service = $svc;
    $self->system = $svc ? $svc->system : NULL;
    $self->input = $input;
    $self->output = $output;
    return $self;
  }

  public function export() {
    $filename = rtrim($this->input->getOption('out'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->buildFilename();
    $this->output->writeln("<info>Generate file <comment>{$filename}</comment></info>");
    $ini = $this->buildSystemdIni();
    $this->writeUnit($filename, $ini);
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
   * @param string $appName
   * @param string $svc
   *   Loco service name
   *   Ex: 'apache-vdr'
   * @return string
   *   Systemd unit name
   *   Ex: 'loco-apache-vdr.service'
   */
  protected function serviceName($appName, $svc) {
    $name = $appName . '-' . $svc;
    if (!preg_match('/^[ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_\-]+/', $name)) {
      // This test may be stricter than necessary. Easier to start conservative and relax as necessary.
      throw new \RuntimeException("Malformed service name [$name]");
    }

    return $name . ".service";
  }

  /**
   * @param string $filename
   * @param array $ini
   *   Array(string $section => string[] $lines].
   *   Ex: ['Service' => ['Environment=FOO=bar']]
   */
  protected function writeUnit($filename, $ini) {
    $buf = '';
    foreach ($ini as $section => $lines) {
      $buf .= "[" . $section . "]\n";
      $buf .= implode("\n", $lines);
      $buf .= "\n\n";
    }
    file_put_contents($filename, $buf);
  }

}
