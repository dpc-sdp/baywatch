<?php

namespace Drupal\baywatch\Plugin\monitoring\Support;

use Abraham\TwitterOAuth\TwitterOAuth;
use Drupal\monitoring\Result\SensorResultInterface;

class TideExternalServiceSensorTwitterSupport implements TideExternalServiceSupportInterface {

    /**
     * The network manager.
     *
     * @var \Drupal\social_api\Plugin\NetworkManager
     */
    protected $networkManager;

    /**
     * The social post manager.
     *
     * @var \Drupal\social_post\SocialPostManager
     */
    protected $postManager;

    /**
     * Lazy load accessor for the social network manager.
     *
     * @return \Drupal\social_api\Plugin\NetworkManager
     *   The network manager.
     */
    protected function getNetworkManager() {
      if (!$this->networkManager) {
        $this->networkManager = \Drupal::service('plugin.network.manager');
      }
      return $this->networkManager;
    }

    /**
     * Lazy load accessor for the auth provider.
     *
     * @return \Drupal\social_post\SocialPostManager
     *   The post manager.
     */
    protected function getPostManager() {
      if (!$this->postManager) {
        $this->postManager = \Drupal::service('social_post.post_manager');
      }
      return $this->postManager;
    }

    public function runCheck(SensorResultInterface &$result)
    {
      $config = \Drupal::config('social_post_twitter.settings');
      $consumer = $config->get('consumer_key');
      $secret = $config->get('consumer_secret');

      $connection = new TwitterOAuth($consumer, $secret);
      $network_plugin = $this->getNetworkManager()->createInstance('social_post_twitter');

      try {
        // If we can't get a request token then the credentials are incorrect.
        $request_token = $connection->oauth('oauth/request_token', ['oauth_callback' => $network_plugin->getOauthCallback()]);
      } catch(\Exception $e) {
        $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
        $result->setMessage("Invalid credentials.");
        return;
      }

      if (empty($request_token['oauth_callback_confirmed'])) {
        $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
        $result->setMessage("OAuth callback is not confirmed.");
        return;
      }

      $result->setStatus(SensorResultInterface::STATUS_OK);
    }
}