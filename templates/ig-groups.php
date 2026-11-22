<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$me = get_current_user_id();
$detail_id = isset( $_GET['group'] ) ? absint( $_GET['group'] ) : 0;
$base = remove_query_arg( 'group' );
?>
<div class="ig-plugin-container ig-app-page ig-groups-root" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-social-nonce="<?php echo esc_attr( wp_create_nonce( 'am_social' ) ); ?>" data-follow-nonce="<?php echo esc_attr( wp_create_nonce( 'am_follow' ) ); ?>" data-group-id="<?php echo (int) $detail_id; ?>" data-me="<?php echo (int) $me; ?>">
  <?php am_app_shell_open( 'home' ); ?>

<?php if ( $detail_id ) :
    $group = AM_Social::get_group( $detail_id );
    if ( ! $group ) : ?>
      <div class="ig-empty">Group not found. <a href="<?php echo esc_url( $base ); ?>">Back to Groups</a></div>
    <?php else :
        $private = ( 'private' === $group['privacy'] );
        $member = AM_Social::is_member( $detail_id, $me );
        $locked = $private && ! $member;
    ?>
      <div class="ig-group-detail-head">
        <a class="ig-back-link" href="<?php echo esc_url( $base ); ?>">← Groups</a>
        <div class="ig-group-detail-cover"><span>👥</span></div>
        <div class="ig-group-detail-info">
          <h2><?php echo esc_html( $group['name'] ); ?></h2>
          <p class="ig-group-desc"><?php echo esc_html( $group['description'] ); ?></p>
          <p class="ig-group-meta-line">
            <span class="ig-privacy-badge"><?php echo $private ? '🔒 Private' : '🌐 Public'; ?></span>
            <span class="ig-group-count" data-group-count="<?php echo (int) $detail_id; ?>">👤 <?php echo (int) AM_Social::group_count( $detail_id ); ?> members</span>
          </p>
          <?php if ( $me ) : ?>
            <button class="ig-btn <?php echo $member ? 'ig-btn-ghost' : 'ig-btn-primary'; ?> ig-group-join" data-group="<?php echo (int) $detail_id; ?>" data-joined="<?php echo $member ? '1' : '0'; ?>"><?php echo $member ? '✓ Joined' : 'Join group'; ?></button>
          <?php endif; ?>
        </div>
      </div>

      <?php if ( $locked ) : ?>
        <div class="ig-group-locked">
          <span class="ig-lock-icon">🔒</span>
          <h3>This group is private</h3>
          <p>Join this group to see its posts and members.</p>
        </div>
      <?php else :
        $gposts = AM_Social::group_posts( $detail_id );
        $member_ids = AM_Social::group_member_ids( $detail_id );
      ?>
        <?php if ( $member ) : ?>
          <form class="ig-composer ig-group-composer" id="ig-group-post-form">
            <div class="ig-composer-top"><span class="ig-composer-avatar">🙂</span><button type="button" class="ig-composer-open">Write something to this group...</button></div>
            <div class="ig-composer-body">
              <textarea class="ig-composer-input" name="body" maxlength="2000" placeholder="Write something to this group..."></textarea>
              <div class="ig-composer-tools"><button type="submit" class="ig-btn ig-btn-primary ig-composer-submit">Post</button></div>
            </div>
          </form>
        <?php endif; ?>
        <div class="ig-tabs ig-group-tabs" role="tablist">
          <button type="button" class="ig-tab is-active" data-tab="group-posts" role="tab">▤ <?php esc_html_e( 'Posts', 'ababil-members' ); ?></button>
          <button type="button" class="ig-tab" data-tab="group-members" role="tab">👤 <?php esc_html_e( 'Members', 'ababil-members' ); ?> <span class="ig-group-tab-count"><?php echo (int) count( $member_ids ); ?></span></button>
        </div>
        <div class="ig-tab-panel is-active" data-panel="group-posts">
          <div class="ig-feed-list" id="ig-group-feed">
            <?php if ( $gposts ) : foreach ( $gposts as $p ) echo AM_Social::render_post( $p, $me ); // phpcs:ignore ?>
            <?php else : ?><div class="ig-empty">No group posts yet.</div><?php endif; ?>
          </div>
        </div>
        <div class="ig-tab-panel" data-panel="group-members">
          <div class="ig-member-list">
            <?php foreach ( $member_ids as $mid ) :
              $mu = get_userdata( (int) $mid ); if ( ! $mu ) continue;
              $mav = get_user_meta( (int) $mid, 'am_avatar_url', true );
              $mem = get_user_meta( (int) $mid, 'am_avatar', true ) ?: '🙂';
            ?>
              <a class="ig-member-chip" href="<?php echo esc_url( AM_Profile::url( $mu->user_login ) ); ?>">
                <span class="ig-member-avatar"><?php echo $mav ? '<img src="' . esc_url( $mav ) . '" alt="">' : esc_html( $mem ); ?></span>
                <strong><?php echo esc_html( $mu->user_login ); ?></strong>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

<?php else :
    $groups = AM_Social::groups();
?>
  <?php if ( $me ) : ?>
    <form class="ig-group-create-form">
      <input name="name" maxlength="120" required placeholder="Group name">
      <input name="description" maxlength="500" placeholder="What is this group about?">
      <select name="privacy"><option value="public">🌐 Public</option><option value="private">🔒 Private</option></select>
      <button class="ig-btn ig-btn-primary">Create group</button>
    </form>
  <?php endif; ?>
  <div class="ig-groups-grid">
    <?php if ( $groups ) : foreach ( $groups as $group ) :
        $gid = (int) $group['id'];
        $joined = AM_Social::is_member( $gid, $me );
        $private = ( 'private' === $group['privacy'] );
    ?>
      <article class="ig-group-card">
        <div class="ig-group-cover"><span>👥</span><span class="ig-privacy-badge"><?php echo $private ? '🔒 Private' : '🌐 Public'; ?></span></div>
        <div class="ig-group-card-body">
          <h3><?php echo esc_html( $group['name'] ); ?></h3>
          <p><?php echo esc_html( $group['description'] ); ?></p>
          <p class="ig-group-count" data-group-count="<?php echo esc_attr( $gid ); ?>">👤 <?php echo (int) AM_Social::group_count( $gid ); ?> members</p>
          <div class="ig-group-card-actions">
            <a class="ig-btn ig-btn-ghost" href="<?php echo esc_url( add_query_arg( 'group', $gid, $base ) ); ?>">Preview</a>
            <?php if ( $me ) : ?>
              <button class="ig-btn <?php echo $joined ? 'ig-btn-ghost' : 'ig-btn-primary'; ?> ig-group-join" data-group="<?php echo $gid; ?>" data-joined="<?php echo $joined ? '1' : '0'; ?>"><?php echo $joined ? '✓ Joined' : 'Join group'; ?></button>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; else : ?><div class="ig-empty">No groups yet. Create the first one!</div><?php endif; ?>
  </div>
<?php endif; ?>
  <?php am_app_shell_close(); ?>
</div>
