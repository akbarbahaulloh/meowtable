<?php
/**
 * Plugin Name: Meowtable
 * Description: A free, lightweight, customizable proper table builder with WP post integration, inspired by Ninja Tables.
 * Version: 1.0.0
 * Author: Akbar Bahaulloh
 * Text Domain: meowtable
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('MEOWTABLE_VERSION', '1.0.0');
define('MEOWTABLE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MEOWTABLE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MEOWTABLE_PLUGIN_FILE', __FILE__);

// Simple autoloader for the app directory
spl_autoload_register(function ($class) {
    $prefix = 'Meowtable\\';
    $base_dir = MEOWTABLE_PLUGIN_DIR . 'app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Run Activation Hook
register_activation_hook(__FILE__, ['Meowtable\Models\Database', 'activate']);

// Boot the plugin
\Meowtable\Controllers\AdminController::init();
\Meowtable\Controllers\FrontendController::init();
