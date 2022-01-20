<?php
/**
 * Yachtall Shiplisting WordPress Plugin.
 * @author      Stefan Meyer <stefan@milkycode.com>
 * @category    Milkycode
 * @package     shiplisting
 * @copyright   Copyright (c) 2019 milkycode GmbH (https://www.milkycode.com)
 */

class Shiplisting_i18n
{
    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'shiplisting',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}