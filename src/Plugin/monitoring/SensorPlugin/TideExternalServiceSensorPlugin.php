<?php

namespace Drupal\baywatch\Plugin\monitoring\SensorPlugin;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\SensorPluginBase;
use Drupal\monitoring\SensorPlugin\SensorPluginInterface;
use Drupal\monitoring\Entity\SensorConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\baywatch\Plugin\monitoring\Support\TideExternalServiceSensorSectionSupport;
use Drupal\baywatch\Plugin\monitoring\Support\TideExternalServiceSensorMailgunSupport;
use Drupal\baywatch\Plugin\monitoring\Support\TideExternalServiceSensorTwitterSupport;
use Drupal\baywatch\Plugin\monitoring\Support\TideExternalServiceSensorVuelioSupport;

/**
 * Custom sensor plugin.
 *
 * @SensorPlugin(
 *   id = "tide_external_service",
 *   label = @Translation("My Custom Sensor"),
 *   description = @Translation("Description of your custom sensor."),
 *   provider = "baywatch",
 * )
 */
class TideExternalServiceSensorPlugin extends SensorPluginBase implements SensorPluginInterface {

    /**
     * {@inheritdoc}
     */
    public function __construct(SensorConfig $sensor_config, $plugin_id, $plugin_definition, ClientInterface $http_client, KeyRepositoryInterface $key_repository, ConfigFactoryInterface $config_factory) {
      parent::__construct($sensor_config, $plugin_id, $plugin_definition);
      $this->client = $http_client;
      $this->keyRepository = $key_repository;
      $this->config = $config_factory->get(Constants::SETTINGS);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, SensorConfig $sensor_config, $plugin_id, $plugin_definition) {
      return new static(
        $sensor_config,
        $plugin_id,
        $plugin_definition,
        $container->get('http_client'),
        $container->get('key.repository'),
        $container->get('config.factory')
      );
    }

    public function runSensor(SensorResultInterface $result)
    {
        $check_type = $this->sensorConfig->getSetting('check_type');
        switch ($check_type) {
          case 'section':
            $this->checkSection($result);
            break;
          case 'mailgun':
            $this->checkMailgun($result);
            break;
          case 'twitter':
            $this->checkTwitter($result);
            break;
          case 'vuelio':
            $this->checkVuelio($result);
            break;
        }
    }


    protected function checkSection(SensorResultInterface &$result) {
      // Just in case the required modules have become uninstalled.
      $required_modules_classes_exist = \Drupal::moduleHandler()->moduleExists('section_purger') && \Drupal::moduleHandler()->moduleExists('key');

      if ($required_modules_classes_exist) {
        $section_sensor = TideExternalServiceSensorSectionSupport::create(\Drupal::getContainer());
        $section_sensor->runCheck($result);
      } else {
        $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
        $result->setMessage('section_purger or key module has gone missing!');
      }
    }

    protected function checkMailgun(SensorResultInterface &$result) {

      $required_modules_classes_exist = \Drupal::moduleHandler()->moduleExists('tide_mailgun') && class_exists('\Mailgun\Mailgun');

      if ($required_modules_classes_exist) {
        $mailgun_sensor = TideExternalServiceSensorMailgunSupport::create(\Drupal::getContainer());
        $mailgun_sensor->runCheck($result);
      } else {
        $result->setValue(SensorResultInterface::STATUS_CRITICAL);
        $result->addStatusMessage('tide_mailgun module or mailgun-php library has gone missing!');
      }

    }

    protected function checkTwitter(SensorResultInterface &$result) {

      $required_modules_classes_exist = \Drupal::moduleHandler()->moduleExists('social_api') && \Drupal::moduleHandler()->moduleExists('social_post') && class_exists('\Abraham\TwitterOAuth\TwitterOAuth');

      if ($required_modules_classes_exist) {
        $twitter_sensor = new TideExternalServiceSensorTwitterSupport();
        $twitter_sensor->runCheck($result);
      } else {
        $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
        $result->setMessage('social_api module, social_post module, or Twitter OAuth library has gone missing!');
      }

    }

    protected function checkVuelio(SensorResultInterface &$result) {

      $required_modules_classes_exist = \Drupal::moduleHandler()->moduleExists('vicpol_vuelio');

      if ($required_modules_classes_exist) {
        $vuelio_sensor = TideExternalServiceSensorVuelioSupport::create(\Drupal::getContainer());
        $vuelio_sensor->runCheck($result);
      } else {
        $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
        $result->setMessage('vicpol_vuelio module has gone missing!');
      }

    }
}