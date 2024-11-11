<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
 * WPLKLicenseKeyAutoloader Class
 *
 * @since 1.0.0
 */
class WPLKLicenseKeyAutoloader {
    const WPLAUNCHIFY_URL = 'https://wplaunchify.com';

    public function __construct() {
        add_action('admin_menu', array($this, 'launchkit_license_menu'));
        add_action('admin_init', array($this, 'license_key_autoloader_save')); 
        add_action('admin_init', array($this, 'license_key_autoloader_check_default_key'));
        add_action('admin_head', array($this, 'hide_fcom_portal_section_if_default_key'));
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
        add_action('admin_head', array($this, 'hide_license_submenu_item'));
    }

    public function hide_license_submenu_item() {
        global $submenu;
        $parent_slug = 'wplk';
        $page_slug = 'launchkit-license';

        if (isset($submenu[$parent_slug])) {
            foreach ($submenu[$parent_slug] as &$item) {
                if ($item[2] === $page_slug) {
                    $item[4] = 'launchkit-license-hidden';
                    break;
                }
            }
        }

        echo '<style>
            .launchkit-license-hidden { display: none !important; }
            .license-input-group { display: flex; align-items: center; gap: 10px; }
            .license-input-group .regular-text { flex: 1; }
            .license-input-group .button { margin: 0 !important; }
        </style>';
    }

