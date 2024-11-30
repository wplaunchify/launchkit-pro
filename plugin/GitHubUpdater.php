<?php

class GitHubUpdater
{
    private $file = '.../wp-content/plugins/launchkit/launchkit.php';
    private $gitHubUrl = '';
    private $gitHubPath = '';
    private $gitHubOrg = '';
    private $gitHubRepo = '';
    private $gitHubBranch = 'main';
    private $gitHubAccessToken = '';
    private $pluginFile = '';
    private $pluginDir = '';
    private $pluginFilename = '';
    private $pluginSlug = '';
    private $pluginUrl = '';
    private $pluginVersion = '';
    private $pluginIcon = '';
    private $pluginBannerSmall = '';
    private $pluginBannerLarge = '';
    private $changelog = '';
    private $testedUpTo = '';
    private $enableDebugger = false;

    public function __construct($file)
    {
        $this->file = $file;
        $this->load();
    }

    public function setAccessToken($accessToken)
    {
        $this->gitHubAccessToken = $accessToken;
        return $this;
    }

    public function setBranch($branch)
    {
        $this->gitHubBranch = $branch;
        return $this;
    }

    public function setPluginIcon($file)
    {
        $this->pluginIcon = ltrim($file, '/');
        return $this;
    }

    public function setPluginBannerSmall($file)
    {
        $this->pluginBannerSmall = ltrim($file, '/');
        return $this;
    }

    public function setPluginBannerLarge($file)
    {
        $this->pluginBannerLarge = ltrim($file, '/');
        return $this;
    }

    public function setChangelog($changelog)
    {
        $this->changelog = ltrim($changelog, '/');
        return $this;
    }

    public function enableDebugger()
    {
        $this->enableDebugger = true;
        return $this;
    }

    public function add()
    {
        // Add update hooks to handle plugin updates
        add_filter('site_transient_update_plugins', [$this, 'checkForUpdates']);
        add_filter('plugins_api', [$this, 'getPluginDetails'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'afterInstall'], 10, 3);
    }

    private function load()
    {
        $pluginData = get_file_data(
            $this->file,
            [
                'PluginURI' => 'Plugin URI',
                'Version' => 'Version',
                'TestedUpTo' => 'Tested up to',
                'UpdateURI' => 'Update URI',
            ]
        );

        $pluginUri = $pluginData['PluginURI'] ?? '';
        $updateUri = $pluginData['UpdateURI'] ?? '';
        $version = $pluginData['Version'] ?? '';
        $testedUpTo = $pluginData['TestedUpTo'] ?? '';

        if (!$updateUri || !$version) {
            $this->addAdminNotice('Plugin <b>%s</b> is missing one or more required header fields: <b>Version</b> and/or <b>Update URI</b>.');
            return;
        }

        $this->gitHubUrl = $updateUri;
        $this->gitHubPath = trim(
            wp_parse_url($updateUri, PHP_URL_PATH),
            '/'
        );

        list($this->gitHubOrg, $this->gitHubRepo) = explode('/', $this->gitHubPath);

        $this->pluginFile = str_replace(WP_PLUGIN_DIR . '/', '', $this->file);

        list($this->pluginDir, $this->pluginFilename) = explode('/', $this->pluginFile);

        $this->pluginSlug = sprintf(
            '%s-%s',
            $this->gitHubOrg,
            $this->gitHubRepo
        );

        $this->pluginUrl = $pluginUri;
        $this->pluginVersion = $version;
        $this->testedUpTo = $testedUpTo;
    }

    private function addAdminNotice($message)
    {
        add_action('admin_notices', function () use ($message) {
            $pluginFile = str_replace(WP_PLUGIN_DIR . '/', '', $this->file);
            echo '<div class="notice notice-error">';
            echo '<p>';
            echo sprintf($message, $pluginFile);
            echo '</p>';
            echo '</div>';
        });
    }

    public function checkForUpdates($transient)
    {
        if (!isset($transient->checked)) {
            return $transient;
        }

        $latestRelease = $this->fetchLatestRelease();
        if (!$latestRelease || version_compare($this->pluginVersion, $latestRelease['tag_name'], '>=')) {
            return $transient;
        }

        $transient->response[$this->pluginFile] = (object)[
            'new_version' => $latestRelease['tag_name'],
            'package'     => $latestRelease['zipball_url'],
            'url'         => $this->gitHubUrl,
        ];

        return $transient;
    }

    private function fetchLatestRelease()
    {
        $url = sprintf('https://api.github.com/repos/%s/releases/latest', $this->gitHubPath);
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => $this->gitHubAccessToken ? 'Bearer ' . $this->gitHubAccessToken : '',
                'Accept'        => 'application/vnd.github.v3+json',
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return isset($data['tag_name']) ? $data : false;
    }

    public function getPluginDetails($result, $action, $args)
    {
        if ($action !== 'plugin_information' || $args->slug !== $this->pluginSlug) {
            return $result;
        }

        $result = (object)[
            'name'        => $this->pluginSlug,
            'slug'        => $this->pluginSlug,
            'version'     => $this->pluginVersion,
            'author'      => '<a href="https://github.com/' . $this->gitHubOrg . '">' . $this->gitHubOrg . '</a>',
            'homepage'    => $this->gitHubUrl,
            'requires'    => '5.0',
            'tested'      => $this->testedUpTo,
            'download_link' => $this->fetchLatestRelease()['zipball_url'] ?? '',
            'sections'    => [
                'description' => 'This plugin is updated via GitHub.',
            ],
        ];

        return $result;
    }

    public function afterInstall($response, $hook_extra, $result)
    {
        global $wp_filesystem;
        $pluginFolder = WP_PLUGIN_DIR . '/' . $this->pluginDir;
        $wp_filesystem->move($result['destination'], $pluginFolder);
        $result['destination'] = $pluginFolder;

        if ($this->pluginFile) {
            activate_plugin($this->pluginFile);
        }

        return $response;
    }

    private function log($message)
    {
        if (!$this->enableDebugger || !WP_DEBUG || !WP_DEBUG_LOG) return;

        error_log('[GitHubUpdater] ' . $message);
    }

    private function logStart($method, $hook = '')
    {
        $message = $method . '() ';

        if ($hook) $message = $hook . ' → ' . $message;

        $this->log($message);
        $this->log(str_repeat('-', 50));
    }

    private function logValue($label, $value)
    {
        if (!is_string($value)) {
            $value = var_export($value, true);
        }

        $this->log($label . ': ' . $value);
    }

    private function logFinish($method)
    {
        $this->log('/ ' . $method . '()');
        $this->log('');
    }
}
