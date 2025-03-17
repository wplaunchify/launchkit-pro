<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * WPLKInstaller Class
 *
 * @since 2.11.5
 */
class WPLKInstaller {

    const WPLAUNCHIFY_URL = 'https://wplaunchify.com';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Add sub-menus
        add_action('admin_menu', array($this, 'launchkit_installer_menu'));
        add_action('admin_menu', array($this, 'launchkit_packages_menu'));

        // callbacks
        add_action('wp_ajax_check_for_updates', array($this, 'check_for_updates_callback'));
        add_action('wp_ajax_install_prime_mover', array($this, 'install_prime_mover_callback'));
        add_action('in_admin_header', array($this, 'launchkit_banner_on_prime_mover_backup_menu'));
        add_action('wp_ajax_upload_package_from_url', array($this, 'upload_package_from_url_callback'));
        add_action('wp_ajax_get_prime_package', array($this, 'get_prime_package_callback'));
        add_action('admin_menu', array($this, 'get_prime_submenu'));

        // Skip prime mover activation script
        add_action('admin_footer', array($this, 'skip_prime_mover_activation_script'));

        add_action('wp_ajax_install_kadence_theme', array($this, 'install_kadence_theme_callback'));
        add_action('wp_ajax_install_base_launchkit', array($this, 'install_base_launchkit_callback'));

