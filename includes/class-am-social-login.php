<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Phase 3 — Google / Facebook social login.
 * Uses a distinct class name because AM_Social is the feed/groups engine.
 * Flow: ?am_social_start=google|facebook  →  provider  →  ?am_social=google|facebook&code=…&state=…
 */
class AM_Social_Login {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'query_vars', array( $this, 'query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle' ), 3 );
    }

    public function query_vars( $vars ) {
        $vars[] = 'am_social';
        $vars[] = 'am_social_start';
        return $vars;
    }

    public static function settings() {
        $s = am_get_option( 'am_social_login', array() );
        return is_array( $s ) ? $s : array();
    }

    public static function enabled() {
        $s = self::settings();
        return ! empty( $s['enabled'] );
    }

    /** URL that starts the OAuth flow. */
    public static function start_url( $provider ) {
        return home_url( '/?am_social_start=' . rawurlencode( $provider ) );
    }

    public static function callback_url( $provider ) {
        return home_url( '/?am_social=' . rawurlencode( $provider ) );
    }

    public function handle() {
        if ( is_admin() ) return;

        $start = sanitize_key( get_query_var( 'am_social_start' ) );
        if ( $start && in_array( $start, array( 'google', 'facebook' ), true ) ) {
            $this->start( $start );
        }

        $provider = sanitize_key( get_query_var( 'am_social' ) );
        if ( $provider && in_array( $provider, array( 'google', 'facebook' ), true ) ) {
            if ( isset( $_GET['am_error'] ) ) {
                $this->fail( __( 'Social login was cancelled or denied.', 'ababil-members' ) );
            }
            if ( isset( $_GET['code'] ) ) {
                $this->callback( $provider, sanitize_text_field( wp_unslash( $_GET['code'] ) ) );
            }
        }
    }

    private function start( $provider ) {
        if ( ! self::enabled() ) $this->fail( __( 'Social login is disabled.', 'ababil-members' ) );
        $s = self::settings();
        $id_key = ( 'google' === $provider ) ? 'google_id' : 'facebook_id';
        $client_id = isset( $s[ $id_key ] ) ? trim( $s[ $id_key ] ) : '';
        if ( ! $client_id ) $this->fail( __( 'Social login is not configured.', 'ababil-members' ) );

        $state = wp_generate_password( 24, false );
        // Store state in a short-lived cookie and match it on callback.
        setcookie( 'am_social_state', $state, time() + 10 * MINUTE_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );

        if ( 'google' === $provider ) {
            $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( array(
                'client_id'     => $client_id,
                'redirect_uri'  => self::callback_url( $provider ),
                'response_type' => 'code',
                'scope'         => 'openid email profile',
                'state'         => $state,
                'access_type'   => 'online',
            ) );
        } else {
            $url = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query( array(
                'client_id'     => $client_id,
                'redirect_uri'  => self::callback_url( $provider ),
                'response_type' => 'code',
                'scope'         => 'email,public_profile',
                'state'         => $state,
            ) );
        }
        wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
        exit;
    }

    private function callback( $provider, $code ) {
        // Verify state (CSRF).
        $state = isset( $_COOKIE['am_social_state'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['am_social_state'] ) ) : '';
        $sent  = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
        if ( ! $state || ! $sent || ! hash_equals( $state, $sent ) ) {
            $this->fail( __( 'Social login security check failed. Please try again.', 'ababil-members' ) );
        }
        setcookie( 'am_social_state', '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );

        $s = self::settings();
        $id_key = ( 'google' === $provider ) ? 'google_id' : 'facebook_id';
        $secret_key = ( 'google' === $provider ) ? 'google_secret' : 'facebook_secret';
        $client_id = isset( $s[ $id_key ] ) ? trim( $s[ $id_key ] ) : '';
        $secret    = isset( $s[ $secret_key ] ) ? trim( $s[ $secret_key ] ) : '';
        if ( ! $client_id || ! $secret ) $this->fail( __( 'Social login is not configured.', 'ababil-members' ) );

        $profile = ( 'google' === $provider ) ? $this->google_profile( $code, $client_id, $secret, $provider ) : $this->facebook_profile( $code, $client_id, $secret, $provider );
        if ( ! $profile || empty( $profile['id'] ) ) $this->fail( __( 'Could not retrieve your social profile.', 'ababil-members' ) );

        $user = $this->find_or_create_user( $provider, $profile );
        if ( ! $user || is_wp_error( $user ) ) {
            $this->fail( __( 'Could not create your account.', 'ababil-members' ) );
        }

        // Log the member in.
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );
        update_user_meta( $user->ID, 'am_last_login', current_time( 'mysql' ) );
        do_action( 'am_login', $user->ID );
        do_action( 'am_social_login', $user->ID, $provider );
        am_safe_redirect( am_page_url( 'am-profile' ) );
    }

    private function google_profile( $code, $client_id, $secret, $provider ) {
        $res = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $secret,
                'redirect_uri'  => self::callback_url( $provider ),
                'grant_type'    => 'authorization_code',
            ),
            'timeout' => 15,
        ) );
        if ( is_wp_error( $res ) ) return null;
        $data = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( empty( $data['access_token'] ) ) return null;

        $res = wp_remote_get( 'https://openidconnect.googleapis.com/v1/userinfo', array(
            'headers' => array( 'Authorization' => 'Bearer ' . $data['access_token'] ),
            'timeout' => 15,
        ) );
        if ( is_wp_error( $res ) ) return null;
        $p = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! is_array( $p ) ) return null;
        return array(
            'id'    => $p['sub'] ?? '',
            'email' => $p['email'] ?? '',
            'name'  => $p['name'] ?? ( $p['given_name'] ?? '' ),
            'verified' => ! empty( $p['email_verified'] ),
        );
    }

    private function facebook_profile( $code, $client_id, $secret, $provider ) {
        $url = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query( array(
            'client_id'     => $client_id,
            'client_secret' => $secret,
            'code'          => $code,
            'redirect_uri'  => self::callback_url( $provider ),
        ) );
        $res = wp_remote_get( $url, array( 'timeout' => 15 ) );
        if ( is_wp_error( $res ) ) return null;
        $data = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( empty( $data['access_token'] ) ) return null;

        $url = 'https://graph.facebook.com/me?' . http_build_query( array(
            'fields' => 'id,name,email',
            'access_token' => $data['access_token'],
        ) );
        $res = wp_remote_get( $url, array( 'timeout' => 15 ) );
        if ( is_wp_error( $res ) ) return null;
        $p = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! is_array( $p ) ) return null;
        return array(
            'id'    => $p['id'] ?? '',
            'email' => $p['email'] ?? '',
            'name'  => $p['name'] ?? '',
            'verified' => true,
        );
    }

    private function find_or_create_user( $provider, $profile ) {
        $meta_key = 'am_social_' . $provider . '_id';

        // Step 1: match by stored provider ID.
        $args = array(
            'meta_key'   => $meta_key,
            'meta_value' => $profile['id'],
            'number'     => 1,
            'fields'     => 'all',
        );
        $found = get_users( $args );
        if ( $found ) return $found[0];

        // Step 2: match by email.
        if ( ! empty( $profile['email'] ) && is_email( $profile['email'] ) ) {
            $by_email = get_user_by( 'email', $profile['email'] );
            if ( $by_email ) {
                update_user_meta( $by_email->ID, $meta_key, $profile['id'] );
                return $by_email;
            }
        }

        // Step 3: create a new member.
        $email = ( ! empty( $profile['email'] ) && is_email( $profile['email'] ) )
            ? $profile['email']
            : $provider . '_' . sanitize_key( $profile['id'] ) . '@social.local';
        $login = sanitize_user( current( explode( '@', $email ) ), true );
        if ( '' === $login ) $login = $provider . 'user';
        $base = $login;
        $i = 1;
        while ( username_exists( $login ) ) { $login = $base . $i; $i++; }

        $uid = wp_insert_user( array(
            'user_login'   => $login,
            'user_email'   => $email,
            'display_name' => sanitize_text_field( $profile['name'] ? $profile['name'] : $login ),
            'user_pass'    => wp_generate_password( 32, true, true ),
            'role'         => 'subscriber',
        ) );
        if ( is_wp_error( $uid ) ) return $uid;

        update_user_meta( $uid, $meta_key, $profile['id'] );
        update_user_meta( $uid, 'am_role', 'member' );
        update_user_meta( $uid, 'am_points', 0 );
        $status = am_get_option( 'register_status', 'approved' );
        update_user_meta( $uid, 'am_account_status', $status ? $status : 'approved' );
        update_user_meta( $uid, 'am_avatar', '🙂' );
        do_action( 'am_register', $uid );
        return get_userdata( $uid );
    }

    private function fail( $message ) {
        $url = add_query_arg( 'am_login_error', rawurlencode( $message ), am_page_url( 'am-login' ) );
        wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
        exit;
    }
}
