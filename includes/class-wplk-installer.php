<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
    * WPLKInstaller Class
    *
    *
    * @since 1.0.0
    */

class WPLKInstaller {

const WPLAUNCHIFY_URL = 'https://wplaunchify.com';

/**
   * Constructor
   *
   * @since 1.0.0
   * @access public
   */

   public function __construct() {

    // Add sub-menus
    add_action('admin_menu', array ($this, 'launchkit_installer_menu') );
    add_action('admin_menu', array ($this, 'launchkit_packages_menu') );


    // callbacks
    add_action('wp_ajax_check_for_updates', array($this, 'check_for_updates_callback') );
    add_action('wp_ajax_install_prime_mover', array($this, 'install_prime_mover_callback') ); 
    add_action('in_admin_header', array($this, 'launchkit_banner_on_prime_mover_backup_menu') );
    add_action('wp_ajax_upload_package_from_url', array($this, 'upload_package_from_url_callback') );
    add_action('wp_ajax_get_prime_package', array($this, 'get_prime_package_callback') );
    add_action('admin_menu', array($this, 'get_prime_submenu') );


    // skip prime mover activation script
    add_action('admin_footer', array($this, 'skip_prime_mover_activation_script') );

    add_action('wp_ajax_install_kadence_theme', array($this, 'install_kadence_theme_callback') );
    add_action('wp_ajax_install_base_launchkit', array($this, 'install_base_launchkit_callback') );

    add_action('wp_ajax_lk_remote_login', array($this, 'lk_remote_login_callback') );
    add_action('wp_ajax_nopriv_lk_remote_login', array($this, 'lk_remote_login_callback') );

    add_action('wp_ajax_lk_show_when_logged_in_check', array($this, 'lk_show_when_logged_in_check_callback') );
    add_action('wp_ajax_nopriv_lk_show_when_logged_in_check', array($this, 'lk_show_when_logged_in_check_callback') );

    add_action('wp_ajax_lk_hide_when_logged_in_check', array($this, 'lk_hide_when_logged_in_check_callback') );
    add_action('wp_ajax_nopriv_lk_hide_when_logged_in_check', array($this, 'lk_hide_when_logged_in_check_callback') );

    add_action('wp_ajax_install_plugin', array($this, 'install_plugin_callback') );
    add_action('wp_ajax_check_plugin_updates', array($this, 'check_plugin_updates_callback'));


   } // end construct




// Add the installer submenu
public function launchkit_installer_menu() {
    $parent_slug = 'wplk'; // The slug of the LaunchKit plugin's main menu
    $page_slug = 'launchkit-installer'; // The slug for the submenu page
    $capability = 'manage_options';

    add_submenu_page(
        $parent_slug,
        __('LaunchKit Installer', 'launchkit-installer'),
        __('LaunchKit Installer', 'launchkit-installer'),
        $capability,
        $page_slug,
        array($this, 'lk_get_meta_plugin_installer_page')
    );

    // Add a unique CSS class to the hidden submenu item
    add_action('admin_head', array($this, 'launchkit_hide_installer_submenu_item'));
}

// Hide the empty space of the hidden installer submenu item
public function launchkit_hide_installer_submenu_item() {
    global $submenu;
    $parent_slug = 'wplk';
    $page_slug = 'launchkit-installer';

    if (isset($submenu[$parent_slug])) {
        foreach ($submenu[$parent_slug] as &$item) {
            if ($item[2] === $page_slug) {
                $item[4] = 'launchkit-installer-hidden';
                break;
            }
        }
    }

    echo '<style>.launchkit-installer-hidden { display: none !important; }</style>';
}

// Add the packages submenu
public function launchkit_packages_menu() {
    $parent_slug = 'wplk'; // The slug of the LaunchKit plugin's main menu
    $page_slug = 'launchkit-packages'; // The slug for the submenu page
    $capability = 'manage_options';

    add_submenu_page(
        $parent_slug,
        __('LaunchKit Packages', 'launchkit-packages'),
        __('LaunchKit Packages', 'launchkit-packages'),
        //    '',  // Set the menu title to an empty string or null
        $capability,
        $page_slug,
        array($this, 'get_prime_page')
    );

    // Add a unique CSS class to the hidden submenu item
    add_action('admin_head', array($this, 'launchkit_hide_packages_submenu_item'));
}

    // Hide the empty space of the hidden packages submenu item
    public function launchkit_hide_packages_submenu_item() {
        global $submenu;
        $parent_slug = 'wplk';
        $page_slug = 'launchkit-packages';

        if (isset($submenu[$parent_slug])) {
            foreach ($submenu[$parent_slug] as &$item) {
                if ($item[2] === $page_slug) {
                    $item[4] = 'launchkit-packages-hidden';
                    break;
                }
            }
        }

        echo '<style>.launchkit-packages-hidden { display: none !important; }</style>';
    }


