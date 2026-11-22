<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Phase 4 — security hardening: rate limiting, honeypot, math CAPTCHA,
 * email OTP two-factor authentication and an audit log.
 */
class AM_Security {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_login_failed', array( $this, 'on_login_failed' ) );
        add_action( 'am_login', array( $this, 'on_login' ) );
        add_action( 'am_register', array( $this, 'on_register' ) );
        add_action( 'wp_login', array( $this, 'on_wp_login' ), 10, 2 );
    }

    public static function settings() {
        $s = am_get_option( 'am_security', array() );
        return is_array( $s ) ? $s : array();
    }

    private static function on( $key, $default = 0 ) {
        $s = self::settings();
        return empty( $s[ $key ] ) ? $default : $s[ $key ];
    }

    /* ------------------------------------------------------------------ *
     * Audit log
     * ------------------------------------------------------------------ */

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'am_audit';
    }

    public static function create_table() {
        global $wpdb;
        $t = self::table();
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$t} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL DEFAULT 0,
            event VARCHAR(50) NOT NULL,
            details TEXT NOT NULL,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created DATETIME NOT NULL,
            KEY event (event),
            KEY created (created)
        ) {$wpdb->get_charset_collate()}" );
    }

    public static function log( $event, $details = '', $user_id = 0 ) {
        if ( ! self::on( 'audit_log', 1 ) ) return;
        global $wpdb;
        $wpdb->insert( self::table(), array(
            'user_id' => (int) $user_id,
            'event'   => sanitize_key( $event ),
            'details' => mb_substr( sanitize_text_field( $details ), 0, 250 ),
            'ip'      => self::ip(),
            'created' => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%s', '%s' ) );
    }

    public static function recent( $limit = 100, $event = '' ) {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::table();
        if ( $event ) $sql .= $wpdb->prepare( ' WHERE event = %s', sanitize_key( $event ) );
        $sql .= ' ORDER BY id DESC LIMIT ' . max( 1, min( 500, (int) $limit ) );
        return $wpdb->get_results( $sql );
    }

    public static function clear_log() {
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE ' . self::table() );
    }

    public function on_login_failed( $username ) {
        self::rate_limit_failure();
        self::log( 'login_failed', 'Failed login for ' . sanitize_user( $username ) );
    }

    public function on_login( $uid ) {
        self::rate_limit_clear();
        self::log( 'login', 'Logged in', (int) $uid );
    }

    public function on_wp_login( $username, $user ) {
        // Local AM login already logs; avoid duplicates from native WP logins.
        if ( is_a( $user, 'WP_User' ) && ! doing_filter( 'am_login' ) ) {
            self::log( 'login', 'Logged in (WordPress)', $user->ID );
        }
    }

    public function on_register( $uid ) {
        self::log( 'register', 'New member registered', (int) $uid );
    }

    /* ------------------------------------------------------------------ *
     * Rate limiting
     * ------------------------------------------------------------------ */

    public static function ip() {
        $ip = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? trim( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] ) : ( $_SERVER['REMOTE_ADDR'] ?? '' );
        return is_string( $ip ) ? mb_substr( sanitize_text_field( $ip ), 0, 45 ) : '';
    }

    private static function key() {
        return 'am_rl_' . md5( self::ip() );
    }

    public static function rate_limit_enabled() {
        return (bool) self::on( 'rate_limit', 1 );
    }

    public static function is_locked() {
        if ( ! self::rate_limit_enabled() ) return false;
        $until = (int) get_transient( self::key() . '_lock' );
        return $until > time();
    }

    public static function lock_seconds_left() {
        $until = (int) get_transient( self::key() . '_lock' );
        return max( 0, $until - time() );
    }

    public static function rate_limit_failure() {
        if ( ! self::rate_limit_enabled() ) return;
        $key   = self::key();
        $max   = max( 2, min( 20, (int) self::on( 'max_attempts', 5 ) ) );
        $mins  = max( 1, min( 1440, (int) self::on( 'lockout_minutes', 15 ) ) );
        $count = (int) get_transient( $key ) + 1;
        set_transient( $key, $count, $mins * MINUTE_IN_SECONDS );
        if ( $count >= $max ) set_transient( $key . '_lock', time() + $mins * MINUTE_IN_SECONDS, $mins * MINUTE_IN_SECONDS );
    }

    public static function rate_limit_clear() {
        delete_transient( self::key() );
        delete_transient( self::key() . '_lock' );
    }

    /* ------------------------------------------------------------------ *
     * Honeypot + math CAPTCHA
     * ------------------------------------------------------------------ */

    public static function captcha_enabled() {
        return (bool) self::on( 'captcha', 1 );
    }

    /** Echo a hidden honeypot field (humans never fill it; bots do). */
    public static function honeypot_field() {
        echo '<div class="ig-hp" aria-hidden="true"><label>' . esc_html__( 'Leave this field empty', 'ababil-members' ) .
             '<input type="text" name="am_hp" value="" tabindex="-1" autocomplete="off"></label></div>';
    }

    public static function honeypot_ok() {
        return empty( $_POST['am_hp'] );
    }

    /** Sign a value so the CAPTCHA stays valid even when the page is cached. */
    private static function sign( $payload, $expiry ) {
        return hash_hmac( 'sha256', $payload . '|' . $expiry, wp_salt( 'auth' ) );
    }

    /**
     * Render a simple, stateless math CAPTCHA.
     * The token is an HMAC of the (cached-safe) question plus an expiry, so it
     * works with full-page caching instead of relying on a one-shot transient.
     */
    public static function captcha_field() {
        if ( ! self::captcha_enabled() ) return;
        $a = wp_rand( 2, 9 );
        $b = wp_rand( 1, 8 );
        $sum    = (int) ( $a + $b );
        $expiry = time() + ( 30 * MINUTE_IN_SECONDS );
        $token  = self::sign( (string) $sum, $expiry );
        echo '<div class="ig-captcha">';
        echo '<label class="ig-edit-label" for="am-captcha-' . esc_attr( $token ) . '">' .
             /* translators: %1$d and %2$d are the numbers to add */
             esc_html( sprintf( __( 'What is %1$d + %2$d?', 'ababil-members' ), $a, $b ) ) . '</label>';
        echo '<input class="ig-input" id="am-captcha-' . esc_attr( $token ) . '" type="number" name="am_captcha_answer" required inputmode="numeric">';
        echo '<input type="hidden" name="am_captcha_exp" value="' . (int) $expiry . '">';
        echo '<input type="hidden" name="am_captcha_token" value="' . esc_attr( $token ) . '">';
        echo '</div>';
    }

    public static function captcha_ok() {
        if ( ! self::captcha_enabled() ) return true;
        $token  = sanitize_key( $_POST['am_captcha_token'] ?? '' );
        $expiry = absint( $_POST['am_captcha_exp'] ?? 0 );
        $answer = trim( (string) ( $_POST['am_captcha_answer'] ?? '' ) );
        if ( ! $token || ! $expiry || $answer === '' ) return false;
        if ( $expiry < time() ) return false;
        return hash_equals( $token, self::sign( (string) ( (int) $answer ), $expiry ) );
    }

    /** Combined bot check used by login and registration. */
    public static function bot_check_ok() {
        if ( ! self::honeypot_ok() ) return false;
        if ( self::is_locked() ) return false;
        return self::captcha_ok();
    }

    /* ------------------------------------------------------------------ *
     * Email OTP two-factor authentication
     * ------------------------------------------------------------------ */

    /** Global 2FA switch (admin setting). */
    public static function two_factor_active() {
        return (bool) self::on( 'two_factor', 0 );
    }

    public static function two_factor_enabled_for( $user_id ) {
        return self::two_factor_active() && (int) get_user_meta( $user_id, 'am_2fa_enabled', true ) === 1;
    }

    /** Start a 2FA challenge; returns a token to carry in the redirect. */
    public static function begin_2fa( $user_id ) {
        $token = wp_generate_password( 24, false );
        $code  = sprintf( '%06d', wp_rand( 0, 999999 ) );
        set_transient( 'am_2fa_login_' . $token, (int) $user_id, 10 * MINUTE_IN_SECONDS );
        set_transient( 'am_2fa_code_' . (int) $user_id, wp_hash( $code ), 10 * MINUTE_IN_SECONDS );
        $user = get_userdata( $user_id );
        if ( $user ) {
            /* translators: %s is the one-time code */
            wp_mail( $user->user_email, sprintf( __( '[%s] Your login code', 'ababil-members' ), get_bloginfo( 'name' ) ),
                sprintf( __( 'Your one-time login code is %s. It expires in 10 minutes.', 'ababil-members' ), $code ) );
        }
        self::log( '2fa_sent', 'Two-factor code sent', (int) $user_id );
        return $token;
    }

    /** Verify a 2FA token + code. Returns the user ID or 0. */
    public static function complete_2fa( $token, $code ) {
        $token = sanitize_key( $token );
        $uid   = (int) get_transient( 'am_2fa_login_' . $token );
        if ( ! $uid ) return 0;
        $expected = get_transient( 'am_2fa_code_' . $uid );
        if ( ! $expected || ! hash_equals( $expected, wp_hash( trim( (string) $code ) ) ) ) return 0;
        delete_transient( 'am_2fa_login_' . $token );
        delete_transient( 'am_2fa_code_' . $uid );
        self::log( '2fa_success', 'Two-factor verification passed', $uid );
        return $uid;
    }
}
