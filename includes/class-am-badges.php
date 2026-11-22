<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Badges {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {
        if ( am_get_option( 'badges', 1 ) ) {
            add_action( 'am_login', array( $this, 'refresh' ) );
            add_action( 'am_points_changed', array( $this, 'refresh' ) );
        }
    }
    public function refresh( $user_id ) {
        $badge = am_badge_for_points( am_get_points( $user_id ) );
        update_user_meta( $user_id, 'am_badge', $badge['key'] );
        do_action( 'am_badge_changed', $user_id, $badge['key'] );
    }
    public static function html( $user_id ) {
        if ( ! am_get_option( 'badges', 1 ) ) return '';
        $badge = am_badge_for_points( am_get_points( $user_id ) );
        return '<span class="am-badge am-badge-' . esc_attr( $badge['key'] ) . '">' . esc_html( $badge['emoji'] . ' ' . $badge['label'] ) . '</span>';
    }
}
