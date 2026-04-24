<?php
namespace Meowtable\Controllers;

class AdminController {

    public static function init() {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        
        // AJAX Endpoints for saving settings
        add_action('wp_ajax_meowtable_save_settings', [__CLASS__, 'save_settings']);
        add_action('wp_ajax_meowtable_delete_table', [__CLASS__, 'delete_table']);
    }

    public static function register_admin_menu() {
        add_menu_page(
            'Meowtable',
            'Meowtable',
            'manage_options',
            'meowtable',
            [__CLASS__, 'render_admin_page'],
            'dashicons-editor-table',
            25
        );
        
        add_submenu_page(
            'meowtable',
            'All Tables',
            'All Tables',
            'manage_options',
            'meowtable',
            [__CLASS__, 'render_admin_page']
        );

        add_submenu_page(
            'meowtable',
            'Add New Table',
            'Add New Table',
            'manage_options',
            'meowtable-add',
            [__CLASS__, 'render_add_page']
        );
    }

    public static function enqueue_admin_assets($hook) {
        if (strpos($hook, 'meowtable') === false) {
            return;
        }
        
        wp_enqueue_script('jquery-ui-sortable');
        
        wp_localize_script('jquery', 'meowtable_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('meowtable_nonce')
        ]);
    }

    public static function render_admin_page() {
        if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
            require_once MEOWTABLE_PLUGIN_DIR . 'app/Views/admin-edit.php';
        } else {
            require_once MEOWTABLE_PLUGIN_DIR . 'app/Views/admin-list.php';
        }
    }

    public static function render_add_page() {
        require_once MEOWTABLE_PLUGIN_DIR . 'app/Views/admin-add.php';
    }

    public static function save_settings() {
        check_ajax_referer('meowtable_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'meowtables';
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $title = sanitize_text_field($_POST['title']);
        $settings = isset($_POST['settings']) ? stripslashes($_POST['settings']) : '{}'; // JSON string

        if ($id > 0) {
            $wpdb->update($table_name, [
                'title' => $title,
                'settings' => $settings
            ], ['id' => $id]);
            wp_send_json_success(['id' => $id, 'message' => 'Updated successfully']);
        } else {
            $wpdb->insert($table_name, [
                'title' => $title,
                'settings' => $settings,
                'shortcode' => ''
            ]);
            $new_id = $wpdb->insert_id;
            // Update shortcode with ID
            $wpdb->update($table_name, ['shortcode' => '[meowtable id="' . $new_id . '"]'], ['id' => $new_id]);
            wp_send_json_success(['id' => $new_id, 'message' => 'Created successfully', 'redirect' => admin_url('admin.php?page=meowtable&action=edit&id='.$new_id)]);
        }
    }

    public static function delete_table() {
        check_ajax_referer('meowtable_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $id = intval($_POST['id']);
        if ($id > 0) {
            $wpdb->delete($wpdb->prefix . 'meowtables', ['id' => $id]);
            wp_send_json_success('Deleted');
        }
        wp_send_json_error('Invalid ID');
    }
}
