<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Phase 3 — GDPR personal-data export / erasure.
 * Integrates with WordPress core privacy tools (Tools → Export/Erase Personal Data)
 * and powers the admin export/erase buttons on the Privacy page.
 */
class AM_Privacy {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
    }

    public function register_exporter( $exporters ) {
        $exporters['ababil-members'] = array(
            'exporter_friendly_name' => __( 'Ababil Members', 'ababil-members' ),
            'callback'               => array( $this, 'export' ),
        );
        return $exporters;
    }

    public function register_eraser( $erasers ) {
        $erasers['ababil-members'] = array(
            'eraser_friendly_name' => __( 'Ababil Members', 'ababil-members' ),
            'callback'             => array( $this, 'erase' ),
        );
        return $erasers;
    }

    /** Meta keys that belong to this plugin. */
    private static function meta_keys() {
        $keys = array(
            'am_role', 'am_points', 'am_account_status', 'am_banned', 'am_last_login',
            'am_avatar', 'am_avatar_url', 'am_cover_url', 'am_cover_pos_x', 'am_cover_pos_y',
            'am_fav_color', 'am_age', 'am_gender', 'am_location', 'am_badge',
        );
        foreach ( AM_Fields::all() as $f ) {
            $keys[] = AM_Fields::meta_key( $f['slug'] );
        }
        return $keys;
    }

    /** Collect all plugin data for a user, grouped by area. */
    public static function export_user( $user_id ) {
        global $wpdb;
        $user_id = (int) $user_id;
        $user = get_userdata( $user_id );
        $groups = array();

        // Profile + usermeta
        $profile = array();
        if ( $user ) {
            $profile['user_login'] = $user->user_login;
            $profile['display_name'] = $user->display_name;
            $profile['email'] = $user->user_email;
        }
        foreach ( self::meta_keys() as $key ) {
            $val = get_user_meta( $user_id, $key, true );
            if ( $val !== '' && $val !== false && $val !== null ) $profile[ $key ] = maybe_unserialize( $val );
        }
        $groups['profile'] = array( 'label' => __( 'Profile & settings', 'ababil-members' ), 'rows' => $profile );

        // Custom-table rows, keyed by the column that references the user.
        $tables = array(
            'posts'          => array( 'user_id', __( 'Feed posts', 'ababil-members' ) ),
            'comments'       => array( 'user_id', __( 'Comments & replies', 'ababil-members' ) ),
            'reviews'        => array( 'user_id', __( 'Reviews written', 'ababil-members' ) ),
            'reviews_target' => array( 'target_id', __( 'Reviews received', 'ababil-members' ) ),
            'messages_from'  => array( 'from_id', __( 'Messages sent', 'ababil-members' ) ),
            'messages_to'    => array( 'to_id', __( 'Messages received', 'ababil-members' ) ),
            'follows_from'   => array( 'follower_id', __( 'Following', 'ababil-members' ) ),
            'follows_to'     => array( 'followed_id', __( 'Followers', 'ababil-members' ) ),
            'likes'          => array( 'user_id', __( 'Post likes', 'ababil-members' ) ),
            'comment_likes'  => array( 'user_id', __( 'Comment likes', 'ababil-members' ) ),
            'group_members'  => array( 'user_id', __( 'Group memberships', 'ababil-members' ) ),
            'groups'         => array( 'creator_id', __( 'Groups created', 'ababil-members' ) ),
            'reports'        => array( 'reporter_id', __( 'Reports filed', 'ababil-members' ) ),
            'activity'       => array( 'user_id', __( 'Activity log', 'ababil-members' ) ),
        );
        $table_map = array(
            'reviews_target' => 'reviews',
            'messages_from'  => 'messages',
            'messages_to'    => 'messages',
            'follows_from'   => 'follows',
            'follows_to'     => 'follows',
        );
        $no_id = array( 'likes', 'comment_likes', 'group_members', 'follows_from', 'follows_to' );
        foreach ( $tables as $key => $def ) {
            list( $col, $label ) = $def;
            $table = isset( $table_map[ $key ] ) ? AM_Social::table( $table_map[ $key ] ) : AM_Social::table( $key );
            $order = in_array( $key, $no_id, true ) ? '' : ' ORDER BY id DESC';
            $rows = $wpdb->get_results(
                $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE ' . $col . ' = %d' . $order, $user_id ),
                ARRAY_A
            );
            $groups[ $key ] = array( 'label' => $label, 'rows' => is_array( $rows ) ? $rows : array() );
        }

        return array(
            'user_id' => $user_id,
            'groups'  => $groups,
        );
    }

    /** Permanently delete all plugin data for a user. */
    public static function erase_user( $user_id ) {
        global $wpdb;
        $user_id = (int) $user_id;
        $removed = 0;

        // Usermeta
        foreach ( self::meta_keys() as $key ) {
            delete_user_meta( $user_id, $key );
            $removed++;
        }

        $deletes = array(
            array( 'posts', 'user_id' ),
            array( 'comments', 'user_id' ),
            array( 'reviews', 'user_id' ),
            array( 'reviews', 'target_id' ),
            array( 'messages', 'from_id' ),
            array( 'messages', 'to_id' ),
            array( 'follows', 'follower_id' ),
            array( 'follows', 'followed_id' ),
            array( 'likes', 'user_id' ),
            array( 'comment_likes', 'user_id' ),
            array( 'group_members', 'user_id' ),
            array( 'groups', 'creator_id' ),
            array( 'reports', 'reporter_id' ),
            array( 'activity', 'user_id' ),
        );
        foreach ( $deletes as $d ) {
            list( $table, $col ) = $d;
            $removed += (int) $wpdb->delete( AM_Social::table( $table ), array( $col => $user_id ), array( '%d' ) );
        }

        // Clear cached exports that may reference the user.
        delete_transient( 'am_export_admin' );

        do_action( 'am_privacy_erased', $user_id, $removed );
        return $removed;
    }

    /** WP core exporter callback: $email or username, $page (1-based). */
    public function export( $email, $page = 1 ) {
        $user = get_user_by( 'email', $email );
        if ( ! $user ) $user = get_user_by( 'login', $email );
        if ( ! $user ) {
            return array( 'data' => array(), 'done' => true, 'item_removed' => false );
        }
        $export = self::export_user( $user->ID );
        $items = array();
        foreach ( $export['groups'] as $gkey => $group ) {
            $rows = $group['rows'];
            if ( $gkey === 'profile' ) {
                foreach ( $rows as $name => $value ) {
                    $items[] = array(
                        'item_id'    => 'profile-' . sanitize_key( $name ),
                        'name'       => $name,
                        'value'      => is_scalar( $value ) ? $value : wp_json_encode( $value ),
                    );
                }
            } else {
                foreach ( $rows as $i => $row ) {
                    $items[] = array(
                        'item_id'    => $gkey . '-' . ( $row['id'] ?? ( $i + 1 ) ),
                        'name'       => $group['label'],
                        'value'      => wp_json_encode( $row ),
                    );
                }
            }
        }
        return array(
            'data' => array( array(
                'group_id'    => 'ababil-members',
                'group_label' => __( 'Ababil Members', 'ababil-members' ),
                'item_id'     => 'am-' . $user->ID,
                'data'        => $items,
            ) ),
            'done' => true,
        );
    }

    /** WP core eraser callback: $user_id, $page (1-based). */
    public function erase( $user_id, $page = 1 ) {
        $removed = self::erase_user( $user_id );
        return array(
            'items_removed'  => $removed > 0,
            'items_retained' => false,
            'messages'       => array( sprintf( __( 'Removed %d Ababil Members data records.', 'ababil-members' ), $removed ) ),
            'done'           => true,
        );
    }
}
