<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Get an option with default. */
function am_get_option( $key, $default = '' ) {
    $opts = get_option( 'am_options', array() );
    return is_array( $opts ) && array_key_exists( $key, $opts ) ? $opts[ $key ] : $default;
}

/** Update one option. */
function am_update_option( $key, $value ) {
    $opts = get_option( 'am_options', array() );
    if ( ! is_array( $opts ) ) $opts = array();
    $opts[ $key ] = $value;
    update_option( 'am_options', $opts );
}

/** Current user's AM role. */
function am_get_role( $user_id = 0 ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    if ( ! $user_id ) return 'guest';
    $role = get_user_meta( $user_id, 'am_role', true );
    return $role ? $role : 'member';
}

/** Points (stored in usermeta). */
function am_get_points( $user_id ) {
    return (int) get_user_meta( $user_id, 'am_points', true );
}
function am_add_points( $user_id, $amount ) {
    $new = am_get_points( $user_id ) + (int) $amount;
    update_user_meta( $user_id, 'am_points', $new );
    do_action( 'am_points_changed', $user_id, $new );
    return $new;
}

/** Badge for points. */
function am_badge_for_points( $points ) {
    $points = (int) $points;
    if ( $points >= 200 ) return array( 'key' => 'champion', 'emoji' => "\xF0\x9F\x8F\x86", 'label' => __( 'Champion', 'ababil-members' ) );
    if ( $points >= 50 )  return array( 'key' => 'star', 'emoji' => "\xE2\xAD\x90", 'label' => __( 'Star', 'ababil-members' ) );
    return array( 'key' => 'new', 'emoji' => "\xF0\x9F\x8C\xB1", 'label' => __( 'New Member', 'ababil-members' ) );
}

/** Safe redirect helper. */
function am_safe_redirect( $url ) {
    $url = wp_sanitize_redirect( $url );
    wp_safe_redirect( $url );
    exit;
}

/** Map of plugin slug => assigned page ID (Phase 4). */
function am_pages_map() {
    $pages = am_get_option( 'am_pages', array() );
    return is_array( $pages ) ? $pages : array();
}

/** Canonical page URL for a feature slug (feed / groups / messages / profile...). */
function am_page_url( $slug ) {
    $map = am_pages_map();
    if ( ! empty( $map[ $slug ] ) ) {
        $url = get_permalink( (int) $map[ $slug ] );
        if ( $url ) return $url;
    }
    $page = get_page_by_path( $slug );
    return $page ? get_permalink( $page ) : home_url( '/' );
}

/** Shortcode aliases → their canonical page slug, so links work even without assigned pages. */
function am_canonical_url( $slug ) {
    $aliases = array(
        'am-feed'     => 'am-feed',
        'am-groups'   => 'am-groups',
        'am-messages' => 'am-messages',
        'am-profile'  => 'am-profile',
        'am-members'  => 'am-members',
    );
    $slug = isset( $aliases[ $slug ] ) ? $aliases[ $slug ] : $slug;
    return am_page_url( $slug );
}

/**
 * Clean fallback for user meta values when meta doesn't exist.
 */
function am_user_meta_clean( $user_id, $key, $default = '' ) {
    $val = get_user_meta( (int) $user_id, $key, true );
    return ( '' !== $val && 'Prefer not to say' !== $val ) ? (string) $val : $default;
}

/** Registration settings with safe defaults (Phase 4). */
function am_registration_settings() {
    $r = am_get_option( 'am_registration', array() );
    $r = is_array( $r ) ? $r : array();
    return array(
        'role'           => sanitize_key( $r['role'] ?? 'subscriber' ),
        'display_name'   => sanitize_key( $r['display_name'] ?? 'username' ),
        'gravatar'       => empty( $r['gravatar'] ) ? 0 : 1,
        'permalink_base' => am_permalink_base(),
        'redirect'       => sanitize_key( $r['redirect'] ?? 'profile' ),
    );
}

