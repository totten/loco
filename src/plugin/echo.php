<?php

namespace Loco;

Loco::dispatcher()->addListener('loco.expr.functions', function (LocoEvent $e) {
  $e['functions']['echo'] = function (...$argv) {
    return implode(' ', $argv);
  };
});
