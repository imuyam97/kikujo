<?php
/**
 * Kikujo Theme functions and definitions
 *
 * @package Kikujo_Theme
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Include files
 */
require_once get_template_directory() . '/inc/contact-form.php';

/**
 * Google Tag Manager
 */
define( 'KIKUJO_GTM_ID', 'GTM-W24GFG5D' );

function kikujo_gtm_head() {
    ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo esc_js( KIKUJO_GTM_ID ); ?>');</script>
<!-- End Google Tag Manager -->
    <?php
}
add_action( 'wp_head', 'kikujo_gtm_head', 1 );

function kikujo_gtm_body() {
    ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( KIKUJO_GTM_ID ); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
    <?php
}
add_action( 'wp_body_open', 'kikujo_gtm_body' );

/**
 * Force custom templates for specific URLs
 */
function kikujo_force_custom_templates( $template ) {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    $path = parse_url( $request_uri, PHP_URL_PATH );

    // Match /contact/ or /contact
    if ( preg_match( '#^/contact/?$#', $path ) ) {
        $contact_template = get_template_directory() . '/page-contact.php';
        if ( file_exists( $contact_template ) ) {
            global $wp_query;
            if ( $wp_query->is_404() ) {
                status_header( 200 );
                $wp_query->is_404 = false;
            }
            return $contact_template;
        }
    }

    // Match /news/ or /news
    if ( preg_match( '#^/news/?$#', $path ) ) {
        $news_template = get_template_directory() . '/archive-news.php';
        if ( file_exists( $news_template ) ) {
            global $wp_query;
            if ( $wp_query->is_404() ) {
                status_header( 200 );
                $wp_query->is_404 = false;
            }
            return $news_template;
        }
    }

    return $template;
}
add_filter( 'template_include', 'kikujo_force_custom_templates', 99 );

/**
 * AJAX handler for loading more posts (infinite scroll)
 */
function kikujo_load_more_posts() {
    $paged = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
    $posts_per_page = 10;

    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
    );

    $query = new WP_Query( $args );

    if ( $query->have_posts() ) :
        while ( $query->have_posts() ) : $query->the_post();
            ?>
            <a href="<?php the_permalink(); ?>" class="news-card">
                <span class="news-date"><?php echo get_the_date(); ?></span>
                <span class="news-title"><?php the_title(); ?></span>
            </a>
            <?php
        endwhile;
        wp_reset_postdata();
    endif;

    wp_die();
}
add_action( 'wp_ajax_kikujo_load_more', 'kikujo_load_more_posts' );
add_action( 'wp_ajax_nopriv_kikujo_load_more', 'kikujo_load_more_posts' );

/**
 * External Link Card Embed
 * 外部リンクをOGPカード表示に変換
 */

/**
 * Fetch OGP data from URL
 */
