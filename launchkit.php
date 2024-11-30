<?php

/**
 * Plugin Name: LaunchKit Pro
 * Plugin URI:  https://wplaunchify.com
 * Short Description: LaunchKit makes it possible for anyone to get up and running with a fully functional WordPress business site in just a few minutes.
 * Description: Everything you need to Launch, Grow, Market & Monetize with WordPress
 * Version:     2.11.1
 * Author:      1WD LLC
 * Text Domain: wplk
 * Tested up to: 6.7.1
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

    /**
     * Plugin Version
     *
     * @since 1.0.0
     * @var string The plugin version.
     */
    const VERSION = '2.11.1';

    /**
     * Minimum PHP Version
     *
     * @since 1.0.0
     * @var string Minimum PHP version required to run the plugin.
     */
    const MINIMUM_PHP_VERSION = '7.4';

    /**
     * Constructor
     *
     * @since 1.0.0
     * @access public
     */
    public function __construct() {
        // Hook to check and delete original plugin upon activation
        register_activation_hook(__FILE__, array($this, 'check_and_delete_original_plugin'));

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
    }

    /**
     * Check for original plugin and delete it if found
     */
    public function check_and_delete_original_plugin() {
        $original_plugin_slug = 'launchkit/launchkit.php';

        // Check if the original plugin is active
        if (is_plugin_active($original_plugin_slug)) {
            // Deactivate the original plugin
            deactivate_plugins($original_plugin_slug);
        }

        // Get the full path to the plugin to delete
        $plugin_path = WP_PLUGIN_DIR . '/' . dirname($original_plugin_slug);

        // Check if the plugin directory exists and delete it
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

    /**
     * Add Select All JavaScript
     */
    public function add_select_all_script() {
    $screen = get_current_screen();
    if (is_object($screen) && $screen->id == 'toplevel_page_wplk' && (!isset($_GET['tab']) || $_GET['tab'] === 'settings')) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Create a new table row at the start of the form table with matching style
            $('.wplk-settings-container .form-table tbody').prepend(
                '<tr>' +
                '<th scope="row"><?php esc_html_e('Select/Deselect All Options', 'wplk'); ?></th>' +
                '<td><input type="checkbox" id="select-all-checkboxes"></td>' +
                '</tr>'
            );

            // Initial state check
            var $checkboxes = $('input[name^="wplk_settings[wplk_checkbox_field_"]');
            var allChecked = $checkboxes.length === $checkboxes.filter(':checked').length;
            $('#select-all-checkboxes').prop('checked', allChecked);

            // Handle select all functionality
            $('#select-all-checkboxes').change(function() {
                var isChecked = $(this).prop('checked');
                $checkboxes.prop('checked', isChecked).trigger('change');
            });

            // Update select all checkbox state based on individual checkboxes
            $checkboxes.change(function() {
                var allChecked = $checkboxes.length === $checkboxes.filter(':checked').length;
                $('#select-all-checkboxes').prop('checked', allChecked);
            });
        });
        </script>
        <?php
    }
}