    // Get admin menu page content
    public function lk_get_meta_plugin_installer_page() {
        $last_email = get_option('lk_get_meta_last_email', '');
        $last_password = get_option('lk_get_meta_last_password', '');
        $last_plugin_update = get_option('lk_plugin_last_update', '');
        $last_login_date = get_option('lk_last_login_date', '');
        $can_access_launchkit = 'No'; // Initialize with a default value
        $can_access_agency = 'No'; // Initialize with a default value

        $logged_in = get_transient('lk_logged_in');
        $user_data = null; // Initialize $user_data

        if (isset($_POST['lk_get_meta_submit'])) {
            $email = sanitize_email($_POST['email']);
            $password = sanitize_text_field($_POST['password']);

            // Update stored last used email/password
            update_option('lk_get_meta_last_email', $email);
            update_option('lk_get_meta_last_password', $password);

            // Call the lk_get_user_data method using $this
            $user_data = $this->lk_get_user_data($email, $password);

            if (isset($user_data['error']) && $user_data['error']) {
                if (strpos($user_data['message'], 'Invalid credentials') !== false) {
                    $notice = '<div class="notice notice-error"><p>Invalid username or password. Please check your credentials and try again.</p></div>';
                } else {
                    $notice = '<div class="notice notice-error"><p>' . esc_html($user_data['message']) . '</p></div>';
                }
            } elseif (isset($user_data['can_access_launchkit']) && $user_data['can_access_launchkit']) {
                // Set the logged in and user data transients for 30 minutes
                set_transient('lk_logged_in', true, 30 * MINUTE_IN_SECONDS);
                set_transient('lk_user_data', $user_data, 30 * MINUTE_IN_SECONDS);
                update_option('lk_last_login_date', time());
                $logged_in = true;
            } else {
                $notice = '<div class="notice notice-error"><p>Unable to access LaunchKit features. Please ensure your account has the necessary permissions.</p></div>';
            }
        }

        // when someone logs out delete transient
        if (isset($_POST['lk_logout'])) {
            delete_transient('lk_logged_in');
            delete_transient('lk_user_data');
            $logged_in = false;
        }

        // Use transient for logged-in user data (so it doesn't have to check endpoint again)
        if ($logged_in) {
            $user_data = get_transient('lk_user_data');
        }

        // available endpoint data
        if ($user_data) {
            $first_name = isset($user_data['first_name']) ? $user_data['first_name'] : '';
            $can_access_launchkit = isset($user_data['can_access_launchkit']) && $user_data['can_access_launchkit'] ? 'Yes' : 'No';
            $can_access_agency = isset($user_data['can_access_agency']) && $user_data['can_access_agency'] ? 'Yes' : 'No';

            // for future use grab any other meta_data
            $last_name = isset($user_data['last_name']) ? $user_data['last_name'] : '';
            $tags = isset($user_data['tags']) ? $user_data['tags'] : array();
            $available_tags = isset($user_data['available_tags']) ? $user_data['available_tags'] : array();

            // Check if can_access_launchkit is "Yes"
            if ($can_access_launchkit === 'Yes') {
                ob_start();
                $this->fetch_latest_launchkit_plugins();
                $plugin_output = ob_get_clean();
            } else {
                $notice = '<div class="wplk-notice">';
                $notice .= '<p>Please check that you have a current Membership with <a href="https://wplaunchify.com" target="_blank">WPLaunchify</a>.</p>';

                $notice .= '</div>';
            }
        }

        // Check if Prime Mover plugin is installed and activated
        $prime_mover_installed = is_plugin_active('prime-mover/prime-mover.php');

        // display login form and buttons within protected area
    ?>
    <div id="wpbody" role="main">
        <div id="wpbody-content">

            <h1>Pro Features</h1>
            <p>Unlock All Features By <a href='https://wplaunchify.com/#pricing' target='_blank'>Upgrading To Pro</a></p>
            <br/><em>*Use your login credentials from <a href="https://wplaunchify.com" target="_blank">WPLaunchify</a></em></p>

            <div class="wrap">

                <?php if ($logged_in) : ?>

                <!-- header of installer -->

                <p>Hi <?php echo $first_name; ?>, you are now logged in until <?php date_default_timezone_set('America/Chicago'); echo date('F j, Y \a\t g:ia', strtotime('+30 minutes')); ?> (Chicago Time) - <a href="#" onclick="document.getElementById('lk_logout_form').submit(); return false;"><strong>Log Out</strong></a></p>
                <form id="lk_logout_form" method="post" style="display: none;">
                    <input type="hidden" name="lk_logout" value="1">
                </form>

                <?php if ($can_access_launchkit === 'Yes') : ?>
                <div class="button-container">
                    <button type="button" class="button button-primary" id="install_kadence_theme">Install Kadence Theme</button>
                    <?php if (!$prime_mover_installed) : ?>
                    <button type="button" class="button button-primary" id="install_prime_mover">Install Prime Mover</button>
                    <?php else : ?>
                    <button type="button" class="button button-primary" id="install_base_launchkit_package">Install Base LaunchKit Package</button>
                    <button type="button" class="button button-primary" id="view_packages">View Packages</button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php else : ?>
                <form method="post">

                    <table class="form-table" style="max-width: 300px;">
                        <tr>
                            <th style="width:50px;" scope="row"><label for="email">Email</label></th>
                            <td><input style="position:relative; width:90%" type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr($last_email); ?>" required></td>
                        </tr>
                        <tr>
                            <th style="width:50px;" scope="row"><label for="password">Password</label></th>
                            <td><input style="position:relative; width:90%" type="password" name="password" id="password" class="regular-text" value="<?php echo esc_attr($last_password); ?>" required></td>
                        </tr>
                    </table>

                    <p>
                        <?php submit_button('Log In', 'primary', 'lk_get_meta_submit', false, array('style' => 'width: 270px;')); ?>
                    </p>


                    <?php if (!empty($last_login_date)) : ?>
                    <p><em>Last Login: <?php echo date('F j, Y', $last_login_date); ?></em></p>
                    <?php endif; ?>

                </form>
                <?php endif; ?>

                <?php if (isset($notice)) echo $notice; ?>

                <?php if (isset($plugin_output)) echo $plugin_output; ?>

            </div>
        </div>
    </div>

    <div class="clear"></div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // click function for kadence install button
            $('#install_kadence_theme').click(function() {
                var button = $(this);
                button.text('Installing...').prop('disabled', true);

                var data = {
                    'action': 'install_kadence_theme',
                    'security': '<?php echo wp_create_nonce('install_kadence_theme_nonce'); ?>'
                };

                $.post(ajaxurl, data, function(response) {
                    button.text('Kadence Theme Installed').prop('disabled', true);
                });
            });

            // click function for install base launchkit package button
            $('#install_base_launchkit_package').click(function() {
                var button = $(this);
                button.text('Installing...').prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'get_prime_package',
                    security: '<?php echo wp_create_nonce("get_prime_package_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        // Package downloaded successfully
                        button.text('Base LaunchKit Package Installed').prop('disabled', true);
                    } else {
                        // Error occurred while downloading the package
                        button.text('Installation Failed').prop('disabled', false);
                    }
                }).fail(function(xhr, status, error) {
                    button.text('Installation Failed').prop('disabled', false);
                });
            });

            // click function for view packages button
            $('#view_packages').click(function() {
                window.location.href = '<?php echo admin_url('admin.php?page=migration-panel-backup-menu'); ?>';
            });


            // click function for install prime mover button
            $('#install_prime_mover').click(function() {
                var button = $(this);
                button.text('Installing...').prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'install_prime_mover',
                    security: '<?php echo wp_create_nonce("install_prime_mover_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        // Prime Mover installed successfully
                        button.text('Prime Mover Installed').prop('disabled', true);
                        location.reload(); // Reload the page to update the button display
                    } else {
                        // Error occurred while installing Prime Mover
                        button.text('Installation Failed').prop('disabled', false);
                    }
                }).fail(function(xhr, status, error) {
                    button.text('Installation Failed').prop('disabled', false);
                });
            });
        });

    </script>

    <?php
        }


    // talk to endpoint to validate user from endpoint
    public function lk_get_user_data($email, $password) {
        $site_url = site_url();

        $response = wp_remote_post(self::WPLAUNCHIFY_URL . '/wp-json/wplaunchify/v1/user-meta', array(
            'body' => array(
                'email' => $email,
                'password' => $password,
                'site_url' => $site_url
            )
        ));

        if (is_wp_error($response)) {
            return ['error' => true, 'message' => 'Failed to connect to the WPLaunchify service. Please try again later.'];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $body = json_decode($response_body, true);
            if ($response_code === 401) {
                return ['error' => true, 'message' => 'Invalid credentials provided. Please check your username and password at WPLaunchify.com'];
            } elseif ($response_code === 403) {
                return ['error' => true, 'message' => 'Access denied. You do not have the required membership level or permission to access this feature.'];
            } else {
                return ['error' => true, 'message' => 'An unexpected error occurred. Please try again.'];
            }
        }

        $user_data = json_decode($response_body, true);

        if (isset($user_data['launchkit_plugins_url'])) {
            set_transient('lk_user_data', $user_data, 30 * MINUTE_IN_SECONDS);
        }

        return $user_data;
    }

    //updated cached version of get plugin updates
    public function fetch_latest_launchkit_plugins() {
        $last_download_timestamp = get_option('lk_last_download_timestamp', 0);
        $current_timestamp = time();
        $cache_expiration = 12 * HOUR_IN_SECONDS; // Cache for 12 hours

        if ($current_timestamp - $last_download_timestamp >= $cache_expiration) {
            $user_data = get_transient('lk_user_data');
            
            if (!$user_data || !isset($user_data['launchkit_plugins_url'])) {
                echo "<p>Error: Unable to retrieve LaunchKit plugins URL. Please log in again.</p>";
                return;
            }

            $bundle_url = $user_data['launchkit_plugins_url'];
            $upload_dir = wp_upload_dir();
            $target_dir = trailingslashit($upload_dir['basedir']) . 'launchkit-updates';

            // Delete all existing files in the target directory
            if (file_exists($target_dir)) {
                $files = glob($target_dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            } else {
                wp_mkdir_p($target_dir);
            }

            $tmp_file = download_url($bundle_url);
            if (is_wp_error($tmp_file)) {
                echo "<p>Failed to download LaunchKit plugins: " . $tmp_file->get_error_message() . "</p>";
                return;
            }

            $zip = new ZipArchive();
            if ($zip->open($tmp_file) === TRUE) {
                $zip->extractTo($target_dir);
                $zip->close();
                @unlink($tmp_file);
                echo "<p>Latest LaunchKit downloaded and extracted successfully:</p> ";
                update_option('lk_plugin_last_update', time());
                update_option('lk_last_download_timestamp', $current_timestamp);
            } else {
                echo "<p>Failed to extract LaunchKit plugins.</p>";
                return;
            }
        } else {
            // Use the previously downloaded files
            $upload_dir = wp_upload_dir();
            $target_dir = trailingslashit($upload_dir['basedir']) . 'launchkit-updates';
        }

        // Display the plugin list
        $this->lk_display_plugins_table($target_dir, $upload_dir);
    }

    // Display list of plugins under testing that may be updated soon
    public function display_plugin_updates() {
        echo '<div class="plugin-updates">';
        echo '<h3 style="display:inline-block">Plugins Being Tested</h3><br/>';
        echo '<span>We test every new plugin update before we release them to you below.</span>';
        echo '<a href="#" id="check_plugin_updates" style="display: inline-block; margin-left: 10px;">Check for Updates</a>';
        echo '<ul id="plugin_update_list"></ul>';
        echo '</div>';
        echo '<style>
            .plugin-updates {
                margin-bottom: 20px;
            }
            .plugin-updates h3 {
                margin-bottom: 10px;
            }
            .plugin-updates ul {
                margin-left: 20px;
            }
            #check_plugin_updates {
                display: inline-block;
                margin-bottom: 10px;
                text-decoration: none;
            }
        </style>';
        echo '<script>
            jQuery(document).ready(function($) {
                function updatePluginList() {
                    $("#check_plugin_updates").text("Checking for updates...").css("pointer-events", "none");
                    $.post(ajaxurl, {
                        action: "check_plugin_updates",
                        security: "' . wp_create_nonce("check_plugin_updates_nonce") . '"
                    }, function(response) {
                        $("#plugin_update_list").html(response);
                        $("#check_plugin_updates").text("Check for Updates").css("pointer-events", "auto");
                    });
                }

                updatePluginList(); // Initial load

                $("#check_plugin_updates").click(function(e) {
                    e.preventDefault();
                    updatePluginList();
                });
            });
        </script>';
    }

    public function lk_display_plugins_table($target_dir, $upload_dir) {
    $last_download_timestamp = get_option('lk_last_download_timestamp', 0);

    // Set the timezone to Chicago (Central Time)
    date_default_timezone_set('America/Chicago');

    $last_updated_date = $last_download_timestamp > 0 ? date('F j, Y \\a\\t g:ia', $last_download_timestamp) : 'Never';

    echo '<div style="margin-top: 20px;">';

//testing
    $this->display_plugin_updates(); // Call the new method here
//testing

    echo '<h3 style="display: inline-block;">Plugins Available To Update</h3>';
    echo '<span style="margin-left: 10px;">Last Updated: ' . $last_updated_date . ' (Chicago Time)</span>';
    echo '<a href="#" id="check_for_updates" style="display: inline-block; margin-left: 10px;">Check For Updates</a>';
    echo '<div style="clear:both; margin-bottom:10px;"></div>';
    echo '<button type="button" id="install_selected" class="button button-primary">Install Selected Plugins</button>';
?>
            <button type="button" id="launchkit-cleanup-button" class="button button-secondary"><?php _e('Cleanup All Inactive Plugins', 'lk'); ?></button>
            <div id="launchkit-progress"></div>
            <div id="launchkit-summary"></div>

<script type="text/javascript">
document.getElementById('launchkit-cleanup-button').addEventListener('click', function() {
    document.getElementById('launchkit-progress').textContent = '<?php _e('Cleaning up inactive plugins...', 'lk'); ?>';
    document.getElementById('launchkit-summary').innerHTML = '';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                document.getElementById('launchkit-progress').textContent = '<?php _e('Cleanup complete.', 'lk'); ?>';

                // Combine both deleted and manually deleted plugins into a single list
                var allDeletedPlugins = response.data.deleted.concat(response.data.manual);

                // Format the output with each plugin on a new line
                var deletedPluginsList = '<p><?php _e("Deleted Plugins:", "lk"); ?></p><ul>';
                allDeletedPlugins.forEach(function(plugin) {
                    deletedPluginsList += '<li>' + plugin + '</li>';
                });
                deletedPluginsList += '</ul>';

                document.getElementById('launchkit-summary').innerHTML = deletedPluginsList;

                // Show the "Click to Refresh" link in red
                var refreshLink = '<br/><a href="#" id="launchkit-refresh-link" style="color: red; text-decoration: underline;"><?php _e("Click To Refresh Plugin List", "lk"); ?></a>';
                document.getElementById('launchkit-summary').innerHTML += refreshLink;

                // Add event listener for the refresh link
                document.getElementById('launchkit-refresh-link').addEventListener('click', function(e) {
                    e.preventDefault(); // Prevent default link behavior
                    window.location.reload(); // Reload the page when the link is clicked
                });

            } else {
                document.getElementById('launchkit-progress').textContent = '<?php _e("Error:", "lk"); ?> ' + response.data;
            }
        } else {
            document.getElementById('launchkit-progress').textContent = '<?php _e("An error occurred.", "lk"); ?>';
        }
    };

    xhr.send('action=launchkit_cleanup_plugins&security=<?php echo wp_create_nonce('launchkit_cleanup_plugins_nonce'); ?>');
});
</script>

