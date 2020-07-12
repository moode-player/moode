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
 * 2020-07-09 TC moOde 6.6.0
 *
 */

function notify(cmd, msg, duration) {
    msg = msg || '';

    var map = {
		add: 'Added to playlist',
        clradd: 'Added after playlist cleared',
		clrplay: 'Added after playlist cleared',
        addall: 'Added to playlist',
        playall: 'Added to playlist',
        clrplayall: 'Added after playlist cleared',
        update_library: 'Updating library...',
        library_updating: 'Library update in progress',
        library_loading: 'Library loading...',
		remove: 'Removed from playlist',
		move: 'Playlist items moved',
		savepl: 'Playlist saved',
		needplname: 'Enter a name',
		plnameerror: 'NAS, RADIO and SDCARD cannot be used in the name',
		needssid: 'Static IP requres an SSID',
		needdhcp: 'Blank SSID requires DHCP',
		delsavedpl: 'Playlist deleted',
		newstation: 'New station created',
		updstation: 'Station updated',
        validation_check: 'Validation check',
		blankentries: 'Blank entries are not allowed',
		delstation: 'Station deleted',
		updclockradio: 'Clock radio updated',
		settings_updated: 'Settings updated',
		usbaudioready: 'USB audio ready',
		favset: 'Name has been set',
		favadded: 'Favorite has been added',
		nofavtoadd: 'Nothing to add',
		mpderror: 'MPD error',
		restart: 'Restarting...',
		shutdown: 'Shutting down...',
        viewport: 'Viewport'
    };

    if (typeof map[cmd] === undefined) {
        console.log('notify(): Unknown cmd (' + cmd + ')');
    }

    if (typeof duration == 'undefined') {
        duration = 2000;
    }
	// override and combine title and message on one line
	if (cmd == 'update') {
		msg = 'Path: ' + msg;
	}

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