function kikujo_fetch_ogp_data( $url ) {
    // Check cache first
    $cache_key = 'kikujo_ogp_' . md5( $url );
    $cached = get_transient( $cache_key );
    if ( $cached !== false ) {
        return $cached;
    }

    $ogp_data = array(
        'title'       => '',
        'description' => '',
        'image'       => '',
        'site_name'   => '',
        'url'         => $url,
    );

    // Fetch URL content
    $response = wp_remote_get( $url, array(
        'timeout'    => 10,
        'user-agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo( 'version' ) . ')',
    ) );

    if ( is_wp_error( $response ) ) {
        // Cache empty result for 1 hour to avoid repeated failed requests
        set_transient( $cache_key, $ogp_data, HOUR_IN_SECONDS );
        return $ogp_data;
    }

    $html = wp_remote_retrieve_body( $response );
    if ( empty( $html ) ) {
        set_transient( $cache_key, $ogp_data, HOUR_IN_SECONDS );
        return $ogp_data;
    }

    // Parse OGP meta tags
    $doc = new DOMDocument();
    @$doc->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
    $xpath = new DOMXPath( $doc );

    // Get OGP tags
    $og_title = $xpath->query( '//meta[@property="og:title"]/@content' );
    $og_desc = $xpath->query( '//meta[@property="og:description"]/@content' );
    $og_image = $xpath->query( '//meta[@property="og:image"]/@content' );
    $og_site = $xpath->query( '//meta[@property="og:site_name"]/@content' );

    if ( $og_title->length > 0 ) {
        $ogp_data['title'] = $og_title->item(0)->nodeValue;
    }
    if ( $og_desc->length > 0 ) {
        $ogp_data['description'] = $og_desc->item(0)->nodeValue;
    }
    if ( $og_image->length > 0 ) {
        $ogp_data['image'] = $og_image->item(0)->nodeValue;
    }
    if ( $og_site->length > 0 ) {
        $ogp_data['site_name'] = $og_site->item(0)->nodeValue;
    }

    // Fallback to regular meta tags if OGP not found
    if ( empty( $ogp_data['title'] ) ) {
        $title_tags = $xpath->query( '//title' );
        if ( $title_tags->length > 0 ) {
            $ogp_data['title'] = $title_tags->item(0)->nodeValue;
        }
    }
    if ( empty( $ogp_data['description'] ) ) {
        $meta_desc = $xpath->query( '//meta[@name="description"]/@content' );
        if ( $meta_desc->length > 0 ) {
            $ogp_data['description'] = $meta_desc->item(0)->nodeValue;
        }
    }

    // Get site name from URL if not found
    if ( empty( $ogp_data['site_name'] ) ) {
        $parsed = wp_parse_url( $url );
        $ogp_data['site_name'] = isset( $parsed['host'] ) ? $parsed['host'] : '';
    }

    // Cache for 24 hours
    set_transient( $cache_key, $ogp_data, DAY_IN_SECONDS );

    return $ogp_data;
}

/**
 * Generate link card HTML
 */
function kikujo_generate_link_card( $url ) {
    $ogp = kikujo_fetch_ogp_data( $url );

    // Skip if no title found
    if ( empty( $ogp['title'] ) ) {
        return '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a></p>';
    }

    // Determine if we have an image
    $has_image = ! empty( $ogp['image'] );

    // Adjust description length based on whether we have an image
    $desc_length = $has_image ? 80 : 120;

    $html = '<div class="link-card' . ( $has_image ? '' : ' link-card-no-image' ) . '">';
    $html .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" class="link-card-link">';

    if ( $has_image ) {
        $html .= '<div class="link-card-image">';
        $html .= '<img src="' . esc_url( $ogp['image'] ) . '" alt="" loading="lazy">';
        $html .= '</div>';
    }

    $html .= '<div class="link-card-content">';

    // Site name at top
    if ( ! empty( $ogp['site_name'] ) ) {
        $html .= '<div class="link-card-site">' . esc_html( $ogp['site_name'] ) . '</div>';
    }

    $html .= '<div class="link-card-title">' . esc_html( $ogp['title'] ) . '</div>';

    if ( ! empty( $ogp['description'] ) ) {
        $desc = trim( $ogp['description'] );
        if ( mb_strlen( $desc ) > $desc_length ) {
            $desc = mb_substr( $desc, 0, $desc_length ) . '...';
        }
        $html .= '<div class="link-card-description">' . esc_html( $desc ) . '</div>';
    }

    $html .= '</div>';
    $html .= '</a>';
    $html .= '</div>';

    return $html;
}

/**
 * Convert standalone URLs in content to link cards
 */
function kikujo_embed_external_links( $content ) {
    // Skip on archive/home pages
    if ( is_home() || is_archive() || is_search() ) {
        return $content;
    }

    // Skip during REST API requests (prevents issues when saving pages)
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return $content;
    }

    // Skip if no http links in content
    if ( strpos( $content, 'http' ) === false ) {
        return $content;
    }

    // Pattern: paragraph containing only a link
    // Matches: <p><a href="URL">text</a></p> or <p>URL</p>
    $pattern = '#<p[^>]*>(\s*<a[^>]+href=["\']?(https?://[^"\'>\s]+)["\']?[^>]*>.*?</a>\s*|(\s*https?://[^\s<]+\s*))</p>#is';

    $content = preg_replace_callback( $pattern, function( $matches ) {
        // Extract URL
        if ( ! empty( $matches[2] ) ) {
            // URL from href attribute
            $url = $matches[2];
        } elseif ( ! empty( $matches[3] ) ) {
            // Plain URL
            $url = trim( $matches[3] );
        } else {
            return $matches[0];
        }

        $card = kikujo_process_link_card( $url, $matches[0] );
        return $card !== null ? $card : $matches[0];
    }, $content );

    return $content;
}

