<?php
/**
 * Contact Form Handler with OTP Email Verification
 *
 * Flow: email_form → otp → form → confirm → complete
 *
 * @package Kikujo_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Contact Form Class with OTP
 */
class Kikujo_Contact_Form {

    /**
     * Sender (From) address for system-generated emails (OTP, admin notification, auto-reply).
     * Kept separate from the admin inquiry-receiving address (kikujo_contact_email theme_mod).
     */
    const FROM_EMAIL = 'noreply@kikujo.com';

    /**
     * Form fields (excluding email which is handled separately)
     */
    private $fields = array();

    /**
     * Form errors
     */
    private $errors = array();

    /**
     * Form data
     */
    private $data = array();

    /**
     * Form status: email_form, otp, form, confirm, complete, error
     */
    private $status = 'email_form';

    /**
     * Session key for OTP
     */
    private $session_key = '';

    /**
     * Dev mode OTP (for display when email fails)
     */
    private $dev_otp = '';

    /**
     * Constructor
     */
    public function __construct() {
        // Fields after email verification (subject removed)
        $this->fields = array(
            'name' => array(
                'label'    => 'お名前',
                'type'     => 'text',
                'required' => true,
            ),
            'phone' => array(
                'label'    => '電話番号',
                'type'     => 'tel',
                'required' => false,
            ),
            'message' => array(
                'label'    => 'お問い合わせ内容',
                'type'     => 'textarea',
                'required' => true,
            ),
        );

        // Generate session key
        $this->init_session_key();

        // Process form
        $this->process();
    }

    /**
     * Initialize session key
     */
    private function init_session_key() {
        if ( ! isset( $_COOKIE['kikujo_contact_session'] ) ) {
            $session_id = wp_generate_password( 32, false );
            setcookie( 'kikujo_contact_session', $session_id, time() + 3600, '/' );
            $this->session_key = 'kikujo_otp_' . $session_id;
        } else {
            $this->session_key = 'kikujo_otp_' . sanitize_text_field( $_COOKIE['kikujo_contact_session'] );
        }
    }

    /**
     * Check if admin email is configured
     */
    private function get_admin_email() {
        $email = get_theme_mod( 'kikujo_contact_email' );
        return ! empty( $email ) && is_email( $email ) ? $email : false;
    }

    /**
     * Process form submission
     */
    private function process() {
        // Check if admin email is configured
        if ( ! $this->get_admin_email() ) {
            $this->errors['general'] = 'お問い合わせフォームは現在ご利用いただけません。';
            $this->status = 'error';
            return;
        }

        // Check stored session data for current state
        $stored = get_transient( $this->session_key . '_data' );

        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            // Determine status from stored data
            if ( $stored ) {
                if ( isset( $stored['completed'] ) && $stored['completed'] ) {
                    // Already completed, show form confirmation if data exists
                    $this->data = $stored['data'];
                    $this->status = 'confirm';
                } elseif ( isset( $stored['otp_verified'] ) && $stored['otp_verified'] ) {
                    // Email verified, show main form
                    $this->data = $stored['data'];
                    $this->status = 'form';
                }
            }
            return;
        }

        // Verify nonce
        if ( ! isset( $_POST['kikujo_contact_nonce'] ) ||
             ! wp_verify_nonce( $_POST['kikujo_contact_nonce'], 'kikujo_contact_form' ) ) {
            $this->errors['general'] = 'セキュリティエラーが発生しました。もう一度お試しください。';
            return;
        }

        $action = isset( $_POST['contact_action'] ) ? sanitize_text_field( $_POST['contact_action'] ) : '';

