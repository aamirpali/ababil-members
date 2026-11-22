<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Access {
    private static $instance = null;
    /** Reason for the most recent can_view() denial. */
    public static $reason = '';

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {}

    /**
     * Check whether the current user may view restricted content.
     * $rules accepts role / points / status; omitted values fall back to the
     * global "Password & Access" settings.
     */
    public static function can_view( $need = 'member', $rules = array() ) {
        self::$reason = '';
        if ( ! is_user_logged_in() ) {
            self::$reason = 'login';
            return false;
        }
        $user = wp_get_current_user();
        $uid  = (int) $user->ID;
        if ( in_array( 'banned', (array) $user->roles, true ) || (int) get_user_meta( $uid, 'am_banned', true ) ) {
            self::$reason = 'banned';
            return false;
        }
        if ( 'admin' === $need ) {
            if ( ! current_user_can( 'manage_options' ) ) self::$reason = 'admin';
            return current_user_can( 'manage_options' );
        }

        // Merge global rules with explicit overrides.
        $saved = am_get_option( 'am_access_rules', array() );
        $saved = is_array( $saved ) ? $saved : array();
        $rules = is_array( $rules ) ? $rules : array();
        $role   = ( isset( $rules['role'] ) && $rules['role'] !== '' ) ? sanitize_key( $rules['role'] ) : ( $saved['min_role'] ?? '' );
        $points = ( isset( $rules['points'] ) && (int) $rules['points'] > 0 ) ? (int) $rules['points'] : (int) ( $saved['min_points'] ?? 0 );
        $status = ( isset( $rules['status'] ) && $rules['status'] !== '' ) ? sanitize_key( $rules['status'] ) : ( $saved['require_status'] ?? '' );

        if ( $role && ! self::role_at_least( $user, $role ) ) {
            self::$reason = 'role';
            return false;
        }
        if ( $points > 0 && am_get_points( $uid ) < $points ) {
            self::$reason = 'points';
            return false;
        }
        if ( $status ) {
            $current = get_user_meta( $uid, 'am_account_status', true );
            $current = $current ? $current : 'approved';
            if ( $current !== $status ) {
                self::$reason = 'status';
                return false;
            }
        }
        return true;
    }

    /** True when the user's highest role is at least as privileged as $needed. */
    private static function role_at_least( $user, $needed ) {
        $order = apply_filters( 'am_role_order', array(
            'subscriber' => 1,
            'contributor' => 2,
            'author' => 3,
            'editor' => 4,
            'administrator' => 5,
        ) );
        if ( ! isset( $order[ $needed ] ) ) return current_user_can( $needed );
        $need_rank = (int) $order[ $needed ];
        $best = 0;
        foreach ( (array) $user->roles as $r ) {
            if ( isset( $order[ $r ] ) ) $best = max( $best, (int) $order[ $r ] );
        }
        return $best >= $need_rank;
    }

    /** Human-readable text for the most recent denial. */
    public static function reason_text() {
        switch ( self::$reason ) {
            case 'role':   return __( 'Your account role does not have access to this area.', 'ababil-members' );
            case 'points': return __( 'You need more points to access this area.', 'ababil-members' );
            case 'status': return __( 'Your account status does not allow access to this area.', 'ababil-members' );
            case 'banned': return __( 'Your account is banned.', 'ababil-members' );
            case 'admin':  return __( 'This area is for administrators only.', 'ababil-members' );
            default:       return '';
        }
    }
}