/**
 * Process URL and return link card or null if should skip
 */
function kikujo_process_link_card( $url, $original = null ) {
    // Skip internal links
    $site_url = home_url();
    if ( strpos( $url, $site_url ) === 0 ) {
        return $original;
    }

    // Skip common embed URLs (YouTube, Twitter, etc.) - let WordPress handle those
    $skip_domains = array(
        'youtube.com', 'youtu.be',
        'twitter.com', 'x.com',
        'facebook.com',
        'instagram.com',
        'vimeo.com',
        'tiktok.com',
    );
    $parsed = wp_parse_url( $url );
    $host = isset( $parsed['host'] ) ? $parsed['host'] : '';
    foreach ( $skip_domains as $domain ) {
        if ( strpos( $host, $domain ) !== false ) {
            return $original;
        }
    }

    return kikujo_generate_link_card( $url );
}

add_filter( 'the_content', 'kikujo_embed_external_links', 99 );

/**
 * Shortcode for link card
 * Usage: [linkcard url="https://example.com"]
 */
function kikujo_linkcard_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'url' => '',
    ), $atts, 'linkcard' );

    if ( empty( $atts['url'] ) ) {
        return '';
    }

    // During REST API requests, return a simple link to prevent save issues
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return '<p><a href="' . esc_url( $atts['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $atts['url'] ) . '</a></p>';
    }

    return kikujo_generate_link_card( $atts['url'] );
}
add_shortcode( 'linkcard', 'kikujo_linkcard_shortcode' );

/**
 * Theme setup
 */
function kikujo_theme_setup() {
    // Add default posts and comments RSS feed links to head
    add_theme_support( 'automatic-feed-links' );

    // Let WordPress manage the document title
    add_theme_support( 'title-tag' );

    // Enable support for Post Thumbnails on posts and pages
    add_theme_support( 'post-thumbnails' );

    // Set post thumbnail size
    set_post_thumbnail_size( 1200, 630, true );

    // Add custom image sizes
    add_image_size( 'kikujo-card', 600, 400, true );
    add_image_size( 'kikujo-hero', 1920, 800, true );

    // Register navigation menus
    register_nav_menus( array(
        'primary' => __( 'メインメニュー', 'kikujo-theme' ),
        'footer'  => __( 'フッターメニュー', 'kikujo-theme' ),
    ) );

    // Switch default core markup to output valid HTML5
    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ) );

    // Add support for custom logo
    add_theme_support( 'custom-logo', array(
        'height'      => 100,
        'width'       => 300,
        'flex-height' => true,
        'flex-width'  => true,
    ) );

    // Add support for responsive embeds
    add_theme_support( 'responsive-embeds' );

    // Add support for Block Styles
    add_theme_support( 'wp-block-styles' );

    // Add support for editor styles
    add_theme_support( 'editor-styles' );

    // Add support for wide alignment
    add_theme_support( 'align-wide' );
}
add_action( 'after_setup_theme', 'kikujo_theme_setup' );

/**
 * Set the content width in pixels
 */
function kikujo_content_width() {
    $GLOBALS['content_width'] = apply_filters( 'kikujo_content_width', 1200 );
}
add_action( 'after_setup_theme', 'kikujo_content_width', 0 );

/**
 * Enqueue scripts and styles
 */
