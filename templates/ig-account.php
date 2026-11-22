<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** Rendered inside the profile .ig-plugin-container for the profile owner only. */
$me   = get_current_user_id();
$user = wp_get_current_user();
$s    = class_exists( 'AM_Security' ) ? AM_Security::settings() : array();
$twofa_on_global = ! empty( $s['two_factor'] );
$twofa_on_me     = (int) get_user_meta( $me, 'am_2fa_enabled', true ) === 1;
$status          = isset( $_GET['am_account'] ) ? sanitize_key( $_GET['am_account'] ) : '';
$messages = array(
    'password' => __( 'Password changed.', 'ababil-members' ),
    '2fa'      => __( 'Two-factor setting saved.', 'ababil-members' ),
    'export'   => __( 'Your data export is ready. Use the download button below.', 'ababil-members' ),
    'erased'   => __( 'Your plugin data has been erased.', 'ababil-members' ),
);
?>
<div class="ig-account">
  <?php if ( ! empty( $GLOBALS['am_account_notice'] ) ) : ?>
    <p class="ig-error"><?php echo esc_html( $GLOBALS['am_account_notice'] ); ?></p>
  <?php elseif ( $status && isset( $messages[ $status ] ) ) : ?>
    <p class="ig-notice">✅ <?php echo esc_html( $messages[ $status ] ); ?></p>
  <?php endif; ?>

  <div class="ig-account-grid">
    <section class="ig-account-card">
      <h3 class="ig-account-title">🔒 <?php esc_html_e( 'Change password', 'ababil-members' ); ?></h3>
      <form method="post" class="ig-account-form">
        <?php wp_nonce_field( 'am_account', 'am_nonce' ); ?>
        <input type="hidden" name="am_action" value="am_acct_password">
        <label class="ig-field"><span class="ig-edit-label"><?php esc_html_e( 'Current password', 'ababil-members' ); ?></span>
          <span class="ig-pw-wrap"><input class="ig-input" type="password" name="current_password" required autocomplete="current-password"><button type="button" class="ig-pw-toggle" aria-label="<?php echo esc_attr__( 'Show password', 'ababil-members' ); ?>">👁</button></span>
        </label>
        <label class="ig-field"><span class="ig-edit-label"><?php esc_html_e( 'New password', 'ababil-members' ); ?></span>
          <span class="ig-pw-wrap"><input class="ig-input" type="password" name="new_password" required autocomplete="new-password"><button type="button" class="ig-pw-toggle" aria-label="<?php echo esc_attr__( 'Show password', 'ababil-members' ); ?>">👁</button></span>
        </label>
        <label class="ig-field"><span class="ig-edit-label"><?php esc_html_e( 'Confirm new password', 'ababil-members' ); ?></span>
          <span class="ig-pw-wrap"><input class="ig-input" type="password" name="confirm_password" required autocomplete="new-password"><button type="button" class="ig-pw-toggle" aria-label="<?php echo esc_attr__( 'Show password', 'ababil-members' ); ?>">👁</button></span>
        </label>
        <button class="ig-btn ig-btn-primary" type="submit"><?php esc_html_e( 'Update password', 'ababil-members' ); ?></button>
      </form>
    </section>

    <?php if ( $twofa_on_global ) : ?>
    <section class="ig-account-card">
      <h3 class="ig-account-title">📱 <?php esc_html_e( 'Two-factor authentication', 'ababil-members' ); ?></h3>
      <p class="ig-muted"><?php esc_html_e( 'When enabled, logging in requires a one-time code sent to your email.', 'ababil-members' ); ?></p>
      <form method="post" class="ig-account-form">
        <?php wp_nonce_field( 'am_account', 'am_nonce' ); ?>
        <input type="hidden" name="am_action" value="am_acct_2fa">
        <label class="ig-check"><input type="checkbox" name="am_2fa_enable" value="1" <?php checked( $twofa_on_me ); ?>> <?php esc_html_e( 'Enable two-factor authentication', 'ababil-members' ); ?></label>
        <label class="ig-field"><span class="ig-edit-label"><?php esc_html_e( 'Current password (required to change)', 'ababil-members' ); ?></span>
          <span class="ig-pw-wrap"><input class="ig-input" type="password" name="current_password" required autocomplete="current-password"><button type="button" class="ig-pw-toggle" aria-label="<?php echo esc_attr__( 'Show password', 'ababil-members' ); ?>">👁</button></span>
        </label>
        <button class="ig-btn ig-btn-primary" type="submit"><?php esc_html_e( 'Save 2FA setting', 'ababil-members' ); ?></button>
      </form>
    </section>
    <?php endif; ?>

    <section class="ig-account-card">
      <h3 class="ig-account-title">📦 <?php esc_html_e( 'Your data', 'ababil-members' ); ?></h3>
      <p class="ig-muted"><?php esc_html_e( 'Download a copy of the data this plugin stores about you.', 'ababil-members' ); ?></p>
      <form method="post" class="ig-account-form">
        <?php wp_nonce_field( 'am_account', 'am_nonce' ); ?>
        <input type="hidden" name="am_action" value="am_acct_export">
        <button class="ig-btn" type="submit"><?php esc_html_e( 'Export my data', 'ababil-members' ); ?></button>
      </form>
      <?php if ( $status === 'export' && get_transient( 'am_export_self_' . $me ) ) : ?>
        <p><a class="ig-btn ig-btn-primary" href="<?php echo esc_url( wp_nonce_url( am_page_url( 'am-profile' ) . '?am_self_export=1', 'am_self_export' ) ); ?>"><?php esc_html_e( 'Download JSON', 'ababil-members' ); ?></a></p>
      <?php endif; ?>
    </section>

    <section class="ig-account-card ig-account-danger">
      <h3 class="ig-account-title">⚠️ <?php esc_html_e( 'Erase or delete', 'ababil-members' ); ?></h3>
      <p class="ig-muted"><?php esc_html_e( 'Erasing removes all plugin data but keeps your account. Deleting removes your account entirely. Both require typing ERASE and your password.', 'ababil-members' ); ?></p>
      <form method="post" class="ig-account-form">
        <?php wp_nonce_field( 'am_account', 'am_nonce' ); ?>
        <label class="ig-field"><span class="ig-edit-label"><?php esc_html_e( 'Type ERASE to confirm', 'ababil-members' ); ?></span>
          <input class="ig-input" type="text" name="confirm_text" required pattern="ERASE" autocomplete="off">
        </label>
        <label class="ig-field"><span class="ig-edit-label"><?php esc_html_e( 'Password', 'ababil-members' ); ?></span>
          <span class="ig-pw-wrap"><input class="ig-input" type="password" name="current_password" required autocomplete="current-password"><button type="button" class="ig-pw-toggle" aria-label="<?php echo esc_attr__( 'Show password', 'ababil-members' ); ?>">👁</button></span>
        </label>
        <button class="ig-btn" name="am_action" value="am_acct_erase" type="submit"><?php esc_html_e( 'Erase my plugin data', 'ababil-members' ); ?></button>
        <button class="ig-btn ig-btn-danger" name="am_action" value="am_acct_delete" type="submit" onclick="return confirm('<?php echo esc_js( __( 'Delete your account permanently? This cannot be undone.', 'ababil-members' ) ); ?>')"><?php esc_html_e( 'Delete my account', 'ababil-members' ); ?></button>
      </form>
    </section>
  </div>
</div>
