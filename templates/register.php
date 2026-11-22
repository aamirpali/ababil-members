<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="am-form">
  <h2><?php esc_html_e( 'Join Ababil Members 🌟', 'ababil-members' ); ?></h2>
  <?php if ( ! empty( $GLOBALS['am_error'] ) ) : ?>
    <p class="am-error"><?php echo esc_html( $GLOBALS['am_error'] ); ?></p>
  <?php endif; ?>
  <form method="post">
    <?php wp_nonce_field( 'am_register', 'am_nonce' ); ?>
    <input type="hidden" name="am_action" value="register">
    <p><label><?php esc_html_e( 'Username', 'ababil-members' ); ?><br>
      <input type="text" name="username" required maxlength="20" pattern="[A-Za-z0-9_]+"></label></p>
    <p><label><?php esc_html_e( 'Email', 'ababil-members' ); ?><br>
      <input type="email" name="email" required></label></p>
    <p><label><?php esc_html_e( 'Password', 'ababil-members' ); ?><br>
      <input type="password" name="password" required minlength="6"></label></p>
    <p><button type="submit" class="am-btn"><?php esc_html_e( 'Create Account 🚀', 'ababil-members' ); ?></button></p>
  </form>
  <p><a href="<?php echo esc_url( am_page_url( 'am-login' ) ); ?>"><?php esc_html_e( 'Already a member? Login', 'ababil-members' ); ?></a></p>
</div>
