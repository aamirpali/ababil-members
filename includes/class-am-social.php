<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Social {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_am_post_create', array( $this, 'ajax_post' ) );
        add_action( 'wp_ajax_am_post_like', array( $this, 'ajax_like' ) );
        add_action( 'wp_ajax_am_comment_add', array( $this, 'ajax_comment' ) );
        add_action( 'wp_ajax_am_comment_like', array( $this, 'ajax_comment_like' ) );
        add_action( 'wp_ajax_am_group_join', array( $this, 'ajax_group' ) );
        add_action( 'wp_ajax_am_group_create', array( $this, 'ajax_group_create' ) );
        add_action( 'wp_ajax_am_group_post', array( $this, 'ajax_group_post' ) );
        add_action( 'wp_ajax_am_review_add', array( $this, 'ajax_review' ) );
        add_action( 'wp_ajax_am_story_add', array( $this, 'ajax_story_add' ) );
        add_action( 'wp_ajax_am_story_delete', array( $this, 'ajax_story_delete' ) );
        add_action( 'wp_ajax_am_story_view', array( $this, 'ajax_story_view' ) );
        add_action( 'wp_ajax_am_story_viewers', array( $this, 'ajax_story_viewers' ) );
    }

    /** Supported post feelings: key => array( emoji, label ). */
    public static function feelings() {
        return array(
            'happy'       => array( '😊', 'happy' ),
            'love'        => array( '😍', 'love' ),
            'excited'     => array( '🤩', 'excited' ),
            'grateful'    => array( '🙏', 'grateful' ),
            'proud'       => array( '😎', 'proud' ),
            'sad'         => array( '😢', 'sad' ),
            'angry'       => array( '😡', 'angry' ),
            'tired'       => array( '😴', 'tired' ),
            'inspired'    => array( '💡', 'inspired' ),
            'celebrating' => array( '🎉', 'celebrating' ),
        );
    }

    /** Active stories (last 24 hours), newest first. */
    public static function stories( $limit = 60 ) {
        global $wpdb;
        $t = self::table('stories');
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t} WHERE created > DATE_SUB( NOW(), INTERVAL 24 HOUR ) ORDER BY created DESC LIMIT " . (int) $limit
        ), ARRAY_A );
    }

    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'am_' . $name;
    }

    public static function create_tables() {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        $wpdb->query( "CREATE TABLE IF NOT EXISTS " . self::table('posts') . " (
            id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id BIGINT NOT NULL, body TEXT NOT NULL,
            image_url TEXT DEFAULT NULL, group_id BIGINT NOT NULL DEFAULT 0, feeling VARCHAR(40) DEFAULT NULL,
            created DATETIME NOT NULL, KEY user_id (user_id)
        ) {$c}" );
        $wpdb->query( "CREATE TABLE IF NOT EXISTS " . self::table('stories') . " (
            id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id BIGINT NOT NULL, image_url TEXT NOT NULL,
            caption VARCHAR(200) DEFAULT NULL, created DATETIME NOT NULL,
            KEY user_id (user_id), KEY created (created)
        ) {$c}" );
        $wpdb->query( "CREATE TABLE IF NOT EXISTS " . self::table('story_views') . " (
            story_id BIGINT NOT NULL, user_id BIGINT NOT NULL, created DATETIME NOT NULL,
            PRIMARY KEY (story_id, user_id), KEY story_id (story_id)
        ) {$c}" );
        $wpdb->query( "CREATE TABLE IF NOT EXISTS " . self::table('likes') . " (
            post_id BIGINT NOT NULL, user_id BIGINT NOT NULL, created DATETIME NOT NULL,
            PRIMARY KEY (post_id, user_id)
        ) {$c}" );
        $wpdb->query( "CREATE TABLE IF NOT EXISTS " . self::table('comments') . " (
            id BIGINT AUTO_INCREMENT PRIMARY KEY, post_id BIGINT NOT NULL, user_id BIGINT NOT NULL,
            body TEXT NOT NULL, parent_id BIGINT NOT NULL DEFAULT 0, created DATETIME NOT NULL, KEY post_id (post_id)
        ) {$c}" );
        $wpdb->query( "CREATE TABLE IF NOT EXISTS " . self::table('groups') . " (
            id BIGINT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, description TEXT NOT NULL,
            creator_id BIGINT NOT NULL, privacy VARCHAR(10) NOT NULL DEFAULT 'public', created DATETIME NOT NULL
        ) {$c}" );
        $wpdb->query( "CREATE TABLE IF NOT EXISTS " . self::table('group_members') . " (
            group_id BIGINT NOT NULL, user_id BIGINT NOT NULL, created DATETIME NOT NULL,
            PRIMARY KEY (group_id, user_id)
        ) {$c}" );
        $wpdb->query( "CREATE TABLE IF NOT EXISTS " . self::table('reviews') . " (
            id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id BIGINT NOT NULL, target_id BIGINT NOT NULL,
            rating TINYINT NOT NULL, body TEXT NOT NULL, created DATETIME NOT NULL,
            KEY target_id (target_id)
        ) {$c}" );
    }

    /** Add columns for sites upgrading from earlier Phase 2 builds. */
    public static function upgrade_tables() {
        global $wpdb;
        $g = self::table('groups');
        if ( ! $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$g} LIKE %s", 'privacy' ) ) ) {
            $wpdb->query( "ALTER TABLE {$g} ADD COLUMN privacy VARCHAR(10) NOT NULL DEFAULT 'public' AFTER creator_id" );
        }
        $p = self::table('posts');
        if ( ! $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$p} LIKE %s", 'group_id' ) ) ) {
            $wpdb->query( "ALTER TABLE {$p} ADD COLUMN group_id BIGINT NOT NULL DEFAULT 0 AFTER image_url" );
        }
        if ( ! $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$p} LIKE %s", 'feeling' ) ) ) {
            $wpdb->query( "ALTER TABLE {$p} ADD COLUMN feeling VARCHAR(40) DEFAULT NULL AFTER group_id" );
        }
        $cm = self::table('comments');
        if ( ! $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$cm} LIKE %s", 'parent_id' ) ) ) {
            $wpdb->query( "ALTER TABLE {$cm} ADD COLUMN parent_id BIGINT NOT NULL DEFAULT 0 AFTER body" );
        }
        $wpdb->query( "CREATE TABLE IF NOT EXISTS " . self::table('comment_likes') . " (
            comment_id BIGINT NOT NULL, user_id BIGINT NOT NULL, created DATETIME NOT NULL,
            PRIMARY KEY (comment_id, user_id)
        ) {$wpdb->get_charset_collate()}" );
    }

    public static function is_member( $group_id, $user_id ) {
        if ( ! $user_id ) return false;
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::table('group_members') . ' WHERE group_id = %d AND user_id = %d', $group_id, $user_id ) );
    }

    public static function group_posts( $group_id, $limit = 30 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table('posts') . ' WHERE group_id = %d ORDER BY id DESC LIMIT ' . (int) $limit, $group_id ), ARRAY_A );
    }

    public static function group_member_ids( $group_id ) {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . self::table('group_members') . ' WHERE group_id = %d ORDER BY created DESC LIMIT 30', $group_id ) );
    }

    public static function profile_link( $user_id ) {
        $u = get_userdata( (int) $user_id );
        $name = $u ? $u->user_login : __( 'member', 'ababil-members' );
        return '<a class="ig-user-link" href="' . esc_url( AM_Profile::url( $name ) ) . '">' . esc_html( $name ) . '</a>';
    }

    public static function avatar_html( $user_id, $class ) {
        $url = get_user_meta( (int) $user_id, 'am_avatar_url', true );
        if ( $url ) return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $url ) . '" alt="">';
        return '<span class="' . esc_attr( $class ) . '">' . esc_html( get_user_meta( (int) $user_id, 'am_avatar', true ) ?: '🙂' ) . '</span>';
    }

    public static function linkify( $text ) {
        $safe = esc_html( $text );
        return preg_replace_callback( '/@([A-Za-z0-9_.-]+)/', function ( $m ) {
            return '<a class="ig-mention" href="' . esc_url( AM_Profile::url( $m[1] ) ) . '">@' . esc_html( $m[1] ) . '</a>';
        }, $safe );
    }

    public static function comment_likes( $comment_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table('comment_likes') . ' WHERE comment_id = %d', $comment_id ) );
    }

    public static function comment_liked( $comment_id, $user_id ) {
        if ( ! $user_id ) return false;
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::table('comment_likes') . ' WHERE comment_id = %d AND user_id = %d', $comment_id, $user_id ) );
    }

    private function check() {
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => __( 'Please log in.', 'ababil-members' ) ) );
        check_ajax_referer( 'am_social', 'nonce' );
        return get_current_user_id();
    }

    public static function feed( $limit = 30, $user_id = 0 ) {
        global $wpdb;
        $t = self::table('posts');
        if ( $user_id ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d ORDER BY id DESC LIMIT " . (int) $limit, $user_id ), ARRAY_A );
        }
        return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC LIMIT " . (int) $limit, ARRAY_A );
    }

    public static function like_count( $post_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table('likes') . ' WHERE post_id = %d', $post_id ) );
    }

    public static function liked( $post_id, $user_id ) {
        if ( ! $user_id ) return false;
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::table('likes') . ' WHERE post_id = %d AND user_id = %d', $post_id, $user_id ) );
    }

    public static function comments( $post_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table('comments') . ' WHERE post_id = %d ORDER BY id ASC LIMIT 30', $post_id ), ARRAY_A );
    }

    public static function render_comment( $c, $me, $replies = array() ) {
        $cid = (int) $c['id'];
        $u = get_userdata( (int) $c['user_id'] );
        $name = $u ? $u->user_login : __( 'member', 'ababil-members' );
        $liked = self::comment_liked( $cid, $me );
        $html = '<div class="ig-comment" data-comment="' . $cid . '">';
        $html .= self::avatar_html( $c['user_id'], 'ig-comment-avatar' );
        $html .= '<div class="ig-comment-main"><div class="ig-comment-bubble"><strong>' . self::profile_link( $c['user_id'] ) . '</strong> <span class="ig-comment-text">' . self::linkify( $c['body'] ) . '</span></div>';
        $html .= '<div class="ig-comment-actions">'
            . '<button type="button" class="ig-comment-like ' . ( $liked ? 'is-liked' : '' ) . '" data-comment="' . $cid . '">♥ <span class="ig-comment-like-count">' . self::comment_likes( $cid ) . '</span></button>'
            . '<button type="button" class="ig-reply-btn" data-comment="' . $cid . '" data-user="' . esc_attr( $name ) . '">' . esc_html__( 'Reply', 'ababil-members' ) . '</button>'
            . '<small>' . esc_html( human_time_diff( strtotime( $c['created'] ), current_time( 'timestamp' ) ) ) . '</small></div>';
        if ( $replies ) {
            $html .= '<div class="ig-comment-replies">';
            foreach ( $replies as $r ) $html .= self::render_comment( $r, $me );
            $html .= '</div>';
        }
        if ( $me ) {
            $html .= '<form class="ig-comment-form ig-reply-form" hidden><input type="hidden" name="parent_id" value="' . $cid . '"><input type="text" name="body" class="ig-comment-input" placeholder="Reply to ' . esc_attr( $name ) . '..." maxlength="300" required><button type="submit" class="ig-send-btn">➤</button></form>';
        }
        $html .= '</div></div>';
        return $html;
    }

    public static function story_views( $story_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table('story_views') . ' WHERE story_id = %d', (int) $story_id ) );
    }

    /** One story-ring card (reused by the initial page and the add-story response). */
    public static function story_card( $user_id, $image, $index, $own = false ) {
        $u      = get_userdata( (int) $user_id );
        $name   = $u ? $u->user_login : __( 'member', 'ababil-members' );
        $avatar = get_user_meta( (int) $user_id, 'am_avatar_url', true );
        $emoji  = get_user_meta( (int) $user_id, 'am_avatar', true ) ?: '🙂';
        $av     = $avatar ? '<img class="ig-story-avatar-img" src="' . esc_url( $avatar ) . '" alt="">' : esc_html( $emoji );
        $style  = $image ? ' style="background-image:url(' . esc_url( $image ) . ')"' : '';
        $grad   = $image ? '' : ' ig-story-grad-' . ( (int) $index % 5 );
        return '<button type="button" class="ig-story' . $grad . ( $own ? ' ig-story-own' : '' ) . '" data-story-index="' . (int) $index . '"' . $style . '>'
            . '<span class="ig-story-avatar">' . $av . '</span>'
            . '<span class="ig-story-name">' . esc_html( $name ) . '</span>'
            . '</button>';
    }

    public static function render_post( $p, $me ) {
        $liked = self::liked( (int) $p['id'], $me );
        $likes = self::like_count( (int) $p['id'] );
        $all = self::comments( (int) $p['id'] );
        $top = array();
        $replies = array();
        foreach ( $all as $c ) {
            if ( (int) ( $c['parent_id'] ?? 0 ) ) $replies[ (int) $c['parent_id'] ][] = $c;
            else $top[] = $c;
        }
        $feeling = '';
        $fmap    = self::feelings();
        $fkey    = isset( $p['feeling'] ) ? sanitize_key( $p['feeling'] ) : '';
        if ( $fkey && isset( $fmap[ $fkey ] ) ) {
            $feeling = ' <span class="ig-post-feeling">' . $fmap[ $fkey ][0] . ' ' . esc_html__( 'feeling', 'ababil-members' ) . ' ' . esc_html( $fmap[ $fkey ][1] ) . '</span>';
        }
        $html = '<article class="ig-post-card" data-post="' . (int) $p['id'] . '">';
        $html .= '<div class="ig-post-head">' . self::avatar_html( $p['user_id'], 'ig-post-avatar' ) . '<div class="ig-post-headinfo"><div class="ig-post-headline">' . self::profile_link( $p['user_id'] ) . ( $feeling ? ' ' . esc_html__( 'is', 'ababil-members' ) . $feeling : '' ) . '</div><small>' . esc_html( human_time_diff( strtotime( $p['created'] ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'ababil-members' ) . ' · 🌐</small></div></div>';
        if ( ! empty( $p['body'] ) ) $html .= '<div class="ig-post-body">' . nl2br( self::linkify( $p['body'] ) ) . '</div>';
        if ( ! empty( $p['image_url'] ) ) $html .= '<img class="ig-post-image" src="' . esc_url( $p['image_url'] ) . '" alt="">';
        $html .= '<div class="ig-post-stats"><span>❤️ <span class="ig-like-count">' . $likes . '</span></span><span>💬 ' . count( $all ) . '</span></div>';
        $html .= '<div class="ig-post-actions">'
            . '<button type="button" class="ig-like-btn ' . ( $liked ? 'is-liked' : '' ) . '" data-post="' . (int) $p['id'] . '">👍 ' . esc_html__( 'Like', 'ababil-members' ) . '</button>'
            . '<button type="button" class="ig-comment-toggle">' . esc_html__( 'Comment', 'ababil-members' ) . '</button>'
            . '<button type="button" class="ig-share-btn" data-post="' . (int) $p['id'] . '">↗ ' . esc_html__( 'Share', 'ababil-members' ) . '</button>'
            . '</div>';
        $html .= '<div class="ig-comment-list">';
        foreach ( $top as $c ) $html .= self::render_comment( $c, $me, isset( $replies[ (int) $c['id'] ] ) ? $replies[ (int) $c['id'] ] : array() );
        $html .= '</div>';
        if ( $me ) {
            $html .= '<form class="ig-comment-form"><input type="text" name="body" class="ig-comment-input" placeholder="Write a comment..." required maxlength="300"><input type="hidden" name="post_id" value="' . (int) $p['id'] . '"><button type="submit" class="ig-send-btn">➤</button></form>';
        }
        $html .= '</article>';
        return $html;
    }

    /* ---------------- AJAX ---------------- */
    public function ajax_post() {
        $me = $this->check();
        $body = trim( wp_unslash( $_POST['body'] ?? '' ) );
        $image = null;
        if ( ! empty( $_FILES['image']['name'] ) ) {
            if ( (int) $_FILES['image']['size'] > 1024 * 1024 ) wp_send_json_error( array( 'message' => __( 'Image must be 1 MB or smaller.', 'ababil-members' ) ) );
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $chk = wp_check_filetype_and_ext( $_FILES['image']['tmp_name'], $_FILES['image']['name'] );
            if ( empty( $chk['type'] ) || strpos( $chk['type'], 'image/' ) !== 0 ) wp_send_json_error( array( 'message' => __( 'Only images allowed.', 'ababil-members' ) ) );
            $filter = function ( $d ) { $s = 'ababil-members/posts'; $d['subdir'] = '/' . $s; $d['path'] = $d['basedir'] . '/' . $s; $d['url'] = $d['baseurl'] . '/' . $s; if ( ! is_dir( $d['path'] ) ) wp_mkdir_p( $d['path'] ); return $d; };
            add_filter( 'upload_dir', $filter );
            $up = wp_handle_upload( $_FILES['image'], array( 'test_form' => false, 'action' => 'am_post_image' ) );
            remove_filter( 'upload_dir', $filter );
            if ( empty( $up['url'] ) ) wp_send_json_error( array( 'message' => __( 'Upload failed.', 'ababil-members' ) ) );
            $image = $up['url'];
        }
        $feeling = sanitize_key( $_POST['feeling'] ?? '' );
        if ( ! isset( self::feelings()[ $feeling ] ) ) $feeling = '';
        if ( $body === '' && ! $image && ! $feeling ) wp_send_json_error( array( 'message' => __( 'Write something first.', 'ababil-members' ) ) );
        global $wpdb;
        $wpdb->insert( self::table('posts'), array( 'user_id' => $me, 'body' => mb_substr( sanitize_textarea_field( $body ), 0, 2000 ), 'image_url' => $image, 'group_id' => 0, 'feeling' => $feeling ? $feeling : null, 'created' => current_time( 'mysql' ) ), array( '%d','%s','%s','%d','%s','%s' ) );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table('posts') . ' WHERE id = %d', $wpdb->insert_id ), ARRAY_A );
        wp_send_json_success( array( 'html' => self::render_post( $row, $me ) ) );
    }

    public function ajax_story_add() {
        $me = $this->check();
        if ( empty( $_FILES['image']['name'] ) ) wp_send_json_error( array( 'message' => __( 'Choose a photo first.', 'ababil-members' ) ) );
        if ( (int) $_FILES['image']['size'] > 2 * 1024 * 1024 ) wp_send_json_error( array( 'message' => __( 'Story photo must be 2 MB or smaller.', 'ababil-members' ) ) );
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $chk = wp_check_filetype_and_ext( $_FILES['image']['tmp_name'], $_FILES['image']['name'] );
        if ( empty( $chk['type'] ) || strpos( $chk['type'], 'image/' ) !== 0 ) wp_send_json_error( array( 'message' => __( 'Only images allowed.', 'ababil-members' ) ) );
        $filter = function ( $d ) { $s = 'ababil-members/stories'; $d['subdir'] = '/' . $s; $d['path'] = $d['basedir'] . '/' . $s; $d['url'] = $d['baseurl'] . '/' . $s; if ( ! is_dir( $d['path'] ) ) wp_mkdir_p( $d['path'] ); return $d; };
        add_filter( 'upload_dir', $filter );
        $up = wp_handle_upload( $_FILES['image'], array( 'test_form' => false, 'action' => 'am_story_image' ) );
        remove_filter( 'upload_dir', $filter );
        if ( empty( $up['url'] ) ) wp_send_json_error( array( 'message' => __( 'Upload failed.', 'ababil-members' ) ) );
        $caption = sanitize_text_field( mb_substr( wp_unslash( $_POST['caption'] ?? '' ), 0, 200 ) );
        global $wpdb;
        $wpdb->insert( self::table('stories'), array( 'user_id' => $me, 'image_url' => $up['url'], 'caption' => $caption, 'created' => current_time( 'mysql' ) ), array( '%d','%s','%s','%s' ) );
        wp_send_json_success( array( 'html' => self::story_card( $me, $up['url'], 0, true ), 'image' => $up['url'] ) );
    }

    public function ajax_story_delete() {
        $me = $this->check();
        $id = (int) ( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error();
        global $wpdb;
        $wpdb->delete( self::table('stories'), array( 'id' => $id, 'user_id' => $me ), array( '%d','%d' ) );
        wp_send_json_success();
    }

    /** Record that a member viewed a story (once per member per story). */
    public function ajax_story_view() {
        $me = $this->check();
        $id = (int) ( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error();
        global $wpdb;
        if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table('stories') . ' WHERE id = %d', $id ) ) ) wp_send_json_error();
        $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::table('story_views') . ' (story_id, user_id, created) VALUES (%d,%d,%s)', $id, $me, current_time( 'mysql' ) ) );
        wp_send_json_success();
    }

    /** Who viewed one of my stories, plus the view count. */
    public function ajax_story_viewers() {
        $me = $this->check();
        $id = (int) ( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error();
        global $wpdb;
        if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table('stories') . ' WHERE id = %d AND user_id = %d', $id, $me ) ) ) wp_send_json_error();
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT v.user_id, v.created FROM ' . self::table('story_views') . ' v WHERE v.story_id = %d ORDER BY v.created DESC LIMIT 100',
            $id
        ) );
        $html = '';
        foreach ( $rows as $r ) {
            $u    = get_userdata( (int) $r->user_id );
            $name = $u ? $u->user_login : __( 'member', 'ababil-members' );
            $av   = get_user_meta( (int) $r->user_id, 'am_avatar_url', true );
            $em   = get_user_meta( (int) $r->user_id, 'am_avatar', true ) ?: '🙂';
            $url  = $u ? AM_Profile::url( $u->user_login ) : '#';
            $html .= '<a class="ig-story-viewer-row" href="' . esc_url( $url ) . '"><span class="ig-story-viewer-avatar">';
            $html .= $av ? '<img src="' . esc_url( $av ) . '" alt="">' : esc_html( $em );
            $html .= '</span><span>' . esc_html( $name ) . '</span><small>' . esc_html( human_time_diff( strtotime( $r->created ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'ababil-members' ) . '</small></a>';
        }
        wp_send_json_success( array( 'count' => count( $rows ), 'html' => $rows ? $html : '<div class="ig-notif-empty">' . esc_html__( 'No views yet.', 'ababil-members' ) . '</div>' ) );
    }

    public function ajax_like() {
        $me = $this->check();
        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) wp_send_json_error();
        global $wpdb;
        $owner = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . self::table('posts') . ' WHERE id = %d', $post_id ) );
        $was_liked = self::liked( $post_id, $me );
        if ( $was_liked ) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table('likes') . ' WHERE post_id = %d AND user_id = %d', $post_id, $me ) );
        else $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table('likes') . ' (post_id, user_id, created) VALUES (%d,%d,%s)', $post_id, $me, current_time( 'mysql' ) ) );
        do_action( $was_liked ? 'am_post_unliked' : 'am_post_liked', $post_id, $owner, $me );
        wp_send_json_success( array( 'liked' => self::liked( $post_id, $me ), 'count' => self::like_count( $post_id ) ) );
    }

    public function ajax_comment() {
        $me = $this->check();
        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        $body = trim( sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ) );
        $parent = (int) ( $_POST['parent_id'] ?? 0 );
        global $wpdb;
        if ( ! $post_id || $body === '' || ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table('posts') . ' WHERE id = %d', $post_id ) ) ) wp_send_json_error();
        if ( $parent && ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table('comments') . ' WHERE id = %d', $parent ) ) ) wp_send_json_error();
        $wpdb->insert( self::table('comments'), array( 'post_id' => $post_id, 'user_id' => $me, 'body' => mb_substr( $body, 0, 300 ), 'parent_id' => $parent, 'created' => current_time( 'mysql' ) ), array( '%d','%d','%s','%d','%s' ) );
        $cid = (int) $wpdb->insert_id;
        do_action( 'am_comment_added', $cid, $post_id, $me, $body, $parent );
        if ( $parent ) do_action( 'am_reply_added', $cid, $post_id, $me, $body, $parent );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table('comments') . ' WHERE id = %d', $cid ), ARRAY_A );
        wp_send_json_success( array( 'html' => self::render_comment( $row, $me ), 'parent' => $parent ) );
    }

    public function ajax_comment_like() {
        $me = $this->check();
        $cid = (int) ( $_POST['comment_id'] ?? 0 );
        if ( ! $cid ) wp_send_json_error();
        global $wpdb;
        $crow = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id, post_id FROM ' . self::table('comments') . ' WHERE id = %d', $cid ), ARRAY_A );
        $owner = $crow ? (int) $crow['user_id'] : 0;
        $post_id = $crow ? (int) $crow['post_id'] : 0;
        $was_liked = self::comment_liked( $cid, $me );
        if ( $was_liked ) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table('comment_likes') . ' WHERE comment_id = %d AND user_id = %d', $cid, $me ) );
        else $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table('comment_likes') . ' (comment_id, user_id, created) VALUES (%d,%d,%s)', $cid, $me, current_time( 'mysql' ) ) );
        do_action( $was_liked ? 'am_comment_unliked' : 'am_comment_liked', $cid, $owner, $me, $post_id );
        wp_send_json_success( array( 'liked' => self::comment_liked( $cid, $me ), 'count' => self::comment_likes( $cid ) ) );
    }

    public function ajax_group_create() {
        $me = $this->check();
        $name = mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 0, 120 );
        $description = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 0, 500 );
        $privacy = ( isset( $_POST['privacy'] ) && 'private' === $_POST['privacy'] ) ? 'private' : 'public';
        if ( '' === $name ) wp_send_json_error( array( 'message' => __( 'Enter a group name.', 'ababil-members' ) ) );
        global $wpdb;
        $wpdb->insert( self::table('groups'), array( 'name' => $name, 'description' => $description, 'creator_id' => $me, 'privacy' => $privacy, 'created' => current_time( 'mysql' ) ), array( '%s','%s','%d','%s','%s' ) );
        $id = (int) $wpdb->insert_id;
        $wpdb->insert( self::table('group_members'), array( 'group_id' => $id, 'user_id' => $me, 'created' => current_time( 'mysql' ) ), array( '%d','%d','%s' ) );
        wp_send_json_success( array( 'id' => $id, 'name' => $name ) );
    }

    public function ajax_group_post() {
        $me = $this->check();
        $group_id = (int) ( $_POST['group_id'] ?? 0 );
        $body = trim( sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ) );
        if ( ! $group_id || $body === '' || ! self::is_member( $group_id, $me ) ) wp_send_json_error( array( 'message' => __( 'Join the group to post.', 'ababil-members' ) ) );
        global $wpdb;
        $wpdb->insert( self::table('posts'), array( 'user_id' => $me, 'body' => mb_substr( $body, 0, 2000 ), 'image_url' => null, 'group_id' => $group_id, 'created' => current_time( 'mysql' ) ), array( '%d','%s','%s','%d','%s' ) );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table('posts') . ' WHERE id = %d', $wpdb->insert_id ), ARRAY_A );
        do_action( 'am_post_created', $row );
        wp_send_json_success( array( 'html' => self::render_post( $row, $me ) ) );
    }

    public function ajax_group() {
        $me = $this->check();
        $group_id = (int) ( $_POST['group_id'] ?? 0 );
        if ( ! $group_id ) wp_send_json_error();
        global $wpdb;
        $t = self::table('group_members');
        $joined = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . $t . ' WHERE group_id = %d AND user_id = %d', $group_id, $me ) );
        if ( $joined ) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $t . ' WHERE group_id = %d AND user_id = %d', $group_id, $me ) );
        else $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . $t . ' (group_id, user_id, created) VALUES (%d,%d,%s)', $group_id, $me, current_time( 'mysql' ) ) );
        if ( ! $joined ) do_action( 'am_group_joined', $group_id, $me );
        $count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $t . ' WHERE group_id = %d', $group_id ) );
        wp_send_json_success( array( 'joined' => ! $joined, 'count' => $count ) );
    }

    public function ajax_review() {
        $me = $this->check();
        $target = (int) ( $_POST['target_id'] ?? 0 );
        $rating = max( 1, min( 5, (int) ( $_POST['rating'] ?? 5 ) ) );
        $body = trim( sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ) );
        if ( ! $target || $target === $me || ! get_userdata( $target ) ) wp_send_json_error();
        global $wpdb;
        $wpdb->insert( self::table('reviews'), array( 'user_id' => $me, 'target_id' => $target, 'rating' => $rating, 'body' => mb_substr( $body, 0, 300 ), 'created' => current_time( 'mysql' ) ), array( '%d','%d','%d','%s','%s' ) );
        do_action( 'am_review_added', (int) $wpdb->insert_id, $target, $me, $body, $rating );
        $avg = $wpdb->get_var( $wpdb->prepare( 'SELECT AVG(rating) FROM ' . self::table('reviews') . ' WHERE target_id = %d', $target ) );
        wp_send_json_success( array( 'average' => round( (float) $avg, 1 ) ) );
    }

    public static function get_group( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table('groups') . ' WHERE id = %d', $id ), ARRAY_A );
    }

    public static function groups() {
        global $wpdb;
        return $wpdb->get_results( 'SELECT * FROM ' . self::table('groups') . ' ORDER BY id DESC LIMIT 30', ARRAY_A );
    }

    public static function group_count( $id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table('group_members') . ' WHERE group_id = %d', $id ) );
    }

    /** Groups a member has joined (for the profile sidebar). */
    public static function user_groups( $user_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT g.* FROM ' . self::table('group_members') . ' gm JOIN ' . self::table('groups') . ' g ON g.id = gm.group_id WHERE gm.user_id = %d ORDER BY g.id DESC LIMIT 10',
            (int) $user_id
        ), ARRAY_A );
    }

    public static function reviews( $target ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table('reviews') . ' WHERE target_id = %d ORDER BY id DESC LIMIT 20', $target ), ARRAY_A );
    }
}
