<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'AM_Social_Login' ) || ! AM_Social_Login::enabled() ) return;
$s = AM_Social_Login::settings();
$has_google = ! empty( $s['google_id'] );
$has_fb     = ! empty( $s['facebook_id'] );
if ( ! $has_google && ! $has_fb ) return;
?>
<div class="ig-social-login">
  <div class="ig-social-sep"><span><?php esc_html_e( 'or continue with', 'ababil-members' ); ?></span></div>
  <?php if ( $has_google ) : ?>
    <a class="ig-social-btn ig-social-google" href="<?php echo esc_url( AM_Social_Login::start_url( 'google' ) ); ?>">
      <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="#4285F4" d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v3.9h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.7 3-4.3 3-7.4z"/><path fill="#34A853" d="M12 22c2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 1-3.4 1a6 6 0 0 1-5.6-4.2H3.1v2.6A10 10 0 0 0 12 22z"/><path fill="#FBBC05" d="M6.4 14.9a6 6 0 0 1 0-3.8V8.5H3.1a10 10 0 0 0 0 9l3.3-2.6z"/><path fill="#EA4335" d="M12 6.1c1.5 0 2.8.5 3.8 1.5l2.8-2.8A10 10 0 0 0 3.1 8.5l3.3 2.6A6 6 0 0 1 12 6.1z"/></svg>
      <?php esc_html_e( 'Google', 'ababil-members' ); ?>
    </a>
  <?php endif; ?>
  <?php if ( $has_fb ) : ?>
    <a class="ig-social-btn ig-social-facebook" href="<?php echo esc_url( AM_Social_Login::start_url( 'facebook' ) ); ?>">
      <span class="ig-social-fb-icon">f</span> <?php esc_html_e( 'Facebook', 'ababil-members' ); ?>
    </a>
  <?php endif; ?>
</div>