function kikujo_scripts() {
    // Main stylesheet
    wp_enqueue_style(
        'kikujo-style',
        get_stylesheet_uri(),
        array(),
        wp_get_theme()->get( 'Version' )
    );

    // Skip link focus fix
    wp_enqueue_script(
        'kikujo-skip-link-focus-fix',
        get_template_directory_uri() . '/js/skip-link-focus-fix.js',
        array(),
        '1.0.0',
        true
    );

    // Navigation script
    wp_enqueue_script(
        'kikujo-navigation',
        get_template_directory_uri() . '/js/navigation.js',
        array(),
        '1.0.0',
        true
    );

    // Comment reply script
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}
add_action( 'wp_enqueue_scripts', 'kikujo_scripts' );

/**
 * Register widget areas
 */
function kikujo_widgets_init() {
    register_sidebar( array(
        'name'          => __( 'サイドバー', 'kikujo-theme' ),
        'id'            => 'sidebar-1',
        'description'   => __( 'サイドバーにウィジェットを追加してください。', 'kikujo-theme' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ) );

    register_sidebar( array(
        'name'          => __( 'フッター 1', 'kikujo-theme' ),
        'id'            => 'footer-1',
        'description'   => __( 'フッターの左側にウィジェットを追加してください。', 'kikujo-theme' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );

    register_sidebar( array(
        'name'          => __( 'フッター 2', 'kikujo-theme' ),
        'id'            => 'footer-2',
        'description'   => __( 'フッターの中央にウィジェットを追加してください。', 'kikujo-theme' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );

    register_sidebar( array(
        'name'          => __( 'フッター 3', 'kikujo-theme' ),
        'id'            => 'footer-3',
        'description'   => __( 'フッターの右側にウィジェットを追加してください。', 'kikujo-theme' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
}
add_action( 'widgets_init', 'kikujo_widgets_init' );

/**
 * Custom excerpt length
 */
function kikujo_excerpt_length( $length ) {
    return 40;
}
add_filter( 'excerpt_length', 'kikujo_excerpt_length' );

/**
 * Custom excerpt more
 */
function kikujo_excerpt_more( $more ) {
    return '...';
}
add_filter( 'excerpt_more', 'kikujo_excerpt_more' );

/**
 * Add custom classes to body
 */
function kikujo_body_classes( $classes ) {
    // Add a class if there is no sidebar
    if ( ! is_active_sidebar( 'sidebar-1' ) ) {
        $classes[] = 'no-sidebar';
    }

    return $classes;
}
add_filter( 'body_class', 'kikujo_body_classes' );

/**
 * Custom template tags
 */

/**
 * Display posted date
 */
function kikujo_posted_on() {
    $time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time>';

    $time_string = sprintf(
        $time_string,
        esc_attr( get_the_date( DATE_W3C ) ),
        esc_html( get_the_date() )
    );

    echo '<span class="posted-on">' . $time_string . '</span>';
}

/**
 * Display post categories
 */
function kikujo_entry_categories() {
    $categories_list = get_the_category_list( ', ' );
    if ( $categories_list ) {
        echo '<span class="cat-links">' . $categories_list . '</span>';
    }
}

/**
 * Display pagination
 */
function kikujo_pagination() {
    the_posts_pagination( array(
        'mid_size'  => 2,
        'prev_text' => '&laquo; 前へ',
        'next_text' => '次へ &raquo;',
        'class'     => 'pagination',
    ) );
}

/**
 * Get theme option
 */
function kikujo_get_option( $key, $default = '' ) {
    $options = get_option( 'kikujo_theme_options', array() );
    return isset( $options[ $key ] ) ? $options[ $key ] : $default;
}

/**
 * Theme Customizer settings
 */
function kikujo_customize_register( $wp_customize ) {
    // Hero Section
    $wp_customize->add_section( 'kikujo_hero', array(
        'title'    => __( 'ヒーローセクション', 'kikujo-theme' ),
        'priority' => 30,
    ) );

    // Hero Title
    $wp_customize->add_setting( 'kikujo_hero_title', array(
        'default'           => '心に寄り添う福祉サービス',
        'sanitize_callback' => 'sanitize_text_field',
    ) );

    $wp_customize->add_control( 'kikujo_hero_title', array(
        'label'   => __( 'ヒーロータイトル', 'kikujo-theme' ),
        'section' => 'kikujo_hero',
        'type'    => 'text',
    ) );

    // Hero Description
    $wp_customize->add_setting( 'kikujo_hero_description', array(
        'default'           => '一人ひとりに寄り添い、地域とともに歩む福祉サービスを提供しています。',
        'sanitize_callback' => 'sanitize_textarea_field',
    ) );

    $wp_customize->add_control( 'kikujo_hero_description', array(
        'label'   => __( 'ヒーロー説明文', 'kikujo-theme' ),
        'section' => 'kikujo_hero',
        'type'    => 'textarea',
    ) );

    // Hero Background Image
    $wp_customize->add_setting( 'kikujo_hero_background', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
    ) );

    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'kikujo_hero_background', array(
        'label'   => __( 'ヒーロー背景画像', 'kikujo-theme' ),
        'section' => 'kikujo_hero',
    ) ) );

    // Contact Information Section
    $wp_customize->add_section( 'kikujo_contact', array(
        'title'    => __( '連絡先情報', 'kikujo-theme' ),
        'priority' => 35,
    ) );

    // Contact Email (for receiving inquiries)
    $wp_customize->add_setting( 'kikujo_contact_email', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_email',
    ) );

    $wp_customize->add_control( 'kikujo_contact_email', array(
        'label'       => __( '通知先メールアドレス', 'kikujo-theme' ),
        'description' => __( 'お問い合わせを受信するメールアドレス（必須）', 'kikujo-theme' ),
        'section'     => 'kikujo_contact',
        'type'        => 'email',
    ) );

    // Phone Number
    $wp_customize->add_setting( 'kikujo_phone', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ) );

    $wp_customize->add_control( 'kikujo_phone', array(
        'label'   => __( '電話番号', 'kikujo-theme' ),
        'section' => 'kikujo_contact',
        'type'    => 'text',
    ) );

    // Mailing Address
    $wp_customize->add_setting( 'kikujo_address', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
    ) );

    $wp_customize->add_control( 'kikujo_address', array(
        'label'   => __( '手紙送付先', 'kikujo-theme' ),
        'section' => 'kikujo_contact',
        'type'    => 'textarea',
    ) );

    // Business Hours
    $wp_customize->add_setting( 'kikujo_hours', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ) );

    $wp_customize->add_control( 'kikujo_hours', array(
        'label'   => __( '営業時間', 'kikujo-theme' ),
        'section' => 'kikujo_contact',
        'type'    => 'text',
    ) );

    // LINE Official Account Section
    $wp_customize->add_section( 'kikujo_line', array(
        'title'    => __( '公式LINE', 'kikujo-theme' ),
        'priority' => 36,
    ) );

    // LINE Friend Add URL
    $wp_customize->add_setting( 'kikujo_line_url', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
    ) );

    $wp_customize->add_control( 'kikujo_line_url', array(
        'label'       => __( '友だち追加URL', 'kikujo-theme' ),
        'description' => __( '例: https://line.me/R/ti/p/@xxxxx', 'kikujo-theme' ),
        'section'     => 'kikujo_line',
        'type'        => 'url',
    ) );

    // LINE Banner Image
    $wp_customize->add_setting( 'kikujo_line_banner', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
    ) );

    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'kikujo_line_banner', array(
        'label'       => __( 'バナー画像', 'kikujo-theme' ),
        'description' => __( 'LINEの友だち追加ボタン画像', 'kikujo-theme' ),
        'section'     => 'kikujo_line',
    ) ) );

    // LINE Display Text (optional)
    $wp_customize->add_setting( 'kikujo_line_text', array(
        'default'           => 'LINEでお問い合わせ',
        'sanitize_callback' => 'sanitize_text_field',
    ) );

    $wp_customize->add_control( 'kikujo_line_text', array(
        'label'   => __( '表示テキスト（バナーがない場合）', 'kikujo-theme' ),
        'section' => 'kikujo_line',
        'type'    => 'text',
    ) );

    // Footer Banners Section
    $wp_customize->add_section( 'kikujo_footer_banners', array(
        'title'    => __( 'フッターバナー', 'kikujo-theme' ),
        'priority' => 40,
    ) );

    // Add 5 banner slots
    for ( $i = 1; $i <= 5; $i++ ) {
        // Banner Image
        $wp_customize->add_setting( "kikujo_footer_banner_{$i}_image", array(
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ) );

        $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, "kikujo_footer_banner_{$i}_image", array(
            'label'   => sprintf( __( 'バナー %d 画像', 'kikujo-theme' ), $i ),
            'section' => 'kikujo_footer_banners',
        ) ) );

        // Banner URL
        $wp_customize->add_setting( "kikujo_footer_banner_{$i}_url", array(
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ) );

        $wp_customize->add_control( "kikujo_footer_banner_{$i}_url", array(
            'label'   => sprintf( __( 'バナー %d リンクURL', 'kikujo-theme' ), $i ),
            'section' => 'kikujo_footer_banners',
            'type'    => 'url',
        ) );
    }
}
add_action( 'customize_register', 'kikujo_customize_register' );

