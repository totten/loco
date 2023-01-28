<?php

namespace Loco;

Loco::dispatcher()->addListener('loco.expr.functions', function (LocoEvent $e) {
  $e['functions']['dirname'] = function ($expr) {
    return dirname($expr);
  };
});
