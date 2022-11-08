<?php
/**
 * Yachtino Shiplisting WordPress Plugin.
 * @author      Christian Hinz <christian@milkycode.com>
 * @category    Milkycode
 * @package     shiplisting
 * @copyright   Copyright (c) 2022 milkycode GmbH (https://www.milkycode.com)
 */

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

class Shiplisting_Activator
{
    private static $do_update = false;

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        global $wpdb;

        $apikey = get_option('shiplisting_api_key');
        $apisiteid = get_option('shiplisting_api_siteid');
        $version = get_option('shiplisting_version');

        if (!$apikey) {
            add_option('shiplisting_api_key', '', '', 'yes');
        }

        if (!$apisiteid) {
            add_option('shiplisting_api_siteid', '', '', 'yes');
        }

        if (!$version) {
            add_option('shiplisting_version', SHIPLISTING_VERSION, '', 'yes');
        } else {
            $before_version = $version;
            if ($before_version != SHIPLISTING_VERSION) {
                update_option('shiplisting_version', SHIPLISTING_VERSION);
                self::$do_update = true;
            }
        }

        if (self::$do_update) {
//            dbDelta('DROP TABLE IF EXISTS `wp_shiplisting_routes`;');
//            dbDelta('DROP TABLE IF EXISTS `wp_shiplisting_filter`;');
//            dbDelta('DROP TABLE IF EXISTS `wp_shiplisting_settings`;');
        }

        $charset_collate = $wpdb->get_charset_collate();
        if (!self::table_exists('wp_shiplisting_routes')) {
            $sql = "CREATE TABLE `wp_shiplisting_routes` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NULL DEFAULT NULL,
	`path` VARCHAR(50) NULL DEFAULT NULL,
	`vars` VARCHAR(500) NULL DEFAULT NULL,
	`arguments` VARCHAR(500) NULL DEFAULT NULL,
	`title` VARCHAR(255) NULL DEFAULT NULL,
	`callback` VARCHAR(255) NULL DEFAULT NULL,
	`adv_filter` VARCHAR(255) NULL DEFAULT NULL,
	`linked_detail_view` INT(11) NULL DEFAULT NULL,
	`language` VARCHAR(15) NULL DEFAULT NULL,
	`added` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
	`updated` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `id` (`id`),
	UNIQUE INDEX `name` (`name`)
) $charset_collate;";
            dbDelta($sql);
        }

        if (!self::table_exists('wp_shiplisting_filter')) {
            $sql = "CREATE TABLE `wp_shiplisting_filter` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NULL DEFAULT NULL,
	`fields_to_display` VARCHAR(500) NULL DEFAULT NULL,
	`fields_to_filter` VARCHAR(500) NULL DEFAULT NULL,
	`added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
) $charset_collate;";
            dbDelta($sql);
        }

        if (!self::table_exists('wp_shiplisting_settings')) {
            $sql = "CREATE TABLE `wp_shiplisting_settings` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`active` INT(11) NULL DEFAULT NULL,
	`maintenance` INT(11) NULL DEFAULT NULL,
	`api_key` VARCHAR (255) NULL DEFAULT NULL,
	`api_site_id` VARCHAR (255) NULL DEFAULT NULL,
	`filtering_standard` INT(11) NULL DEFAULT NULL,
	`filtering_advanced` INT(11) NULL DEFAULT NULL,
	`contact_form` INT(11) NULL DEFAULT NULL,
	`cache_active` INT(11) NULL DEFAULT 0,
	`cache_time` VARCHAR(255) NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
) $charset_collate;";
            dbDelta($sql);

            $sql = "INSERT INTO `wp_shiplisting_settings` (`active`, `maintenance`, `filtering_standard`, `filtering_advanced`, `contact_form`, `cache_active`, `cache_time`) VALUES ('1', '0', '1', '1', '1', '0', '3 minutes');";
            dbDelta($sql);
        } else {
            $rowcount = $wpdb->get_var("SELECT COUNT(*) FROM `wp_shiplisting_settings`");
            if ($rowcount == 0) {
                $sql = "INSERT INTO `wp_shiplisting_settings` (`active`, `maintenance`, `filtering_standard`, `filtering_advanced`, `contact_form`, `cache_active`, `cache_time`) VALUES ('1', '0', '1', '1', '1', '0', '3 minutes');";
                dbDelta($sql);
            }
        }

        if (!self::table_exists('wp_shiplisting_caching')) {
            $sql = "CREATE TABLE `wp_shiplisting_caching` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`hash` VARCHAR(255) NOT NULL DEFAULT '0',
	`data` TEXT NOT NULL,
	`result` TEXT NOT NULL,
	`added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`used` INT(11) NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	INDEX `id` (`id`)
)$charset_collate;";
            dbDelta($sql);
        }
    }

    private static function table_exists($table_name)
    {
        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
            return true;
        } else {
            return false;
        }
    }
}