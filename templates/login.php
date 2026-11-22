<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="am-form">
  <h2><?php esc_html_e( 'Welcome back! 👋', 'ababil-members' ); ?></h2>
  <?php if ( ! empty( $GLOBALS['am_error'] ) ) : ?>
    <p class="am-error"><?php echo esc_html( $GLOBALS['am_error'] ); ?></p>
  <?php endif; ?>
  <form method="post">
    <?php wp_nonce_field( 'am_login', 'am_nonce' ); ?>
    <input type="hidden" name="am_action" value="login">
    <p><label><?php esc_html_e( 'Username', 'ababil-members' ); ?><br>
      <input type="text" name="username" required maxlength="20"></label></p>
    <p><label><?php esc_html_e( 'Password', 'ababil-members' ); ?><br>
      <input type="password" name="password" required></label></p>
    <p><button type="submit" class="am-btn"><?php esc_html_e( 'Login 🚀', 'ababil-members' ); ?></button></p>
  </form>
  <p><a href="<?php echo esc_url( am_page_url( 'am-register' ) ); ?>"><?php esc_html_e( 'New here? Join free', 'ababil-members' ); ?></a></p>
</div>
