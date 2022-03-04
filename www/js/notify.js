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

function notify(cmd, msg, duration) {
    msg = msg || '';

    var map = {
		add_item: 'Added to the Queue',
        add_item_next: 'Added to the Queue',
        add_group: 'Added to the Queue',
        add_group_next: 'Added to the Queue',
        clear_add_item: 'Added after Queue cleared',
        clear_add_group: 'Added after Queue cleared',
		clear_play_item: 'Playing after Queue cleared',
        clear_play_group: 'Playing after Queue cleared',
        clear_libcache: 'Library cache cleared',
        update_library: 'Updating library...',
        library_updating: 'Library update in progress',
        library_loading: 'Library loading...',
        recorder_installed: 'Recorder installed',
        recorder_uninstalled: 'Recorder uninstalled',
        recorder_plugin_na: 'Recorder plugin not available',
        recorder_deleted: 'Recordings deleted',
        recorder_tagging: 'Recordings being tagged...',
        recorder_tagged: 'Tagging complete',
        regen_thumbs: 'Thumbnail resolution updated',
		remove: 'Removed from Queue',
		move: 'Queue items moved',
		savepl: 'Playlist saved',
		needplname: 'Enter a name',
		plnameerror: 'NAS, RADIO and SDCARD cannot be used in the name',
		needssid: 'Static IP requres an SSID',
		needdhcp: 'Blank SSID requires DHCP',
		delsavedpl: 'Playlist deleted',
        creating_station: 'Creating new station',
        updating_station: 'Updating station',
		newstation: 'Station created',
		updstation: 'Station updated',
        validation_check: 'Validation check',
		delstation: 'Station deleted',
		updclockradio: 'Clock radio updated',
		settings_updated: 'Settings updated',
		gathering_info: 'Gathering info...',
        discovering_players: 'Discovering players...',
        querying_receivers: 'Querying receivers...',
        no_receivers_found: 'No receivers found',
        run_receiver_discovery: 'Run receiver Discovery',
		favset: 'Name has been set',
		favadded: 'Favorite has been added',
		nofavtoadd: 'Nothing to add',
		mpderror: 'MPD error',
        update_cdsp: 'Updating configuration...',
        update_cdsp_ok: 'Configuration updated',
        update_cdsp_err: 'Configuration update failed',
        renderer_disconnect: 'Disconnecting...',
        renderer_turnoff: 'Turning off...',
		restart: 'Restarting...',
		shutdown: 'Shutting down...',
        viewport: 'Viewport'
    };

    if (typeof map[cmd] === undefined) {
        console.log('notify(): Unknown cmd (' + cmd + ')');
    }

    if (typeof duration == 'undefined') {
        duration = 2000; // Default 2 seconds
    }
    else if (duration == '3_seconds') {
        duration = 3000;
    }
    else if (duration == '5_seconds') {
        duration = 5000;
    }
    else if (duration == '10_seconds') {
        duration = 10000;
    }
    else if (duration == 'infinite') {
        duration = 86400000; // 24 hours
    }

    // Close previous message if any
    $('.ui-pnotify-closer').click();

    //var icon = cmd == 'needplname' || cmd == 'needssid' ? 'fas fa-info-circle' : 'fas fa-check';
	var icon = '';
    $.pnotify({
        title: map[cmd],
        text: msg,
        icon: icon,
        delay: duration,
        opacity: 1.0,
        history: false
    });
}