<?php
    echo '<div class="lk-plugin-installer-form">';
    echo '<form id="plugin_installer_form">';
    echo '<table class="wp-list-table widefat fixed striped" style="width: 100%; max-width: 800px;" id="plugin-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th class="plugin-checkbox-column" style="width: 30px;" data-column-name="Select"><input type="checkbox" id="select_all" style="margin-left: 0;" /></th>';
    echo '<th class="plugin-file-column sortable" data-sort="plugin-file" style="width: 300px;" data-column-name="Plugin File"><span>Plugin File (Click To Download)</span><span class="sorting-indicator">&#9660;</span></th>';
    echo '<th class="sortable" data-sort="last-update" data-column-name="Last Update"><span>Last Update</span><span class="sorting-indicator">&#9660;</span></th>';
    echo '<th style="width: 100px;" data-column-name="Status">Status</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    $plugin_files = glob("$target_dir/*.zip");
    foreach ($plugin_files as $file) {
        $plugin_name = basename($file, ".zip");
        $plugin_slug = sanitize_title($plugin_name);
        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug);
        $status = $is_installed ? 'Installed' : 'Not Installed';
        $action_button = $is_installed ? 'Installed' : "<button type='button' class='button install_button' data-url='" . esc_url(trailingslashit($upload_dir['baseurl']) . "launchkit-updates/" . basename($file)) . "'>Install</button>";

        $last_modified = date('Y-m-d', filemtime($file));
        $download_link = esc_url(trailingslashit($upload_dir['baseurl']) . "launchkit-updates/" . basename($file));

        echo "<tr>";
        echo "<td class='plugin-checkbox-column'><input type='checkbox' class='plugin_checkbox' data-url='" . $download_link . "' data-slug='$plugin_slug'></td>";
        echo "<td data-plugin-file='$plugin_name'><a href='$download_link' download>$plugin_name.zip</a></td>";
        echo "<td data-last-update='$last_modified'>" . date('F j, Y', strtotime($last_modified)) . "</td>";
        echo "<td class='plugin_status'>$action_button</td>";
        echo "</tr>";
    }

    echo '</tbody></table>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    $ajax_nonce = wp_create_nonce('plugin_installer_nonce');
