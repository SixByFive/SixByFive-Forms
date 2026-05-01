<?php
/**
 * Plugin Name: SixByFive Forms
 * Description: Enquiry form with custom DB storage, admin screen, email notifications and spam protection.
 * Version: 1.0.0
 * Author: FalconChipp
 * Author URI: https://sixbyfive.co.uk
 * Text Domain: sbf
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'SBF_VERSION', '1.0.0' );
define( 'SBF_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SBF_URL',     plugin_dir_url( __FILE__ ) );
define( 'SBF_TABLE',   'sbf_enquiries' );

require_once SBF_DIR . 'inc/db.php';
require_once SBF_DIR . 'inc/spam.php';
require_once SBF_DIR . 'inc/email.php';
require_once SBF_DIR . 'inc/form.php';
require_once SBF_DIR . 'inc/admin.php';

register_activation_hook( __FILE__, 'sbf_install' );