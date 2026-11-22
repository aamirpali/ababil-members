<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Phase 3 — email notifications with admin-editable templates.
 * All mail is opt-in per template and uses wp_mail(). No tracking pixels.
 */
class AM_Emails {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'am_register', array( $this, 'welcome' ), 10, 1 );
        add_action( 'am_message_sent', array( $this, 'new_message' ), 10, 4 );
        add_action( 'am_comment_added', array( $this, 'new_comment' ), 10, 5 );
        add_action( 'am_reply_added', array( $this, 'new_reply' ), 10, 5 );
        add_action( 'am_review_added', array( $this, 'new_review' ), 10, 5 );
    }

    /** Default templates: key => label, subject, body, available tokens. */
    public static function templates() {
        return array(
            'welcome' => array(
                'label'   => __( 'Welcome email (on register)', 'ababil-members' ),
                'subject' => 'Welcome to {site_name}, {user_login}!',
                'body'    => "Hi {user_login},\n\nYour account on {site_name} is ready.\nLogin: {login_url}\nProfile: {profile_url}\n\nThanks for joining!",
                'tokens'  => array( 'user_login', 'site_name', 'login_url', 'profile_url' ),
            ),
            'new_message' => array(
                'label'   => __( 'New private message', 'ababil-members' ),
                'subject' => 'New message from {sender} on {site_name}',
                'body'    => "Hi {recipient},\n\n{sender} sent you a message:\n\n{message}\n\nRead it: {messages_url}",
                'tokens'  => array( 'recipient', 'sender', 'message', 'site_name', 'messages_url' ),
            ),
            'new_comment' => array(
                'label'   => __( 'New comment on your post', 'ababil-members' ),
                'subject' => '{actor} commented on your post',
                'body'    => "Hi {recipient},\n\n{actor} commented on your post:\n\n{comment}\n\nView: {post_url}",
                'tokens'  => array( 'recipient', 'actor', 'comment', 'post_url', 'site_name' ),
            ),
            'new_reply' => array(
                'label'   => __( 'New reply to your comment', 'ababil-members' ),
                'subject' => '{actor} replied to you on {site_name}',
                'body'    => "Hi {recipient},\n\n{actor} replied to your comment:\n\n{reply}\n\nView: {post_url}",
                'tokens'  => array( 'recipient', 'actor', 'reply', 'post_url', 'site_name' ),
            ),
            'new_review' => array(
                'label'   => __( 'New review on your profile', 'ababil-members' ),
                'subject' => '{actor} left you a {rating}-star review',
                'body'    => "Hi {recipient},\n\n{actor} reviewed you:\n\n{review}\n\nView: {profile_url}",
                'tokens'  => array( 'recipient', 'actor', 'rating', 'review', 'profile_url', 'site_name' ),
            ),
        );
    }

    /** Saved template settings merged over defaults. */
    public static function get( $key ) {
        $all = self::templates();
        if ( ! isset( $all[ $key ] ) ) return null;
        $saved = am_get_option( 'am_emails', array() );
        $saved = is_array( $saved ) ? $saved : array();
        $row   = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array();
        return array(
            'enabled' => ! empty( $row['enabled'] ),
            'subject' => isset( $row['subject'] ) && $row['subject'] !== '' ? $row['subject'] : $all[ $key ]['subject'],
            'body'    => isset( $row['body'] ) && $row['body'] !== '' ? $row['body'] : $all[ $key ]['body'],
            'label'   => $all[ $key ]['label'],
            'tokens'  => $all[ $key ]['tokens'],
        );
    }

    /** Replace {token} placeholders with escaped values. */
    public static function render( $text, $tokens ) {
        $pairs = array();
        foreach ( $tokens as $k => $v ) {
            $pairs[ '{' . $k . '}' ] = is_scalar( $v ) ? esc_html( (string) $v ) : '';
        }
        return strtr( $text, $pairs );
    }

    /** Send one template to a user id, if enabled and address valid. */
    public static function send( $key, $user_id, $tokens ) {
        $tpl = self::get( $key );
        if ( ! $tpl || ! $tpl['enabled'] ) return false;
        $user = get_userdata( (int) $user_id );
        if ( ! $user || ! is_email( $user->user_email ) ) return false;

        $tokens = array_merge( array(
            'site_name'   => wp_specialchars_decode( get_bloginfo( 'name' ) ),
            'recipient'   => $user->user_login,
            'login_url'   => am_page_url( 'am-login' ),
            'profile_url' => am_page_url( 'am-profile' ),
            'messages_url'=> am_page_url( 'am-messages' ),
        ), $tokens );

        $subject = self::render( $tpl['subject'], $tokens );
        $body    = self::render( $tpl['body'], $tokens );
        $body    = nl2br( $body );
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        return wp_mail( $user->user_email, $subject, $body, $headers );
    }

    /* ---------- event handlers ---------- */

    public function welcome( $user_id ) {
        self::send( 'welcome', $user_id, array( 'user_login' => get_userdata( $user_id )->user_login ) );
    }

    public function new_message( $id, $from, $to, $body ) {
        if ( (int) $from === (int) $to ) return;
        $sender = get_userdata( $from );
        self::send( 'new_message', $to, array(
            'sender'  => $sender ? $sender->user_login : __( 'a member', 'ababil-members' ),
            'message' => mb_substr( sanitize_text_field( $body ), 0, 300 ),
        ) );
    }

    public function new_comment( $id, $post_id, $actor, $body, $parent ) {
        if ( $parent ) return; // replies handled separately
        global $wpdb;
        $owner = $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . AM_Social::table('posts') . ' WHERE id = %d', $post_id ) );
        if ( ! $owner || (int) $owner === (int) $actor ) return;
        $a = get_userdata( $actor );
        self::send( 'new_comment', $owner, array(
            'actor'   => $a ? $a->user_login : __( 'a member', 'ababil-members' ),
            'comment' => mb_substr( sanitize_text_field( $body ), 0, 300 ),
            'post_url'=> home_url( '/?am_post=' . (int) $post_id ),
        ) );
    }

    public function new_reply( $id, $post_id, $actor, $body, $parent ) {
        global $wpdb;
        $owner = $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . AM_Social::table('comments') . ' WHERE id = %d', $parent ) );
        if ( ! $owner || (int) $owner === (int) $actor ) return;
        $a = get_userdata( $actor );
        self::send( 'new_reply', $owner, array(
            'actor'   => $a ? $a->user_login : __( 'a member', 'ababil-members' ),
            'reply'   => mb_substr( sanitize_text_field( $body ), 0, 300 ),
            'post_url'=> home_url( '/?am_post=' . (int) $post_id ),
        ) );
    }

    public function new_review( $id, $target_id, $actor, $body, $rating ) {
        if ( (int) $target_id === (int) $actor ) return;
        $a = get_userdata( $actor );
        self::send( 'new_review', $target_id, array(
            'actor'   => $a ? $a->user_login : __( 'a member', 'ababil-members' ),
            'review'  => mb_substr( sanitize_text_field( $body ), 0, 300 ),
            'rating'  => (int) $rating,
            'profile_url' => AM_Profile::url( get_userdata( $target_id )->user_login ),
        ) );
    }
}
