<?php
/**
 * Plugin Name: WP Fullcalendar
 * Version: 1.0.0
 * Plugin URI: http://www.hughlashbrooke.com/
 * Description: This is your starter template for your next WordPress plugin.
 * Author: Hugh Lashbrooke
 * Author URI: http://www.hughlashbrooke.com/
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: wp-fullcalendar
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Hugh Lashbrooke
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load plugin class files.
require_once 'includes/class-wp-fullcalendar.php';
require_once 'includes/class-wp-fullcalendar-settings.php';

// Load plugin libraries.
require_once 'includes/lib/class-wp-fullcalendar-admin-api.php';
require_once 'includes/lib/class-wp-fullcalendar-post-type.php';
require_once 'includes/lib/class-wp-fullcalendar-taxonomy.php';

/**
 * Returns the main instance of WP_Fullcalendar to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object WP_Fullcalendar
 */
function wp_fullcalendar() {
	$instance = WP_Fullcalendar::instance( __FILE__, '1.0.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = WP_Fullcalendar_Settings::instance( $instance );
	}

	return $instance;
}

wp_fullcalendar();