/**
     * Adds WPLaunchKit Page Styles & Scripts
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function wplk_add_public_style() {
        // load public facing css (minified)
        wp_register_style( 'wplk-public', WPLK_DIR_URL . 'assets/css/wplk-public.css', false, '1.0.0' );
        wp_enqueue_style( 'wplk-public' );
    }   

    /**
     * Load Textdomain
     *
     * Load plugin localization files.
     * Fired by `init` action hook.
     *
     * @since 1.0.0
     * @access public
     */
    public function wplk() {
        load_plugin_textdomain( 'wplk' );
    }

    /**
     * Initialize LaunchKit Plugin
     *
     * Fired by `plugins_loaded` action hook.
     *
     * @since 1.0.0
     * @access public
     */
    public function init() {
        require_once( 'includes/class-wplk-deleter.php' );
        require_once( 'includes/class-wplk-functions-launchkit.php');
        require_once( 'includes/class-wplk-installer.php' );
        require_once( 'includes/class-wplk-license-loader.php');
        require_once( 'includes/class-wplk-manager.php');
    }

    /**
     * Setup plugin constants
     *
     * @since 1.0.0
     *
     * @access private
     * @return void
     */
    public function setup_constants() {
        if ( ! defined ( 'VERSION') ) {
            $plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
            $plugin_version = $plugin_data['Version'];
            define ( 'VERSION', $plugin_version );
        }

        if ( ! defined( 'WPLK_DIR_PATH' ) ) {
            define( 'WPLK_DIR_PATH', plugin_dir_path( __FILE__ ) );
        }

        if ( ! defined( 'WPLK_PLUGIN_PATH' ) ) {
            define( 'WPLK_PLUGIN_PATH', plugin_basename( __FILE__ ) );
        }

        if ( ! defined( 'WPLK_DIR_URL' ) ) {
            define( 'WPLK_DIR_URL', plugin_dir_url( __FILE__ ) );
        }
    }

    /**
     * Load testing environment
     *
     * @since 1.0.0
     *
     * @access private
     * @return void
     */
    public function includes() {
        //   require_once 'includes/class-wplk-experimental.php';
    }

    /**
     * Adds Admin Page
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function wplk_add_admin_menu() {
        $parent_slug = 'wplk';
        $capability = 'manage_options';

        add_menu_page(
            'LaunchKit',
            'LaunchKit',
            $capability,
            $parent_slug,
            array($this, 'wplk_options_page'),
            'dashicons-rest-api'
        );

        add_action('admin_head', array($this, 'hide_wplk_admin_submenu_item'));
    }

    public function hide_wplk_admin_submenu_item() {
        $parent_slug = 'wplk';
        echo '<style>';
        echo '#toplevel_page_' . $parent_slug . ' ul.wp-submenu.wp-submenu-wrap, #adminmenu .wp-submenu a[href="admin.php?page=' . $parent_slug . '"] { display: none; }';
        echo '</style>';
    }

    /**
     * Adds Admin Page Styles
     *
     * @since 1.0.4
     *
     * @return void
     */
    public function wplk_add_script_to_menu_page() {
        $screen = get_current_screen();

        if (is_object($screen) && $screen->id == 'toplevel_page_wplk') {
            wp_register_style( 'wplk-admin', WPLK_DIR_URL . 'assets/css/wplk-admin.css', false, '1.0' );
            wp_enqueue_style( 'wplk-admin' );
        }   

        wp_register_style( 'wplk-wp-admin', WPLK_DIR_URL . 'assets/css/wplk-wp-admin.css', false, '1.0' );
        wp_enqueue_style( 'wplk-wp-admin' );
    }
    /**
     * Adds Admin Page Settings
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function wplk_settings_init() { 
        register_setting('wplk_options_page', 'wplk_settings');

        // LaunchKit Base Options
        add_settings_section(
            'wplk_options_section_base',
            esc_html__('', 'wplk'),
            array($this, 'wplk_settings_section_base'),
            'wplk_options_page'
        );

        add_settings_field(
            'wplk_checkbox_field_004',
            esc_html__('Hide LaunchKit from Admin Menu (Whitelabel For Client Sites)', 'wplk') . '<p class="description">' . esc_html__('For Agency Owners With Client sites. Use "page=wplk" in the URL to access it.', 'wplk') . '</p>',
            array($this, 'wplk_checkbox_field_004_render'),
            'wplk_options_page',
            'wplk_options_section_base'
        );

        add_settings_field(
            'wplk_checkbox_field_000',
            esc_html__('Disable All Plugin Activation Wizards', 'wplk') . '<p class="description">' . esc_html__('To ensure that you stay on the plugin manager page when activating plugins.', 'wplk') . '</p>',
            array($this, 'wplk_checkbox_field_000_render'),
            'wplk_options_page',
            'wplk_options_section_base'
        );

        add_settings_field(
            'wplk_checkbox_field_001',
            esc_html__('Hide All Admin Notices', 'wplk') . '<p class="description">' . esc_html__('A clean dashboard is a productive dashboard! Click Notices Hidden to reveal.', 'wplk') . '</p>',
            array($this, 'wplk_checkbox_field_001_render'),
            'wplk_options_page',
            'wplk_options_section_base'
        );

        add_settings_field(
            'wplk_checkbox_field_002',
            esc_html__('Disable LearnDash License Management Plugin', 'wplk') . '<p class="description">' . esc_html__('Removes this unnecessary plugin.', 'wplk') . '</p>',
            array($this, 'wplk_checkbox_field_002_render'),
            'wplk_options_page',
            'wplk_options_section_base'
        );

        add_settings_field(
            'wplk_checkbox_field_003',
            esc_html__('Disable WordPress Plugin Manager Dependencies', 'wplk') . '<p class="description">' . esc_html__('Restores Plugin Manager to pre-6.5 capabilities for total control.', 'wplk') . '</p>',
            array($this, 'wplk_checkbox_field_003_render'),
            'wplk_options_page',
            'wplk_options_section_base'
        );

        add_settings_field(
            'wplk_checkbox_field_005',
            esc_html__('Disable WordPress Sending Update Emails', 'wplk') . '<p class="description">' . esc_html__('For Core, Updates and Themes', 'wplk') . '</p>',
            array($this, 'wplk_checkbox_field_005_render'),
            'wplk_options_page',
            'wplk_options_section_base'
        );
    }

    /**
     * Adds Admin Page Fields
     *
     * @since 4.0.8
     *
     * @return void
     */
    public function save_plugin_settings() {
        if (isset($_POST['wplk_settings']) && is_array($_POST['wplk_settings'])) {
            $options = get_option('wplk_settings', array());
            
            if (!is_array($options)) {
                $options = array();
            }
            
            foreach ($_POST['wplk_settings'] as $key => $value) {
                if (is_string($key) && strpos($key, 'wplk_checkbox_field_') === 0) {
                    $options[$key] = (is_string($value) && $value === '1') ? '1' : '0';
                }
            }
            
            update_option('wplk_settings', $options);
        }
    }

    public function wplk_checkbox_field_000_render() {
        $options = get_option('wplk_settings');
        $checked = isset($options['wplk_checkbox_field_000']) && $options['wplk_checkbox_field_000'] === '1' ? 1 : 0;
        ?>
        <input type='checkbox' name='wplk_settings[wplk_checkbox_field_000]' <?php checked($checked, 1); ?> value='1'>
        <?php
    }

    public function wplk_checkbox_field_001_render() {
        $options = get_option('wplk_settings');
        $checked = isset($options['wplk_checkbox_field_001']) && $options['wplk_checkbox_field_001'] === '1' ? 1 : 0;
        ?>
        <input type='checkbox' name='wplk_settings[wplk_checkbox_field_001]' <?php checked($checked, 1); ?> value='1'>
        <?php
    }

    public function wplk_checkbox_field_002_render() {
        $options = get_option('wplk_settings');
        ?>
        <input type='checkbox' name='wplk_settings[wplk_checkbox_field_002]' <?php checked(isset($options['wplk_checkbox_field_002']), 1); ?> value='1'>
        <?php
    }

    public function wplk_checkbox_field_003_render() {
        $options = get_option('wplk_settings');
        ?>
        <input type='checkbox' name='wplk_settings[wplk_checkbox_field_003]' <?php checked(isset($options['wplk_checkbox_field_003']), 1); ?> value='1'>
        <?php
    }

    public function wplk_checkbox_field_004_render() {
        $options = get_option('wplk_settings');
        ?>
        <input type='checkbox' name='wplk_settings[wplk_checkbox_field_004]' <?php checked(isset($options['wplk_checkbox_field_004']), 1); ?> value='1'>
        <?php
    }

    public function wplk_checkbox_field_005_render() {
        $options = get_option('wplk_settings');
        ?>
        <input type='checkbox' name='wplk_settings[wplk_checkbox_field_005]' <?php checked(isset($options['wplk_checkbox_field_005']), 1); ?> value='1'>
        <?php
    }

    public function wplk_settings_section_base() {
        /* reserved for future use */   
    }
    public function wplk_options_page() {
        ?>
        <div id="wplk-page">
            <!-- Page Header -->
            <div class="wplk-dashboard__header">
                <a href="https://wplaunchify.com/#pricing" target="_blank"><img class="wplk-dashboard__logo" src="<?php echo WPLK_DIR_URL; ?>assets/images/launchkit-logo.svg"></a>
                <div class="wplk-dashboard__version">v<?php echo VERSION; ?></div>
            </div>

            <!-- Page Navigation Tabs -->
            <?php 
            $default_tab = 'settings';
            $tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;
            ?>

            <nav class="nav-tab-wrapper">
                <a href="?page=wplk&tab=settings" class="nav-tab <?php if ($tab === 'settings'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Get Started', 'wplk'); ?></a>
                <a href="?page=wplk&tab=featured" class="nav-tab <?php if ($tab === 'featured'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Features', 'wplk'); ?></a>
                <a href="?page=wplk&tab=installer" class="nav-tab <?php if ($tab === 'installer'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Pro Features', 'wplk'); ?></a>

            </nav>

            <div class="wplk-wrap">
                <div class="tab-content">
                    <?php switch($tab) :
                    case 'installer': ?>
                        <div class="wplk-dashboard__content">
                            <div class="wplk-inner">
                                <?php
                                $installer = new WPLKInstaller();
                                $installer->lk_get_meta_plugin_installer_page();
                                ?>
                            </div>
                        </div>
                    <?php break;
                    case 'deleter': ?>
                        <div class="wplk-dashboard__content">
                            <div class="wplk-inner">
                                <?php
                                $deleter = new WPLKDeleter();
                                $deleter->launchkit_deleter_page();
                                ?>
                            </div>
                        </div>
                    <?php break;
                    case 'manager': ?>
                        <div class="wplk-dashboard__content">
                            <div class="wplk-inner">
                                <?php
                                $manager = new WPLKManager();
                                $manager->launchkit_manager_page();
                                ?>
                            </div>
                        </div>
                    <?php break;
                    case 'license': ?>
                        <div class="wplk-dashboard__content">
                            <div class="wplk-inner">
                                <?php
                                $license = new WPLKLicenseKeyAutoloader();
                                $license->license_key_autoloader_page();
                                ?>
                            </div>
                        </div>
                    <?php break;
                    case 'packages': ?>
                        <div class="wplk-dashboard__content">
                            <div class="wplk-inner">
                                <?php
                                $packages = new WPLKInstaller();
                                $packages->get_prime_page();
                                ?>
                            </div>
                        </div>
                    <?php break;
                    case 'settings': ?>
                        <div class="wplk-dashboard__content">
                            <div class="wplk-inner">
                                <h1><?php esc_html_e('Get Started', 'wplk'); ?></h1>
                                <style>
                                    .wplk-settings-container ul {
                                        list-style: disc;
                                        text-align: left;
                                        max-width: 80%;
                                        margin: 0 auto;
                                    }
                                    .postbox {
                                        margin-bottom: 20px;
                                    }
                                    .postbox .hndle {
                                        margin-top:0;
                                        cursor: pointer;
                                        padding: 10px;
                                        background-color: #f1f1f1;
                                        border: 1px solid #ccc;
                                    }
                                    .postbox .inside {
                                        padding: 10px;
                                        border: 1px solid #ccc;
                                        border-top: none;
                                    }
                                    .postbox.closed .inside {
                                        display: none;
                                    }
                                    .postbox h3.hndle {
                                        text-align: left;
                                        padding-left:15px;
                                    }
                                </style>

                                <form id="launchkit" action='options.php' method='post'>
                                    <?php
                                    settings_fields('wplk_options_page');
                                    echo '<div class="wplk-settings-container">';
                                    do_settings_sections('wplk_options_page');
                                    echo '</div>';
                                    submit_button();
                                    ?>
                                </form>
                            </div>
                        </div>
                    <?php break;
                    case 'featured': ?>
                        <div class="wplk-dashboard__content">
                            <div class="wplk-inner">
                                <h1><?php esc_html_e('Features', 'wplk'); ?></h1>
                                <p>For Launching & Managing Your Site</p>

                                <nav class="nav-tab-wrapper-more">
                                    <a href="?page=wplk&tab=deleter" class="nav-tab <?php if ($tab === 'deleter'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Deleter', 'wplk'); ?></a><span class="nav-description">Delete any or all plugins instantly.</span>
                                    <a href="?page=wplk&tab=manager" class="nav-tab <?php if ($tab === 'manager'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Recipe Manager', 'wplk'); ?></a><span class="nav-description">Create & manage plugin recipes.</span>
									
					<?php $logged_in = get_transient('lk_logged_in'); ?>
					<?php if ($logged_in) : ?>
									?>
									<a href="?page=wplk&tab=account" class="nav-tab <?php if ($tab === 'account'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Account', 'wplk'); ?></a><span class="nav-description">Create your WPLaunchify access account.</span>
                                    <a href="?page=wplk&tab=license" class="nav-tab <?php if ($tab === 'license'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('License', 'wplk'); ?></a><span class="nav-description">Manage software licenses.</span>
                                    <a href="?page=wplk&tab=packages" class="nav-tab <?php if ($tab === 'packages'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Packages', 'wplk'); ?></a><span class="nav-description">Create or import full site templates.</span>
                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    <?php break;
                    case 'account': ?>
                        <div class="wplk-dashboard__content">
                            <div class="wplk-inner">
                                <h1><?php esc_html_e('Account', 'wplk'); ?></h1>
                                <br/>
                                <div class="wplk-settings-container">
                                    <span class="dashicons dashicons-update"></span>
                                    <h2>Get Your Pro Account</h2>
                                    <p> Available with concierge support from WPLaunchify.</p>
                                    <p><a href="https://wplaunchify.com/#pricing" class="wplk-button wplk-featured"target="_blank">Upgrade To Pro Now!</a></p>
                                </div>

                                <div class="wplk-settings-container">
                                    <h2>Subscribe To The WPLaunchClub Newsletter</h2>
                                    <p>WordPress Tutorials For Business Owners<br/>
                                    Membership, Marketing Automation, Online Courses, eCommerce & BuddyBoss</em></strong></p>
                                    <a href="https://wplaunchify.com/#newsletter" class="wplk-button wplk-featured" target="_blank">Subscribe Now (It's Free)</a>
                                </div>
                            </div>
                        </div>
                    <?php break;
                    endswitch; ?>
                </div>
            </div>
        </div>
        <?php
    }

public function lk_enable_plugin_deactivation_js() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Re-enable deactivation links
        $('.inactive-plugin').removeClass('inactive-plugin');
        $('tr.inactive').find('.deactivate').removeClass('edit-plugin');
    });
    </script>
    <?php
}

