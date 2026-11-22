<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** Two-factor verification step, rendered by the login shortcode when ?am_2fa=TOKEN is present. */
$token = isset( $_GET['am_2fa'] ) ? sanitize_key( $_GET['am_2fa'] ) : '';
$site_name = get_bloginfo( 'name' );
?>
<div class="ig-plugin-container ig-auth-wrap">
  <div class="ig-auth-card">
    <div class="ig-auth-logo">📱</div>
    <h2 class="ig-auth-title"><?php esc_html_e( 'Two-factor verification', 'ababil-members' ); ?></h2>
    <p class="ig-auth-sub"><?php echo esc_html( sprintf( __( 'Enter the 6-digit code we sent to your email to continue to %s.', 'ababil-members' ), $site_name ) ); ?></p>

    <?php if ( ! empty( $GLOBALS['am_error'] ) ) : ?>
      <p class="ig-error"><?php echo esc_html( $GLOBALS['am_error'] ); ?></p>
    <?php endif; ?>

    <form method="post" class="ig-auth-form">
      <?php wp_nonce_field( 'am_2fa', 'am_nonce' ); ?>
      <input type="hidden" name="am_action" value="am_2fa_verify">
      <input type="hidden" name="am_2fa_token" value="<?php echo esc_attr( $token ); ?>">
      <label class="ig-field">
        <span class="ig-edit-label"><?php esc_html_e( 'Verification code', 'ababil-members' ); ?></span>
        <input class="ig-input" type="text" name="am_2fa_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" placeholder="000000">
      </label>
      <button class="ig-btn ig-btn-primary ig-auth-submit" type="submit"><?php esc_html_e( 'Verify and log in', 'ababil-members' ); ?></button>
    </form>

    <p class="ig-auth-foot">
      <a href="<?php echo esc_url( am_page_url( 'am-login' ) ); ?>"><?php esc_html_e( 'Back to login', 'ababil-members' ); ?></a>
    </p>
  </div>
</div>
