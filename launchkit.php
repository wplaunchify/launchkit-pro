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

class LaunchKitPro {

    const VERSION = '2.10.0';
    const MINIMUM_PHP_VERSION = '7.4';
    private $original_plugin_slug = 'launchkit/launchkit.php';

    public function __construct() {
        add_action('plugins_loaded', array($this, 'deactivate_original_plugin'), 0);
        add_action('plugins_loaded', array($this, 'initialize_pro_plugin'), 1);
        add_action('launchkit_delete_original_plugin', array($this, 'delete_original_plugin'));
    }

    public function deactivate_original_plugin() {
        if (is_plugin_active($this->original_plugin_slug)) {
            deactivate_plugins($this->original_plugin_slug);
        }
    }

    public function initialize_pro_plugin() {
        require_once 'autoloader.php';
        new Plugin(__FILE__);

        register_activation_hook(__FILE__, array($this, 'schedule_deletion_of_original_plugin'));

        add_action('admin_init', array($this, 'save_plugin_settings'));
        add_action('init', array($this, 'wplk'));
        add_action('init', array($this, 'setup_constants'));
        add_action('plugins_loaded', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'includes'));
        add_action('admin_menu', array($this, 'wplk_add_admin_menu'));
        add_action('admin_init', array($this, 'wplk_settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'wplk_add_script_to_menu_page'));
        add_action('init', array($this, 'wplk_apply_settings'));
        add_action('wp_enqueue_scripts', array($this, 'wplk_add_public_style'), 999);
        add_action('admin_footer', array($this, 'add_select_all_script'));
    }

    public function schedule_deletion_of_original_plugin() {
        if (!wp_next_scheduled('launchkit_delete_original_plugin')) {
            wp_schedule_single_event(time() + 5, 'launchkit_delete_original_plugin');
        }
    }

    public function delete_original_plugin() {
        $plugin_path = WP_PLUGIN_DIR . '/launchkit';

        if (is_dir($plugin_path)) {
            $this->delete_plugin_directory($plugin_path);
        }
    }

    private function delete_plugin_directory($plugin_path) {
        if (!class_exists('WP_Filesystem_Base')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        $wp_filesystem->delete($plugin_path, true);
    }

    public function wplk() {
        load_plugin_textdomain('wplk');
    }

    public function init() {
        require_once('includes/class-wplk-deleter.php');
        require_once('includes/class-wplk-functions-launchkit.php');
        require_once('includes/class-wplk-installer.php');
        require_once('includes/class-wplk-license-loader.php');
        require_once('includes/class-wplk-manager.php');
    }

    public function setup_constants() {
        if (!defined('VERSION')) {
            $plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
            define('VERSION', $plugin_data['Version']);
        }
        if (!defined('WPLK_DIR_PATH')) define('WPLK_DIR_PATH', plugin_dir_path(__FILE__));
        if (!defined('WPLK_PLUGIN_PATH')) define('WPLK_PLUGIN_PATH', plugin_basename(__FILE__));
        if (!defined('WPLK_DIR_URL')) define('WPLK_DIR_URL', plugin_dir_url(__FILE__));
    }

    public function includes() {
        // Placeholder for future use
    }

    public function wplk_add_admin_menu() {
        $capability = 'manage_options';

        add_menu_page(
            'LaunchKit',
            'LaunchKit',
            $capability,
            'wplk',
            array($this, 'wplk_options_page'),
            'dashicons-rest-api'
        );

        add_action('admin_head', array($this, 'hide_wplk_admin_submenu_item'));
    }

    public function hide_wplk_admin_submenu_item() {
        echo '<style>#toplevel_page_wplk ul.wp-submenu.wp-submenu-wrap, #adminmenu .wp-submenu a[href="admin.php?page=wplk"] { display: none; }</style>';
    }

    public function wplk_add_script_to_menu_page() {
        $screen = get_current_screen();
        if (is_object($screen) && $screen->id == 'toplevel_page_wplk') {
            wp_register_style('wplk-admin', WPLK_DIR_URL . 'assets/css/wplk-admin.css', false, '1.0');
            wp_enqueue_style('wplk-admin');
        }
        wp_register_style('wplk-wp-admin', WPLK_DIR_URL . 'assets/css/wplk-wp-admin.css', false, '1.0');
        wp_enqueue_style('wplk-wp-admin');
    }

    public function wplk_apply_settings() {
        $options = get_option('wplk_settings');
        if (isset($options['wplk_checkbox_field_000']) && $options['wplk_checkbox_field_000'] == '1') {
            add_filter('woocommerce_prevent_automatic_wizard_redirect', '__return_true');
        }
    }

    public function add_select_all_script() {
        $screen = get_current_screen();
        if (is_object($screen) && $screen->id == 'toplevel_page_wplk' && (!isset($_GET['tab']) || $_GET['tab'] === 'settings')) {
            echo '<script type="text/javascript">jQuery(document).ready(function($) {
                $(".wplk-settings-container .form-table tbody").prepend(
                    "<tr><th scope=\"row\">Select/Deselect All Options</th><td><input type=\"checkbox\" id=\"select-all-checkboxes\"></td></tr>"
                );
                var $checkboxes = $("input[name^=\'wplk_settings[wplk_checkbox_field_\']");
                $("#select-all-checkboxes").change(function() {
                    $checkboxes.prop("checked", $(this).prop("checked")).trigger("change");
                });
                $checkboxes.change(function() {
                    $("#select-all-checkboxes").prop("checked", $checkboxes.length === $checkboxes.filter(":checked").length);
                });
            });</script>';
        }
    }

    public function save_plugin_settings() {
        if (isset($_POST['wplk_settings']) && is_array($_POST['wplk_settings'])) {
            $options = get_option('wplk_settings', array());
            foreach ($_POST['wplk_settings'] as $key => $value) {
                if (is_string($key) && strpos($key, 'wplk_checkbox_field_') === 0) {
                    $options[$key] = ($value === '1') ? '1' : '0';
                }
            }
            update_option('wplk_settings', $options);
        }
    }

    public function wplk_options_page() {
        echo '<div id="wplk-page"><div class="wplk-dashboard__header">
                <a href="https://wplaunchify.com/#pricing" target="_blank"><img class="wplk-dashboard__logo" src="'.WPLK_DIR_URL.'assets/images/launchkit-logo.svg"></a>
                <div class="wplk-dashboard__version">v'.VERSION.'</div></div>
                <nav class="nav-tab-wrapper">
                <a href="?page=wplk&tab=settings" class="nav-tab nav-tab-active">Get Started</a></nav>
                <div class="wplk-wrap">
                <div class="tab-content"><div class="wplk-dashboard__content">
                <div class="wplk-inner"><h1>Get Started</h1>
                <form id="launchkit" action="options.php" method="post">'.settings_fields('wplk_options_page');
                echo '<div class="wplk-settings-container">'.do_settings_sections('wplk_options_page').'</div>';
                submit_button();
                echo '</form></div></div></div></div></div>';
    }
}

new LaunchKitPro();
