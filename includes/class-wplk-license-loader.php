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
    
    // Check and restore license status immediately
    $this->license_key_autoloader_check_default_key();
    
    add_action('admin_menu', array($this, 'launchkit_license_menu'));
    add_action('admin_init', array($this, 'conditionally_run_license_key_autoloader_save'));
    add_action('admin_init', array($this, 'license_key_autoloader_check_default_key'));
    add_action('admin_head', array($this, 'hide_fcom_portal_section_if_default_key'));
    
    $this->setup_enhanced_license_management();
    add_action('admin_init', array($this, 'remove_license_notices'), 999);
    register_deactivation_hook($this->plugin_file, array($this, 'cleanup_on_deactivation'));
}

private function setup_enhanced_license_management() {
    // Don't setup overrides if using a custom key
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

public function catch_direct_license_update($new_value, $old_value) {
    $user_data = get_transient('lk_user_data');
    $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
    
    if ($new_value && $new_value !== $default_key) {
        // Remove ALL our filters immediately
        remove_filter('pre_option___fluent_community_pro_license', array($this, 'override_fluent_license_status'), 1);
        remove_filter('pre_update_option___fluent_community_pro_license', array($this, 'filter_license_update'), 10);
        remove_filter('fluent_community_pro_license_status', array($this, 'force_valid_license_status'));
        remove_filter('fluent_community_pro_license_status_details', array($this, 'force_valid_license_details'));
        remove_filter('rest_pre_dispatch', array($this, 'handle_rest_license_check'), 10);
        remove_filter('pre_option_' . $this->license_verify_key, array($this, 'force_license_verification_time'));
        
        // Clean up overrides
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
    // Get stored keys
    $user_data = get_transient('lk_user_data');
    $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
    $stored_key = get_option('__fluent_community_pro_license_key');
    
    // Only clean up if we were using the default key
    if (!$stored_key || $stored_key === $default_key) {
        // Remove all our overrides
        delete_option('__fluent_community_pro_license');
        delete_option('__fluent_community_pro_license_verify');
        delete_option('__fluent_community_pro_license_key');
        
        // Remove any transients
        delete_transient('fc_license_check_status');
        
        // Force Fluent to recheck its license
        if (class_exists('FluentCommunityPro\\App\\Services\\PluginManager\\LicenseManager')) {
            do_action('fluent_community_license_verify');
        }
    }
}

    public function override_fluent_license_status($value) {
        // Check if there's a custom key that isn't the default key
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        // If they have their own key stored (not default) and it's valid, don't override
        if ($stored_key && $stored_key !== $default_key) {
            return false; // Let normal Fluent validation happen
        }
        
        // Otherwise proceed with our override
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
            return $status; // Let Fluent handle validation
        }
        
        return 'valid';
    }

    public function force_valid_license_details($details) {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return $details; // Let Fluent handle validation
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

        // Check if using custom key
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return $result; // Let normal REST API handling occur
        }

        if ($request->get_route() == '/fluent-community/v2/license/status' || 
            strpos($request->get_route(), 'fluent-community/v2/license') !== false) {
            return $this->get_valid_license_response();
        }

        return $result;
    }

