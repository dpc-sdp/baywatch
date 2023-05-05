<?php

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\RequestStackCacheContextBase;

class HealthzHeaderTokenCacheContext extends RequestStackCacheContextBase {
    /**
     * Name of a GET param to get the key.
     */
    protected const TOKEN_HEADER_NAME = 'Healthz-Token';

    public function getContext() {
      return $this->requestStack->getCurrentRequest()->headers->get(static::TOKEN_HEADER_NAME);
    }

    public function getCacheableMetadata() {
      return new CacheableMetadata();
    }
}