<?php
/**
 * 404 Template
 *
 * @package Kikujo_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="container">
    <div class="error-404">
        <h1>ページが見つかりません</h1>
        <p>お探しのページは見つかりませんでした。</p>
        <p>URLが正しいかご確認いただくか、下記のリンクからお探しください。</p>
        <p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary">トップページへ戻る</a></p>
    </div>
</div>

<?php
get_footer();
