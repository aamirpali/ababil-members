<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Shortcodes {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {
        add_shortcode( 'am_login',    array( $this, 'login_form' ) );
        add_shortcode( 'am_register', array( $this, 'register_form' ) );
        add_shortcode( 'am_profile',  array( $this, 'profile' ) );
        add_shortcode( 'am_members',  array( $this, 'members' ) );
        add_shortcode( 'am_restrict', array( $this, 'restrict' ) );
        add_shortcode( 'am_messages', array( $this, 'messages' ) );
        add_shortcode( 'am_logout', array( $this, 'logout' ) );
        add_shortcode( 'am_feed', array( $this, 'feed' ) );
        add_shortcode( 'am_groups', array( $this, 'groups' ) );
        add_action( 'template_redirect', array( $this, 'handle_logout' ) );
    }

    private function render( $slug, $vars = array() ) {
        $file = AM_PATH . 'templates/' . $slug . '.php';
        if ( ! is_file( $file ) ) return '';
        extract( $vars );
        ob_start();
        include $file;
        return ob_get_clean();
    }

    public function login_form() {
        if ( is_user_logged_in() ) return '<div class="ig-plugin-container"><p class="ig-notice">✅ ' . esc_html__( 'You are already logged in.', 'ababil-members' ) . '</p></div>';
        return $this->render( 'ig-login' );
    }

    public function register_form() {
        if ( ! am_get_option( 'registration', 1 ) ) return '<div class="ig-plugin-container"><p class="ig-notice">' . esc_html__( 'Registration is closed.', 'ababil-members' ) . '</p></div>';
        if ( is_user_logged_in() ) return '<div class="ig-plugin-container"><p class="ig-notice">✅ ' . esc_html__( 'You are already registered.', 'ababil-members' ) . '</p></div>';
        return $this->render( 'ig-register' );
    }

    public function profile() {
        if ( ! is_user_logged_in() ) return '<p>' . esc_html__( 'Please login to view your profile.', 'ababil-members' ) . '</p>';
        $GLOBALS['am_profile_user'] = wp_get_current_user();
        return $this->render( 'ig-profile' );
    }

    public function members() {
        if ( ! am_get_option( 'member_directory', 1 ) ) return '';
        $search = isset( $_GET['am_q'] ) ? sanitize_text_field( wp_unslash( $_GET['am_q'] ) ) : '';
        return $this->render( 'ig-members' );
    }

    public function messages() {
        if ( ! am_get_option( 'messaging', 1 ) ) return '';
        if ( ! is_user_logged_in() ) return '<p>' . esc_html__( 'Please login to view messages.', 'ababil-members' ) . '</p>';
        return $this->render( 'ig-messages' );
    }

    public function handle_logout() {
        if ( ! isset( $_GET['am_logout'] ) || '1' !== $_GET['am_logout'] ) return;
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'am_logout' ) ) return;
        if ( is_user_logged_in() ) { wp_logout(); }
        wp_safe_redirect( am_page_url( 'am-login' ) );
        exit;
    }

    public function logout() {
        if ( ! is_user_logged_in() ) {
            return '<div class="ig-plugin-container"><p class="ig-notice">✅ ' . esc_html__( 'You are already logged out.', 'ababil-members' ) . '</p>'
                . '<p><a class="ig-btn ig-btn-primary" href="' . esc_url( am_page_url( 'am-login' ) ) . '">' . esc_html__( 'Log in again', 'ababil-members' ) . '</a></p></div>';
        }
        $url = add_query_arg( array( 'am_logout' => '1', '_wpnonce' => wp_create_nonce( 'am_logout' ) ), am_page_url( 'am-logout' ) );
        return '<div class="ig-plugin-container ig-auth-wrap"><div class="ig-auth-card">'
            . '<h2 class="ig-auth-title">' . esc_html__( 'Log out', 'ababil-members' ) . '</h2>'
            . '<p class="ig-auth-sub">' . esc_html__( 'Are you sure you want to log out?', 'ababil-members' ) . '</p>'
            . '<a class="ig-btn ig-btn-primary ig-auth-submit" href="' . esc_url( $url ) . '">' . esc_html__( 'Yes, log me out', 'ababil-members' ) . '</a>'
            . '</div></div>';
    }

    public function feed() {
        if ( ! am_get_option( 'posts', 1 ) ) return '<div class="ig-plugin-container"><p class="ig-notice">' . esc_html__( 'Posts are disabled.', 'ababil-members' ) . '</p></div>';
        return $this->render( 'ig-feed' );
    }

    public function groups() {
        if ( ! am_get_option( 'groups', 1 ) ) return '<div class="ig-plugin-container"><p class="ig-notice">' . esc_html__( 'Groups are disabled.', 'ababil-members' ) . '</p></div>';
        return $this->render( 'ig-groups' );
    }

    public function restrict( $atts, $content = '' ) {
        if ( ! am_get_option( 'restriction', 1 ) ) return do_shortcode( $content );
        $atts = shortcode_atts( array(
            'need'   => 'member',
            'role'   => '',
            'points' => 0,
            'status' => '',
        ), $atts, 'am_restrict' );

        $rules = array(
            'role'   => $atts['role'],
            'points' => (int) $atts['points'],
            'status' => $atts['status'],
        );
        if ( ! AM_Access::can_view( sanitize_key( $atts['need'] ), $rules ) ) {
            $reason = AM_Access::reason_text();
            $msg = $reason ? $reason : sprintf(
                /* translators: %s = login URL */
                esc_html__( 'This area is for members. %sPlease login%s.', 'ababil-members' ),
                '<a href="' . esc_url( am_page_url( 'am-login' ) ) . '">',
                '</a>'
            );
            return '<div class="ig-plugin-container"><p class="ig-notice am-locked">🔒 ' . $msg . '</p></div>';
        }
        return do_shortcode( $content );
    }
}