/**
 * WebP Auto Conversion
 * - Keep original uploaded file (JPG/PNG)
 * - Convert thumbnails to WebP and delete JPG/PNG versions
 */

/**
 * Convert image to WebP and delete original
 *
 * @param string $file_path Path to the image file
 * @param int    $quality   WebP quality (0-100)
 * @return string|false WebP file path on success, false on failure
 */
function kikujo_convert_to_webp( $file_path, $quality = 80 ) {
    if ( ! file_exists( $file_path ) ) {
        return false;
    }

    $mime_type = mime_content_type( $file_path );
    $webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file_path );

    // Skip if already WebP or unsupported format
    if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/png' ), true ) ) {
        return false;
    }

    // Create image resource
    switch ( $mime_type ) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg( $file_path );
            break;
        case 'image/png':
            $image = imagecreatefrompng( $file_path );
            imagepalettetotruecolor( $image );
            imagealphablending( $image, true );
            imagesavealpha( $image, true );
            break;
        default:
            return false;
    }

    if ( ! $image ) {
        return false;
    }

    // Convert to WebP
    $result = imagewebp( $image, $webp_path, $quality );
    imagedestroy( $image );

    if ( $result ) {
        // Delete original thumbnail file (not the main uploaded file)
        unlink( $file_path );
        return $webp_path;
    }

    return false;
}

