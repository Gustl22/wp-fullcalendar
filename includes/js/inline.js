var wpfc_loaded = false;
var wpfc_counts = {};
jQuery(document).ready( function($){
	var DIALOG_MAX_SIZE = 840;

	var bootstrapLoaded = (typeof $().modal == 'function');

	var eventSources = [{
		url: WPFC.ajaxurl,
		data: WPFC.data,
		ignoreTimezone: true,
		allDayDefault: false
	}];
	/* Fetching Goolge Events from Server instead
	if (WPFC.google_calendar_api_key && WPFC.google_calendar_ids) {
		for (var i = 0; i < WPFC.google_calendar_ids.length; i++) {
			var calendarId = WPFC.google_calendar_ids[i];
			eventSources.push({googleCalendarId: calendarId});
		}
	}
	*/

	var fullcalendar_args = {
		timeFormat: WPFC.timeFormat,
        // lang: WPFC.locale,
		defaultView: WPFC.defaultView,
		weekends: WPFC.weekends,
		header: {
			left: WPFC.header.left,
			center: WPFC.header.center,
			right: WPFC.header.right
		},
		month: WPFC.month,
		year: WPFC.year,
		theme: WPFC.wpfc_theme,
		themeSystem: WPFC.wpfc_theme_system,
		firstDay: WPFC.firstDay,
		editable: false,
		views: {
			listYear: {
				validRange: function(nowDate) {
					return {
						start: nowDate.clone().subtract(7, 'days')
					};
				}
			}
		},
		// Fetching Google Events from server instead // googleCalendarApiKey: WPFC.google_calendar_api_key,
		eventSources: eventSources,
		//eventRender: function(event, element) {
		// Only add to array if qtips / tooltips are enabled
			eventMouseover: WPFC.wpfc_qtips ? function (event, jsEvent) {
				var tooltip = '<div class="tooltipevent card" style="padding:5px;;position:absolute;z-index:10001;">' +
					'<div class="card-header">' + event.title + '</div>' +
					'<div class="card-block" style="text-align: center">';
				var timeFormat = 'h:mma';
				var dateFormat = 'MMMM Do YYYY, h:mma';
				var dateFormatAllday = 'MMMM Do YYYY';
				if (event.allDay) {
					tooltip += event.start.format(dateFormatAllday) + '<br/>';
				} else {
					if (event.start) {
						tooltip += event.start.format(dateFormat);
						if (event.end) {
							tooltip += ' - ';
							var days_apart = Math.abs(event.end.diff(event.start, 'days'));
							if (days_apart > 0) {
								tooltip += event.end.format(dateFormat);
							} else {
								tooltip += event.end.format(timeFormat);
							}

						}
						tooltip += '<br/>';
					}
				}
				if (event.excerpt) {
					tooltip += '<small class="text-muted">';
					tooltip += event.excerpt;
					tooltip += '</small>';
				}
				tooltip += '<small class="text-muted" style="color:grey;"><i>...Click event for more details...</i></small></div>';
				tooltip += '</div>';
				var $tooltip = $(tooltip).appendTo('body');

				$(this).mouseover(function (e) {
					$(this).css('z-index', 10000);
					$tooltip.fadeIn('500');
					$tooltip.fadeTo('10', 1.9);
				}).mousemove(function (e) {
					$tooltip.css('top', e.pageY + 10);
					$tooltip.css('left', e.pageX + 25);
				});
			} : null,
			eventMouseout: WPFC.wpfc_qtips ? function (calEvent, jsEvent) {
				$(this).css('z-index', 8);
				$('.tooltipevent').remove();
			} : null,

		// Only add to array if dialogs are enabled
			eventClick: WPFC.wpfc_dialog ? function (event, jsEvent, view) {
				if (event.event_source_type === 'wordpress' && WPFC.wpfc_dialog == 1) {
					return true;
				} else if (event.event_source_type === 'tribe') {
					return true;
				} else {
					var w = $(window).width();
					if (w > DIALOG_MAX_SIZE) {
						w = DIALOG_MAX_SIZE;
					}
					var h = $(window).height() * 0.8;

					var dateFormat = 'MMMM Do YYYY, h:mma';
					var dateFormatAllday = 'MMMM Do YYYY';
					var htmlDate;
					if (event.allDay) {
						htmlDate = '<strong>Date:</strong> ' + event.start.format(dateFormatAllday) + '<br/>'
					} else {
						htmlDate = '<strong>Start:</strong> ' + event.start.format(dateFormat) + '<br/>'
							+ '<strong>End:</strong> ' + event.end.format(dateFormat) + '<br/>';
					}
					htmlDate += '<br/>';
					if (event.location) {
						htmlDate += '<strong>Location: </strong>' + event.location + '<br/>';
						if (event.lat && event.long) {
							// http://maps.google.com/maps?&z=15&mrt=yp&t=k&q=27.9879012+86.9253141
							htmlDate += '<a href="https://maps.google.com/maps?&z=18&mrt=yp&t=m&q=' + event.lat + '+' + event.long + '' + '" target="_blank" class="btn btn-primary active" style="margin: 5px;" role="button" aria-pressed="true"><span class="fa fa-map-marker" aria-hidden="true"></span>&nbsp;&nbsp;Open in map</a>';
							// https://www.google.com/maps/@42.585444,13.007813,6z
							// htmlDate += '<a href="https://www.google.com/maps/@' + event.lat + ',' + event.long + ',6z' + '" target="_blank">Open location in Google Maps</a><br/>';
						} else {
							htmlDate += '<a href="https://maps.google.com/maps?&z=18&mrt=yp&t=m&q=' + encodeURIComponent(event.location) + '" target="_blank" class="btn btn-primary active" style="margin: 5px;" role="button" aria-pressed="true"><span class="fa fa-map-marker" aria-hidden="true"></span>&nbsp;&nbsp;Open in map</a>';
						}
						htmlDate += !bootstrapLoaded ? "<br/>" : '';
					}
					htmlDate += !bootstrapLoaded ? '<br/>' : '';
					var htmlEventDescription = event.description ? event.description.replace(/$/mg, '<br/>') : '';
					var viewEventLabel = "View Event";
					var sourceTypeIcon = "";
					if (event.event_source_type === 'google') {
						viewEventLabel = "View Event on Google Calendar";
						sourceTypeIcon = 'fa-google';
					} else if (event.event_source_type === 'facebook') {
						viewEventLabel = "View Event on Facebook";
						sourceTypeIcon = 'fa-facebook-official';
					}

					var htmlDescription = htmlDate
						+ '<a href="' + event.url + '" target="_blank" class="btn btn-primary active" style="margin: 5px;" role="button" aria-pressed="true"><span class="fa ' + sourceTypeIcon + '" aria-hidden="true"></span>&nbsp;&nbsp;' + viewEventLabel + '</a>';
					htmlDescription += !bootstrapLoaded ? '<br/><br/>' : '';
					htmlDescription +=
						'<a href="/?wpfc-ical=' + event.id + '&event_source_type=' + event.event_source_type + '" target="_blank" class="btn btn-primary active" style="margin: 5px;" role="button" aria-pressed="true"><span class="fa fa-calendar-plus-o" aria-hidden="true"></span>&nbsp;&nbsp;Add to Calendar</a>' //
						+ '<hr style="margin-top: 20px; margin-right: 0px; margin-bottom: 20px; margin-left: 0px;">'
						+ htmlEventDescription;

					if (bootstrapLoaded) { // Use Bootstrap modal
						$('#wpfc-event-dialog-title').text(event.title);
						$('#wpfc-event-dialog-body').html(htmlDescription);
						$('#wpfc-event-dialog').modal();
					} else { // If Bootstrap not loaded, use jQuery UI dialog
						$('#wpfc-event-dialog')
							.html(htmlDescription)
							.attr('title', event.title)
							.dialog({
								height: h,
								width: w,
								position: {my: "center", at: "center", of: window}
							});
					}
				}
				return false;
			} :  null,
		loading: function(bool) {
			if (bool) {
				$(this).parent().find('.wpfc-loading').show();
			}else {
				$(this).parent().find('.wpfc-loading').hide();
			}
		},
		viewRender: function(view, element) {
			if( !wpfc_loaded ){
				var container = $(element).parents('.wpfc-calendar-wrapper');
				container.find('.fc-toolbar').after(container.next('.wpfc-calendar-search').show());
				//catchall selectmenu handle
				$.widget( "custom.wpfc_selectmenu", $.ui.selectmenu, {
					_renderItem: function( ul, item ) {
						var li = $( "<li>", { html: item.label.replace(/#([a-zA-Z0-9]{3}[a-zA-Z0-9]{3}?) - /g, '<span class="wpfc-cat-icon" style="background-color:#$1"></span>') } );
						if ( item.disabled ) {
							li.addClass( "ui-state-disabled" );
						}
						return li.appendTo( ul );
					}
				});
				$('select.wpfc-taxonomy').wpfc_selectmenu({
					format: function(text){
						//replace the color hexes with color boxes
						return text.replace(/#([a-zA-Z0-9]{3}[a-zA-Z0-9]{3}?) - /g, '<span class="wpfc-cat-icon" style="background-color:#$1"></span>');
					},
					select: function( event, ui ){
						var calendar = $('.wpfc-calendar');
						menu_name = $(this).attr('name');
						$( '#' + menu_name + '-button .ui-selectmenu-text' ).html( ui.item.label.replace(/#([a-zA-Z0-9]{3}[a-zA-Z0-9]{3}?) - /g, '<span class="wpfc-cat-icon" style="background-color:#$1"></span>') );
						WPFC.data[menu_name] = ui.item.value;
						calendar.fullCalendar('removeEventSource', WPFC.ajaxurl);
						calendar.fullCalendar('addEventSource', {url : WPFC.ajaxurl, allDayDefault:false, ignoreTimezone: true, data : WPFC.data});
					}
				})
			}
			wpfc_loaded = true;
		}
	};
	if( WPFC.wpfc_locale ){
		$.extend(fullcalendar_args, WPFC.wpfc_locale);
	}
	$(document).trigger('wpfc_fullcalendar_args', [fullcalendar_args]);
	$('.wpfc-calendar').first().fullCalendar(fullcalendar_args);
});
