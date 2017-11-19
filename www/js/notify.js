/**
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
 * 2017-11-11 TC moOde 4.0
 *
 */

function notify(cmd, msg, wait) {
    msg = msg || ''; // msg optional

    var map = {
		add: 'Added to playlist',
		clrplay: 'Added after clearing playlist',
		update: 'Update path: ',
		remove: 'Removed from playlist',
		move: 'Playlist items moved',
		savepl: 'Playlist saved',
		needplname: 'Enter a name',
		plnameerror: 'NAS, RADIO and SDCARD cannot be used in playlist name',
		needssid: 'Static IP requres an SSID',
		needdhcp: 'Blank SSID requires DHCP',
		delsavedpl: 'Playlist deleted',
		delstation: 'Radio station deleted',
		addstation: 'Radio station added',
		updstation: 'Radio station updated',
		updclockradio: 'Clock radio updated',
		updcustomize: 'Settings updated',
		themechange: 'Theme color changed',
		liboptionchange: 'Library option changed',
		usbaudioready: 'USB audio ready',
		reboot: 'Rebooting...',
		shutdown: 'Shutting down...'
    };

    if (typeof map[cmd] === undefined) {
        console.error('[notify] Unknown cmd ' + cmd);
    }

    if (typeof wait === undefined) {
        wait = 2000;
    }

    var icon = cmd == 'needplname' || cmd == 'needssid' ? 'icon-info-sign' : 'icon-ok';
    $.pnotify({
        title: map[cmd],
        text: msg,
        icon: icon,
        delay: wait,
        opacity: 0.9,
        history: false
    });
}
