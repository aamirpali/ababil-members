<?php
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

if ( ! isset( $GLOBALS['am_profile_user'] ) ) {
    $GLOBALS['am_profile_user'] = is_user_logged_in() ? wp_get_current_user() : null;
}

include AM_PATH . 'templates/ig-profile.php';

get_footer();
