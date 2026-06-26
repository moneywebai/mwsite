<?php
/**
 * Fallback template for moneyweb-base.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main class="mw-main mw-container">
    <?php
    if ( have_posts() ) :
        while ( have_posts() ) :
            the_post();
            ?>
            <article <?php post_class( 'mw-article' ); ?>>
                <header class="mw-article__header">
                    <h1 class="mw-article__title"><?php the_title(); ?></h1>
                </header>
                <div class="mw-article__content">
                    <?php the_content(); ?>
                </div>
            </article>
            <?php
        endwhile;
    else :
        ?>
        <p><?php esc_html_e( 'Intet indhold fundet.', 'moneyweb-base' ); ?></p>
        <?php
    endif;
    ?>
</main>
<?php
get_footer();
