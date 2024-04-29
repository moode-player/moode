/*!
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * tsunamp player ui (C) 2013 Andrea Coiutti & Simone De Gregori
 * http://www.tsunamp.com
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

// arg3: duration or extra message text
// arg4: optional duration if arg3 (extra message text) present
function notify(title, message, arg3, arg4 = '') {
    //console.log('title=' + title + ', message=' + message + ', arg3=' + arg3 + ', arg4=' + arg4);
    var msgQueueAdd = 'Selected items have been added to the Queue. ';
    var msgQueueClearAdd = 'Selected items have been added after clearing the Queue. ';

    var messages = {
        // Queue
		add_item: msgQueueAdd,
        add_item_next: msgQueueAdd,
        add_group: msgQueueAdd,
        add_group_next: msgQueueAdd,
        clear_add_item: msgQueueClearAdd,
        clear_add_group: msgQueueClearAdd,
        queue_item_removed: 'Selected items have been removed. ',
		queue_item_moved: 'Selected items have been moved. ',
        queue_cleared: 'Queue has been cleared. ',
        playqueue_info: 'Queue statistics.<br>',
        // Library
        update_library: 'Updating library... ',
        library_updating: 'Library update is already in progress. ',
        library_loading: 'Library loading... ',
        // Playlist/Queue
        saving_queue: 'Saving Queue. ',
        queue_saved: 'Queue saved. ',
		playlist_name_needed: 'Playlist name is empty. ',
		playlist_name_error: 'Invalid playlist name. ',
        setting_favorites_name: 'Setting Favorites name... ',
        favorites_name_set: 'Favorites name set. ',
        adding_favorite: 'Adding favorite... ',
        favorite_added: 'Favorite added. ',
		no_favorite_to_add: 'Nothing to add. ',
        add_to_playlist: 'Items added. ',
        select_playlist: 'Select a playlist. ',
        // Playlist view                            // TODO: refactor
        creating_playlist: 'Creating new playlist... ', // playlist_create
        updating_playlist: 'Updating playlist... ',     // playlist_update
        new_playlist: 'Playlist created. ',           // playlist_created
		upd_playlist: 'Playlist updated. ',           // playlist_updated
		del_playlist: 'Playlist deleted. ',           // playlist_deleted
        // Radio view
        validation_check: 'Validation check. ',       // station_validation
        creating_station: 'Creating new station... ',   // station_create
        updating_station: 'Updating station... ',       // station_update
		new_station: 'Station created. ',             // station_created
		upd_station: 'Station updated. ',             // station_updated
		del_station: 'Station deleted. ',             // station_deleted
        blank_entries: 'Name or URL is blank. ',
        // Multiroom
        querying_receivers: 'Querying receivers... ',
        no_receivers_found: 'No receivers found. Run receiver Discovery. ',
        run_receiver_discovery: 'Run receiver Discovery. ',
        // CamillaDSP
        cdsp_updating_config: 'Updating configuration... ',
        cdsp_config_updated: 'Configuration updated. ',
        cdsp_config_update_failed: 'Configuraton update failed. ',
        // Renderers
        renderer_disconnect: 'Disconnecting... ',
        renderer_turnoff: 'Turning off... ',
        // Network config
		dhcp_required: 'DHCP is required. ',
        // System
        restart: 'Restarting... ',
		shutdown: 'Shutting down... ',
        reconnect: 'Reconnecting... ',
        mpd_error: '',
        updater: 'An update is available.<br>',
        viewport: 'VIEWPORT<br>',
        debug: 'DEBUG<br>',
        // Players >>
        discovering_players: 'Discovering players...<br>',
        players_action_submit: 'Action submitted: ',
        // Advanced search
        search_fields_empty: 'Search fields are empty. ',
        predefined_filter_invalid: 'Predefined filter invalid. ',
        // Library saved searches
        search_name_blank: 'Name is blank. ',
        // Miscellaneous
        upd_clock_radio: 'Clock radio updated. ',
		settings_updated: 'Settings updated. ',
		gathering_info: 'Gathering info... ',
        installing_plugin: 'Installing plugin... ',
        auto_coverview: 'Auto-CoverView ',
        // Recorder plugin
        recorder_installed: 'Recorder installed. ',
        recorder_uninstalled: 'Recorder uninstalled. ',
        recorder_plugin_na: 'Recorder plugin n/a. ',
        recorder_deleted: 'Raw recordings deleted. ',
        recorder_tagging: 'Recordings being tagged... ',
        recorder_tagged: 'Tagging complete.<br>',
        recorder_nofiles: 'No files to tag. '
    };

    // Parse the args
    // TODO: redo this logic!
    if (typeof(arg3) == 'number') {
        var duration = arg3;
        var extraMessageText = '';
    } else if (typeof(arg3) == 'string') {
        var extraMessageText = arg3;
        if (arg4 != '' ) {
            var duration = arg4;
        } else {
            var duration = NOTIFY_DURATION_DEFAULT;
        }
    } else if (typeof(arg3) == 'undefined') {
        var duration = NOTIFY_DURATION_DEFAULT;
        var extraMessageText = '';
    }

    // Check for unknown message (coding error)
    if (typeof(messages[message]) == 'undefined') {
        messageText = 'Unknown message. Check the source code!'
    } else {
        messageText = messages[message];
    }

    // Display new notification after closing any previous one
    $('.ui-pnotify-closer').click();
    $.pnotify({
        title: title,
        text: messageText + extraMessageText,
        icon: '',
        delay: (duration * 1000),
        opacity: 1.0,
        history: false
    });
}