public function handle_ajax_license_check() {
        // Check if using custom key before auto-validating
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return; // Let Fluent handle the AJAX response
        }
        
        wp_send_json($this->get_valid_license_response());
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

    public function force_license_verification_time() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return false; // Let Fluent handle verification time
        }
        
        return time();
    }

    private function initialize_default_options() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');

        // Only set default options if no custom key exists
        if (!$stored_key || $stored_key === $default_key) {
            update_option('__fluent_community_pro_license', $this->override_fluent_license_status(null));
            update_option($this->license_verify_key, time());
        }
    }

    public function filter_license_update($value, $old_value) {
        // Don't modify if using custom key
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

    public function modify_plugin_update_transient($transient) {
        // Only modify if using default key
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

    public function remove_license_notices() {
        // Only remove notices if using default key
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $stored_key = get_option('__fluent_community_pro_license_key');
        
        if ($stored_key && $stored_key !== $default_key) {
            return;
        }

        global $wp_filter;
        if (isset($wp_filter['admin_notices'])) {
            foreach ($wp_filter['admin_notices']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $key => $callback) {
                    if (is_array($callback['function']) && is_object($callback['function'][0])) {
                        $class_name = get_class($callback['function'][0]);
                        if (strpos($class_name, 'FluentCommunity') !== false) {
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
                // First remove all filters
                remove_filter('pre_option___fluent_community_pro_license', array($this, 'override_fluent_license_status'), 1);
                remove_filter('pre_update_option___fluent_community_pro_license', array($this, 'filter_license_update'), 10);
                remove_filter('fluent_community_pro_license_status', array($this, 'force_valid_license_status'));
                remove_filter('fluent_community_pro_license_status_details', array($this, 'force_valid_license_details'));
                remove_filter('rest_pre_dispatch', array($this, 'handle_rest_license_check'), 10);
                remove_filter('pre_option_' . $this->license_verify_key, array($this, 'force_license_verification_time'));
                remove_filter('pre_update_option___fluent_community_pro_license_key', array($this, 'catch_direct_license_update'), 10);
                
                // Clean up all overrides and stored values
                delete_option('__fluent_community_pro_license');
                delete_option('__fluent_community_pro_license_verify');
                delete_option('__fluent_community_pro_license_key');
                delete_transient('fc_license_check_status');
                delete_transient('fluent_community_pro_license_status');
                
                // Force an immediate license check by Fluent
                if (class_exists('FluentCommunityPro\\App\\Services\\PluginManager\\LicenseManager')) {
                    do_action('fluent_community_license_verify');
                }

                // Set a flag that we're using custom key mode
                update_option('__fluent_using_custom_key', true);
                
                // Redirect to Fluent's license page
                wp_redirect(admin_url('admin.php?page=fluent-community&license=yes'));
                exit;
            }
            
            if (isset($_POST['reset_fluent_default'])) {
                // Switching back to default key - remove custom key flag
                delete_option('__fluent_using_custom_key');
            }
            
            // Handle regular fluent actions (Use Default Key)
            $this->save_or_reset_key(
                '__fluent_community_pro_license_key',
                $default_key,
                isset($_POST['fluent_license_key']) ? sanitize_text_field($_POST['fluent_license_key']) : null
            );
            $this->update_fluent_license_status($default_key);
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
            // Saving a custom key - clean up all our overrides
            delete_option('__fluent_community_pro_license');
            delete_option('__fluent_community_pro_license_verify');
            delete_transient('fc_license_check_status');
            
            // Save the new key and let Fluent verify it
            update_option($option_name, $new_key);
            if (class_exists('FluentCommunityPro\\App\\Services\\PluginManager\\LicenseManager')) {
                do_action('fluent_community_license_verify');
            }
        } else {
            // Using default key - restore our overrides
            update_option($option_name, $default_key);
            $this->update_fluent_license_status($default_key);
            update_option($this->license_verify_key, time());
            
            // Reinitialize our filters
            $this->setup_enhanced_license_management();
        }
    } else {
        // Handle other plugin keys normally
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

public function license_key_autoloader_check_default_key() {
    $user_data = get_transient('lk_user_data');
    $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
    $stored_key = get_option('__fluent_community_pro_license_key', '');

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

    // Set ACF Pro license (keeping this part as it was)
    $acf_license = get_option('acf_pro_license', '');
    if (empty($acf_license)) {
        $license_data = array(
            'key' => $default_key,
            'url' => get_site_url()
        );
        update_option('acf_pro_license', base64_encode(serialize($license_data)));
    }
}

public function hide_fcom_portal_section_if_default_key() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        $current_key = get_option('__fluent_community_pro_license_key', '');

        // Check if using default key AND our overrides are active
        if ($current_key === $default_key && get_option('__fluent_community_pro_license')) {
           // echo '<style>.fcal_license_box, #fluent-community-pro-invalid-notice { display: none !important; }</style>';
        }
    }

} // End class

new WPLKLicenseKeyAutoloader();