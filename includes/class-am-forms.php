<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Forms {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'template_redirect', array( $this, 'handle_submit' ) );
        add_filter( 'wp_authenticate_user', array( $this, 'check_account_status' ), 10, 2 );
        add_action( 'password_reset', array( $this, 'on_password_reset' ), 10, 1 );
    }

    /** Clear the expiry flag when a member resets their password. */
    public function on_password_reset( $user ) {
        if ( ! is_a( $user, 'WP_User' ) ) return;
        update_user_meta( $user->ID, 'am_pw_changed', current_time( 'timestamp' ) );
        delete_user_meta( $user->ID, 'am_pw_expired' );
    }

    /** Block pending/rejected accounts at login. */
    public function check_account_status( $user, $password ) {
        if ( ! is_a( $user, 'WP_User' ) ) return $user;
        $status = get_user_meta( $user->ID, 'am_account_status', true );
        if ( 'pending' === $status ) return new WP_Error( 'am_pending', __( 'Your account is pending admin approval.', 'ababil-members' ) );
        if ( 'rejected' === $status ) return new WP_Error( 'am_rejected', __( 'Your account was not approved.', 'ababil-members' ) );
        if ( (int) get_user_meta( $user->ID, 'am_banned', true ) ) return new WP_Error( 'am_banned', __( 'This account is banned.', 'ababil-members' ) );
        $this->check_password_expiry( $user->ID );
        return $user;
    }

    /** Validate a password against the admin policy. Returns empty string when valid. */
    public static function validate_password( $pass ) {
        $pw = am_get_option( 'am_password', array() );
        $pw = is_array( $pw ) ? $pw : array();
        $min = max( 6, (int) ( $pw['min_length'] ?? 6 ) );
        if ( strlen( $pass ) < $min ) {
            return sprintf( __( 'Password needs at least %d characters.', 'ababil-members' ), $min );
        }
        if ( ! empty( $pw['complexity'] ) ) {
            if ( ! preg_match( '/[a-z]/', $pass ) || ! preg_match( '/[A-Z]/', $pass ) ||
                 ! preg_match( '/[0-9]/', $pass ) || ! preg_match( '/[^A-Za-z0-9]/', $pass ) ) {
                return __( 'Password must include uppercase, lowercase, a number and a symbol.', 'ababil-members' );
            }
        }
        return '';
    }

    /** Flag (do not block) accounts whose password is older than the expiry window. */
    private function check_password_expiry( $user_id ) {
        $pw = am_get_option( 'am_password', array() );
        $pw = is_array( $pw ) ? $pw : array();
        $days = (int) ( $pw['expiry_days'] ?? 0 );
        if ( $days <= 0 ) { delete_user_meta( $user_id, 'am_pw_expired' ); return; }
        $changed = (int) get_user_meta( $user_id, 'am_pw_changed', true );
        if ( ! $changed ) { update_user_meta( $user_id, 'am_pw_changed', current_time( 'timestamp' ) ); delete_user_meta( $user_id, 'am_pw_expired' ); return; }
        $expired = ( current_time( 'timestamp' ) - $changed ) > ( $days * DAY_IN_SECONDS );
        if ( $expired ) update_user_meta( $user_id, 'am_pw_expired', 1 );
        else delete_user_meta( $user_id, 'am_pw_expired' );
    }

    /** Where a member lands after registering, based on the admin setting. */
    private static function after_register_url() {
        $reg = am_registration_settings();
        if ( 'feed' === $reg['redirect'] ) return am_page_url( 'am-feed' );
        if ( 'members' === $reg['redirect'] ) return am_page_url( 'am-members' );
        if ( 'home' === $reg['redirect'] ) return home_url( '/' );
        return am_page_url( 'am-profile' );
    }

    /** Resolve the configured display-name format for a new member. */
    private static function display_name_for( $username, $email ) {
        $reg = am_registration_settings();
        $fmt = $reg['display_name'];
        if ( 'email' === $fmt ) return $email;
        if ( 0 === strpos( $fmt, 'field:' ) ) {
            $slug  = sanitize_key( substr( $fmt, 6 ) );
            $field = AM_Fields::get( $slug );
            if ( $field ) {
                $value = AM_Fields::sanitize( $field, $_POST[ $slug ] ?? '' );
                if ( '' !== $value ) return $value;
            }
        }
        return $username;
    }

    public function handle_submit() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['am_action'] ) ) return;
        $action = sanitize_key( $_POST['am_action'] );

        if ( 'register' === $action ) {
            if ( ! isset( $_POST['am_nonce'] ) || ! wp_verify_nonce( $_POST['am_nonce'], 'am_register' ) ) return;
            if ( ! am_get_option( 'registration', 1 ) ) return;

            if ( ! AM_Security::bot_check_ok() ) {
                $GLOBALS['am_error'] = AM_Security::is_locked()
                    ? __( 'Too many attempts. Please try again later.', 'ababil-members' )
                    : __( 'Bot check failed. Please try again.', 'ababil-members' );
                return;
            }

            $consent = am_consent_settings();
            if ( $consent['enabled'] && empty( $_POST['am_consent'] ) ) {
                $GLOBALS['am_error'] = __( 'Please accept the terms to register.', 'ababil-members' );
                return;
            }

            $username = sanitize_user( $_POST['username'] ?? '' );
            $email    = sanitize_email( $_POST['email'] ?? '' );
            $pass     = (string) ( $_POST['password'] ?? '' );
            $err      = '';

            if ( ! preg_match( '/^[A-Za-z0-9_]{3,20}$/', $username ) ) $err = __( 'Username must be 3-20 letters (a-z, 0-9, _).', 'ababil-members' );
            elseif ( ! is_email( $email ) ) $err = __( 'Please enter a valid email.', 'ababil-members' );
            elseif ( ( $pwerr = self::validate_password( $pass ) ) ) $err = $pwerr;
            elseif ( username_exists( $username ) || email_exists( $email ) ) $err = __( 'Username or email already registered.', 'ababil-members' );

            if ( ! $err ) {
                $opts = get_option( 'am_options', array() );
                $chosen = isset( $opts['form_fields'] ) && is_array( $opts['form_fields'] ) ? $opts['form_fields'] : array();
                foreach ( AM_Fields::all() as $field ) {
                    if ( ! in_array( $field['slug'], $chosen, true ) || empty( $field['required'] ) ) continue;
                    if ( trim( (string) ( $_POST[ $field['slug'] ] ?? '' ) ) === '' ) {
                        $err = sprintf( __( '%s is required.', 'ababil-members' ), $field['label'] );
                        break;
                    }
                }
            }

            if ( $err ) {
                $GLOBALS['am_error'] = $err;
                return;
            }

            $reg = am_registration_settings();
            $role = in_array( $reg['role'], array( 'subscriber', 'contributor', 'author' ), true ) ? $reg['role'] : 'subscriber';
            $user_id = wp_insert_user( array(
                'user_login' => $username,
                'user_email' => $email,
                'user_pass'  => $pass,
                'role'       => $role,
                'display_name' => self::display_name_for( $username, $email ),
            ) );
            if ( is_wp_error( $user_id ) ) {
                $GLOBALS['am_error'] = $user_id->get_error_message();
                return;
            }
            update_user_meta( $user_id, 'am_role', 'member' );
            update_user_meta( $user_id, 'am_points', 0 );
            update_user_meta( $user_id, 'am_account_status', am_get_option( 'register_status', 'approved' ) );
            update_user_meta( $user_id, 'am_avatar', sanitize_text_field( $_POST['avatar'] ?? '😀' ) );
            update_user_meta( $user_id, 'am_pw_changed', current_time( 'timestamp' ) );
            if ( $reg['gravatar'] ) update_user_meta( $user_id, 'am_avatar_url', esc_url_raw( get_avatar_url( $email ) ) );
            if ( $consent['enabled'] ) update_user_meta( $user_id, 'am_consent_time', current_time( 'mysql' ) );
            $opts2 = get_option( 'am_options', array() );
            $chosen2 = isset( $opts2['form_fields'] ) && is_array( $opts2['form_fields'] ) ? $opts2['form_fields'] : array();
            $custom = array_values( array_filter( AM_Fields::all(), function ( $f ) use ( $chosen2 ) { return in_array( $f['slug'], $chosen2, true ); } ) );
            AM_Fields::save( $user_id, $custom, $_POST );
            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id );
            do_action( 'am_register', $user_id );
            am_safe_redirect( self::after_register_url() );
        }

        if ( 'login' === $action ) {
            if ( ! isset( $_POST['am_nonce'] ) || ! wp_verify_nonce( $_POST['am_nonce'], 'am_login' ) ) return;
            if ( AM_Security::is_locked() ) {
                $GLOBALS['am_error'] = __( 'Too many attempts. Please try again later.', 'ababil-members' );
                return;
            }
            if ( ! AM_Security::bot_check_ok() ) {
                $GLOBALS['am_error'] = __( 'Bot check failed. Please try again.', 'ababil-members' );
                return;
            }
            $creds = array(
                'user_login'    => trim( $_POST['username'] ?? '' ),
                'user_password' => (string) ( $_POST['password'] ?? '' ),
                'remember'      => ! empty( $_POST['remember'] ),
            );

            // Two-factor pre-check. Only runs when 2FA is enabled globally so the
            // normal login path stays identical to previous versions.
            if ( AM_Security::two_factor_active() ) {
                $pre = wp_authenticate( $creds['user_login'], $creds['user_password'] );
                if ( is_wp_error( $pre ) ) {
                    do_action( 'wp_login_failed', $creds['user_login'] );
                    $GLOBALS['am_error'] = __( 'Wrong username or password.', 'ababil-members' );
                    return;
                }
                if ( AM_Security::two_factor_enabled_for( $pre->ID ) ) {
                    $token = AM_Security::begin_2fa( $pre->ID );
                    am_safe_redirect( add_query_arg( 'am_2fa', $token, am_page_url( 'am-login' ) ) );
                }
            }

            $user = wp_signon( $creds, false );
            if ( is_wp_error( $user ) ) {
                $GLOBALS['am_error'] = __( 'Wrong username or password.', 'ababil-members' );
                return;
            }
            self::finish_login( $user );
        }

        if ( 'am_2fa_verify' === $action ) {
            if ( ! isset( $_POST['am_nonce'] ) || ! wp_verify_nonce( $_POST['am_nonce'], 'am_2fa' ) ) return;
            $token = sanitize_key( $_POST['am_2fa_token'] ?? '' );
            $code  = (string) ( $_POST['am_2fa_code'] ?? '' );
            $uid   = AM_Security::complete_2fa( $token, $code );
            if ( ! $uid ) {
                $GLOBALS['am_error'] = __( 'Invalid or expired code. Please try again.', 'ababil-members' );
                return;
            }
            $user = get_userdata( $uid );
            if ( ! $user ) {
                $GLOBALS['am_error'] = __( 'Invalid or expired code. Please try again.', 'ababil-members' );
                return;
            }
            wp_set_current_user( $uid );
            wp_set_auth_cookie( $uid );
            self::finish_login( $user );
        }
    }

    /** Shared post-login bookkeeping + redirect. */
    private static function finish_login( $user ) {
        update_user_meta( $user->ID, 'am_last_login', current_time( 'mysql' ) );
        am_add_points( $user->ID, (int) am_get_option( 'points_per_login', 1 ) );
        do_action( 'am_login', $user->ID );
        am_safe_redirect( am_page_url( 'am-profile' ) );
    }
}
