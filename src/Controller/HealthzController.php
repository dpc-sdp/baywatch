<?php

namespace Drupal\baywatch\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\monitoring\Entity\SensorResultDataInterface;
use Drupal\monitoring\Result\SensorResult;
use Drupal\monitoring\SensorRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Monitoring controller.
 */
class HealthzController extends ControllerBase {

    /**
     * Minimum period to wait between re-checking sensor status.
     */
    protected const MINIMUM_CHECK_INTERVAL = 30;

    /**
     * The sensor runner.
     *
     * @var \Drupal\monitoring\SensorRunner
     */
    protected $sensorRunner;

    /**
     * Constructs a \Drupal\monitoring\Form\SensorDetailForm object.
     *
     * @param \Drupal\monitoring\SensorRunner $sensor_runner
     *   The factory for configuration objects.
     */
    public function __construct(SensorRunner $sensor_runner) {
      $this->sensorRunner = $sensor_runner;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
      return new static(
        $container->get('monitoring.sensor_runner')
      );
    }

    /**
     * API endpoint to get health for the site.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *   A JSON response.
     */
    public function json(): JsonResponse {
      // Results may be cached from sensor runner.
      $results = $this->sensorRunner->runSensors();

      $resultFailures = array_filter($results, function (SensorResult $result): bool {
        return $result->getStatus() === SensorResultDataInterface::STATUS_CRITICAL;
      });

      $response = [];
      if (count($resultFailures) > 0) {
        $response = [
          'failed_sensor_count' => count($resultFailures),
          'failed_sensors' => [],
        ];

        foreach ($resultFailures as $resultFailure) {
          array_push($response['failed_sensors'], [
            'sensor_name' => $resultFailure->getSensorId(),
            'sensor_message' => $resultFailure->getMessage()
          ]);
        }
        $response = new CacheableJsonResponse($response, 500);
      } else {
        $response = new CacheableJsonResponse(['message' => 'All sensors OK!']);
      }

      // 30 second break between sensors runs at a minimum.
      $cache_metadata = new CacheableMetadata();
      $cache_metadata->setCacheMaxAge(static::MINIMUM_CHECK_INTERVAL);
      $response->addCacheableDependency($cache_metadata);

      return $response;
    }
}