/**
 * Convert thumbnail images to WebP (keep original file)
 */
function kikujo_generate_webp_on_upload( $metadata, $attachment_id ) {
    $upload_dir = wp_upload_dir();
    $base_dir   = $upload_dir['basedir'];

    // Check if this is a convertible image
    $mime_type = get_post_mime_type( $attachment_id );
    if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/png' ), true ) ) {
        return $metadata;
    }

    // Keep original file intact - only convert thumbnails
    if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
        $file_dir = dirname( $metadata['file'] );
        foreach ( $metadata['sizes'] as $size => $size_data ) {
            $size_path = $base_dir . '/' . $file_dir . '/' . $size_data['file'];
            $webp_path = kikujo_convert_to_webp( $size_path );

            if ( $webp_path ) {
                // Update size metadata to WebP
                $metadata['sizes'][ $size ]['file'] = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $size_data['file'] );
                $metadata['sizes'][ $size ]['mime-type'] = 'image/webp';
            }
        }
    }

    return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'kikujo_generate_webp_on_upload', 10, 2 );

/**
 * Use WebP for full size images on frontend
 */
function kikujo_use_webp_for_full_size( $image, $attachment_id, $size, $icon ) {
    if ( ! is_array( $image ) || empty( $image[0] ) ) {
        return $image;
    }

    // Only convert full size to WebP on frontend display
    if ( $size !== 'full' ) {
        return $image;
    }

    $original_url = $image[0];

    // Check if it's a JPEG or PNG
    if ( ! preg_match( '/\.(jpe?g|png)$/i', $original_url ) ) {
        return $image;
    }

    // Generate WebP URL
    $webp_url = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $original_url );

    // Check if WebP file exists
    $upload_dir = wp_upload_dir();
    $webp_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $webp_url );

    // Create WebP if it doesn't exist
    if ( ! file_exists( $webp_path ) ) {
        $original_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $original_url );
        if ( file_exists( $original_path ) ) {
            kikujo_create_webp_keep_original( $original_path );
        }
    }

    if ( file_exists( $webp_path ) ) {
        $image[0] = $webp_url;
    }

    return $image;
}
add_filter( 'wp_get_attachment_image_src', 'kikujo_use_webp_for_full_size', 10, 4 );

