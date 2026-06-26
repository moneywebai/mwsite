<?php
/**
 * Header — moneyweb-base
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'mw-body' ); ?>>
<?php wp_body_open(); ?>

<a class="mw-skip-link screen-reader-text" href="#mw-main"><?php esc_html_e( 'Spring til indhold', 'moneyweb-base' ); ?></a>

<header class="mw-site-header">
    <div class="mw-container mw-site-header__inner">
        <p class="mw-site-header__brand">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
                <?php
                $logo_id = function_exists( 'get_field' ) ? get_field( 'logo_primary', 'option' ) : 0;
                if ( $logo_id ) {
                    $logo = wp_get_attachment_image( (int) $logo_id, 'medium', false, [
                        'class' => 'mw-site-header__logo',
                        'alt'   => esc_attr( get_bloginfo( 'name' ) ),
                    ] );
                    echo $logo; // wp_get_attachment_image escapes attributes itself.
                } else {
                    echo esc_html( get_bloginfo( 'name' ) );
                }
                ?>
            </a>
        </p>

        <button class="mw-nav-toggle" aria-expanded="false" aria-controls="mw-primary-nav" type="button">
            <span class="screen-reader-text"><?php esc_html_e( 'Menu', 'moneyweb-base' ); ?></span>
            <span class="mw-nav-toggle__bar" aria-hidden="true"></span>
            <span class="mw-nav-toggle__bar" aria-hidden="true"></span>
            <span class="mw-nav-toggle__bar" aria-hidden="true"></span>
        </button>

        <nav id="mw-primary-nav" class="mw-primary-nav" aria-label="<?php esc_attr_e( 'Hovedmenu', 'moneyweb-base' ); ?>">
            <?php
            if ( has_nav_menu( 'primary' ) ) {
                wp_nav_menu( [
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'mw-menu',
                    'fallback_cb'    => false,
                    'depth'          => 2,
                ] );
            }
            ?>
        </nav>
    </div>
</header>
