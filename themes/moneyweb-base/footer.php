<?php
/**
 * Footer — moneyweb-base
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<footer class="mw-site-footer">
    <div class="mw-container mw-site-footer__inner">
        <?php
        $company = function_exists( 'get_field' ) ? get_field( 'company_name', 'option' ) : '';
        if ( ! $company ) {
            $company = get_bloginfo( 'name' );
        }
        ?>
        <p class="mw-site-footer__copy">
            &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $company ); ?>
        </p>

        <?php if ( has_nav_menu( 'footer' ) ) : ?>
            <nav class="mw-footer-nav" aria-label="<?php esc_attr_e( 'Footer-menu', 'moneyweb-base' ); ?>">
                <?php
                wp_nav_menu( [
                    'theme_location' => 'footer',
                    'container'      => false,
                    'menu_class'     => 'mw-menu mw-menu--footer',
                    'fallback_cb'    => false,
                    'depth'          => 1,
                ] );
                ?>
            </nav>
        <?php endif; ?>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