public function lk_enable_plugin_deactivation_css() {
    ?>
    <style type="text/css">
        .plugins .inactive-plugin .delete-plugin {
            display: inline-block !important;
        }
        .plugins .inactive .deactivate {
            display: inline-block !important;
        }
    </style>
    <?php
}

public function lk_add_deactivate_link($actions, $plugin_file) {
    if(isset($actions['deactivate'])) {
        $actions['deactivate'] = str_replace('class="edit-plugin"', '', $actions['deactivate']);
    }
    return $actions;
}

public function lk_add_delete_link($actions, $plugin_file) {
    if(isset($actions['delete'])) {
        $actions['delete'] = str_replace('class="delete-plugin"', 'class="delete-plugin" style="display:inline-block;"', $actions['delete']);
    }
    return $actions;
}

    public function wplk_apply_settings() {
        $options = get_option('wplk_settings');

        /* Base Options */
        // Disable Activation Wizards
        if(isset($options['wplk_checkbox_field_000']) && $options['wplk_checkbox_field_000'] == '1') {
            function lk_prevent_plugin_activation_redirect() {
                if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/wp-admin/plugins.php') !== false) {
                    $redirect_pages = array(
                        'kadence-starter-templates',
                        'searchwp-welcome',
                        'cptui_main_menu',
                        'sc-about',
                        'woocommerce-events-help',
                        'fooevents-introduction',
                        'fooevents-settings',
                        'yith-licence-activation',
                        'profile-builder-basic-info',
                        'bp-components',
                    );

                    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

                    if (in_array($current_page, $redirect_pages)) {
                        wp_redirect(admin_url('plugins.php'));
                        exit();
                    }
                }
            }
            add_action('admin_init', 'lk_prevent_plugin_activation_redirect', 1);
            add_filter('woocommerce_prevent_automatic_wizard_redirect', '__return_true');

            function lk_disable_wc_setup_wizard_redirect() {
                if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'wc-admin' && isset($_GET['path']) && $_GET['path'] === '/setup-wizard') {
                    wp_redirect(admin_url());
                    exit;
                }
            }
            add_action('admin_init', 'lk_disable_wc_setup_wizard_redirect');
        }

        // Hide Admin Notices
        if(isset($options['wplk_checkbox_field_001']) && $options['wplk_checkbox_field_001'] == '1') {
            function lk_admin_bar_button($wp_admin_bar) {
                $hide_notices = get_option('launchkit_hide_notices', false);
                $button_text = $hide_notices ? 'Notices Hidden' : 'Hide Notices';
                $button_id = 'launchkit-hide-notices';
                $button_class = $hide_notices ? 'launchkit-show-notices' : 'launchkit-hide-notices';
                $notice_count_html = $hide_notices ? '<span class="launchkit-notice-count">!</span>' : '';

                $args = array(
                    'id' => $button_id,
                    'title' => $button_text . $notice_count_html,
                    'href' => '#',
                    'meta' => array('class' => $button_class)
                );

                $wp_admin_bar->add_node($args);
            }
            add_action('admin_bar_menu', 'lk_admin_bar_button', 999);

            function lk_enqueue_scripts() {
                wp_enqueue_script('jquery');
                ?>
                <style>
                    #wp-admin-bar-launchkit-hide-notices .ab-item,
                    #wp-admin-bar-launchkit-show-notices .ab-item {
                        color: #fff;
                        background-color: transparent;
                    }
                    #wp-admin-bar-launchkit-hide-notices .launchkit-notice-count,
                    #wp-admin-bar-launchkit-show-notices .launchkit-notice-count {
                        display: inline-block;
                        min-width: 18px;
                        height: 18px;
                        border-radius: 9px;
                        margin: 7px 0 0 2px;
                        vertical-align: top;
                        font-size: 11px;
                        line-height: 1.6;
                        text-align: center;
                        background-color: #ff0000;
                        color: #fff;
                    }
                </style>
                <script>
                    jQuery(document).ready(function($) {
                        $('.launchkit-hide-notices, .launchkit-show-notices').on('click', function(e) {
                            e.preventDefault();
                            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                                action: 'launchkit_toggle_notices'
                            }, function(response) {
                                if (response.success) {
                                    location.reload();
                                }
                            });
                        });
                    });
                </script>
                <?php
            }
            add_action('admin_footer', 'lk_enqueue_scripts');

            function lk_toggle_notices() {
                $hide_notices = get_option('launchkit_hide_notices', false);
                update_option('launchkit_hide_notices', !$hide_notices);
                wp_send_json_success();
            }
            add_action('wp_ajax_launchkit_toggle_notices', 'lk_toggle_notices');

            function lk_hide_notices_css() {
                $hide_notices = get_option('launchkit_hide_notices', false);
                if ($hide_notices) {
                    echo '<style>
                        body.wp-admin #lmnExt, 
                        body.wp-admin #wp-admin-bar-seedprod_admin_bar,
                        body.wp-admin .update-nag,
                        body.wp-admin .updated,
                        body.wp-admin .error, 
                        body.wp-admin .is-dismissible,
                        body.wp-admin .notice,
                        body.wp-admin .wp-pointer-left,
                        #yoast-indexation-warning,
                        li#wp-admin-bar-searchwp_support,
                        .searchwp-license-key-bar,
                        .searchwp-settings-statistics-upsell,
                        .dashboard_page_searchwp-welcome .swp-button.swp-button--xl:nth-child(2),
                        .dashboard_page_searchwp-welcome .swp-content-block.swp-bg--black,
                        .woocommerce-layout__header-tasks-reminder-bar,
                        #woocommerce-activity-panel #activity-panel-tab-setup,
                        span.wp-ui-notification.searchwp-menu-notification-counter,
                        .yzp-heading, 
                        .youzify-affiliate-banner,
                        .pms-cross-promo,
                        .yzp-heading
                        {
                            display: none !important;
                        }
                        .lf-always-show-notice{ display:block!important;}
                        a.searchwp-sidebar-add-license-key, 
                        a.searchwp-sidebar-add-license-key:hover, 
                        a.searchwp-sidebar-add-license-key:focus, 
                        a.searchwp-sidebar-add-license-key:active {
                            color: rgba(240,246,252,.7) !important;
                            background-color: inherit !important;
                            font-weight: normal !important;
                        }
                    </style>';
                }
            }
            add_action('admin_head', 'lk_hide_notices_css');

            function lk_remove_searchwp_about_us_submenu($submenu_pages) {
                unset($submenu_pages['about-us']);
                return $submenu_pages;
            }
            add_filter('searchwp\options\submenu_pages', 'lk_remove_searchwp_about_us_submenu', 999);

            function lk_change_searchwp_license_submenu_label() {
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('.searchwp-sidebar-add-license-key a').text('General Settings');
                    });
                </script>
                <?php
            }
            add_action('admin_footer', 'lk_change_searchwp_license_submenu_label');
        }

        // LearnDash License Management Disabler
        if(isset($options['wplk_checkbox_field_002']) && $options['wplk_checkbox_field_002'] == '1') {
            add_filter('plugins_api', 'lk_disable_learndash_license_management_install', 10, 3);
            function lk_disable_learndash_license_management_install($api, $action, $args) {
                if (isset($args->slug) && $args->slug === 'learndash-hub') {
                    return new WP_Error('plugin_disabled', 'The LearnDash license management plugin is disabled.');
                }
                return $api;
            }

            add_action('admin_init', 'lk_deactivate_learndash_license_management');
            function lk_deactivate_learndash_license_management() {
                $plugin = 'learndash-hub/learndash-hub.php';
                if (is_plugin_active($plugin)) {
                    deactivate_plugins($plugin);
                }
            }

            add_action('admin_head-plugins.php', 'lk_learndash_license_management_row_style');
            function lk_learndash_license_management_row_style() {
                echo '<style>tr.inactive[data-slug="learndash-licensing-management"] { display:none; }</style>';
            }
        }

        // ReEnable Pre 6.5 Plugin Manager
        if(isset($options['wplk_checkbox_field_003']) && $options['wplk_checkbox_field_003'] == '1') {
            add_action('admin_head', 'lk_enable_plugin_deactivation_js');
            add_action('admin_print_styles', 'lk_enable_plugin_deactivation_css');
            add_filter('plugin_action_links', 'lk_add_deactivate_link', 10, 2);
            add_filter('plugin_action_links', 'lk_add_delete_link', 10, 2);
        }

        // Hide LaunchKit from Admin Menu
        if(isset($options['wplk_checkbox_field_004']) && $options['wplk_checkbox_field_004'] == '1') {
            add_action('admin_menu', 'hide_wplk_admin_menu');
            function hide_wplk_admin_menu() {
                remove_menu_page('wplk');
            }
        }

        // Disable WP Update Emails
        if(isset($options['wplk_checkbox_field_005']) && $options['wplk_checkbox_field_005'] == '1') {
            // disable plugin update emails
            add_filter('auto_plugin_update_send_email', '__return_false');    

            // disable theme update emails
            add_filter('auto_theme_update_send_email', '__return_false');

            // disable core update emails
            add_filter('auto_core_update_send_email', 'lf_disable_core_update_emails', 10, 4);
            
            function lf_disable_core_update_emails($send, $type, $core_update, $result) {
                if (!empty($type) && $type == 'success') {
                    return false;
                }
                return true;
            }
        }

        return;
    }
} 

// Instantiate LaunchKit Class
new LaunchKit();
