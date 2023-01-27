<?php

namespace Loco;

Loco::dispatcher()->addListener('loco.function.list', function (LocoEvent $e) {
  $e['functions']['dirname'] = function ($expr) {
    return dirname($expr);
  };
});
