<?php

namespace Drupal\baywatch\Access;

use Drupal\Core\Site\Settings;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Check if a token is present in GET args.
 */
class HealthzAccessCheck implements AccessInterface {

    /**
     * Name of a GET param to get the key.
     */
    protected const TOKEN_HEADER_NAME = 'Healthz-Token';

    /**
     * Determine access for the healthz endpoint.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming HTTP request object.
     *
     * @return \Drupal\Core\Access\AccessResult
     *   An access result.
     */
    public function access(Request $request): AccessResult {
      $expected_key = Settings::get('baywatch.healthz_key');
      if (empty($expected_key)) {
        throw new \Exception('baywatch.healthz_key is not set!');
      }
      $user_key = $request->headers->get(static::TOKEN_HEADER_NAME);
      // Substring caps the value length.
      return AccessResult::allowedIf((is_string($user_key) && substr($user_key, 0, 32) == $expected_key))
        ->addCacheContexts(['healthz_header_token']);
    }
}