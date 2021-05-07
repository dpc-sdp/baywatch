<?php

namespace Drupal\baywatch;

use Drupal\Core\Serialization\Yaml;
use Drupal\Core\File\FileSystemInterface;


trait BaywatchOperationTrait
{

  public function remove_purge_lateruntime()
  {
    \Drupal::service('module_installer')->uninstall(['purge_processor_lateruntime']);
  }

  public function enable_preview()
  {
    $private = 'private://';
    \Drupal::service('file_system')->prepareDirectory($private, FileSystemInterface::CREATE_DIRECTORY);

    // Check if tide_oauth is both installed and enabled.
    if (\Drupal::moduleHandler()->moduleExists('tide_oauth') === FALSE) {
      // If not, install the tide_oauth module.
      \Drupal::service('module_installer')->install(['tide_oauth']);
    }

    // Check if tide_site_preview is both installed and enabled.
    if (\Drupal::moduleHandler()->moduleExists('tide_site_preview') === FALSE) {
      // If not, install the tide_site_preview module.
      \Drupal::service('module_installer')->install(['tide_site_preview']);
    }

    $consumers = \Drupal::entityTypeManager()->getStorage('consumer')
      ->loadByProperties([
        'machine_name' => 'editorial_preview',
        'is_default' => FALSE,
      ]);
    if ($consumers) {
      /** @var \Drupal\consumers\Entity\Consumer $consumer */
      $consumer = reset($consumers);
      $consumer->set('uuid', 'dc881486-c14a-4b92-a0d0-e5dcd706f5ad')->save();
    }
  }

  public function enable_share_links()
  {
    // Check if tide_share_link is both installed and enabled.
    if (\Drupal::moduleHandler()->moduleExists('tide_share_link') === FALSE) {
      // If not, install the tide_share_link module.
      \Drupal::service('module_installer')->install(['tide_share_link']);
    }
    // Update shield config to exclude oauth path.
    if (\Drupal::moduleHandler()->moduleExists('shield') === TRUE) {
      $shield_settings = \Drupal::configFactory()->getEditable('shield.settings');
      $path = "/oauth\r\n/oauth/authorize\r\n/oauth/token";
      $shield_settings->set('domains', '');
      $shield_settings->set('method', 0);
      $shield_settings->set('paths', $path);
      $shield_settings->save();
    }
    // Add new permission to previewer role.
    $permissions = ['bypass site restriction'];
    user_role_grant_permissions('previewer', $permissions);
  }

  public function enable_config_split()
  {
    $configs = [
      'config_split.config_split.ci' => 'config_split',
      'config_split.config_split.dev' => 'config_split',
      'config_split.config_split.local' => 'config_split',
    ];
    module_load_include('inc', 'tide_core', 'includes/helpers');
    $config_location = [drupal_get_path('module', 'baywatch') . '/config/optional'];
    // Check if field already exported to config/sync.
    foreach ($configs as $config => $type) {
      $config_read = _tide_read_config($config, $config_location, TRUE);
      $storage = \Drupal::entityTypeManager()->getStorage($type);
      $id = substr($config, strrpos($config, '.') + 1);
      if ($storage->load($id) == NULL) {
        $config_entity = $storage->createFromStorageRecord($config_read);
        $config_entity->save();
      } else {
        $config_file = DRUPAL_ROOT . '/' . drupal_get_path('module', 'baywatch') . '/config/optional/' . $config . '.yml';
        $inactive_config_values = Yaml::decode(file_get_contents($config_file));
        $inactive_graylist = $inactive_config_values['graylist'];
        $active_config = \Drupal::configFactory()->getEditable($config);
        $active_graylist = $active_config->get('graylist');
        if ($config == 'config_split.config_split.dev') {
          if (in_array('clamav.settings', $active_graylist)) {
            if (($key = array_search('clamav.settings', $active_graylist)) !== FALSE) {
              unset($active_graylist[$key]);
            }
          }
        }
        $result = array_unique(array_merge($active_graylist, $inactive_graylist));
        $active_config->set('graylist', $result)->save();
      }
    }
  }

