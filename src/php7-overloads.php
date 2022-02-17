<?php

// This could maybe be more clever... but for now, keep it simple...
if (version_compare(PHP_VERSION, '8', '<')) {
  require_once __DIR__ . '/Utils/BasicArrayAccessTrait.php7';
}
