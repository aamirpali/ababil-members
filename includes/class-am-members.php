<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Members {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {}

    /**
     * Phase 3 — directory query with role/status filters and pagination.
     * Returns array( users, total, pages, paged ).
     */
    public static function query( $search = '', $order = 'points', $args = array() ) {
        $args = wp_parse_args( $args, array(
            'role'    => '',
            'status'  => '',
            'paged'   => 1,
            'per_page'=> 12,
        ) );

        $wp_args = array(
            'orderby' => 'login',
            'order'   => 'ASC',
            'fields'  => 'all',
        );
        if ( $search !== '' ) $wp_args['search'] = '*' . sanitize_text_field( $search ) . '*';
        if ( $args['role'] !== '' ) $wp_args['role'] = sanitize_key( $args['role'] );

        $query = new WP_User_Query( $wp_args );
        $users = $query->get_results();

        if ( $args['status'] !== '' ) {
            $status = sanitize_key( $args['status'] );
            $users = array_values( array_filter( $users, function ( $u ) use ( $status ) {
                $s = get_user_meta( $u->ID, 'am_account_status', true );
                $s = $s ? $s : 'approved';
                $banned = (int) get_user_meta( $u->ID, 'am_banned', true );
                if ( 'banned' === $status ) return 1 === $banned;
                if ( 1 === $banned && 'approved' === $status ) return false;
                return $s === $status;
            } ) );
        }

        usort( $users, function ( $a, $b ) use ( $order ) {
            if ( 'points' === $order ) {
                $pa = (int) get_user_meta( $a->ID, 'am_points', true );
                $pb = (int) get_user_meta( $b->ID, 'am_points', true );
                return $pb <=> $pa;
            }
            return strcasecmp( $a->user_login, $b->user_login );
        } );

        $total = count( $users );
        $per   = max( 1, (int) $args['per_page'] );
        $pages = max( 1, (int) ceil( $total / $per ) );
        $paged = max( 1, min( $pages, (int) $args['paged'] ) );
        $slice = array_slice( $users, ( $paged - 1 ) * $per, $per );

        return array( 'users' => $slice, 'total' => $total, 'pages' => $pages, 'paged' => $paged );
    }

    /** Roles available for the directory filter. */
    public static function roles() {
        global $wp_roles;
        $roles = $wp_roles ? $wp_roles->get_names() : array();
        return apply_filters( 'am_directory_roles', $roles );
    }
}
