<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Messaging {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'template_redirect', array( $this, 'handle_send' ) );
        add_action( 'wp_ajax_am_chat_send', array( $this, 'ajax_send' ) );
        add_action( 'wp_ajax_am_chat_poll', array( $this, 'ajax_poll' ) );
        add_action( 'wp_ajax_am_chat_typing', array( $this, 'ajax_typing' ) );
        add_action( 'wp_ajax_am_chat_report', array( $this, 'ajax_report' ) );
    }

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'am_messages';
    }

    public static function reports_table() {
        global $wpdb;
        return $wpdb->prefix . 'am_reports';
    }

    public static function create_table() {
        global $wpdb;
        $t = self::table();
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$t} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            from_id BIGINT NOT NULL,
            to_id BIGINT NOT NULL,
            body TEXT NOT NULL,
            kind VARCHAR(20) NOT NULL DEFAULT 'text',
            attachment_url TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'delivered',
            created DATETIME NOT NULL,
            read_at DATETIME DEFAULT NULL,
            KEY from_id (from_id),
            KEY to_id (to_id)
        ) {$wpdb->get_charset_collate()}" );
        $r = self::reports_table();
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$r} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            reporter_id BIGINT NOT NULL,
            reported_id BIGINT NOT NULL,
            reason TEXT NOT NULL,
            created DATETIME NOT NULL
        ) {$wpdb->get_charset_collate()}" );
    }

    /** Safe migration for sites upgrading from the old 6-column table. */
    public static function upgrade_table() {
        global $wpdb;
        self::create_table();
        $t = self::table();
        $cols = $wpdb->get_col( "DESCRIBE {$t}" );
        if ( ! $cols ) return;
        $add = array(
            'kind'           => "ALTER TABLE {$t} ADD COLUMN kind VARCHAR(20) NOT NULL DEFAULT 'text' AFTER body",
            'attachment_url' => "ALTER TABLE {$t} ADD COLUMN attachment_url TEXT DEFAULT NULL AFTER kind",
            'status'         => "ALTER TABLE {$t} ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'delivered' AFTER attachment_url",
        );
        foreach ( $add as $name => $sql ) {
            if ( ! in_array( $name, $cols, true ) ) $wpdb->query( $sql );
        }
    }

    /* -------------------- non-AJAX fallback -------------------- */
    public function handle_send() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['am_message_send'] ) ) return;
        if ( ! am_get_option( 'messaging', 1 ) || ! is_user_logged_in() ) return;
        if ( ! isset( $_POST['am_nonce'] ) || ! wp_verify_nonce( $_POST['am_nonce'], 'am_message' ) ) return;

        $to = (int) ( $_POST['to_id'] ?? 0 );
        $body = trim( wp_unslash( $_POST['body'] ?? '' ) );
        $me = get_current_user_id();
        if ( ! $to || $to === $me || ! get_userdata( $to ) || $body === '' ) return;

        global $wpdb;
        $wpdb->insert( self::table(), array(
            'from_id' => $me, 'to_id' => $to,
            'body' => mb_substr( sanitize_textarea_field( $body ), 0, 2000 ),
            'kind' => 'text', 'status' => 'delivered', 'created' => current_time( 'mysql' ),
        ), array( '%d', '%d', '%s', '%s', '%s', '%s' ) );
        do_action( 'am_message_sent', (int) $wpdb->insert_id, $me, $to, $body );
        am_safe_redirect( am_page_url( 'am-messages' ) . '?to=' . rawurlencode( get_userdata( $to )->user_login ) );
    }

    /* -------------------- AJAX -------------------- */
    private function check_ajax() {
        if ( ! am_get_option( 'messaging', 1 ) || ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'login' ) );
        check_ajax_referer( 'am_chat', 'nonce' );
        return get_current_user_id();
    }

    public function ajax_send() {
        $me = $this->check_ajax();
        $to = (int) ( $_POST['other_id'] ?? 0 );
        $body = trim( wp_unslash( $_POST['body'] ?? '' ) );
        if ( ! $to || $to === $me || ! get_userdata( $to ) ) wp_send_json_error( array( 'message' => 'invalid' ) );

        $kind = 'text';
        $attachment = null;

        if ( ! empty( $_FILES['image']['name'] ) ) {
            if ( (int) $_FILES['image']['size'] > 1024 * 1024 ) wp_send_json_error( array( 'message' => __( 'Image must be 1 MB or smaller.', 'ababil-members' ) ) );
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $check = wp_check_filetype_and_ext( $_FILES['image']['tmp_name'], $_FILES['image']['name'] );
            if ( empty( $check['type'] ) || strpos( $check['type'], 'image/' ) !== 0 ) wp_send_json_error( array( 'message' => __( 'Only images are allowed.', 'ababil-members' ) ) );

            $dir_filter = function ( $dirs ) {
                $sub = 'ababil-members/chat';
                $dirs['subdir'] = '/' . $sub;
                $dirs['path'] = $dirs['basedir'] . '/' . $sub;
                $dirs['url'] = $dirs['baseurl'] . '/' . $sub;
                if ( ! is_dir( $dirs['path'] ) ) wp_mkdir_p( $dirs['path'] );
                return $dirs;
            };
            add_filter( 'upload_dir', $dir_filter );
            $upload = wp_handle_upload( $_FILES['image'], array( 'test_form' => false, 'action' => 'am_chat_image' ) );
            remove_filter( 'upload_dir', $dir_filter );
            if ( empty( $upload['url'] ) || ! empty( $upload['error'] ) ) wp_send_json_error( array( 'message' => __( 'Upload failed.', 'ababil-members' ) ) );
            $kind = 'image';
            $attachment = $upload['url'];
            $body = $body ?: '📷';
        }

        if ( 'text' === $kind && $body === '' ) wp_send_json_error( array( 'message' => __( 'Empty message.', 'ababil-members' ) ) );

        global $wpdb;
        $wpdb->insert( self::table(), array(
            'from_id' => $me, 'to_id' => $to,
            'body' => mb_substr( sanitize_textarea_field( $body ), 0, 2000 ),
            'kind' => $kind, 'attachment_url' => $attachment,
            'status' => 'delivered', 'created' => current_time( 'mysql' ),
        ), array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' ) );

        $id = (int) $wpdb->insert_id;
        do_action( 'am_message_sent', $id, $me, $to, $body );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A );
        delete_transient( 'am_typing_' . $me . '_' . $to );
        wp_send_json_success( array( 'row' => self::render_row( $row, $me ), 'id' => $id ) );
    }

    public function ajax_poll() {
        $me = $this->check_ajax();
        $to = (int) ( $_POST['other_id'] ?? 0 );
        $last = (int) ( $_POST['last_id'] ?? 0 );
        if ( ! $to || ! get_userdata( $to ) ) wp_send_json_error();

        global $wpdb;
        $t = self::table();
        // Recipient is viewing → mark incoming messages as seen.
        $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'seen', read_at = %s WHERE from_id = %d AND to_id = %d AND status != 'seen'", current_time( 'mysql' ), $to, $me ) );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t} WHERE ((from_id = %d AND to_id = %d) OR (from_id = %d AND to_id = %d)) AND id > %d ORDER BY id ASC LIMIT 50",
            $me, $to, $to, $me, $last
        ), ARRAY_A );

        // Status updates for messages the sender already has on screen.
        $updates = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, status FROM {$t} WHERE from_id = %d AND to_id = %d AND status != 'seen' ORDER BY id ASC LIMIT 50",
            $me, $to
        ), ARRAY_A );

        $html = '';
        foreach ( $rows as $row ) { $html .= self::render_row( $row, $me ); }
        $typing = (int) get_transient( 'am_typing_' . $to . '_' . $me ) > 0;

        wp_send_json_success( array(
            'html' => $html,
            'last_id' => $rows ? (int) end( $rows )['id'] : $last,
            'statuses' => $updates,
            'typing' => $typing,
        ) );
    }

    public function ajax_typing() {
        $me = $this->check_ajax();
        $to = (int) ( $_POST['other_id'] ?? 0 );
        if ( ! $to || $to === $me ) wp_send_json_error();
        set_transient( 'am_typing_' . $me . '_' . $to, time(), 8 );
        wp_send_json_success();
    }

    public function ajax_report() {
        $me = $this->check_ajax();
        $to = (int) ( $_POST['other_id'] ?? 0 );
        $reason = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ), 0, 300 );
        if ( ! $to || $to === $me || ! get_userdata( $to ) ) wp_send_json_error();
        global $wpdb;
        $wpdb->insert( self::reports_table(), array(
            'reporter_id' => $me, 'reported_id' => $to,
            'reason' => $reason ?: 'No reason given', 'created' => current_time( 'mysql' ),
        ), array( '%d', '%d', '%s', '%s' ) );
        wp_send_json_success( array( 'message' => __( 'Conversation reported. Thank you.', 'ababil-members' ) ) );
    }

    /* -------------------- rendering helpers -------------------- */
    public static function render_row( $row, $me ) {
        $mine = (int) $row['from_id'] === (int) $me;
        $is_image = isset( $row['kind'] ) && 'image' === $row['kind'];
        $body = $is_image
            ? '<img class="ig-chat-image" src="' . esc_url( $row['attachment_url'] ) . '" alt="image">'
            : nl2br( esc_html( $row['body'] ) );
        $status = '';
        if ( $mine ) {
            $s = isset( $row['status'] ) ? $row['status'] : 'delivered';
            $label = 'sent' === $s ? 'Sent ✓' : ( 'seen' === $s ? 'Seen ✓✓' : 'Delivered ✓✓' );
            $status = '<span class="ig-msg-status ig-status-' . esc_attr( $s ) . '">' . esc_html( $label ) . '</span>';
        } else {
            $status = '<span class="ig-msg-time">' . esc_html( mb_substr( $row['created'], 11, 5 ) ) . '</span>';
        }
        return '<div class="ig-bubble-row ' . ( $mine ? 'is-mine' : '' ) . '" data-id="' . (int) $row['id'] . '">'
            . '<div class="ig-bubble ' . ( $mine ? 'is-mine' : 'is-theirs' ) . '">' . $body . $status . '</div>'
            . '</div>';
    }

    public static function threads( $user_id ) {
        global $wpdb;
        $t = self::table();
        $all = $wpdb->get_results( $wpdb->prepare(
            "SELECT m.*, u.user_login FROM {$t} m JOIN {$wpdb->users} u ON u.ID = CASE WHEN m.from_id = %d THEN m.to_id ELSE m.from_id END
             WHERE m.from_id = %d OR m.to_id = %d ORDER BY m.id DESC LIMIT 100",
            $user_id, $user_id, $user_id
        ) );
        // One thread per person — keep only the latest message for each peer.
        $seen = array();
        $out  = array();
        foreach ( $all as $row ) {
            $peer = ( (int) $row->from_id === (int) $user_id ) ? (int) $row->to_id : (int) $row->from_id;
            if ( isset( $seen[ $peer ] ) ) continue;
            $seen[ $peer ] = true;
            $out[] = $row;
            if ( count( $out ) >= 30 ) break;
        }
        return $out;
    }

    public static function conversation( $me, $other ) {
        global $wpdb;
        $t = self::table();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t} WHERE (from_id = %d AND to_id = %d) OR (from_id = %d AND to_id = %d) ORDER BY id ASC LIMIT 50",
            $me, $other, $other, $me
        ), ARRAY_A );
        $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'seen', read_at = %s WHERE from_id = %d AND to_id = %d AND status != 'seen'", current_time( 'mysql' ), $other, $me ) );
        return $rows;
    }

    public static function unread( $user_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE to_id = %d AND status != %s', $user_id, 'seen' ) );
    }
}
