<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package wp_plugin
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clean up options, custom tables, etc. Example:
delete_option('wp_plugin_options');
