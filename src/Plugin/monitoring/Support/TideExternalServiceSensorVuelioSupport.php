<?php

namespace Drupal\baywatch\Plugin\monitoring\Support;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\monitoring\Result\SensorResultInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

class TideExternalServiceSensorVuelioSupport implements TideExternalServiceSupportInterface, ContainerInjectionInterface {

    /**
     * @var
     */
    protected $vuelio;

    /**
     *
     */
    protected $module_handler;

    public function __construct(ModuleHandlerInterface $module_handler) {
      $this->module_handler = $module_handler;
    }

    /**
     * @inheritDoc
     */
    public static function create(ContainerInterface $container)
    {
      return new static(
        $container->get('module_handler')
      );
    }

    /**
     * Lazy load the vuelio integration.
     *
     * @return \Drupal\vicpol_vuelio\VuelioServicesInterface
     *   The vuelio service.
     */
    protected function getVuelioService() {
      return \Drupal::service('vicpol_vuelio.vuelio_services');
    }

    public function runCheck(SensorResultInterface &$result){
      try {
        if (!$this->getVuelioService()->vuelioFetchXmlData(TRUE)) {
          $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
          $result->setMessage('Unable to connect to API.');
        }
      } catch (\Error $e) {
        $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
        $result->setMessage('Unhandled runtime error caused by vicpol_vuelio!');
        return;
      }


      $result->setStatus(SensorResultInterface::STATUS_OK);
      $result->setMessage('OK');
    }
}