/**
 * Create WebP from original without deleting original
 */
function kikujo_create_webp_keep_original( $file_path, $quality = 80 ) {
    if ( ! file_exists( $file_path ) ) {
        return false;
    }

    $mime_type = mime_content_type( $file_path );
    $webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file_path );

    if ( file_exists( $webp_path ) ) {
        return $webp_path;
    }

    if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/png' ), true ) ) {
        return false;
    }

    switch ( $mime_type ) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg( $file_path );
            break;
        case 'image/png':
            $image = imagecreatefrompng( $file_path );
            imagepalettetotruecolor( $image );
            imagealphablending( $image, true );
            imagesavealpha( $image, true );
            break;
        default:
            return false;
    }

    if ( ! $image ) {
        return false;
    }

    $result = imagewebp( $image, $webp_path, $quality );
    imagedestroy( $image );

    return $result ? $webp_path : false;
}

/**
 * Convert hero background URL to WebP
 */
function kikujo_convert_hero_background_to_webp( $url ) {
    if ( empty( $url ) || ! is_string( $url ) ) {
        return $url;
    }

    if ( ! preg_match( '/\.(jpe?g|png)$/i', $url ) ) {
        return $url;
    }

    $webp_url = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $url );
    $upload_dir = wp_upload_dir();
    $webp_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $webp_url );

    // Create WebP if it doesn't exist
    if ( ! file_exists( $webp_path ) ) {
        $original_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
        if ( file_exists( $original_path ) ) {
            kikujo_create_webp_keep_original( $original_path );
        }
    }

    if ( file_exists( $webp_path ) ) {
        return $webp_url;
    }

    return $url;
}
add_filter( 'theme_mod_kikujo_hero_background', 'kikujo_convert_hero_background_to_webp' );

/**
 * REST API Security
 * 非ログインユーザーのREST APIアクセスを制限
 */
function kikujo_restrict_rest_api( $result ) {
    // 既にエラーがある場合はそのまま返す
    if ( is_wp_error( $result ) ) {
        return $result;
    }

    // ログイン済みユーザーは許可
    if ( is_user_logged_in() ) {
        return $result;
    }

    // 非ログインユーザーはアクセス拒否
    return new WP_Error(
        'rest_not_logged_in',
        __( 'REST APIへのアクセスは許可されていません。', 'kikujo-theme' ),
        array( 'status' => 401 )
    );
}
add_filter( 'rest_authentication_errors', 'kikujo_restrict_rest_api' );

/**
 * Remove REST API links from head
 * REST APIリンクをHTMLヘッダーから削除
 */
remove_action( 'wp_head', 'rest_output_link_wp_head' );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );

/**
 * Disable XML-RPC
 * XML-RPCを無効化（セキュリティ対策）
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Remove WordPress version from head
 * WordPressバージョン情報を削除
 */
remove_action( 'wp_head', 'wp_generator' );

/**
 * Disable author archives to prevent username enumeration
 * 著者アーカイブを無効化（ユーザー名漏洩防止）
 */
function kikujo_disable_author_archive() {
    if ( is_author() ) {
        wp_redirect( home_url(), 301 );
        exit;
    }
}
add_action( 'template_redirect', 'kikujo_disable_author_archive' );

/**
 * 送信元メールのReturn-Pathを差出人アドレス（From）と一致させる
 * (SPFのエンベロープ送信者ドメインをFromと揃え、DMARCアライメントを通すため)
 */
function kikujo_phpmailer_set_sender( $phpmailer ) {
    $phpmailer->Sender = $phpmailer->From;
}
add_action( 'phpmailer_init', 'kikujo_phpmailer_set_sender' );
