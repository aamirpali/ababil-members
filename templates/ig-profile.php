<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$user = isset( $GLOBALS['am_profile_user'] ) ? $GLOBALS['am_profile_user'] : null;
if ( ! $user && is_user_logged_in() ) { $user = wp_get_current_user(); }

if ( ! $user ) : ?>
  <div class="ig-plugin-container">
    <div class="ig-empty">Please log in to view this profile.</div>
  </div>
<?php else:
  $uid       = (int) $user->ID;
  $me        = get_current_user_id();
  $own       = ( $me === $uid );
  $avatar    = get_user_meta( $uid, 'am_avatar', true ) ?: '🙂';
  $about     = get_user_meta( $uid, 'description', true );
  $counts    = am_get_option( 'followers', 1 ) ? AM_Followers::counts( $uid ) : array( 'followers' => 0, 'following' => 0 );
  $following = am_get_option( 'followers', 1 ) && $me ? AM_Followers::is_following( $me, $uid ) : false;
  $verified  = ( $user->roles && in_array( 'administrator', (array) $user->roles, true ) ) || am_get_points( $uid ) >= 200;
  $badgeSvg  = '<svg class="ig-verified" viewBox="0 0 24 24" role="img" aria-label="Verified"><path fill="#0095f6" d="M12 0C5.37 0 0 5.37 0 12s5.37 12 12 12 12-5.37 12-12S18.63 0 12 0zm6.7 9.3-7.8 7.8c-.5.5-1.3.5-1.8 0l-3.8-3.8c-.5-.5-.5-1.3 0-1.8s1.3-.5 1.8 0l2.9 2.9 6.9-6.9c.5-.5 1.3-.5 1.8 0s.5 1.3 0 1.8z"/></svg>';
  $age       = get_user_meta( $uid, 'am_age', true );
  $gender    = get_user_meta( $uid, 'am_gender', true );
  $location  = get_user_meta( $uid, 'am_location', true );
  $metaBits  = array();
  foreach ( array( $age, $gender, $location ) as $bit ) { if ( $bit && 'Prefer not to say' !== $bit ) { $metaBits[] = $bit; } }
  $avatarUrl = get_user_meta( $uid, 'am_avatar_url', true );
  $reg_setup = am_registration_settings();
  if ( ! $avatarUrl && ! empty( $reg_setup['gravatar'] ) ) $avatarUrl = get_avatar_url( $uid );
  $coverUrl  = get_user_meta( $uid, 'am_cover_url', true );
  $coverX    = get_user_meta( $uid, 'am_cover_pos_x', true );
  $coverY    = get_user_meta( $uid, 'am_cover_pos_y', true );
  $coverX    = ( '' === $coverX ) ? 50 : max( 0, min( 100, (int) $coverX ) );
  $coverY    = ( '' === $coverY ) ? 50 : max( 0, min( 100, (int) $coverY ) );
  $profile_posts = AM_Social::feed( 30, $uid );
  $cameraSvg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M9 3 7.2 5H4a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-3.2L15 3H9zm3 5a5 5 0 1 1 0 10 5 5 0 0 1 0-10z"/></svg>';
