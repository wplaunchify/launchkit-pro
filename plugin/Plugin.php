<?php
/**
 *
 * @package GitHubUpdaterDemo
 */
    
class Plugin
{
    /**
     * Create and configure new Updater to keep plugin updated.
     *
     * @param string $file The main plugin file path.
     */
    public function __construct($file)
    {
        // Instantiate GitHubUpdater directly, assuming it's in the same (global) namespace
        $updater = new GitHubUpdater($file);
        $updater->setBranch('main')
                ->setPluginIcon('assets/images/icon-256x256.png')
                ->setPluginBannerSmall('assets/images/banner-772x250.gif')
                ->setPluginBannerLarge('assets/images/banner-1544x500.gif')
                ->setChangelog('readme.txt')
                ->add();
    }
}
