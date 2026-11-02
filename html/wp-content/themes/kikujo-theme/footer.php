<?php
/**
 * Footer template
 *
 * @package Kikujo_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
</main>

<footer id="colophon" class="site-footer">
    <div class="container">
        <?php
        // Collect banners
        $banners = array();
        for ( $i = 1; $i <= 5; $i++ ) {
            $image = get_theme_mod( "kikujo_footer_banner_{$i}_image" );
            $url   = get_theme_mod( "kikujo_footer_banner_{$i}_url" );
            if ( $image ) {
                $banners[] = array(
                    'image' => $image,
                    'url'   => $url,
                );
            }
        }

        if ( ! empty( $banners ) ) :
        ?>
            <div class="footer-banners">
                <?php foreach ( $banners as $banner ) : ?>
                    <div class="footer-banner">
                        <?php if ( $banner['url'] ) : ?>
                            <a href="<?php echo esc_url( $banner['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?php echo esc_url( $banner['image'] ); ?>" alt="">
                            </a>
                        <?php else : ?>
                            <img src="<?php echo esc_url( $banner['image'] ); ?>" alt="">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="footer-inner">
            <?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
                <div class="footer-widget footer-widget-1">
                    <?php dynamic_sidebar( 'footer-1' ); ?>
                </div>
            <?php endif; ?>

            <?php if ( is_active_sidebar( 'footer-2' ) ) : ?>
                <div class="footer-widget footer-widget-2">
                    <?php dynamic_sidebar( 'footer-2' ); ?>
                </div>
            <?php endif; ?>

            <?php if ( is_active_sidebar( 'footer-3' ) ) : ?>
                <div class="footer-widget footer-widget-3">
                    <?php dynamic_sidebar( 'footer-3' ); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( has_nav_menu( 'footer' ) ) : ?>
            <nav class="footer-navigation" aria-label="<?php esc_attr_e( 'フッターナビゲーション', 'kikujo-theme' ); ?>">
                <?php
                wp_nav_menu( array(
                    'theme_location' => 'footer',
                    'menu_class'     => 'footer-menu',
                    'container'      => false,
                    'depth'          => 1,
                ) );
                ?>
            </nav>
        <?php endif; ?>

        <div class="footer-bottom">
            <p>&copy; <?php echo date( 'Y' ); ?> <?php bloginfo( 'name' ); ?>. All rights reserved.</p>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
