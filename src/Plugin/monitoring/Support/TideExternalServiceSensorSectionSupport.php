<?php

namespace Drupal\baywatch\Plugin\monitoring\Support;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\section_purger\Entity\SectionPurgerSettings;
use GuzzleHttp\ClientInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\monitoring\Result\SensorResultInterface;

class TideExternalServiceSensorSectionSupport implements TideExternalServiceSupportInterface, ContainerInjectionInterface {

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

    public function __construct(ClientInterface $http_client, KeyRepositoryInterface $keyRepository) {
      $this->client = $http_client;
      $this->keyRepository = $keyRepository;
    }

    /**
     * @inheritDoc
     */
    public static function create(ContainerInterface $container)
    {
      return new static(
        $container->get('http_client'),
        $container->get('key.repository')
      );
    }

    public function runCheck(SensorResultInterface &$result)
    {
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


}