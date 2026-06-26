<?php
/**
 * Template Name: Om os
 *
 * Page template for the "about" page key in moneyweb-theme.json.
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
            <?php mw_field( 'content_body', false, 'html' ); ?>
        </div>
    </article>
</main>
<?php
get_footer();
