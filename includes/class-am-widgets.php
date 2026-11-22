<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Phase 4 — lightweight widgets that reuse the plugin shortcode output.
 * All markup stays inside .ig-plugin-container so frontend CSS remains scoped.
 */
class AM_Widgets {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'widgets_init', array( $this, 'register' ) );
    }

    public function register() {
        register_widget( 'AM_Widget_Login' );
        register_widget( 'AM_Widget_Members' );
        register_widget( 'AM_Widget_Feed' );
    }

    /** Shortcode wrapper with a tidy widget heading. */
    private static function box( $title, $body ) {
        $out = '<div class="ig-plugin-container am-widget"><div class="am-widget-inner">';
        if ( $title ) $out .= '<h3 class="am-widget-title">' . esc_html( $title ) . '</h3>';
        $out .= $body;
        $out .= '</div></div>';
        return $out;
    }
}

/** Login / logout widget. */
class AM_Widget_Login extends WP_Widget {
    public function __construct() {
        parent::__construct( 'am_widget_login', __( 'Ababil — Login', 'ababil-members' ),
            array( 'description' => __( 'Login form, or a welcome panel for logged-in members.', 'ababil-members' ) ) );
    }

    public function widget( $args, $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : '';
        echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if ( is_user_logged_in() ) {
            $u = wp_get_current_user();
            $body = '<p class="am-widget-hi">' . esc_html( sprintf( __( 'Hi, %s!', 'ababil-members' ), $u->display_name ) ) . '</p>'
                  . '<p><a class="ig-btn ig-btn-primary" href="' . esc_url( am_page_url( 'am-profile' ) ) . '">' . esc_html__( 'My profile', 'ababil-members' ) . '</a></p>'
                  . '<p><a class="am-widget-link" href="' . esc_url( am_page_url( 'am-logout' ) ) . '">' . esc_html__( 'Log out', 'ababil-members' ) . '</a></p>';
        } else {
            $body = '<p><a class="ig-btn ig-btn-primary" href="' . esc_url( am_page_url( 'am-login' ) ) . '">' . esc_html__( 'Log in', 'ababil-members' ) . '</a></p>'
                  . ( am_get_option( 'registration', 1 ) ? '<p><a class="am-widget-link" href="' . esc_url( am_page_url( 'am-register' ) ) . '">' . esc_html__( 'Create an account', 'ababil-members' ) . '</a></p>' : '' );
        }
        echo AM_Widgets::box( $title, $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : __( 'Members', 'ababil-members' ); ?>
        <p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'ababil-members' ); ?></label>
        <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $title ); ?>"></p>
        <?php
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ?? '' ) );
    }
}

/** Recent members widget. */
class AM_Widget_Members extends WP_Widget {
    public function __construct() {
        parent::__construct( 'am_widget_members', __( 'Ababil — Members', 'ababil-members' ),
            array( 'description' => __( 'A short list of recent members.', 'ababil-members' ) ) );
    }

    public function widget( $args, $instance ) {
        if ( ! am_get_option( 'member_directory', 1 ) ) return;
        $title = isset( $instance['title'] ) ? $instance['title'] : __( 'New members', 'ababil-members' );
        $count = max( 1, min( 20, (int) ( $instance['count'] ?? 5 ) ) );
        $users = get_users( array( 'number' => $count, 'orderby' => 'registered', 'order' => 'DESC',
                                   'role__not_in' => array( 'administrator' ) ) );
        $body = '<ul class="am-widget-users">';
        foreach ( $users as $u ) {
            $avatar = get_user_meta( $u->ID, 'am_avatar', true ) ?: '🙂';
            $body .= '<li><span class="am-widget-avatar">' . esc_html( $avatar ) . '</span>'
                   . '<a href="' . esc_url( AM_Profile::url( $u->user_login ) ) . '">' . esc_html( $u->user_login ) . '</a></li>';
        }
        $body .= '</ul><p><a class="am-widget-link" href="' . esc_url( am_page_url( 'am-members' ) ) . '">' . esc_html__( 'View all members', 'ababil-members' ) . '</a></p>';
        echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo AM_Widgets::box( $title, $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : __( 'New members', 'ababil-members' );
        $count = (int) ( $instance['count'] ?? 5 ); ?>
        <p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'ababil-members' ); ?></label>
        <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $title ); ?>"></p>
        <p><label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Number to show:', 'ababil-members' ); ?></label>
        <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" min="1" max="20" value="<?php echo (int) $count; ?>"></p>
        <?php
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ?? '' ), 'count' => absint( $new['count'] ?? 5 ) );
    }
}

/** Recent activity / feed teaser widget. */
class AM_Widget_Feed extends WP_Widget {
    public function __construct() {
        parent::__construct( 'am_widget_feed', __( 'Ababil — Feed', 'ababil-members' ),
            array( 'description' => __( 'A short teaser of the community feed.', 'ababil-members' ) ) );
    }

    public function widget( $args, $instance ) {
        if ( ! am_get_option( 'posts', 1 ) ) return;
        $title = isset( $instance['title'] ) ? $instance['title'] : __( 'Community feed', 'ababil-members' );
        $body  = '<p class="am-widget-hi">' . esc_html__( 'See what the community is sharing.', 'ababil-members' ) . '</p>'
               . '<p><a class="ig-btn ig-btn-primary" href="' . esc_url( am_page_url( 'am-feed' ) ) . '">' . esc_html__( 'Open the feed', 'ababil-members' ) . '</a></p>';
        echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo AM_Widgets::box( $title, $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : __( 'Community feed', 'ababil-members' ); ?>
        <p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'ababil-members' ); ?></label>
        <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $title ); ?>"></p>
        <?php
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ?? '' ) );
    }
}
