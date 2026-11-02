<?php
/**
 * News Archive Template
 * お知らせ一覧（無限スクロール対応）
 *
 * @package Kikujo_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$posts_per_page = 10;

$news_query = new WP_Query( array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => $posts_per_page,
    'paged'          => 1,
) );

$max_pages = $news_query->max_num_pages;
?>

<div class="container">
    <header class="page-header">
        <h1 class="page-title"><?php esc_html_e( 'お知らせ', 'kikujo-theme' ); ?></h1>
    </header>

    <?php if ( $news_query->have_posts() ) : ?>
        <div class="news-cards" id="news-list">
            <?php while ( $news_query->have_posts() ) : $news_query->the_post(); ?>
                <a href="<?php the_permalink(); ?>" class="news-card">
                    <span class="news-date"><?php echo get_the_date(); ?></span>
                    <span class="news-title"><?php the_title(); ?></span>
                </a>
            <?php endwhile; ?>
        </div>

        <?php if ( $max_pages > 1 ) : ?>
            <div class="load-more-container" id="load-more-container">
                <button type="button" class="btn btn-secondary" id="load-more-btn">
                    <?php esc_html_e( 'さらに読み込む', 'kikujo-theme' ); ?>
                </button>
                <div class="loading-spinner" id="loading-spinner" style="display: none;">
                    <span><?php esc_html_e( '読み込み中...', 'kikujo-theme' ); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <script>
        (function() {
            var currentPage = 1;
            var maxPages = <?php echo intval( $max_pages ); ?>;
            var loading = false;
            var newsList = document.getElementById('news-list');
            var loadMoreBtn = document.getElementById('load-more-btn');
            var loadMoreContainer = document.getElementById('load-more-container');
            var loadingSpinner = document.getElementById('loading-spinner');

            if (!loadMoreBtn) return;

            function loadMorePosts() {
                if (loading || currentPage >= maxPages) return;

                loading = true;
                currentPage++;
                loadMoreBtn.style.display = 'none';
                loadingSpinner.style.display = 'block';

                var formData = new FormData();
                formData.append('action', 'kikujo_load_more');
                formData.append('page', currentPage);

                fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    return response.text();
                })
                .then(function(html) {
                    if (html.trim()) {
                        newsList.insertAdjacentHTML('beforeend', html);
                    }

                    loading = false;
                    loadingSpinner.style.display = 'none';

                    if (currentPage >= maxPages) {
                        loadMoreContainer.style.display = 'none';
                    } else {
                        loadMoreBtn.style.display = 'inline-block';
                    }
                })
                .catch(function(error) {
                    console.error('Error loading posts:', error);
                    loading = false;
                    loadingSpinner.style.display = 'none';
                    loadMoreBtn.style.display = 'inline-block';
                });
            }

            // Button click handler
            loadMoreBtn.addEventListener('click', loadMorePosts);

            // Infinite scroll - load when near bottom
            var scrollThreshold = 300;
            window.addEventListener('scroll', function() {
                if (loading || currentPage >= maxPages) return;

                var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                var windowHeight = window.innerHeight;
                var documentHeight = document.documentElement.scrollHeight;

                if (scrollTop + windowHeight >= documentHeight - scrollThreshold) {
                    loadMorePosts();
                }
            });
        })();
        </script>

    <?php else : ?>
        <div class="no-results">
            <p><?php esc_html_e( 'お知らせはまだありません。', 'kikujo-theme' ); ?></p>
        </div>
    <?php endif; ?>

    <?php wp_reset_postdata(); ?>
</div>

<?php
get_footer();