        add_action('wp_ajax_install_plugin', array($this, 'install_plugin_callback'));
        add_action('wp_ajax_check_plugin_updates', array($this, 'check_plugin_updates_callback'));
    }

    /**
     * Add the Installer Submenu
     */
    public function launchkit_installer_menu() {
        $parent_slug = 'wplk'; // The slug of the LaunchKit plugin's main menu
        $page_slug   = 'launchkit-installer'; // The slug for the submenu page
        $capability  = 'manage_options';

        add_submenu_page(
            $parent_slug,
            __('LaunchKit Installer', 'launchkit-installer'),
            __('LaunchKit Installer', 'launchkit-installer'),
            $capability,
            $page_slug,
            array($this, 'lk_get_meta_plugin_installer_page')
        );

        // Hide the submenu visually
        add_action('admin_head', array($this, 'launchkit_hide_installer_submenu_item'));
    }

    /**
     * Hide the empty space of the hidden Installer submenu item
     */
    public function launchkit_hide_installer_submenu_item() {
        global $submenu;
        $parent_slug = 'wplk';
        $page_slug   = 'launchkit-installer';

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

    /**
     * Add the Packages Submenu
     */
    public function launchkit_packages_menu() {
        $parent_slug = 'wplk'; // The slug of the LaunchKit plugin's main menu
        $page_slug   = 'launchkit-packages'; // The slug for the submenu page
        $capability  = 'manage_options';

        add_submenu_page(
            $parent_slug,
            __('LaunchKit Packages', 'launchkit-packages'),
            __('LaunchKit Packages', 'launchkit-packages'),
            $capability,
            $page_slug,
            array($this, 'get_prime_page')
        );

        // Hide the submenu visually
        add_action('admin_head', array($this, 'launchkit_hide_packages_submenu_item'));
    }

    /**
     * Hide the empty space of the hidden Packages submenu item
     */
    public function launchkit_hide_packages_submenu_item() {
        global $submenu;
        $parent_slug = 'wplk';
        $page_slug   = 'launchkit-packages';

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

    /**
     * Main "Installer" / Installer Page
     */
    public function lk_get_meta_plugin_installer_page() {
        // Check transients set by your "header login" code
        $logged_in = get_transient('lk_logged_in');
        $user_data = get_transient('lk_user_data');

        // Standard WP admin wrap
      //  echo '<div class="wrap">';
        echo '<h1>Software Bundle Plugin Installer</h1>';

        // If not logged in
        if (! $logged_in) {
            echo '<p>Unlock All Features By Subscribing To WPLaunchify.com <a href="https://wplaunchify.com/#pricing" target="_blank">Minute Launch Software Bundle</a></p>';
            echo '<div class="wplk-notice"><p>You are logged-out. Please log in via the header using your WPLaunchify.com username and password.</p></div>';
            echo '</div>'; // .wrap
            return;
        }

        // If logged in, check if can_access_launchkit
        $can_access_launchkit = ! empty($user_data['can_access_launchkit']);
        $first_name           = isset($user_data['first_name']) ? $user_data['first_name'] : '';

        if (! $can_access_launchkit) {
            // Logged in but no membership
            echo '<div class="notice notice-error"><p>';
            echo 'You are logged in, but your account does not have Software Bundle access. ';
            echo 'Please check that you have a current Subscription with <a href="https://wplaunchify.com" target="_blank">WPLaunchify</a>.';
            echo '</p></div>';
            echo '</div>'; // .wrap
            return;
        }

        // Logged in + can_access_launchkit = true
        echo '<p>Hi ' . esc_html($first_name) . ', you are logged in with Software Bundle.</p>';

        // Check if Prime Mover plugin is installed
        $prime_mover_installed = is_plugin_active('prime-mover/prime-mover.php');
        ?>
        <p>
            <button type="button" class="button button-primary" id="install_kadence_theme">Install Kadence Theme</button>
            <?php if (! $prime_mover_installed) : ?>
                <button type="button" class="button button-primary" id="install_prime_mover">Install Prime Mover</button>
            <?php else : ?>
                <button type="button" class="button button-primary" id="install_base_launchkit_package">Install Base LaunchKit Package</button>
                <button type="button" class="button button-primary" id="view_packages">View Packages</button>
            <?php endif; ?>
        </p>
        <?php
        // Display plugin updates & table
        $this->fetch_latest_launchkit_plugins();
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Kadence
            $('#install_kadence_theme').click(function() {
                var button = $(this);
                button.text('Installing...').prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'install_kadence_theme',
                    security: '<?php echo wp_create_nonce("install_kadence_theme_nonce"); ?>'
                }, function() {
                    button.text('Kadence Theme Installed').prop('disabled', true);
                });
            });

            // Prime Mover
            $('#install_prime_mover').click(function() {
                var button = $(this);
                button.text('Installing...').prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'install_prime_mover',
                    security: '<?php echo wp_create_nonce("install_prime_mover_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        button.text('Prime Mover Installed').prop('disabled', true);
                        location.reload();
                    } else {
                        button.text('Installation Failed').prop('disabled', false);
                    }
                }).fail(function() {
                    button.text('Installation Failed').prop('disabled', false);
                });
            });

            // Base LaunchKit
            $('#install_base_launchkit_package').click(function() {
                var button = $(this);
                button.text('Installing...').prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'get_prime_package',
                    security: '<?php echo wp_create_nonce("get_prime_package_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        button.text('Base LaunchKit Package Installed').prop('disabled', true);
                    } else {
                        button.text('Installation Failed').prop('disabled', false);
                    }
                }).fail(function() {
                    button.text('Installation Failed').prop('disabled', false);
                });
            });

            // View Packages
            $('#view_packages').click(function() {
                window.location.href = '<?php echo admin_url('admin.php?page=migration-panel-backup-menu'); ?>';
            });
        });
        </script>
        <?php
       // echo '</div>'; // .wrap
    }

    /**
     * Download & display plugin updates from remote LaunchKit zip
     */
    public function fetch_latest_launchkit_plugins() {
        $last_download_timestamp = get_option('lk_last_download_timestamp', 0);
        $current_timestamp       = time();
        $cache_expiration        = 12 * HOUR_IN_SECONDS; // 12 hours

        $user_data = get_transient('lk_user_data');
        if (! $user_data || empty($user_data['launchkit_plugins_url'])) {
            echo "<p>Error: Unable to retrieve LaunchKit plugins URL. Please log in again.</p>";
            return;
        }

        $bundle_url = $user_data['launchkit_plugins_url'];
        $upload_dir = wp_upload_dir();
        $target_dir = trailingslashit($upload_dir['basedir']) . 'launchkit-updates';

        // Re-download if cache expired
        if ($current_timestamp - $last_download_timestamp >= $cache_expiration) {
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
                echo "<p>Latest LaunchKit downloaded and extracted successfully.</p>";
                update_option('lk_plugin_last_update', time());
                update_option('lk_last_download_timestamp', $current_timestamp);
            } else {
                echo "<p>Failed to extract LaunchKit plugins.</p>";
                return;
            }
        }

        // Show the plugin table
        $this->lk_display_plugins_table($target_dir, $upload_dir);
    }

    /**
     * Display "Plugins Being Tested" and the plugin table
     */
    public function display_plugin_updates() {
        echo '<div class="plugin-updates">';
        echo '<h3 style="display:inline-block">Plugins Being Tested</h3><br/>';
        echo '<span>Plugins with available updates that we need to test before releasing to the Software Bundle.</span>';
        echo '<a href="#" id="check_plugin_updates" style="display: inline-block; margin-left: 10px;">Check Plugins For Updates</a>';
        echo '<ul id="plugin_update_list"></ul>';
        echo '</div>';
        echo '<style>
            .plugin-updates {
                margin-bottom: 20px; 
                border: 1px dashed #DEDEDD;
                background: #f6f6f6;
                border-radius: 10px;
                padding: 0 10px;
            }
            .plugin-updates h3 { margin-bottom: 10px; }
            .plugin-updates ul { margin-left: 20px; }
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
                        $("#check_plugin_updates").text("Check Plugins For Updates").css("pointer-events", "auto");
                    });
                }
                updatePluginList(); // initial load
                $("#check_plugin_updates").click(function(e) {
                    e.preventDefault();
                    updatePluginList();
                });
            });
        </script>';
    }

    /**
     * Show the table of "Plugins Available To Update"
     */
    public function lk_display_plugins_table($target_dir, $upload_dir) {
        $last_download_timestamp = get_option('lk_last_download_timestamp', 0);
        date_default_timezone_set('America/Chicago');
        $last_updated_date = $last_download_timestamp > 0 ? date('F j, Y \a\t g:ia', $last_download_timestamp) : 'Never';

        // "Plugins Being Tested"
        $this->display_plugin_updates();

        echo '<h3 style="display:inline-block;">Plugins Available To Update</h3>';
        echo '<span style="margin-left: 10px;">Last Updated: ' . $last_updated_date . ' (Chicago Time)</span>';
        echo '<a href="#" id="check_for_updates" style="display:inline-block; margin-left:10px;">Update Software Bundle</a>';
        echo '<div style="clear:both; margin-bottom:10px;"></div>';
        echo '<button type="button" id="install_selected" class="button button-primary">Install Selected Plugins</button>';

        // Cleanup
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

                        var allDeleted = response.data.deleted.concat(response.data.manual);
                        var deletedList = '<p><?php _e("Deleted Plugins:", "lk"); ?></p><ul>';
                        allDeleted.forEach(function(plugin) {
                            deletedList += '<li>' + plugin + '</li>';
                        });
                        deletedList += '</ul>';

                        document.getElementById('launchkit-summary').innerHTML = deletedList;

                        var refreshLink = '<br/><a href="#" id="launchkit-refresh-link" style="color:red;text-decoration:underline;"><?php _e("Click To Refresh Plugin List", "lk"); ?></a>';
                        document.getElementById('launchkit-summary').innerHTML += refreshLink;

                        document.getElementById('launchkit-refresh-link').addEventListener('click', function(e) {
                            e.preventDefault();
                            window.location.reload();
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
        echo '<table class="wp-list-table widefat fixed striped" style="width:100%; max-width:800px;" id="plugin-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="width:30px;" data-column-name="Select"><input type="checkbox" id="select_all" /></th>';
        echo '<th class="plugin-file-column sortable" data-sort="plugin-file" style="width:300px;" data-column-name="Plugin File"><span>Plugin File (Click To Download)</span><span class="sorting-indicator">&#9660;</span></th>';
        echo '<th class="sortable" data-sort="last-update" data-column-name="Last Update"><span>Last Update</span><span class="sorting-indicator">&#9660;</span></th>';
        echo '<th style="width:100px;" data-column-name="Status">Status</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $plugin_files = glob("$target_dir/*.zip");
        if ($plugin_files) {
            foreach ($plugin_files as $file) {
                $plugin_name  = basename($file, ".zip");
                $plugin_slug  = sanitize_title($plugin_name);
                $is_installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug);
                $status       = $is_installed ? 'Installed' : 'Not Installed';

                $action_button = $is_installed
                    ? 'Installed'
                    : "<button type='button' class='button install_button' data-url='" . esc_url(trailingslashit($upload_dir['baseurl']) . "launchkit-updates/" . basename($file)) . "'>Install</button>";

                $last_modified = date('Y-m-d', filemtime($file));
                $download_link = esc_url(trailingslashit($upload_dir['baseurl']) . "launchkit-updates/" . basename($file));

                echo "<tr>";
                echo "<td><input type='checkbox' class='plugin_checkbox' data-url='$download_link' data-slug='$plugin_slug'></td>";
                echo "<td data-plugin-file='$plugin_name'><a href='$download_link' download>$plugin_name.zip</a></td>";
                echo "<td data-last-update='$last_modified'>" . date('F j, Y', strtotime($last_modified)) . "</td>";
                echo "<td class='plugin_status'>$action_button</td>";
                echo "</tr>";
            }
        } else {
            echo '<tr><td colspan="4">No plugin files found. Click "Check For Updates" to download them.</td></tr>';
        }

        echo '</tbody></table>';
        echo '</form>';
        echo '</div>';

        $ajax_nonce = wp_create_nonce('plugin_installer_nonce');
        ?>
        <script type="text/javascript">
        (function($){
            // Single plugin install
            $(document).on('click', '.install_button', function() {
                var button = $(this);
                var plugin_url = button.data('url');
                var status_element = button.closest('tr').find('.plugin_status');

                status_element.html('Installing... <img src="<?php echo includes_url('images/spinner.gif'); ?>" alt="Installing...">');

                $.post(ajaxurl, {
                    action: 'install_plugin',
                    plugin_url: plugin_url,
                    security: '<?php echo $ajax_nonce; ?>'
                }, function(response) {
                    if (response.success) {
                        status_element.html('Installed');
                    } else {
                        status_element.html('Installation failed: ' + response.data);
                    }
                });
            });

            // Bulk install
            $('#install_selected').click(function() {
                $('.plugin_checkbox').each(function() {
                    if ($(this).is(':checked')) {
                        $(this).closest('tr').find('.install_button').trigger('click');
                    }
                });
            });

            // Select all
            $('#select_all').change(function() {
                $('.plugin_checkbox').prop('checked', this.checked);
            });

            // Sorting
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
                        return (sortOrder === 'asc') ? aValue - bValue : bValue - aValue;
                    } else {
                        aValue = aValue ? aValue.toString() : '';
                        bValue = bValue ? bValue.toString() : '';
                        return (sortOrder === 'asc')
                            ? aValue.localeCompare(bValue)
                            : bValue.localeCompare(aValue);
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

            // Check for updates
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
        })(jQuery);
        </script>
        <style>
            /* Responsive table */
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
            }
            .sortable { cursor: pointer; }
            .sortable span { display: inline-block; vertical-align: middle; }
            .sorting-indicator {
                margin-left: 5px;
                margin-top:-15px;
                color: #000;
            }
            .sortable.asc .sorting-indicator,
            .sortable.desc .sorting-indicator {
                color: #333;
                margin-top:-15px;
            }
        </style>
        <?php
    }

    /**
     * AJAX: check_for_updates
     */
    public function check_for_updates_callback() {
        check_ajax_referer('check_for_updates_nonce', 'security');

        $user_data = get_transient('lk_user_data');
        if (! $user_data || empty($user_data['launchkit_plugins_url'])) {
            wp_send_json_error('Unable to retrieve LaunchKit plugins URL. Please log in again.');
        }

        $bundle_url = $user_data['launchkit_plugins_url'];
        $upload_dir = wp_upload_dir();
        $target_dir = trailingslashit($upload_dir['basedir']) . 'launchkit-updates';

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

    /**
     * AJAX: install_plugin
     */
    public function install_plugin_callback() {
        check_ajax_referer('plugin_installer_nonce', 'security');

        $plugin_url = isset($_POST['plugin_url']) ? sanitize_text_field($_POST['plugin_url']) : '';
        if (empty($plugin_url)) {
            wp_send_json_error('No plugin URL provided.');
        }

        $upload_dir            = wp_upload_dir();
        $launchkit_updates_dir = trailingslashit($upload_dir['basedir']) . 'launchkit-updates/';
        $file_path             = $launchkit_updates_dir . basename($plugin_url);

        if (! file_exists($file_path)) {
            wp_send_json_error('The file does not exist: ' . $file_path);
        }

        if (! is_readable($file_path)) {
            wp_send_json_error('The file is not readable: ' . $file_path);
        }

        WP_Filesystem();
        $plugin_slug = sanitize_title(basename($file_path, '.zip'));
        $plugin_dir  = WP_PLUGIN_DIR . '/' . $plugin_slug;

        // Remove existing folder if any
        if (is_dir($plugin_dir)) {
            $this->delete_directory($plugin_dir);
        }

        $result = unzip_file($file_path, WP_PLUGIN_DIR);
        if (is_wp_error($result)) {
            wp_send_json_error('Installation failed: ' . $result->get_error_message());
        }

        wp_send_json_success('Plugin installed successfully.');
    }

    private function delete_directory($dir) {
        if (! is_dir($dir)) {
            return;
        }
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object !== '.' && $object !== '..') {
                $file = $dir . '/' . $object;
                is_dir($file) ? $this->delete_directory($file) : unlink($file);
            }
        }
        rmdir($dir);
    }

    /**
     * AJAX: install_kadence_theme
     */
    public function install_kadence_theme_callback() {
        check_ajax_referer('install_kadence_theme_nonce', 'security');

        include_once ABSPATH . 'wp-admin/includes/theme-install.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $api = themes_api('theme_information', array('slug' => 'kadence'));
        if (is_wp_error($api)) {
            wp_send_json_error($api->get_error_message());
        }

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Theme_Upgrader($skin);
        $result   = $upgrader->install($api->download_link);

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

    /**
     * AJAX: install_prime_mover
     */
    public function install_prime_mover_callback() {
        check_ajax_referer('install_prime_mover_nonce', 'security');

        if (file_exists(WP_PLUGIN_DIR . '/prime-mover/prime-mover.php')) {
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

            $skin     = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);
            $result   = $upgrader->install($api->download_link);

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

    /**
     * After installing Prime Mover, auto-click the "skip activation" button
     */
    public function skip_prime_mover_activation_script() {
        $current_url = isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : '';
        if ($current_url === '/wp-admin/admin.php?page=migration-panel-settings') {
            ?>
            <script>
            jQuery(document).ready(function($){
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

    /**
     * Submenu for Prime Mover packages
     */
    public function get_prime_submenu() {
        add_submenu_page(
            'lk-get-meta-plugin-installer', // slug of the parent
            'Packages',
            'Packages',
            'manage_options',
            'get-prime',
            array($this, 'get_prime_page')
        );
    }

    /**
     * Show a LaunchKit banner on Prime Mover admin pages
     */
    public function launchkit_banner_on_prime_mover_backup_menu() {
        $current_screen = get_current_screen();
        $current_url    = isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : '';

        $is_prime_mover_page = (
            strpos($current_url, '/wp-admin/admin.php?page=migration-panel') === 0 ||
            strpos($current_url, '/wp-admin/tools.php?page=migration-tools') === 0
        );

        if (
            strpos($current_screen->id, 'prime-mover_page_') === 0 ||
            $current_screen->id === 'tools_page_migration-tools' ||
            $is_prime_mover_page ||
            $current_screen->base === 'toplevel_page_migration-panel-settings'
        ) {
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
                margin-left: -20px;
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

    /**
     * Packages page
     */
    public function get_prime_page() {
        $user_data = get_transient('lk_user_data');

        // Must have can_access_launchkit
        if (!$user_data || empty($user_data['can_access_launchkit'])) {
            ?>
            <div class="wrap">
                <h1>LaunchKit Packages</h1>
                <div class="notice notice-warning"><p>Sorry, please <a href="<?php echo admin_url('admin.php?page=wplk&tab=installer'); ?>">log in with proper credentials</a> to view available packages.</p></div>
            </div>
            <?php
            return;
        }

        $prime_mover_installed = is_plugin_active('prime-mover/prime-mover.php');
        $launchkit_package_url = isset($user_data['launchkit_package_url']) ? $user_data['launchkit_package_url'] : '';
        $package_one_url       = isset($user_data['package_one_url']) ? $user_data['package_one_url'] : '';
        $package_two_url       = isset($user_data['package_two_url']) ? $user_data['package_two_url'] : '';
        $package_three_url     = isset($user_data['package_three_url']) ? $user_data['package_three_url'] : '';

        ?>
        <div class="wrap">
            <h1>Install LaunchKit Packages</h1>
            <div id="launchkit_package_notice" style="display: none;"></div>
            <?php if ($prime_mover_installed) : ?>
                <a class="button" href="<?php echo admin_url('admin.php?page=migration-panel-backup-menu'); ?>">View Your Installed Packages</a>
            <?php endif; ?>

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
                <label for="custom_package_url">
                    Add the URL of any package
                    <a href="/wp-admin/tools.php?page=migration-tools&blog_id=1&action=prime_mover_create_backup_action">you have created</a>:
                </label>
                <input type="text" id="custom_package_url" name="custom_package_url" placeholder="Enter package URL">
                <button type="submit" class="button upload-package-button">Upload</button>
            </form>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
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

    /**
     * AJAX: upload_package_from_url
     */
    public function upload_package_from_url_callback() {
        check_ajax_referer('upload_package_from_url_nonce', 'security');

        $package_url = isset($_POST['package_url']) ? sanitize_text_field($_POST['package_url']) : '';
        if (empty($package_url)) {
            wp_send_json_error(['message' => 'No package URL provided.']);
        }

        $upload_dir  = ABSPATH . 'wp-content/uploads/prime-mover-export-files/1/';
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

    /**
     * The function that actually downloads the base .wprime package
     */
    public function get_prime_function() {
        $user_data = get_transient('lk_user_data');
        if ($user_data && ! empty($user_data['launchkit_package_url'])) {
            $remote_file_url = $user_data['launchkit_package_url'];
            $local_dir       = ABSPATH . 'wp-content/uploads/prime-mover-export-files/1/';
            if (! file_exists($local_dir)) {
                wp_mkdir_p($local_dir);
            }
            $local_file_path = $local_dir . basename($remote_file_url);
            $response        = wp_remote_get($remote_file_url);
            if (is_wp_error($response)) {
                return ['success' => false, 'message' => 'Error downloading file: ' . $response->get_error_message()];
            }
            $file_contents = wp_remote_retrieve_body($response);
            if (file_put_contents($local_file_path, $file_contents)) {
                return ['success' => true, 'message' => 'File successfully downloaded to ' . $local_file_path];
            } else {
                return ['success' => false, 'message' => 'Error saving file to directory.'];
            }
        }
        return ['success' => false, 'message' => 'Access denied. You do not have permission to download LaunchKit packages.'];
    }

    /**
     * AJAX: get_prime_package
     */
    public function get_prime_package_callback() {
        check_ajax_referer('get_prime_package_nonce', 'security');

        $result = $this->get_prime_function();
        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: check_plugin_updates (for "Plugins Being Tested")
     */
    public function check_plugin_updates_callback() {
        check_ajax_referer('check_plugin_updates_nonce', 'security');

        wp_update_plugins();
        $plugins = get_plugins();
        $updates = get_site_transient('update_plugins');

        if (! empty($updates->response)) {
            foreach ($updates->response as $plugin_file => $plugin_data) {
                $plugin_name = isset($plugins[$plugin_file]['Name']) ? $plugins[$plugin_file]['Name'] : $plugin_file;
                echo '<li>' . esc_html($plugin_name) . ' - Version ' . esc_html($plugin_data->new_version) . '</li>';
            }
        } else {
            echo '<li>No plugin updates available at this time</li>';
        }
        wp_die();
    }
}

// Instantiate
new WPLKInstaller();