    public function license_key_autoloader_page() {
        // Get LaunchKit default key
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';

        // Kadence License Management
        $current_key_blocks = get_option('stellarwp_uplink_license_key_kadence-blocks-pro', $default_key);
        $current_key_theme = get_option('stellarwp_uplink_license_key_kadence-theme-pro', $default_key);

        // FluentCommunity License Management
        $fluent_key = get_option('__fluent_community_pro_license_key', $default_key);
        
        // ACF Pro License Management 
        $acf_license = get_option('acf_pro_license', null);
        if ($acf_license) {
            $acf_license = unserialize(base64_decode($acf_license));
            $acf_key = isset($acf_license['key']) ? $acf_license['key'] : $default_key;
        } else {
            $acf_key = $default_key;
        }
        
        // Placeholder text for all keys
        $placeholder_text = '&#10004; LaunchKit Key Is Installed And Activated';
        $custom_key_text = 'Your Key Has Been Saved';

        // Set appropriate placeholder text for each input
        $kadence_placeholder = ($current_key_blocks === $default_key && $current_key_theme === $default_key) ? 
            $placeholder_text : $custom_key_text;
        $fluent_placeholder = ($fluent_key === $default_key) ? $placeholder_text : $custom_key_text;
        $acf_placeholder = ($acf_key === $default_key) ? $placeholder_text : $custom_key_text;

        ?>
        <div class="wrap">
            <h1>License Management</h1>
            <?php if (get_transient('lk_logged_in')) : ?>
                <p>Manage your Pro License Keys below:</p>
                
                <!-- Kadence License Key Section -->
                <h2>Kadence Pro License Key</h2>
                <form method="post" action="" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('license_key_autoloader'); ?>
                    <div class="license-input-group">
                        <input type="text" name="license_key" class="regular-text" value="" 
                            placeholder="<?php echo esc_attr($kadence_placeholder); ?>" style="color: #888;">
                        <input type="submit" name="save_kadence_key" class="button-primary" value="Save Your Key">
                        <input type="submit" name="reset_kadence_default" class="button-secondary" value="Use Default Key">
                        <input type="hidden" name="action_type" value="kadence">
                    </div>
                </form>

                <!-- FluentCommunity License Key Section -->
                <h2>FluentCommunity Pro License Key</h2>
                <form method="post" action="" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('license_key_autoloader'); ?>
                    <div class="license-input-group">
                        <input type="text" name="fluent_license_key" class="regular-text" value="" 
                            placeholder="<?php echo esc_attr($fluent_placeholder); ?>" style="color: #888;">
                        <input type="submit" name="save_fluent_key" class="button-primary" value="Save Your Key">
                        <input type="submit" name="reset_fluent_default" class="button-secondary" value="Use Default Key">
                        <input type="hidden" name="action_type" value="fluent">
                    </div>
                </form>

                <!-- ACF Pro License Key Section -->
                <h2>ACF Pro License Key</h2>
                <form method="post" action="" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('license_key_autoloader'); ?>
                    <div class="license-input-group">
                        <input type="text" name="acf_license_key" class="regular-text" value="" 
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

    public function license_key_autoloader_save() {
        if (!isset($_POST['action_type']) || !check_admin_referer('license_key_autoloader')) {
            return;
        }

        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';

        switch ($_POST['action_type']) {
            case 'kadence':
                if (isset($_POST['save_kadence_key'])) {
                    $license_key = sanitize_text_field($_POST['license_key']);
                    if (!empty($license_key)) {
                        update_option('stellarwp_uplink_license_key_kadence-blocks-pro', $license_key);
                        update_option('stellarwp_uplink_license_key_kadence-theme-pro', $license_key);
                    }
                } elseif (isset($_POST['reset_kadence_default'])) {
                    update_option('stellarwp_uplink_license_key_kadence-blocks-pro', $default_key);
                    update_option('stellarwp_uplink_license_key_kadence-theme-pro', $default_key);
                }
                break;

            case 'fluent':
                if (isset($_POST['save_fluent_key'])) {
                    $fluent_license_key = sanitize_text_field($_POST['fluent_license_key']);
                    if (!empty($fluent_license_key)) {
                        update_option('__fluent_community_pro_license_key', $fluent_license_key);
                        update_option('__fluent_community_pro_license', [
                            'license_key' => $fluent_license_key,
                            'price_id'    => '1',
                            'expires'     => '2099-12-31',
                            'status'      => 'valid'
                        ]);
                    }
                } elseif (isset($_POST['reset_fluent_default'])) {
                    update_option('__fluent_community_pro_license_key', $default_key);
                    update_option('__fluent_community_pro_license', [
                        'license_key' => $default_key,
                        'price_id'    => '1',
                        'expires'     => '2099-12-31',
                        'status'      => 'valid'
                    ]);
                }
                break;

            case 'acf':
                if (isset($_POST['save_acf_key'])) {
                    $acf_license_key = sanitize_text_field($_POST['acf_license_key']);
                    if (!empty($acf_license_key)) {
                        $license_data = array(
                            'key' => $acf_license_key,
                            'url' => get_site_url()
                        );
                        update_option('acf_pro_license', base64_encode(serialize($license_data)));
                        update_option('acf_pro_license_status', array(
                            'status' => 'active',
                            'created' => 0,
                            'expiry' => 0,
                            'name' => 'Agency',
                            'lifetime' => true,
                            'refunded' => false,
                            'view_licenses_url' => 'https://www.advancedcustomfields.com/my-account/view-licenses/',
                            'manage_subscription_url' => '',
                            'error_msg' => '',
                            'next_check' => time() + (30 * 24 * 60 * 60)
                        ));
                    }
                } elseif (isset($_POST['reset_acf_default'])) {
                    $license_data = array(
                        'key' => $default_key,
                        'url' => get_site_url()
                    );
                    update_option('acf_pro_license', base64_encode(serialize($license_data)));
                    update_option('acf_pro_license_status', array(
                        'status' => 'active',
                        'created' => 0,
                        'expiry' => 0,
                        'name' => 'Agency',
                        'lifetime' => true,
                        'refunded' => false,
                        'view_licenses_url' => 'https://www.advancedcustomfields.com/my-account/view-licenses/',
                        'manage_subscription_url' => '',
                        'error_msg' => '',
                        'next_check' => time() + (30 * 24 * 60 * 60)
                    ));
                }
                break;
        }

        wp_redirect(admin_url('admin.php?page=wplk&tab=license&settings-updated=true'));
        exit;
    }

    public function license_key_autoloader_check_default_key() {
        $user_data = get_transient('lk_user_data');
        $default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';

        // Check FluentCommunity default key
        $current_key = get_option('__fluent_community_pro_license_key', '');
        if (empty($current_key)) {
            update_option('__fluent_community_pro_license_key', $default_key);
            update_option('__fluent_community_pro_license', [
                'license_key' => $default_key,
                'price_id'    => '1',
                'expires'     => '2099-12-31',
                'status'      => 'valid'
            ]);
        }

        // Check ACF Pro default key
        $acf_license = get_option('acf_pro_license', '');
        if (empty($acf_license)) {
			$license_data = array(
				'key' => $default_key,
				'url' => get_site_url()
			);
			update_option('acf_pro_license', base64_encode(serialize($license_data)));
			update_option('acf_pro_license_status', array(
				'status' => 'active',
				'created' => 0,
				'expiry' => 0,
				'name' => 'Agency',
				'lifetime' => true,
				'refunded' => false,
				'view_licenses_url' => 'https://www.advancedcustomfields.com/my-account/view-licenses/',
				'manage_subscription_url' => '',
				'error_msg' => '',
				'next_check' => time() + (30 * 24 * 60 * 60)
			));
		}
	}

	public function hide_fcom_portal_section_if_default_key() {
		$user_data = get_transient('lk_user_data');
		$default_key = isset($user_data['default_key']) ? $user_data['default_key'] : '';
		$current_key = get_option('__fluent_community_pro_license_key', '');

		if ($current_key === $default_key) {
			echo '<style>.fcom_portal_section { display: none !important; }</style>';
		}
	}
}
new WPLKLicenseKeyAutoloader();