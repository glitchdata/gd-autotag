<?php
namespace WpPlugin\Admin;

class Admin
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function enqueue_admin_assets(): void
    {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        wp_enqueue_style('wp-plugin-admin', plugin_dir_url($this->file) . 'assets/css/admin' . $suffix . '.css', [], WP_PLUGIN_VERSION);
        wp_enqueue_script('wp-plugin-admin', plugin_dir_url($this->file) . 'assets/js/admin.js', ['jquery'], WP_PLUGIN_VERSION, true);
    }

    public function add_admin_menu(): void
    {
        add_menu_page('wp_plugin', 'wp_plugin', 'manage_options', 'wp-plugin', function () {
            echo '<div class="wrap"><h1>wp_plugin</h1><p>Welcome to the admin page.</p></div>';
        });
    }
}
