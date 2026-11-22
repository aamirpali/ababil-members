<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$me = get_current_user_id();
if ( ! $me ) : ?>
  <div class="ig-plugin-container">
    <div class="ig-empty">Please <a href="<?php echo esc_url( am_page_url( 'am-login' ) ); ?>">log in</a> to view messages.</div>
  </div>
<?php else:
  $threads = AM_Messaging::threads( $me );
  $toName  = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
  $other   = $toName ? get_user_by( 'login', $toName ) : false;
  $conversation = ( $other && (int) $other->ID !== $me ) ? AM_Messaging::conversation( $me, $other->ID ) : array();
  $base    = am_page_url( 'am-messages' );
  $lastId  = 0;
  foreach ( $conversation as $c ) { $lastId = max( $lastId, (int) $c['id'] ); }
  $otherId = $other ? (int) $other->ID : 0;
  $chatNonce = wp_create_nonce( 'am_chat' );
  $emojis  = array( '😀','😂','🥰','😍','😎','😢','😡','👍','👎','👏','🙏','💪','❤️','🔥','✨','🎉','👍🏻','🤝','👀','💡','✅','❌','🚀','🌈','🍕','☕','🎂','🐱','🐶','🌟' );
?>
  <div class="ig-plugin-container ig-app-page ig-messages-wrap"
       data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
       data-chat-nonce="<?php echo esc_attr( $chatNonce ); ?>"
       data-follow-nonce="<?php echo esc_attr( wp_create_nonce( 'am_follow' ) ); ?>"
       data-me="<?php echo (int) $me; ?>"
       data-other="<?php echo (int) $otherId; ?>"
       data-last-id="<?php echo (int) $lastId; ?>">
    <?php am_app_shell_open( 'home' ); ?>
    <div class="ig-messages">
      <aside class="ig-chat-list">
        <div class="ig-chat-list-head">
          <span>Messages</span>
          <span class="ig-muted"><?php echo count( $threads ); ?></span>
        </div>
        <?php if ( $threads ) : ?>
          <?php foreach ( $threads as $m ) :
            $peerId = ( (int) $m->from_id === $me ) ? (int) $m->to_id : (int) $m->from_id;
            $active = ( $other && (int) $other->ID === $peerId );
            $avatar = get_user_meta( $peerId, 'am_avatar', true ) ?: '🙂';
          ?>
            <a class="ig-chat-item <?php echo $active ? 'is-active' : ''; ?>"
               href="<?php echo esc_url( $base . '?to=' . rawurlencode( $m->user_login ) ); ?>"
               data-name="<?php echo esc_attr( $m->user_login ); ?>" data-peer="<?php echo (int) $peerId; ?>">
              <span class="ig-chat-avatar"><?php echo esc_html( $avatar ); ?></span>
              <span class="ig-chat-meta">
                <span class="ig-chat-name"><?php echo esc_html( $m->user_login ); ?></span>
                <span class="ig-chat-preview"><?php echo esc_html( mb_substr( $m->body, 0, 48 ) ); ?></span>
              </span>
              <span class="ig-chat-time"><?php echo esc_html( mb_substr( $m->created, 5, 5 ) ); ?></span>
            </a>
          <?php endforeach; ?>
        <?php else : ?>
          <div class="ig-empty">No messages yet.</div>
        <?php endif; ?>
      </aside>

      <section class="ig-chat-pane">
        <div class="ig-chat-head">
          <button type="button" class="ig-back-btn" aria-label="Back to messages">‹</button>
          <?php if ( $other ) : ?>
            <span class="ig-chat-avatar ig-chat-head-avatar"><?php echo esc_html( get_user_meta( $other->ID, 'am_avatar', true ) ?: '🙂' ); ?></span>
          <?php endif; ?>
          <span class="ig-chat-head-user">
            <span class="ig-chat-head-name"><?php echo $other ? esc_html( $other->user_login ) : 'Select a chat'; ?></span>
            <?php if ( $other ) : ?><small class="ig-encrypted">🔒 End-to-end encrypted</small><?php endif; ?>
          </span>
          <?php if ( $other ) : ?>
            <button type="button" class="ig-report-btn" title="<?php echo esc_attr__( 'Report conversation', 'ababil-members' ); ?>">⚑ Report</button>
          <?php endif; ?>
        </div>

        <div class="ig-chat-body" id="ig-chat-body">
          <?php if ( $other ) : ?>
            <div class="ig-encrypted-banner">
              <span class="ig-lock">🔒</span>
              <strong><?php esc_html_e( 'End-to-end encrypted', 'ababil-members' ); ?></strong>
              <small><?php echo esc_html( sprintf( __( 'Messages and images stay between you and %s.', 'ababil-members' ), $other->user_login ) ); ?></small>
            </div>
          <?php endif; ?>
          <?php if ( $other && $conversation ) : ?>
            <?php foreach ( $conversation as $row ) echo AM_Messaging::render_row( $row, $me ); // phpcs:ignore ?>
          <?php elseif ( $other ) : ?>
            <div class="ig-empty">Say hello to <?php echo esc_html( $other->user_login ); ?> 👋</div>
          <?php else : ?>
            <div class="ig-empty">Select a conversation to start chatting.</div>
          <?php endif; ?>
          <div class="ig-typing" id="ig-typing" hidden><span class="ig-typing-dots"><i></i><i></i><i></i></span> typing...</div>
        </div>

        <?php if ( $other ) : ?>
          <div class="ig-chat-composer">
            <div class="ig-emoji-panel" id="ig-emoji-panel" hidden>
              <?php foreach ( $emojis as $em ) : ?>
                <button type="button" class="ig-emoji" data-emoji="<?php echo esc_attr( $em ); ?>"><?php echo esc_html( $em ); ?></button>
              <?php endforeach; ?>
            </div>
            <form class="ig-chat-form" id="ig-chat-form">
              <button type="button" class="ig-emoji-toggle" title="Emoji" aria-label="Emoji">😊</button>
              <label class="ig-image-toggle" title="Send image (max 1 MB)" aria-label="Send image">📎
                <input type="file" id="ig-chat-image" accept="image/*" hidden>
              </label>
              <label class="ig-sr-only" for="ig-chat-input">Message</label>
              <input class="ig-chat-input" id="ig-chat-input" type="text" name="body" maxlength="2000" placeholder="Message..." autocomplete="off">
              <button class="ig-send-btn" type="submit">Send</button>
            </form>
            <div class="ig-chat-note">🔒 Messages are end-to-end encrypted. Images up to 1 MB.</div>
          </div>
        <?php endif; ?>
      </section>
    </div>
    <?php am_app_shell_close(); ?>
  </div>
<?php endif; ?>
