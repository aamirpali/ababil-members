<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Profile {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'query_vars', array( $this, 'query_var' ) );
        add_action( 'init', array( $this, 'add_rewrite' ) );
        add_action( 'template_redirect', array( $this, 'save_profile' ), 5 );
        // Use template_include (NOT template_redirect + exit) so wp_head/wp_footer still run.
        add_filter( 'template_include', array( $this, 'template' ), 99 );
    }

    public static function add_rewrite() {
        $base = function_exists( 'am_permalink_base' ) ? am_permalink_base() : 'user';
        add_rewrite_rule( '^' . $base . '/([^/]+)/?$', 'index.php?am_user=$matches[1]', 'top' );
    }

    public function query_var( $vars ) {
        $vars[] = 'am_user';
        return $vars;
    }

    public static function url( $username ) {
        $base = function_exists( 'am_permalink_base' ) ? am_permalink_base() : 'user';
        return home_url( '/' . $base . '/' . rawurlencode( $username ) . '/' );
    }

    public static function user_by_name( $name ) {
        return get_user_by( 'login', sanitize_user( $name ) );
    }

    public function save_profile() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['am_action'] ) || 'profile' !== $_POST['am_action'] ) return;
        if ( ! is_user_logged_in() ) return;
        if ( ! isset( $_POST['am_nonce'] ) || ! wp_verify_nonce( $_POST['am_nonce'], 'am_profile' ) ) return;

        $uid = get_current_user_id();
        if ( isset( $_POST['display_name'] ) ) {
            wp_update_user( array( 'ID' => $uid, 'display_name' => sanitize_text_field( $_POST['display_name'] ) ) );
        }
        if ( isset( $_POST['description'] ) ) {
            update_user_meta( $uid, 'description', sanitize_textarea_field( $_POST['description'] ) );
        }
        $fav = sanitize_text_field( $_POST['fav_color'] ?? '' );
        if ( $fav !== '' ) update_user_meta( $uid, 'am_fav_color', mb_substr( $fav, 0, 40 ) );

        $avatars = array( '😀','😎','🐱','🦊','🐼','🐯','🦁','🐸','🦄','🚀','⭐','🌈' );
        if ( ! empty( $_POST['avatar'] ) && in_array( $_POST['avatar'], $avatars, true ) ) {
            update_user_meta( $uid, 'am_avatar', $_POST['avatar'] );
        }

        $ages = array( 'Prefer not to say','5-9','10-14','15-19','20-29','30-39','40-49','50-59','60+' );
        if ( isset( $_POST['am_age'] ) && in_array( $_POST['am_age'], $ages, true ) ) {
            update_user_meta( $uid, 'am_age', $_POST['am_age'] );
        }
        $genders = array( 'Prefer not to say','Female','Male','Non-binary','Other' );
        if ( isset( $_POST['am_gender'] ) && in_array( $_POST['am_gender'], $genders, true ) ) {
            update_user_meta( $uid, 'am_gender', $_POST['am_gender'] );
        }
        $locations = array( 'Prefer not to say','India','Pakistan','UAE','Saudi Arabia','United Kingdom','United States','Canada','Australia','France','Germany','Spain','Other' );
        if ( isset( $_POST['am_location'] ) && in_array( $_POST['am_location'], $locations, true ) ) {
            update_user_meta( $uid, 'am_location', $_POST['am_location'] );
        }

        // Avatar / cover uploads — optional. Empty file input = no change.
        $uploads = array(
            'am_avatar_file' => array( 'am_avatar_url', 'avatars' ),
            'am_cover_file'  => array( 'am_cover_url',  'covers' ),
        );
        foreach ( $uploads as $file_key => $setup ) {
            if ( empty( $_FILES[ $file_key ]['name'] ) ) continue;
            $file = $_FILES[ $file_key ];
            if ( ! empty( $file['error'] ) || (int) $file['size'] > 2 * 1024 * 1024 ) continue;

            require_once ABSPATH . 'wp-admin/includes/file.php';
            $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
            $allowed = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
            if ( empty( $check['type'] ) || ! in_array( $check['type'], $allowed, true ) ) continue;

            if ( 'am_cover_file' === $file_key ) {
                $size = @getimagesize( $file['tmp_name'] );
                if ( ! $size || (int) $size[0] < 1024 ) {
                    $GLOBALS['am_profile_error'] = __( 'Cover image must be at least 1024px wide.', 'ababil-members' );
                    continue;
                }
            }

            $subdir = 'ababil-members/' . $setup[1];
            $dir_filter = function ( $dirs ) use ( $subdir ) {
                $dirs['subdir'] = '/' . $subdir;
                $dirs['path']   = $dirs['basedir'] . '/' . $subdir;
                $dirs['url']    = $dirs['baseurl'] . '/' . $subdir;
                if ( ! is_dir( $dirs['path'] ) ) wp_mkdir_p( $dirs['path'] );
                return $dirs;
            };
            add_filter( 'upload_dir', $dir_filter );
            $result = wp_handle_upload( $file, array( 'test_form' => false, 'action' => 'am_profile_upload' ) );
            remove_filter( 'upload_dir', $dir_filter );

            if ( ! empty( $result['url'] ) && empty( $result['error'] ) ) {
                update_user_meta( $uid, $setup[0], esc_url_raw( $result['url'] ) );
            }
        }

        // Cover crop position (0–100% object-position), saved from the drag editor.
        foreach ( array( 'am_cover_pos_x', 'am_cover_pos_y' ) as $pos_key ) {
            if ( isset( $_POST[ $pos_key ] ) && is_numeric( $_POST[ $pos_key ] ) ) {
                $pos = max( 0, min( 100, (int) $_POST[ $pos_key ] ) );
                update_user_meta( $uid, $pos_key, $pos );
            }
        }

        AM_Fields::save( $uid, AM_Fields::all(), $_POST );

        do_action( 'am_profile_updated', $uid );

        // Post/Redirect/Get: always end a successful save on a clean GET.
        if ( empty( $GLOBALS['am_profile_error'] ) ) {
            am_safe_redirect( add_query_arg( 'am_saved', '1' ) );
        }
    }

    public function template( $template ) {
        $username = get_query_var( 'am_user' );
        if ( ! $username ) return $template;

        $user = self::user_by_name( $username );
        if ( ! $user ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            return get_404_template();
        }

        $GLOBALS['am_profile_user'] = $user;
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'activity';
        $GLOBALS['am_profile_tab'] = in_array( $tab, array( 'activity', 'followers', 'following', 'edit' ), true ) ? $tab : 'activity';

        add_filter( 'document_title_parts', function ( $parts ) use ( $user ) {
            $parts['title'] = ( $user->display_name ?: $user->user_login ) . ' — ' . get_bloginfo( 'name' );
            return $parts;
        } );

        return AM_PATH . 'templates/profile-full.php';
    }
}
