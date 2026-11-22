<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Login-aware navigation visibility.
 *
 * Hides the plugin's own pages from navigation based on the visitor's state:
 *   - Login / Register pages              → shown to logged-out visitors only.
 *   - Profile / Messages / Log out pages  → shown to logged-in members only.
 *
 * Supports both classic menus (wp_nav_menu) and the block theme "Page List"
 * block (core/page-list), which is what Twenty Twenty-Five uses. The Page List
 * block is an inner block of Navigation and is rendered through WP_Block::render(),
 * so we filter get_pages() directly for frontend page listings.
 */
class AM_Menu {
    private static $instance = null;

    /** Lazily built map of plugin page permalink => rule. */
    private static $url_map = null;

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'wp_nav_menu_objects', array( $this, 'filter_classic_menu' ), 20, 2 );
        add_filter( 'get_pages', array( $this, 'filter_pages' ), 20, 2 );
    }

    /** Master switch (option + filter override). */
    private function enabled() {
        return (bool) apply_filters( 'am_menu_visibility_enabled', am_get_option( 'menu_visibility', 1 ) );
    }

    /** Shortcode/slug fingerprints for a page object or ID. */
    private function page_slugs( $page ) {
        $id = is_object( $page ) ? (int) $page->ID : (int) $page;
        $found = array();
        if ( ! $id ) return $found;

        // Plugin slug => page ID map (when pages were assigned in Settings).
        foreach ( am_pages_map() as $slug => $pid ) {
            if ( (int) $pid === $id ) $found[] = $slug;
        }

        // Detect by shortcode inside the page content.
        $content = get_post_field( 'post_content', $id );
        if ( $content ) {
            $map = array(
                'am_login'    => 'am-login',
                'am_register' => 'am-register',
                'am_profile'  => 'am-profile',
                'am_messages' => 'am-messages',
                'am_logout'   => 'am-logout',
                'am_members'  => 'am-members',
                'am_feed'     => 'am-feed',
                'am_groups'   => 'am-groups',
            );
            foreach ( $map as $sc => $slug ) {
                if ( has_shortcode( $content, $sc ) ) $found[] = $slug;
            }
        }

        // Detect by the auto-created page slug.
        $name = get_post_field( 'post_name', $id );
        if ( $name && preg_match( '/^am-(login|register|profile|messages|logout)$/', $name ) ) {
            $found[] = $name;
        }
        return array_values( array_unique( $found ) );
    }

    /** Return 'loggedout' | 'loggedin' | 'everyone' for a page. */
    private function page_rule( $page ) {
        $slugs = $this->page_slugs( $page );
        $rule  = 'everyone';
        if ( array_intersect( $slugs, array( 'am-login', 'am-register' ) ) ) {
            $rule = 'loggedout';
        } elseif ( array_intersect( $slugs, array( 'am-profile', 'am-messages', 'am-logout' ) ) ) {
            $rule = 'loggedin';
        }
        return apply_filters( 'am_menu_page_rule', $rule, $page, $slugs );
    }

    /** Whether a rule hides the item for the current visitor. */
    private function is_hidden( $rule ) {
        $logged = is_user_logged_in();
        if ( 'loggedin' === $rule && ! $logged ) return true;
        if ( 'loggedout' === $rule && $logged ) return true;
        return false;
    }

    /** Rule for a custom-link URL that points at one of the plugin pages. */
    private function url_rule( $url ) {
        if ( null === self::$url_map ) {
            self::$url_map = array();
            foreach ( array( 'am-login', 'am-register', 'am-profile', 'am-messages', 'am-logout' ) as $slug ) {
                $p = get_page_by_path( $slug );
                if ( ! $p ) continue;
                self::$url_map[ untrailingslashit( get_permalink( $p ) ) ] =
                    in_array( $slug, array( 'am-login', 'am-register' ), true ) ? 'loggedout' : 'loggedin';
            }
        }
        $key = untrailingslashit( (string) $url );
        return isset( self::$url_map[ $key ] ) ? self::$url_map[ $key ] : 'everyone';
    }

    /* ---------- Classic menus (wp_nav_menu) ---------- */

    public function filter_classic_menu( $items, $args = null ) {
        if ( is_admin() || ! $this->enabled() || ! is_array( $items ) ) return $items;

        $hidden = array();
        foreach ( $items as $item ) {
            if ( ! isset( $item->ID ) ) continue;

            $rule = 'everyone';
            if ( isset( $item->object ) && 'page' === $item->object && ! empty( $item->object_id ) ) {
                $rule = $this->page_rule( (int) $item->object_id );
            } elseif ( isset( $item->type ) && 'custom' === $item->type && ! empty( $item->url ) ) {
                $rule = $this->url_rule( $item->url );
            }

            if ( $this->is_hidden( $rule ) ) $hidden[ (int) $item->ID ] = true;
        }
        if ( ! $hidden ) return $items;

        // Cascade to descendants of a hidden item.
        $changed = true;
        while ( $changed ) {
            $changed = false;
            foreach ( $items as $item ) {
                $id     = (int) $item->ID;
                $parent = (int) ( $item->menu_item_parent ?? 0 );
                if ( $parent && isset( $hidden[ $parent ] ) && ! isset( $hidden[ $id ] ) ) {
                    $hidden[ $id ] = true;
                    $changed = true;
                }
            }
        }

        $out = array();
        foreach ( $items as $item ) {
            if ( ! isset( $hidden[ (int) $item->ID ] ) ) $out[] = $item;
        }
        return $out;
    }

    /* ---------- Block "Page List" (core/page-list) via get_pages ---------- */

    public function filter_pages( $pages, $args = array() ) {
        if ( ! $this->is_frontend_render() || ! $this->enabled() || ! is_array( $pages ) ) return $pages;
        if ( ! $this->is_page_listing( $args ) ) return $pages;

        $out = array();
        foreach ( $pages as $page ) {
            if ( $this->is_hidden( $this->page_rule( $page ) ) ) continue;
            $out[] = $page;
        }
        return $out;
    }

    /** Only touch "list all pages" calls — not targeted include/child_of lookups. */
    private function is_page_listing( $args ) {
        if ( ! is_array( $args ) ) return false;
        if ( ! empty( $args['include'] ) ) return false;
        if ( ! empty( $args['child_of'] ) ) return false;
        if ( isset( $args['parent'] ) && (int) $args['parent'] > 0 ) return false;
        return true;
    }

    /** Frontend only — never touch admin screens, REST responses or cron. */
    private function is_frontend_render() {
        if ( is_admin() ) return false;
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return false;
        if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) return false;
        if ( defined( 'WP_CLI' ) && WP_CLI ) return false;
        return true;
    }
}
