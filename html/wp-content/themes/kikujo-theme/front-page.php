<?php
/**
 * Front page template
 *
 * @package Kikujo_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Get customizer settings
$hero_title       = get_theme_mod( 'kikujo_hero_title', '心に寄り添う福祉サービス' );
$hero_description = get_theme_mod( 'kikujo_hero_description', '一人ひとりに寄り添い、地域とともに歩む福祉サービスを提供しています。' );
$hero_background  = get_theme_mod( 'kikujo_hero_background' );

$hero_style = '';
if ( $hero_background ) {
    $hero_style = 'background-image: url(' . esc_url( $hero_background ) . ');';
}
?>

<!-- Hero Section -->
<section class="hero-section"<?php echo $hero_style ? ' style="' . esc_attr( $hero_style ) . '"' : ''; ?>>
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title"><?php echo esc_html( $hero_title ); ?></h1>
            <p class="hero-description"><?php echo nl2br( esc_html( $hero_description ) ); ?></p>
            <div class="hero-buttons">
                <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn-white">
                    <?php esc_html_e( 'お問い合わせ', 'kikujo-theme' ); ?>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="section services-section">
    <div class="container">
        <h2 class="section-title"><?php esc_html_e( 'サービス紹介', 'kikujo-theme' ); ?></h2>
        <div class="card-grid">
            <div class="card service-card">
                <div class="service-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                </div>
                <h3 class="service-title"><?php echo nl2br( esc_html__( "日常で、ちょこっとした\n困りごとがある方への\nサポート", 'kikujo-theme' ) ); ?></h3>
                <p class="service-description">
                    <?php echo nl2br( esc_html__( "「買い物時、手伝いの人がいたら･･･」「役所の書類作成・手続き、助けてほしいなぁ」
                    といった高齢者さん、障碍者さん\n
                    お子さんを「少しの間、家でみていてもらえれば助かるかな」「宿題や勉強のお手伝い、お願い！」
                    といった保護者さん\n
                    「話し相手が欲しいなぁ～」「こんな事があったのだけれど、誰かに聞いてもらいたい！」
                    といった会話を希望される方", 'kikujo-theme' ) ); ?>
                </p>
            </div>

            <div class="card service-card">
                <div class="service-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                </div>
                <h3 class="service-title"><?php echo nl2br( esc_html__( "誰かを支援しすぎて\n心が疲れてしまった方\nへのサポート", 'kikujo-theme' ) ); ?></h3>
                <p class="service-description">
                    <?php echo nl2br( esc_html__( "「介護で疲れてしまったの･･･」「仕事がんばったんだけど、しんどいな」「家事・育児で、何だかクタクタになっちゃった」
                    といった、心の優しい責任感のある方", 'kikujo-theme' ) ); ?>
                </p>
            </div>

            <div class="card service-card">
                <div class="service-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <h3 class="service-title"><?php echo nl2br( esc_html__( "故郷が外国で\n日本での生活にお困りの方\nへのサポート", 'kikujo-theme' ) ); ?></h3>
                <p class="service-description">
                    <?php echo nl2br( esc_html__( "「国際結婚で日本に来たけれど、文化や風習が違って困っている」「日本語が読めないよ～」
                    といった国際人の方\n
                    「子供の日本語教育や、しつけをどうしたら良いかしら？」「物件探し手伝って！」
                    とお困りの外国籍の保護者さん", 'kikujo-theme' ) ); ?>
                </p>
            </div>
        </div>

        <?php
        // Get "ご利用の流れ" page using WP_Query
        $flow_query = new WP_Query( array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'title'          => 'ご利用の流れ',
            'posts_per_page' => 1,
        ) );
        if ( $flow_query->have_posts() ) :
            $flow_page = $flow_query->posts[0];
        ?>
        <div class="text-center mt-4">
            <a href="<?php echo esc_url( get_permalink( $flow_page ) ); ?>" class="btn btn-secondary">
                <?php esc_html_e( 'ご利用の流れ', 'kikujo-theme' ); ?>
            </a>
        </div>
        <?php
            wp_reset_postdata();
        endif;
        ?>
    </div>
</section>

<!-- News Section -->
<section class="section news-section" style="background-color: var(--color-background-alt);">
    <div class="container">
        <h2 class="section-title"><?php esc_html_e( 'お知らせ', 'kikujo-theme' ); ?></h2>

        <?php
        $news_query = new WP_Query( array(
            'post_type'      => 'post',
            'posts_per_page' => 3,
            'post_status'    => 'publish',
        ) );

        if ( $news_query->have_posts() ) :
        ?>
            <div class="news-cards">
                <?php while ( $news_query->have_posts() ) : $news_query->the_post(); ?>
                    <a href="<?php the_permalink(); ?>" class="news-card">
                        <span class="news-date"><?php echo get_the_date(); ?></span>
                        <span class="news-title"><?php the_title(); ?></span>
                    </a>
                <?php endwhile; ?>
            </div>

            <div class="text-center mt-4">
                <a href="<?php echo esc_url( home_url( '/news/' ) ); ?>" class="btn btn-secondary">
                    <?php esc_html_e( 'お知らせ一覧', 'kikujo-theme' ); ?>
                </a>
            </div>
        <?php
            wp_reset_postdata();
        else :
        ?>
            <p class="text-center"><?php esc_html_e( 'お知らせはありません。', 'kikujo-theme' ); ?></p>
        <?php endif; ?>
    </div>
</section>

<!-- About Section -->
<section class="section about-section">
    <div class="container">
        <h2 class="section-title"><?php esc_html_e( 'キクジョー　掬常とは', 'kikujo-theme' ); ?></h2>
        <div class="about-content">
            <p><?php echo nl2br( esc_html__( "キクジョー　掬常は、2023年6月1日、生活クラブ虹の街　市原センターの地域福祉コミュニティとして発足致しました。\n\n既存の公的支援では叶う事が難しい個々の様々なご要望に対し、ご利用者様のテンポと合わせながらきめ細かいサービスを提供しております。\n\n\n", 'kikujo-theme' ) ); ?></p>
        </div>
        <h2 class="section-title"><?php esc_html_e( '対象エリア', 'kikujo-theme' ); ?></h2>
        <div class="about-content">
            <p><?php echo nl2br( esc_html__( "木更津市を中心として\n自転車で移動できる範囲\n\n<<ご相談可能エリア>>\n君津市・富津市・袖ケ浦市・市原市", 'kikujo-theme' ) ); ?></p>
            <p><small><?php esc_html_e( '※交通費が発生する場合は実費をご負担いただくことがあります。', 'kikujo-theme' ); ?></small></p>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="section cta-section">
    <div class="container">
        <div class="text-center">
            <h2><?php esc_html_e( 'お気軽にご相談ください', 'kikujo-theme' ); ?></h2>
            <p><?php echo nl2br( esc_html__( "サービスに関するご質問やご相談など、\nお気軽にお問い合わせください。", 'kikujo-theme' ) ); ?></p>
            <?php
            $phone = get_theme_mod( 'kikujo_phone' );
            if ( $phone ) :
            ?>
                <p class="cta-phone">
                    <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $phone ) ); ?>">
                        <?php echo esc_html( $phone ); ?>
                    </a>
                </p>
            <?php endif; ?>

            <div class="cta-buttons">
                <?php
                $line_url    = get_theme_mod( 'kikujo_line_url' );
                $line_banner = get_theme_mod( 'kikujo_line_banner' );
                $line_text   = get_theme_mod( 'kikujo_line_text', 'LINEでお問い合わせ' );

                if ( $line_url ) :
                ?>
                    <a href="<?php echo esc_url( $line_url ); ?>" class="line-button" target="_blank" rel="noopener noreferrer">
                        <?php if ( $line_banner ) : ?>
                            <img src="<?php echo esc_url( $line_banner ); ?>" alt="<?php echo esc_attr( $line_text ); ?>">
                        <?php else : ?>
                            <span class="btn btn-line"><?php echo esc_html( $line_text ); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn-primary">
                    <?php esc_html_e( 'お問い合わせフォーム', 'kikujo-theme' ); ?>
                </a>
            </div>

            <?php
            $address = get_theme_mod( 'kikujo_address' );
            if ( $address ) :
            ?>
                <div class="cta-address">
                    <p class="cta-address-label"><?php esc_html_e( 'お手紙の送付先', 'kikujo-theme' ); ?></p>
                    <p class="cta-address-text"><?php echo esc_html( $address ); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
get_footer();