  public function enable_queue_mail()
  {
    // Check if queue_mail is both installed and enabled.
    if (\Drupal::moduleHandler()->moduleExists('queue_mail') === FALSE) {
      // If not, install the queue_mail module.
      \Drupal::service('module_installer')->install(['queue_mail']);
    }
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('queue_mail.settings');
    $config->set('queue_mail_keys', '*');
    $config->save();
  }

  public function import_authenticated_content_key()
  {
    $configs = [
      'key.key.authenticated_content' => 'key',
    ];
    module_load_include('inc', 'tide_core', 'includes/helpers');
    $config_location = [drupal_get_path('module', 'baywatch') . '/config/optional'];
    // Check if field already exported to config/sync.
    foreach ($configs as $config => $type) {
      $config_read = _tide_read_config($config, $config_location, TRUE);
      $storage = \Drupal::entityTypeManager()->getStorage($type);
      $id = substr($config, strrpos($config, '.') + 1);
      if ($storage->load($id) == NULL) {
        $config_entity = $storage->createFromStorageRecord($config_read);
        $config_entity->save();
      }
    }
  }

  public function cleanup_tables()
  {
    $tables = [];
    $r = \Drupal::database()->query("SHOW TABLES LIKE 'old_%'");
    $rows = $r->fetchCol();
    if (!empty($rows)) {
      foreach ($rows as $row) {
        $tables[] = $row;
      }
    }

    foreach ($tables as $table) {
      try {
        \Drupal::messenger()->addMessage("Dropping obsolete table ${table}");
        \Drupal::database()->query(sprintf("DROP TABLE %s;", $table));
      } catch (\Exception $e) {
        \Drupal::messenger()->addMessage("Error when dropping table ${table}");
        \Drupal::logger('baywatch')->error($e->getMessage());
      }
    }
  }

  public function import_sdpa_password_policy()
  {
    $module_installer = \Drupal::service('module_installer');
    $module_handler = \Drupal::moduleHandler();
    // Enables required password_policy module.
    if (!$module_handler->moduleExists('password_policy')) {
      $module_installer->install(['password_policy']);
    }
    // Enables required sub modules.
    if (!$module_handler->moduleExists('password_policy_character_types')) {
      $module_installer->install(['password_policy_character_types']);
    }
    if (!$module_handler->moduleExists('password_policy_characters')) {
      $module_installer->install(['password_policy_characters']);
    }
    if (!$module_handler->moduleExists('password_policy_consecutive')) {
      $module_installer->install(['password_policy_consecutive']);
    }
    if (!$module_handler->moduleExists('password_policy_history')) {
      $module_installer->install(['password_policy_history']);
    }
    if (!$module_handler->moduleExists('password_policy_length')) {
      $module_installer->install(['password_policy_length']);
    }
    if (!$module_handler->moduleExists('password_policy_username')) {
      $module_installer->install(['password_policy_username']);
    }
    if (!$module_handler->moduleExists('password_strength')) {
      $module_installer->install(['password_strength']);
    }
    // Remove default password policy if exists.
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('password_policy.password_policy.default');
    $default_id = $config->get('id');
    if (!empty($default_id)) {
      $config->delete();
      echo "Password policy with id " . $default_id . " has been deleted.\n";
    }
    $configs = [
      'password_policy.password_policy.password_policy_sdpa' => 'password_policy',
    ];
    module_load_include('inc', 'tide_core', 'includes/helpers');
    $config_location = [drupal_get_path('module', 'baywatch') . '/config/optional'];
    foreach ($configs as $config => $type) {
      $config_read = _tide_read_config($config, $config_location, TRUE);
      $storage = \Drupal::entityTypeManager()->getStorage($type);
      $id = substr($config, strrpos($config, '.') + 1);
      if ($storage->load($id) == NULL) {
        $config_entity = $storage->createFromStorageRecord($config_read);
        $config_entity->save();
      }
    }
  }

  public function remove_previewer_role()
  {
    $results = \Drupal::entityQuery('user')
      ->condition('roles', 'previewer')
      ->execute();
    if (!empty($results)) {
      $users = \Drupal::entityTypeManager()->getStorage('user')
        ->loadMultiple($results);

      foreach ($users as $user) {
        $user->removeRole('previewer');
        $user->save();
      }
    }
  }
}
