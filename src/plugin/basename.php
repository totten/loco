<?php

namespace Loco;

Loco::dispatcher()->addListener('loco.function.list', function (LocoEvent $e) {
  $e['functions']['basename'] = function ($expr) {
    return basename($expr);
  };
});
