<?php

/**
 * Plugin Name: LaunchKit Pro
 * Plugin URI:  https://wplaunchify.com
 * Short Description: LaunchKit makes it possible for anyone to get up and running with a fully functional WordPress business site in just a few minutes.
 * Description: Everything you need to Launch, Grow, Market & Monetize with WordPress
 * Version:     2.10.0
 * Author:      1WD LLC
 * Text Domain: wplk
 * Tested up to: 6.6.2
 * Update URI:  https://github.com/wplaunchify/launchkit-pro
 * License:     GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

// Include the autoloader
require_once 'autoloader.php';

// Instantiate the Plugin class without any namespace
new Plugin(__FILE__);

/**
 * LaunchKit Class
 *
 * @since 1.0.0
 */
class LaunchKit {

    const VERSION = '2.10.0';
    const MINIMUM_PHP_VERSION = '7.4';

    public function __construct() {
        // Hook to check and delete original plugin upon activation
        register_activation_hook(__FILE__, array($this, 'schedule_deletion_of_original_plugin'));

        // Other hooks and actions
        add_action('admin_init', array($this, 'save_plugin_settings') );
        add_action('init', array( $this, 'wplk' ));
        add_action('init', array($this, 'setup_constants' ));
        add_action('plugins_loaded', array( $this, 'init' ));
        add_action('plugins_loaded', array($this, 'includes' ));
        add_action('admin_menu', array( $this, 'wplk_add_admin_menu' ));
        add_action('admin_init', array ($this , 'wplk_settings_init' ));
        add_action('admin_enqueue_scripts', array( $this, 'wplk_add_script_to_menu_page' ) );
        add_action('init', array( $this, 'wplk_apply_settings' ) );
        add_action('wp_enqueue_scripts', array($this, 'wplk_add_public_style' ), 999);
        add_action('admin_footer', array($this, 'add_select_all_script'));
        
        // Hook for delayed deletion
        add_action('launchkit_delete_original_plugin', array($this, 'delete_original_plugin'));
    }

    /**
     * Schedule the deletion of the original plugin
     */
    public function schedule_deletion_of_original_plugin() {
        $original_plugin_slug = 'launchkit/launchkit.php';

        // Deactivate the original plugin
        if (is_plugin_active($original_plugin_slug)) {
            deactivate_plugins($original_plugin_slug);
        }

        // Schedule deletion to run after a short delay
        if (!wp_next_scheduled('launchkit_delete_original_plugin')) {
            wp_schedule_single_event(time() + 5, 'launchkit_delete_original_plugin');
        }
    }

    /**
     * Delete the original plugin after deactivation
     */
    public function delete_original_plugin() {
        $plugin_path = WP_PLUGIN_DIR . '/launchkit';

        if (is_dir($plugin_path)) {
            $this->delete_plugin_directory($plugin_path);
        }
    }

    /**
     * Utility function to delete a plugin directory
     */
    private function delete_plugin_directory($plugin_path) {
        if (!class_exists('WP_Filesystem_Base')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        // Delete plugin directory recursively
        $wp_filesystem->delete($plugin_path, true);
    }

    // The rest of your plugin code remains the same...
    // (Add all other functions from the original Pro plugin file here)
}

// Instantiate LaunchKit Class
new LaunchKit();
