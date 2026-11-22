<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Followers {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        if ( ! am_get_option( 'followers', 1 ) ) return;
        add_action( 'wp_ajax_am_follow', array( $this, 'follow' ) );
        add_action( 'wp_ajax_am_unfollow', array( $this, 'unfollow' ) );
    }

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'am_follows';
    }

    public static function create_table() {
        global $wpdb;
        $t = self::table();
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$t} (
            follower_id BIGINT NOT NULL,
            followed_id BIGINT NOT NULL,
            created DATETIME NOT NULL,
            PRIMARY KEY (follower_id, followed_id),
            KEY followed_id (followed_id)
        ) {$wpdb->get_charset_collate()}" );
    }

    public static function is_following( $follower_id, $followed_id ) {
        global $wpdb;
        if ( ! $follower_id || ! $followed_id ) return false;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            'SELECT 1 FROM ' . self::table() . ' WHERE follower_id = %d AND followed_id = %d',
            $follower_id, $followed_id
        ) );
    }

    public static function counts( $user_id ) {
        global $wpdb;
        $t = self::table();
        return array(
            'followers'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE followed_id = %d", $user_id ) ),
            'following'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE follower_id = %d", $user_id ) ),
        );
    }

    public static function ids( $user_id, $type = 'followers' ) {
        global $wpdb;
        $t = self::table();
        $col = 'follower_id';
        $where = 'followed_id';
        if ( 'following' === $type ) { $col = 'followed_id'; $where = 'follower_id'; }
        return $wpdb->get_col( $wpdb->prepare( "SELECT {$col} FROM {$t} WHERE {$where} = %d ORDER BY created DESC LIMIT 50", $user_id ) );
    }

    public function follow() {
        $this->change( true );
    }

    public function unfollow() {
        $this->change( false );
    }

    private function change( $follow ) {
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'login' ) );
        check_ajax_referer( 'am_follow', 'nonce' );
        $target = (int) ( $_POST['user_id'] ?? 0 );
        $me = get_current_user_id();
        if ( ! $target || $target === $me || ! get_userdata( $target ) ) wp_send_json_error( array( 'message' => 'invalid' ) );

        global $wpdb;
        $t = self::table();
        if ( $follow ) {
            $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t} (follower_id, followed_id, created) VALUES (%d, %d, %s)", $me, $target, current_time( 'mysql' ) ) );
            do_action( 'am_follow', $me, $target );
        } else {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE follower_id = %d AND followed_id = %d", $me, $target ) );
            do_action( 'am_unfollow', $me, $target );
        }
        wp_send_json_success( array( 'following' => $follow, 'counts' => self::counts( $target ) ) );
    }
}
