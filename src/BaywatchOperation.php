<?php

namespace Drupal\baywatch;

use Drupal\Core\Serialization\Yaml;
use Drupal\Core\File\FileSystemInterface;
use Drupal\user\Entity\Role;


class BaywatchOperation
{

  /**
   * Helper to install a module.
   *
   * @param string $module
   *   Module name.
   *
   * @throws \Exception
   *   When module already installed.
   */
  public function baywatch_install_module($module) {
    /** @var \Drupal\Core\Extension\ModuleHandler $moduleHandler */
    $moduleExists = \Drupal::service('module_handler')->moduleExists($module);
    // Check if module is both installed and enabled.
    if (!$moduleExists) {
      // If not, install the queue_mail module.
      \Drupal::service('module_installer')->install([$module]);
    }
  }

  public function remove_purge_lateruntime() {
    \Drupal::service('module_installer')->uninstall(['purge_processor_lateruntime']);
  }

  public function enable_preview() {
    $private = 'private://';
    \Drupal::service('file_system')->prepareDirectory($private, FileSystemInterface::CREATE_DIRECTORY);

    // Install tide_oauth if not installed.
    $this->baywatch_install_module('tide_oauth');

    // Install tide_site_preview if not installed.
    $this->baywatch_install_module('tide_site_preview');

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

  public function enable_share_links() {
    // Install tide_share_link if not installed.
    $this->baywatch_install_module('tide_share_link');

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

  public function enable_config_split() {
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

  public function enable_queue_mail() {
    // Install queue_mail if not installed.
    $this->baywatch_install_module('queue_mail');

    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('queue_mail.settings');
    $config->set('queue_mail_keys', '*');
    $config->save();
  }

  public function import_authenticated_content_key() {
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

  public function cleanup_tables() {
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

  public function import_sdpa_password_policy() {
    // Enables required password_policy module.
    $this->baywatch_install_module('password_policy');
    // Enables required sub modules.
    $this->baywatch_install_module('password_policy_character_types');
    $this->baywatch_install_module('password_policy_characters');
    $this->baywatch_install_module('password_policy_consecutive');
    $this->baywatch_install_module('password_policy_history');
    $this->baywatch_install_module('password_policy_length');
    $this->baywatch_install_module('password_policy_username');
    $this->baywatch_install_module('password_strength');
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

  public function remove_previewer_role() {
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

  public function remove_authenticated_content() {
    $module_handler = \Drupal::moduleHandler();
    $authenticated_module_exist = $module_handler->moduleExists('tide_authenticated_content');
    // Load the site name out of configuration.
    $config = \Drupal::config('system.site');
    $site_name = $config->get('name');
    if (($site_name !== 'Victoria Police' || $site_name !== 'Shared Service Provider Content Repository') && $authenticated_module_exist) {
      \Drupal::service('module_installer')->uninstall(['tide_authenticated_content']);
    }
  }

  public function enable_tide_edit_protection() {
    $administrator = Role::load('administrator');
    $anonymous = Role::load('anonymous');
    if ($administrator && $anonymous) {
      // Enable tide_edit_protection module.
      $this->baywatch_install_module('tide_edit_protection');
    }
  }

  public function enable_tide_dashboard() {
    // Enable tide_dashboard module.
    $this->baywatch_install_module('tide_dashboard');
  }

  public function enable_tide_paragraphs_enhanced_modal() {
    // Enable tide_paragraphs_enhanced_modal module.
    $this->baywatch_install_module('tide_paragraphs_enhanced_modal');
  }
}
