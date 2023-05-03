<?php

namespace Drupal\baywatch\Plugin\monitoring\Support;

use Drupal\monitoring\Result\SensorResultInterface;

interface TideExternalServiceSupportInterface {
  public function runCheck(SensorResultInterface &$result);
}