?>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        $(document).on('click', '.install_button', function() {
            var button = $(this);
            var plugin_url = button.data('url');
            var status_element = button.closest('tr').find('.plugin_status');

            status_element.html('Installing... <img src="<?php echo includes_url('images/spinner.gif'); ?>" alt="Installing...">');

            var data = {
                'action': 'install_plugin',
                'plugin_url': plugin_url,
                'security': '<?php echo $ajax_nonce; ?>'
            };

            jQuery.post(ajaxurl, data, function(response) {
                if (response.success) {
                    status_element.html('Installed');
                } else {
                    status_element.html('Installation failed: ' + response.data);
                }
            });
        });

        $('#install_selected').click(function() {
            $('.plugin_checkbox').each(function() {
                if ($(this).is(':checked')) {
                    $(this).closest('tr').find('.install_button').trigger('click');
                }
            });
        });

        $(document).on('change', '#select_all', function() {
            $('.plugin_checkbox').prop('checked', this.checked);
        });

        // Sorting functionality
        $('.sortable').click(function() {
            var table = $('#plugin-table');
            var tbody = table.find('tbody');
            var rows = tbody.find('tr').toArray();
            var sortColumn = $(this).data('sort');
            var sortOrder = $(this).hasClass('asc') ? 'desc' : 'asc';

            rows.sort(function(a, b) {
                var aValue = $(a).find('td[data-' + sortColumn + ']').data(sortColumn);
                var bValue = $(b).find('td[data-' + sortColumn + ']').data(sortColumn);

                if (sortColumn === 'last-update') {
                    aValue = new Date(aValue);
                    bValue = new Date(bValue);
                    return sortOrder === 'asc' ? aValue - bValue : bValue - aValue;
                } else {
                    return sortOrder === 'asc' ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
                }
            });

            tbody.empty();
            $.each(rows, function(index, row) {
                tbody.append(row);
            });

            $('.sortable').removeClass('asc desc');
            $(this).addClass(sortOrder);
            $(this).find('.sorting-indicator').html(sortOrder === 'asc' ? '&#9650;' : '&#9660;');
        });

        $('#check_for_updates').click(function(e) {
            e.preventDefault();
            var button = $(this);
            button.text('Checking for updates...').prop('disabled', true);

            $.post(ajaxurl, {
                action: 'check_for_updates',
                security: '<?php echo wp_create_nonce("check_for_updates_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || 'Failed to check for updates. Please try again.');
                    button.text('Check For Updates').prop('disabled', false);
                }
            }).fail(function(xhr, status, error) {
                alert('An error occurred while checking for updates: ' + error);
                button.text('Check For Updates').prop('disabled', false);
            });
        });
    });
