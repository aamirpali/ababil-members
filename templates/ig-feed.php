<?php if ( ! defined( 'ABSPATH' ) ) exit;
$me = get_current_user_id();
$posts = AM_Social::feed( 30 );
$u = $me ? get_userdata( $me ) : null;
$name = $u ? $u->user_login : '';
$avatar_url = $me ? get_user_meta( $me, 'am_avatar_url', true ) : '';

// Active stories (last 24h) grouped by member, newest story first.
$story_rows = AM_Social::stories( 60 );
$story_groups = array();
foreach ( $story_rows as $s ) {
    $uid = (int) $s['user_id'];
    if ( ! isset( $story_groups[ $uid ] ) ) {
        $su  = get_userdata( $uid );
        $sav = get_user_meta( $uid, 'am_avatar_url', true );
        $story_groups[ $uid ] = array(
            'user'   => $uid,
            'name'   => $su ? $su->user_login : __( 'member', 'ababil-members' ),
            'avatar' => $sav ? $sav : ( get_user_meta( $uid, 'am_avatar', true ) ?: '🙂' ),
            'own'    => ( $uid === $me ),
            'items'  => array(),
        );
    }
    $story_groups[ $uid ]['items'][] = array(
        'id'      => (int) $s['id'],
        'image'   => $s['image_url'],
        'caption' => (string) $s['caption'],
        'time'    => human_time_diff( strtotime( $s['created'] ), current_time( 'timestamp' ) ),
        'views'   => AM_Social::story_views( (int) $s['id'] ),
    );
}
$story_groups = array_values( $story_groups );
?>
<div class="ig-plugin-container ig-app-page ig-feed-root" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-social-nonce="<?php echo esc_attr( wp_create_nonce( 'am_social' ) ); ?>" data-follow-nonce="<?php echo esc_attr( wp_create_nonce( 'am_follow' ) ); ?>">
  <?php am_app_shell_open( 'feed' ); ?>

  <?php if ( $me ) : ?>
  <!-- Stories -->
  <div class="ig-stories" id="ig-stories" data-stories="<?php echo esc_attr( wp_json_encode( $story_groups ) ); ?>">
    <button type="button" class="ig-story ig-story-add" id="ig-story-open">
      <span class="ig-story-add-icon">＋</span>
      <span class="ig-story-name"><?php esc_html_e( 'Add Story', 'ababil-members' ); ?></span>
    </button>
    <?php foreach ( $story_groups as $i => $g ) echo AM_Social::story_card( $g['user'], $g['items'][0]['image'], $i, $g['own'] ); // phpcs:ignore ?>
  </div>

  <!-- Composer -->
  <form class="ig-composer" id="ig-post-form">
    <div class="ig-composer-top">
      <span class="ig-composer-avatar"><?php if ( $avatar_url ) : ?><img src="<?php echo esc_url( $avatar_url ); ?>" alt=""><?php else : ?><?php echo esc_html( get_user_meta( $me, 'am_avatar', true ) ?: '🙂' ); ?><?php endif; ?></span>
      <button type="button" class="ig-composer-open"><?php echo esc_html( sprintf( __( "What's new, %s?", 'ababil-members' ), $name ) ); ?></button>
      <button type="submit" class="ig-btn ig-btn-primary ig-composer-quick">＋ <?php esc_html_e( 'Post it!', 'ababil-members' ); ?></button>
    </div>
    <div class="ig-composer-body">
      <textarea class="ig-composer-input" name="body" maxlength="2000" placeholder="<?php echo esc_attr( sprintf( __( "What's new, %s?", 'ababil-members' ), $name ) ); ?>"></textarea>
      <label class="ig-dropzone" id="ig-post-drop">
        <span class="ig-drop-icon">📷</span>
        <span class="ig-drop-text"><strong><?php esc_html_e( 'Drop a photo', 'ababil-members' ); ?></strong> <?php esc_html_e( 'or click to browse', 'ababil-members' ); ?> <small><?php esc_html_e( '(max 1 MB)', 'ababil-members' ); ?></small></span>
        <input type="file" id="ig-post-image" name="image" accept="image/*" hidden>
      </label>
      <div class="ig-post-preview" hidden>
        <img class="ig-post-preview-img" alt="">
        <button type="button" class="ig-post-preview-remove" aria-label="<?php echo esc_attr__( 'Remove photo', 'ababil-members' ); ?>">✕</button>
      </div>
      <div class="ig-composer-tools">
        <div class="ig-feeling-wrap">
          <button type="button" class="ig-composer-tool ig-composer-feeling" id="ig-feeling-btn"><span class="ig-tool-icon">😊</span> <span class="ig-feeling-btn-label"><?php esc_html_e( 'Feeling', 'ababil-members' ); ?></span></button>
          <div class="ig-feeling-picker" hidden>
            <?php foreach ( AM_Social::feelings() as $fkey => $f ) : ?>
              <button type="button" class="ig-feeling-option" data-feeling="<?php echo esc_attr( $fkey ); ?>"><span><?php echo esc_html( $f[0] ); ?></span><small><?php echo esc_html( $f[1] ); ?></small></button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="feeling" value="" class="ig-feeling-value">
        </div>
        <span class="ig-composer-count">0 / 2000</span>
        <button type="submit" class="ig-btn ig-btn-primary ig-composer-submit"><?php esc_html_e( 'Post', 'ababil-members' ); ?></button>
      </div>
    </div>
    <div class="ig-composer-msg" hidden></div>
  </form>
  <?php endif; ?>

  <div class="ig-feed-list" id="ig-feed-list">
    <?php if ( $posts ) : foreach ( $posts as $p ) echo AM_Social::render_post( $p, $me ); // phpcs:ignore ?>
    <?php else : ?><div class="ig-empty"><?php esc_html_e( 'No posts yet. Be the first!', 'ababil-members' ); ?> ✨</div><?php endif; ?>
  </div>

  <?php if ( $me ) : ?>
  <!-- Add-story modal -->
  <div class="ig-modal ig-story-add-modal" hidden>
    <div class="ig-modal-card">
      <div class="ig-modal-head"><h3><?php esc_html_e( 'Create story', 'ababil-members' ); ?></h3><button type="button" class="ig-modal-close" aria-label="<?php echo esc_attr__( 'Close', 'ababil-members' ); ?>">✕</button></div>
      <form class="ig-story-form">
        <label class="ig-dropzone ig-story-drop">
          <span class="ig-drop-icon">📷</span>
          <span class="ig-drop-text"><strong><?php esc_html_e( 'Drop a photo', 'ababil-members' ); ?></strong> <?php esc_html_e( 'or click to browse', 'ababil-members' ); ?> <small><?php esc_html_e( '(max 2 MB)', 'ababil-members' ); ?></small></span>
          <input type="file" id="ig-story-image" name="image" accept="image/*" hidden>
        </label>
        <div class="ig-story-preview" hidden><img alt=""></div>
        <input type="text" name="caption" class="ig-story-caption" placeholder="<?php echo esc_attr__( 'Add a caption', 'ababil-members' ); ?>" maxlength="200">
        <div class="ig-story-msg" hidden></div>
        <button type="submit" class="ig-btn ig-btn-primary ig-story-submit"><?php esc_html_e( 'Share story', 'ababil-members' ); ?></button>
      </form>
    </div>
  </div>

  <!-- Story viewer -->
  <div class="ig-modal ig-story-viewer" hidden>
    <div class="ig-story-viewer-card">
      <div class="ig-story-progress"></div>
      <div class="ig-story-viewer-head">
        <span class="ig-story-viewer-avatar"></span>
        <span class="ig-story-viewer-meta"><strong></strong><small></small></span>
        <button type="button" class="ig-story-viewer-delete" hidden><?php esc_html_e( 'Delete', 'ababil-members' ); ?></button>
        <button type="button" class="ig-story-viewer-insights" id="ig-story-insights" hidden>👁 <span class="ig-story-views">0</span></button>
        <button type="button" class="ig-story-viewer-close" aria-label="<?php echo esc_attr__( 'Close', 'ababil-members' ); ?>">✕</button>
      </div>
      <img class="ig-story-viewer-img" alt="">
      <div class="ig-story-viewer-caption"></div>
      <button type="button" class="ig-story-nav ig-story-prev" aria-label="<?php echo esc_attr__( 'Previous', 'ababil-members' ); ?>">‹</button>
      <button type="button" class="ig-story-nav ig-story-next" aria-label="<?php echo esc_attr__( 'Next', 'ababil-members' ); ?>">›</button>
    </div>
    <div class="ig-story-insights-panel" hidden>
      <div class="ig-story-insights-head"><strong><?php esc_html_e( 'Story viewers', 'ababil-members' ); ?></strong><span class="ig-story-insights-count"></span></div>
      <div class="ig-story-insights-list" data-story-viewer-list></div>
    </div>
  </div>
  <?php endif; ?>

  <?php am_app_shell_close(); ?>
</div>
