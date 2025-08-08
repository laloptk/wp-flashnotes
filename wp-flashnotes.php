<?php
/**
 * Plugin Name: WP FlashNotes
 * Description: A WordPress plugin to manage notes and flashcards, integrated with Gutenberg and the REST API.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL-2.0-or-later
 * Text Domain: wp-flashnotes
 */

defined( 'ABSPATH' ) || exit;

if (!defined('WPFN_PLUGIN_FILE')) {
    define('WPFN_PLUGIN_FILE', __FILE__);
}

// Autoloading PSR-4 classes
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

/**
 * Plugin activation hook: Initialize necessary tasks on activation.
 */
function wp_flashnotes_activation() {
    // Future activation logic (e.g., table creation, etc.)
}
register_activation_hook( __FILE__, 'wp_flashnotes_activation' );

/**
 * Plugin deactivation hook: Clean up tasks on deactivation.
 */
function wp_flashnotes_deactivation() {
    // Future deactivation logic (e.g., cleanup tasks, removing options, etc.)
}
register_deactivation_hook( __FILE__, 'wp_flashnotes_deactivation' );

/**
 * Initialize the plugin: Setup autoloading, controllers, and other core components.
 */
function wp_flashnotes_init() {
    // Initialize Controllers, Routes, or any other core logic for your plugin
}
add_action( 'plugins_loaded', 'wp_flashnotes_init' );

/**
 * Register custom post types, taxonomies, and hooks.
 */
function wp_flashnotes_register_post_types() {
    // Register CPTs (e.g., for flashcards or sets) if applicable
}
add_action( 'init', 'wp_flashnotes_register_post_types' );

/**
 * Register REST API endpoints for notes and flashcards.
 */
function wp_flashnotes_register_api_routes() {
    // Register API routes for CRUD operations
}
add_action( 'rest_api_init', 'wp_flashnotes_register_api_routes' );

/**
 * Enqueue styles and scripts.
 */
function wp_flashnotes_enqueue_assets() {
    // Enqueue plugin-specific styles or scripts here
    // Example: wp_enqueue_style( 'wp-flashnotes-style', plugins_url( 'assets/css/style.css', __FILE__ ) );
    // Example: wp_enqueue_script( 'wp-flashnotes-script', plugins_url( 'assets/js/script.js', __FILE__ ), array( 'jquery' ), null, true );
}
add_action( 'wp_enqueue_scripts', 'wp_flashnotes_enqueue_assets' );
add_action( 'admin_enqueue_scripts', 'wp_flashnotes_enqueue_assets' );
