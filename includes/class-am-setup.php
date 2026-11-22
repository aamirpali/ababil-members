<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Setup {
    /** Safe to run on every plugin update — creates missing tables/pages and flushes rewrite once. */
    public static function maybe_upgrade() {
        if ( get_option( 'am_db_version' ) === AM_VERSION ) return;
        // One-time addition for sites upgrading to v1.3.3.
        if ( ! get_option( 'am_logout_page_added' ) ) {
            self::create_page( 'am-logout', __( 'Log out', 'ababil-members' ), '[am_logout]' );
            update_option( 'am_logout_page_added', 1 );
        }
        // Other pages are created on activation only. Deleted pages stay deleted on upgrades.
        AM_Followers::create_table();
        AM_Messaging::upgrade_table();
        AM_Activity::create_table();
        AM_Social::create_tables();
        AM_Social::upgrade_tables();
        AM_Security::create_table();
        AM_Activity::purge_messages();
        self::set_new_defaults();
        AM_Profile::add_rewrite();
        flush_rewrite_rules();
        update_option( 'am_db_version', AM_VERSION );
    }

    public static function activate() {
        self::create_pages();
        self::set_defaults();
        AM_Followers::create_table();
        AM_Messaging::upgrade_table();
        AM_Activity::create_table();
        AM_Social::create_tables();
        AM_Social::upgrade_tables();
        AM_Security::create_table();
        AM_Activity::purge_messages();
        self::set_new_defaults();
        AM_Profile::add_rewrite();
        flush_rewrite_rules();
        do_action( 'am_activated' );
    }

    public static function deactivate() {
        flush_rewrite_rules();
        do_action( 'am_deactivated' );
    }

    private static function set_defaults() {
        if ( ! get_option( 'am_options' ) ) {
            update_option( 'am_options', array(
                'registration'      => 1,
                'badges'            => 1,
                'member_directory'  => 1,
                'restriction'       => 1,
                'points_per_post'   => 2,
                'points_per_login'  => 1,
                'followers'         => 1,
                'messaging'         => 1,
                'activity'          => 1,
                'posts'             => 1,
                'groups'            => 1,
                'reviews'           => 1,
            ) );
        }
    }

    /** Idempotently add Phase 4 option defaults without overwriting saved settings. */
    private static function set_new_defaults() {
        $opts = get_option( 'am_options', array() );
        if ( ! is_array( $opts ) ) $opts = array();
        $defaults = array(
            'am_pages'        => array(),
            'am_registration' => array( 'role' => 'subscriber', 'display_name' => 'username', 'gravatar' => 0, 'redirect' => 'profile' ),
            'am_permalink_base' => 'user',
            'am_consent'      => array( 'enabled' => 0, 'text' => __( 'I agree to the terms and privacy policy.', 'ababil-members' ) ),
            'am_security'     => array( 'rate_limit' => 1, 'captcha' => 1, 'two_factor' => 0, 'audit_log' => 1, 'max_attempts' => 5, 'lockout_minutes' => 15 ),
            'am_fields_order' => array(),
            'menu_visibility' => 1,
        );
        $changed = false;
        foreach ( $defaults as $key => $value ) {
            if ( ! array_key_exists( $key, $opts ) ) { $opts[ $key ] = $value; $changed = true; }
        }
        if ( $changed ) update_option( 'am_options', $opts );
    }

    private static function create_pages() {
        $site = get_bloginfo( 'name' );
        $pages = array(
            'am-login'    => array( $site . ' ' . __( 'Login', 'ababil-members' ),  '[am_login]' ),
            'am-register' => array( $site,                                            '[am_register]' ),
            'am-profile'  => array( __( 'My Profile', 'ababil-members' ),   '[am_profile]' ),
            'am-members'  => array( __( 'Members', 'ababil-members' ),      '[am_members]' ),
            'am-messages' => array( __( 'Messages', 'ababil-members' ),     '[am_messages]' ),
            'am-logout'   => array( __( 'Log out', 'ababil-members' ),      '[am_logout]' ),
        );
        foreach ( $pages as $slug => $data ) {
            self::create_page( $slug, $data[0], $data[1] );
        }
    }

    private static function create_page( $slug, $title, $content ) {
        if ( get_page_by_path( $slug ) ) return;
        wp_insert_post( array(
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
    }
}
