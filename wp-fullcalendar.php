<?php
/*
Plugin Name: WP FullCalendar
Version: 2.0.3
Plugin URI: http://wordpress.org/extend/plugins/wp-fullcalendar/
Description: Uses the jQuery FullCalendar plugin to create a stunning calendar view of events, posts and eventually other CPTs. Integrates well with Events Manager
Author: Marcus Sykes
Author URI: http://msyk.es
*/

/*
Copyright (c) 2016, Marcus Sykes

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

define('WPFC_VERSION', '2.1.0');
define('WPFC_UI_VERSION','1.11'); //jQuery 1.11.x
define("WPFC_QUERY_VARIABLE", "wpfc-ical");
define("WPFC_FACEBOOK_REQUEST_TOKEN_QUERY_VARIABLE", "wpfc-facebook-request-token");
define("WPFC_FACEBOOK_TOKEN_QUERY_VARIABLE", "wpfc-facebook-token");
// Include the required dependencies.
require_once( 'vendor/autoload.php' );

define('WPFC_GOOGLE_SCOPES', implode(' ', array(
		Google_Service_Calendar::CALENDAR_READONLY)
));


use Ical\Component\Calendar;
use Ical\Component\Event;
use Ical\Feed;

class WP_FullCalendar{
	static $args = array();

	public static function init() {
		//Scripts
		if( !is_admin() ){ //show only in public area
			add_action('wp_enqueue_scripts',array('WP_FullCalendar','enqueue_scripts'));
			//shortcodes
			add_shortcode('fullcalendar', array('WP_FullCalendar','calendar'));
			add_shortcode('events_fullcalendar', array('WP_FullCalendar','calendar')); //depreciated, will be gone by 1.0
		}else{
			//admin actions
			include('wpfc-admin.php');
		}
		add_action('wp_ajax_WP_FullCalendar', array('WP_FullCalendar','ajax') );
		add_action('wp_ajax_nopriv_WP_FullCalendar', array('WP_FullCalendar','ajax') );
		add_action('wp_ajax_wpfc_dialog_content', array('WP_FullCalendar','dialog_content') );
		add_action('wp_ajax_nopriv_dialog_dialog_content', array('WP_FullCalendar','dialog_content') );
		//base arguments
		self::$args['type'] = get_option('wpfc_default_type','event');
		//START Events Manager Integration - will soon be removed
		if( defined('EM_VERSION') && EM_VERSION <= 5.62 ){
			if( !empty($_REQUEST['action']) && $_REQUEST['action'] = 'WP_FullCalendar' && !empty($_REQUEST['type']) && $_REQUEST['type'] == EM_POST_TYPE_EVENT ){
				//reset the start/end times for Events Manager 5.6.1 and less, to avoid upgrade-breakages
				$_REQUEST['start'] = $_POST['start'] = strtotime($_REQUEST['start']); //maybe excessive, but easy sanitization
				$_REQUEST['end'] = $_POST['end'] = strtotime($_REQUEST['end']);
			}
		}
		if( defined('EM_VERSION') && is_admin() ){
			include('wpfc-events-manager.php');
		}
		//END Events Manager Integration
	}
	
	public static function enqueue_scripts(){
		global $wp_query;
		$min = defined('WP_DEBUG') && WP_DEBUG ? '':'.min';
		$obj_id = is_home() ? '-1':$wp_query->get_queried_object_id();
		$wpfc_scripts_limit = get_option('wpfc_scripts_limit');
		if( empty($wpfc_scripts_limit) || in_array($obj_id, explode(',',$wpfc_scripts_limit)) ){
			$dependencies = array(
				'jquery',
				'jquery-ui-core',
				'jquery-ui-widget',
				'jquery-ui-position',
				'jquery-ui-selectmenu',
				'jquery-ui-dialog');
			//Scripts
			wp_enqueue_script('wpfc-moment', plugins_url('bower_components/moment/min/moment-with-locales.js', __FILE__, $dependencies, WPFC_VERSION));
			wp_enqueue_script( 'wp-fullcalendar', plugins_url( 'bower_components/fullcalendar/dist/fullcalendar.js', __FILE__ ), $dependencies, WPFC_VERSION ); //jQuery will load as dependency
			self::loadLocaleJS('fc-locale', 'bower_components/fullcalendar/dist/locale');
			wp_enqueue_script( 'wpfc-gcal', plugins_url( 'bower_components/fullcalendar/dist/gcal.js', __FILE__ ), $dependencies, WPFC_VERSION ); //jQuery will load as dependency
			WP_FullCalendar::localize_script();
			//Styles
			wp_enqueue_style('wpfc-custom', plugins_url('includes/css/wpfc.css',__FILE__), array(), WPFC_VERSION);
			wp_enqueue_style('wp-fullcalendar', plugins_url('bower_components/fullcalendar/dist/fullcalendar.css',__FILE__), array(), WPFC_VERSION);
			//Load custom style or jQuery UI Theme
			$wpfc_theme = get_option('wpfc_theme_css');
			if( preg_match('/\.css$/', $wpfc_theme) ){
				//user-defined style within the themes/themename/plugins/wp-fullcalendar/ folder
				//if you're using jQuery UI Theme-Roller, you need to include the jquery-ui-css framework file too, you could do this using the @import CSS rule or include it all in your CSS file
				if( file_exists(get_stylesheet_directory()."/plugins/wp-fullcalendar/".$wpfc_theme) ){
					$wpfc_theme_css = get_stylesheet_directory_uri()."/plugins/wp-fullcalendar/".$wpfc_theme;
					wp_deregister_style('jquery-ui');
					wp_enqueue_style('jquery-ui', $wpfc_theme_css, array('wp-fullcalendar'), WPFC_VERSION);
				}
			}elseif( !empty($wpfc_theme) ){
				//We'll find the current jQuery UI version and attempt to load the right version of jQuery UI, otherwise we'll load the default. This allows backwards compatability from 3.6 onwards.
				global $wp_scripts;
				$jquery_ui_version = preg_replace('/\.[0-9]+$/', '', $wp_scripts->registered['jquery-ui-core']->ver);
				if( $jquery_ui_version != WPFC_UI_VERSION ){
					$jquery_ui_css_versions = glob( $plugin_path = plugin_dir_path(__FILE__)."/includes/css/jquery-ui-".$jquery_ui_version.'*', GLOB_ONLYDIR);
					if( !empty($jquery_ui_css_versions) ){
						//use backwards compatible theme
						$jquery_ui_css_folder = str_replace(plugin_dir_path(__FILE__),'', array_pop($jquery_ui_css_versions));
						$jquery_ui_css_uri = plugins_url(trailingslashit($jquery_ui_css_folder).$wpfc_theme."/jquery-ui$min.css",__FILE__);
						$wpfc_theme_css = plugins_url(trailingslashit($jquery_ui_css_folder).$wpfc_theme.'/theme.css',__FILE__);
					}
				}
				if( empty($wpfc_theme_css) ){
					//use default theme
					$jquery_ui_css_uri = plugins_url('/includes/css/jquery-ui/'.$wpfc_theme."/jquery-ui$min.css",__FILE__);
					$wpfc_theme_css = plugins_url('/includes/css/jquery-ui/'.$wpfc_theme.'/theme.css',__FILE__);
				}
				if( !empty($wpfc_theme_css) ){
					wp_deregister_style('jquery-ui');
					wp_enqueue_style('jquery-ui', $jquery_ui_css_uri, array('wp-fullcalendar'), WPFC_VERSION);
					wp_enqueue_style('jquery-ui-theme', $wpfc_theme_css, array('wp-fullcalendar'), WPFC_VERSION);
				}
			}
		}
	}

	public static function localize_script(){
		$js_vars = array();
		$schema = is_ssl() ? 'https':'http';
		$js_vars['ajaxurl'] = admin_url('admin-ajax.php', $schema);
		$js_vars['firstDay'] =  get_option('start_of_week');
		$js_vars['wpfc_theme'] = get_option('wpfc_theme_css') ? true:false;
		$js_vars['wpfc_theme_system'] = get_option('wpfc_theme_system');
		$js_vars['wpfc_limit'] = get_option('wpfc_limit',3);
		$js_vars['wpfc_limit_txt'] = get_option('wpfc_limit_txt','more ...');
		//$js_vars['google_calendar_api_key'] = get_option('wpfc_google_calendar_api_key', '');
		//$js_vars['google_calendar_ids'] = preg_split('/\s+/', get_option('wpfc_google_calendar_ids', ''));
		//FC options
		$js_vars['timeFormat'] = get_option('wpfc_timeFormat', 'h(:mm)t');
		$js_vars['defaultView'] = get_option('wpfc_defaultView', 'month');
		$js_vars['weekends'] = get_option('wpfc_weekends',true) ? 'true':'false';
		$js_vars['header'] = new stdClass();
		$js_vars['header']->left = 'prev,next today';
		$js_vars['header']->center = 'title';
		$js_vars['header']->right = implode(',', get_option('wpfc_available_views', array('month','basicWeek','basicDay')));
		$js_vars['header'] = apply_filters('wpfc_calendar_header_vars', $js_vars['header']);
        $js_vars['wpfc_qtips'] = get_option('wpfc_qtips',true) == true;
		$js_vars['wpfc_dialog'] = get_option('wpfc_dialog',true) == true;
		$js_vars['locale'] = get_locale();
		//calendar translations
		wp_localize_script('wp-fullcalendar', 'WPFC', apply_filters('wpfc_js_vars', $js_vars));
	}

	/**
	 * Catches ajax requests by fullcalendar
	 */
	public static function ajax(){
		global $post;
		//sort out args
		unset($_REQUEST['month']); //no need for these two
		unset($_REQUEST['year']);
		//maybe excessive, but easy sanitization of start/end query params
		$_REQUEST['start'] = $_POST['start'] = date('Y-m-d', strtotime($_REQUEST['start']));
		$_REQUEST['end'] = $_POST['end'] = date('Y-m-d', strtotime($_REQUEST['end']));

		//initiate vars
		$fb_fetch_now = false;
		$fb_last_fetch = intval(get_option('wpfc_facebook_last_fetch', -1));
		if ($fb_last_fetch < 0) {
			$fb_fetch_now = true;
		} else {
			$fb_refresh_interval = intval(get_option('wpfc_facebook_refresh_interval', 600)); // Default 600 = 10 minutes
			if ($fb_refresh_interval == 0 || (time() - $fb_last_fetch) > $fb_refresh_interval) {
				$fb_fetch_now = true;
			}
		}
		$items = $fb_fetch_now ? array() : get_option('wpfc_facebook_events', array());

		$facebook_events_enabled = get_option('wpfc_facebook_group_events', false);
		// TODO Fetch Facebook events when a request param instructs to
		if ($facebook_events_enabled && empty($items)) {
			$fb_app_id            = get_option('wpfc_facebook_app_id');
			$fb_app_secret        = get_option('wpfc_facebook_app_secret');
			$fb_user_access_token = get_option('wpfc_facebook_access_token');
			$fb_group_ids         = explode(',',get_option('wpfc_facebook_group_ids'));

			$_SESSION['fb_access_token'] = $fb_user_access_token;

			// Initialize the Facebook PHP SDK v5.
			$fb = new Facebook\Facebook( [
				'app_id'                => $fb_app_id,
				'app_secret'            => $fb_app_secret,
				'default_graph_version' => 'v2.3',
				'default_access_token'  => $fb_user_access_token
			] );

			foreach($fb_group_ids as $fb_group_id) {
				$fb_events = $fb->get( '/' . $fb_group_id . '/events', $fb_user_access_token );

				$graph_edge = $fb_events->getGraphEdge();

				foreach ( $graph_edge as $graph_node ) {
					$event_name        = $graph_node['name'];
					$event_description = $graph_node['description'];
					$event_start_time  = $graph_node['start_time'];
					$event_end_time    = $graph_node['end_time'];
					$event_id          = $graph_node['id'];
					$place             = $graph_node->getField('place');

					$event_location = is_null($place) ? '' : $place['name'];
					$latitude = null;
					$longitude = null;
					if (!is_null($place) && !is_null($place->getField('location'))) {
						$place_location = $place['location'];
						$event_location .= ', ' . $place_location['street'] . ', ' . $place_location['city'] . ', ' . $place_location['state'] . ', ' . $place_location['zip'];
						$latitude = $place_location['latitude'];
						$longitude = $place_location['longitude'];
					}

					$time_zone = get_option('timezone_string');

					$start_dt = DateTime::createFromFormat( 'U', $event_start_time->getTimestamp());
					$start_dt->setTimezone(new DateTimeZone($time_zone));
					$end_dt = DateTime::createFromFormat( 'U', $event_end_time->getTimestamp());
					$end_dt->setTimezone(new DateTimeZone($time_zone));

					$item = array(
						"id"                => $event_id,
						"title"             => $event_name,
						"description"       => $event_description,
						"color"             => '#3b5998', // TODO Make this configurable
						"start"             => $start_dt->format(DateTime::ISO8601),
						"end"               => $end_dt->format(DateTime::ISO8601),
						"url"               => 'https://www.facebook.com/events/' . $event_id,
						'event_id'          => $event_id,
						'location'          => $event_location,
						'lat'               => $latitude,
						'long'              => $longitude,
						'event_source_type' => 'facebook',
						'className'         => 'facebook-event'
					);
					$items[] = apply_filters( 'wpfc_ajax_post', $item, $post );
				}
			}
			update_option( 'wpfc_facebook_events', $items );
			update_option('wpfc_facebook_last_fetch', time());
		}

		$google_calendar_api_key = get_option('wpfc_google_calendar_api_key', null);
		$google_calendar_ids_string = get_option('wpfc_google_calendar_ids', null);
		if ($google_calendar_api_key && $google_calendar_ids_string) {

			$google_fetch_now = false;
			$google_last_fetch = intval(get_option('wpfc_facebook_last_fetch', -1));
			if ($google_last_fetch < 0) {
				$google_fetch_now = true;
			} else {
				$google_refresh_interval = intval(get_option('wpfc_facebook_refresh_interval', 600)); // Default 600 = 10 minutes
				if ($google_refresh_interval == 0 || (time() - $google_last_fetch) > $google_refresh_interval) {
					$google_fetch_now = true;
				}
			}

			$google_items = $google_fetch_now ? array() : get_option('wpfc_google_events', array());
			if (empty($google_items)) {
				$google_client = new Google_Client();
				$google_client->setApplicationName( 'wp-fullcalendar' );
				$google_client->setScopes( WPFC_GOOGLE_SCOPES );
				$google_client->setDeveloperKey( $google_calendar_api_key );
				$google_service      = new Google_Service_Calendar( $google_client );
				$google_calendar_ids = array_filter( preg_split( '/\s+/', get_option( 'wpfc_google_calendar_ids', '' ) ) );
				foreach ( $google_calendar_ids as $google_calendar_id ) {
					$results = $google_service->events->listEvents( $google_calendar_id );
					if ( count( $results->getItems() ) > 0 ) {
						foreach ( $results->getItems() as $event ) {
							$start = $event->start->dateTime;
							if ( empty( $start ) ) {
								$start = $event->start->date;
							}
							$end = $event->end->dateTime;
							if ( empty( $end ) ) {
								$end = $event->end->date;
							}
							$item           = array(
								"id"                => $event->id,
								"title"             => $event->summary,
								"description"       => $event->description,
								"color"             => '#4885ed',
								"start"             => $start,
								"end"               => $end,
								"url"               => $event->htmlLink,
								'event_id'          => $event->id,
								'allDay'            => $event->allDay,
								'location'          => $event->location,
								'event_source_type' => 'google',
								'className'         => 'google-event'
							);
							$google_items[] = $item;
						}
					}
				}
				update_option( 'wpfc_google_events', $google_items );
				update_option( 'wpfc_google_last_fetch', time() );
			}
			if ($google_items && count($google_items) > 0) {
				$items = array_merge( $items, $google_items );
			}
		}

		//echo json_encode(apply_filters('wpfc_ajax', $items));
		//die();
		if (get_option('wpfc_wordpress_events', false)) {
			$args = array( 'scope'   => array( $_REQUEST['start'], $_REQUEST['end'] ),
						   'owner'   => false,
						   'status'  => 1,
						   'order'   => 'ASC',
						   'orderby' => 'post_date',
						   'full'    => 1
			);
			//get post type and taxonomies, determine if we're filtering by taxonomy
			$post_types             = get_post_types( array( 'public' => true ), 'names' );
			$post_type              = ! empty( $_REQUEST['type'] ) && in_array( $_REQUEST['type'], $post_types ) ? $_REQUEST['type'] : get_option( 'wpfc_default_type' );
			$args['post_type']      = $post_type;
			$args['post_status']    = 'publish'; //only show published status
			$args['posts_per_page'] = - 1;
			if ( $args['post_type'] == 'attachment' ) {
				$args['post_status'] = 'inherit';
			}
			$args['tax_query'] = array();
			foreach ( get_object_taxonomies( $post_type ) as $taxonomy_name ) {
				if ( ! empty( $_REQUEST[ $taxonomy_name ] ) ) {
					$args['tax_query'][] = array(
						'taxonomy' => $taxonomy_name,
						'field'    => 'id',
						'terms'    => $_REQUEST[ $taxonomy_name ]
					);
				}
			}
			//initiate vars
			$args  = apply_filters( 'wpfc_fullcalendar_args', $args );
			$limit = get_option( 'wpfc_limit', 3 );
			//$items = array();
			$item_dates_more  = array();
			$item_date_counts = array();

			//Create our own loop here and tamper with the where sql for date ranges, as per http://codex.wordpress.org/Class_Reference/WP_Query#Time_Parameters
			function wpfc_temp_filter_where( $where = '' ) {
				global $wpdb;
				$where .= $wpdb->prepare( " AND post_date >= %s AND post_date < %s", $_REQUEST['start'], $_REQUEST['end'] );

				return $where;
			}

			add_filter( 'posts_where', 'wpfc_temp_filter_where' );
			do_action( 'wpfc_before_wp_query' );
			$the_query = new WP_Query( $args );
			remove_filter( 'posts_where', 'wpfc_temp_filter_where' );
			//loop through each post and slot them into the array of posts to return to browser
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$color          = "#a8d144";
				$post_date      = substr( $post->post_date, 0, 10 );
				$post_timestamp = strtotime( $post->post_date );
				if ( empty( $item_date_counts[ $post_date ] ) || $item_date_counts[ $post_date ] < $limit ) {
					$title                          = $post->post_title;
					$item = array(
						"title"             => $title,
						"color"             => $color,
						"start"             => date( 'Y-m-d\TH:i:s', $post_timestamp ),
						"end"               => date( 'Y-m-d\TH:i:s', $post_timestamp ),
						"url"               => get_permalink( $post->ID ),
						'post_id'           => $post->ID,
						'event_source_type' => 'wordpress'
					);
					$items[]                        = apply_filters( 'wpfc_ajax_post', $item, $post );
					$item_date_counts[ $post_date ] = ( ! empty( $item_date_counts[ $post_date ] ) ) ? $item_date_counts[ $post_date ] + 1 : 1;
				} elseif ( empty( $item_dates_more[ $post_date ] ) ) {
					$item_dates_more[ $post_date ] = 1;
					$day_ending                    = $post_date . "T23:59:59";
					//TODO archives not necessarily working
					$more_array = array( "title"     => get_option( 'wpfc_limit_txt', 'more ...' ),
										 "color"     => get_option( 'wpfc_limit_color', '#fbbe30' ),
										 "start"     => $day_ending,
										 'post_id'   => 0,
										 'className' => 'wpfc-more'
					);
					global $wp_rewrite;
					$archive_url = get_post_type_archive_link( $post_type );
					if ( ! empty( $archive_url ) || $post_type == 'post' ) { //posts do have archives
						$archive_url       = trailingslashit( $archive_url );
						$archive_url       .= $wp_rewrite->using_permalinks() ? date( 'Y/m/', $post_timestamp ) : '?m=' . date( 'Ym', $post_timestamp );
						$more_array['url'] = $archive_url;
					}
					$items[] = apply_filters( 'wpfc_ajax_more', $more_array, $post_date );
				}
			}
		}

		/********************************************************************************
		 * Modern Tribe "The Events Calendar"
		 *
		 * https://theeventscalendar.com/product/wordpress-events-calendar/
		 ********************************************************************************/
		if ( is_plugin_active( 'the-events-calendar/the-events-calendar.php' ) ) {

			$args = array();

			$defaults = array(
				'post_type'            => Tribe__Events__Main::POSTTYPE,
				'orderby'              => 'event_date',
				'order'                => 'ASC',
				//'posts_per_page'       => tribe_get_option( 'postsPerPage', 10 ),
				'tribe_render_context' => 'default',
			);
			$args = wp_parse_args( $args, $defaults );

			$the_query = new WP_Query( $args );

			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$color              = "#449CD4";
				$post_timestamp     = strtotime( $post->EventStartDate );
				$post_end_timestamp = strtotime( $post->EventEndDate );

				$title   = $post->post_title;
				$item = array(
					"title"             => $title,
					"color"             => $color,
					"start"             => date( 'Y-m-d\TH:i:s', $post_timestamp ),
					"end"               => date( 'Y-m-d\TH:i:s', $post_end_timestamp ),
					"url"               => get_permalink( $post->ID ),
					'post_id'           => $post->ID,
					'event_source_type' => 'tribe',
					'excerpt'           => tribe_events_get_the_excerpt( $post )
				);
				$items[] = apply_filters( 'wpfc_ajax_post', $item, $post );
			}
		}

		echo json_encode(apply_filters('wpfc_ajax', $items));
		die(); //normally we'd wp_reset_postdata();
	}

	/**
	 * Called during AJAX request for dialog content for a calendar item
	 */
	public static function dialog_content(){
		$content = '';
		$event_id = $_REQUEST['event_id'];
		if (!empty($event_id)) {
			$items = get_option('wpfc_facebook_events', array());
			foreach ($items as $item) {
				if ($item['event_id'] == $event_id) {
					//$content .= "&#x1F5D3";
					$content .= '<p><strong>Start Time:</strong>&nbsp;'.$item['start'].'</p>';
					$content .= '<p><strong>End Time:</strong>&nbsp;'.$item['end'].'</p>';
					$content .= '<p><strong><a href="'.$item['url'].'" target="_blank">View event on Facebook</a></strong></p>';
					$content .= '<p><strong><a href="/?'.WPFC_QUERY_VARIABLE.'='.$event_id.'&event_source_type=facebook" target="_blank">Add to Calendar</a></strong></p>';
					$content .= '<hr style="margin-top: 20px; margin-right: 0px; margin-bottom: 20px; margin-left: 0px;">';
					$content .= '<div style="float:left; margin:0px 5px 5px 0px;">'.nl2br(htmlentities($item['description'])).'</div>';
					break;
				}
			}
		} else if( !empty($_REQUEST['post_id']) ){
			$post = get_post($_REQUEST['post_id']);
			if( $post->post_type == 'attachment' ){
				$content = wp_get_attachment_image($post->ID, 'thumbnail');
			}else{
				$content = ( !empty($post) ) ? $post->post_content : '';
				if( get_option('wpfc_dialog_image',1) ){
					$post_image = get_the_post_thumbnail($post->ID, array(get_option('wpfc_dialog_image_w',75),get_option('wpfc_dialog_image_h',75)));
					if( !empty($post_image) ){
						$content = '<div style="float:left; margin:0px 5px 5px 0px;">'.$post_image.'</div>'.$content;
					}
				}
			}
		}

		echo apply_filters('wpfc_dialog_content', $content);
		die();
	}
	
	/**
	 * Returns the calendar HTML setup and primes the js to load at wp_footer
	 * @param array $args
	 * @return string
	 */
	public static function calendar( $args = array() ){
		if (is_array($args) ) self::$args = array_merge(self::$args, $args);
		self::$args['month'] = (!empty($args['month'])) ? $args['month']-1:date('m', current_time('timestamp'))-1;
		self::$args['year'] = (!empty($args['year'])) ? $args['year']:date('Y', current_time('timestamp'));
		self::$args = apply_filters('wpfc_fullcalendar_args', self::$args);
		add_action('wp_footer', array('WP_FullCalendar','footer_js'));
		ob_start();
		?>
		<div class="wpfc-calendar-wrapper"><form class="wpfc-calendar"></form><div class="wpfc-loading"></div></div>
		<div class="wpfc-calendar-search" style="display:none;">
			<?php
				$post_type = !empty(self::$args['type']) ? self::$args['type']:'post';
				//figure out what taxonomies to show
				$wpfc_post_taxonomies = get_option('wpfc_post_taxonomies');
				$search_taxonomies = !empty($wpfc_post_taxonomies[$post_type]) ? array_keys($wpfc_post_taxonomies[$post_type]):array();
				if( !empty($args['taxonomies']) ){
					//we accept taxonomies in arguments
					$search_taxonomies = explode(',',$args['taxonomies']);
					array_walk($search_taxonomies, 'trim');
					unset(self::$args['taxonomies']);
				}
				//go through each post type taxonomy and display if told to
				foreach( get_object_taxonomies($post_type) as $taxonomy_name ){
					$taxonomy = get_taxonomy($taxonomy_name);
					if( count(get_terms($taxonomy_name, array('hide_empty'=>1))) > 0 && in_array($taxonomy_name, $search_taxonomies) ){
						$default_value = !empty(self::$args[$taxonomy_name]) ? self::$args[$taxonomy_name]:0;
                        if ($taxonomy_name == EM_TAXONOMY_CATEGORY && !empty(self::$args['category']))
                            $default_value = self::$args['category'];
                        if (!is_numeric($default_value)) {
                            $default_value = get_term_by('slug', $default_value, $taxonomy_name)->term_id;
                        }
						$taxonomy_args = array( 'echo'=>true, 'hide_empty' => 1, 'name' => $taxonomy_name, 'hierarchical' => true, 'class' => 'wpfc-taxonomy '.$taxonomy_name, 'taxonomy' => $taxonomy_name, 'selected'=> $default_value, 'show_option_all' => $taxonomy->labels->all_items);
						wp_dropdown_categories( apply_filters('wpmfc_calendar_taxonomy_args', $taxonomy_args, $taxonomy ) );
					}
				}
				do_action('wpfc_calendar_search', self::$args);
			?>
		</div>
		<div id="wpfc-event-dialog" title="Event" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h5 id="wpfc-event-dialog-title" class="modal-title">Event</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div id="wpfc-event-dialog-body" class="modal-body">
					</div>
					<!--<div class="modal-footer"></div>-->
				</div>
			</div>
		</div>
		<script type="text/javascript">
			WPFC.data = { action : 'WP_FullCalendar'<?php
					//these arguments were assigned earlier on when displaying the calendar, and remain constant between ajax calls
					if(!empty(self::$args)){ echo ", "; }
					$strings = array();
					foreach( self::$args as $key => $arg ){
						$arg = is_numeric($arg) ? (int) $arg : "'$arg'";
						$strings[] = "'$key'" ." : ". $arg ;
					}
					echo implode(", ", $strings);
			?> };
			WPFC.month = <?php echo self::$args['month']; ?>;
			WPFC.year = <?php echo self::$args['year']; ?>;
		</script>
		<?php
		do_action('wpfc_calendar_displayed', $args);
		return ob_get_clean();
	}

	/**
	 * Run at wp_footer if a calendar is output earlier on in the page.
	 * @uses self::$args - which was modified during self::calendar()
	 */
	public static function footer_js(){
        wp_enqueue_script( 'inline', plugins_url('includes/js/inline.js', __FILE__));

        self::loadLocaleJS('wpfc-locale', 'includes/js/lang');
	}

    /**
     * @param $name    some unique script name
     * @param $relPath the path to the js file
     */
	public static function loadLocaleJS($name, $relPath){
        $locale_code = strtolower(str_replace('_','-', get_locale()));
        $file_long = $relPath . '/' . $locale_code . '.js';
        $file_short = $relPath .'/'. substr($locale_code, 0, 2) . '.js';

        if (file_exists(dirname(__FILE__) . '/' . $file_short)) {
            wp_enqueue_script( $name, plugins_url($file_short, __FILE__));
        } elseif (file_exists(dirname(__FILE__) . '/' . $file_long)) {
            wp_enqueue_script( $name, plugins_url($file_long, __FILE__));
        } else {
            /*
            echo "Files not found: <br>";
            echo dirname(__FILE__) . '/' . $file_long . '<br>';
            echo dirname(__FILE__) . '/' . $file_short . '<br>';
            die();
            */
        }
    }

	public static function wpfc_filter_query_vars( $vars ) {
		if ( isset( $_GET[ WPFC_QUERY_VARIABLE ] ) ) {
			$event_id = $_GET[ WPFC_QUERY_VARIABLE ];
			$event_source_type = $_GET['event_source_type'];

			if ($event_source_type == 'facebook') {
				$items = get_option( 'wpfc_facebook_events', array() );
			} else if ($event_source_type = 'google') {
				$items = get_option( 'wpfc_google_events', array() );
			} else {
				$items = [];
				error_log('Unsupported event source type');
			}
			foreach ($items as $item) {
				if ($item['event_id'] == $event_id) {

					// One feed (i.e. URL) can host more than one calendar, lets create a feed
					$feed = new Feed();

					// This calendar will contain our events
					$calendar = new Calendar( 'wpfc-ical//v1' );

					$event = new Event( $event_source_type . '-' . $item['id'] );
					//$event->created( new DateTime( '2015-01-01' ) );
					//$event->lastModified( new DateTime( '2015-01-05' ) );

					$time_zone = get_option('timezone_string');

					$start_dt = new DateTime( $item['start'] );
					$start_dt->setTimezone(new DateTimeZone($time_zone));
					$end_dt   = new DateTime( $item['end'] );
					$end_dt->setTimezone(new DateTimeZone($time_zone));

					$event->between( $start_dt, $end_dt );
					$event->summary( $item['title'] );
					$event->description( $item['description'] );
					$event->allDay( false ); // TODO set this
					$event->url( $item['url'] );
					if (!is_null($item['location'])) {
						$event->location($item['location']);
					}

					// Add this event to the calendar
					$calendar->addEvent( $event );

					// Add the calendar to the feed
					$feed->addCalendar( $calendar );

					// Output the feed with appropriate HTTP header
					$feed->download($event_source_type.'-'.$event_id.'.ics');
					die();
				}
			}
		} else if ( isset( $_GET[ WPFC_FACEBOOK_TOKEN_QUERY_VARIABLE ] ) ) {
			if(!session_id()) {
				session_start();
			}
			// Facebook code is set in 'code' GET URL parameter
			$fb = self::getFacebook();

			$helper = $fb->getRedirectLoginHelper();
			try {
				$accessToken = $helper->getAccessToken();
				$longLivedToken = $fb->getOAuth2Client()->getLongLivedAccessToken($accessToken);

			} catch(Facebook\Exceptions\FacebookResponseException $e) {
				// When Graph returns an error
				echo 'Graph returned an error: ' . $e->getMessage();
				exit;
			} catch(Facebook\Exceptions\FacebookSDKException $e) {
				// When validation fails or other local issues
				echo 'Facebook SDK returned an error: ' . $e->getMessage();
				exit;
			}

			if (isset($longLivedToken)) {
				// Logged in!
				$_SESSION['facebook_access_token'] = (string) $longLivedToken;
				update_option('wpfc_facebook_access_token', $longLivedToken);
				// Now you can redirect to another page and use the
				// access token from $_SESSION['facebook_access_token']
				echo '<h2 style="color: blue">SUCCESS: Facebook long-lived access token retrieved. Do this often, tokens expire after 60 days.</h2>';
				die();
			} elseif ($helper->getError()) {
				// The user denied the request
				exit;
			}

		} else if ( isset( $_GET[ WPFC_FACEBOOK_REQUEST_TOKEN_QUERY_VARIABLE ] ) ) {
			if(!session_id()) {
				session_start();
			}

			$fb = self::getFacebook();

			$helper    = $fb->getRedirectLoginHelper();
			$returnUrl = get_site_url() . '/?' . WPFC_FACEBOOK_TOKEN_QUERY_VARIABLE . '=true';
			$loginUrl  = $helper->getLoginUrl($returnUrl);
			if ( wp_redirect( $loginUrl ) ) {
				exit;
			}
		}

		return $vars;
	}

	/**
	 * @return \Facebook\Facebook
	 */
	public static function getFacebook(): \Facebook\Facebook {
		$fb_app_id     = get_option( 'wpfc_facebook_app_id' );
		$fb_app_secret = get_option( 'wpfc_facebook_app_secret' );

		// Initialize the Facebook PHP SDK v5.
		$fb = new Facebook\Facebook( [
			'app_id'                => $fb_app_id,
			'app_secret'            => $fb_app_secret,
			'default_graph_version' => 'v2.3'
		] );

		return $fb;
	}
}
add_action('plugins_loaded',array('WP_FullCalendar','init'), 100);
add_filter('query_vars', array('WP_FullCalendar', 'wpfc_filter_query_vars'));

// action links
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'wpfc_settings_link', 10, 1);
function wpfc_settings_link($links) {
	$new_links = array(); //put settings first
	$new_links[] = '<a href="'.admin_url('options-general.php?page=wp-fullcalendar').'">'.__('Settings', 'wp-fullcalendar').'</a>';
	return array_merge($new_links,$links);
}

//translations
load_plugin_textdomain('wp-fullcalendar', false, dirname( plugin_basename( __FILE__ ) ).'/includes/langs');