        // Handle different actions
        switch ( $action ) {
            case 'send_otp':
                $this->handle_send_otp();
                break;
            case 'verify_otp':
                $this->handle_verify_otp();
                break;
            case 'submit_form':
                $this->handle_submit_form();
                break;
            case 'back_to_email':
                $this->handle_back_to_email();
                break;
            case 'back_to_form':
                $this->handle_back_to_form();
                break;
            case 'send':
                $this->handle_send();
                break;
            default:
                $this->status = 'email_form';
        }
    }

    /**
     * Handle send OTP (after email input)
     */
    private function handle_send_otp() {
        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

        // Validate email
        if ( empty( $email ) ) {
            $this->errors['email'] = 'メールアドレスを入力してください。';
            $this->status = 'email_form';
            return;
        }

        if ( ! is_email( $email ) ) {
            $this->errors['email'] = '正しいメールアドレスを入力してください。';
            $this->status = 'email_form';
            return;
        }

        // Validate email domain (MX record check)
        if ( ! $this->validate_email_domain( $email ) ) {
            $this->errors['email'] = 'メールアドレスのドメインが無効です。';
            $this->status = 'email_form';
            return;
        }

        $this->data['email'] = $email;

        // Generate OTP
        $otp = sprintf( '%06d', random_int( 0, 999999 ) );

        // Store OTP and data
        $store_data = array(
            'otp'          => $otp,
            'otp_verified' => false,
            'data'         => $this->data,
            'created_at'   => time(),
        );
        set_transient( $this->session_key . '_data', $store_data, 600 ); // 10 minutes

        // Send OTP email
        $sent = $this->send_otp_email( $otp );
        $is_dev = defined( 'WP_DEBUG' ) && WP_DEBUG;

        if ( $sent ) {
            $this->status = 'otp';
        } elseif ( $is_dev ) {
            // Dev mode: proceed even if email fails, store OTP for display
            $this->dev_otp = $otp;
            $this->status = 'otp';
        } else {
            $this->errors['general'] = '確認コードの送信に失敗しました。メールアドレスをご確認ください。';
            $this->status = 'email_form';
        }
    }

    /**
     * Validate email domain (MX record check)
     */
    private function validate_email_domain( $email ) {
        $domain = substr( strrchr( $email, '@' ), 1 );

        if ( empty( $domain ) ) {
            return false;
        }

        // Check MX record
        if ( checkdnsrr( $domain, 'MX' ) ) {
            return true;
        }

        // Fallback to A record
        if ( checkdnsrr( $domain, 'A' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Handle verify OTP
     */
    private function handle_verify_otp() {
        $input_otp = isset( $_POST['otp'] ) ? sanitize_text_field( $_POST['otp'] ) : '';
        $stored = get_transient( $this->session_key . '_data' );

        if ( ! $stored ) {
            $this->errors['general'] = '確認コードの有効期限が切れました。もう一度最初からお試しください。';
            $this->status = 'email_form';
            return;
        }

        $this->data = $stored['data'];

        // Check OTP
        if ( $stored['otp'] !== $input_otp ) {
            $this->errors['otp'] = '確認コードが正しくありません。';
            $this->status = 'otp';
            return;
        }

        // Mark as verified
        $stored['otp_verified'] = true;
        set_transient( $this->session_key . '_data', $stored, 600 );

        // Go to main form
        $this->status = 'form';
    }

    /**
     * Handle submit form (after OTP verification)
     */
    private function handle_submit_form() {
        $stored = get_transient( $this->session_key . '_data' );

        if ( ! $stored || ! $stored['otp_verified'] ) {
            $this->errors['general'] = 'セッションが無効です。もう一度最初からお試しください。';
            $this->status = 'email_form';
            return;
        }

        // Get email from stored data
        $this->data = $stored['data'];

        // Get form data
        foreach ( $this->fields as $key => $field ) {
            $this->data[ $key ] = isset( $_POST[ $key ] ) ? sanitize_textarea_field( $_POST[ $key ] ) : '';
        }

        // Validate
        $this->validate();

        if ( ! empty( $this->errors ) ) {
            $this->status = 'form';
            return;
        }

        // Update stored data
        $stored['data'] = $this->data;
        $stored['completed'] = true;
        set_transient( $this->session_key . '_data', $stored, 600 );

        $this->status = 'confirm';
    }

    /**
     * Handle back to email
     */
    private function handle_back_to_email() {
        $stored = get_transient( $this->session_key . '_data' );
        if ( $stored ) {
            $this->data = $stored['data'];
        }
        delete_transient( $this->session_key . '_data' );
        $this->status = 'email_form';
    }

    /**
     * Handle back to form
     */
    private function handle_back_to_form() {
        $stored = get_transient( $this->session_key . '_data' );
        if ( $stored && $stored['otp_verified'] ) {
            $this->data = $stored['data'];
            $stored['completed'] = false;
            set_transient( $this->session_key . '_data', $stored, 600 );
            $this->status = 'form';
        } else {
            $this->status = 'email_form';
        }
    }

    /**
     * Handle send
     */
    private function handle_send() {
        $stored = get_transient( $this->session_key . '_data' );

        if ( ! $stored || ! $stored['otp_verified'] ) {
            $this->errors['general'] = 'セッションが無効です。もう一度最初からお試しください。';
            $this->status = 'email_form';
            return;
        }

        $this->data = $stored['data'];
        $is_dev = defined( 'WP_DEBUG' ) && WP_DEBUG;

        // Send emails
        $sent = $this->send_emails();

        if ( $sent ) {
            delete_transient( $this->session_key . '_data' );
            $this->status = 'complete';
        } elseif ( $is_dev ) {
            // Dev mode: proceed to complete even if email fails
            delete_transient( $this->session_key . '_data' );
            $this->status = 'complete_dev';
        } else {
            // Production: show send failed page
            $this->status = 'send_failed';
        }
    }

    /**
     * Validate form data
     */
    private function validate() {
        foreach ( $this->fields as $key => $field ) {
            $value = isset( $this->data[ $key ] ) ? $this->data[ $key ] : '';

            // Required check
            if ( $field['required'] && empty( $value ) ) {
                $this->errors[ $key ] = $field['label'] . 'を入力してください。';
                continue;
            }

            // Phone validation
            if ( $field['type'] === 'tel' && ! empty( $value ) && ! preg_match( '/^[0-9\-\+\(\)\s]+$/', $value ) ) {
                $this->errors[ $key ] = '正しい電話番号を入力してください。';
            }
        }
    }

    /**
     * Send OTP email
     */
    private function send_otp_email( $otp ) {
        $site_name = get_bloginfo( 'name' );

        $to = $this->data['email'];
        $subject = '【' . $site_name . '】メールアドレス確認コード';

        $message = "メールアドレス確認のため、以下の確認コードを入力してください。\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "確認コード: " . $otp . "\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "※このコードは10分間有効です。\n";
        $message .= "※このメールに心当たりがない場合は、このメールを無視してください。\n\n";
        $message .= "----------------------------------------\n";
        $message .= $site_name . "\n";

        $headers = array(
            'From: ' . $site_name . ' <' . self::FROM_EMAIL . '>',
        );

        return wp_mail( $to, $subject, $message, $headers );
    }

    /**
     * Send emails (notification to admin + confirmation to user)
     */
    private function send_emails() {
        $site_name = get_bloginfo( 'name' );
        $admin_email = $this->get_admin_email();

        // Email to admin (subject is auto-generated)
        $admin_subject = '【' . $site_name . '】お問い合わせがありました';

        $admin_message = "お問い合わせを受け付けました。\n\n";
        $admin_message .= "----------------------------------------\n";
        $admin_message .= "お名前: " . $this->data['name'] . "\n";
        $admin_message .= "メールアドレス: " . $this->data['email'] . "\n";
        if ( ! empty( $this->data['phone'] ) ) {
            $admin_message .= "電話番号: " . $this->data['phone'] . "\n";
        }
        $admin_message .= "----------------------------------------\n";
        $admin_message .= "お問い合わせ内容:\n" . $this->data['message'] . "\n";
        $admin_message .= "----------------------------------------\n";

        $admin_headers = array(
            'From: ' . $site_name . ' <' . self::FROM_EMAIL . '>',
            'Reply-To: ' . $this->data['name'] . ' <' . $this->data['email'] . '>',
        );

        $admin_sent = wp_mail( $admin_email, $admin_subject, $admin_message, $admin_headers );

        if ( ! $admin_sent ) {
            return false;
        }

        // Auto-reply to user
        $user_subject = '【' . $site_name . '】お問い合わせありがとうございます';

        $user_message = $this->data['name'] . " 様\n\n";
        $user_message .= "この度はお問い合わせいただき、誠にありがとうございます。\n";
        $user_message .= "以下の内容でお問い合わせを受け付けました。\n\n";
        $user_message .= "----------------------------------------\n";
        $user_message .= "お問い合わせ内容:\n" . $this->data['message'] . "\n";
        $user_message .= "----------------------------------------\n\n";
        $user_message .= "内容を確認の上、担当者よりご連絡させていただきます。\n";
        $user_message .= "今しばらくお待ちくださいますようお願い申し上げます。\n\n";
        $user_message .= "※このメールは自動送信されています。\n";
        $user_message .= "----------------------------------------\n";
        $user_message .= $site_name . "\n";

        $user_headers = array(
            'From: ' . $site_name . ' <' . self::FROM_EMAIL . '>',
        );

        wp_mail( $this->data['email'], $user_subject, $user_message, $user_headers );

        return true;
    }

    /**
     * Get form status
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Get field value
     */
    public function get_value( $key ) {
        return isset( $this->data[ $key ] ) ? $this->data[ $key ] : '';
    }

    /**
     * Get field error
     */
    public function get_error( $key ) {
        return isset( $this->errors[ $key ] ) ? $this->errors[ $key ] : '';
    }

    /**
     * Has errors
     */
    public function has_errors() {
        return ! empty( $this->errors );
    }

    /**
     * Get general error
     */
    public function get_general_error() {
        return isset( $this->errors['general'] ) ? $this->errors['general'] : '';
    }

    /**
     * Get fields
     */
    public function get_fields() {
        return $this->fields;
    }

    /**
     * Render email form (Step 1)
     */
    public function render_email_form() {
        ?>
        <form method="post" class="contact-form contact-email-form" novalidate>
            <?php wp_nonce_field( 'kikujo_contact_form', 'kikujo_contact_nonce' ); ?>

            <?php if ( $this->get_general_error() ) : ?>
                <div class="contact-form-error">
                    <?php echo esc_html( $this->get_general_error() ); ?>
                </div>
            <?php endif; ?>

            <div class="form-group <?php echo $this->get_error( 'email' ) ? 'has-error' : ''; ?>">
                <label for="email">
                    メールアドレス
                    <span class="required">*</span>
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo esc_attr( $this->get_value( 'email' ) ); ?>"
                    required
                >
                <?php if ( $this->get_error( 'email' ) ) : ?>
                    <span class="error-message"><?php echo esc_html( $this->get_error( 'email' ) ); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" name="contact_action" value="send_otp" class="btn btn-primary">
                    確認コードを送信
                </button>
            </div>

            <p class="form-note">
                ※入力されたメールアドレスに確認コードを送信します。
            </p>
        </form>
        <?php
    }

    /**
     * Render OTP verification (Step 2)
     */
    public function render_otp() {
        $is_dev = defined( 'WP_DEBUG' ) && WP_DEBUG;
        $show_dev_otp = $is_dev && ! empty( $this->dev_otp );
        ?>
        <div class="contact-otp">
            <?php if ( $show_dev_otp ) : ?>
                <div class="dev-otp-notice">
                    <p><strong>【開発モード】</strong> メール送信がスキップされました。</p>
                    <p>確認コード: <code><?php echo esc_html( $this->dev_otp ); ?></code></p>
                </div>
                <script>console.log('【開発モード】確認コード:', '<?php echo esc_js( $this->dev_otp ); ?>');</script>
            <?php endif; ?>

            <div class="otp-message">
                <p><strong><?php echo esc_html( $this->get_value( 'email' ) ); ?></strong> 宛に確認コードを送信しました。</p>
                <p>メールに記載された6桁の確認コードを入力してください。</p>
            </div>

            <form method="post" class="otp-form">
                <?php wp_nonce_field( 'kikujo_contact_form', 'kikujo_contact_nonce' ); ?>

                <?php if ( $this->get_error( 'otp' ) ) : ?>
                    <div class="contact-form-error">
                        <?php echo esc_html( $this->get_error( 'otp' ) ); ?>
                    </div>
                <?php endif; ?>

                <div class="form-group <?php echo $this->get_error( 'otp' ) ? 'has-error' : ''; ?>">
                    <label for="otp">確認コード <span class="required">*</span></label>
                    <input
                        type="text"
                        id="otp"
                        name="otp"
                        class="otp-input"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        placeholder="000000"
                        required
                    >
                </div>

                <div class="form-actions">
                    <button type="submit" name="contact_action" value="back_to_email" class="btn btn-secondary">
                        メールアドレスを変更
                    </button>
                    <button type="submit" name="contact_action" value="verify_otp" class="btn btn-primary">
                        確認する
                    </button>
                </div>

                <p class="form-note">
                    ※確認コードは10分間有効です。届かない場合は迷惑メールフォルダをご確認ください。
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render main form (Step 3 - after email verification)
     */
    public function render_form() {
        ?>
        <div class="contact-form-step">
            <div class="verified-email">
                <span class="verified-label">確認済みメールアドレス:</span>
                <span class="verified-value"><?php echo esc_html( $this->get_value( 'email' ) ); ?></span>
            </div>

            <form method="post" class="contact-form" novalidate>
                <?php wp_nonce_field( 'kikujo_contact_form', 'kikujo_contact_nonce' ); ?>

                <?php if ( $this->get_general_error() ) : ?>
                    <div class="contact-form-error">
                        <?php echo esc_html( $this->get_general_error() ); ?>
                    </div>
                <?php endif; ?>

                <?php foreach ( $this->fields as $key => $field ) : ?>
                    <div class="form-group <?php echo $this->get_error( $key ) ? 'has-error' : ''; ?>">
                        <label for="<?php echo esc_attr( $key ); ?>">
                            <?php echo esc_html( $field['label'] ); ?>
                            <?php if ( $field['required'] ) : ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>

                        <?php if ( $field['type'] === 'textarea' ) : ?>
                            <textarea
                                id="<?php echo esc_attr( $key ); ?>"
                                name="<?php echo esc_attr( $key ); ?>"
                                rows="6"
                                <?php echo $field['required'] ? 'required' : ''; ?>
                            ><?php echo esc_textarea( $this->get_value( $key ) ); ?></textarea>
                        <?php else : ?>
                            <input
                                type="<?php echo esc_attr( $field['type'] ); ?>"
                                id="<?php echo esc_attr( $key ); ?>"
                                name="<?php echo esc_attr( $key ); ?>"
                                value="<?php echo esc_attr( $this->get_value( $key ) ); ?>"
                                <?php echo $field['required'] ? 'required' : ''; ?>
                            >
                        <?php endif; ?>

                        <?php if ( $this->get_error( $key ) ) : ?>
                            <span class="error-message"><?php echo esc_html( $this->get_error( $key ) ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" name="contact_action" value="submit_form" class="btn btn-primary">
                        入力内容を確認
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render confirmation (Step 4)
     */
    public function render_confirm() {
        ?>
        <div class="contact-confirm">
            <p class="confirm-message">以下の内容でよろしければ「送信する」ボタンを押してください。</p>

            <dl class="confirm-data">
                <dt>メールアドレス</dt>
                <dd><?php echo esc_html( $this->get_value( 'email' ) ); ?></dd>

                <?php foreach ( $this->fields as $key => $field ) : ?>
                    <?php if ( ! empty( $this->get_value( $key ) ) ) : ?>
                        <dt><?php echo esc_html( $field['label'] ); ?></dt>
                        <dd><?php echo nl2br( esc_html( $this->get_value( $key ) ) ); ?></dd>
                    <?php endif; ?>
                <?php endforeach; ?>
            </dl>

            <form method="post" class="confirm-form">
                <?php wp_nonce_field( 'kikujo_contact_form', 'kikujo_contact_nonce' ); ?>

                <div class="form-actions">
                    <button type="submit" name="contact_action" value="back_to_form" class="btn btn-secondary">
                        戻る
                    </button>
                    <button type="submit" name="contact_action" value="send" class="btn btn-primary">
                        送信する
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render complete (Step 5)
     */
    public function render_complete() {
        ?>
        <div class="contact-complete">
            <div class="complete-icon">&#10003;</div>
            <h2>送信完了</h2>
            <p>お問い合わせいただきありがとうございます。</p>
            <p>確認メールをお送りしましたのでご確認ください。<br>
            内容を確認の上、担当者よりご連絡させていただきます。</p>
            <p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary">トップページへ戻る</a></p>
        </div>
        <?php
    }

    /**
     * Render complete for dev mode (email skipped)
     */
    public function render_complete_dev() {
        ?>
        <div class="contact-complete">
            <div class="dev-otp-notice" style="margin-bottom: var(--spacing-xl);">
                <p><strong>【開発モード】</strong> メール送信がスキップされました。</p>
                <p>本番環境では確認メールが送信されます。</p>
            </div>
            <script>console.log('【開発モード】お問い合わせ送信完了（メール送信スキップ）');</script>
            <div class="complete-icon">&#10003;</div>
            <h2>送信完了</h2>
            <p>お問い合わせいただきありがとうございます。</p>
            <p>内容を確認の上、担当者よりご連絡させていただきます。</p>
            <p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary">トップページへ戻る</a></p>
        </div>
        <?php
    }

    /**
     * Render send failed
     */
    public function render_send_failed() {
        ?>
        <div class="contact-error">
            <div class="error-icon">!</div>
            <h2>送信失敗</h2>
            <p>申し訳ございません。メールの送信に失敗しました。</p>
            <p>しばらく経ってから再度お試しいただくか、<br>
            お電話にてお問い合わせください。</p>
            <p>
                <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn-secondary">もう一度試す</a>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary">トップページへ戻る</a>
            </p>
        </div>
        <?php
    }

    /**
     * Render error
     */
    public function render_error() {
        ?>
        <div class="contact-error">
            <div class="error-icon">!</div>
            <h2>エラー</h2>
            <p><?php echo esc_html( $this->get_general_error() ); ?></p>
            <p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary">トップページへ戻る</a></p>
        </div>
        <?php
    }

    /**
     * Render based on status
     */
    public function render() {
        switch ( $this->status ) {
            case 'otp':
                $this->render_otp();
                break;
            case 'form':
                $this->render_form();
                break;
            case 'confirm':
                $this->render_confirm();
                break;
            case 'complete':
                $this->render_complete();
                break;
            case 'complete_dev':
                $this->render_complete_dev();
                break;
            case 'send_failed':
                $this->render_send_failed();
                break;
            case 'error':
                $this->render_error();
                break;
            default:
                $this->render_email_form();
                break;
        }
    }
}
