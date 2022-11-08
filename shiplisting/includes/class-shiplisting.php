<?php
/**
 * Yachtino Shiplisting WordPress Plugin.
 * @author      Christian Hinz <christian@milkycode.com>
 * @category    Milkycode
 * @package     shiplisting
 * @copyright   Copyright (c) 2022 milkycode GmbH (https://www.milkycode.com)
 */

class Shiplisting
{

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Shiplisting_Loader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;
    public $api;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $plugin_name The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.1.0
     * @access   protected
     * @var      string $version The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        if (defined('SHIPLISTING_VERSION')) {
            $this->version = SHIPLISTING_VERSION;
        }

        $this->plugin_name = 'shiplisting';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Shiplisting_Loader. Orchestrates the hooks of the plugin.
     * - Shiplisting_i18n. Defines internationalization functionality.
     * - Shiplisting_Admin. Defines all hooks for the admin area.
     * - Shiplisting_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-shiplisting-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-shiplisting-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-shiplisting-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-shiplisting-public.php';
        $this->loader = new Shiplisting_Loader();

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-shiplisting-api.php';
        $this->api = new Shiplisting_Api();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Shiplisting_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale()
    {
        $plugin_i18n = new Shiplisting_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all hooks related to the admin area functionality of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new Shiplisting_Admin($this->get_plugin_name(), $this->get_version());
        $plugin_admin->api = $this->api;

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'shiplisting_adminmenu');
        $this->loader->add_filter('site_transient_update_plugins', $plugin_admin, 'shiplisting_pushupdate');
        $this->loader->add_filter('plugins_api', $plugin_admin, 'shiplisting_plugininfo', 1, 3);
        $this->loader->add_action('upgrader_process_complete', $plugin_admin, 'shiplisting_afterupdate');
        $this->loader->add_action('wp_ajax_nopriv_shiplisting_advanced_filters', $plugin_admin->api, 'public_ajax_get_all_filters');
        $this->loader->add_action('wp_ajax_shiplisting_advanced_filters', $plugin_admin->api, 'public_ajax_get_all_filters');
        $this->loader->add_action('wp_ajax_nopriv_shiplisting_get_detail_views', $plugin_admin->api, 'ajax_get_detail_views');
        $this->loader->add_action('wp_ajax_shiplisting_get_detail_views', $plugin_admin->api, 'ajax_get_detail_views');
        $this->loader->add_action('wp_ajax_nopriv_shiplisting_get_api_langues', $plugin_admin->api, 'public_ajax_get_api_langues');
        $this->loader->add_action('wp_ajax_shiplisting_get_api_langues', $plugin_admin->api, 'public_ajax_get_api_langues');
        $this->loader->add_action('wp_ajax_nopriv_shiplisting_route_exists', $plugin_admin->api, 'public_route_exists');
        $this->loader->add_action('wp_ajax_shiplisting_route_exists', $plugin_admin->api, 'public_route_exists');
        $this->loader->add_action('wp_ajax_nopriv_shiplisting_get_api_title_placeholders', $plugin_admin->api, 'public_ajax_get_api_title_placeholders');
        $this->loader->add_action('wp_ajax_shiplisting_get_api_title_placeholders', $plugin_admin->api, 'public_ajax_get_api_title_placeholders');
        $this->loader->add_action('wp_ajax_nopriv_shiplisting_path_exists', $plugin_admin->api, 'public_path_exists');
        $this->loader->add_action('wp_ajax_shiplisting_path_exists', $plugin_admin->api, 'public_path_exists');
        $this->loader->add_action('wp_ajax_nopriv_shiplisting_get_translation', $plugin_admin->api, 'public_get_translation');
        $this->loader->add_action('wp_ajax_shiplisting_get_translation', $plugin_admin->api, 'public_get_translation');
        $this->loader->add_action('wp_ajax_shiplisting_api_init_route', $plugin_admin->api, 'ajax_api_init_route');
    }

    /**
     * Register all hooks related to the public-facing functionality.
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {
        $plugin_public = new Shiplisting_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return    string    The name of the plugin.
     * @since     1.0.0
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return    Shiplisting_Loader    Orchestrates the hooks of the plugin.
     * @since     1.0.0
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return    string    The version number of the plugin.
     * @since     1.1.0
     */
    public function get_version()
    {
        return $this->version;
    }
}