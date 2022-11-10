<?php
/**
 * Yachtino Shiplisting WordPress Plugin.
 * @author      Christian Hinz <christian@milkycode.com>
 * @category    Milkycode
 * @package     shiplisting
 * @copyright   Copyright (c) 2022 milkycode GmbH (https://www.milkycode.com)
 */

class Shiplisting_Public
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version The version of this plugin.
     *
     * @since    1.0.0
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Shiplisting_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Shiplisting_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/shiplisting-public.css', array(), $this->version, 'all');

    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Shiplisting_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Shiplisting_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

//        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/shiplisting-public.js', array( 'jquery' ), $this->version, false );
//        wp_localize_script( $this->plugin_name, 'my_ajax_object',
//                            array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
    }

}