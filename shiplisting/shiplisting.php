<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://www.milkycode.com
 * @since             1.0.0
 * @package           Shiplisting
 *
 * @wordpress-plugin
 * Plugin Name:       yachtino Shiplisting
 * Plugin URI:        http://www.yachtino.com
 * Description:       Plugin for detailed listing of your yachtino (yachtall, happycharter) offers.
 * Version:           1.8.7
 * Author:            yachtino GmbH
 * Author URI:        http://www.yachtino.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       shiplisting
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.2.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'SHIPLISTING_VERSION', '1.8.7' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-shiplisting-activator.php
 */
function activate_shiplisting() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-shiplisting-activator.php';
	Shiplisting_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-shiplisting-deactivator.php
 */
function deactivate_shiplisting() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-shiplisting-deactivator.php';
	Shiplisting_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_shiplisting' );
register_deactivation_hook( __FILE__, 'deactivate_shiplisting' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__). 'router/wp-router.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-shiplisting.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_shiplisting() {

	$plugin = new Shiplisting();
	$plugin->run();
    $GLOBALS['shiplisting'] = $plugin->api;

}
run_shiplisting();
