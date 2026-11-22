<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="am-directory">
  <h2>👥 <?php esc_html_e( 'Member Directory', 'ababil-members' ); ?></h2>
  <form method="get" class="am-search">
    <input type="text" name="am_q" placeholder="<?php esc_attr_e( 'Search username...', 'ababil-members' ); ?>" value="<?php echo esc_attr( $search ); ?>">
    <button class="am-btn" type="submit"><?php esc_html_e( 'Search', 'ababil-members' ); ?></button>
  </form>
  <div class="am-grid">
    <?php foreach ( $users as $u ) : ?>
      <a class="am-card" href="<?php echo esc_url( AM_Profile::url( $u->user_login ) ); ?>">
        <div class="am-avatar"><?php echo esc_html( get_user_meta( $u->ID, 'am_avatar', true ) ?: '😀' ); ?></div>
        <b class="am-name" title="<?php echo esc_attr( $u->user_login ); ?>"><?php echo esc_html( $u->user_login ); ?></b>
        <div class="am-sub">⭐ <?php echo esc_html( am_get_points( $u->ID ) ); ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