</script>
<style>
    /* Responsive styles */
    @media screen and (max-width: 782px) {
        .lk-plugin-installer-form table.wp-list-table {
            display: block;
            overflow-x: auto;
        }

        .lk-plugin-installer-form table.wp-list-table thead,
        .lk-plugin-installer-form table.wp-list-table tbody,
        .lk-plugin-installer-form table.wp-list-table tr,
        .lk-plugin-installer-form table.wp-list-table td,
        .lk-plugin-installer-form table.wp-list-table th {
            display: block;
        }

        .lk-plugin-installer-form table.wp-list-table thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }

        .lk-plugin-installer-form table.wp-list-table tr {
            border: 1px solid #ccc;
            margin-bottom: 10px;
        }

        .lk-plugin-installer-form table.wp-list-table td {
            border: none;
            border-bottom: 1px solid #eee;
            position: relative;
            padding-left: 5%;
            text-align: left;
        }

        .lk-plugin-installer-form table.wp-list-table td:before {
            position: absolute;
            top: 6px;
            left: 6px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            content: attr(data-column-name);
            font-weight: bold;
        }

        .lk-plugin-installer-form table.wp-list-table .plugin-checkbox-column,
        .lk-plugin-installer-form table.wp-list-table .plugin-file-column {
            width: auto;
        }

        .lk-plugin-installer-form table.wp-list-table .button-container {
            flex-direction: column;
        }
    }

    .sortable {
        cursor: pointer;
    }
    .sortable span {
        display: inline-block;
        vertical-align: middle;
    }
    .sorting-indicator {
        margin-left: 5px;
        margin-top:-15px;
        color: #000;
    }
    .sortable.asc .sorting-indicator,
    .sortable.desc .sorting-indicator {
        color: #333;
        margin-top: -15px;
    }

    .button-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .button-container .button {
        flex: 1;
    }    
</style>
<?php
}

    // callback when checking for plugin updates vs logging in
    public function check_for_updates_callback() {
        check_ajax_referer('check_for_updates_nonce', 'security');

        $user_data = get_transient('lk_user_data');
        
        if (!$user_data || !isset($user_data['launchkit_plugins_url'])) {
            wp_send_json_error('Unable to retrieve LaunchKit plugins URL. Please log in again.');
            return;
        }

        $bundle_url = $user_data['launchkit_plugins_url'];
        $upload_dir = wp_upload_dir();
        $target_dir = trailingslashit($upload_dir['basedir']) . 'launchkit-updates';

        // Delete all existing files in the target directory
        if (file_exists($target_dir)) {
            $files = glob($target_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        } else {
            wp_mkdir_p($target_dir);
        }

        $tmp_file = download_url($bundle_url);
        if (is_wp_error($tmp_file)) {
            error_log('Failed to download LaunchKit plugins: ' . $tmp_file->get_error_message());
            wp_send_json_error('Failed to download LaunchKit plugins.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp_file) === true) {
            $zip->extractTo($target_dir);
            $zip->close();
            @unlink($tmp_file);
            update_option('lk_plugin_last_update', time());
            update_option('lk_last_download_timestamp', time());
			wp_send_json_success();
		} else {
			error_log('Failed to extract LaunchKit plugins.');
			wp_send_json_error('Failed to extract LaunchKit plugins.');
		}
	}


	// callback for validating plugins updated
	public function install_plugin_callback() {
		check_ajax_referer('plugin_installer_nonce', 'security');
		$plugin_url = isset($_POST['plugin_url']) ? sanitize_text_field($_POST['plugin_url']) : '';
		if (empty($plugin_url)) {
			wp_send_json_error('No plugin URL provided.');
		}
		$temp_file = download_url($plugin_url);
		if (is_wp_error($temp_file)) {
			wp_send_json_error('Download failed: ' . $temp_file->get_error_message());
		}
		WP_Filesystem();
		$plugin_name = basename($plugin_url, '.zip');
		$plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_name;
		if (is_dir($plugin_dir)) {
			$this->delete_directory($plugin_dir);
		}
		$result = unzip_file($temp_file, WP_PLUGIN_DIR);
		unlink($temp_file);
		if (is_wp_error($result)) {
			wp_send_json_error('Installation failed: ' . $result->get_error_message());
		} else {
			wp_send_json_success('Installed');
		}
	}

	private function delete_directory($dir) {
		if (!is_dir($dir)) {
			return;
		}

		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (is_dir($dir . "/" . $object)) {
					$this->delete_directory($dir . "/" . $object);
				} else {
					unlink($dir . "/" . $object);
				}
			}
		}
		rmdir($dir);
	}


	// callback for installing kadence theme
	public function install_kadence_theme_callback() {
		check_ajax_referer('install_kadence_theme_nonce', 'security');

		include_once ABSPATH . 'wp-admin/includes/theme-install.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$api = themes_api('theme_information', array('slug' => 'kadence'));

		if (is_wp_error($api)) {
			wp_send_json_error($api->get_error_message());
		}

		$skin = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Theme_Upgrader($skin);
		$result = $upgrader->install($api->download_link);

		if (is_wp_error($result)) {
			wp_send_json_error($result->get_error_message());
		} elseif (is_wp_error($skin->result)) {
			wp_send_json_error($skin->result->get_error_message());
		} elseif ($skin->get_errors()->has_errors()) {
			wp_send_json_error($skin->get_error_messages());
		} else {
			switch_theme('kadence');
			wp_send_json_success();
		}
	}


	// after installing prime mover it will click the opt out button on modal on specific page only
	public function skip_prime_mover_activation_script() {
		$current_url = isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : '';
		if ($current_url === '/wp-admin/admin.php?page=migration-panel-settings') {
?>
<script>
	jQuery(document).ready(function($) {
		var skipLink = null;
		var observer = new MutationObserver(function(mutations) {
			mutations.forEach(function(mutation) {
				if (mutation.type === 'childList') {
					skipLink = $(mutation.target).find('#skip_activation').first();
					if (skipLink.length > 0 && !window.skipPrimeMoverActivationClicked) {
						window.skipPrimeMoverActivationClicked = true;
						window.location.href = skipLink.attr('href');
					}
				}
			});
		});

		observer.observe(document.body, { childList: true, subtree: true });
	});
</script>
<?php
		}
	}

	public function get_prime_submenu() {
		add_submenu_page(
			'lk-get-meta-plugin-installer', // Use the slug of the parent plugin
			'Packages', // Page title
			'Packages', // Menu title
			'manage_options', // Capability
			'get-prime', // Menu slug
			array($this, 'get_prime_page') // Function to display the page content
		);
	}


	// Add LaunchKit banner on Prime Mover admin pages
	public function launchkit_banner_on_prime_mover_backup_menu() {
		$current_screen = get_current_screen();
		$current_url = isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : '';

		// Check if the current URL starts with /wp-admin/admin.php?page=migration-panel or /wp-admin/tools.php?page=migration-tools
		$is_prime_mover_page = (strpos($current_url, '/wp-admin/admin.php?page=migration-panel') === 0) || (strpos($current_url, '/wp-admin/tools.php?page=migration-tools') === 0);

		// Check if the current screen ID starts with 'prime-mover_page_', 'tools_page_migration-tools', or if it's a Prime Mover page
		if (strpos($current_screen->id, 'prime-mover_page_') === 0 || $current_screen->id === 'tools_page_migration-tools' || $is_prime_mover_page || $current_screen->base === 'toplevel_page_migration-panel-settings') {
?>
<div class="launchkit-banner">
	<div class="launchkit-banner-content">
		<a href="<?php echo admin_url('admin.php?page=launchkit-packages'); ?>" class="button button-primary">Browse LaunchKit Packages</a>
		<span class="launchkit-banner-text">Then Launch In A Minute!</span>
	</div>
</div>
<style>
	.launchkit-banner {
		background-color: #fff;
		padding: 20px;
		margin-bottom: 20px;
		border-bottom: 1px solid #ccc;
		margin-left:-20px;
	}
	.launchkit-banner-content {
		display: flex;
		align-items: center;
	}
	.launchkit-banner-text {
		margin-left: 20px;
	}
</style>
<?php
    }
	}


	public function launchkit_view_packages_button() {
		$logged_in = get_transient('lk_logged_in');
		$prime_mover_installed = is_plugin_active('prime-mover/prime-mover.php');

		if ($logged_in && $prime_mover_installed) {
?>
<button type="button" class="button button-primary" id="view_packages" style="width: 200px;">View Packages</button>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#view_packages').click(function() {
			window.location.href = '<?php echo admin_url('admin.php?page=get-prime'); ?>';
		});
	});
