<?php if ( ! defined( 'ABSPATH' ) ) exit;
// Two-factor verification step takes over the login form when a challenge is open.
if ( ! empty( $_GET['am_2fa'] ) ) {
    include AM_PATH . 'templates/ig-2fa.php';
    return;
}
$site_name = get_bloginfo( 'name' );
$locked = class_exists( 'AM_Security' ) && AM_Security::is_locked();
?>
<div class="ig-plugin-container ig-auth-wrap">
  <div class="ig-auth-card">
    <div class="ig-auth-logo">🌈</div>
    <h2 class="ig-auth-title"><?php echo esc_html( $site_name ); ?></h2>
    <p class="ig-auth-sub"><?php echo esc_html( sprintf( __( 'Log in to continue to %s.', 'ababil-members' ), $site_name ) ); ?></p>

    <?php if ( $locked ) : ?>
      <p class="ig-error"><?php esc_html_e( 'Too many failed attempts. Please try again later.', 'ababil-members' ); ?></p>
    <?php elseif ( ! empty( $GLOBALS['am_error'] ) ) : ?>
      <p class="ig-error"><?php echo esc_html( $GLOBALS['am_error'] ); ?></p>
    <?php elseif ( isset( $_GET['am_login_error'] ) ) : ?>
      <p class="ig-error"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['am_login_error'] ) ) ); ?></p>
    <?php endif; ?>

    <form method="post" class="ig-auth-form">
      <?php wp_nonce_field( 'am_login', 'am_nonce' ); ?>
      <input type="hidden" name="am_action" value="login">
      <?php if ( class_exists( 'AM_Security' ) ) AM_Security::honeypot_field(); ?>
      <label class="ig-field">
        <span class="ig-edit-label"><?php esc_html_e( 'Username', 'ababil-members' ); ?></span>
        <input class="ig-input" type="text" name="username" required maxlength="20" autocomplete="username" <?php disabled( $locked ); ?>>
      </label>
      <label class="ig-field">
        <span class="ig-edit-label"><?php esc_html_e( 'Password', 'ababil-members' ); ?></span>
        <span class="ig-pw-wrap"><input class="ig-input" type="password" name="password" required autocomplete="current-password" <?php disabled( $locked ); ?>><button type="button" class="ig-pw-toggle" aria-label="<?php echo esc_attr__( 'Show password', 'ababil-members' ); ?>">👁</button></span>
      </label>
      <?php if ( class_exists( 'AM_Security' ) ) AM_Security::captcha_field(); ?>
      <button class="ig-btn ig-btn-primary ig-auth-submit" type="submit" <?php disabled( $locked ); ?>><?php esc_html_e( 'Log in', 'ababil-members' ); ?></button>
    </form>

    <?php include AM_PATH . 'templates/ig-social-buttons.php'; ?>

    <p class="ig-auth-foot">
      <?php esc_html_e( 'New here?', 'ababil-members' ); ?>
      <a href="<?php echo esc_url( am_page_url( 'am-register' ) ); ?>"><?php esc_html_e( 'Create an account', 'ababil-members' ); ?></a>
    </p>
  </div>
</div>
