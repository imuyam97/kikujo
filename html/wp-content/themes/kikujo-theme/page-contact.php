<?php
/**
 * Template Name: お問い合わせ
 * Template for contact page (also works with slug "contact")
 *
 * @package Kikujo_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Initialize contact form BEFORE get_header() to allow cookie/session handling
$contact_form = new Kikujo_Contact_Form();
$status = $contact_form->get_status();

get_header();

// Get page title - fallback to default if not in loop
$page_title = 'お問い合わせ';
if ( have_posts() ) {
    while ( have_posts() ) {
        the_post();
        $page_title = get_the_title();
        break;
    }
    wp_reset_postdata();
}
?>

<div class="container">
    <article class="contact-page">
        <?php if ( ! in_array( $status, array( 'complete', 'complete_dev', 'send_failed', 'error' ), true ) ) : ?>
        <header class="page-header">
            <h1 class="page-title"><?php echo esc_html( $page_title ); ?></h1>
            <?php if ( $status === 'otp' ) : ?>
                <p class="page-description">メールアドレスの確認</p>
            <?php elseif ( $status === 'form' ) : ?>
                <p class="page-description">以下のフォームに必要事項をご記入の上、送信してください。<br>
                <span class="required">*</span> は必須項目です。</p>
            <?php elseif ( $status === 'confirm' ) : ?>
                <p class="page-description">入力内容をご確認ください。</p>
            <?php endif; ?>
        </header>
        <?php endif; ?>

        <?php if ( $status === 'email_form' ) :
            $line_url    = get_theme_mod( 'kikujo_line_url' );
            $line_banner = get_theme_mod( 'kikujo_line_banner' );
            $line_text   = get_theme_mod( 'kikujo_line_text', 'LINEでお問い合わせ' );

            if ( $line_url ) :
        ?>
            <div class="contact-line-section text-center">
                <h2>LINEでのお問い合わせ</h2>
                <p class="page-description">LINEからお気軽にご相談いただけます。</p>
                <a href="<?php echo esc_url( $line_url ); ?>" class="line-button" target="_blank" rel="noopener noreferrer">
                    <?php if ( $line_banner ) : ?>
                        <img src="<?php echo esc_url( $line_banner ); ?>" alt="<?php echo esc_attr( $line_text ); ?>">
                    <?php else : ?>
                        <span class="btn btn-line"><?php echo esc_html( $line_text ); ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <hr class="contact-hr">
        <?php endif; ?>
            <div class="contact-mail-section text-center">
                <h2>メールでのお問い合わせ</h2>
                <p class="page-description">メールアドレスを入力してください。<br>
                確認コードを送信します。</p>
            </div>
        <?php endif; ?>

        <div class="entry-content">
            <?php $contact_form->render(); ?>
        </div>
    </article>
</div>

<?php
get_footer();
