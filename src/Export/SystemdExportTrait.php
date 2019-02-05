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

  public static function create(LocoService $svc, InputInterface $input, OutputInterface $output) {
    $self = new static();
    $self->service = $svc;
    $self->system = $svc->system;
    $self->input = $input;
    $self->output = $output;
    return $self;
  }

  public function export() {
    $filename = $this->buildFilename();
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

  protected function mountServiceName($dir) {
    return SystemdUtil::escapePath($dir) . '.mount';
  }

}