</script>
<?php
												  } else {
?>
<?php launchkit_view_packages_button(); ?>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#install_base_launchkit').click(function() {
			var button = $(this);
			button.text('Installing...').prop('disabled', true);

			var data = {
				'action': 'install_base_launchkit',
				'security': '<?php echo wp_create_nonce('install_base_launchkit_nonce'); ?>'
			};

			$.post(ajaxurl, data, function(response) {
				if (response.success) {
					// Redirect if successful
					window.location.href = response.data.redirect_url;
				} else {
					// Show error message
					alert(response.data.message);
					button.text('Install Base LaunchKit').prop('disabled', false);
				}
			}).fail(function() {
				alert('An error occurred.');
			});
		});
	});
</script>
<?php
														 }
	}

	public function launchkit_switch_packages_button() {
		$logged_in = get_transient('lk_logged_in');
		$prime_mover_installed = is_plugin_active('prime-mover/prime-mover.php');

		if ($logged_in && $prime_mover_installed) {
			// ... (View Packages button logic remains the same) ...
		} else {
?>
<button type="button" class="button button-primary" id="install_base_launchkit_package" style="width: 200px;">Install Base LaunchKit Package</button>
<div id="launchkit_package_notice" style="display: none;"></div>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#install_base_launchkit_package').click(function() {
			var button = $(this);
			var noticeDiv = $('#launchkit_package_notice');
			button.text('Installing...').prop('disabled', true);
			noticeDiv.hide();

			// Make an AJAX request to get_prime_function()
			$.post(ajaxurl, {
				action: 'get_prime_package',
				security: '<?php echo wp_create_nonce("get_prime_package_nonce"); ?>'
			}, function(response) {
				if (response.success) {
					// Package downloaded successfully
					button.text('Base LaunchKit Package Installed');
					noticeDiv.html('<div class="notice notice-success"><p>Base LaunchKit package downloaded and installed successfully.</p></div>').show();
				} else {
					// Error occurred while downloading the package
					button.text('Install Base LaunchKit Package').prop('disabled', false);
					noticeDiv.html('<div class="notice notice-error"><p>Error downloading Base LaunchKit package: ' + response.data.message + '</p></div>').show();
				}
			}).fail(function(xhr, status, error) {
				button.text('Install Base LaunchKit Package').prop('disabled', false);
				noticeDiv.html('<div class="notice notice-error"><p>An error occurred while downloading the Base LaunchKit package: ' + error + '</p></div>').show();
			});
		});
	});
</script>
<?php
			   }
	}


	public function install_base_launchkit_callback() {
		check_ajax_referer('install_base_launchkit_nonce', 'security');

		// Check if Prime Mover plugin is installed and activated
		$prime_mover_installed = is_plugin_active('prime-mover/prime-mover.php');

		if (!$prime_mover_installed) {
			// ... (Prime Mover installation logic remains the same) ...
		} else {
			// If Prime Mover is already installed and activated, show the alternative button to install the Base LaunchKit package
?>
<button type="button" class="button button-primary" id="install_base_launchkit_package">Install Base LaunchKit Package</button>
<div id="launchkit_package_notice" style="display: none;"></div>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#install_base_launchkit_package').click(function() {
			var button = $(this);
			var noticeDiv = $('#launchkit_package_notice');
			button.text('Installing...').prop('disabled', true);
			noticeDiv.hide();

			// Make an AJAX request to get_prime_function()
			$.post(ajaxurl, {
				action: 'get_prime_package',
				security: '<?php echo wp_create_nonce("get_prime_package_nonce"); ?>'
			}, function(response) {
				if (response.success) {
					// Package downloaded successfully
					button.text('Base LaunchKit Package Installed');
					noticeDiv.html('<div class="notice notice-success"><p>Base LaunchKit package downloaded and installed successfully.</p></div>').show();
				} else {
					// Error occurred while downloading the package
					button.text('Install Base LaunchKit Package').prop('disabled', false);
					noticeDiv.html('<div class="notice notice-error"><p>Error downloading Base LaunchKit package: ' + response.data.message + '</p></div>').show();
				}
			}).fail(function(xhr, status, error) {
				button.text('Install Base LaunchKit Package').prop('disabled', false);
				noticeDiv.html('<div class="notice notice-error"><p>An error occurred while downloading the Base LaunchKit package: ' + error + '</p></div>').show();
			});
		});
	});
