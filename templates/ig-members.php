<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$me     = get_current_user_id();
$search = isset( $_GET['am_q'] ) ? sanitize_text_field( wp_unslash( $_GET['am_q'] ) ) : '';
$role   = isset( $_GET['am_role'] ) ? sanitize_key( wp_unslash( $_GET['am_role'] ) ) : '';
$status = isset( $_GET['am_status'] ) ? sanitize_key( wp_unslash( $_GET['am_status'] ) ) : '';
$paged  = isset( $_GET['am_paged'] ) ? absint( $_GET['am_paged'] ) : 1;
$per    = (int) apply_filters( 'am_directory_per_page', 12 );
$result = am_get_option( 'member_directory', 1 )
    ? AM_Members::query( $search, 'name', array( 'role' => $role, 'status' => $status, 'paged' => $paged, 'per_page' => $per ) )
    : array( 'users' => array(), 'total' => 0, 'pages' => 1, 'paged' => 1 );
$users  = $result['users'];
$base   = remove_query_arg( 'am_paged' );
$badgeSvg = '<svg class="ig-verified" viewBox="0 0 24 24" role="img" aria-label="Verified"><path fill="#0095f6" d="M12 0C5.37 0 0 5.37 0 12s5.37 12 12 12 12-5.37 12-12S18.63 0 12 0zm6.7 9.3-7.8 7.8c-.5.5-1.3.5-1.8 0l-3.8-3.8c-.5-.5-.5-1.3 0-1.8s1.3-.5 1.8 0l2.9 2.9 6.9-6.9c.5-.5 1.3-.5 1.8 0s.5 1.3 0 1.8z"/></svg>';
?>
<div class="ig-plugin-container ig-app-page" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-follow-nonce="<?php echo esc_attr( wp_create_nonce( 'am_follow' ) ); ?>">
  <?php am_app_shell_open( 'people' ); ?>
  <div class="ig-members-head">
    <h2 class="ig-members-title">Members</h2>
    <form class="ig-members-search" method="get">
      <label class="ig-sr-only" for="ig-member-search">Search members</label>
      <input class="ig-search-input" id="ig-member-search" type="text" name="am_q" placeholder="Search" value="<?php echo esc_attr( $search ); ?>">
      <label class="ig-sr-only" for="ig-member-role">Role</label>
      <select class="ig-search-select" id="ig-member-role" name="am_role">
        <option value=""><?php esc_html_e( 'All roles', 'ababil-members' ); ?></option>
        <?php foreach ( AM_Members::roles() as $key => $label ) : ?>
          <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $role, $key ); ?>><?php echo esc_html( $label ); ?></option>
        <?php endforeach; ?>
      </select>
      <label class="ig-sr-only" for="ig-member-status">Status</label>
      <select class="ig-search-select" id="ig-member-status" name="am_status">
        <option value=""><?php esc_html_e( 'All statuses', 'ababil-members' ); ?></option>
        <option value="approved" <?php selected( $status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'ababil-members' ); ?></option>
        <option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'ababil-members' ); ?></option>
        <option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'ababil-members' ); ?></option>
        <option value="banned" <?php selected( $status, 'banned' ); ?>><?php esc_html_e( 'Banned', 'ababil-members' ); ?></option>
      </select>
      <button class="ig-btn" type="submit"><?php esc_html_e( 'Search', 'ababil-members' ); ?></button>
    </form>
  </div>

  <p class="ig-members-count"><?php echo esc_html( sprintf( __( '%d member(s) found', 'ababil-members' ), $result['total'] ) ); ?></p>

  <?php if ( $users ) : ?>
    <div class="ig-members-grid">
      <?php foreach ( $users as $u ) :
        $uid       = (int) $u->ID;
        $avatar    = get_user_meta( $uid, 'am_avatar', true ) ?: '🙂';
        $following = am_get_option( 'followers', 1 ) && $me ? AM_Followers::is_following( $me, $uid ) : false;
        $verified  = ( $u->roles && in_array( 'administrator', (array) $u->roles, true ) ) || am_get_points( $uid ) >= 200;
        $isOwn     = ( $me === $uid );
      ?>
        <div class="ig-member-card">
          <a class="ig-member-avatar" href="<?php echo esc_url( AM_Profile::url( $u->user_login ) ); ?>"><?php echo esc_html( $avatar ); ?></a>
          <div class="ig-member-name">
            <a class="ig-member-name-text" href="<?php echo esc_url( AM_Profile::url( $u->user_login ) ); ?>"><?php echo esc_html( $u->user_login ); ?></a>
            <?php if ( $verified ) echo $badgeSvg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </div>
          <div class="ig-member-handle">@<?php echo esc_html( $u->user_login ); ?></div>
          <?php if ( am_get_option( 'followers', 1 ) ) : ?>
            <?php if ( $isOwn ) : ?>
              <a class="ig-btn ig-member-follow" href="<?php echo esc_url( am_page_url( 'am-profile' ) ); ?>"><?php esc_html_e( 'Your profile', 'ababil-members' ); ?></a>
            <?php elseif ( $me ) : ?>
              <button type="button" class="ig-btn ig-member-follow <?php echo $following ? 'is-following' : 'ig-btn-primary'; ?> ig-follow-btn" data-user="<?php echo (int) $uid; ?>" data-following="<?php echo $following ? '1' : '0'; ?>"><?php echo $following ? esc_html__( 'Following', 'ababil-members' ) : esc_html__( 'Follow', 'ababil-members' ); ?></button>
            <?php else : ?>
              <a class="ig-btn ig-member-follow ig-btn-primary" href="<?php echo esc_url( am_page_url( 'am-login' ) ); ?>"><?php esc_html_e( 'Follow', 'ababil-members' ); ?></a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ( $result['pages'] > 1 ) : ?>
      <nav class="ig-pagination" aria-label="<?php echo esc_attr__( 'Member pages', 'ababil-members' ); ?>">
        <?php for ( $i = 1; $i <= $result['pages']; $i++ ) : ?>
          <a class="ig-page-link <?php echo $i === $result['paged'] ? 'is-current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'am_paged', $i, $base ) ); ?>"><?php echo (int) $i; ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php else : ?>
    <div class="ig-empty"><?php esc_html_e( 'No members found.', 'ababil-members' ); ?></div>
  <?php endif; ?>
  <?php am_app_shell_close(); ?>
</div>
