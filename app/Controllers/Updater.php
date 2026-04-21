<?php
namespace Meowtable\Controllers;

class Updater {

    private $user;
    private $repo;
    private $branch;
    private $plugin_slug;
    private $plugin_file;
    private $github_api_url;

    public function __construct($user, $repo, $branch) {
        $this->user = $user;
        $this->repo = $repo;
        $this->branch = $branch;
        $this->plugin_slug = 'meowtable/meowtable.php';
        $this->plugin_file = MEOWTABLE_PLUGIN_FILE;
        $this->github_api_url = "https://api.github.com/repos/{$this->user}/{$this->repo}";
    }

    public function init() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'rename_github_extracted_folder'], 10, 4);
        add_action('upgrader_process_complete', [$this, 'update_completed'], 10, 2);
        add_filter('plugin_action_links_' . $this->plugin_slug, [$this, 'add_action_links']);
        add_action('admin_init', [$this, 'manual_update_check']);
    }

    public function add_action_links($links) {
        $check_url = wp_nonce_url(admin_url('plugins.php?meowtable_check_update=1'), 'meowtable_check_update');
        $links[] = '<a href="' . esc_url($check_url) . '">Check for Updates</a>';
        return $links;
    }

    public function manual_update_check() {
        if (isset($_GET['meowtable_check_update']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'meowtable_check_update')) {
            delete_transient('meowtable_latest_commit_check');
            wp_clean_plugins_cache(true); // forces WP to refresh transient next load
            wp_redirect(admin_url('plugins.php'));
            exit;
        }
    }

    private function get_latest_commit() {
        $transient_key = 'meowtable_latest_commit_check';
        $cached = get_transient($transient_key);

        if ($cached !== false) {
            return $cached;
        }

        $url = "{$this->github_api_url}/commits/{$this->branch}";
        $response = wp_remote_get($url, [
            'headers' => ['Accept' => 'application/vnd.github.v3+json'],
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!empty($data->sha)) {
            $commit_info = [
                'sha' => $data->sha,
                'message' => $data->commit->message,
                'date' => $data->commit->author->date
            ];
            set_transient($transient_key, $commit_info, 12 * HOUR_IN_SECONDS);
            return $commit_info;
        }

        return false;
    }

    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $latest_commit = $this->get_latest_commit();
        if (!$latest_commit) {
            return $transient;
        }

        $current_commit = get_option('meowtable_current_commit', '');

        // If local commit is different from remote commit, push update!
        if ($current_commit !== $latest_commit['sha']) {
            $plugin_data = get_plugin_data($this->plugin_file);
            // Construct a fake version string out of the small SHA so WP registers it as a change
            $short_sha = substr($latest_commit['sha'], 0, 7);
            
            $update = new \stdClass();
            $update->slug = 'meowtable';
            $update->plugin = $this->plugin_slug;
            $update->new_version = $plugin_data['Version'] . '-' . $short_sha;
            $update->url = "https://github.com/{$this->user}/{$this->repo}";
            $update->package = "https://github.com/{$this->user}/{$this->repo}/archive/refs/heads/{$this->branch}.zip";

            $transient->response[$this->plugin_slug] = $update;
        }

        return $transient;
    }

    public function plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information' || empty($response->slug) || $response->slug !== 'meowtable') {
            return $false;
        }

        $latest_commit = $this->get_latest_commit();
        $plugin_data = get_plugin_data($this->plugin_file);

        $response = new \stdClass();
        $response->name = $plugin_data['Name'];
        $response->slug = 'meowtable';
        $response->version = $latest_commit ? $plugin_data['Version'] . '-' . substr($latest_commit['sha'], 0, 7) : $plugin_data['Version'];
        $response->author = $plugin_data['Author'];
        $response->homepage = "https://github.com/{$this->user}/{$this->repo}";
        $response->sections = [
            'description' => $plugin_data['Description'],
            'changelog' => 'Latest Commit: ' . ($latest_commit ? esc_html($latest_commit['message']) : 'Unknown')
        ];

        if ($latest_commit) {
            $response->download_link = "https://github.com/{$this->user}/{$this->repo}/archive/refs/heads/{$this->branch}.zip";
        }

        return $response;
    }

    public function rename_github_extracted_folder($source, $remote_source, $upgrader, $hook_extra = null) {
        global $wp_filesystem;

        // Is it part of an update or install?
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_slug) {
            // Because github branches become e.g. meowtable-main we need to change it
            $extracted_dir = basename($source);
            $expected_dir = 'meowtable';

            if ($extracted_dir !== $expected_dir) {
                $new_source = trailingslashit($remote_source) . $expected_dir;
                if ($wp_filesystem->move($source, $new_source, true)) {
                    return trailingslashit($new_source);
                }
            }
        }
        return $source;
    }

    public function update_completed($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin' && !empty($options['plugins'])) {
            if (in_array($this->plugin_slug, $options['plugins'])) {
                $latest_commit = $this->get_latest_commit();
                if ($latest_commit) {
                    update_option('meowtable_current_commit', $latest_commit['sha']);
                }
            }
        }
    }
}
