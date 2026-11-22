<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Activity {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        if ( ! am_get_option( 'activity', 1 ) ) return;
        add_action( 'am_register', array( $this, 'on_register' ) );
        add_action( 'am_login', array( $this, 'on_login' ) );
        add_action( 'am_follow', array( $this, 'on_follow' ), 10, 2 );
        add_action( 'am_badge_changed', array( $this, 'on_badge' ), 10, 2 );
    }

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'am_activity';
    }

    public static function create_table() {
        global $wpdb;
        $t = self::table();
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$t} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            type VARCHAR(40) NOT NULL,
            text TEXT NOT NULL,
            created DATETIME NOT NULL,
            KEY user_id (user_id)
        ) {$wpdb->get_charset_collate()}" );
    }

    public static function add( $user_id, $type, $text ) {
        global $wpdb;
        $wpdb->insert( self::table(), array(
            'user_id' => $user_id,
            'type'    => sanitize_key( $type ),
            'text'    => mb_substr( sanitize_text_field( $text ), 0, 300 ),
            'created' => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%s' ) );
    }

    public static function feed( $user_id, $limit = 20 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY created DESC LIMIT ' . (int) $limit, $user_id ) );
    }

    public function on_register( $uid ) { self::add( $uid, 'join', 'joined the club 🎉' ); }
    public function on_login( $uid ) { self::add( $uid, 'login', 'logged in 👋' ); }
    public function on_follow( $me, $target ) {
        $u = get_userdata( $target );
        self::add( $me, 'follow', 'started following ' . ( $u ? $u->user_login : 'a member' ) );
    }
    /** Messages are private — never logged into public activity. */
    public static function purge_messages() {
        global $wpdb;
        $wpdb->query( "DELETE FROM " . self::table() . " WHERE type = 'message'" );
    }
    public function on_badge( $uid, $badge ) { self::add( $uid, 'badge', 'earned the ' . $badge . ' badge' ); }
}