/** Profile permalink base (Phase 4). */
function am_permalink_base() {
    $base = am_get_option( 'am_permalink_base', 'user' );
    $base = preg_replace( '/[^a-z0-9_-]/i', '', (string) $base );
    return $base ? strtolower( $base ) : 'user';
}

/** Consent checkbox settings (Phase 4). */
function am_consent_settings() {
    $c = am_get_option( 'am_consent', array() );
    $c = is_array( $c ) ? $c : array();
    return array(
        'enabled' => empty( $c['enabled'] ) ? 0 : 1,
        'text'    => (string) ( $c['text'] ?? __( 'I agree to the terms and privacy policy.', 'ababil-members' ) ),
    );
}

/** Simple role ranking for visibility rules. */
function am_role_rank( $role ) {
    $ranks = array( 'guest' => 0, 'subscriber' => 1, 'contributor' => 2, 'author' => 3, 'editor' => 4, 'administrator' => 9 );
    return $ranks[ sanitize_key( $role ) ] ?? 1;
}

/** Custom profile tabs visible to the current viewer (Phase 4 per-tab privacy). */
function am_visible_tabs() {
    $me   = get_current_user_id();
    $rank = $me ? am_role_rank( am_get_role( $me ) ) : 0;
    $out  = array();
    foreach ( am_profile_tabs() as $tab ) {
        $visibility = sanitize_key( $tab['visibility'] ?? 'all' );
        if ( 'members' === $visibility && ! $me ) continue;
        if ( 'admin' === $visibility && ! current_user_can( 'manage_options' ) ) continue;
        $min = isset( $tab['min_role'] ) ? am_role_rank( $tab['min_role'] ) : 0;
        if ( $min && $rank < $min ) continue;
        $out[] = $tab;
    }
    return $out;
}

/** Admin-defined custom profile tabs (Phase 3). */
function am_profile_tabs() {
    $tabs = am_get_option( 'am_tabs', array() );
    return is_array( $tabs ) ? array_values( $tabs ) : array();
}

/** Unique, safe key for a custom tab. */
function am_tab_key( $tab ) {
    $id = is_array( $tab ) && isset( $tab['id'] ) ? $tab['id'] : '';
    return 'custom_' . sanitize_key( $id ? $id : ( is_array( $tab ) && isset( $tab['label'] ) ? $tab['label'] : 'tab' ) );
}

/**
 * App-style shell shared by all member-facing pages (top bar + left nav).
 * Called inside a .ig-plugin-container so all markup and JS stay scoped.
 */
