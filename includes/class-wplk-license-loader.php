<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPLKLicenseKeyAutoloader {
    const WPLAUNCHIFY_URL = 'https://wplaunchify.com';
    private $license_verify_key = '__fluent_community_pro_license_verify';
    private $plugin_file;

    public function __construct($plugin_file = null) {
        if (null === $plugin_file) {
            $plugin_file = dirname(dirname(__FILE__)) . '/launchkit.php';
        }
        $this->plugin_file = $plugin_file;
        
        $this->license_key_autoloader_check_default_key();
        
        add_action('admin_menu', array($this, 'launchkit_license_menu'));
        add_action('admin_init', array($this, 'conditionally_run_license_key_autoloader_save'));
        add_action('admin_init', array($this, 'license_key_autoloader_check_default_key'));
        add_action('admin_head', array($this, 'hide_fcom_portal_section_if_default_key'));
        
        $this->setup_enhanced_license_management();
        $this->setup_fluentboards_license_management();
        add_action('admin_init', array($this, 'remove_license_notices'), 999);
        register_deactivation_hook($this->plugin_file, array($this, 'cleanup_on_deactivation'));
    }

    public function license_key_autoloader_check_default_key() {
    $user_data = get_transient('lk_user_data');
    $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
    $stored_key = get_option('__fluent_community_pro_license_key', '');
    $stored_boards_key = get_option('__fbs_plugin_license_key', '');

    // If using default key or no key exists, restore our overrides
    if (empty($stored_key) || $stored_key === $default_key) {
        // Set Fluent Community Pro license
        update_option('__fluent_community_pro_license_key', $default_key);
        $this->update_fluent_license_status($default_key);
        update_option($this->license_verify_key, time());
        
        // Force refresh our filters
        $this->setup_enhanced_license_management();
        
        // Prevent Fluent from checking license immediately
        delete_transient('fc_license_check_status');
        add_filter('fluent_community_pro_license_status', array($this, 'force_valid_license_status'));
    }

    // Same for FluentBoards
    if (empty($stored_boards_key) || $stored_boards_key === $default_key) {
        update_option('__fbs_plugin_license_key', $default_key);
        update_option('__fbs_plugin_license', $this->override_fluentboards_license_status(null));
        
        // Force refresh FluentBoards filters
        $this->setup_fluentboards_license_management();
    }
}

    private function setup_enhanced_license_management() {
        if (get_option('__fluent_using_custom_key')) {
            return;
        }

        add_filter('pre_option___fluent_community_pro_license', array($this, 'override_fluent_license_status'), 1);
        add_filter('pre_update_option___fluent_community_pro_license', array($this, 'filter_license_update'), 10, 2);
        add_filter('site_transient_update_plugins', array($this, 'modify_plugin_update_transient'));
        add_filter('fluent_community_pro_license_status', array($this, 'force_valid_license_status'));
        add_filter('fluent_community_pro_license_status_details', array($this, 'force_valid_license_details'));
        add_filter('rest_pre_dispatch', array($this, 'handle_rest_license_check'), 10, 3);
        add_filter('pre_option_' . $this->license_verify_key, array($this, 'force_license_verification_time'));
        add_filter('pre_update_option___fluent_community_pro_license_key', array($this, 'catch_direct_license_update'), 10, 2);
        add_action('wp_ajax_fc_pro_check_license_status', array($this, 'handle_ajax_license_check'), 1);
        
        $this->initialize_default_options();
    }
    private function setup_fluentboards_license_management() {
        if (get_option('__fluentboards_using_custom_key')) {
            return;
        }

        add_filter('pre_option___fbs_plugin_license', array($this, 'override_fluentboards_license_status'), 1);
        add_filter('pre_update_option___fbs_plugin_license', array($this, 'filter_fluentboards_license_update'), 10, 2);
        add_filter('site_transient_update_plugins', array($this, 'modify_fluentboards_update_transient'));
        add_filter('rest_pre_dispatch', array($this, 'handle_fluentboards_rest_license_check'), 10, 3);
        add_action('wp_ajax_fbs_check_license_status', array($this, 'handle_fluentboards_ajax_license_check'), 1);
        
        $this->initialize_fluentboards_default_options();
    }

    public function catch_direct_license_update($new_value, $old_value) {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        
        if ($new_value && $new_value !== $default_key) {
            remove_filter('pre_option___fluent_community_pro_license', array($this, 'override_fluent_license_status'), 1);
            remove_filter('pre_update_option___fluent_community_pro_license', array($this, 'filter_license_update'), 10);
            remove_filter('fluent_community_pro_license_status', array($this, 'force_valid_license_status'));
            remove_filter('fluent_community_pro_license_status_details', array($this, 'force_valid_license_details'));
            remove_filter('rest_pre_dispatch', array($this, 'handle_rest_license_check'), 10);
            remove_filter('pre_option_' . $this->license_verify_key, array($this, 'force_license_verification_time'));
            
            delete_option('__fluent_community_pro_license');
            delete_option('__fluent_community_pro_license_verify');
            delete_transient('fc_license_check_status');
            
            if (class_exists('FluentCommunityPro\\App\\Services\\PluginManager\\LicenseManager')) {
                do_action('fluent_community_license_verify');
            }
        }
        
        return $new_value;
    }
    public function cleanup_on_deactivation() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if (!$stored_key || $stored_key === $default_key) {
            delete_option('__fluent_community_pro_license');
            delete_option('__fluent_community_pro_license_verify');
            delete_option('__fluent_community_pro_license_key');
            delete_transient('fc_license_check_status');
            
            if (class_exists('FluentCommunityPro\\App\\Services\\PluginManager\\LicenseManager')) {
                do_action('fluent_community_license_verify');
            }
        }

        $stored_boards_key = get_option('__fbs_plugin_license_key');
        if (!$stored_boards_key || $stored_boards_key === $default_key) {
            delete_option('__fbs_plugin_license');
            delete_option('__fbs_plugin_license_key');
            
            if (class_exists('FluentBoardsPro\\App\\Services\\PluginManager\\LicenseManager')) {
                do_action('fluent_boards_license_verify');
            }
        }
    }

    public function override_fluent_license_status($value) {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return false;
        }
        
        return array(
            'license_key' => $default_key,
            'status' => 'valid',
            'expires' => date('Y-m-d', strtotime('+10 years')),
            'price_id' => '1',
            '_last_checked' => time(),
            'activated_by' => 'launchkit'
        );
    }
    public function override_fluentboards_license_status($value) {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fbs_plugin_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return false;
        }
        
        return array(
            'license_key' => $default_key,
            'status' => 'valid',
            'expires' => date('Y-m-d', strtotime('+10 years')),
            'price_id' => '1',
            '_last_checked' => time(),
            'activated_by' => 'launchkit'
        );
    }

    public function force_valid_license_status($status) {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return $status;
        }
        
        return 'valid';
    }

    public function force_valid_license_details($details) {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return $details;
        }

        return array(
            'status' => 'valid',
            'expires' => date('Y-m-d', strtotime('+10 years')),
            'success' => true
        );
    }
    public function handle_rest_license_check($result, $server, $request) {
        if (!$request) {
            return $result;
        }

        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return $result;
        }

        if ($request->get_route() == '/fluent-community/v2/license/status' || 
            strpos($request->get_route(), 'fluent-community/v2/license') !== false) {
            return $this->get_valid_license_response();
        }

        return $result;
    }

    public function handle_fluentboards_rest_license_check($result, $server, $request) {
        if (!$request) {
            return $result;
        }

        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fbs_plugin_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return $result;
        }

        if (strpos($request->get_route(), 'fluent-boards/v2/license') !== false) {
            return $this->get_valid_fluentboards_license_response();
        }

        return $result;
    }

    public function handle_ajax_license_check() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return;
        }
        
        wp_send_json($this->get_valid_license_response());
    }

    public function handle_fluentboards_ajax_license_check() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fbs_plugin_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return;
        }
        
        wp_send_json($this->get_valid_fluentboards_license_response());
    }
    private function get_valid_license_response() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        
        return array(
            'license_key' => $default_key,
            'status' => 'valid',
            'expires' => date('Y-m-d', strtotime('+10 years')),
            'success' => true
        );
    }

    private function get_valid_fluentboards_license_response() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        
        return array(
            'license_key' => $default_key,
            'status' => 'valid',
            'expires' => date('Y-m-d', strtotime('+10 years')),
            'success' => true
        );
    }

    public function force_license_verification_time() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return false;
        }
        
        return time();
    }

    private function initialize_default_options() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');

        if (!$stored_key || $stored_key === $default_key) {
            update_option('__fluent_community_pro_license', $this->override_fluent_license_status(null));
            update_option($this->license_verify_key, time());
        }
    }

    private function initialize_fluentboards_default_options() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fbs_plugin_license_key');

        if (!$stored_key || $stored_key === $default_key) {
            update_option('__fbs_plugin_license', $this->override_fluentboards_license_status(null));
        }
    }
    public function filter_license_update($value, $old_value) {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return $value;
        }

        if(is_array($value)) {
            $value['status'] = 'valid';
            $value['expires'] = date('Y-m-d', strtotime('+10 years'));
        }
        return $value;
    }

    public function filter_fluentboards_license_update($value, $old_value) {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fbs_plugin_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return $value;
        }

        if(is_array($value)) {
            $value['status'] = 'valid';
            $value['expires'] = date('Y-m-d', strtotime('+10 years'));
        }
        return $value;
    }

    public function modify_plugin_update_transient($transient) {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return $transient;
        }

        if (isset($transient->response['fluent-community-pro/fluent-community-pro.php'])) {
            unset($transient->response['fluent-community-pro/fluent-community-pro.php']);
        }
        return $transient;
    }

    public function modify_fluentboards_update_transient($transient) {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fbs_plugin_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return $transient;
        }

        if (isset($transient->response['fluent-boards-pro/fluent-boards-pro.php'])) {
            unset($transient->response['fluent-boards-pro/fluent-boards-pro.php']);
        }
        return $transient;
    }
    public function remove_license_notices() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        $stored_boards_key = get_option('__fbs_plugin_license_key');
        
        if (($stored_key && $stored_key !== $default_key) && ($stored_boards_key && $stored_boards_key !== $default_key)) {
            return;
        }

        global $wp_filter;
        if (isset($wp_filter['admin_notices'])) {
            foreach ($wp_filter['admin_notices']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $key => $callback) {
                    if (is_array($callback['function']) && is_object($callback['function'][0])) {
                        $class_name = get_class($callback['function'][0]);
                        if (strpos($class_name, 'FluentCommunity') !== false || strpos($class_name, 'FluentBoards') !== false) {
                            remove_action('admin_notices', $callback['function'], $priority);
                        }
                    }
                }
            }
        }
    }

    public function launchkit_license_menu() {
        $parent_slug = 'wplk';
        $page_slug = 'license';
        $capability = 'manage_options';

        add_submenu_page(
            $parent_slug,
            __('LaunchKit License', 'launchkit-license'),
            __('LaunchKit License', 'launchkit-license'),
            $capability,
            $page_slug,
            array($this, 'license_key_autoloader_page')
        );
    }

    public function license_key_autoloader_page() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';

        $current_key_blocks = get_option('stellarwp_uplink_license_key_kadence-blocks-pro', $default_key);
        $current_key_theme = get_option('stellarwp_uplink_license_key_kadence-theme-pro', $default_key);
        $fluent_key = get_option('__fluent_community_pro_license_key', $default_key);
        $fluentboards_key = get_option('__fbs_plugin_license_key', $default_key);

        $acf_license = get_option('acf_pro_license', null);
        if ($acf_license) {
            $acf_license = unserialize(base64_decode($acf_license));
            $acf_key = isset($acf_license['key']) ? $acf_license['key'] : $default_key;
        } else {
            $acf_key = $default_key;
        }

        $placeholder_text = '&#10004; LaunchKit Key Is Installed And Activated';
        $custom_key_text = 'Your Key Has Been Saved';

        $kadence_placeholder = ($current_key_blocks === $default_key && $current_key_theme === $default_key) ? 
            $placeholder_text : $custom_key_text;
        $fluent_placeholder = ($fluent_key === $default_key) ? $placeholder_text : $custom_key_text;
        $fluentboards_placeholder = ($fluentboards_key === $default_key) ? $placeholder_text : $custom_key_text;
        $acf_placeholder = ($acf_key === $default_key) ? $placeholder_text : $custom_key_text;

        ?>
        <div class="wrap">
            <h1>License Management</h1>
            <?php if (get_transient('lk_logged_in')) : ?>
                <p>Manage your Pro License Keys below:</p>
                
                <h2>Kadence Pro License Key</h2>
                <form method="post" action="" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('license_key_autoloader'); ?>
                    <div class="license-input-group">
                        <input type="text" name="license_key" class="regular-text" 
                            placeholder="<?php echo esc_attr($kadence_placeholder); ?>" style="color: #888;">
                        <input type="submit" name="save_kadence_key" class="button-primary" value="Save Your Key">
                        <input type="submit" name="reset_kadence_default" class="button-secondary" value="Use Default Key">
                        <input type="hidden" name="action_type" value="kadence">
                    </div>
                </form>

                <h2>FluentCommunity Pro License Key</h2>
                <div class="license-input-group">
                    <input type="text" class="regular-text" disabled
                        placeholder="<?php echo esc_attr($fluent_placeholder); ?>" style="color: #888;">
                    <form method="post" action="" style="display: inline;">
                        <?php wp_nonce_field('license_key_autoloader'); ?>
                        <input type="hidden" name="action_type" value="fluent">
                        <input type="hidden" name="redirect_to_fluent" value="1">
                        <input type="submit" name="use_fluent_key" class="button-primary" value="Use Your Key">
                    </form>
                    <form method="post" action="" style="display: inline;">
                        <?php wp_nonce_field('license_key_autoloader'); ?>
                        <input type="submit" name="reset_fluent_default" class="button-secondary" value="Use Default Key">
                        <input type="hidden" name="action_type" value="fluent">
                    </form>
                </div>

                <h2>FluentBoards Pro License Key</h2>
                <div class="license-input-group">
                    <input type="text" class="regular-text" disabled
                        placeholder="<?php echo esc_attr($fluentboards_placeholder); ?>" style="color: #888;">
                    <form method="post" action="" style="display: inline;">
                        <?php wp_nonce_field('license_key_autoloader'); ?>
                        <input type="hidden" name="action_type" value="fluentboards">
                        <input type="hidden" name="redirect_to_fluentboards" value="1">
                        <input type="submit" name="use_fluentboards_key" class="button-primary" value="Use Your Key">
                    </form>
                    <form method="post" action="" style="display: inline;">
                        <?php wp_nonce_field('license_key_autoloader'); ?>
                        <input type="submit" name="reset_fluentboards_default" class="button-secondary" value="Use Default Key">
                        <input type="hidden" name="action_type" value="fluentboards">
                    </form>
                </div>

                <h2>ACF Pro License Key</h2>
                <form method="post" action="" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('license_key_autoloader'); ?>
                    <div class="license-input-group">
                        <input type="text" name="acf_license_key" class="regular-text" 
                            placeholder="<?php echo esc_attr($acf_placeholder); ?>" style="color: #888;">
                        <input type="submit" name="save_acf_key" class="button-primary" value="Save Your Key">
                        <input type="submit" name="reset_acf_default" class="button-secondary" value="Use Default Key">
                        <input type="hidden" name="action_type" value="acf">
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
    public function conditionally_run_license_key_autoloader_save() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (!isset($_GET['page']) || $_GET['page'] !== 'wplk' || !isset($_GET['tab']) || $_GET['tab'] !== 'license') {
            return;
        }

        $this->license_key_autoloader_save();
    }

    public function license_key_autoloader_save() {
        if (!isset($_POST['action_type']) || !check_admin_referer('license_key_autoloader')) {
            return;
        }

        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';

        switch ($_POST['action_type']) {
            case 'kadence':
                $this->save_or_reset_key(
                    'stellarwp_uplink_license_key_kadence-blocks-pro',
                    $default_key,
                    isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : null
                );
                break;

            case 'fluent':
                if (isset($_POST['redirect_to_fluent'])) {
                    remove_filter('pre_option___fluent_community_pro_license', array($this, 'override_fluent_license_status'), 1);
                    remove_filter('pre_update_option___fluent_community_pro_license', array($this, 'filter_license_update'), 10);
                    remove_filter('fluent_community_pro_license_status', array($this, 'force_valid_license_status'));
                    remove_filter('fluent_community_pro_license_status_details', array($this, 'force_valid_license_details'));
                    remove_filter('rest_pre_dispatch', array($this, 'handle_rest_license_check'), 10);
                    remove_filter('pre_option_' . $this->license_verify_key, array($this, 'force_license_verification_time'));
                    remove_filter('pre_update_option___fluent_community_pro_license_key', array($this, 'catch_direct_license_update'), 10);
                    
                    delete_option('__fluent_community_pro_license');
                    delete_option('__fluent_community_pro_license_verify');
                    delete_option('__fluent_community_pro_license_key');
                    delete_transient('fc_license_check_status');
                    
                    if (class_exists('FluentCommunityPro\\App\\Services\\PluginManager\\LicenseManager')) {
                        do_action('fluent_community_license_verify');
                    }

                    update_option('__fluent_using_custom_key', true);
                    
                    wp_redirect(admin_url('admin.php?page=fluent-community&license=yes'));
                    exit;
                }
                if (isset($_POST['reset_fluent_default'])) {
                    delete_option('__fluent_using_custom_key');
                }
                
                $this->save_or_reset_key(
                    '__fluent_community_pro_license_key',
                    $default_key,
                    isset($_POST['fluent_license_key']) ? sanitize_text_field($_POST['fluent_license_key']) : null
                );
                $this->update_fluent_license_status($default_key);
                break;

            case 'fluentboards':
                if (isset($_POST['redirect_to_fluentboards'])) {
                    remove_filter('pre_option___fbs_plugin_license', array($this, 'override_fluentboards_license_status'), 1);
                    remove_filter('pre_update_option___fbs_plugin_license', array($this, 'filter_fluentboards_license_update'), 10);
                    remove_filter('rest_pre_dispatch', array($this, 'handle_fluentboards_rest_license_check'), 10);
                    
                    delete_option('__fbs_plugin_license');
                    delete_option('__fbs_plugin_license_key');
                    
                    if (class_exists('FluentBoardsPro\\App\\Services\\PluginManager\\LicenseManager')) {
                        do_action('fluent_boards_license_verify');
                    }

                    update_option('__fluentboards_using_custom_key', true);
                    
                    wp_redirect(admin_url('admin.php?page=fluent-boards#/settings/license'));
                    exit;
                }
                
                if (isset($_POST['reset_fluentboards_default'])) {
                    delete_option('__fluentboards_using_custom_key');
                }
                
                $this->save_or_reset_key(
                    '__fbs_plugin_license_key',
                    $default_key,
                    isset($_POST['fluentboards_license_key']) ? sanitize_text_field($_POST['fluentboards_license_key']) : null
                );
                break;

            case 'acf':
                $this->save_or_reset_acf_key(
                    $default_key,
                    isset($_POST['acf_license_key']) ? sanitize_text_field($_POST['acf_license_key']) : null
                );
                break;
        }

        wp_redirect(admin_url('admin.php?page=wplk&tab=license&settings-updated=true'));
        exit;
    }
    private function save_or_reset_key($option_name, $default_key, $new_key) {
        if ($option_name === '__fluent_community_pro_license_key') {
            if ($new_key) {
                delete_option('__fluent_community_pro_license');
                delete_option('__fluent_community_pro_license_verify');
                delete_transient('fc_license_check_status');
                
                update_option($option_name, $new_key);
                if (class_exists('FluentCommunityPro\\App\\Services\\PluginManager\\LicenseManager')) {
                    do_action('fluent_community_license_verify');
                }
            } else {
                update_option($option_name, $default_key);
                $this->update_fluent_license_status($default_key);
                update_option($this->license_verify_key, time());
                
                $this->setup_enhanced_license_management();
            }
        } else {
            if ($new_key) {
                update_option($option_name, $new_key);
            } else {
                update_option($option_name, $default_key);
            }
        }
    }

    private function update_fluent_license_status($default_key) {
        update_option('__fluent_community_pro_license', array(
            'license_key' => $default_key,
            'status' => 'valid',
            'expires' => date('Y-m-d', strtotime('+10 years')),
            'price_id' => '1',
            '_last_checked' => time()
        ));
    }

    private function save_or_reset_acf_key($default_key, $new_key) {
        $license_data = array(
            'key' => $new_key ? $new_key : $default_key,
            'url' => get_site_url()
        );
        update_option('acf_pro_license', base64_encode(serialize($license_data)));
    }

    public function hide_fcom_portal_section_if_default_key() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $current_key = get_option('__fluent_community_pro_license_key', '');

        if ($current_key === $default_key && get_option('__fluent_community_pro_license')) {
            echo '<style>.fcal_license_box, #fluent-community-pro-invalid-notice { display: none !important; }</style>';
        }
    }
} // End of WPLKLicenseKeyAutoloader class

new WPLKLicenseKeyAutoloader();