?>
  <div class="ig-plugin-container ig-app-page" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-follow-nonce="<?php echo esc_attr( wp_create_nonce( 'am_follow' ) ); ?>" data-social-nonce="<?php echo esc_attr( wp_create_nonce( 'am_social' ) ); ?>">
    <?php am_app_shell_open( 'profile' ); ?>
    <?php if ( $own && isset( $_GET['am_saved'] ) ) : ?>
      <p class="ig-notice">✅ <?php esc_html_e( 'Profile saved.', 'ababil-members' ); ?></p>
    <?php endif; ?>
    <?php if ( $own && get_user_meta( $uid, 'am_pw_expired', true ) ) : ?>
      <p class="ig-notice">🔒 <?php esc_html_e( 'Your password has expired.', 'ababil-members' ); ?> <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Reset it', 'ababil-members' ); ?></a></p>
    <?php endif; ?>
    <div class="ig-profile-layout">
    <div class="ig-profile-left">
    <div class="ig-cover">
      <?php if ( $coverUrl ) : ?>
        <img class="ig-cover-img" src="<?php echo esc_url( $coverUrl ); ?>" alt="" style="object-position:<?php echo (int) $coverX; ?>% <?php echo (int) $coverY; ?>%;">
      <?php endif; ?>
      <?php if ( $own ) : ?>
        <label class="ig-camera-btn ig-cover-camera" for="ig-cover-file" title="<?php echo esc_attr__( 'Change cover photo', 'ababil-members' ); ?>"><?php echo $cameraSvg; // phpcs:ignore ?> <span class="ig-camera-label-text"><?php esc_html_e( 'Change cover', 'ababil-members' ); ?></span></label>
        <input class="ig-sr-only ig-file-input" type="file" id="ig-cover-file" name="am_cover_file" accept="image/*" form="ig-edit-form">
      <?php endif; ?>
    </div>

    <div class="ig-profile-header">
      <div class="ig-avatar-ring">
        <div class="ig-avatar-inner">
          <?php if ( $avatarUrl ) : ?>
            <img class="ig-avatar-img" src="<?php echo esc_url( $avatarUrl ); ?>" alt="">
          <?php else : ?>
            <?php echo esc_html( $avatar ); ?>
          <?php endif; ?>
        </div>
        <?php if ( $own ) : ?>
          <label class="ig-camera-btn ig-avatar-camera" for="ig-avatar-file" title="<?php echo esc_attr__( 'Change profile photo', 'ababil-members' ); ?>"><?php echo $cameraSvg; // phpcs:ignore ?></label>
          <input class="ig-sr-only ig-file-input" type="file" id="ig-avatar-file" name="am_avatar_file" accept="image/*" form="ig-edit-form">
        <?php endif; ?>
      </div>

      <div class="ig-profile-main">
        <div class="ig-username-row">
          <span class="ig-username"><?php echo esc_html( $user->user_login ); ?></span>
          <?php if ( $verified ) echo $badgeSvg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>

        <div class="ig-actions">
          <?php if ( $own ) : ?>
            <button type="button" class="ig-btn ig-edit-toggle">✏️ Edit Profile</button>
            <a class="ig-btn ig-btn-ghost" href="<?php echo esc_url( am_page_url( 'am-logout' ) ); ?>">↪ Log out</a>
          <?php else : ?>
            <?php if ( am_get_option( 'followers', 1 ) ) : ?>
              <?php if ( $me ) : ?>
                <button type="button" class="ig-btn <?php echo $following ? 'is-following' : 'ig-btn-primary'; ?> ig-follow-btn" data-user="<?php echo (int) $uid; ?>" data-following="<?php echo $following ? '1' : '0'; ?>"><?php echo $following ? 'Following' : 'Follow'; ?></button>
              <?php else : ?>
                <a class="ig-btn ig-btn-primary" href="<?php echo esc_url( am_page_url( 'am-login' ) ); ?>">Follow</a>
              <?php endif; ?>
            <?php endif; ?>
            <?php if ( am_get_option( 'messaging', 1 ) && $me ) : ?>
              <a class="ig-btn ig-btn-ghost" href="<?php echo esc_url( am_page_url( 'am-messages' ) . '?to=' . rawurlencode( $user->user_login ) ); ?>">✉️ Message</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <div class="ig-stats">
           <div class="ig-stat"><span class="ig-stat-num"><?php echo count( $profile_posts ); ?></span><span class="ig-stat-label">posts</span></div>
          <?php if ( am_get_option( 'followers', 1 ) ) : ?>
            <div class="ig-stat ig-stat-followers"><span class="ig-stat-num"><?php echo (int) $counts['followers']; ?></span><span class="ig-stat-label">followers</span></div>
            <div class="ig-stat"><span class="ig-stat-num"><?php echo (int) $counts['following']; ?></span><span class="ig-stat-label">following</span></div>
          <?php endif; ?>
        </div>

        <?php if ( $about || $user->display_name ) : ?>
          <div class="ig-bio">
            <div class="ig-name"><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></div>
            <?php if ( $about ) : ?><p class="ig-bio-text"><?php echo esc_html( $about ); ?></p><?php endif; ?>
            <?php if ( $metaBits ) : ?><div class="ig-bio-text ig-muted"><?php echo esc_html( implode( ' · ', $metaBits ) ); ?></div><?php endif; ?>
            <?php foreach ( AM_Fields::all() as $cf ) :
                if ( 'public' !== ( $cf['visibility'] ?? 'public' ) ) continue;
                $fv = AM_Fields::value( $uid, $cf['slug'] );
                if ( '' === $fv ) continue; ?>
                <div class="ig-bio-text"><strong><?php echo esc_html( $cf['label'] ); ?>:</strong> <?php echo esc_html( $fv ); ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ( $own ) : ?>
      <div class="ig-edit-panel <?php echo ! empty( $GLOBALS['am_profile_error'] ) ? 'is-open' : ''; ?>">
        <form method="post" id="ig-edit-form" enctype="multipart/form-data">
          <?php wp_nonce_field( 'am_profile', 'am_nonce' ); ?>
          <input type="hidden" name="am_action" value="profile">
          <?php if ( ! empty( $GLOBALS['am_profile_error'] ) ) : ?>
            <p class="ig-error"><?php echo esc_html( $GLOBALS['am_profile_error'] ); ?></p>
          <?php endif; ?>
          <div class="ig-crop-wrap <?php echo $coverUrl ? 'is-visible' : ''; ?>" id="ig-crop-wrap">
            <p class="ig-edit-label">Drag the cover to adjust its position</p>
            <div class="ig-crop-stage" id="ig-crop-stage">
              <img class="ig-crop-img" id="ig-crop-img" src="<?php echo $coverUrl ? esc_url( $coverUrl ) : ''; ?>" alt="" style="object-position:<?php echo (int) $coverX; ?>% <?php echo (int) $coverY; ?>%;">
            </div>
            <p class="ig-muted" style="font-size:12px;margin:6px 0 12px">Minimum width 1024px. Drag the image to choose the visible area, then save.</p>
            <input type="hidden" name="am_cover_pos_x" id="ig-cover-pos-x" value="<?php echo (int) $coverX; ?>">
            <input type="hidden" name="am_cover_pos_y" id="ig-cover-pos-y" value="<?php echo (int) $coverY; ?>">
          </div>
          <div class="ig-edit-row">
            <label class="ig-edit-label" for="ig-display-name">Display name</label>
            <input class="ig-edit-input" id="ig-display-name" type="text" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>">
          </div>
          <div class="ig-edit-row">
            <label class="ig-edit-label" for="ig-about">About you</label>
            <textarea class="ig-edit-textarea" id="ig-about" name="description"><?php echo esc_textarea( $about ); ?></textarea>
          </div>
          <?php
          $ig_fields = array(
              array( 'am_age', 'Age', array( 'Prefer not to say','5-9','10-14','15-19','20-29','30-39','40-49','50-59','60+' ), $age ),
              array( 'am_gender', 'Gender', array( 'Prefer not to say','Female','Male','Non-binary','Other' ), $gender ),
              array( 'am_location', 'Location', array( 'Prefer not to say','India','Pakistan','UAE','Saudi Arabia','United Kingdom','United States','Canada','Australia','France','Germany','Spain','Other' ), $location ),
          );
          foreach ( $ig_fields as $ig_field ) : ?>
            <div class="ig-edit-row">
              <label class="ig-edit-label" for="ig-<?php echo esc_attr( $ig_field[0] ); ?>"><?php echo esc_html( $ig_field[1] ); ?></label>
              <select class="ig-edit-input" id="ig-<?php echo esc_attr( $ig_field[0] ); ?>" name="<?php echo esc_attr( $ig_field[0] ); ?>">
                <?php foreach ( $ig_field[2] as $ig_option ) : ?>
                  <option value="<?php echo esc_attr( $ig_option ); ?>" <?php selected( $ig_option, $ig_field[3] ); ?>><?php echo esc_html( $ig_option ); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
          <?php
          $custom_fields = AM_Fields::all();
          $custom_values = array();
          foreach ( $custom_fields as $cf ) { $custom_values[ $cf['slug'] ] = AM_Fields::value( $uid, $cf['slug'] ); }
          AM_Fields::render_inputs( $custom_fields, $custom_values, 'profile-' );
          ?>
          <p class="ig-muted" style="font-size:12px;margin:4px 0 12px">Profile photo and cover are optional — leave empty to keep the current image.</p>
          <button class="ig-btn ig-btn-primary" type="submit">Save Profile</button>
        </form>
      </div>
    <?php endif; ?>

    <div class="ig-tabs" role="tablist">
      <button type="button" class="ig-tab is-active" data-tab="grid" role="tab">▦ Grid</button>
      <button type="button" class="ig-tab" data-tab="reels" role="tab">▶ Reels</button>
      <button type="button" class="ig-tab" data-tab="reviews" role="tab">⭐ Reviews</button>
      <?php foreach ( am_visible_tabs() as $tab ) : ?>
        <button type="button" class="ig-tab" data-tab="<?php echo esc_attr( am_tab_key( $tab ) ); ?>" role="tab"><?php echo esc_html( ( isset( $tab['icon'] ) ? $tab['icon'] . ' ' : '' ) . $tab['label'] ); ?></button>
      <?php endforeach; ?>
      <?php if ( $own ) : ?>
        <button type="button" class="ig-tab" data-tab="account" role="tab">⚙️ Account</button>
      <?php endif; ?>
    </div>

    <div class="ig-tab-panel is-active" data-panel="grid">
       <?php if ( $profile_posts ) : foreach ( $profile_posts as $post ) echo AM_Social::render_post( $post, $me ); // phpcs:ignore ?>
       <?php else : ?><div class="ig-empty">No posts yet ✨</div><?php endif; ?>
    </div>

    <div class="ig-tab-panel" data-panel="reels">
      <div class="ig-empty">No reels yet 🎞️</div>
    </div>

    <div class="ig-tab-panel" data-panel="reviews">
      <?php $reviews = AM_Social::reviews( $uid ); ?>
      <?php if ( $me && ! $own ) : ?>
        <form class="ig-review-form">
          <input type="hidden" name="target_id" value="<?php echo (int) $uid; ?>">
          <select name="rating"><option value="5">★★★★★</option><option value="4">★★★★</option><option value="3">★★★</option><option value="2">★★</option><option value="1">★</option></select>
          <textarea name="body" maxlength="300" placeholder="Write a review for <?php echo esc_attr( $user->user_login ); ?>..."></textarea>
          <button type="submit" class="ig-btn ig-btn-primary">Submit review</button>
        </form>
      <?php endif; ?>
      <div class="ig-review-list">
        <?php foreach ( $reviews as $review ) : ?>
          <article class="ig-review"><strong><?php echo esc_html( get_the_author_meta( 'user_login', $review['user_id'] ) ); ?></strong> <span><?php echo str_repeat( '★', (int) $review['rating'] ); ?></span><p><?php echo esc_html( $review['body'] ); ?></p></article>
        <?php endforeach; if ( ! $reviews ) : ?><div class="ig-empty">No reviews yet.</div><?php endif; ?>
      </div>
    </div>

    <?php foreach ( am_visible_tabs() as $tab ) : ?>
      <div class="ig-tab-panel" data-panel="<?php echo esc_attr( am_tab_key( $tab ) ); ?>">
        <?php echo do_shortcode( wp_kses_post( isset( $tab['content'] ) ? $tab['content'] : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </div>
    <?php endforeach; ?>

    <?php if ( $own ) : ?>
      <div class="ig-tab-panel" data-panel="account">
        <?php include AM_PATH . 'templates/ig-account.php'; ?>
      </div>
    <?php endif; ?>
    </div><!-- /ig-profile-left -->
    <aside class="ig-profile-right">
      <div class="ig-app-block">
        <div class="ig-profile-sidebar-header"><?php esc_html_e( 'Member details', 'ababil-members' ); ?></div>
        <div class="ig-bio-text"><strong><?php esc_html_e( 'Member since', 'ababil-members' ); ?>:</strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) ); ?></div>
        <div class="ig-bio-text"><strong><?php esc_html_e( 'Points', 'ababil-members' ); ?>:</strong> <?php echo (int) am_get_points( $uid ); ?></div>
        <?php if ( $verified ) : ?><div class="ig-bio-text">✔ <?php esc_html_e( 'Verified member', 'ababil-members' ); ?></div><?php endif; ?>
      </div>
      <?php $u_groups = AM_Social::user_groups( $uid ); if ( $u_groups ) : ?>
      <div class="ig-app-block ig-profile-groups">
        <div class="ig-profile-sidebar-header"><?php esc_html_e( 'Groups', 'ababil-members' ); ?></div>
        <?php foreach ( $u_groups as $ug ) : ?>
          <a class="ig-profile-group-item" href="<?php echo esc_url( add_query_arg( 'group', (int) $ug['id'], am_page_url( 'am-groups' ) ) ); ?>">
            <span class="ig-profile-group-avatar">👥</span>
            <span class="ig-profile-group-info">
              <span class="ig-profile-group-name"><?php echo esc_html( $ug['name'] ); ?></span>
              <span class="ig-profile-group-members"><?php echo (int) AM_Social::group_count( (int) $ug['id'] ); ?> <?php esc_html_e( 'members', 'ababil-members' ); ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </aside>
    </div><!-- /ig-profile-layout -->
    <?php am_app_shell_close(); ?>
  </div>
<?php endif; ?>
