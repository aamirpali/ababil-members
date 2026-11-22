<?php if ( ! defined( 'ABSPATH' ) ) exit;
$me = get_current_user_id();
$to_name = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
$to_user = $to_name ? get_user_by( 'login', $to_name ) : false;
?>
<div class="am-messages">
  <h2>✉️ <?php esc_html_e( 'Messages', 'ababil-members' ); ?></h2>
  <?php if ( ! empty( $GLOBALS['am_message_error'] ) ) : ?>
    <p class="am-error"><?php echo esc_html( $GLOBALS['am_message_error'] ); ?></p>
  <?php endif; ?>
  <?php if ( ! empty( $_GET['sent'] ) ) : ?>
    <p class="am-notice">✅ <?php esc_html_e( 'Message sent.', 'ababil-members' ); ?></p>
  <?php endif; ?>

  <?php if ( $to_user && (int) $to_user->ID !== $me ) : ?>
    <h3><?php esc_html_e( 'Conversation with', 'ababil-members' ); ?> <?php echo esc_html( $to_user->display_name ); ?></h3>
    <?php foreach ( AM_Messaging::conversation( $me, $to_user->ID ) as $m ) : ?>
      <div class="am-msg <?php echo (int) $m->from_id === $me ? 'mine' : 'theirs'; ?>">
        <?php echo nl2br( esc_html( $m->body ) ); ?><small><?php echo esc_html( $m->created ); ?></small>
      </div>
    <?php endforeach; ?>
    <form method="post" class="am-form">
      <?php wp_nonce_field( 'am_message', 'am_nonce' ); ?>
      <input type="hidden" name="to_id" value="<?php echo (int) $to_user->ID; ?>">
      <textarea name="body" rows="4" maxlength="2000" required placeholder="<?php esc_attr_e( 'Write a message...', 'ababil-members' ); ?>"></textarea>
      <p><button class="am-btn" type="submit" name="am_message_send" value="1"><?php esc_html_e( 'Send', 'ababil-members' ); ?></button></p>
    </form>
  <?php else : ?>
    <?php $threads = AM_Messaging::threads( $me ); ?>
    <?php if ( $threads ) : ?>
      <div class="am-threads">
        <?php foreach ( $threads as $m ) : ?>
          <a class="am-thread" href="<?php echo esc_url( am_page_url( 'am-messages' ) . '?to=' . rawurlencode( $m->user_login ) ); ?>">
            <b><?php echo esc_html( $m->user_login ); ?></b>
            <span><?php echo esc_html( mb_substr( $m->body, 0, 60 ) ); ?></span>
            <small><?php echo esc_html( $m->created ); ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else : ?>
      <p class="am-sub"><?php esc_html_e( 'No messages yet. Visit a member profile to send one.', 'ababil-members' ); ?></p>
    <?php endif; ?>
  <?php endif; ?>
</div>
