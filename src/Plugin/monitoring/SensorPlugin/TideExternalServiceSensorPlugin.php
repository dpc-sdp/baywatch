<?php

namespace Drupal\baywatch\Plugin\monitoring\SensorPlugin;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\SensorPluginBase;
use Drupal\monitoring\SensorPlugin\SensorPluginInterface;
use Drupal\section_purger\Entity\SectionPurgerSettings;
use GuzzleHttp\ClientInterface;
use Drupal\monitoring\Entity\SensorConfig;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
     * The client interface.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $client;

    /**
     * The key repository.
     *
     * @var \Drupal\key\KeyRepository
     */
    protected $keyRepository;

    /**
     * {@inheritdoc}
     */
    public function __construct(SensorConfig $sensor_config, $plugin_id, $plugin_definition, ClientInterface $http_client, KeyRepositoryInterface $key_repository) {
      parent::__construct($sensor_config, $plugin_id, $plugin_definition);
      $this->client = $http_client;
      $this->keyRepository = $key_repository;
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
        $container->get('key.repository')
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
            break;
        }
    }


    protected function checkSection(SensorResultInterface &$result) {
      $purgers = SectionPurgerSettings::loadMultiple();
      $purger = reset($purgers);

      if (empty($purger)) {
        $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
        $result->setMessage('Section purger is not configured.');
        return;
      }

      $uri = sprintf(
        '%s://%s:%s/api/v1/account/%s/application/%s/environment/%s',
        $purger->scheme,
        $purger->hostname,
        $purger->port,
        $purger->account,
        $purger->application,
        $purger->environmentname
      );

      $opt = array(
        'auth' => [$purger->username, $this->keyRepository->getKey($purger->password)->getKeyValue()],
        'connect_timeout' => $purger->connect_timeout,
        'timeout' => $purger->timeout,
      );

      // Sensor interface catches raised exceptions - Guzzle will throw
      // an exception when HTTP >= 400.
      $this->client->get($uri, $opt);

      $result->setValue(0);
      $result->addStatusMessage('Section OK!');
    }

    protected function checkMailgun(SensorResultInterface &$result) {
      $result->setValue(1);
      $result->addStatusMessage('Not implemented!');
    }

    protected function checkTwitter(SensorResultInterface &$result) {
      $result->setValue(1);
      $result->addStatusMessage('Not implemented!');
    }

    protected function checkVuelio(SensorResultInterface &$result) {
      $result->setValue(1);
      $result->addStatusMessage('Not implemented!');
    }
}