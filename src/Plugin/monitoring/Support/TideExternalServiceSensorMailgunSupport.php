<?php

namespace Drupal\baywatch\Plugin\monitoring\Support;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\tide_mailgun\Constants;
use Drupal\tide_mailgun\Utils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Mailgun\Mailgun;

class TideExternalServiceSensorMailgunSupport implements TideExternalServiceSupportInterface, ContainerInjectionInterface {
    /**
     * The config.
     *
     * @var Drupal\Core\Config\ConfigFactoryInterface
     */
    protected $config;

    public function __construct(ConfigFactoryInterface $config_factory) {
      $this->config = $config_factory->get(Constants::SETTINGS);
    }
    /**
     * @inheritDoc
     */
    public static function create(ContainerInterface $container) {
      return new static(
        $container->get('config.factory')
      );
    }

    public function runCheck(SensorResultInterface &$result) {
      $private_key = $this->config->get(Utils::prefix('private_key'));
      $mg = Mailgun::create($private_key);

      try {
        $domains = $mg->domains()->index();
      } catch (\Exception $error) {
        $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
        $result->setMessage("Invalid credentials.");
        $result->setValue(FALSE);
        return;
      }

      if ($domains->getTotalCount() < 1) {
        $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
        $result->setMessage("No available domains.");
        $result->setValue(FALSE);
        return;
      }

      $result->setStatus('Mailgun OK!');
      $result->setValue(TRUE);
    }

}