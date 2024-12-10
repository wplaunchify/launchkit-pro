<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPLKLicenseKeyAutoloader {
    const WPLAUNCHIFY_URL = 'https://wplaunchify.com';

    public function __construct() {
        add_action('admin_menu', array($this, 'launchkit_license_menu'));
        add_action('admin_init', array($this, 'conditionally_run_license_key_autoloader_save'));
        add_action('admin_init', array($this, 'license_key_autoloader_check_default_key'));
        add_action('admin_head', array($this, 'hide_fcom_portal_section_if_default_key'));
        
        // License override filters
        add_filter('pre_option___fluent_community_pro_license', array($this, 'override_fluent_license_status'), 10, 1);
        add_filter('pre_update_option___fluent_community_pro_license', array($this, 'filter_license_update'), 10, 2);
        add_filter('site_transient_update_plugins', array($this, 'modify_plugin_update_transient'));
        
        // Remove license notices
        add_action('admin_init', array($this, 'remove_license_notices'), 999);
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
                <form method="post" action="" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('license_key_autoloader'); ?>
                    <div class="license-input-group">
                        <input type="text" name="fluent_license_key" class="regular-text" 
                            placeholder="<?php echo esc_attr($fluent_placeholder); ?>" style="color: #888;">
                        <input type="submit" name="save_fluent_key" class="button-primary" value="Save Your Key">
                        <input type="submit" name="reset_fluent_default" class="button-secondary" value="Use Default Key">
                        <input type="hidden" name="action_type" value="fluent">
                    </div>
                </form>

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

    public function override_fluent_license_status($value) {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
        
        return array(
            'license_key' => $default_key,
            'status' => 'valid',
            'expires' => date('Y-m-d', strtotime('+10 years')),
            'price_id' => '1',
            '_last_checked' => time()
        );
    }

    public function filter_license_update($value, $old_value) {
        if(is_array($value)) {
            $value['status'] = 'valid';
            $value['expires'] = date('Y-m-d', strtotime('+10 years'));
        }
        return $value;
    }

    public function modify_plugin_update_transient($transient) {
        if (isset($transient->response['fluent-community-pro/fluent-community-pro.php'])) {
            unset($transient->response['fluent-community-pro/fluent-community-pro.php']);
        }
        return $transient;
    }

    public function remove_license_notices() {
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
                $this->save_or_reset_key(
                    '__fluent_community_pro_license_key',
                    $default_key,
                    isset($_POST['fluent_license_key']) ? sanitize_text_field($_POST['fluent_license_key']) : null
                );
                $this->update_fluent_license_status($default_key);
                break;

            case 'acf':
                $this->save_or_reset_acf_key($default_key, isset($_POST['acf_license_key']) ? sanitize_text_field($_POST['acf_license_key']) : null);
                break;
        }

        wp_redirect(admin_url('admin.php?page=wplk&tab=license&settings-updated=true'));
        exit;
    }

    private function save_or_reset_key($option_name, $default_key, $new_key) {
        if ($new_key) {
            update_option($option_name, $new_key);
        } else {
            update_option($option_name, $default_key);
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
//
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

        // Set Fluent Community Pro license
        if (empty(get_option('__fluent_community_pro_license_key'))) {
            update_option('__fluent_community_pro_license_key', $default_key);
        }
        
        if (empty(get_option('__fluent_community_pro_license'))) {
            $this->update_fluent_license_status($default_key);
        }

        // Set ACF Pro license
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

        if ($current_key === $default_key) {
            // echo '<style>.fcal_license_box, #fluent-community-pro-invalid-notice { display: none !important; }</style>';
        }
    }
}

new WPLKLicenseKeyAutoloader();