<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Ababil Members — notification centre.
 * Stores who-did-what-to-whom and powers the bell dropdown.
 */
class AM_Notifications {

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( __CLASS__, 'create_table' ) );
        add_action( 'am_follow',        array( __CLASS__, 'on_follow' ), 10, 2 );
        add_action( 'am_unfollow',      array( __CLASS__, 'on_unfollow' ), 10, 2 );
        add_action( 'am_comment_added', array( __CLASS__, 'on_comment' ), 10, 5 );
        add_action( 'am_post_created',  array( __CLASS__, 'on_group_post' ) );
        add_action( 'am_message_sent',  array( __CLASS__, 'on_message' ), 10, 4 );
        add_action( 'am_group_joined',  array( __CLASS__, 'on_group_join' ), 10, 2 );
        add_action( 'am_post_liked',         array( __CLASS__, 'on_post_like' ), 10, 3 );
        add_action( 'am_post_unliked',       array( __CLASS__, 'on_post_unlike' ), 10, 3 );
        add_action( 'am_comment_liked',      array( __CLASS__, 'on_comment_like' ), 10, 4 );
        add_action( 'am_comment_unliked',    array( __CLASS__, 'on_comment_unlike' ), 10, 4 );

        add_action( 'wp_ajax_am_notifications',         array( __CLASS__, 'ajax_list' ) );
        add_action( 'wp_ajax_am_notifications_read_all', array( __CLASS__, 'ajax_read_all' ) );
        add_action( 'wp_ajax_am_notifications_read_one', array( __CLASS__, 'ajax_read_one' ) );
        add_action( 'wp_ajax_am_dark_mode',              array( __CLASS__, 'ajax_dark_mode' ) );
    }

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'am_notifications';
    }

    public static function create_table() {
        global $wpdb;
        $t = self::table();
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$t} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            actor_id BIGINT NOT NULL DEFAULT 0,
            type VARCHAR(20) NOT NULL,
            text VARCHAR(300) NOT NULL,
            url VARCHAR(300) NOT NULL DEFAULT '',
            is_read TINYINT NOT NULL DEFAULT 0,
            created DATETIME NOT NULL,
            KEY user_id (user_id), KEY read_key (is_read), KEY created (created)
        ) {$wpdb->get_charset_collate()}" );
    }

    public static function push( $user_id, $actor_id, $type, $text, $url = '' ) {
        $user_id = (int) $user_id;
        if ( ! $user_id || ( (int) $actor_id === $user_id ) ) return false;
        global $wpdb;
        $wpdb->insert( self::table(), array(
            'user_id'  => $user_id,
            'actor_id' => (int) $actor_id,
            'type'     => sanitize_key( $type ),
            'text'     => mb_substr( sanitize_text_field( $text ), 0, 300 ),
            'url'      => esc_url_raw( $url ),
            'is_read'  => 0,
            'created'  => current_time( 'mysql' ),
        ), array( '%d','%d','%s','%s','%s','%d','%s' ) );
        return (bool) $wpdb->insert_id;
    }

    public static function unread_count( $user_id ) {
        if ( ! $user_id ) return 0;
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id = %d AND is_read = 0', $user_id ) );
    }

    public static function items( $user_id, $limit = 25 ) {
        global $wpdb;
        $user_id = (int) $user_id;
        $limit   = max( 1, min( 100, (int) $limit ) );
        if ( ! $user_id ) return array();
        $table = self::table();
        // Use placeholders for every value. Some hosts mis-handle a concatenated
        // LIMIT clause inside prepare(), so keep the whole statement parameterised.
        $sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d", $user_id, $limit );
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( ! $rows && self::unread_count( $user_id ) ) {
            // Defensive fallback: guarantees the bell list shows rows that the count query sees.
            $rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE user_id = {$user_id} ORDER BY id DESC LIMIT {$limit}", ARRAY_A );
        }
        return $rows ? $rows : array();
    }

    public static function mark_all_read( $user_id ) {
        if ( ! $user_id ) return 0;
        global $wpdb;
        return (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET is_read = 1 WHERE user_id = %d', $user_id ) );
    }

    /** Mark one notification read — always constrained to its owner. */
    public static function mark_one( $user_id, $id ) {
        if ( ! $user_id || ! $id ) return 0;
        global $wpdb;
        return (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET is_read = 1 WHERE id = %d AND user_id = %d', (int) $id, (int) $user_id ) );
    }

    /** Remove a "like" notification when the like is undone. */
    private static function drop_like( $type, $owner, $actor, $url ) {
        if ( ! $owner ) return;
        global $wpdb;
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE type = %s AND user_id = %d AND actor_id = %d AND url = %s', $type, (int) $owner, (int) $actor, esc_url_raw( $url ) ) );
    }

    /* ---------------- event handlers ---------------- */
    public static function on_follow( $me, $target ) {
        $me = (int) $me; $target = (int) $target;
        if ( ! $target || $me === $target ) return;
        $u = get_userdata( $me );
        $name = $u ? $u->user_login : __( 'A member', 'ababil-members' );
        self::push( $target, $me, 'follow', sprintf( __( '%s started following you.', 'ababil-members' ), $name ), self::profile_url( $me ) );
    }

    public static function on_unfollow( $me, $target ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE type = %s AND user_id = %d AND actor_id = %d', 'follow', (int) $target, (int) $me ) );
    }

    public static function on_comment( $cid, $post_id, $me, $body, $parent ) {
        $post_id = (int) $post_id;
        global $wpdb;
        $owner = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . self::table_posts() . ' WHERE id = %d', $post_id ) );
        $u = get_userdata( (int) $me );
        $name = $u ? $u->user_login : __( 'A member', 'ababil-members' );
        $url  = self::feed_url( $post_id );
        if ( $parent ) {
            $parent_user = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . self::table_comments() . ' WHERE id = %d', (int) $parent ) );
            if ( $parent_user ) self::push( $parent_user, $me, 'reply', sprintf( __( '%s replied to your comment.', 'ababil-members' ), $name ), $url );
        } elseif ( $owner ) {
            self::push( $owner, $me, 'comment', sprintf( __( '%s commented on your post.', 'ababil-members' ), $name ), $url );
        }
    }

    public static function on_group_post( $post ) {
        if ( empty( $post['group_id'] ) ) return;
        global $wpdb;
        $gid = (int) $post['group_id'];
        $author = (int) $post['user_id'];
        $gname = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . self::table_groups() . ' WHERE id = %d', $gid ) );
        $uids = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . self::table_group_members() . ' WHERE group_id = %d', $gid ) );
        foreach ( $uids as $uid ) {
            self::push( $uid, $author, 'group_post', sprintf( __( 'New post in %s.', 'ababil-members' ), $gname ), self::group_url( $gid ) );
        }
    }

    public static function on_message( $id, $me, $to, $body ) {
        $u = get_userdata( (int) $me );
        $name = $u ? $u->user_login : __( 'A member', 'ababil-members' );
        self::push( $to, $me, 'message', sprintf( __( '%s sent you a message.', 'ababil-members' ), $name ), self::message_url( $me ) );
    }

    public static function on_group_join( $group_id, $user_id ) {
        global $wpdb;
        $creator = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT creator_id FROM ' . self::table_groups() . ' WHERE id = %d', (int) $group_id ) );
        $u = get_userdata( (int) $user_id );
        $name = $u ? $u->user_login : __( 'A member', 'ababil-members' );
        self::push( $creator, $user_id, 'group_join', sprintf( __( '%s joined your group.', 'ababil-members' ), $name ), self::group_url( $group_id ) );
    }

    public static function on_post_like( $post_id, $owner, $me ) {
        if ( ! $owner ) return;
        $u = get_userdata( (int) $me );
        $name = $u ? $u->user_login : __( 'A member', 'ababil-members' );
        self::push( $owner, $me, 'like', sprintf( __( '%s liked your post.', 'ababil-members' ), $name ), self::feed_url( $post_id ) );
    }

    public static function on_post_unlike( $post_id, $owner, $me ) {
        self::drop_like( 'like', $owner, $me, self::feed_url( $post_id ) );
    }

    public static function on_comment_like( $cid, $owner, $me, $post_id ) {
        if ( ! $owner ) return;
        $u = get_userdata( (int) $me );
        $name = $u ? $u->user_login : __( 'A member', 'ababil-members' );
        self::push( $owner, $me, 'like', sprintf( __( '%s liked your comment.', 'ababil-members' ), $name ), self::feed_url( $post_id ) );
    }

    public static function on_comment_unlike( $cid, $owner, $me, $post_id ) {
        self::drop_like( 'like', $owner, $me, self::feed_url( $post_id ) );
    }

    /* ---------------- small helpers ---------------- */
    private static function table_posts() { global $wpdb; return $wpdb->prefix . 'am_posts'; }
    private static function table_comments() { global $wpdb; return $wpdb->prefix . 'am_comments'; }
    private static function table_groups() { global $wpdb; return $wpdb->prefix . 'am_groups'; }
    private static function table_group_members() { global $wpdb; return $wpdb->prefix . 'am_group_members'; }

    private static function profile_url( $user_id ) {
        $u = get_userdata( (int) $user_id );
        return $u ? AM_Profile::url( $u->user_login ) : home_url( '/' );
    }
    private static function feed_url( $post_id = 0 ) { return add_query_arg( 'am_post', (int) $post_id, am_canonical_url( 'am-feed' ) ); }
    private static function group_url( $group_id ) { return add_query_arg( 'group', (int) $group_id, am_canonical_url( 'am-groups' ) ); }
    private static function message_url( $user_id ) {
        $u = get_userdata( (int) $user_id );
        return $u ? add_query_arg( 'to', rawurlencode( $u->user_login ), am_canonical_url( 'am-messages' ) ) : am_canonical_url( 'am-messages' );
    }

    /* ---------------- AJAX ---------------- */
    public static function ajax_list() {
        $me = get_current_user_id();
        if ( ! $me ) wp_send_json_error();
        check_ajax_referer( 'am_social', 'nonce' );
        wp_send_json_success( array( 'count' => self::unread_count( $me ), 'html' => self::render_items( $me ) ) );
    }

    public static function ajax_read_all() {
        $me = get_current_user_id();
        if ( ! $me ) wp_send_json_error();
        check_ajax_referer( 'am_social', 'nonce' );
        wp_send_json_success( array( 'count' => 0, 'n' => self::mark_all_read( $me ) ) );
    }

    public static function ajax_read_one() {
        $me = get_current_user_id();
        if ( ! $me ) wp_send_json_error();
        check_ajax_referer( 'am_social', 'nonce' );
        $id = (int) ( $_POST['id'] ?? 0 );
        $n = self::mark_one( $me, $id );
        wp_send_json_success( array( 'count' => self::unread_count( $me ), 'n' => $n ) );
    }

    /** Persist a member's dark-mode preference. */
    public static function ajax_dark_mode() {
        $me = get_current_user_id();
        if ( ! $me ) wp_send_json_error();
        check_ajax_referer( 'am_social', 'nonce' );
        $on = ! empty( $_POST['on'] ) ? 1 : 0;
        update_user_meta( $me, 'am_dark_mode', $on );
        wp_send_json_success( array( 'on' => $on ) );
    }

    /** Icon shown per notification type. */
    public static function icon( $type ) {
        $map = array(
            'follow' => '👤', 'comment' => '💬', 'reply' => '↩', 'group_post' => '📣',
            'message' => '✉', 'group_join' => '🙌', 'like' => '❤',
        );
        return isset( $map[ $type ] ) ? $map[ $type ] : '🔔';
    }

    public static function render_items( $user_id ) {
        $items = self::items( $user_id );
        if ( ! $items ) return '<div class="ig-notif-empty">' . esc_html__( 'No notifications yet.', 'ababil-members' ) . '</div>';
        $out = '<div class="ig-notif-list">';
        foreach ( $items as $n ) {
            if ( is_object( $n ) ) $n = (array) $n;
            $actor = (int) $n['actor_id'];
            $av    = $actor ? get_user_meta( $actor, 'am_avatar_url', true ) : '';
            $emoji = $actor ? ( get_user_meta( $actor, 'am_avatar', true ) ?: '🙂' ) : '';
            $ico   = self::icon( $n['type'] );
            $unread = $n['is_read'] ? '' : ' is-unread';
            $out  .= '<div class="ig-notif-item' . $unread . '" data-id="' . (int) $n['id'] . '">';
            $out  .= '<a class="ig-notif-link" href="' . esc_url( $n['url'] ) . '">';
            $out  .= '<span class="ig-notif-avatar">';
            $out  .= $av ? '<img src="' . esc_url( $av ) . '" alt="">' : esc_html( $emoji ? $emoji : $ico );
            $out  .= '</span><span class="ig-notif-text-wrap"><span class="ig-notif-text">' . esc_html( $n['text'] ) . '</span>';
            $out  .= '<span class="ig-notif-time">' . esc_html( human_time_diff( strtotime( $n['created'] ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'ababil-members' ) . '</span>';
            $out  .= '</span></a>';
            $out  .= '<button type="button" class="ig-notif-read" data-id="' . (int) $n['id'] . '" aria-label="' . esc_attr__( 'Mark as read', 'ababil-members' ) . '"' . ( $n['is_read'] ? ' hidden' : '' ) . '>✓</button>';
            $out  .= '<span class="ig-notif-type">' . esc_html( $ico ) . '</span></div>';
        }
        return $out . '</div>';
    }
}