function am_app_shell_open( $page = 'feed' ) {
    $me     = get_current_user_id();
    $u      = $me ? get_userdata( $me ) : null;
    $site   = get_bloginfo( 'name' );
    $avatar = $me ? get_user_meta( $me, 'am_avatar_url', true ) : '';
    $emoji  = $me ? ( get_user_meta( $me, 'am_avatar', true ) ?: '🙂' ) : '🙂';
    $login  = $u ? $u->user_login : '';
    $bell   = $me && class_exists( 'AM_Notifications' ) ? AM_Notifications::unread_count( $me ) : 0;

    $nav = array(
        'home'     => array( __( 'Home', 'ababil-members' ),     '⌂', am_page_url( 'am-feed' ) ),
        'people'   => array( __( 'People', 'ababil-members' ),   '♥', am_page_url( 'am-members' ) ),
        'photos'   => array( __( 'Photos', 'ababil-members' ),   '▣', am_page_url( 'am-profile' ) ),
        'feed'     => array( __( 'News Feed', 'ababil-members' ),'▤', am_page_url( 'am-feed' ) ),
        'profile'  => array( __( 'Profile', 'ababil-members' ),  '☺', am_page_url( 'am-profile' ) ),
        'settings' => array( __( 'Settings', 'ababil-members' ), '⚙', am_page_url( 'am-profile' ) ),
    );
    $counts = array(
        'people'   => count( get_users( array( 'fields' => 'ID', 'role__not_in' => array( 'administrator' ) ) ) ),
        'settings' => 0,
    );

    echo '<div class="ig-app-shell">';
    echo '<header class="ig-app-topbar">';
    echo '<a class="ig-app-brand" href="' . esc_url( am_page_url( 'am-feed' ) ) . '"><span class="ig-app-logo">◢</span><span>' . esc_html( $site ) . '</span></a>';
    echo '<form class="ig-app-search" method="get" action="' . esc_url( am_page_url( 'am-members' ) ) . '"><span class="ig-app-search-icon">⌕</span><input type="text" name="am_q" placeholder="' . esc_attr__( 'Search', 'ababil-members' ) . '"><button type="submit" class="ig-app-search-btn">' . esc_html__( 'Go', 'ababil-members' ) . '</button></form>';
    echo '<div class="ig-app-actions">';
    if ( $me ) {
        echo '<div class="ig-notif-wrap">';
        echo '<button type="button" class="ig-notif-bell" id="ig-notif-btn" aria-label="' . esc_attr__( 'Notifications', 'ababil-members' ) . '">🔔';
        echo '<span class="ig-notif-badge" data-notif-count' . ( $bell ? '' : ' hidden' ) . '>' . (int) $bell . '</span></button>';
        echo '<div class="ig-notif-panel" hidden><div class="ig-notif-head"><strong>' . esc_html__( 'Notifications', 'ababil-members' ) . '</strong><button type="button" class="ig-notif-readall">' . esc_html__( 'Mark all read', 'ababil-members' ) . '</button></div>';
        echo '<div class="ig-notif-body" data-notif-list>' . AM_Notifications::render_items( $me ) . '</div>';
        echo '</div></div>';
        $dark_mode = get_user_meta( $me, 'am_dark_mode', true );
        echo '<button type="button" class="ig-dark-toggle" id="ig-dark-btn" aria-label="' . esc_attr__( 'Toggle dark mode', 'ababil-members' ) . '">' . ( $dark_mode ? '&#9728;' : '&#127769;' ) . '</button>';
    }
    echo '<a class="ig-btn ig-btn-primary ig-app-create" href="' . esc_url( am_page_url( 'am-groups' ) ) . '">＋ ' . esc_html__( 'Create', 'ababil-members' ) . '</a>';
    if ( $me ) {
        echo '<a class="ig-app-me" href="' . esc_url( am_page_url( 'am-profile' ) ) . '">';
        echo $avatar ? '<img src="' . esc_url( $avatar ) . '" alt="">' : esc_html( $emoji );
        echo '</a>';
    }
    echo '</div></header>';

    echo '<div class="ig-app-body"><aside class="ig-app-left">';
    echo '<a class="ig-app-user" href="' . esc_url( am_page_url( 'am-profile' ) ) . '"><span class="ig-app-user-avatar">';
    echo $avatar ? '<img src="' . esc_url( $avatar ) . '" alt="">' : esc_html( $emoji );
    echo '</span><span class="ig-app-user-info"><strong>' . esc_html( $login ) . '</strong><small>@' . esc_html( $login ) . '</small></span></a>';

    echo '<nav class="ig-app-nav">';
    foreach ( $nav as $key => $item ) {
        echo '<a class="ig-app-nav-item' . ( $key === $page ? ' is-active' : '' ) . '" href="' . esc_url( $item[2] ) . '"><span class="ig-app-nav-icon">' . esc_html( $item[1] ) . '</span><span>' . esc_html( $item[0] ) . '</span>';
        if ( isset( $counts[ $key ] ) ) echo '<span class="ig-app-badge' . ( $counts[ $key ] ? '' : ' is-gray' ) . '">' . (int) $counts[ $key ] . '</span>';
        echo '</a>';
    }
    echo '</nav>';

    echo '<div class="ig-app-invite"><div class="ig-app-invite-label">' . esc_html__( 'Community', 'ababil-members' ) . '</div>';
    echo '<div class="ig-app-invite-title">' . esc_html( sprintf( __( 'Grow with %s', 'ababil-members' ), $site ) ) . '</div>';
    echo '<a class="ig-app-invite-btn" href="' . esc_url( am_page_url( 'am-members' ) ) . '">' . esc_html__( 'Meet members', 'ababil-members' ) . '</a><span class="ig-app-invite-x">✕</span></div>';
    echo '</aside><main class="ig-app-center">';
}

