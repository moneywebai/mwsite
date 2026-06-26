<?php
/**
 * Template Name: Kontakt
 *
 * Page template for the "contact" page key in moneyweb-theme.json.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main id="mw-main" class="mw-main mw-container">
    <article class="mw-article">
        <header class="mw-article__header">
            <h1><?php mw_field( 'heading' ); ?></h1>
        </header>
        <div class="mw-article__content">
            <?php mw_field( 'text', false, 'html' ); ?>
        </div>

        <ul class="mw-contact-info">
            <li>
                <strong><?php esc_html_e( 'Telefon:', 'moneyweb-test-01' ); ?></strong>
                <?php mw_field( 'company_phone', 'option' ); ?>
            </li>
            <li>
                <strong><?php esc_html_e( 'E-mail:', 'moneyweb-test-01' ); ?></strong>
                <?php mw_field( 'company_email', 'option' ); ?>
            </li>
        </ul>
    </article>
</main>
<?php
get_footer();
