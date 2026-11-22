<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Admin {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'save' ) );
        add_action( 'admin_notices', array( $this, 'notices' ) );
        add_filter( 'plugin_action_links_' . AM_BASENAME, array( $this, 'links' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
    }

    public function assets( $hook ) {
        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        if ( 0 !== strpos( $page, 'ababil-members' ) ) return;
        wp_enqueue_style( 'am-admin-style', AM_URL . 'assets/css/am-admin.css', array(), AM_VERSION );
        if ( 'ababil-members-builder' === $page ) {
            wp_enqueue_script( 'am-admin-builder', AM_URL . 'assets/js/am-admin.js', array(), AM_VERSION, true );
        }
    }

    public function links( $links ) {
        $links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=ababil-members' ) ) . '">' . esc_html__( 'Settings', 'ababil-members' ) . '</a>';
        return $links;
    }

    public function menu() {
        add_menu_page( __( 'Ababil Members', 'ababil-members' ), __( 'Ababil Members', 'ababil-members' ), 'manage_options', 'ababil-members', array( $this, 'page' ), 'dashicons-groups', 30 );
        add_submenu_page( 'ababil-members', __( 'Settings', 'ababil-members' ), __( 'Settings', 'ababil-members' ), 'manage_options', 'ababil-members', array( $this, 'page' ) );
        add_submenu_page( 'ababil-members', __( 'Fields', 'ababil-members' ), __( 'Fields', 'ababil-members' ), 'manage_options', 'ababil-members-fields', array( $this, 'fields_page' ) );
        add_submenu_page( 'ababil-members', __( 'Forms', 'ababil-members' ), __( 'Forms', 'ababil-members' ), 'manage_options', 'ababil-members-forms', array( $this, 'forms_page' ) );
        add_submenu_page( 'ababil-members', __( 'Members', 'ababil-members' ), __( 'Members', 'ababil-members' ), 'manage_options', 'ababil-members-members', array( $this, 'members_page' ) );
        // Phase 3
        add_submenu_page( 'ababil-members', __( 'Emails', 'ababil-members' ), __( 'Emails', 'ababil-members' ), 'manage_options', 'ababil-members-emails', array( $this, 'emails_page' ) );
        add_submenu_page( 'ababil-members', __( 'Profile Tabs', 'ababil-members' ), __( 'Profile Tabs', 'ababil-members' ), 'manage_options', 'ababil-members-tabs', array( $this, 'tabs_page' ) );
        add_submenu_page( 'ababil-members', __( 'Password & Access', 'ababil-members' ), __( 'Password & Access', 'ababil-members' ), 'manage_options', 'ababil-members-access', array( $this, 'access_page' ) );
        add_submenu_page( 'ababil-members', __( 'Privacy (GDPR)', 'ababil-members' ), __( 'Privacy (GDPR)', 'ababil-members' ), 'manage_options', 'ababil-members-privacy', array( $this, 'privacy_page' ) );
        add_submenu_page( 'ababil-members', __( 'Social Login', 'ababil-members' ), __( 'Social Login', 'ababil-members' ), 'manage_options', 'ababil-members-social', array( $this, 'social_page' ) );
        add_submenu_page( 'ababil-members', __( 'Styles', 'ababil-members' ), __( 'Styles', 'ababil-members' ), 'manage_options', 'ababil-members-styles', array( $this, 'styles_page' ) );
        // Phase 4
        add_submenu_page( 'ababil-members', __( 'Pages', 'ababil-members' ), __( 'Pages', 'ababil-members' ), 'manage_options', 'ababil-members-pages', array( $this, 'pages_page' ) );
        add_submenu_page( 'ababil-members', __( 'Registration', 'ababil-members' ), __( 'Registration', 'ababil-members' ), 'manage_options', 'ababil-members-registration', array( $this, 'registration_page' ) );
        add_submenu_page( 'ababil-members', __( 'Security', 'ababil-members' ), __( 'Security', 'ababil-members' ), 'manage_options', 'ababil-members-security', array( $this, 'security_page' ) );
        add_submenu_page( 'ababil-members', __( 'Audit Log', 'ababil-members' ), __( 'Audit Log', 'ababil-members' ), 'manage_options', 'ababil-members-audit', array( $this, 'audit_page' ) );
        add_submenu_page( 'ababil-members', __( 'Builder', 'ababil-members' ), __( 'Builder', 'ababil-members' ), 'manage_options', 'ababil-members-builder', array( $this, 'builder_page' ) );
        // Phase 5
        add_submenu_page( 'ababil-members', __( 'Notifications', 'ababil-members' ), __( 'Notifications', 'ababil-members' ), 'manage_options', 'ababil-members-notifications', array( $this, 'notifications_page' ) );
    }

    /** Notifications admin: totals + recent activity feed. */
    public function notifications_page() {
        if ( ! $this->can() ) return;
        global $wpdb;
        $t   = AM_Notifications::table();
        $tot = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
        $unread = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE is_read = 0" );
        $rows = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC LIMIT 50" );
        ?>
        <div class="wrap am-admin-wrap">
          <h1><?php esc_html_e( 'Notifications', 'ababil-members' ); ?></h1>
          <p class="am-intro"><?php esc_html_e( 'Notifications are generated automatically when members follow, comment, reply, join groups or post. The bell in the site header shows each member their own unread count.', 'ababil-members' ); ?></p>
          <table class="widefat striped" style="max-width:720px;margin-bottom:18px">
            <tbody>
              <tr><td><strong><?php esc_html_e( 'Total notifications', 'ababil-members' ); ?></strong></td><td><?php echo (int) $tot; ?></td></tr>
              <tr><td><strong><?php esc_html_e( 'Unread', 'ababil-members' ); ?></strong></td><td><?php echo (int) $unread; ?></td></tr>
            </tbody>
          </table>
          <h2><?php esc_html_e( 'Recent activity', 'ababil-members' ); ?></h2>
          <table class="widefat striped">
            <thead><tr><th><?php esc_html_e( 'To', 'ababil-members' ); ?></th><th><?php esc_html_e( 'Type', 'ababil-members' ); ?></th><th><?php esc_html_e( 'Text', 'ababil-members' ); ?></th><th><?php esc_html_e( 'When', 'ababil-members' ); ?></th></tr></thead>
            <tbody>
            <?php if ( $rows ) : foreach ( $rows as $r ) :
                $to = get_userdata( (int) $r->user_id );
                ?>
                <tr>
                  <td><?php echo $to ? esc_html( $to->user_login ) : '&mdash;'; ?></td>
                  <td><?php echo esc_html( $r->type ); ?></td>
                  <td><?php echo esc_html( $r->text ); ?></td>
                  <td><?php echo esc_html( $r->created ); ?></td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="4"><?php esc_html_e( 'No notifications recorded yet.', 'ababil-members' ); ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
    }

    private function can() { return current_user_can( 'manage_options' ); }

    /** Render our settings notices, including the GDPR export download link. */
    public function notices() {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 0 !== strpos( $page, 'ababil-members' ) ) return;
        settings_errors( 'am_settings' );
    }

    private function update_opts( $changes ) {
        $opts = get_option( 'am_options', array() );
        if ( ! is_array( $opts ) ) $opts = array();
        update_option( 'am_options', array_merge( $opts, $changes ) );
    }

    public function save() {
        if ( ! $this->can() ) return;

        // Stream a previously generated GDPR export (GET from the download link).
        if ( isset( $_GET['am_download'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'am_download' ) ) {
            $key  = 'am_export_' . get_current_user_id();
            $data = get_transient( $key );
            if ( is_array( $data ) ) {
                delete_transient( $key );
                nocache_headers();
                header( 'Content-Type: application/json; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="ababil-export-' . (int) $_GET['am_download'] . '.json"' );
                echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                exit;
            }
        }

        // Settings
        if ( isset( $_POST['am_settings_nonce'] ) && wp_verify_nonce( $_POST['am_settings_nonce'], 'am_settings' ) ) {
            $this->update_opts( array(
                'registration'     => empty( $_POST['registration'] ) ? 0 : 1,
                'badges'           => empty( $_POST['badges'] ) ? 0 : 1,
                'member_directory' => empty( $_POST['member_directory'] ) ? 0 : 1,
                'restriction'      => empty( $_POST['restriction'] ) ? 0 : 1,
                'followers'        => empty( $_POST['followers'] ) ? 0 : 1,
                'messaging'        => empty( $_POST['messaging'] ) ? 0 : 1,
                'activity'         => empty( $_POST['activity'] ) ? 0 : 1,
                'menu_visibility'  => empty( $_POST['menu_visibility'] ) ? 0 : 1,
                'register_status'  => ( isset( $_POST['register_status'] ) && in_array( $_POST['register_status'], array( 'approved','pending' ), true ) ) ? $_POST['register_status'] : 'approved',
            ) );
            add_settings_error( 'am_settings', 'am_saved', __( 'Settings saved.', 'ababil-members' ), 'updated' );
        }

        // Field add / update
        if ( isset( $_POST['am_field_nonce'] ) && wp_verify_nonce( $_POST['am_field_nonce'], 'am_field' ) ) {
            $opts = get_option( 'am_options', array() );
            $fields = isset( $opts['fields'] ) && is_array( $opts['fields'] ) ? $opts['fields'] : array();
            $slug = sanitize_key( $_POST['slug'] ?? '' );
            $label = sanitize_text_field( $_POST['label'] ?? '' );
            $type = sanitize_key( $_POST['type'] ?? 'text' );
            if ( $slug && $label && in_array( $type, array_keys( AM_Fields::types() ), true ) ) {
                $field = array(
                    'slug' => $slug, 'label' => $label, 'type' => $type,
                    'options' => sanitize_textarea_field( $_POST['options'] ?? '' ),
                    'required' => empty( $_POST['required'] ) ? 0 : 1,
                    'visibility' => in_array( $_POST['visibility'] ?? '', array( 'public','members','admin' ), true ) ? $_POST['visibility'] : 'public',
                    'depends_on' => sanitize_key( $_POST['depends_on'] ?? '' ),
                    'depends_value' => sanitize_text_field( $_POST['depends_value'] ?? '' ),
                );
                $found = false;
                foreach ( $fields as $i => $f ) { if ( $f['slug'] === $slug ) { $fields[ $i ] = $field; $found = true; } }
                if ( ! $found ) $fields[] = $field;
                $opts['fields'] = $fields;
                update_option( 'am_options', $opts );
                add_settings_error( 'am_settings', 'am_field_saved', __( 'Field saved.', 'ababil-members' ), 'updated' );
            }
        }

        // Field delete
        if ( isset( $_GET['am_delete_field'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'am_delete_field' ) ) {
            $opts = get_option( 'am_options', array() );
            $fields = isset( $opts['fields'] ) && is_array( $opts['fields'] ) ? $opts['fields'] : array();
            $slug = sanitize_key( $_GET['am_delete_field'] );
            $opts['fields'] = array_values( array_filter( $fields, function ( $f ) use ( $slug ) { return $f['slug'] !== $slug; } ) );
            update_option( 'am_options', $opts );
            add_settings_error( 'am_settings', 'am_field_deleted', __( 'Field deleted.', 'ababil-members' ), 'updated' );
        }

        // Form builder
        if ( isset( $_POST['am_form_nonce'] ) && wp_verify_nonce( $_POST['am_form_nonce'], 'am_form' ) ) {
            $selected = isset( $_POST['form_fields'] ) && is_array( $_POST['form_fields'] ) ? array_map( 'sanitize_key', $_POST['form_fields'] ) : array();
            $this->update_opts( array( 'form_fields' => $selected, 'submit_label' => sanitize_text_field( $_POST['submit_label'] ?? __( 'Create account', 'ababil-members' ) ) ) );
            add_settings_error( 'am_settings', 'am_form_saved', __( 'Registration form saved.', 'ababil-members' ), 'updated' );
        }

        // Member actions
        if ( isset( $_POST['am_member_nonce'] ) && wp_verify_nonce( $_POST['am_member_nonce'], 'am_member' ) ) {
            $uid = (int) ( $_POST['uid'] ?? 0 );
            $action = sanitize_key( $_POST['member_action'] ?? '' );
            if ( $uid && $uid !== get_current_user_id() && get_userdata( $uid ) ) {
                if ( 'approve' === $action ) update_user_meta( $uid, 'am_account_status', 'approved' );
                if ( 'reject' === $action ) update_user_meta( $uid, 'am_account_status', 'rejected' );
                if ( 'ban' === $action ) update_user_meta( $uid, 'am_banned', 1 );
                if ( 'unban' === $action ) update_user_meta( $uid, 'am_banned', 0 );
                if ( 'role' === $action ) {
                    $role = sanitize_key( $_POST['new_role'] ?? 'subscriber' );
                    if ( in_array( $role, array( 'subscriber','contributor','author','editor' ), true ) ) {
                        $u = new WP_User( $uid ); $u->set_role( $role );
                        update_user_meta( $uid, 'am_role', $role );
                    }
                }
                add_settings_error( 'am_settings', 'am_member_saved', __( 'Member updated.', 'ababil-members' ), 'updated' );
            }
        }

        /* ================= Phase 3 ================= */

        // Email templates
        if ( isset( $_POST['am_emails_nonce'] ) && wp_verify_nonce( $_POST['am_emails_nonce'], 'am_emails' ) ) {
            $saved = am_get_option( 'am_emails', array() );
            $saved = is_array( $saved ) ? $saved : array();
            foreach ( AM_Emails::templates() as $key => $def ) {
                $saved[ $key ] = array(
                    'enabled' => empty( $_POST[ 'em_' . $key . '_enabled' ] ) ? 0 : 1,
                    'subject' => sanitize_text_field( wp_unslash( $_POST[ 'em_' . $key . '_subject' ] ?? '' ) ),
                    'body'    => sanitize_textarea_field( wp_unslash( $_POST[ 'em_' . $key . '_body' ] ?? '' ) ),
                );
            }
            am_update_option( 'am_emails', $saved );
            add_settings_error( 'am_settings', 'am_emails_saved', __( 'Email templates saved.', 'ababil-members' ), 'updated' );
        }

        // Profile tab add / update
        if ( isset( $_POST['am_tab_nonce'] ) && wp_verify_nonce( $_POST['am_tab_nonce'], 'am_tab' ) ) {
            $tabs  = am_profile_tabs();
            $id    = sanitize_key( $_POST['tab_id'] ?? '' );
            $label = sanitize_text_field( $_POST['tab_label'] ?? '' );
            if ( $id && $label ) {
                $row = array(
                    'id'         => $id,
                    'label'      => $label,
                    'icon'       => sanitize_text_field( $_POST['tab_icon'] ?? '' ),
                    'content'    => wp_kses_post( wp_unslash( $_POST['tab_content'] ?? '' ) ),
                    'visibility' => in_array( $_POST['tab_visibility'] ?? '', array( 'all','members','admin' ), true ) ? $_POST['tab_visibility'] : 'all',
                    'min_role'   => sanitize_key( $_POST['tab_min_role'] ?? '' ),
                );
                $found = false;
                foreach ( $tabs as $i => $t ) { if ( $t['id'] === $id ) { $tabs[ $i ] = $row; $found = true; } }
                if ( ! $found ) $tabs[] = $row;
                am_update_option( 'am_tabs', $tabs );
                add_settings_error( 'am_settings', 'am_tab_saved', __( 'Profile tab saved.', 'ababil-members' ), 'updated' );
            }
        }

        // Profile tab delete
        if ( isset( $_GET['am_delete_tab'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'am_delete_tab' ) ) {
            $id   = sanitize_key( $_GET['am_delete_tab'] );
            $tabs = am_profile_tabs();
            $tabs = array_values( array_filter( $tabs, function ( $t ) use ( $id ) { return $t['id'] !== $id; } ) );
            am_update_option( 'am_tabs', $tabs );
            add_settings_error( 'am_settings', 'am_tab_deleted', __( 'Profile tab deleted.', 'ababil-members' ), 'updated' );
        }

        // Password policy + access rules
        if ( isset( $_POST['am_access_nonce'] ) && wp_verify_nonce( $_POST['am_access_nonce'], 'am_access' ) ) {
            am_update_option( 'am_password', array(
                'min_length' => max( 6, min( 64, absint( $_POST['pw_min'] ?? 8 ) ) ),
                'complexity' => empty( $_POST['pw_complexity'] ) ? 0 : 1,
                'expiry_days'=> max( 0, min( 3650, absint( $_POST['pw_expiry'] ?? 0 ) ) ),
            ) );
            am_update_option( 'am_access_rules', array(
                'min_role'   => sanitize_key( $_POST['min_role'] ?? '' ),
                'min_points' => max( 0, absint( $_POST['min_points'] ?? 0 ) ),
                'require_status' => sanitize_key( $_POST['require_status'] ?? '' ),
            ) );
            add_settings_error( 'am_settings', 'am_access_saved', __( 'Password & access rules saved.', 'ababil-members' ), 'updated' );
        }

        // Social login keys
        if ( isset( $_POST['am_social_nonce'] ) && wp_verify_nonce( $_POST['am_social_nonce'], 'am_social' ) ) {
            am_update_option( 'am_social_login', array(
                'enabled'         => empty( $_POST['social_enabled'] ) ? 0 : 1,
                'google_id'       => sanitize_text_field( $_POST['google_id'] ?? '' ),
                'google_secret'   => sanitize_text_field( $_POST['google_secret'] ?? '' ),
                'facebook_id'     => sanitize_text_field( $_POST['facebook_id'] ?? '' ),
                'facebook_secret' => sanitize_text_field( $_POST['facebook_secret'] ?? '' ),
            ) );
            add_settings_error( 'am_settings', 'am_social_saved', __( 'Social login settings saved.', 'ababil-members' ), 'updated' );
        }

        // Style customizer
        if ( isset( $_POST['am_styles_nonce'] ) && wp_verify_nonce( $_POST['am_styles_nonce'], 'am_styles' ) ) {
            am_update_option( 'am_styles', array(
                'primary'  => $this->sanitize_color( $_POST['st_primary'] ?? '', '#0095f6' ),
                'accent'   => $this->sanitize_color( $_POST['st_accent'] ?? '', '#ff2e63' ),
                'bg'       => $this->sanitize_color( $_POST['st_bg'] ?? '', '#ffffff' ),
                'radius'   => max( 0, min( 40, absint( $_POST['st_radius'] ?? 8 ) ) ),
                'font_scale'=> max( 80, min( 150, absint( $_POST['st_font'] ?? 100 ) ) ),
            ) );
            add_settings_error( 'am_settings', 'am_styles_saved', __( 'Styles saved.', 'ababil-members' ), 'updated' );
        }

        // GDPR export / erase
        if ( isset( $_POST['am_privacy_nonce'] ) && wp_verify_nonce( $_POST['am_privacy_nonce'], 'am_privacy' ) ) {
            $uid = (int) ( $_POST['privacy_user'] ?? 0 );
            $action = sanitize_key( $_POST['privacy_action'] ?? '' );
            if ( $uid && get_userdata( $uid ) ) {
                if ( 'export' === $action ) {
                    $data = AM_Privacy::export_user( $uid );
                    $this->export_download( $data, $uid );
                } elseif ( 'erase' === $action ) {
                    AM_Privacy::erase_user( $uid );
                    add_settings_error( 'am_settings', 'am_erased', __( 'Member data erased.', 'ababil-members' ), 'updated' );
                }
            }
        }

        /* ================= Phase 4 ================= */

        // Assigned plugin pages
        if ( isset( $_POST['am_pages_nonce'] ) && wp_verify_nonce( $_POST['am_pages_nonce'], 'am_pages' ) ) {
            $map = array();
            foreach ( array( 'am-login','am-register','am-profile','am-members','am-messages','am-feed','am-groups','am-logout' ) as $slug ) {
                $id = absint( $_POST[ 'page_' . str_replace( '-', '_', $slug ) ] ?? 0 );
                if ( $id && get_post( $id ) ) $map[ $slug ] = $id;
            }
            am_update_option( 'am_pages', $map );
            add_settings_error( 'am_settings', 'am_pages_saved', __( 'Page assignments saved.', 'ababil-members' ), 'updated' );
        }

        // Registration settings + consent
        if ( isset( $_POST['am_registration_nonce'] ) && wp_verify_nonce( $_POST['am_registration_nonce'], 'am_registration' ) ) {
            $field = sanitize_key( $_POST['reg_display_name'] ?? 'username' );
            if ( ! in_array( $field, array( 'username','email' ), true ) && 0 !== strpos( $field, 'field:' ) ) $field = 'username';
            am_update_option( 'am_registration', array(
                'role'         => in_array( $_POST['reg_role'] ?? '', array( 'subscriber','contributor','author' ), true ) ? $_POST['reg_role'] : 'subscriber',
                'display_name' => $field,
                'gravatar'     => empty( $_POST['reg_gravatar'] ) ? 0 : 1,
                'redirect'     => in_array( $_POST['reg_redirect'] ?? '', array( 'profile','feed','members','home' ), true ) ? $_POST['reg_redirect'] : 'profile',
            ) );
            am_update_option( 'am_permalink_base', sanitize_key( $_POST['reg_permalink_base'] ?? 'user' ) ?: 'user' );
            am_update_option( 'am_consent', array(
                'enabled' => empty( $_POST['consent_enabled'] ) ? 0 : 1,
                'text'    => wp_kses_post( wp_unslash( $_POST['consent_text'] ?? __( 'I agree to the terms and privacy policy.', 'ababil-members' ) ) ),
            ) );
            flush_rewrite_rules();
            add_settings_error( 'am_settings', 'am_registration_saved', __( 'Registration settings saved.', 'ababil-members' ), 'updated' );
        }

        // Security settings
        if ( isset( $_POST['am_security_nonce'] ) && wp_verify_nonce( $_POST['am_security_nonce'], 'am_security' ) ) {
            am_update_option( 'am_security', array(
                'rate_limit'      => empty( $_POST['sec_rate_limit'] ) ? 0 : 1,
                'captcha'         => empty( $_POST['sec_captcha'] ) ? 0 : 1,
                'two_factor'      => empty( $_POST['sec_two_factor'] ) ? 0 : 1,
                'audit_log'       => empty( $_POST['sec_audit_log'] ) ? 0 : 1,
                'max_attempts'    => max( 2, min( 20, absint( $_POST['sec_max_attempts'] ?? 5 ) ) ),
                'lockout_minutes' => max( 1, min( 1440, absint( $_POST['sec_lockout'] ?? 15 ) ) ),
            ) );
            add_settings_error( 'am_settings', 'am_security_saved', __( 'Security settings saved.', 'ababil-members' ), 'updated' );
        }

        // Audit log clear
        if ( isset( $_POST['am_audit_nonce'] ) && wp_verify_nonce( $_POST['am_audit_nonce'], 'am_audit' ) && ! empty( $_POST['am_clear_audit'] ) ) {
            AM_Security::clear_log();
            add_settings_error( 'am_settings', 'am_audit_cleared', __( 'Audit log cleared.', 'ababil-members' ), 'updated' );
        }

        // Field ordering (builder)
        if ( isset( $_POST['am_builder_nonce'] ) && wp_verify_nonce( $_POST['am_builder_nonce'], 'am_builder' ) ) {
            $order = isset( $_POST['am_field_order'] ) && is_array( $_POST['am_field_order'] ) ? array_map( 'sanitize_key', $_POST['am_field_order'] ) : array();
            am_update_option( 'am_fields_order', array_values( $order ) );
            add_settings_error( 'am_settings', 'am_builder_saved', __( 'Field order saved.', 'ababil-members' ), 'updated' );
        }
    }

    private function sanitize_color( $color, $default ) {
        $color = sanitize_text_field( $color );
        return preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ? $color : $default;
    }

    private function export_download( $data, $uid ) {
        // Admin-init already sent headers? Use a transient-backed download link instead.
        set_transient( 'am_export_' . get_current_user_id(), $data, 5 * MINUTE_IN_SECONDS );
        $url = wp_nonce_url( admin_url( 'admin.php?page=ababil-members-privacy&am_download=' . (int) $uid ), 'am_download' );
        add_settings_error( 'am_settings', 'am_export_ready', sprintf( __( 'Export ready. <a href="%s">Download JSON</a>', 'ababil-members' ), $url ), 'updated' );
    }

    public function page() {
        $opts = get_option( 'am_options', array() );
        $toggles = array(
            'registration'     => __( 'Allow registration', 'ababil-members' ),
            'badges'           => __( 'Enable auto badges', 'ababil-members' ),
            'member_directory' => __( 'Enable member directory', 'ababil-members' ),
            'restriction'      => __( 'Enable member-only restriction', 'ababil-members' ),
            'followers'        => __( 'Enable Followers', 'ababil-members' ),
            'messaging'        => __( 'Enable Messaging', 'ababil-members' ),
            'activity'         => __( 'Enable Activity', 'ababil-members' ),
            'menu_visibility'  => __( 'Hide Login/Register from logged-in users and Profile/Messages/Log out from guests in menus', 'ababil-members' ),
        );
        ?>
        <div class="wrap am-admin-wrap">
          <h1>🕊️ Ababil Members — <?php esc_html_e( 'Settings', 'ababil-members' ); ?></h1>
          <form method="post">
            <?php wp_nonce_field( 'am_settings', 'am_settings_nonce' ); ?>
            <table class="form-table">
              <?php foreach ( $toggles as $key => $label ) : ?>
                <tr><th scope="row"><?php echo esc_html( $label ); ?></th>
                <td><label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $opts[ $key ] ) ); ?>> <?php esc_html_e( 'Enabled', 'ababil-members' ); ?></label></td></tr>
              <?php endforeach; ?>
              <tr><th scope="row"><?php esc_html_e( 'New account status', 'ababil-members' ); ?></th>
                <td><select name="register_status">
                  <option value="approved" <?php selected( ( $opts['register_status'] ?? 'approved' ), 'approved' ); ?>><?php esc_html_e( 'Approved — can login immediately', 'ababil-members' ); ?></option>
                  <option value="pending" <?php selected( ( $opts['register_status'] ?? '' ), 'pending' ); ?>><?php esc_html_e( 'Pending — admin approval required', 'ababil-members' ); ?></option>
                </select></td></tr>
            </table>
            <?php submit_button( __( 'Save Settings', 'ababil-members' ) ); ?>
          </form>
          <h2><?php esc_html_e( 'Shortcodes', 'ababil-members' ); ?></h2>
          <ul class="am-shortcode-list">
            <li><code>[am_login]</code></li><li><code>[am_register]</code></li><li><code>[am_profile]</code></li>
            <li><code>[am_members]</code></li><li><code>[am_messages]</code></li><li><code>[am_logout]</code></li>
            <li><code>[am_restrict]...[/am_restrict]</code></li><li><code>[am_feed]</code></li><li><code>[am_groups]</code></li>
          </ul>
        </div>
        <?php
    }

    public function fields_page() {
        $fields = AM_Fields::all();
        $edit_slug = isset( $_GET['edit_field'] ) ? sanitize_key( $_GET['edit_field'] ) : '';
        $edit = $edit_slug ? AM_Fields::get( $edit_slug ) : null;
        ?>
        <div class="wrap am-admin-wrap">
          <h1><?php esc_html_e( 'Profile Fields', 'ababil-members' ); ?></h1>
          <form method="post">
            <?php wp_nonce_field( 'am_field', 'am_field_nonce' ); ?>
            <table class="form-table">
              <tr><th><label for="am-f-label"><?php esc_html_e( 'Label', 'ababil-members' ); ?></label></th><td><input id="am-f-label" class="regular-text" name="label" value="<?php echo esc_attr( $edit['label'] ?? '' ); ?>" required></td></tr>
              <tr><th><label for="am-f-slug"><?php esc_html_e( 'Slug', 'ababil-members' ); ?></label></th><td><input id="am-f-slug" class="regular-text" name="slug" value="<?php echo esc_attr( $edit['slug'] ?? '' ); ?>" required pattern="[a-z0-9_]+"></td></tr>
              <tr><th><label for="am-f-type"><?php esc_html_e( 'Type', 'ababil-members' ); ?></label></th><td><select id="am-f-type" name="type">
                <?php foreach ( AM_Fields::types() as $k => $label ) : ?>
                  <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $edit['type'] ?? 'text', $k ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
              </select></td></tr>
              <tr><th><label for="am-f-options"><?php esc_html_e( 'Options (one per line, for Select)', 'ababil-members' ); ?></label></th><td><textarea id="am-f-options" name="options" rows="3" class="large-text"><?php echo esc_textarea( $edit['options'] ?? '' ); ?></textarea></td></tr>
              <tr><th><?php esc_html_e( 'Required', 'ababil-members' ); ?></th><td><label><input type="checkbox" name="required" value="1" <?php checked( ! empty( $edit['required'] ) ); ?>> <?php esc_html_e( 'Yes', 'ababil-members' ); ?></label></td></tr>
              <tr><th><?php esc_html_e( 'Visibility', 'ababil-members' ); ?></th><td><select name="visibility">
                <?php foreach ( array( 'public','members','admin' ) as $v ) : ?>
                  <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $edit['visibility'] ?? 'public', $v ); ?>><?php echo esc_html( ucfirst( $v ) ); ?></option>
                <?php endforeach; ?>
              </select></td></tr>
              <tr><th><?php esc_html_e( 'Conditional display', 'ababil-members' ); ?></th><td>
                <input type="text" class="regular-text" name="depends_on" value="<?php echo esc_attr( $edit['depends_on'] ?? '' ); ?>" placeholder="field_slug">
                <input type="text" class="regular-text" name="depends_value" value="<?php echo esc_attr( $edit['depends_value'] ?? '' ); ?>" placeholder="value">
                <p class="description"><?php esc_html_e( 'Optional. Show this field only when the chosen field equals the given value. Leave blank to always show.', 'ababil-members' ); ?></p>
              </td></tr>
            </table>
            <?php submit_button( $edit ? __( 'Update Field', 'ababil-members' ) : __( 'Add Field', 'ababil-members' ) ); ?>
          </form>

          <h2><?php esc_html_e( 'Existing fields', 'ababil-members' ); ?></h2>
          <table class="widefat striped">
            <thead><tr><th><?php esc_html_e( 'Label', 'ababil-members' ); ?></th><th><?php esc_html_e( 'Slug', 'ababil-members' ); ?></th><th><?php esc_html_e( 'Type', 'ababil-members' ); ?></th><th><?php esc_html_e( 'Visibility', 'ababil-members' ); ?></th><th><?php esc_html_e( 'Actions', 'ababil-members' ); ?></th></tr></thead>
            <tbody>
            <?php if ( ! $fields ) : ?><tr><td colspan="5"><?php esc_html_e( 'No custom fields yet.', 'ababil-members' ); ?></td></tr><?php endif; ?>
            <?php foreach ( $fields as $f ) : ?>
              <tr>
                <td><?php echo esc_html( $f['label'] ); ?></td><td><code><?php echo esc_html( $f['slug'] ); ?></code></td>
                <td><?php echo esc_html( $f['type'] ); ?></td><td><?php echo esc_html( $f['visibility'] ); ?></td>
                <td>
                  <a href="<?php echo esc_url( admin_url( 'admin.php?page=ababil-members-fields&edit_field=' . $f['slug'] ) ); ?>"><?php esc_html_e( 'Edit', 'ababil-members' ); ?></a> |
                  <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ababil-members-fields&am_delete_field=' . $f['slug'] ), 'am_delete_field' ) ); ?>" onclick="return confirm('Delete this field?');"><?php esc_html_e( 'Delete', 'ababil-members' ); ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
    }

    public function forms_page() {
        $opts = get_option( 'am_options', array() );
        $selected = isset( $opts['form_fields'] ) && is_array( $opts['form_fields'] ) ? $opts['form_fields'] : array();
        $fields = AM_Fields::all();
        ?>
        <div class="wrap am-admin-wrap">
          <h1><?php esc_html_e( 'Registration Form', 'ababil-members' ); ?></h1>
          <form method="post">
            <?php wp_nonce_field( 'am_form', 'am_form_nonce' ); ?>
            <table class="form-table">
              <tr><th><?php esc_html_e( 'Built-in fields', 'ababil-members' ); ?></th><td><?php esc_html_e( 'Username, Email and Password are always shown.', 'ababil-members' ); ?></td></tr>
              <tr><th><?php esc_html_e( 'Include custom fields', 'ababil-members' ); ?></th><td class="am-forms-list">
                <?php if ( ! $fields ) : ?><?php esc_html_e( 'Add fields on the Fields page first.', 'ababil-members' ); ?>
                <?php else : foreach ( $fields as $f ) : ?>
                  <label><input type="checkbox" name="form_fields[]" value="<?php echo esc_attr( $f['slug'] ); ?>" <?php checked( in_array( $f['slug'], $selected, true ) ); ?>> <?php echo esc_html( $f['label'] ); ?> <code><?php echo esc_html( $f['slug'] ); ?></code></label>
                <?php endforeach; endif; ?>
              </td></tr>
              <tr><th><label for="am-submit-label"><?php esc_html_e( 'Submit button label', 'ababil-members' ); ?></label></th><td><input id="am-submit-label" class="regular-text" name="submit_label" value="<?php echo esc_attr( $opts['submit_label'] ?? __( 'Create account', 'ababil-members' ) ); ?>"></td></tr>
            </table>
            <?php submit_button( __( 'Save Form', 'ababil-members' ) ); ?>
          </form>
        </div>
        <?php
    }

    public function members_page() {
        $users = get_users( array( 'role__not_in' => array( 'administrator' ), 'number' => 50, 'orderby' => 'registered', 'order' => 'DESC' ) );
        ?>
        <div class="wrap am-admin-wrap am-members-page">
          <div class="am-page-head">
            <div>
              <h1 class="am-page-title"><?php esc_html_e( 'Members', 'ababil-members' ); ?> <span class="am-count"><?php echo count( $users ); ?></span></h1>
              <p class="am-page-sub"><?php esc_html_e( 'Manage member roles, approval status and moderation.', 'ababil-members' ); ?></p>
            </div>
          </div>

          <div class="am-table-wrap">
            <table class="widefat striped am-members-table">
              <thead>
                <tr>
                  <th><?php esc_html_e( 'User', 'ababil-members' ); ?></th>
                  <th><?php esc_html_e( 'Role', 'ababil-members' ); ?></th>
                  <th><?php esc_html_e( 'Status', 'ababil-members' ); ?></th>
                  <th><?php esc_html_e( 'Points', 'ababil-members' ); ?></th>
                  <th><?php esc_html_e( 'Actions', 'ababil-members' ); ?></th>
                </tr>
              </thead>
              <tbody>
              <?php if ( ! $users ) : ?>
                <tr><td colspan="5" class="am-empty"><?php esc_html_e( 'No members yet.', 'ababil-members' ); ?></td></tr>
              <?php endif; ?>
              <?php foreach ( $users as $u ) :
                  $status = get_user_meta( $u->ID, 'am_account_status', true );
                  if ( ! $status ) $status = 'approved';
                  $banned = (int) get_user_meta( $u->ID, 'am_banned', true );
                  $avatar = get_user_meta( $u->ID, 'am_avatar', true ) ?: '🙂';
              ?>
                <tr>
                  <td>
                    <div class="am-user-cell">
                      <span class="am-user-avatar"><?php echo esc_html( $avatar ); ?></span>
                      <span class="am-user-info">
                        <strong><?php echo esc_html( $u->user_login ); ?></strong>
                        <small><?php echo esc_html( $u->user_email ); ?></small>
                      </span>
                    </div>
                  </td>
                  <td><span class="am-role-badge"><?php echo esc_html( implode( ', ', $u->roles ) ); ?></span></td>
                  <td>
                    <div class="am-status-group">
                      <span class="am-status am-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status ); ?></span>
                      <?php if ( $banned ) : ?><span class="am-status am-status-banned">🚫 <?php esc_html_e( 'Banned', 'ababil-members' ); ?></span><?php endif; ?>
                    </div>
                  </td>
                  <td><span class="am-points">⭐ <?php echo (int) am_get_points( $u->ID ); ?></span></td>
                  <td>
                    <form method="post" class="am-actions">
                      <?php wp_nonce_field( 'am_member', 'am_member_nonce' ); ?>
                      <input type="hidden" name="uid" value="<?php echo (int) $u->ID; ?>">
                      <select name="new_role" class="am-role-select">
                        <?php foreach ( array( 'subscriber','contributor','author','editor' ) as $r ) : ?>
                          <option value="<?php echo esc_attr( $r ); ?>" <?php selected( in_array( $r, $u->roles, true ) ); ?>><?php echo esc_html( $r ); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="button button-small" name="member_action" value="role"><?php esc_html_e( 'Set role', 'ababil-members' ); ?></button>
                      <?php if ( 'pending' === $status ) : ?><button class="button button-small button-primary" name="member_action" value="approve"><?php esc_html_e( 'Approve', 'ababil-members' ); ?></button><?php endif; ?>
                      <?php if ( 'approved' === $status ) : ?><button class="button button-small" name="member_action" value="reject"><?php esc_html_e( 'Reject', 'ababil-members' ); ?></button><?php endif; ?>
                      <?php if ( $banned ) : ?><button class="button button-small" name="member_action" value="unban"><?php esc_html_e( 'Unban', 'ababil-members' ); ?></button><?php else : ?><button class="button button-small" name="member_action" value="ban"><?php esc_html_e( 'Ban', 'ababil-members' ); ?></button><?php endif; ?>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php
    }

    /* ================= Phase 3 admin pages ================= */

    /** Email templates page. */
    public function emails_page() {
        $saved = am_get_option( 'am_emails', array() );
        $saved = is_array( $saved ) ? $saved : array();
        ?>
        <div class="wrap am-admin-wrap am-page-emails">
          <h1><?php esc_html_e( 'Email Notifications', 'ababil-members' ); ?></h1>
          <p class="am-intro"><?php esc_html_e( 'Edit the emails sent to members. Enable only the notifications you want. Use the tokens listed under each template.', 'ababil-members' ); ?></p>
          <form method="post">
            <?php wp_nonce_field( 'am_emails', 'am_emails_nonce' ); ?>
            <?php foreach ( AM_Emails::templates() as $key => $def ) :
                $row = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array();
                $enabled = ! empty( $row['enabled'] );
                $subject = isset( $row['subject'] ) ? $row['subject'] : $def['subject'];
                $body    = isset( $row['body'] ) ? $row['body'] : $def['body'];
            ?>
              <div class="am-card">
                <h2 class="am-card-title"><?php echo esc_html( $def['label'] ); ?></h2>
                <label class="am-check"><input type="checkbox" name="em_<?php echo esc_attr( $key ); ?>_enabled" value="1" <?php checked( $enabled ); ?>> <?php esc_html_e( 'Enable this email', 'ababil-members' ); ?></label>
                <table class="form-table">
                  <tr>
                    <th scope="row"><?php esc_html_e( 'Subject', 'ababil-members' ); ?></th>
                    <td><input type="text" class="regular-text" name="em_<?php echo esc_attr( $key ); ?>_subject" value="<?php echo esc_attr( $subject ); ?>"></td>
                  </tr>
                  <tr>
                    <th scope="row"><?php esc_html_e( 'Body', 'ababil-members' ); ?></th>
                    <td><textarea name="em_<?php echo esc_attr( $key ); ?>_body" rows="6" class="large-text"><?php echo esc_textarea( $body ); ?></textarea>
                      <p class="description"><?php echo esc_html( sprintf( __( 'Tokens: %s', 'ababil-members' ), implode( ', ', array_map( function ( $t ) { return '{' . $t . '}'; }, $def['tokens'] ) ) ) ); ?></p></td>
                  </tr>
                </table>
              </div>
            <?php endforeach; ?>
            <?php submit_button( __( 'Save email templates', 'ababil-members' ) ); ?>
          </form>
        </div>
        <?php
    }

    /** Profile tabs builder page. */
    public function tabs_page() {
        $tabs = am_profile_tabs();
        $edit = null;
        if ( isset( $_GET['am_edit_tab'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'am_edit_tab' ) ) {
            $eid = sanitize_key( $_GET['am_edit_tab'] );
            foreach ( $tabs as $t ) { if ( $t['id'] === $eid ) { $edit = $t; break; } }
        }
        $e_id      = $edit['id'] ?? '';
        $e_label   = $edit['label'] ?? '';
        $e_icon    = $edit['icon'] ?? '';
        $e_content = $edit['content'] ?? '';
        ?>
        <div class="wrap am-admin-wrap am-page-tabs">
          <h1><?php esc_html_e( 'Profile Tabs', 'ababil-members' ); ?></h1>
          <p class="am-intro"><?php esc_html_e( 'Add custom tabs to member profiles. Content accepts HTML and shortcodes. The built-in Grid, Reels and Reviews tabs are always shown.', 'ababil-members' ); ?></p>

          <h2><?php echo $edit ? esc_html__( 'Edit tab', 'ababil-members' ) : esc_html__( 'Add tab', 'ababil-members' ); ?></h2>
          <form method="post">
            <?php wp_nonce_field( 'am_tab', 'am_tab_nonce' ); ?>
            <table class="form-table">
              <tr><th scope="row"><?php esc_html_e( 'Tab ID', 'ababil-members' ); ?></th><td><input type="text" class="regular-text" name="tab_id" required value="<?php echo esc_attr( $e_id ); ?>" placeholder="about"><p class="description"><?php esc_html_e( 'Unique key (letters, numbers, underscores). Using the same ID updates the tab.', 'ababil-members' ); ?></p></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Label', 'ababil-members' ); ?></th><td><input type="text" class="regular-text" name="tab_label" required value="<?php echo esc_attr( $e_label ); ?>" placeholder="About"></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Icon', 'ababil-members' ); ?></th><td><input type="text" class="regular-text" name="tab_icon" value="<?php echo esc_attr( $e_icon ); ?>" placeholder="ℹ️"><p class="description"><?php esc_html_e( 'Optional emoji shown before the label.', 'ababil-members' ); ?></p></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Content', 'ababil-members' ); ?></th><td><textarea name="tab_content" rows="6" class="large-text" placeholder="Shortcodes and HTML allowed"><?php echo esc_textarea( $e_content ); ?></textarea></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Visibility', 'ababil-members' ); ?></th><td><select name="tab_visibility">
                <?php foreach ( array( 'all' => __( 'Everyone', 'ababil-members' ), 'members' => __( 'Logged-in members', 'ababil-members' ), 'admin' => __( 'Admins only', 'ababil-members' ) ) as $k => $label ) : ?>
                  <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $edit['visibility'] ?? 'all', $k ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
              </select></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Minimum role', 'ababil-members' ); ?></th><td><select name="tab_min_role">
                <option value=""><?php esc_html_e( 'Any role', 'ababil-members' ); ?></option>
                <?php
                global $wp_roles;
                $roles = $wp_roles ? $wp_roles->get_names() : array();
                foreach ( $roles as $k => $label ) : ?>
                  <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $edit['min_role'] ?? '', $k ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
              </select></td></tr>
            </table>
            <?php submit_button( $edit ? __( 'Update tab', 'ababil-members' ) : __( 'Save tab', 'ababil-members' ) ); ?>
            <?php if ( $edit ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ababil-members-tabs' ) ); ?>"><?php esc_html_e( 'Cancel', 'ababil-members' ); ?></a><?php endif; ?>
          </form>

          <h2><?php esc_html_e( 'Existing custom tabs', 'ababil-members' ); ?></h2>
          <?php if ( $tabs ) : ?>
            <table class="widefat striped am-table">
              <thead><tr><th><?php esc_html_e( 'Label', 'ababil-members' ); ?></th><th><?php esc_html_e( 'ID', 'ababil-members' ); ?></th><th><?php esc_html_e( 'Content', 'ababil-members' ); ?></th><th><?php esc_html_e( 'Actions', 'ababil-members' ); ?></th></tr></thead>
              <tbody>
              <?php foreach ( $tabs as $t ) : ?>
                <tr>
                  <td><strong><?php echo esc_html( $t['label'] ); ?></strong></td>
                  <td><code><?php echo esc_html( $t['id'] ); ?></code></td>
                  <td><?php echo esc_html( mb_substr( strip_tags( $t['content'] ), 0, 80 ) ); ?></td>
                  <td>
                    <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ababil-members-tabs&am_edit_tab=' . rawurlencode( $t['id'] ) ), 'am_edit_tab' ) ); ?>"><?php esc_html_e( 'Edit', 'ababil-members' ); ?></a>
                    <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ababil-members-tabs&am_delete_tab=' . rawurlencode( $t['id'] ) ), 'am_delete_tab' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this tab?', 'ababil-members' ) ); ?>')"><?php esc_html_e( 'Delete', 'ababil-members' ); ?></a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php else : ?>
            <p class="am-muted"><?php esc_html_e( 'No custom tabs yet.', 'ababil-members' ); ?></p>
          <?php endif; ?>
        </div>
        <?php
    }

    /** Password policy + advanced access rules page. */
    public function access_page() {
        $pw    = am_get_option( 'am_password', array() );
        $rules = am_get_option( 'am_access_rules', array() );
        $pw    = is_array( $pw ) ? $pw : array();
        $rules = is_array( $rules ) ? $rules : array();
        $min_len = isset( $pw['min_length'] ) ? (int) $pw['min_length'] : 8;
        $complex = ! empty( $pw['complexity'] );
        $expiry  = isset( $pw['expiry_days'] ) ? (int) $pw['expiry_days'] : 0;
        $min_role = isset( $rules['min_role'] ) ? $rules['min_role'] : '';
        $min_pts  = isset( $rules['min_points'] ) ? (int) $rules['min_points'] : 0;
        $req_status = isset( $rules['require_status'] ) ? $rules['require_status'] : '';
        global $wp_roles;
        $roles = $wp_roles ? $wp_roles->get_names() : array();
        ?>
        <div class="wrap am-admin-wrap am-page-access">
          <h1><?php esc_html_e( 'Password & Access', 'ababil-members' ); ?></h1>
          <form method="post">
            <?php wp_nonce_field( 'am_access', 'am_access_nonce' ); ?>
            <h2><?php esc_html_e( 'Password policy', 'ababil-members' ); ?></h2>
            <table class="form-table">
              <tr><th scope="row"><?php esc_html_e( 'Minimum length', 'ababil-members' ); ?></th><td><input type="number" min="6" max="64" name="pw_min" value="<?php echo (int) $min_len; ?>"></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Require complexity', 'ababil-members' ); ?></th><td><label class="am-check"><input type="checkbox" name="pw_complexity" value="1" <?php checked( $complex ); ?>> <?php esc_html_e( 'Require uppercase, lowercase, a number and a symbol.', 'ababil-members' ); ?></label></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Expiry (days)', 'ababil-members' ); ?></th><td><input type="number" min="0" max="3650" name="pw_expiry" value="<?php echo (int) $expiry; ?>"><p class="description"><?php esc_html_e( '0 disables expiry. When set, members must reset their password after this many days.', 'ababil-members' ); ?></p></td></tr>
            </table>
            <h2><?php esc_html_e( 'Advanced access rules ([am_restrict])', 'ababil-members' ); ?></h2>
            <table class="form-table">
              <tr><th scope="row"><?php esc_html_e( 'Minimum role', 'ababil-members' ); ?></th><td><select name="min_role"><option value=""><?php esc_html_e( 'Any logged-in member', 'ababil-members' ); ?></option><?php foreach ( $roles as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $min_role, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Minimum points', 'ababil-members' ); ?></th><td><input type="number" min="0" name="min_points" value="<?php echo (int) $min_pts; ?>"></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Required account status', 'ababil-members' ); ?></th><td><select name="require_status"><option value=""><?php esc_html_e( 'Any status', 'ababil-members' ); ?></option><option value="approved" <?php selected( $req_status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'ababil-members' ); ?></option><option value="pending" <?php selected( $req_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'ababil-members' ); ?></option></select></td></tr>
            </table>
            <p class="description"><?php esc_html_e( 'These rules apply to [am_restrict] when no shortcode attributes are given. Override per shortcode: [am_restrict role="editor" points="100" status="approved"]...[/am_restrict]', 'ababil-members' ); ?></p>
            <?php submit_button(); ?>
          </form>
        </div>
        <?php
    }

    /** Privacy / GDPR tools page. */
    public function privacy_page() {
        ?>
        <div class="wrap am-admin-wrap am-page-privacy">
          <h1><?php esc_html_e( 'Privacy & GDPR', 'ababil-members' ); ?></h1>
          <p class="am-intro"><?php esc_html_e( 'Export or erase a member\'s Ababil Members data. This covers profile fields, points, posts, comments, messages, reviews and follows.', 'ababil-members' ); ?></p>
          <form method="post">
            <?php wp_nonce_field( 'am_privacy', 'am_privacy_nonce' ); ?>
            <table class="form-table">
              <tr><th scope="row"><?php esc_html_e( 'Member', 'ababil-members' ); ?></th><td>
                <select name="privacy_user" required>
                  <option value=""><?php esc_html_e( 'Select a member…', 'ababil-members' ); ?></option>
                  <?php foreach ( get_users( array( 'number' => 200, 'orderby' => 'login', 'order' => 'ASC' ) ) as $u ) : ?>
                    <option value="<?php echo (int) $u->ID; ?>"><?php echo esc_html( $u->user_login . ' — ' . $u->user_email ); ?></option>
                  <?php endforeach; ?>
                </select>
              </td></tr>
            </table>
            <p>
              <button class="button button-primary" name="privacy_action" value="export"><?php esc_html_e( 'Export data', 'ababil-members' ); ?></button>
              <button class="button" name="privacy_action" value="erase" onclick="return confirm('<?php echo esc_js( __( 'Permanently erase this member\'s plugin data? This cannot be undone.', 'ababil-members' ) ); ?>')"><?php esc_html_e( 'Erase data', 'ababil-members' ); ?></button>
            </p>
          </form>
          <h2><?php esc_html_e( 'Self-service', 'ababil-members' ); ?></h2>
          <p class="am-muted"><?php esc_html_e( 'Members can request export/erasure of their own data through WordPress\'s privacy tools if enabled. Ababil Members registers core exporters and erasers automatically.', 'ababil-members' ); ?></p>
        </div>
        <?php
    }

    /** Social login settings page. */
    public function social_page() {
        $s = am_get_option( 'am_social_login', array() );
        $s = is_array( $s ) ? $s : array();
        $enabled = ! empty( $s['enabled'] );
        ?>
        <div class="wrap am-admin-wrap am-page-social">
          <h1><?php esc_html_e( 'Social Login', 'ababil-members' ); ?></h1>
          <p class="am-intro"><?php esc_html_e( 'Let members sign up and log in with Google or Facebook. Create an OAuth app with each provider and paste its credentials below.', 'ababil-members' ); ?></p>
          <form method="post">
            <?php wp_nonce_field( 'am_social', 'am_social_nonce' ); ?>
            <table class="form-table">
              <tr><th scope="row"><?php esc_html_e( 'Enable social login', 'ababil-members' ); ?></th><td><label class="am-check"><input type="checkbox" name="social_enabled" value="1" <?php checked( $enabled ); ?>> <?php esc_html_e( 'Show Google / Facebook buttons on login and register.', 'ababil-members' ); ?></label></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Google Client ID', 'ababil-members' ); ?></th><td><input type="text" class="regular-text" name="google_id" value="<?php echo esc_attr( $s['google_id'] ?? '' ); ?>"></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Google Client Secret', 'ababil-members' ); ?></th><td><input type="password" class="regular-text" name="google_secret" value="<?php echo esc_attr( $s['google_secret'] ?? '' ); ?>"></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Facebook App ID', 'ababil-members' ); ?></th><td><input type="text" class="regular-text" name="facebook_id" value="<?php echo esc_attr( $s['facebook_id'] ?? '' ); ?>"></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Facebook App Secret', 'ababil-members' ); ?></th><td><input type="password" class="regular-text" name="facebook_secret" value="<?php echo esc_attr( $s['facebook_secret'] ?? '' ); ?>"></td></tr>
            </table>
            <p class="description"><?php echo esc_html( sprintf( __( 'Callback URLs to add in your provider dashboards: Google → %s, Facebook → %s', 'ababil-members' ), home_url( '/?am_social=google' ), home_url( '/?am_social=facebook' ) ) ); ?></p>
            <?php submit_button(); ?>
          </form>
        </div>
        <?php
    }

    /** Style customizer page. */
    public function styles_page() {
        $s = am_get_option( 'am_styles', array() );
        $s = is_array( $s ) ? $s : array();
        $primary = $s['primary'] ?? '#0095f6';
        $accent  = $s['accent'] ?? '#ff2e63';
        $bg      = $s['bg'] ?? '#ffffff';
        $radius  = isset( $s['radius'] ) ? (int) $s['radius'] : 8;
        $font    = isset( $s['font_scale'] ) ? (int) $s['font_scale'] : 100;
        ?>
        <div class="wrap am-admin-wrap am-page-styles">
          <h1><?php esc_html_e( 'Style Customizer', 'ababil-members' ); ?></h1>
          <p class="am-intro"><?php esc_html_e( 'Tune the plugin look. Changes are scoped to Ababil Members only and never affect the rest of your site.', 'ababil-members' ); ?></p>
          <form method="post">
            <?php wp_nonce_field( 'am_styles', 'am_styles_nonce' ); ?>
            <table class="form-table">
              <tr><th scope="row"><?php esc_html_e( 'Primary colour', 'ababil-members' ); ?></th><td><input type="text" class="am-color" name="st_primary" value="<?php echo esc_attr( $primary ); ?>" data-default="#0095f6"></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Accent colour', 'ababil-members' ); ?></th><td><input type="text" class="am-color" name="st_accent" value="<?php echo esc_attr( $accent ); ?>" data-default="#ff2e63"></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Surface / background', 'ababil-members' ); ?></th><td><input type="text" class="am-color" name="st_bg" value="<?php echo esc_attr( $bg ); ?>" data-default="#ffffff"></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Corner radius (px)', 'ababil-members' ); ?></th><td><input type="number" min="0" max="40" name="st_radius" value="<?php echo (int) $radius; ?>"></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Font scale (%)', 'ababil-members' ); ?></th><td><input type="number" min="80" max="150" name="st_font" value="<?php echo (int) $font; ?>"></td></tr>
            </table>
            <?php submit_button(); ?>
          </form>
        </div>
        <?php
    }

    /* ================= Phase 4 admin pages ================= */

    /** Explicitly assign the pages that hold each plugin shortcode. */
    public function pages_page() {
        $map   = am_pages_map();
        $pages = get_pages( array( 'sort_column' => 'menu_order,post_title', 'number' => 200 ) );
        $slugs = array(
            'am-login'    => __( 'Login', 'ababil-members' ) . ' — [am_login]',
            'am-register' => __( 'Register', 'ababil-members' ) . ' — [am_register]',
            'am-profile'  => __( 'Profile', 'ababil-members' ) . ' — [am_profile]',
            'am-members'  => __( 'Members', 'ababil-members' ) . ' — [am_members]',
            'am-messages' => __( 'Messages', 'ababil-members' ) . ' — [am_messages]',
            'am-feed'     => __( 'Feed', 'ababil-members' ) . ' — [am_feed]',
            'am-groups'   => __( 'Groups', 'ababil-members' ) . ' — [am_groups]',
            'am-logout'   => __( 'Log out', 'ababil-members' ) . ' — [am_logout]',
        );
        ?>
        <div class="wrap am-admin-wrap am-page-pages">
          <h1><?php esc_html_e( 'Pages', 'ababil-members' ); ?></h1>
          <p class="am-intro"><?php esc_html_e( 'Tell Ababil Members which page holds each shortcode. Leave unassigned to use the default page slug.', 'ababil-members' ); ?></p>
          <form method="post">
            <?php wp_nonce_field( 'am_pages', 'am_pages_nonce' ); ?>
            <table class="form-table">
              <?php foreach ( $slugs as $slug => $label ) : ?>
                <tr><th scope="row"><?php echo esc_html( $label ); ?></th><td>
                  <select name="<?php echo esc_attr( 'page_' . str_replace( '-', '_', $slug ) ); ?>">
                    <option value="0"><?php esc_html_e( '— Default —', 'ababil-members' ); ?></option>
                    <?php foreach ( $pages as $p ) : ?>
                      <option value="<?php echo (int) $p->ID; ?>" <?php selected( (int) ( $map[ $slug ] ?? 0 ), (int) $p->ID ); ?>><?php echo esc_html( $p->post_title . ' (/' . $p->post_name . ')' ); ?></option>
                    <?php endforeach; ?>
                  </select>
                </td></tr>
              <?php endforeach; ?>
            </table>
            <?php submit_button( __( 'Save pages', 'ababil-members' ) ); ?>
          </form>
        </div>
        <?php
    }

    /** Registration behaviour, consent and profile permalink base. */
    public function registration_page() {
        $reg  = am_registration_settings();
        $cons = am_consent_settings();
        global $wp_roles;
        $roles = $wp_roles ? $wp_roles->get_names() : array();
        ?>
        <div class="wrap am-admin-wrap am-page-registration">
          <h1><?php esc_html_e( 'Registration', 'ababil-members' ); ?></h1>
          <form method="post">
            <?php wp_nonce_field( 'am_registration', 'am_registration_nonce' ); ?>
            <table class="form-table">
              <tr><th scope="row"><?php esc_html_e( 'New member role', 'ababil-members' ); ?></th><td><select name="reg_role">
                <?php foreach ( array( 'subscriber','contributor','author' ) as $r ) : ?>
                  <option value="<?php echo esc_attr( $r ); ?>" <?php selected( $reg['role'], $r ); ?>><?php echo esc_html( $roles[ $r ] ?? $r ); ?></option>
                <?php endforeach; ?>
              </select></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Display name format', 'ababil-members' ); ?></th><td><select name="reg_display_name">
                <option value="username" <?php selected( $reg['display_name'], 'username' ); ?>><?php esc_html_e( 'Username', 'ababil-members' ); ?></option>
                <option value="email" <?php selected( $reg['display_name'], 'email' ); ?>><?php esc_html_e( 'Email address', 'ababil-members' ); ?></option>
                <?php foreach ( AM_Fields::all() as $f ) : ?>
                  <option value="field:<?php echo esc_attr( $f['slug'] ); ?>" <?php selected( $reg['display_name'], 'field:' . $f['slug'] ); ?>><?php echo esc_html( 'Field: ' . $f['label'] ); ?></option>
                <?php endforeach; ?>
              </select></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Use Gravatars', 'ababil-members' ); ?></th><td><label class="am-check"><input type="checkbox" name="reg_gravatar" value="1" <?php checked( $reg['gravatar'] ); ?>> <?php esc_html_e( 'Show a Gravatar when no profile photo is uploaded.', 'ababil-members' ); ?></label></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Profile permalink base', 'ababil-members' ); ?></th><td><input type="text" class="regular-text" name="reg_permalink_base" value="<?php echo esc_attr( am_permalink_base() ); ?>" pattern="[a-z0-9_-]+"><p class="description"><?php echo esc_html( sprintf( __( 'Profiles are served at %s/username/. Rewrite rules are flushed when saved.', 'ababil-members' ), home_url( '/' ) ) ); ?></p></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'After registration', 'ababil-members' ); ?></th><td><select name="reg_redirect">
                <?php foreach ( array( 'profile' => __( 'Profile', 'ababil-members' ), 'feed' => __( 'Feed', 'ababil-members' ), 'members' => __( 'Members', 'ababil-members' ), 'home' => __( 'Home', 'ababil-members' ) ) as $k => $label ) : ?>
                  <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $reg['redirect'], $k ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
              </select></td></tr>
            </table>
            <h2><?php esc_html_e( 'Registration consent', 'ababil-members' ); ?></h2>
            <table class="form-table">
              <tr><th scope="row"><?php esc_html_e( 'Require consent checkbox', 'ababil-members' ); ?></th><td><label class="am-check"><input type="checkbox" name="consent_enabled" value="1" <?php checked( $cons['enabled'] ); ?>> <?php esc_html_e( 'Members must accept before registering.', 'ababil-members' ); ?></label></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Consent text', 'ababil-members' ); ?></th><td><textarea name="consent_text" rows="2" class="large-text"><?php echo esc_textarea( $cons['text'] ); ?></textarea></td></tr>
            </table>
            <?php submit_button( __( 'Save registration settings', 'ababil-members' ) ); ?>
          </form>
        </div>
        <?php
    }

    /** Security hardening switches. */
    public function security_page() {
        $s = AM_Security::settings();
        ?>
        <div class="wrap am-admin-wrap am-page-security">
          <h1><?php esc_html_e( 'Security', 'ababil-members' ); ?></h1>
          <p class="am-intro"><?php esc_html_e( 'Protect login and registration against abuse. Bot checks apply to both forms.', 'ababil-members' ); ?></p>
          <form method="post">
            <?php wp_nonce_field( 'am_security', 'am_security_nonce' ); ?>
            <table class="form-table">
              <tr><th scope="row"><?php esc_html_e( 'Rate limiting', 'ababil-members' ); ?></th><td><label class="am-check"><input type="checkbox" name="sec_rate_limit" value="1" <?php checked( ! empty( $s['rate_limit'] ) ); ?>> <?php esc_html_e( 'Lock an IP after repeated failed logins.', 'ababil-members' ); ?></label></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Max attempts', 'ababil-members' ); ?></th><td><input type="number" min="2" max="20" name="sec_max_attempts" value="<?php echo (int) ( $s['max_attempts'] ?? 5 ); ?>"></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Lockout (minutes)', 'ababil-members' ); ?></th><td><input type="number" min="1" max="1440" name="sec_lockout" value="<?php echo (int) ( $s['lockout_minutes'] ?? 15 ); ?>"></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Bot protection', 'ababil-members' ); ?></th><td><label class="am-check"><input type="checkbox" name="sec_captcha" value="1" <?php checked( ! empty( $s['captcha'] ) ); ?>> <?php esc_html_e( 'Honeypot + math CAPTCHA on login and registration.', 'ababil-members' ); ?></label></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Two-factor authentication', 'ababil-members' ); ?></th><td><label class="am-check"><input type="checkbox" name="sec_two_factor" value="1" <?php checked( ! empty( $s['two_factor'] ) ); ?>> <?php esc_html_e( 'Allow members to enable email-code 2FA on their Account tab.', 'ababil-members' ); ?></label></td></tr>
              <tr><th scope="row"><?php esc_html_e( 'Audit log', 'ababil-members' ); ?></th><td><label class="am-check"><input type="checkbox" name="sec_audit_log" value="1" <?php checked( ! empty( $s['audit_log'] ) ); ?>> <?php esc_html_e( 'Record security events (logins, registrations, password changes).', 'ababil-members' ); ?></label></td></tr>
            </table>
            <?php submit_button( __( 'Save security settings', 'ababil-members' ) ); ?>
          </form>
        </div>
        <?php
    }

    /** Audit log viewer. */
    public function audit_page() {
        $rows = AM_Security::recent( 100 );
        ?>
        <div class="wrap am-admin-wrap am-page-audit">
          <h1><?php esc_html_e( 'Audit Log', 'ababil-members' ); ?></h1>
          <p class="am-intro"><?php esc_html_e( 'The 100 most recent security events.', 'ababil-members' ); ?></p>
          <form method="post">
            <?php wp_nonce_field( 'am_audit', 'am_audit_nonce' ); ?>
            <p><button class="button" name="am_clear_audit" value="1" onclick="return confirm('<?php echo esc_js( __( 'Clear the audit log?', 'ababil-members' ) ); ?>')"><?php esc_html_e( 'Clear log', 'ababil-members' ); ?></button></p>
          </form>
          <table class="widefat striped am-table">
            <thead><tr><th><?php esc_html_e( 'Time', 'ababil-members' ); ?></th><th><?php esc_html_e( 'Event', 'ababil-members' ); ?></th><th><?php esc_html_e( 'User', 'ababil-members' ); ?></th><th><?php esc_html_e( 'Details', 'ababil-members' ); ?></th><th><?php esc_html_e( 'IP', 'ababil-members' ); ?></th></tr></thead>
            <tbody>
            <?php if ( ! $rows ) : ?><tr><td colspan="5" class="am-muted"><?php esc_html_e( 'No events recorded yet.', 'ababil-members' ); ?></td></tr><?php endif; ?>
            <?php foreach ( $rows as $row ) : ?>
              <tr>
                <td><?php echo esc_html( $row->created ); ?></td>
                <td><code><?php echo esc_html( $row->event ); ?></code></td>
                <td><?php echo $row->user_id ? esc_html( ( $u = get_userdata( (int) $row->user_id ) ) ? $u->user_login : '#' . (int) $row->user_id ) : '&mdash;'; ?></td>
                <td><?php echo esc_html( $row->details ); ?></td>
                <td><?php echo esc_html( $row->ip ); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
    }

    /** Field ordering + shortcode builder. */
    public function builder_page() {
        $fields = AM_Fields::all();
        $order  = am_get_option( 'am_fields_order', array() );
        $order  = is_array( $order ) ? $order : array();
        ?>
        <div class="wrap am-admin-wrap am-page-builder">
          <h1><?php esc_html_e( 'Builder', 'ababil-members' ); ?></h1>
          <h2><?php esc_html_e( 'Field order', 'ababil-members' ); ?></h2>
          <p class="am-intro"><?php esc_html_e( 'Drag the fields to change their order on registration and profile forms.', 'ababil-members' ); ?></p>
          <form method="post" id="am-builder-form">
            <?php wp_nonce_field( 'am_builder', 'am_builder_nonce' ); ?>
            <ul class="am-sortable" id="am-field-order">
              <?php foreach ( $fields as $f ) : ?>
                <li draggable="true" data-slug="<?php echo esc_attr( $f['slug'] ); ?>">
                  <span class="am-drag" aria-hidden="true">⠿</span>
                  <span><?php echo esc_html( $f['label'] ); ?></span>
                  <code><?php echo esc_html( $f['slug'] ); ?></code>
                  <input type="hidden" name="am_field_order[]" value="<?php echo esc_attr( $f['slug'] ); ?>">
                </li>
              <?php endforeach; ?>
              <?php if ( ! $fields ) : ?><li class="am-muted"><?php esc_html_e( 'Add custom fields on the Fields page first.', 'ababil-members' ); ?></li><?php endif; ?>
            </ul>
            <?php submit_button( __( 'Save field order', 'ababil-members' ) ); ?>
          </form>

          <h2><?php esc_html_e( 'Shortcode builder', 'ababil-members' ); ?></h2>
          <p class="am-intro"><?php esc_html_e( 'Pick a shortcode, copy it and paste it into any page or post.', 'ababil-members' ); ?></p>
          <div class="am-shortcode-builder">
            <select id="am-shortcode-select">
              <?php foreach ( array( 'am_login','am_register','am_profile','am_members','am_messages','am_logout','am_feed','am_groups','am_restrict' ) as $sc ) : ?>
                <option value="<?php echo esc_attr( $sc ); ?>"><?php echo esc_html( '[' . $sc . ']' ); ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="button" id="am-shortcode-insert"><?php esc_html_e( 'Copy shortcode', 'ababil-members' ); ?></button>
            <input type="text" id="am-shortcode-out" class="regular-text" readonly value="[am_login]">
          </div>
        </div>
        <?php
    }
}
