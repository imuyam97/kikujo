<?php
/**
 * Archive template
 *
 * @package Kikujo_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="container">
    <header class="archive-header">
        <?php
        the_archive_title( '<h1 class="archive-title">', '</h1>' );
        the_archive_description( '<div class="archive-description">', '</div>' );
        ?>
    </header>

    <?php if ( have_posts() ) : ?>

        <div class="card-grid">
            <?php while ( have_posts() ) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class( 'card' ); ?>>
                    <?php if ( has_post_thumbnail() ) : ?>
                        <div class="card-image">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail( 'kikujo-card' ); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="card-content">
                        <div class="card-meta">
                            <?php kikujo_posted_on(); ?>
                        </div>
                        <h2 class="card-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h2>
                        <div class="card-excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                        <a href="<?php the_permalink(); ?>" class="btn btn-secondary">
                            <?php esc_html_e( '続きを読む', 'kikujo-theme' ); ?>
                        </a>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <?php kikujo_pagination(); ?>

    <?php else : ?>

        <div class="no-results">
            <h2><?php esc_html_e( '記事が見つかりませんでした', 'kikujo-theme' ); ?></h2>
            <p><?php esc_html_e( '申し訳ありませんが、お探しの記事は見つかりませんでした。', 'kikujo-theme' ); ?></p>
        </div>

    <?php endif; ?>
</div>

<?php
get_footer();
