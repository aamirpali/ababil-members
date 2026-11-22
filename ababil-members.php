<?php
/**
 * Plugin Name: Ababil Members
 * Plugin URI: https://ameerbaksh.in/
 * Description: A clean membership & community plugin — registration, login, profiles, member directory, badges, followers, messaging, activity and content restriction.
 * Version: 1.9.7
 * Author: Ameer Baksh
 * Author URI: https://ameerbaksh.in/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ababil-members
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/aamirpali/ababil-members
 *
 * @package AbabilMembers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AM_VERSION', '1.9.7' );
define( 'AM_PLUGIN_FILE', __FILE__ );
define( 'AM_PATH', plugin_dir_path( __FILE__ ) );
define( 'AM_URL', plugin_dir_url( __FILE__ ) );
define( 'AM_BASENAME', plugin_basename( __FILE__ ) );

require_once AM_PATH . 'includes/am-functions.php';
require_once AM_PATH . 'includes/class-am-setup.php';
require_once AM_PATH . 'includes/class-am-forms.php';
require_once AM_PATH . 'includes/class-am-profile.php';
require_once AM_PATH . 'includes/class-am-members.php';
require_once AM_PATH . 'includes/class-am-badges.php';
require_once AM_PATH . 'includes/class-am-access.php';
require_once AM_PATH . 'includes/class-am-followers.php';
require_once AM_PATH . 'includes/class-am-messaging.php';
require_once AM_PATH . 'includes/class-am-activity.php';
require_once AM_PATH . 'includes/class-am-fields.php';
require_once AM_PATH . 'includes/class-am-social.php';
require_once AM_PATH . 'includes/class-am-emails.php';
require_once AM_PATH . 'includes/class-am-privacy.php';
require_once AM_PATH . 'includes/class-am-social-login.php';
require_once AM_PATH . 'includes/class-am-shortcodes.php';
require_once AM_PATH . 'includes/class-am-menu.php';
// Phase 4
require_once AM_PATH . 'includes/class-am-security.php';
require_once AM_PATH . 'includes/class-am-account.php';
require_once AM_PATH . 'includes/class-am-widgets.php';
// Phase 5
require_once AM_PATH . 'includes/class-am-notifications.php';

// GitHub auto-update via YahnisElsts/Plugin-Update-Checker v6
require_once AM_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
YahnisElsts\PluginUpdateChecker\v6\PucFactory::buildUpdateChecker(
    'https://github.com/aamirpali/ababil-members/',
    __FILE__,
    'ababil-members'
)->getVcsApi()->enableReleaseAssets();

if ( is_admin() ) {
    require_once AM_PATH . 'admin/class-am-admin.php';
}

final class Ababil_Members {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook( AM_PLUGIN_FILE, array( 'AM_Setup', 'activate' ) );
        register_deactivation_hook( AM_PLUGIN_FILE, array( 'AM_Setup', 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );

        AM_Forms::instance();
        AM_Profile::instance();
        AM_Members::instance();
        AM_Badges::instance();
        AM_Access::instance();
        AM_Followers::instance();
        AM_Messaging::instance();
        AM_Activity::instance();
        AM_Shortcodes::instance();
        AM_Menu::instance();
        AM_Social::instance();
        AM_Emails::instance();
        AM_Privacy::instance();
        AM_Social_Login::instance();
        AM_Security::instance();
        AM_Account::instance();
        AM_Widgets::instance();
        AM_Notifications::instance();
        add_action( 'init', array( 'AM_Setup', 'maybe_upgrade' ), 1 );

        if ( is_admin() ) {
            AM_Admin::instance();
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'ababil-members', false, dirname( AM_BASENAME ) . '/languages' );
    }

    public function init() {
        do_action( 'am_init' );
    }

    public function assets() {
        wp_enqueue_style( 'am-style', AM_URL . 'assets/css/am-style.css', array(), AM_VERSION );
        wp_enqueue_style( 'ig-style', AM_URL . 'assets/css/ig-style.css', array(), AM_VERSION );
        wp_enqueue_script( 'ig-scripts', AM_URL . 'assets/js/ig-scripts.js', array(), AM_VERSION, true );

        // Phase 3 — style customizer, strictly scoped to the plugin container.
        $s = am_get_option( 'am_styles', array() );
        $s = is_array( $s ) ? $s : array();
        $primary = $this->valid_color( $s['primary'] ?? '', '#0095f6' );
        $accent  = $this->valid_color( $s['accent'] ?? '', '#ff2e63' );
        $bg      = $this->valid_color( $s['bg'] ?? '', '#ffffff' );
        $radius  = max( 0, min( 40, (int) ( $s['radius'] ?? 8 ) ) );
        $font    = max( 80, min( 150, (int) ( $s['font_scale'] ?? 100 ) ) );

        $css  = '.ig-plugin-container{';
        $css .= '--ig-blue:' . $primary . ';';
        $css .= '--ig-blue-dark:' . $this->darken( $primary ) . ';';
        $css .= '--ig-accent:' . $accent . ';';
        $css .= '--ig-surface:' . $bg . ';';
        $css .= '--ig-radius:' . $radius . 'px;';
        if ( 100 !== $font ) $css .= 'font-size:calc(14px * ' . ( $font / 100 ) . ');';
        $css .= '}';
        // Accent is used by the verified badge / highlights wrapper.
        $css .= '.ig-plugin-container .ig-btn-primary{--ig-blue:' . $primary . ';}';
        wp_add_inline_style( 'ig-style', $css );
    }

    private function valid_color( $color, $default ) {
        $color = sanitize_text_field( $color );
        return preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ? $color : $default;
    }

    private function darken( $hex ) {
        $hex = ltrim( $hex, '#' );
        $r = max( 0, hexdec( substr( $hex, 0, 2 ) ) - 30 );
        $g = max( 0, hexdec( substr( $hex, 2, 2 ) ) - 30 );
        $b = max( 0, hexdec( substr( $hex, 4, 2 ) ) - 30 );
        return '#' . str_pad( dechex( $r ), 2, '0', STR_PAD_LEFT )
                   . str_pad( dechex( $g ), 2, '0', STR_PAD_LEFT )
                   . str_pad( dechex( $b ), 2, '0', STR_PAD_LEFT );
    }
}

function am() {
    return Ababil_Members::instance();
}

am();
