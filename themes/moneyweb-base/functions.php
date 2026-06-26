<?php
/**
 * Moneyweb Base — theme setup, enqueue, nav.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'moneyweb_base_setup' ) ) {
    function moneyweb_base_setup() {
        add_theme_support( 'title-tag' );
        add_theme_support( 'post-thumbnails' );
        add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
        add_theme_support( 'responsive-embeds' );
        add_theme_support( 'automatic-feed-links' );

        register_nav_menus( [
            'primary' => __( 'Hovedmenu', 'moneyweb-base' ),
            'footer'  => __( 'Footer-menu', 'moneyweb-base' ),
        ] );
    }
}
add_action( 'after_setup_theme', 'moneyweb_base_setup' );

if ( ! function_exists( 'moneyweb_base_enqueue' ) ) {
    function moneyweb_base_enqueue() {
        $base_uri = trailingslashit( get_template_directory_uri() );
        $base_dir = trailingslashit( get_template_directory() );

        wp_enqueue_style(
            'moneyweb-base',
            $base_uri . 'assets/css/base.css',
            [],
            file_exists( $base_dir . 'assets/css/base.css' ) ? filemtime( $base_dir . 'assets/css/base.css' ) : '1.0.0'
        );

        // Child theme stylesheet (style.css) — registered after base so it overrides.
        if ( is_child_theme() ) {
            $child_uri = trailingslashit( get_stylesheet_directory_uri() );
            $child_dir = trailingslashit( get_stylesheet_directory() );
            if ( file_exists( $child_dir . 'style.css' ) ) {
                wp_enqueue_style(
                    'moneyweb-child',
                    $child_uri . 'style.css',
                    [ 'moneyweb-base' ],
                    filemtime( $child_dir . 'style.css' )
                );
            }
        }

        wp_enqueue_script(
            'moneyweb-base-nav',
            $base_uri . 'assets/js/nav.js',
            [],
            file_exists( $base_dir . 'assets/js/nav.js' ) ? filemtime( $base_dir . 'assets/js/nav.js' ) : '1.0.0',
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'moneyweb_base_enqueue' );

/**
 * Helper: safely echo an ACF field with proper escaping for the type.
 *
 * @param string     $key
 * @param string|int $source  post ID, 'option', or default current post
 * @param string     $type    'text' | 'html' | 'attr' | 'url'
 */
if ( ! function_exists( 'mw_field' ) ) {
    function mw_field( $key, $source = false, $type = 'text' ) {
        if ( ! function_exists( 'get_field' ) ) {
            return;
        }
        $value = false === $source ? get_field( $key ) : get_field( $key, $source );
        if ( null === $value || false === $value ) {
            return;
        }
        switch ( $type ) {
            case 'html':
                echo wp_kses_post( $value );
                break;
            case 'attr':
                echo esc_attr( (string) $value );
                break;
            case 'url':
                echo esc_url( (string) $value );
                break;
            case 'text':
            default:
                echo esc_html( (string) $value );
                break;
        }
    }
}

/**
 * Helper: get an ACF image-field's URL by attachment ID.
 *
 * @param string     $key
 * @param string     $size
 * @param string|int $source
 * @return string
 */
if ( ! function_exists( 'mw_image_url' ) ) {
    function mw_image_url( $key, $size = 'large', $source = false ) {
        if ( ! function_exists( 'get_field' ) ) {
            return '';
        }
        $id = false === $source ? get_field( $key ) : get_field( $key, $source );
        if ( ! $id ) {
            return '';
        }
        $src = wp_get_attachment_image_url( (int) $id, $size );
        return $src ? $src : '';
    }
}
