<?php

namespace Loco;

Loco::dispatcher()->addListener('loco.expr.functions', function (LocoEvent $e) {
  $e['functions']['basename'] = function ($expr) {
    return basename($expr);
  };
});
