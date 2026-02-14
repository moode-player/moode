/*!
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

const QUEUE_ADD = 'Selected items have been added to the Queue. ';
const QUEUE_CLEAR_ADD = 'Selected items have been added after clearing the Queue. ';

// arg3: duration or extra message text
// arg4: optional duration if arg3 (extra message text) present
function notify(title, tag, arg3, arg4 = '') {
	//console.log('title=' + title + ', tag=' + tag + ', arg3=' + arg3 + ', arg4=' + arg4);

	var allNotifications = {
		// Queue
		add_item: QUEUE_ADD,
		add_item_next: QUEUE_ADD,
		add_group: QUEUE_ADD,
		add_group_next: QUEUE_ADD,
		clear_add_item: QUEUE_CLEAR_ADD,
		clear_add_group: QUEUE_CLEAR_ADD,
		queue_item_removed: 'Selected items have been removed. ',
		queue_item_moved: 'Selected items have been moved. ',
		queue_cleared: 'Queue has been cleared. ',
		queue_cropped: 'Queue has been cropped. ',
		playqueue_info: '<br>',
		// Library
		update_library: 'Library is being updated... <br><br>Click the progress spinner for status.',
		library_updating: 'Library update is already in progress. ',
		library_loading: 'Library is loading... ',
		dbupdate_status: '',
		// Playlist/Queue
		saving_queue: 'Saving Queue. ',
		queue_saved: 'Queue has been saved. ',
		playlist_name_needed: 'Playlist name is empty. ',
		playlist_name_error: 'Invalid playlist name. ',
		setting_favorites_name: 'Setting Favorites name... ',
		favorites_name_set: 'Favorites name has been set. ',
		adding_favorite: 'Adding favorite... ',
		favorite_added: 'Favorite has been added. ',
		no_favorite_to_add: 'Nothing to add. ',
		add_to_playlist: 'Items have been added. ',
		select_playlist: 'Select a playlist. ',
		// Playback
		play_here_config_error: 'HTTP streaming must be ON and encoder set to LAME (MP3).',
		// Playlist view
		creating_playlist: 'Creating new playlist... ',
		updating_playlist: 'Updating playlist... ',
		new_playlist: 'Playlist has been created. ',
		upd_playlist: 'Playlist has been updated. ',
		del_playlist: 'Playlist has been deleted. ',
		// Radio view
		validation_check: 'Validation check. ',
		creating_station: 'Creating new station... ',
		updating_station: 'Updating station... ',
		new_station: 'Station has been created. ',
		upd_station: 'Station has been updated. ',
		del_station: 'Station has been deleted. ',
		blank_entries: 'Name or URL is blank. ',
		// Multiroom
		trx_querying_receivers: 'Querying receivers... ',
		trx_no_receivers_found: 'No receivers were found. Run receiver Discovery. ',
		trx_run_receiver_discovery: 'Run receiver Discovery. ',
		trx_turning_receiver_off: 'Turning off receiver... ',
		trx_discovering_receivers: 'Discovering receivers... ',
		trx_configuring_sender: 'Configuring Sender daemon...',
		trx_configuring_mpd: 'Switching back to MPD...',
		// CamillaDSP
		cdsp_update_config: 'Switching to ',
		cdsp_config_update_failed: 'Configuraton update has failed. ',
		// Renderers
		renderer_disconnect: 'Disconnecting from renderer... ',
		renderer_turnoff: 'Turning off renderer... ',
		// Network config
		dhcp_required: 'DHCP is required. ',
		// System
		restart: 'System is restarting... ',
		shutdown: 'System is shutting down... ',
		reconnect: 'Connection is being reestablished... ',
		mpd_error: '',
		userid_error: 'The image does not contain a userid.<br><br>',
		firstuse_welcome: '<b>moOde audio player ' +
			SESSION.json['moode_release'].substring(0,2) +
			' series</b><br>Powered by PiOS ' +
			SESSION.json['raspbianver'].split(' ')[1] + '<br><br>',
		update_available: 'An update is available.<br>',
		player_info: '<br>',
		viewport: 'VIEWPORT<br>',
		debug: 'DEBUG<br>',
		// Dashboard
		dashboard_discovering: 'Discovering players...<br>',
		//dashboard_cmd_submitted: 'Command: ',
		dashboard_none_selected: 'No players have been selected. ',
		// Advanced search
		search_fields_empty: 'Search fields are empty. ',
		predefined_filter_invalid: 'Predefined filter is invalid. ',
		// Miscellaneous
		upd_clock_radio: 'Clock radio has been updated. ',
		settings_updated: 'Settings have been updated. ',
		settings_updated_with_msg: 'Settings have been updated. ',
		gathering_info: 'Gathering info... ',
		installing_plugin: 'Installing plugin... ',
		auto_coverview: 'Auto-CoverView ',
		nvme_formatting_drive: "Formatting drive...<br>Please wait for completion message. ",
		downgrading_chromium: "Downgrading chromium...<br>Please wait for completion message. ",
		// Recorder plugin
		recorder_installed: 'Recorder has been installed. ',
		recorder_uninstalled: 'Recorder has been uninstalled. ',
		recorder_plugin_na: 'Recorder plugin is n/a. ',
		recorder_deleted: 'Raw recordings have been deleted. ',
		recorder_tagging: 'Recordings are being tagged...<br>',
		recorder_tagged: 'Tagging is complete.<br>',
		recorder_nofiles: 'No files to tag. '
	};

	var reducedNotifications = {
		// Queue
		playqueue_info: '<br>',
		// Library
		update_library: 'Library is being updated... <br><br>Click the progress spinner for status.',
		library_updating: 'Library update is already in progress. ',
		library_loading: 'Library is loading... ',
		dbupdate_status: '',
		// Playlist/Queue
		playlist_name_needed: 'Playlist name is empty. ',
		playlist_name_error: 'Invalid playlist name. ',
		no_favorite_to_add: 'Nothing to add. ',
		select_playlist: 'Select a playlist. ',
		// Playback
		play_here_config_error: 'HTTP streaming must be ON and encoder set to LAME (MP3).',
		// Playlist view
		// no_tags
		// Radio view
		validation_check: 'Validation check. ',
		blank_entries: 'Name or URL is blank. ',
		// Multiroom
		trx_querying_receivers: 'Querying receivers... ',
		trx_no_receivers_found: 'No receivers were found. Run receiver Discovery. ',
		trx_run_receiver_discovery: 'Run receiver Discovery. ',
		trx_turning_receiver_off: 'Turning off receiver... ',
		trx_discovering_receivers: 'Discovering receivers... ',
		trx_configuring_sender: 'Configuring Sender daemon...',
		trx_configuring_mpd: 'Switching back to MPD...',
		// CamillaDSP
		cdsp_update_config: 'Switching to ',
		cdsp_config_update_failed: 'Configuraton update has failed. ',
		// Renderers
		renderer_disconnect: 'Disconnecting from renderer... ',
		renderer_turnoff: 'Turning off renderer... ',
		// Network config
		dhcp_required: 'DHCP is required. ',
		// System
		restart: 'System is restarting... ',
		shutdown: 'System is shutting down... ',
		reconnect: 'Connection is being reestablished... ',
		mpd_error: '',
		userid_error: 'The image does not contain a userid.<br><br>',
		firstuse_welcome: '<b>moOde audio player ' +
			SESSION.json['moode_release'].substring(0,2) +
			' series</b><br>Powered by PiOS ' +
			SESSION.json['raspbianver'].split(' ')[1] + '<br><br>',
		update_available: 'An update is available.<br>',
		player_info: '<br>',
		viewport: 'VIEWPORT<br>',
		debug: 'DEBUG<br>',
		// Dashboard
		dashboard_discovering: 'Discovering players...<br>',
		dashboard_none_selected: 'No players have been selected. ',
		// Advanced search
		search_fields_empty: 'Search fields are empty. ',
		predefined_filter_invalid: 'Predefined filter is invalid. ',
		// Miscellaneous
		settings_updated_with_msg: 'Settings have been updated. ',
		gathering_info: 'Gathering info... ',
		installing_plugin: 'Installing plugin... ',
		auto_coverview: 'Auto-CoverView ',
		nvme_formatting_drive: "Formatting drive...<br>Please wait for completion message. ",
		downgrading_chromium: "Downgrading chromium...<br>Please wait for completion message. ",
		// Recorder plugin
		recorder_installed: 'Recorder has been installed. ',
		recorder_uninstalled: 'Recorder has been uninstalled. ',
		recorder_plugin_na: 'Recorder plugin is n/a. ',
		recorder_deleted: 'Raw recordings have been deleted. ',
		recorder_tagging: 'Recordings are being tagged...<br>',
		recorder_tagged: 'Tagging is complete.<br>',
		recorder_nofiles: 'No files to tag. '
	};

	// All or reduced
	var notifications = SESSION.json['reduce_notifications'] == '0' ? allNotifications : reducedNotifications;

	// Parse the args
	if (typeof(arg3) == 'number') {
		var duration = arg3;
		var extraMessageText = '';
	} else if (typeof(arg3) == 'string') {
		var extraMessageText = arg3;
		if (arg4 == '' ) {
			var duration = NOTIFY_DURATION_DEFAULT;
		} else {
			var duration = arg4;
		}
	} else if (typeof(arg3) == 'undefined') {
		var duration = NOTIFY_DURATION_DEFAULT;
		var extraMessageText = '';
	}

	// Get message text
	if (typeof(notifications[tag]) == 'undefined') {
		console.log('Unknown notification tag (' + tag + ')');
		return;
	} else {
		var messageText = notifications[tag];
	}

	// Welcome screen configs
	if (tag == 'firstuse_welcome') {
		showCloser = false;
		showSticker = false;
		addClass = 'ui-pnotify-welcome';
	} else {
		showCloser = true;
		showSticker = true;
		addClass = 'ui-pnotify-default';
	}

	// Closing any previous notification
	$('.ui-pnotify-closer').click();

	// Display new notification
	$.pnotify({
		title: title,
		text: messageText + extraMessageText,
		icon: '',
		delay: (duration * 1000),
		opacity: 1.0,
		closer: showCloser,
		sticker: showSticker,
		addclass: addClass,
		history: false
	});
}
