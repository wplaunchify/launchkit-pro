<?php
/*since 2.11 */
class GitHubUpdater
{
    private $file;
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
        $this->changelog = $changelog;
        return $this;
    }

    public function enableDebugger()
    {
        $this->enableDebugger = true;
        return $this;
    }

    public function add()
    {
        $this->buildPluginDetailsResult();
        $this->checkPluginUpdates();
        $this->prepareHttpRequestArgs();
        $this->moveUpdatedPlugin();
    }

    private function load()
    {
        // Plugin data loading logic here
    }

    private function buildPluginDetailsResult()
    {
        add_filter('plugins_api', [$this, '_buildPluginDetailsResult'], 10, 3);
    }

    public function _buildPluginDetailsResult($result, $action, $args)
    {
        if ($action !== 'plugin_information' || $args->slug !== $this->pluginSlug) return $result;
        // Populate result logic
        return (object) $result;
    }

    private function checkPluginUpdates()
    {
        add_filter('update_plugins_github.com', [$this, '_checkPluginUpdates'], 10, 3);
    }

    public function _checkPluginUpdates($update, $data, $file)
    {
        if ($file !== $this->pluginFile) return $update;
        // Check updates logic here
        return $update;
    }

    private function prepareHttpRequestArgs()
    {
        add_filter('http_request_args', [$this, '_prepareHttpRequestArgs'], 10, 2);
    }

    public function _prepareHttpRequestArgs($args, $url)
    {
        // Determine if the URL matches the expected private or public GitHub ZIP file URL
        $zipFileUrl = $this->gitHubAccessToken ? $this->getPrivateRemotePluginZipFile() : $this->getPublicRemotePluginZipFile();
        
        if ($url !== $zipFileUrl) return $args;

        // Include GitHub access token if private and set appropriate headers
        if ($this->gitHubAccessToken) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->gitHubAccessToken;
            $args['headers']['Accept'] = 'application/vnd.github+json';
        }

        return $args;
    }

    private function moveUpdatedPlugin()
    {
        add_filter('upgrader_install_package_result', [$this, '_moveUpdatedPlugin'], 10, 2);
    }

    public function _moveUpdatedPlugin($result, $options)
    {
        if (!isset($options['plugin']) || $options['plugin'] !== $this->pluginFile) {
            return $result;
        }
    
        // Logic for moving updated plugin to its correct location
        return $result;
    }

    /**
     * Get path to private remote plugin ZIP file.
     *
     * @return string The GitHub API endpoint to retrieve a private repository ZIP file.
     */
    private function getPrivateRemotePluginZipFile()
    {
        return sprintf(
            'https://api.github.com/repos/%s/zipball/%s',
            $this->gitHubPath,
            $this->gitHubBranch
        );
    }

    /**
     * Get path to public remote plugin ZIP file.
     *
     * @return string The URL to retrieve a ZIP file from a public GitHub repository.
     */
    private function getPublicRemotePluginZipFile()
    {
        return sprintf(
            'https://github.com/%s/archive/refs/heads/%s.zip',
            $this->gitHubPath,
            $this->gitHubBranch
        );
    }
}
