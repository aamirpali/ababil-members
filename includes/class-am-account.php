<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Phase 4 — front-end account & privacy self-service.
 * Powered by the Account tab on the member profile.
 */
class AM_Account {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'template_redirect', array( $this, 'handle' ), 6 );
    }

    private function notice( $msg ) {
        $GLOBALS['am_account_notice'] = $msg;
    }

    private function done( $status ) {
        am_safe_redirect( add_query_arg( 'am_account', $status, am_page_url( 'am-profile' ) ) );
    }

    private function log( $event, $uid ) {
        if ( class_exists( 'AM_Security' ) ) AM_Security::log( $event, 'Account self-service', (int) $uid );
    }

    /** Handle account POST actions and the self-export download. */
    public function handle() {
        // Stream a previously generated personal-data export (GET from the download link).
        if ( isset( $_GET['am_self_export'] ) && isset( $_GET['_wpnonce'] ) &&
             wp_verify_nonce( $_GET['_wpnonce'], 'am_self_export' ) && is_user_logged_in() ) {
            $uid  = get_current_user_id();
            $data = get_transient( 'am_export_self_' . $uid );
            if ( is_array( $data ) ) {
                delete_transient( 'am_export_self_' . $uid );
                nocache_headers();
                header( 'Content-Type: application/json; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="ababil-my-data-' . (int) $uid . '.json"' );
                echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                exit;
            }
        }

        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['am_action'] ) || ! is_user_logged_in() ) return;
        $action = sanitize_key( $_POST['am_action'] );
        if ( 0 !== strpos( $action, 'am_acct_' ) ) return;
        if ( ! isset( $_POST['am_nonce'] ) || ! wp_verify_nonce( $_POST['am_nonce'], 'am_account' ) ) return;

        $uid  = get_current_user_id();
        $user = wp_get_current_user();

        if ( 'am_acct_password' === $action ) {
            $current = (string) ( $_POST['current_password'] ?? '' );
            $new     = (string) ( $_POST['new_password'] ?? '' );
            $confirm = (string) ( $_POST['confirm_password'] ?? '' );
            if ( ! wp_check_password( $current, $user->user_pass, $uid ) ) {
                $this->notice( __( 'Your current password is incorrect.', 'ababil-members' ) );
                return;
            }
            if ( $new !== $confirm ) {
                $this->notice( __( 'The new passwords do not match.', 'ababil-members' ) );
                return;
            }
            $err = AM_Forms::validate_password( $new );
            if ( $err ) { $this->notice( $err ); return; }
            wp_update_user( array( 'ID' => $uid, 'user_pass' => $new ) );
            update_user_meta( $uid, 'am_pw_changed', current_time( 'timestamp' ) );
            delete_user_meta( $uid, 'am_pw_expired' );
            $this->log( 'password_changed', $uid );
            $this->done( 'password' );
        }

        if ( 'am_acct_2fa' === $action ) {
            $current = (string) ( $_POST['current_password'] ?? '' );
            if ( ! wp_check_password( $current, $user->user_pass, $uid ) ) {
                $this->notice( __( 'Your current password is incorrect.', 'ababil-members' ) );
                return;
            }
            $on = empty( $_POST['am_2fa_enable'] ) ? 0 : 1;
            update_user_meta( $uid, 'am_2fa_enabled', $on );
            $this->log( $on ? '2fa_enabled' : '2fa_disabled', $uid );
            $this->done( '2fa' );
        }

        if ( 'am_acct_export' === $action ) {
            $data = AM_Privacy::export_user( $uid );
            set_transient( 'am_export_self_' . $uid, $data, 5 * MINUTE_IN_SECONDS );
            $this->log( 'data_exported', $uid );
            $this->done( 'export' );
        }

        if ( 'am_acct_erase' === $action || 'am_acct_delete' === $action ) {
            $confirm = strtoupper( sanitize_key( $_POST['confirm_text'] ?? '' ) );
            $current = (string) ( $_POST['current_password'] ?? '' );
            if ( 'ERASE' !== $confirm || ! wp_check_password( $current, $user->user_pass, $uid ) ) {
                $this->notice( __( 'Confirmation text or password is incorrect.', 'ababil-members' ) );
                return;
            }
            if ( in_array( 'administrator', (array) $user->roles, true ) ) {
                $this->notice( __( 'Administrator accounts cannot be erased here.', 'ababil-members' ) );
                return;
            }
            AM_Privacy::erase_user( $uid );
            if ( 'am_acct_delete' === $action ) {
                $this->log( 'account_deleted', $uid );
                wp_logout();
                require_once ABSPATH . 'wp-admin/includes/user.php';
                wp_delete_user( $uid );
                am_safe_redirect( am_page_url( 'am-login' ) );
            }
            $this->log( 'data_erased', $uid );
            $this->done( 'erased' );
        }
    }
}
