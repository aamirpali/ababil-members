<?php if ( ! defined( 'ABSPATH' ) ) exit;
$site_name = get_bloginfo( 'name' );
$am_opts = get_option( 'am_options', array() );
$consent = am_consent_settings();
?>
<div class="ig-plugin-container ig-auth-wrap">
  <div class="ig-auth-card">
    <div class="ig-auth-logo">🌈</div>
    <h2 class="ig-auth-title"><?php echo esc_html( $site_name ); ?></h2>
    <p class="ig-auth-sub"><?php echo esc_html( sprintf( __( 'Create your free account at %s to get started.', 'ababil-members' ), $site_name ) ); ?></p>

    <?php if ( ! empty( $GLOBALS['am_error'] ) ) : ?>
      <p class="ig-error"><?php echo esc_html( $GLOBALS['am_error'] ); ?></p>
    <?php endif; ?>

    <form method="post" class="ig-auth-form">
      <?php wp_nonce_field( 'am_register', 'am_nonce' ); ?>
      <input type="hidden" name="am_action" value="register">
      <?php if ( class_exists( 'AM_Security' ) ) AM_Security::honeypot_field(); ?>
      <label class="ig-field">
        <span class="ig-edit-label"><?php esc_html_e( 'Username', 'ababil-members' ); ?></span>
        <input class="ig-input" type="text" name="username" required maxlength="20" pattern="[A-Za-z0-9_]+" autocomplete="username">
      </label>
      <label class="ig-field">
        <span class="ig-edit-label"><?php esc_html_e( 'Email', 'ababil-members' ); ?></span>
        <input class="ig-input" type="email" name="email" required autocomplete="email">
      </label>
      <label class="ig-field">
        <span class="ig-edit-label"><?php esc_html_e( 'Password', 'ababil-members' ); ?></span>
        <span class="ig-pw-wrap"><input class="ig-input" type="password" name="password" required minlength="6" autocomplete="new-password"><button type="button" class="ig-pw-toggle" aria-label="<?php echo esc_attr__( 'Show password', 'ababil-members' ); ?>">👁</button></span>
      </label>
      <?php
      $chosen = isset( $am_opts['form_fields'] ) && is_array( $am_opts['form_fields'] ) ? $am_opts['form_fields'] : array();
      $custom = array_values( array_filter( AM_Fields::all(), function ( $f ) use ( $chosen ) { return in_array( $f['slug'], $chosen, true ); } ) );
      AM_Fields::render_inputs( $custom, array(), 'register-' );
      if ( class_exists( 'AM_Security' ) ) AM_Security::captcha_field();
      if ( $consent['enabled'] ) : ?>
        <label class="ig-check ig-consent"><input type="checkbox" name="am_consent" value="1" required> <span><?php echo wp_kses_post( $consent['text'] ); ?></span></label>
      <?php endif; ?>
      <button class="ig-btn ig-btn-primary ig-auth-submit" type="submit"><?php echo esc_html( $am_opts['submit_label'] ?? __( 'Create account', 'ababil-members' ) ); ?></button>
    </form>

    <?php include AM_PATH . 'templates/ig-social-buttons.php'; ?>

    <p class="ig-auth-foot">
      <?php esc_html_e( 'Already a member?', 'ababil-members' ); ?>
      <a href="<?php echo esc_url( am_page_url( 'am-login' ) ); ?>"><?php esc_html_e( 'Log in', 'ababil-members' ); ?></a>
    </p>
  </div>
</div>