</script>
<?php
			wp_die(); // Stop further execution since we're displaying the alternative button
		}
	}

	public function install_prime_mover_callback() {
		check_ajax_referer('install_prime_mover_nonce', 'security');

		// Check if Prime Mover plugin is already installed
		if (file_exists(WP_PLUGIN_DIR . '/prime-mover/prime-mover.php')) {
			// If Prime Mover is already installed, activate it
			activate_plugin('prime-mover/prime-mover.php');
			$this->skip_prime_mover_activation_script();
			wp_send_json_success();
		} else {
			include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$api = plugins_api('plugin_information', array('slug' => 'prime-mover'));

			if (is_wp_error($api)) {
				wp_send_json_error($api->get_error_message());
			}

			$skin = new WP_Ajax_Upgrader_Skin();
			$upgrader = new Plugin_Upgrader($skin);
			$result = $upgrader->install($api->download_link);

			if (is_wp_error($result)) {
				wp_send_json_error($result->get_error_message());
			} elseif (is_wp_error($skin->result)) {
				wp_send_json_error($skin->result->get_error_message());
			} elseif ($skin->get_errors()->has_errors()) {
				wp_send_json_error($skin->get_error_messages());
			} else {
				activate_plugin('prime-mover/prime-mover.php');
				$this->skip_prime_mover_activation_script();
				wp_send_json_success();
			}
		}
	}
	//add_action('wp_ajax_install_prime_mover', 'install_prime_mover_callback'); 


	public function get_prime_page() {
		$user_data = get_transient('lk_user_data');

		if (!$user_data || !isset($user_data['can_access_launchkit']) || !$user_data['can_access_launchkit']) {
?>
<h1>LaunchKit Packages</h1>
<div class="wrap">
	<p>Sorry, please <a href="<?php echo admin_url('admin.php?page=wplk&tab=installer'); ?>">log in with proper credentials</a> to view available packages.</p>
</div>
<?php
																											   return;
																											  }

		$launchkit_package_url = isset($user_data['launchkit_package_url']) ? $user_data['launchkit_package_url'] : '';
		$package_one_url = isset($user_data['package_one_url']) ? $user_data['package_one_url'] : '';
		$package_two_url = isset($user_data['package_two_url']) ? $user_data['package_two_url'] : '';
		$package_three_url = isset($user_data['package_three_url']) ? $user_data['package_three_url'] : '';
?>

<h1>Install LaunchKit Packages</h1>
<div class="wrap">
	<div id="launchkit_package_notice" style="display: none;"></div>
	<?php
		// Check if Prime Mover plugin is installed and activated
		$prime_mover_installed = is_plugin_active('prime-mover/prime-mover.php');
		if ($prime_mover_installed) {
	?>
	<a class="button" href="<?php echo admin_url('admin.php?page=migration-panel-backup-menu'); ?>">View Your Installed Packages</a>
	<?php
		}
	?>

	<!-- Package Selection -->
	<h3>Select Package</h3>
	<div class="package-selection-row">
		<div class="package-selection-column">
			<div class="package-image-wrapper">
				<img src="https://launchkit.b-cdn.net/minute-launch-package-one.png" alt="Package One Image" class="package-image">
			</div>
			<button class="button upload-package-button" data-package-url="<?php echo esc_url($package_one_url); ?>">Upload Package 1</button>
		</div>
		<div class="package-selection-column">
			<div class="package-image-wrapper">
				<img src="https://launchkit.b-cdn.net/minute-launch-package-two.png" alt="Package Two Image" class="package-image">
			</div>
			<button class="button upload-package-button" data-package-url="<?php echo esc_url($package_two_url); ?>">Upload Package 2</button>
		</div>
		<div class="package-selection-column">
			<div class="package-image-wrapper">
				<img src="https://launchkit.b-cdn.net/minute-launch-package-three.png" alt="Package Three Image" class="package-image">
			</div>
			<button class="button upload-package-button" data-package-url="<?php echo esc_url($package_three_url); ?>">Upload Package 3</button>
		</div>
	</div>

	<!-- Custom Package Upload -->
	<h3>Upload Your Own Package</h3>
	<form method="post" id="custom_package_upload_form">
		<label for="custom_package_url">Add the URL of any package <a href="/wp-admin/tools.php?page=migration-tools&blog_id=1&action=prime_mover_create_backup_action">you have created</a>:</label>
		<input type="text" id="custom_package_url" name="custom_package_url" placeholder="Enter package URL">
		<button type="submit" class="button upload-package-button">Upload</button>
	</form>
</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#install_launchkit_package_form').submit(function(e) {
			e.preventDefault();
			var form = $(this);
			var submitButton = form.find('input[type="submit"]');
			var noticeDiv = $('#launchkit_package_notice');
			submitButton.val('Installing...').prop('disabled', true);
			noticeDiv.hide();

			$.post(ajaxurl, {
				action: 'get_prime_package',
				security: '<?php echo wp_create_nonce("get_prime_package_nonce"); ?>'
			}, function(response) {
				if (response.success) {
					// Package downloaded successfully
					submitButton.val('Base LaunchKit Package Installed');
					noticeDiv.html('<div class="notice notice-success"><p>Base LaunchKit package downloaded and installed successfully.</p></div>').show();
				} else {
					// Error occurred while downloading the package
					submitButton.val('Install Base LaunchKit Package').prop('disabled', false);
					noticeDiv.html('<div class="notice notice-error"><p>Error downloading Base LaunchKit package: ' + response.data.message + '</p></div>').show();
				}
			}).fail(function(xhr, status, error) {
				submitButton.val('Install Base LaunchKit Package').prop('disabled', false);
				noticeDiv.html('<div class="notice notice-error"><p>An error occurred while downloading the Base LaunchKit package: ' + error + '</p></div>').show();
			});
		});

		$('.upload-package-button').click(function(e) {
			e.preventDefault();
			var button = $(this);
			var packageUrl = button.data('package-url');
			if (!packageUrl) {
				packageUrl = $('#custom_package_url').val().trim();
				if (!packageUrl) {
					alert('Please enter a valid package URL.');
					return;
				}
			}
			button.prop('disabled', true).text('Uploading...');

			$.post(ajaxurl, {
				action: 'upload_package_from_url',
				package_url: packageUrl,
				security: '<?php echo wp_create_nonce("upload_package_from_url_nonce"); ?>'
			}, function(response) {
				if (response.success) {
					button.prop('disabled', false).text('Uploaded');
					$('#launchkit_package_notice').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
				} else {
					button.prop('disabled', false).text('Upload');
					$('#launchkit_package_notice').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
				}
			}).fail(function(xhr, status, error) {
				button.prop('disabled', false).text('Upload');
				$('#launchkit_package_notice').html('<div class="notice notice-error"><p>An error occurred while uploading the package: ' + error + '</p></div>').show();
			});
		});
	});
