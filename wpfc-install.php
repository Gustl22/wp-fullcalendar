<?php
//if called, assume we're installing/updated
add_option('wpfc_theme', get_option('dbem_emfc_theme',1));
add_option('wpfc_theme_css', 'ui-lightness');
add_option('wpfc_theme_system', 'standard');
add_option('wpfc_limit', get_option('dbem_emfc_events_limit',3));
add_option('wpfc_limit_txt', get_option('dbem_emfc_events_limit_txt','more ...'));
add_option('wpfc_qtips', false);
add_option('wpfc_dialog', false);
add_option('wpfc_timeFormat', 'h(:mm)A');
add_option('wpfc_defaultView', 'month');
add_option('wpfc_available_views', array('month','basicWeek','basicDay'));
add_option('wpfc_facebook_events', array());
add_option('wpfc_wordpress_events', false);
add_option('wpfc_facebook_group_events', false); // True to enable Facebook Group Events
add_option('wpfc_facebook_app_id', '');
add_option('wpfc_facebook_app_secret', '');
add_option('wpfc_facebook_access_token', '');
add_option('wpfc_facebook_group_ids', '');
add_option('wpfc_facebook_refresh_interval', 600); // In seconds (600 = 10 minutes)
add_option('wpfc_google_refresh_interval', 600); // In seconds (600 = 10 minutes)

//make a change to the theme
if( version_compare( get_option('wpfc_version'), '1.0.2') ){
	$wpfc_theme_css = get_option('wpfc_theme_css');
	//replace CSS theme value for new method
	$wpfc_theme_css = str_replace( plugins_url('includes/css/ui-themes/',__FILE__), '', $wpfc_theme_css);
	if( $wpfc_theme_css !== get_option('wpfc_theme_css') ){
		//it uses jQuery UI CSS, so remove trailing .css from value
		$wpfc_theme_css = str_replace('.css','', $wpfc_theme_css);
	}else{
		//replace custom CSS value
		$wpfc_theme_css = str_replace( get_stylesheet_directory_uri()."/plugins/wp-fullcalendar/", '', $wpfc_theme_css);
	}
	update_option('wpfc_theme_css', $wpfc_theme_css);
}

//update version
update_option('wpfc_version', WPFC_VERSION);