<?php
/**
 * Plugin Name:       Moneyweb Core
 * Plugin URI:        https://moneyweb.ai
 * Description:       Reads the active child theme's moneyweb-theme.json, registers ACF field groups and exposes the moneyweb/v1 REST API.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Moneyweb
 * License:           Proprietary
 * Text Domain:       moneyweb-core
 * Network:           true
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MONEYWEB_CORE_VERSION', '1.0.0' );
define( 'MONEYWEB_CORE_FILE', __FILE__ );
define( 'MONEYWEB_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'MONEYWEB_CORE_NAMESPACE', 'moneyweb/v1' );
define( 'MONEYWEB_CORE_OPTIONS_PAGE', 'moneyweb-settings' );
define( 'MONEYWEB_CORE_API_KEY_OPTION', 'moneyweb_api_key' );
define( 'MONEYWEB_CORE_PAGE_META_KEY', '_moneyweb_page_key' );

require_once MONEYWEB_CORE_DIR . 'includes/class-manifest.php';
require_once MONEYWEB_CORE_DIR . 'includes/class-core-fields.php';
require_once MONEYWEB_CORE_DIR . 'includes/class-acf-builder.php';
require_once MONEYWEB_CORE_DIR . 'includes/class-auth.php';
require_once MONEYWEB_CORE_DIR . 'includes/class-validator.php';
require_once MONEYWEB_CORE_DIR . 'includes/class-schema.php';
require_once MONEYWEB_CORE_DIR . 'includes/class-site-data.php';

/**
 * ACF Pro availability check.
 */
function moneyweb_core_acf_active() {
    return class_exists( 'ACF' ) && function_exists( 'acf_add_local_field_group' );
}

/**
 * Admin notice when ACF Pro is not active.
 */
add_action( 'admin_notices', function () {
    if ( moneyweb_core_acf_active() ) {
        return;
    }
    echo '<div class="notice notice-error"><p><strong>Moneyweb Core:</strong> ACF Pro er påkrævet, men ikke aktivt. API-endpoints returnerer 503 indtil ACF Pro aktiveres.</p></div>';
} );

/**
 * Register ACF field groups + options page on acf/init.
 *
 * Core's field group is registered unconditionally so admins always see the
 * Moneyweb settings page. The theme's manifest is only translated to ACF if
 * Moneyweb_Schema::build_combined() considers it valid; on collisions/invalid
 * manifests we surface an admin notice and leave the API-level 422 to /schema.
 */
add_action( 'acf/init', function () {
    if ( ! moneyweb_core_acf_active() ) {
        return;
    }
    Moneyweb_ACF_Builder::register_options_page();
    Moneyweb_ACF_Builder::register_core_fields();

    $manifest = Moneyweb_Manifest::get();
    if ( is_wp_error( $manifest ) ) {
        return;
    }
    $combined = Moneyweb_Schema::build_combined();
    if ( is_wp_error( $combined ) ) {
        return; // manifest invalid — admin notice handles it.
    }
    Moneyweb_ACF_Builder::register_from_manifest( $manifest );
} );

/**
 * Admin notice when the theme manifest is invalid (e.g. uses a reserved Core key).
 */
add_action( 'admin_notices', function () {
    if ( ! moneyweb_core_acf_active() ) {
        return;
    }
    $combined = Moneyweb_Schema::build_combined();
    if ( ! is_wp_error( $combined ) ) {
        return;
    }
    $data = $combined->get_error_data();
    if ( ! isset( $data['errors'] ) || ! is_array( $data['errors'] ) ) {
        return;
    }
    $items = '';
    foreach ( $data['errors'] as $err ) {
        $items .= sprintf(
            '<li><code>%s</code>%s — %s</li>',
            esc_html( isset( $err['code'] ) ? $err['code'] : '' ),
            isset( $err['field'] ) && $err['field'] !== '' ? ' on <code>' . esc_html( $err['field'] ) . '</code>' : '',
            esc_html( isset( $err['message'] ) ? $err['message'] : '' )
        );
    }
    echo '<div class="notice notice-error"><p><strong>Moneyweb Core:</strong> Aktivt themes manifest er ugyldigt. <code>moneyweb/v1/schema</code> returnerer 422.</p><ul style="margin-left:18px;list-style:disc;">' . $items . '</ul></div>';
} );

/**
 * Register REST routes.
 */
add_action( 'rest_api_init', function () {
    register_rest_route( MONEYWEB_CORE_NAMESPACE, '/schema', [
        'methods'             => 'GET',
        'callback'            => [ 'Moneyweb_Schema', 'handle' ],
        'permission_callback' => [ 'Moneyweb_Auth', 'check' ],
    ] );
    register_rest_route( MONEYWEB_CORE_NAMESPACE, '/site-data', [
        'methods'             => 'POST',
        'callback'            => [ 'Moneyweb_Site_Data', 'handle' ],
        'permission_callback' => [ 'Moneyweb_Auth', 'check' ],
    ] );
} );

/**
 * Activation: nothing destructive. We do NOT self-deactivate when ACF is missing.
 */
register_activation_hook( __FILE__, function () {
    // Intentionally minimal. ACF check is surfaced as admin notice.
} );