</script>
<style>
	.package-selection-row {
		display: flex;
		justify-content: left;
		margin-bottom: 20px;
	}

	.package-selection-column {
		background-color: #ffffff;
		border: 2px solid #cccccc;
		border-radius: 5px;
		padding: 10px;
		margin: 0 10px;
		text-align: center;
		width: 300px;
	}

	.package-image-wrapper {
		height: auto;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-bottom:10px;
	}

	.package-image {
		max-width: 100%;
		max-height: 100%;
	}

	.button.upload-package-button {
		margin-top:0px;
	}
</style>
<?php
	}

	// File upload handling function
	public function lk_handle_wprime_upload() {
		// Check if the file was uploaded without errors
		if (isset($_FILES['wprime_file']) && $_FILES['wprime_file']['error'] === UPLOAD_ERR_OK) {
			$upload_dir = ABSPATH . 'wp-content/uploads/prime-mover-export-files/1/';
			$upload_file = $upload_dir . basename($_FILES['wprime_file']['name']);

			// Move the uploaded file to the target directory
			if (move_uploaded_file($_FILES['wprime_file']['tmp_name'], $upload_file)) {
				echo '<div class="notice notice-success"><p>File uploaded successfully.</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>Error uploading file. Please try again.</p></div>';
			}
		} else {
			echo '<div class="notice notice-error"><p>No file was uploaded or an error occurred during the upload.</p></div>';
		}
	}

	// Function to upload package from URL
	public function upload_package_from_url_callback() {
		check_ajax_referer('upload_package_from_url_nonce', 'security');

		$package_url = isset($_POST['package_url']) ? sanitize_text_field($_POST['package_url']) : '';
		if (empty($package_url)) {
			wp_send_json_error(['message' => 'No package URL provided.']);
		}

		$upload_dir = ABSPATH . 'wp-content/uploads/prime-mover-export-files/1/';
		$upload_file = $upload_dir . basename(parse_url($package_url, PHP_URL_PATH));

		$response = wp_remote_get($package_url);
		if (is_wp_error($response)) {
			wp_send_json_error(['message' => 'Error downloading file: ' . $response->get_error_message()]);
		}

		$file_contents = wp_remote_retrieve_body($response);
		if (file_put_contents($upload_file, $file_contents)) {
			wp_send_json_success(['message' => 'Package uploaded successfully.']);
		} else {
			wp_send_json_error(['message' => 'Error saving file to directory.']);
		}
	}
	//add_action('wp_ajax_upload_package_from_url', 'upload_package_from_url_callback');


	public function get_prime_function() {
		$user_data = get_transient('lk_user_data');

		if ($user_data && isset($user_data['launchkit_package_url'])) {
			$remote_file_url = $user_data['launchkit_package_url'];
			// The target directory for storing the .wprime file.
			$local_dir = ABSPATH . 'wp-content/uploads/prime-mover-export-files/1/';

			// Ensure the directory exists, create it if it does not.
			if (!file_exists($local_dir)) {
				wp_mkdir_p($local_dir);
			}

			// Full path for the downloaded file.
			$local_file_path = $local_dir . basename($remote_file_url);

			// Attempt to download the file.
			$response = wp_remote_get($remote_file_url);
			if (is_wp_error($response)) {
				return ['success' => false, 'message' => 'Error downloading file: ' . $response->get_error_message()];
			}

			// Save the file contents.
			$file_contents = wp_remote_retrieve_body($response);
			if (file_put_contents($local_file_path, $file_contents)) {
				return ['success' => true, 'message' => 'File successfully downloaded to ' . $local_file_path];
			} else {
				return ['success' => false, 'message' => 'Error saving file to directory.'];
			}
		} else {
			return ['success' => false, 'message' => 'Access denied. You do not have permission to download LaunchKit packages.'];
		}
	}

	// AJAX callback for get_prime_function()
	public function get_prime_package_callback() {
		check_ajax_referer('get_prime_package_nonce', 'security');

		$result = get_prime_function();

		if ($result['success']) {
			wp_send_json_success(['message' => $result['message']]);
		} else {
			wp_send_json_error(['message' => $result['message']]);
		}
	}
	//add_action('wp_ajax_get_prime_package', 'get_prime_package_callback');


	public function check_plugin_updates_callback() {
		check_ajax_referer('check_plugin_updates_nonce', 'security');

		// Force WordPress to check for plugin updates
		wp_update_plugins();

		$plugins = get_plugins();
		$updates = get_site_transient('update_plugins');

		if (!empty($updates->response)) {
			foreach ($updates->response as $plugin_file => $plugin_data) {
				$plugin_name = isset($plugins[$plugin_file]['Name']) ? $plugins[$plugin_file]['Name'] : $plugin_file;
				echo '<li>' . esc_html($plugin_name) . ' - Version ' . esc_html($plugin_data->new_version) . '</li>';
			}
		} else {
			echo '<li>No plugin updates available at this time</li>';
		}

		wp_die();
	}


	// save
} // instantiates class
new WPLKInstaller;