<?php
/**
 * Front-page template for moneyweb-test-01.
 *
 * Selected automatically by WordPress when show_on_front = page and
 * page_on_front points to a page assigned via moneyweb-core.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$hero_bg_url = function_exists( 'mw_image_url' ) ? mw_image_url( 'hero_background_image' ) : '';
?>
<main id="mw-main" class="mw-main">

    <section class="mw-hero"
        <?php if ( $hero_bg_url ) : ?>
            style="background-image: url('<?php echo esc_url( $hero_bg_url ); ?>');"
        <?php endif; ?>>
        <div class="mw-container">
            <h1><?php mw_field( 'hero_heading' ); ?></h1>
            <div class="mw-hero__intro"><?php mw_field( 'hero_intro', false, 'html' ); ?></div>

            <?php
            $checklist = function_exists( 'get_field' ) ? get_field( 'hero_checklist' ) : [];
            if ( ! empty( $checklist ) && is_array( $checklist ) ) :
            ?>
                <ul class="mw-checklist">
                    <?php foreach ( $checklist as $row ) : ?>
                        <?php $text = isset( $row['text'] ) ? (string) $row['text'] : ''; ?>
                        <?php if ( '' !== $text ) : ?>
                            <li><?php echo esc_html( $text ); ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <section class="mw-section mw-container">
        <p>
            <strong><?php esc_html_e( 'Virksomhed:', 'moneyweb-test-01' ); ?></strong>
            <?php mw_field( 'company_name', 'option' ); ?>
        </p>
        <p>
            <strong><?php esc_html_e( 'Telefon:', 'moneyweb-test-01' ); ?></strong>
            <?php mw_field( 'company_phone', 'option' ); ?>
        </p>
    </section>

</main>
<?php
get_footer();
