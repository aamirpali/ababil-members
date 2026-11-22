<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="am-profile">
  <h2><?php echo esc_html( $user->display_name ); ?> 🧒</h2>
  <p>⭐ <?php echo esc_html( am_get_points( $user->ID ) ); ?> points
    <?php echo AM_Badges::html( $user->ID ); ?></p>
  <form method="post" class="am-form">
    <?php wp_nonce_field( 'am_profile', 'am_nonce' ); ?>
    <input type="hidden" name="am_action" value="profile">
    <p><label><?php esc_html_e( 'Favorite color', 'ababil-members' ); ?><br>
      <input type="text" name="fav_color" maxlength="40" value="<?php echo esc_attr( get_user_meta( $user->ID, 'am_fav_color', true ) ); ?>"></label></p>
    <p><button type="submit" class="am-btn"><?php esc_html_e( 'Save Profile', 'ababil-members' ); ?></button></p>
  </form>
  <p><a href="<?php echo esc_url( am_page_url( 'am-members' ) ); ?>"><?php esc_html_e( 'Browse members', 'ababil-members' ); ?></a></p>
</div>
