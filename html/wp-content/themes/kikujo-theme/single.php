<?php
/**
 * Single post template
 *
 * @package Kikujo_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="container">
    <?php while ( have_posts() ) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="page-header">
                <h1 class="page-title"><?php the_title(); ?></h1>
                <div class="post-meta">
                    <?php kikujo_posted_on(); ?>
                    <?php kikujo_entry_categories(); ?>
                </div>
            </header>

            <?php if ( has_post_thumbnail() ) : ?>
                <div class="post-thumbnail">
                    <?php the_post_thumbnail( 'large' ); ?>
                </div>
            <?php endif; ?>

            <div class="entry-content">
                <?php
                the_content();

                wp_link_pages( array(
                    'before' => '<div class="page-links">' . esc_html__( 'ページ:', 'kikujo-theme' ),
                    'after'  => '</div>',
                ) );
                ?>
            </div>

            <footer class="entry-footer">
                <?php
                $tags_list = get_the_tag_list( '', ', ' );
                if ( $tags_list ) {
                    echo '<div class="tags-links"><span class="tags-label">' . esc_html__( 'タグ:', 'kikujo-theme' ) . '</span> ' . $tags_list . '</div>';
                }
                ?>
            </footer>
        </article>

        <nav class="post-navigation" aria-label="<?php esc_attr_e( '投稿ナビゲーション', 'kikujo-theme' ); ?>">
            <?php
            $prev_post = get_previous_post();
            $next_post = get_next_post();
            ?>

            <?php if ( $prev_post ) : ?>
                <a href="<?php echo get_permalink( $prev_post ); ?>" class="nav-previous">
                    <span class="nav-label"><?php esc_html_e( '前の記事', 'kikujo-theme' ); ?></span>
                    <span class="nav-title"><?php echo esc_html( get_the_title( $prev_post ) ); ?></span>
                </a>
            <?php else : ?>
                <span></span>
            <?php endif; ?>

            <?php if ( $next_post ) : ?>
                <a href="<?php echo get_permalink( $next_post ); ?>" class="nav-next">
                    <span class="nav-label"><?php esc_html_e( '次の記事', 'kikujo-theme' ); ?></span>
                    <span class="nav-title"><?php echo esc_html( get_the_title( $next_post ) ); ?></span>
                </a>
            <?php endif; ?>
        </nav>

        <?php
        // If comments are open or we have at least one comment, load up the comment template.
        if ( comments_open() || get_comments_number() ) :
            comments_template();
        endif;
        ?>

    <?php endwhile; ?>
</div>

<?php
get_footer();
