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
        add_menu_page(
            'wp_plugin',
            'wp_plugin',
            'manage_options',
            'wp-plugin',
            [$this, 'render_admin_page'],
            'dashicons-admin-plugins'
        );
    }

    public function render_admin_page(): void
    {
        // Check if user can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle manual update check
        $manualCheckPerformed = false;
        if (isset($_POST['check_for_updates']) && check_admin_referer('wp_plugin_check_updates')) {
            $this->force_update_check();
            $manualCheckPerformed = true;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="card">
                <h2>Plugin Information</h2>
                <p><strong>Version:</strong> <?php echo esc_html(WP_PLUGIN_VERSION); ?></p>
                <p><strong>Update Source:</strong> GitHub Releases</p>
                <p><strong>Repository:</strong> <a href="https://github.com/terence/wp-plugin" target="_blank">terence/wp-plugin</a></p>
            </div>

            <div class="card">
                <h2>Update Status</h2>
                <?php if ($manualCheckPerformed): ?>
                    <div class="notice notice-success inline">
                        <p>✓ Checked for updates. Visit the <a href="<?php echo admin_url('plugins.php'); ?>">Plugins page</a> to see if updates are available.</p>
                    </div>
                <?php endif; ?>
                
                <?php $this->display_update_info(); ?>
                
                <form method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('wp_plugin_check_updates'); ?>
                    <button type="submit" name="check_for_updates" class="button button-primary">
                        Check for Updates Now
                    </button>
                </form>
                
                <p style="margin-top: 15px;">
                    <em>Automatic checks run every 12 hours. Last check: <?php echo esc_html($this->get_last_check_time()); ?></em>
                </p>
            </div>

            <div class="card">
                <h2>About Updates</h2>
                <p>This plugin automatically checks for updates from GitHub. When a new release is published, you'll see an update notification in WordPress.</p>
                <p><strong>How to create a release:</strong></p>
                <ol>
                    <li>Update the version number in <code>wp-plugin.php</code></li>
                    <li>Create a new tag: <code>git tag v1.0.1</code></li>
                    <li>Push the tag: <code>git push origin --tags</code></li>
                    <li>Create a GitHub Release with a ZIP asset</li>
                </ol>
            </div>
        </div>
        <?php
    }

    private function display_update_info(): void
    {
        // Get update checker instance if available
        $updateChecker = $this->get_update_checker();
        
        if (!$updateChecker) {
            echo '<div class="notice notice-warning inline"><p>Update checker not initialized.</p></div>';
            return;
        }

        // Try to get update state
        try {
            $state = $updateChecker->getUpdateState();
            if ($state && isset($state->update) && !empty($state->update)) {
                $update = $state->update;
                echo '<div class="notice notice-info inline">';
                echo '<p><strong>Update Available:</strong> Version ' . esc_html($update->version) . '</p>';
                if (!empty($update->download_url)) {
                    echo '<p><a href="' . admin_url('plugins.php') . '" class="button button-primary">Go to Updates</a></p>';
                }
                echo '</div>';
            } else {
                echo '<div class="notice notice-success inline"><p>✓ Plugin is up to date.</p></div>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-warning inline"><p>Unable to check update status.</p></div>';
        }
    }

    private function force_update_check(): void
    {
        $updateChecker = $this->get_update_checker();
        if ($updateChecker && method_exists($updateChecker, 'checkForUpdates')) {
            $updateChecker->checkForUpdates();
        }
    }

    private function get_last_check_time(): string
    {
        $updateChecker = $this->get_update_checker();
        if (!$updateChecker) {
            return 'Never';
        }

        try {
            $state = $updateChecker->getUpdateState();
            if ($state && isset($state->lastCheck) && $state->lastCheck > 0) {
                return human_time_diff($state->lastCheck) . ' ago';
            }
        } catch (Exception $e) {
            // Ignore
        }

        return 'Never';
    }

    private function get_update_checker()
    {
        // Access global update checker instance if stored
        global $wp_plugin_update_checker;
        return $wp_plugin_update_checker ?? null;
    }
}