/** Right-hand suggestions/contacts column that closes the shared app shell. */
function am_app_shell_close() {
    $me = get_current_user_id();
    $exclude = $me ? array( $me ) : array();
    $suggest = get_users( array( 'number' => 3, 'orderby' => 'registered', 'order' => 'DESC', 'role__not_in' => array( 'administrator' ), 'exclude' => $exclude ) );
    $contacts = get_users( array( 'number' => 7, 'orderby' => 'registered', 'order' => 'ASC', 'role__not_in' => array( 'administrator' ), 'exclude' => $exclude ) );

    echo '</main><aside class="ig-app-right">';

    if ( $suggest ) {
        echo '<section class="ig-app-block"><div class="ig-app-block-head">' . esc_html__( 'Requests', 'ababil-members' ) . '<span class="ig-app-badge">' . count( $suggest ) . '</span></div>';
        foreach ( $suggest as $s ) {
            $surl  = AM_Profile::url( $s->user_login );
            $sav   = get_user_meta( $s->ID, 'am_avatar_url', true );
            $semo  = get_user_meta( $s->ID, 'am_avatar', true ) ?: '🙂';
            $follow = class_exists( 'AM_Followers' ) && $me ? AM_Followers::is_following( $me, (int) $s->ID ) : false;
            echo '<div class="ig-app-req"><div class="ig-app-req-top"><span class="ig-app-avatar-sm">';
            echo $sav ? '<img src="' . esc_url( $sav ) . '" alt="">' : esc_html( $semo );
            echo '</span><span class="ig-app-req-name"><a href="' . esc_url( $surl ) . '">' . esc_html( $s->user_login ) . '</a> <span class="ig-app-req-sub">' . esc_html__( 'wants to connect', 'ababil-members' ) . '</span></span></div>';
            echo '<div class="ig-app-req-btns">';
            if ( $me ) {
                echo '<button type="button" class="ig-btn ig-btn-primary ig-follow-btn' . ( $follow ? ' is-following' : '' ) . '" data-user="' . (int) $s->ID . '" data-following="' . ( $follow ? '1' : '0' ) . '">' . esc_html( $follow ? __( 'Following', 'ababil-members' ) : __( 'Follow', 'ababil-members' ) ) . '</button>';
            } else {
                echo '<a class="ig-btn ig-btn-primary" href="' . esc_url( am_page_url( 'am-login' ) ) . '">' . esc_html__( 'Join', 'ababil-members' ) . '</a>';
            }
            echo '<a class="ig-btn ig-app-ignore" href="' . esc_url( $surl ) . '">' . esc_html__( 'View', 'ababil-members' ) . '</a></div></div>';
        }
        echo '</section>';
    }

    if ( $contacts ) {
        echo '<section class="ig-app-block"><div class="ig-app-block-head">' . esc_html__( 'Contacts', 'ababil-members' ) . '<span class="ig-app-pill">' . count( $contacts ) . '</span></div>';
        foreach ( $contacts as $c ) {
            $cav = get_user_meta( $c->ID, 'am_avatar_url', true );
            $cemo = get_user_meta( $c->ID, 'am_avatar', true ) ?: '🙂';
            echo '<a class="ig-app-contact" href="' . esc_url( AM_Profile::url( $c->user_login ) ) . '"><span class="ig-app-avatar-sm">';
            echo $cav ? '<img src="' . esc_url( $cav ) . '" alt="">' : esc_html( $cemo );
            echo '</span><span class="ig-app-contact-name">' . esc_html( $c->user_login ) . '<i class="ig-app-online"></i></span><span class="ig-app-contact-more">···</span></a>';
        }
        echo '</section>';
    }
    echo '</aside></div></div>';
}
