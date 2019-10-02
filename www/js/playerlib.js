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
 * 2019-10-02 TC moOde 6.3.0
 *
 */

// features availability bitmask
const FEAT_RESERVED =    0b0000000000000001; //     1
const FEAT_AIRPLAY =     0b0000000000000010; //     2
const FEAT_MINIDLNA =    0b0000000000000100; //     4
const FEAT_MPDAS =       0b0000000000001000; //     8
const FEAT_SQUEEZELITE = 0b0000000000010000; //    16
const FEAT_UPMPDCLI =    0b0000000000100000; //    32
const FEAT_SQSHCHK =     0b0000000001000000; //    64
const FEAT_GMUSICAPI =   0b0000000010000000; //   128
const FEAT_LOCALUI =     0b0000000100000000; //   256
const FEAT_SOURCESEL =   0b0000001000000000; //   512
const FEAT_UPNPSYNC =    0b0000010000000000; //  1024
const FEAT_SPOTIFY =     0b0000100000000000; //  2048
const FEAT_GPIO =		 0b0001000000000000; //  4096
const FEAT_DJMOUNT =	 0b0010000000000000; //  8192

var UI = {
    knob: null,
    path: '',
    pathr: '',
	restart: '',
	currentFile: 'blank',
	currentHash: 'blank',
	currentSong: 'blank',
	defCover: 'images/default-cover-v6.svg',
	knobPainted: false,
	chipOptions: '',
	hideReconnect: false,
	bgImgChange: false,
	clientIP: '',
    dbPos: [0,0,0,0,0,0,0,0,0,0,0],
    dbEntry: ['', '', '', '', ''],
	// [1]: and [2] used in bootstrap.contextmenu.js
	// [3]: ui row num of song item so highlight can be removed after context menu action
	// [4]: num playlist items for use by delete/move item modals
	dbCmd: '',
	raFolderLevel: [0,0,0,0,0],
	// [0-3]: folder level
	// [4]: master index
	libPos: [-1,-1,-1],
	// [0]: albums list pos
	// [1]: album cover pos
	// [2]: artist list pos
	// special values for [0] and [1]: -1 = full lib displayed, -2 = lib headers clicked, -3 = search performed
	radioPos: -1,
	libAlbum: '',
	mobile: false,
	plModified: -1,
	tagViewCovers: true // controls whether to display covers in tag view, no UI option yet
};

// mpd state and metadata
var MPD = {
	json: 0
};

// session vars (cfg_system table)
var SESSION = {
	json: 0
};
// radio stations (cfg_radio table)
var RADIO = {
	json: 0
};
// themes (cfg_theme table)
var THEME = {
	json: 0
};
// networks (cfg_network table)
var NETWORK = {
	json: 0
};

// Eventually migrate remaining global vars here
var GLOBAL = {
	regExIgnoreArticles: ''
}

// live timeline
var timeSliderMove = false;

// adaptive theme
var themeColor;
var themeBack;
var themeMcolor;
var tempcolor;
var themeOp;
var themeMback;
var adaptColor;
var adaptBack;
var adaptMhalf;
var adaptMcolor;
var adaptMback;
var tempback;
var accentColor;
var abFound;
var showMenuTopW;
var showMenuTopR;
var thumbw = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='13' height='20'><circle fill='%23f0f0f0' cx='6.5' cy='10' r='3.5'/></svg>";
var thumbd = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='13' height='20'><circle fill='%23303030' cx='6.5' cy='10' r='3.5'/></svg>";
var blurrr = CSS.supports('-webkit-backdrop-filter','blur(1px)');

// various flags and things
var libRendered = false; // trigger library load
var radioRendering = false;
var dbFilterResults = [];
var hudTimer = '';
var searchTimer = '';
var showSearchResetPl = false;
var showSearchResetLib = false;
var showSearchResetRa = false;
var showSearchResetPh = false;
var eqGainUpdInterval = '';
var toolbarTimer = '';
var toggleSong = 'blank';
var currentView = 'playback';
var alphabitsFilter;
var lastYIQ = ''; // last yiq value from setColors
//var lastBack = ''; // r50 deprecate

function debugLog(msg)  {
	if (SESSION.json['debuglog'] == '1') {
		console.log(Date.now() + ': ' + msg);
	}
}

// mpd commands
function sendMpdCmd(cmd, async) {
	if (typeof(async) === 'undefined') {async = true;}

	$.ajax({
		type: 'GET',
		url: 'command/?cmd=' + cmd,
		async: async,
		cache: false,
		success: function(data) {
		}
    });
}

// moode.php commands
function sendMoodeCmd(type, cmd, data, async) {
	if (typeof(data) === 'undefined') {data = '';}
	if (typeof(async) === 'undefined') {async = false;}
	//console.log('type', type, 'cmd', cmd, 'data', data, 'async', async);

	var obj;

	$.ajax({
		type: type,
		url: 'command/moode.php?cmd=' + cmd,
		async: async,
		cache: false,
		data: data,
		success: function(result) {
			//debugLog('result=(' + result + ')');
			// always have valid json
			try {
				obj = JSON.parse(result);
			}
			catch (e) {
				obj = e;
			}
		},
		error: function() {
			//debugLog(cmd + ' no data returned');
			obj = false;
		}
	});

	return obj;
}

// specifically for volume
function sendVolCmd(type, cmd, data, async) {
	if (typeof(data) === 'undefined') {data = '';}
	if (typeof(async) === 'undefined') {async = false;}

	var obj;

	$.ajax({
		type: type,
		url: 'command/moode.php?cmd=' + cmd,
		async: async,
		cache: false,
		data: data,
		success: function(result) {
			//debugLog('result=(' + result + ')');
			obj = JSON.parse(result);
			// Omit the try/catch to enable improved volume knob behavior
			// See moode.php case 'updvolume' for explanation
		},
		error: function() {
			//debugLog(cmd + ' no data returned');
			obj = false;
		}
	});

	return obj;
}

// mpd metadata engine
function engineMpd() {
	debugLog('engineMpd: state=(' + MPD.json['state'] + ')');
    $.ajax({
		type: 'GET',
		url: 'engine-mpd.php?state=' + MPD.json['state'],
		async: true,
		cache: false,
		success: function(data) {
			debugLog('engineMpd: success branch: data=(' + data + ')');
			//console.log('engineMpd: success branch: data=(' + data + ')');

			// always have valid json
			try {
				MPD.json = JSON.parse(data);
			}
			catch (e) {
				MPD.json['error'] = e;
			}

			if (typeof(MPD.json['error']) === 'undefined') {
				debugLog('engineMpd: idle_timeout_event=(' + MPD.json['idle_timeout_event'] + ')', 'state', MPD.json['state']);
				//console.log('engineMpd: idle_timeout_event=(' + MPD.json['idle_timeout_event'] + ')', 'state', MPD.json['state']);

				if (UI.hideReconnect === true) {
					hideReconnect();
				}
				// mpd restarted by udev, watchdog, manually via cli, etc
				// udev rule /etc/udev/rules.d/10-usb-audiodevice.rules
				if (MPD.json['idle_timeout_event'] === '') {
					// nop
				}
				// database update
				if (MPD.json['idle_timeout_event'] == 'changed: update') {
					if (typeof(MPD.json['updating_db']) != 'undefined') {
						$('.busy-spinner').show();
					}
					else {
						$('.busy-spinner').hide();
					}
				}
				// render volume
				else if (MPD.json['idle_timeout_event'] == 'changed: mixer') {
					renderUIVol();
				}
				// when last item in playlist finishes just update a few things
				else if (MPD.json['idle_timeout_event'] == 'changed: player' && MPD.json['file'] == null) {
					resetPlayCtls();
				}
				// render full UI
				else {
					renderUI();
				}

				engineMpd();
			}
			// error of some sort
			else {
				debugLog('engineMpd: success branch: error=(' + MPD.json['error'] + '), module=(' + MPD.json['module'] + ')');

				// JSON parse errors @ohinckel https: //github.com/moode-player/moode/pull/14/files
				if (typeof(MPD.json['error']) == 'object') {
                    var errorCode = typeof(MPD.json['error']['code']) === 'undefined' ? '' : ' (' + MPD.json['error']['code'] + ')';
                    // This particular EOF error occurs when client is simply trying to reconnect
                    if (MPD.json['error']['message'] != 'JSON Parse error: Unexpected EOF') {
                        notify('mpderror', MPD.json['error']['message'] + errorCode);
                    }
				}
				// MPD output --> Bluetooth but no actual BT connection
				else if (MPD.json['error'] == 'Failed to open "ALSA bluetooth" (alsa); Failed to open ALSA device "btstream": No such device') {
					notify('mpderror', 'Failed to open ALSA bluetooth output, no such device or connection');
				}
				// client connects before mpd started by worker ?
				else if (MPD.json['error'] == 'SyntaxError: JSON Parse error: Unexpected EOF') {
					notify('mpderror', 'JSON Parse error: Unexpected EOF');
				}
				// mpd bug may have been fixed in 0.20.20 ?
				else if (MPD.json['error'] == 'Not seekable') {
					// nop
				}
				// other network or MPD errors
				else {
					notify('mpderror', MPD.json['error']);
				}

				renderUI();

				setTimeout(function() {
					engineMpd();
				}, 3000);
			}
		},
        // network connection interrupted or client network stack timeout
		error: function(data) {
			debugLog('engineMpd: error branch: data=(' + JSON.stringify(data) + ')');

			setTimeout(function() {
				if (data['statusText'] == 'error' && data['readyState'] == 0) {
			        renderReconnect();
				}
				MPD.json['state'] = 'reconnect';
				engineMpd();
			}, 3000);
		}
    });
}

// mpd metadata engine, lite version for scripts-configs
function engineMpdLite() {
	debugLog('engineMpdLite: state=(' + MPD.json['state'] + ')');
    $.ajax({
		type: 'GET',
		url: 'engine-mpd.php?state=' + MPD.json['state'],
		async: true,
		cache: false,
		success: function(data) {
			debugLog('engineMpdLite: success branch: data=(' + data + ')');
			//console.log('engineMpdLite: success branch: data=(' + data + ')');

			// always have valid json
			try {
				MPD.json = JSON.parse(data);
			}
			catch (e) {
				MPD.json['error'] = e;
			}

			if (typeof(MPD.json['error']) === 'undefined') {
				debugLog('engineMpdLite: idle_timeout_event=(' + MPD.json['idle_timeout_event'] + ')');
				//console.log('engineMpdLite: idle_timeout_event=(' + MPD.json['idle_timeout_event'] + ')', 'state', MPD.json['state']);

				if (UI.hideReconnect === true) {
					hideReconnect();
				}
				// database update
				if (typeof(MPD.json['updating_db']) != 'undefined') {
					$('.busy-spinner').show();
				}
				else {
					$('.busy-spinner').hide();
				}

				engineMpdLite();

			}
			// error of some sort
			else {
				setTimeout(function() {
					// client connects before mpd started by worker, various other network issues
					debugLog('engineMpd: success branch: error=(' + MPD.json['error'] + '), module=(' + MPD.json['module'] + ')');
					engineMpdLite();
				}, 3000);
			}
		},
        // network connection interrupted or client network stack timeout
		error: function(data) {
			debugLog('engineMpdLite: error branch: data=(' + JSON.stringify(data) + ')');
            //console.log('engineMpdLite: error branch: data=(' + JSON.stringify(data) + ')');

			setTimeout(function() {
				if (data['statusText'] == 'error' && data['readyState'] == 0) {
			        renderReconnect();
				}
				MPD.json['state'] = 'reconnect';
				engineMpdLite();
			}, 3000);
		}
    });
}

// Command engine
function engineCmd() {
	var cmd;

    $.ajax({
		type: 'GET',
		url: 'engine-cmd.php',
		async: true,
		cache: false,
		success: function(data) {
			//console.log('engineCmd: success branch: data=(' + data + ')');

			cmd = JSON.parse(data).split(',');

			if (cmd[0] == 'btactive1' || cmd[0] == 'btactive0') { // NOTE: cmd[1] is the connected device name
				inpSrcIndicator(cmd[0], '<a href="blu-config.php">Bluetooth Active</a><br><span>' + cmd[1] + '</span>');
			}
			else if (cmd[0] == 'aplactive1' || cmd[0] == 'aplactive0') {
				inpSrcIndicator(cmd[0], 'Airplay Active' + '<br><span class="disconnect-renderer" data-job="airplaysvc">disconnect</span>');
			}
			else if (cmd[0] == 'spotactive1' || cmd[0] == 'spotactive0') {
				inpSrcIndicator(cmd[0], 'Spotify Active' + '<br><span class="disconnect-renderer" data-job="spotifysvc">disconnect</span>');
			}
			else if (cmd[0] == 'slactive1' || cmd[0] == 'slactive0') {
				inpSrcIndicator(cmd[0], 'Squeezelite Active' + '<br><span class="disconnect-renderer" data-job="slsvc">turn off</span>');
			}
			else if (cmd[0] == 'inpactive1' || cmd[0] == 'inpactive0') { // NOTE: cmd[1] is the input source name
				inpSrcIndicator(cmd[0], '<a href="sel-config.php">' + cmd[1] + ' Input Active</a>' + '<br><span><button class="btn volume-popup" data-toggle="modal"><i class="fal fa-volume-up"></i></button><span id="inpsrc-preamp-volume"></span></span>');
			}
			else if (cmd[0] == 'scnactive1') {
				screenSaver(cmd[0]);
			}

			if (cmd[0] == 'dbupd_done') {
				$('.busy-spinner').hide();
			}

			engineCmd();
		},

		error: function(data) {
			//console.log('engineCmd: error branch: data=(' + JSON.stringify(data) + ')');
			setTimeout(function() {
				engineCmd();
			}, 3000);
		}
    });
}

function inpSrcIndicator(cmd, msgText) {
	UI.currentFile = 'blank';

	if (cmd.slice(-1) == '1') {
		$('#menu-top, #menu-bottom, .btnlist-top, .alphabits').hide();
		$('.viewswitch').css('display', 'none');

		$('#inpsrc-indicator').css('display', 'block');
		$('#inpsrc-msg').html(msgText);

		var preampVolume = SESSION.json['mpdmixer'] == 'disabled' ? '0dB' : SESSION.json['volknob'];
		$('#inpsrc-preamp-volume').text(preampVolume);
	}
	else {
		if ($('#screen-saver').css('display') == 'none') {
            $('#menu-top, .btnlist-top').show();
            $('#index-artists.alphabits').show();
            $('#index-albums.alphabits').show();

            if (SESSION.json['show_genres'] == 'Yes') {
                $('#index-genres.alphabits').show();
            }

			if (currentView.indexOf('playback') == -1) {
				$('#menu-bottom').show();
				$('.viewswitch').css('display', 'flex');
			}
			else {
				$('#menu-bottom').hide();
			}
		}

		$('#inpsrc-msg').html('');
		$('#inpsrc-indicator').css('display', '');
	}
}

// show/hide screen saver
function screenSaver(cmd) {
	if ($('#inpsrc-indicator').css('display') == 'block') {
		return;  // exit if input source is active
	}
	else if (cmd.slice(-1) == '1') {
		$('#playback-panel, #folder-panel, #library-panel, #radio-panel').addClass('hidden');
		$('#menu-bottom, #menu-top').hide();
		$('.viewswitch').css('display', 'none');
		$('#ss-coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'alt="Cover art not found"' + '>');
		$('#screen-saver').show()
	}
}

// reconnect/reboot/poweroff
function renderReconnect() {
	debugLog('renderReconnect: UI.restart=(' + UI.restart + ')');

	if (UI.restart == 'reboot') {
		$('#reboot').show();
	}
	else if (UI.restart == 'poweroff') {
		$('#poweroff').show();
	}
	else {
		$('#reconnect').show();
	}

	$('#countdown-display, #m-countdown, #playbar-countdown').countdown('pause');

	window.clearInterval(UI.knob);
	UI.hideReconnect = true;
}

function hideReconnect() {
	//console.log('hideReconnect: (' + UI.hideReconnect + ')');
	$('#reconnect, #reboot, #poweroff').hide();
	UI.hideReconnect = false;
}

// disable volume knob for mpdmixer == disabled (0dB)
function disableVolKnob() {
	SESSION.json['volmute'] == '1';
	if (UI.mobile) {
		$('#volume-2').attr('data-readOnly', 'true');
		$('#volumedn-2, #volumeup-2').prop('disabled', true);
		$('#mvol-progress').css('width', '100%');
		$('.repeat').show();
		//$('#context-menu-consume').hide();
	}
	else {
		$('.repeat').hide();
		$('#ssvolume').attr('data-readOnly', 'true');
		$('#volumedn, #volumeup, #ssvolup, #ssvoldn').prop('disabled', true);
	}

	$('.volume-popup').hide();
	//$('#playbar-consume').show();

	$('.volume-display, #ssvolume, #volumeup, #volumedn').css('opacity', '.3');
	$('.volume-display, #ssvolume, #inpsrc-preamp-volume').text('0dB');
	$('.volume-display, #ssvolume').css('cursor', 'unset');
}

// when last item in laylist finishes just update a few things, called from engineCmd()
function resetPlayCtls() {
	//console.log('resetPlayCtls()');
	$('#total, #m-total, #playbar-total, #playbar-mtotal').html(updTimeKnob('0'));
	$('.play i').removeClass('fas fa-pause').addClass('fas fa-play');

	$('.playlist li.active ').removeClass('active');
	$('.ss-playlist li.active').removeClass('active');

	refreshTimeKnob();
    refreshTimer(0, 0, MPD.json['state']);
	$('#countdown-display, #m-countdown, #playbar-countdown, #playbar-mcount').html('00:00');
	$('#m-radio').hide();
}

// update UI volume and mute only
function renderUIVol() {
	debugLog('renderUIVol');
	// load session vars (required for multi-client)
	var result = sendMoodeCmd('GET', 'readcfgsystem');
	if (result !== false) {
		SESSION.json = result;
	}

	// disabled volume, 0dB output
	if (SESSION.json['mpdmixer'] == 'disabled') {
		disableVolKnob();
	}
	// software or hardware volume
	else {
		// sync vol and mute to UPnP controller
		if (SESSION.json['feat_bitmask'] & FEAT_UPNPSYNC) {
			// no renderers active
			if (SESSION.json['btactive'] == '0' && SESSION.json['airplayactv'] == '0' && SESSION.json['slsvc'] == '0') {
				if ((SESSION.json['volknob'] != MPD.json['volume']) && SESSION.json['volmute'] == '0') {
					SESSION.json['volknob'] = MPD.json['volume']
					var result = sendMoodeCmd('POST', 'updcfgsystem', {'volknob': SESSION.json['volknob']});
				}
			}
		}

		// update volume knob, ss volume
		$('#volume').val(SESSION.json['volknob']).trigger('change');
		$('.volume-display, #ssvolume, #inpsrc-preamp-volume').text(SESSION.json['volknob']);

		// update mobile volume
		$('#volume-2').val(SESSION.json['volknob']).trigger('change');
		$('#mvol-progress').css('width', SESSION.json['volknob'] + '%');

	   	// update mute and ss mute state
		if (SESSION.json['volmute'] == '1') {
			$('.volume-display').css('opacity', '.3');
			$('.volume-display, #ssvolume, #inpsrc-preamp-volume').text('mute');
		}
		else {
			$('.volume-display').css('opacity', '');
			$('.volume-display').text(SESSION.json['volknob']);
		}
	}
}

// update UI with mpd metadata
function renderUI() {
	debugLog('renderUI');
	var searchStr, searchEngine;

	// load session vars (required for multi-client)
	var result = sendMoodeCmd('GET', 'readcfgsystem');
	if (result !== false) {
		SESSION.json = result;
	}

	// Highlight track in Library
    if (MPD.json['artist'] != 'Radio station' && $('#songsList li').length > 0) {
        //console.log(filteredSongs.length, $('#songsList li').length);
		for (i = 0; i < filteredSongs.length; i++) {
			if (filteredSongs[i].title == MPD.json['title']) {
				$('#songsList .lib-entry-song .songtrack').removeClass('lib-track-highlight');
				$('#lib-song-' + (i + 1) + ' .lib-entry-song .songtrack').addClass('lib-track-highlight');
				break;
			}
		}
        //customScroll('tracks', i, 200);
	}

	// disabled volume, 0dB output
	if (SESSION.json['mpdmixer'] == 'disabled') {
		disableVolKnob();
	}
	// software or hardware volume
	else {
		// update volume knob, ss volume
		$('#volume').val(SESSION.json['volknob']).trigger('change');
		$('.volume-display, #ssvolume, #inpsrc-preamp-volume').text(SESSION.json['volknob']);
		$('#volume-2').val(SESSION.json['volknob']).trigger('change');
		$('#mvol-progress').css('width', SESSION.json['volknob'] + '%');

	   	// update mute state
		if (SESSION.json['volmute'] == '1') {
			$('.volume-display').css('opacity', '.3');
			$('.volume-display, #ssvolume, #inpsrc-preamp-volume').text('mute');
		}
		else {
			$('.volume-display').css('opacity', '');
			$('.volume-display, #ssvolume').text(SESSION.json['volknob']);
		}
	}

	// playback controls, playlist highlight
    if (MPD.json['state'] == 'play') {
		$('.play i').removeClass('fas fa-play').addClass('fas fa-pause');
		$('.playlist li.active').removeClass('active');
        $('.playlist li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('active');
		$('.ss-playlist li.active').removeClass('active');
        $('.ss-playlist li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('active');
    }
	else if (MPD.json['state'] == 'pause' || MPD.json['state'] == 'stop') {
		$('.play i').removeClass('fas fa-pause').addClass('fas fa-play');
    }
	$('#total').html(updTimeKnob(MPD.json['time']));
	$('#m-total, #playbar-total').html(updTimeSlider(MPD.json['time']));
	$('#playbar-mtotal').html(updTimeSlider(MPD.json['time']) == '' ? '' : '&nbsp;/&nbsp;' + updTimeSlider(MPD.json['time']));

	//console.log('CUR: ' + UI.currentHash);
	//console.log('NEW: ' + MPD.json['cover_art_hash']);
	// compare new to current to prevent unnecessary image reloads
	if (MPD.json['file'] !== UI.currentFile && MPD.json['cover_art_hash'] !== UI.currentHash) {
		debugLog(MPD.json['coverurl']);

		/* TEST
		if (MPD.json['coverurl'].indexOf('default-cover-v6') != -1) {
			MPD.json['coverurl'] = getComputedStyle(document.body).getPropertyValue('--defcover');
			console.log('MPD.json[coverurl]=', MPD.json['coverurl']);
		}*/

		$('#coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'data-adaptive-background="1" alt="Cover art not found"' + '>');
		$('#playbar-cover').html('<img src="' + MPD.json['coverurl'] + '">');
		// cover backdrop or bgimage
		if (SESSION.json['cover_backdrop'] == 'Yes' && MPD.json['coverurl'].indexOf('default-cover-v6') === -1) {
			$('#cover-backdrop').html('<img class="ss-backdrop" ' + 'src="' + MPD.json['coverurl'] + '">');
			$('#cover-backdrop').css('filter', 'blur(' + SESSION.json['cover_blur'] + ')');
			$('#cover-backdrop').css('transform', 'scale(' + SESSION.json['cover_scale'] + ')');
		}
		else if (SESSION.json['bgimage'] != '') {
			$('#cover-backdrop').html('<img class="ss-backdrop" ' + 'src="' + SESSION.json['bgimage'] + '">');
			$('#cover-backdrop').css('filter', 'blur(0px)');
			$('#cover-backdrop').css('transform', 'scale(1.0)');
		}
		// screen saver
		$('#ss-backdrop').html('<img class="ss-backdrop" ' + 'src="' + MPD.json['coverurl'] + '">');
		if ($('#screen-saver').css('display') == 'block') {
			$('#ss-coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'alt="Cover art not found"' + '>');
		}

		// adaptive UI theme engine
		if (MPD.json['coverurl'].indexOf('default-cover-v6') === -1) {
			$.adaptiveBackground.run();
		}
		else {
			setColors();
		}
	}

	// extra metadata
	if (SESSION.json['xtagdisp'] == 'Yes') {
		$('#extratags, #ss-extratags').text('');
		if (MPD.json['artist'] == 'Radio station') {
			var extraTags = MPD.json['bitrate'] ? MPD.json['bitrate'] : 'VBR';
		}
		else {
			var extraTags = MPD.json['track'] ? 'Track ' + MPD.json['track'] : '';
			extraTags += MPD.json['disc'] ? (MPD.json['disc'] != 'Disc tag missing' ? '&nbsp;&bull; Disc ' + MPD.json['disc'] : '') : '';
			extraTags += MPD.json['date'] ? '&nbsp;&bull; Year ' + (MPD.json['date']).slice(0,4) : '';
			extraTags += MPD.json['composer'] ? '&nbsp;&bull; ' + MPD.json['composer'] : '';
			extraTags += MPD.json['encoded'] ? (MPD.json['encoded'] != 'Unknown' ? '&nbsp;&bull;&nbsp;' + MPD.json['encoded'] : '') : ''; // rate, format, see getEncodedAt()
			//extraTags += MPD.json['encoded'] ? (MPD.json['encoded'] != 'Unknown' ? '&nbsp&bull; ' + MPD.json['encoded'] : '') : ''; // rate only
		}
		$('#extratags, #ss-extratags').html(extraTags);
	}
	else {
		$('#extratags, #ss-extratags').html('');
	}

	// default metadata
	if (MPD.json['album']) {
		$('#currentalbum, #ss-currentalbum, #playbar-currentalbum').html((MPD.json['artist'] == 'Radio station' ? '' : MPD.json['artist'] + ' - ') + MPD.json['album']);
	}
	else {
		$('#currentalbum, #ss-currentalbum, #playbar-currentalbum').html('');
	}

	// song title
	if (MPD.json['title'] === 'Streaming source' || MPD.json['coverurl'] === UI.defCover || UI.mobile) {
		$('#currentsong').html(MPD.json['title']);
	}
	// add search url, see corresponding code in renderPlaylist()
	else {
		$('#currentsong').html(genSearchUrl(MPD.json['artist'], MPD.json['title'], MPD.json['album']));
	}
	$('#ss-currentsong, #playbar-currentsong').html(MPD.json['title']);

    // scrollto if song change
    if (MPD.json['file'] !== UI.currentFile) {
        countdownRestart(0);
        if ($('#playback-panel').hasClass('active')) {
	        customScroll('pl', parseInt(MPD.json['song']));
        }
        if ($('#ss-hud').css('display') == 'block') {
	        customScroll('ss-pl', parseInt(MPD.json['song']));
        }
    }

	// store toggle song
	if (UI.currentSong != MPD.json['song']) {
		toggleSong = UI.currentSong == 'blank' ? SESSION.json['toggle_song'] : UI.currentSong;
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'toggle_song': toggleSong}, true);
	}

	// set current = new for next cycle
	UI.currentFile = MPD.json['file'];
	UI.currentHash = MPD.json['cover_art_hash'];
	UI.currentSong = MPD.json['song'];

	// toggle buttons
	if (MPD.json['consume'] === '1') {
		$('.consume').addClass('btn-primary');
		$('#menu-check-consume').show();
	}
	else {
		$('.consume').removeClass('btn-primary');
		$('#menu-check-consume').hide();
	}
    if (MPD.json['repeat'] === '1') {
		$('.repeat').addClass('btn-primary')
		$('#menu-check-repeat').show();
	}
	else {
		$('.repeat').removeClass('btn-primary');
		$('#menu-check-repeat').hide();
	}
    if (MPD.json['single'] === '1') {
		$('.single').addClass('btn-primary')
		$('#menu-check-single').show();
	}
	else {
		$('.single').removeClass('btn-primary');
		$('#menu-check-single').hide();
	}
	if (SESSION.json['ashufflesvc'] === '1') {
		if (SESSION.json['ashuffle'] ==='1') {
			$('.random, .consume').addClass('btn-primary')
			$('#songsList .lib-entry-song .songtrack').removeClass('lib-track-highlight');
		}
		else {
			$('.random').removeClass('btn-primary');
		}
	}
	else {
	    MPD.json['random'] === '1' ? $('.random').addClass('btn-primary') : $('.random').removeClass('btn-primary');
	}

	// time knob and timeline
	// count up or down, radio stations always have song time = 0
	if (SESSION.json['timecountup'] === '1' || parseInt(MPD.json['time']) === 0) {
		refreshTimer(parseInt(MPD.json['elapsed']), parseInt(MPD.json['time']), MPD.json['state']);
	}
	else {
		refreshTimer(parseInt(MPD.json['time'] - parseInt(MPD.json['elapsed'])), 0, MPD.json['state']);
	}
	// set flag if song file and knob < 100% painted
	// NOTE radio station time will always be 0
	if (parseInt(MPD.json['time']) !== 0) {
		UI.knobPainted = false;
	}
	// update knob if paint < 100% complete
	if ((MPD.json['state'] === 'play' || MPD.json['state'] === 'pause') && UI.knobPainted === false) {
		refreshTimeKnob();
	}
	// clear knob when stop
	if (MPD.json['state'] === 'stop') {
		refreshTimeKnob();
	}

	// for radio stations always update playlist
	if (MPD.json['artist'] == 'Radio station') {
		renderPlaylist();
	}
	// for song files only update if MPD playlilst seq
	else if (MPD.json['playlist'] != UI.plModified) {
		renderPlaylist();
	}

	// ensure renderer overlays get applied in case mpd ui updates get there first after browser refresh
	// bluetooth renderer
	if (SESSION.json['btactive'] == '1') {
		inpSrcIndicator('btactive1', '<a href="blu-config.php">Bluetooth Active</a>');
	}
	// airplay renderer
	if (SESSION.json['airplayactv'] == '1') {
		inpSrcIndicator('aplactive1', 'Airplay Active' + '<br><span class="disconnect-renderer" data-job="airplaysvc">disconnect</span>');
	}
	// spotify renderer
	if (SESSION.json['spotactive'] == '1') {
		inpSrcIndicator('spotactive1', 'Spotify Active' + '<br><span class="disconnect-renderer" data-job="spotifysvc">disconnect</span>');
	}
	// squeezelite renderer
	if (SESSION.json['slactive'] == '1') {
		inpSrcIndicator('slactive1', 'Squeezelite Active' + '<br><span class="disconnect-renderer" data-job="slsvc">turn off</span>');
	}
	// Input source
	if (SESSION.json['inpactive'] == '1') {
		inpSrcIndicator('inpactive1', '<a href="sel-config.php">' + SESSION.json['audioin'] + ' Input Active</a>' + '<br><span><button class="btn volume-popup" data-toggle="modal"><i class="fal fa-volume-up"></i></button><span id="inpsrc-preamp-volume"></span></span>');
	}

	// database update
	if (typeof(MPD.json['updating_db']) != 'undefined') {
		$('.busy-spinner').show();
	}
	else {
		$('.busy-spinner').hide();
	}
}

// generate search url
function genSearchUrl (artist, title, album) {
	var searchEngine = 'http://www.google.com/search?q=';

	if (typeof(artist) === 'undefined' || artist === 'Radio station') {
		var searchStr = title.replace(/-/g, ' ');
		searchStr = searchStr.replace(/&/g, ' ');
		searchStr = searchStr.replace(/\s+/g, '+');
	}
	else {
		searchStr = artist + '+' + album;
	}

	return '<a id="coverart-link" href=' + '"' + searchEngine + searchStr + '"' + ' target="_blank">'+ title + '</a>';
}

function renderPlaylist() {
	debugLog('renderPlaylist');

    $.getJSON('command/moode.php?cmd=playlist', function(data) {
		var output = '';

		//console.log(MPD.json['playlist'], UI.plModified)
		UI.plModified = MPD.json['playlist'];

        // save for use in delete/move modals
        UI.dbEntry[4] = typeof(data.length) === 'undefined' ? 0 : data.length;

		// format playlist items
		if (data) {
			for (i = 0; i < data.length; i++) {
				//console.log(data[i].file);

				// item active state
				if (i == parseInt(MPD.json['song'])) {
					output += '<li id="pl-' + (i + 1) + '" class="active">';
				}
				else {
					output += '<li id="pl-' + (i + 1) + '">';
				}
				// action menu
				output += '<div class="pl-action" data-toggle="context" data-target="#context-menu-playlist-item">' + (typeof(data[i].Time) == 'undefined' ? '<em class="songtime"></em>' : ' <em class="songtime">' + formatSongTime(data[i].Time) + '</em>') + '<br><i class="fas fa-ellipsis-h"></i></div>';

				// itunes aac file
				if (typeof(data[i].Name) !== 'undefined' && data[i].file.substr(data[i].file.lastIndexOf('.') + 1).toLowerCase() == 'm4a') {
					// line 1 title
					output += '<div class="pl-entry"><span class="pll1">';
					output += data[i].Name + '</span>';
					// line 2 artist, album
					output += ' <span class="pll2">'; // for clock radio
					output += (typeof(data[i].Artist) === 'undefined') ? 'Unknown artist' : data[i].Artist;
					output += ' - ';
					output += (typeof(data[i].Album) === 'undefined') ?  'Unknown album' : data[i].Album;
				}
				// radio station
				else if (typeof(data[i].Name) !== 'undefined' || (data[i].file.substr(0, 4) == 'http' && typeof(data[i].Artist) === 'undefined')) {
					// line 1 title
					output += '<div class="pl-entry">';

					// use custom name for particular station
					if (typeof(data[i].Title) === 'undefined' || data[i].Title.trim() == '' || data[i].file == 'http://stream.radioactive.fm:8000/ractive') {
						output += '<span class="pll1">Streaming source</span>';
					}
					else {
						output += '<span class="pll1">' + data[i].Title + '</span>';
						if (i == parseInt(MPD.json['song'])) { // active
							// update in case MPD did not get Title tag at initial play
							if (data[i].Title.substr(0, 4) === 'http' || MPD.json['coverurl'] === UI.defCover || UI.mobile) {
								$('#currentsong').html(data[i].Title);
							}
							// add search url, see corresponding code in renderUI()
							else {
								$('#currentsong').html(genSearchUrl(data[i].Artist, data[i].Title, data[i].Album));
							}
							$('#ss-currentsong, #playbar-currentsong').html(data[i].Title);
						}
					}

					// line 2, station name
					output += ' <span class="pll2">';
					output += '<i class="fas fa-microphone"></i> ';

					if (typeof(RADIO.json[data[i].file]) === 'undefined') {
						var name = typeof(data[i].Name) === 'undefined' ? 'Radio station' : data[i].Name;
						output += name;
						if (i == parseInt(MPD.json['song'])) { // active
							$('#playbar-currentalbum').html(name);
						}
					}
					else {
						output += RADIO.json[data[i].file]['name'];
						if (i == parseInt(MPD.json['song'])) { // active
							$('#playbar-currentalbum').html(RADIO.json[data[i].file]['name']);
						}
					}
				}
				// song file or upnp url
				else {
					// line 1 title
					output += '<div class="pl-entry"><span class="pll1">';
					if (typeof(data[i].Title) === 'undefined') { // use file name
						var pos = data[i].file.lastIndexOf('.');

						if (pos == -1) {
							output += data[i].file; // some upnp url's have no file ext
						}
						else {
							var filename = data[i].file.slice(0, pos);
							pos = filename.lastIndexOf('/');
							output += filename.slice(pos + 1); // song filename (strip .ext)
						}
						output += '</span>';
					}
					// use title
					else {
						output += data[i].Title + '</span>';
					}
					// line 2 artist, album
					output += ' <span class="pll2">';
					output += (typeof(data[i].Artist) === 'undefined') ? 'Unknown artist' : data[i].Artist;
					output += ' - ';
					output += (typeof(data[i].Album) === 'undefined') ?  'Unknown album' : data[i].Album;
				}

				output += '</span></div></li>';
            } // end loop
        }

		// render playlist
		$('ul.playlist, ul.ss-playlist').html(output);
	});
}

// MPD commands for database, playlist, radio stations, saved playlists
function mpdDbCmd(cmd, path) {
	//console.log(cmd, path);
	var cmds = ['add', 'play', 'clradd', 'clrplay', 'addall', 'playall', 'clrplayall', 'updmpddb'];
	UI.dbCmd = cmd;

	if (cmds.indexOf(cmd) != -1 ) {
		$.post('command/moode.php?cmd=' + cmd, {'path': path}, function(path) {}, 'json');
	}
	else if (cmd == 'lsinfo' || cmd == 'listsavedpl') {
		$.post('command/moode.php?cmd=' + cmd, {'path': path}, function(data) {renderBrowse(data, path);}, 'json');
	}
	else if (cmd == 'lsinfo_radio' && radioRendering == false) {
		$.post('command/moode.php?cmd=' + 'lsinfo', {'path': path}, function(data) {renderRadio(data, path);}, 'json');
	}
	else if (cmd == 'delsavedpl') {
		$.post('command/moode.php?cmd=' + cmd, {'path': path}, function(data) {}, 'json');
		$.post('command/moode.php?cmd=lsinfo', {'path': ''}, function(data) {renderBrowse(data, '');}, 'json');
	}
	else if (cmd == 'newstation' || cmd == 'updstation') {
		var arg = path.split('\n');
		var result = sendMoodeCmd('POST', cmd, {'path': arg[0], 'url': arg[1]});
		$.post('command/moode.php?cmd=lsinfo', { 'path': 'RADIO' }, function(data) {renderBrowse(data, 'RADIO');}, 'json');
	}
	else if (cmd == 'delstation') {
		var result = sendMoodeCmd('POST', cmd, {'path': path});
		$.post('command/moode.php?cmd=lsinfo', {'path': 'RADIO'}, function(data) {renderBrowse(data, 'RADIO');}, 'json');
	}
}

// render browse panel with optimized sort
function renderBrowse(data, path, searchstr) {
	//console.log('renderBowse(): path=(' + path + ')');
	//console.log('UI.dbPos[10]= ' + UI.dbPos[10].toString());
	//console.log('UI.dbPos[' + UI.dbPos[10].toString() + ']= ' + UI.dbPos[UI.dbPos[10]].toString());
	UI.path = path;

	// separate out dirs, playlists, files
	var dirs = [];
	var playlists =[];
	var files = [];
	var j = 0, k = 0, l = 0;
	for (var i = 0; i < data.length; i++) {
		if (typeof(data[i].directory) != 'undefined') {
			dirs[j] = data[i];
			j = j + 1;
		}
		else if (typeof(data[i].playlist) != 'undefined') {
			playlists[k] = data[i];
			k = k + 1;
		}
		else {
			files[l] = data[i];
			l = l + 1;
		}
	}

	// sort directories, playlists and files
	try {
		// natural ordering
		var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
		if (typeof(dirs[0]) != 'undefined') {
			dirs.sort(function(a, b) {
				a = a.directory.lastIndexOf('/') == -1 ? removeArticles(a.directory) : removeArticles(a.directory.substr(a.directory.lastIndexOf('/') + 1));
				b = b.directory.lastIndexOf('/') == -1 ? removeArticles(b.directory) : removeArticles(b.directory.substr(b.directory.lastIndexOf('/') + 1));
				return collator.compare(a, b);
			});
		}
		if (typeof(playlists[0]) != 'undefined') {
			playlists.sort(function(a, b) {
				return collator.compare(removeArticles(a.playlist), removeArticles(b.playlist));
			});
		}
		if (typeof(files[0]) != 'undefined' && files[0].file.indexOf('RADIO') != -1) {
			files.sort(function(a, b) {
				return collator.compare(removeArticles(a.file.substring(6)), removeArticles(b.file.substring(6)));
			});
		}
	}
	catch (e) {
		// fallback to default ordering
		if (typeof(dirs[0]) != 'undefined') {
			dirs.sort(function(a, b) {
				a = a.directory.lastIndexOf('/') == -1 ? removeArticles(a.directory.toLowerCase()) : removeArticles(a.directory.substr(a.directory.lastIndexOf('/') + 1).toLowerCase());
				b = b.directory.lastIndexOf('/') == -1 ? removeArticles(b.directory.toLowerCase()) : removeArticles(b.directory.substr(b.directory.lastIndexOf('/') + 1).toLowerCase());
				return a > b ? 1 : (a < b ? -1 : 0);
			});
		}
		if (typeof(playlists[0]) != 'undefined') {
			playlists.sort(function(a, b) {
				a = removeArticles(a.playlist.toLowerCase());
				b = removeArticles(b.playlist.toLowerCase());
				return a > b ? 1 : (a < b ? -1 : 0);
			});
		}
		if (typeof(files[0]) != 'undefined' && files[0].file.indexOf('RADIO') != -1) {
			files.sort(function(a, b) {
				a = removeArticles(a.file.substring(6).toLowerCase());
				b = removeArticles(b.file.substring(6).toLowerCase());
				return a > b ? 1 : (a < b ? -1 : 0);
			});
		}
	}

	// merge
	data = dirs.concat(playlists).concat(files);

	// format search tally, clear results and search field when back btn
	var dbList = $('ul.database');
	dbList.html('');
	$('#db-search-results').html('');
	$('#db-search-results').hide();
	$('#db-search-results').css('font-weight', 'normal');

	if (searchstr) {
		var result = (data.length) ? data.length : '0';
		var s = (data.length == 1) ? '' : 's';
		var text = result + ' item' + s;
		$('#db-search-results').show();
		$('#db-search-results').html('<a href="#notarget" data-toggle="context" data-target="#context-menu-db-search-results">' + text +'</a>');
	}

	// format and output
	var output = '';
	for (i = 0; i < data.length; i++) {
		output = formatBrowseData(data, path, i, 'browse_panel');
	 	dbList.append(output);
	}

	// scroll and highlight
	customScroll('db', UI.dbPos[UI.dbPos[10]], 100);
	if (path != '' && UI.dbPos[UI.dbPos[10]] > 1) { // don't highlight if at root or only 1 item in list
		$('#db-' + UI.dbPos[UI.dbPos[10]].toString()).addClass('active');
	}
}

// render radio panel
function renderRadio(data, path) {
	//console.log('renderRadio(): path=(' + path + ')');
	radioRendering = true;
	UI.pathr = path;

	// separate out dirs, files
	var dirs = [];
	var files = [];
	var j = 0, k = 0;
	for (var i = 0; i < data.length; i++) {
		if (typeof(data[i].directory) != 'undefined') {
			dirs[j] = data[i];
			j = j + 1;
		}
		else {
			files[k] = data[i];
			k = k + 1;
		}
	}

	// sort directories and files
	try {
		// natural ordering
		var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
		if (typeof(dirs[0]) != 'undefined') {
			dirs.sort(function(a, b) {
				return collator.compare(removeArticles(a.directory.substring(6)), removeArticles(b.directory.substring(6)));
			});
		}
		if (typeof(files[0]) != 'undefined') {
			files.sort(function(a, b) {
				return collator.compare(removeArticles(a.file.substring(6)), removeArticles(b.file.substring(6)));
			});
		}
	}
	catch (e) {
		// fallback to default ordering
		if (typeof(dirs[0]) != 'undefined') {
			dirs.sort(function(a, b) {
				a = removeArticles(a.directory.substring(6).toLowerCase());
				b = removeArticles(b.directory.substring(6).toLowerCase());
				return a > b ? 1 : (a < b ? -1 : 0);
			});
		}
		if (typeof(files[0]) != 'undefined') {
			files.sort(function(a, b) {
				a = removeArticles(a.file.substring(6).toLowerCase());
				b = removeArticles(b.file.substring(6).toLowerCase());
				return a > b ? 1 : (a < b ? -1 : 0);
			});
		}
	}

	// merge
	data = dirs.concat(files);

	var dbList = $('ul.database-radio');
	dbList.html('');
	var output = '';

	$('.btnlist-top-ra').show();
	$('#ra-filter-results').html('')
    $("#searchResetRa").hide();
    showSearchResetRa = false;
	$('#ra-search-keyword').val('');
	$('#ra-filter').val('');

	for (var i = 0; i < data.length; i++) {
		output += formatBrowseData(data, path, i, 'radio_panel');
	}
	$('ul.database-radio').html(output);
	$('#database-radio').scrollTo(0, 200);

	// start lazy load if on the radio panel
	if ($('.radio-view-btn').hasClass('active')) {
		$('img.lazy-radioview').lazyload({
		    container: $('#radiocovers')
		});
	}

	radioRendering = false;
}

// Format for Browse panel
function formatBrowseData(data, path, i, panel) {
	var output = '';

	if (path == '' && typeof data[i].file != 'undefined') {
		var pos = data[i].file.lastIndexOf('/');
		if (pos == -1) {
			path = '';
		}
		else {
			path = data[i].file.slice(0, pos);
		}
	}

	if (typeof data[i].file != 'undefined') {
		// For CUE sheet and future extensions
		var fileExt = data[i].file.substr(data[i].file.lastIndexOf('.') + 1).toLowerCase();

		// Song files
		if (typeof data[i].Title != 'undefined') {
			output = '<li id="db-' + (i + 1) + '" data-path="' + data[i].file + '">'
			// click on item line for menu
			output += '<div class="db-icon db-song db-action">';
			output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder-item">';
            output += '<i class="fas fa-music sx db-browse db-browse-icon"></i></a></div>';
			output += '<div class="db-entry db-song">' + data[i].Title + ' <em class="songtime">' + data[i].TimeMMSS + '</em>';
			output += ' <span>' + data[i].Artist + ' - ' + data[i].Album + '</span></div></li>';
		}
		// Radio stations, playlist items
		else {
			output = '<li id="db-' + (i + 1) + '" data-path="';
			// Remove file extension, except if its url (savedplaylist can contain url's)
			var filename = '';
			if (data[i].file.substr(0,4) == 'http') {
				filename = data[i].file;
			}
			else {
				cutpos = data[i].file.lastIndexOf('.');
	            if (cutpos !=-1) {
	            	filename = data[i].file.slice(0,cutpos);
				}
	        }
			output += data[i].file;

			var itemType = '';
			if (data[i].file.substr(0, 5) == 'RADIO') {
				if (panel == 'radio_panel') {
					var imgurl = '../images/radio-logos/thumbs/' + filename.replace(path + '/', '') + '.jpg';
					output += '"><div class="db-icon db-song db-browse db-action"><img class="lazy-radioview" data-original="' + imgurl  + '"><div class="cover-menu" data-toggle="context" data-target="#context-menu-radio-item"></div></div><div class="db-entry db-song db-browse">';
				}
				else {
					output += '"><div class="db-icon db-song db-browse db-action"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-radio-item"><i class="fas fa-microphone sx db-browse db-browse-icon"></i></a></div><div class="db-entry db-song db-browse">';
				}
				itemType = '';
			}
			// CUE sheet
			else if (fileExt == 'cue') {
				output += '"><div class="db-icon db-song db-browse db-action"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder-item"><i class="fas fa-list-ul icon-root sx db-browse-icon"></i></a></div><div class="db-entry db-song db-browse">';
				itemType = 'CUE sheet';
			}
			// Different icon for song file vs radio station in saved playlist
			else {
				if (data[i].file.substr(0,4) == 'http') {
					output += '"><div class="db-icon db-song db-browse db-action"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-savedpl-item" style="width:100vw;height:2em;"><i class="fas fa-microphone sx db-browse db-browse-icon"></i></a></div><div class="db-entry db-song db-browse">';
					itemType = typeof(RADIO.json[data[i].file]) === 'undefined' ? 'Radio station' : RADIO.json[data[i].file]['name'];
				} else {
					output += '"><div class="db-icon db-song db-browse db-action"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-savedpl-item" style="width:100vw;height:2em;"><i class="fas fa-music sx db-browse db-browse-icon"></i></a></div><div class="db-entry db-song db-browse">';
					itemType = 'Song file';
				}
			}
			output += filename.replace(path + '/', '');
			output += ' <span>';
			output += itemType;
			output += '</span></div></li>';
		}
	}
	// Saved playlists
	else if (typeof data[i].playlist != 'undefined') {
		// Skip .wv (WavPack) files, apparently they can contain embedded playlist
		if (data[i].playlist.substr(data[i].playlist.lastIndexOf('.') + 1).toLowerCase() == 'wv') {
			output= '';
		}
		else {
			output = '<li id="db-' + (i + 1) + '" data-path="' + data[i].playlist + '">';
			output += '<div class="db-icon db-action">';
			output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-savedpl-root">';
			output += '<i class="fas fa-list-ul icon-root sx"></i></a></div>';
			output += '<div class="db-entry db-savedplaylist db-browse">' + data[i].playlist;
			output += '</div></li>';
		}
	}
	// Directories
	else {
		if (data[i].directory !== 'RADIO') { // Exclude in Folder view
			output = '<li id="db-' + (i + 1) + '" data-path="';
			output += data[i].directory;
			if (path != '') {
				if (data[i].directory.substr(0, 5) == 'RADIO' && panel == 'radio_panel') {
					output += '"><div class="db-icon db-radiofolder-icon db-browse db-action"><img src="../images/radiofolder.jpg"></div><div class="db-entry db-radiofolder db-browse">';
				}
				else {
					output += '"><div class="db-icon db-browse db-action"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder"><i class="fas fa-folder sx"></i></a></div><div class="db-entry db-folder db-browse">';
				}
			}
			else {
				output += '"><div class="db-icon db-action"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder"><i class="fas fa-hdd icon-root sx"></i></a></div><div class="db-entry db-folder db-browse">';
			}
			output += data[i].directory.replace(path + '/', '');
			output += '</div></li>';
		}
	}

	return output;
}

// update time knob
function updTimeKnob(mpdTime) {
	if (MPD.json['artist'] == 'Radio station') {
		var str = '';
		$('#total').addClass('total-radio'); // radio station svg
		$('#playbar-mtime').show();
	}
	else {
		var str = formatSongTime(mpdTime) + (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0 ? '<i class="fas fa-caret-up countdown-caret"></i>' : '<i class="fas fa-caret-down countdown-caret"></i>');
		$('#total').removeClass('total-radio'); // radio station svg
		$('#playbar-mtime').hide();
	}
    return str;
}

// update time slider
function updTimeSlider(mpdTime) {
	if (MPD.json['artist'] == 'Radio station') {
		var str = '';
	}
	else {
		var str = formatSongTime(mpdTime);
	}

    return str;
}

// initialize the countdown timers
function refreshTimer(startFrom, stopTo, state) {
	var tick = 3; // call watchCountdown() every tick secs
	$('#countdown-display, #m-countdown, #playbar-countdown, #playbar-mcount').countdown('destroy');

    if (state == 'play' || state == 'pause') {
		if (SESSION.json['timecountup'] == "1" || parseInt(MPD.json['time']) == 0) {
			$('#countdown-display').countdown({since: -(startFrom), onTick: watchCountdown, tickInterval: tick, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			$('#m-countdown, #playbar-countdown, #playbar-mcount').countdown({since: -(startFrom), compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
		else {
			$('#countdown-display').countdown({until: startFrom, onTick: watchCountdown, tickInterval: tick, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			$('#m-countdown, #playbar-countdown, #playbar-mcount').countdown({until: startFrom, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }

	    if (state == 'pause') {
			$('#countdown-display, #m-countdown, #playbar-countdown, #playbar-mcount').countdown('pause');
		}
    }
	else if (state == 'stop') {
		if (SESSION.json['timecountup'] == "1" || parseInt(MPD.json['time']) == 0) {
			$('#countdown-display').countdown({since: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			$('#m-countdown, #playbar-countdown, #playbar-mcount').countdown({since: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
    	}
		else {
			$('#countdown-display').countdown({until: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			$('#m-countdown, #playbar-countdown, #playbar-mcount').countdown({until: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }

		$('#countdown-display, #m-countdown, #playbar-countdown, #playbar-mcount').countdown('pause');
    }
}

// onTick callback for automatic font sizing on time knob
function watchCountdown(period) {
	// period[4] (hours) > 0 reduce font-size so time fits nicely within knob
	//console.log('period ' + period[4]);

	// default
	if ($(window).height() > 479) {
		if (period[4] == 0) {
			$('#countdown-display').css('font-size', '1.9em');
			$('#countdown-display').css('margin-top', '-.5em');
		}
		else if (period[4] < 10) {
			$('#countdown-display').css('font-size', '1.8em');
			$('#countdown-display').css('margin-top', '-.5em');
			$('#countdown-display').css('left', '51%');
		}
		else {
			$('#countdown-display').css('font-size', '1.6em');
			$('#countdown-display').css('margin-top', '-.5em');
		}
	}
	// pi touch 799 x 479
	else {
		if (period[4] == 0) {
			$('#countdown-display').css('font-size', '1.8em');
			$('#countdown-display').css('margin-top', '-1em');
		}
		else if (period[4] < 10) {
			$('#countdown-display').css('font-size', '1.6em');
			$('#countdown-display').css('margin-top', '-1em');
			$('#countdown-display').css('left', '51%');
		}
		else {
			$('#countdown-display').css('font-size', '1.5em');
			$('#countdown-display').css('margin-top', '-1em');
		}
	}
}

// update time knob, time track
function refreshTimeKnob() {
	var initTime, delta;

    window.clearInterval(UI.knob)
    initTime = parseInt(MPD.json['song_percent']);
    delta = parseInt(MPD.json['time']) / 1000;

	if (UI.mobile) {
		//$('#timetrack').val(initTime).trigger('change'); // ORIG
		$('#timetrack').val(initTime * 10).trigger('change');
	}
	else {
		$('#time').val(initTime * 10).trigger('change');
		//$('#playbar-timetrack').val(initTime).trigger('change'); // ORIG
		$('#playbar-timetrack').val(initTime * 10).trigger('change');
	}

	// radio station
	if (delta === 0 || isNaN(delta)) {
		if (UI.mobile) {
			$('#timeline').hide();
			$('#m-radio, #playbar-mtime').show();
		}
		else {
			$('#playbar-timeline').hide();
			$('#playbar-title').css('padding-bottom', '0');
			//$('#playbar-radio').show();
		}
	}
	// song file
	else {
		if (UI.mobile) {
			$('#timeline, #playbar-mtime').show();
			$('#m-radio').hide();
		}
		else {
			$('#playbar-timeline').show();
			$('#playbar-title').css('padding-bottom', '1.5em');
			//$('#playbar-radio').hide();
		}
	}

    if (MPD.json['state'] === 'play') {
        UI.knob = setInterval(function() {
			if (!timeSliderMove) {
				$('#timetrack, #playbar-timetrack').val(initTime * 10).trigger('change');
			}

            delta === 0 ? initTime = initTime + 0.5 : initTime = initTime + 0.1; // fast paint when radio station playing
            if (delta === 0 && initTime > 100) { // stops painting when radio (delta = 0) and knob fully painted
				window.clearInterval(UI.knob)
				UI.knobPainted = true;
            }

            $('#time').val(initTime * 10).trigger('change');
        }, delta * 1000);
    }
}

// fill in timeline color as song plays
$('input[type="range"]').change(function() {
	var val = ($(this).val() - $(this).attr('min')) / ($(this).attr('max') - $(this).attr('min')) * 100;
	if (val < 50) {val = val + 1;} else {val = val - 1;}
	$('.timeline-progress').css('width', val + '%');
	//console.log(val);
});

$('#timetrack, #playbar-timetrack').bind('touchstart mousedown', function(e) {
	timeSliderMove = true;
	$('#m-countdown, #playbar-countdown, #playbar-mcount').countdown('pause');
});

$('#timetrack, #playbar-timetrack').bind('touchend mouseup', function(e) {
	var delta, time;
	time = parseInt(MPD.json['time']);
	delta = time / 1000;
	var seekto = Math.floor(($(this).val() * delta));
	if (seekto > time - 2) {seekto = time - 2;}
	sendMpdCmd('seek ' + MPD.json['song'] + ' ' + seekto);
	timeSliderMove = false;
});

// format song time for knob #total and #countdown-display and for playlist items
function formatSongTime(seconds) {
	var str, hours, minutes, hh, mm, ss;

    if(isNaN(parseInt(seconds))) {
    	str = ''; // so song time is not displayed for radio stations listed in Playlist
    }
	else {
	    hours = Math.floor(seconds / 3600);
    	minutes = Math.floor(seconds / 60);
    	minutes = (minutes < 60) ? minutes : (minutes % 60);
    	seconds = seconds % 60;

    	hh = (hours > 0) ? (hours + ':') : '';
    	mm = (minutes < 10) ? ('0' + minutes) : minutes;
    	ss = (seconds < 10) ? ('0' + seconds) : seconds;

    	str = hh + mm + ':' + ss;
    }

    return str;
}

// Format total time for all songs in library
function formatTotalTime(seconds) {
	var output, hours, minutes, hh, mm, ss;

    if(isNaN(parseInt(seconds))) {
    	output = '';
    }
	else {
	    hours = Math.floor(seconds / 3600);
    	minutes = Math.floor(seconds / 60);
    	minutes = (minutes < 60) ? minutes : (minutes % 60);

        hh = hours == 0 ? '' : (hours == 1 ? hours + ' hour' : hours + ' hours');
        mm = minutes == 0 ? '' : (minutes == 1 ? minutes + ' min' : minutes + ' mins');

		if (hours > 0) {
			if (minutes > 0) {
				output = hh + ', ' + mm;
			}
            else {
				output = hh;
			}
		}
        else {
			output = mm;
		}
    }

    return output;
}

function countdownRestart(startFrom) {
	var tick = 3; // call watchCountdown() every tick secs
    $('#countdown-display, #m-countdown, #playbar-countdown, #playbar-mcount').countdown('destroy');
    $('#countdown-display').countdown({since: startFrom, onTick: watchCountdown, tickInterval: tick, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
    $('#m-countdown, #playbar-countdown, #playbar-mcount').countdown({since: startFrom, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
}

// volume control
function setVolume(level, event) {
	level = parseInt(level); // ensure numeric

	// unmuted, set volume (incl 0 vol)
	if (SESSION.json['volmute'] == '0') {
		SESSION.json['volknob'] = level.toString();
		// update sql value and issue mpd setvol in one round trip
		sendVolCmd('POST', 'updvolume', {'volknob': SESSION.json['volknob']});
    }
	// muted
	else {
		if (level == 0 && event == 'mute')	{
			sendMpdCmd('setvol 0');
			//console.log('setvol 0');
		}
		else {
			// vol up/dn btns pressed, just store the volume for display
			SESSION.json['volknob'] = level.toString();
		}

		var result = sendMoodeCmd('POST', 'updcfgsystem', {'volknob': SESSION.json['volknob']});
    }
}

// scroll item so visible
function customScroll(list, itemNum, speed) {
	//console.log('list=' + list + ', itemNum=(' + itemNum + '), speed=(' + speed + ')');
	var listSelector, scrollSelector, chDivisor, centerHeight, scrollTop, itemHeight, scrollCalc, scrollOffset, itemPos;
	speed = typeof(speed) === 'undefined' ? 500 : speed;

	if (list == 'db') {
		listSelector = '#database';
		scrollSelector = listSelector;
		chDivisor = 4;
	}
	else if (list == 'pl' || list == 'ss-pl') {
        if (isNaN(itemNum)) {return;} // exit if last item in pl ended
		listSelector = list == 'pl' ? '#playlist' : '#ss-playlist';
		scrollSelector = list == 'pl' ? '#container-playlist' : '#ss-container-playlist';
		chDivisor = scrollSelector == '#container-playlist' ? 6 : 1.75;
	}
	else if (list == 'genres') {
		listSelector = '#lib-genre';
		scrollSelector = listSelector;
		chDivisor = 6;
	}
	else if (list == 'artists') {
		listSelector = '#lib-artist';
		scrollSelector = listSelector;
		chDivisor = 6;
	}
	else if (list == 'albums' || list == 'albumcovers') {
		listSelector = list == 'albums' ? '#lib-album' : '#lib-albumcover';
		scrollSelector = listSelector;
		chDivisor = 6;
		itemNum = list == 'albums' ? itemNum : itemNum + 1;
	}
    else if (list == 'tracks') {
		listSelector = '#trackscontainer';
		scrollSelector = '#lib-file';
		chDivisor = 600;
	}
	else if (list == 'radiocovers') {
		listSelector = '#database-radio';
		scrollSelector = listSelector;
		chDivisor = 6;
	}

	// item position
	//console.log($(listSelector + ' ul li:nth-child(' + itemNum + ')').position());
	if ($(listSelector + ' ul li:nth-child(' + itemNum + ')').position() != undefined) {
		itemPos = $(listSelector + ' ul li:nth-child(' + itemNum + ')').position().top;
	}
	else {
		itemPos = 0;
	}

	// scroll to
	centerHeight = parseInt($(scrollSelector).height()/chDivisor);
	scrollTop = $(scrollSelector).scrollTop();
	scrollCalc = (itemPos + scrollTop) - centerHeight;
	//console.log('scrollSelector=' + scrollSelector + ', itemPos=' + itemPos + ', height=' + $(scrollSelector).height() + ', chDivisor=' + chDivisor + ', centerHeight=' + centerHeight + ', scrollTop=' + scrollTop + ', scrollCalc=' + scrollCalc);

	if (scrollCalc > scrollTop) {
	    scrollOffset = '+=' + Math.abs(scrollCalc - scrollTop) + 'px';
	}
	else {
	    scrollOffset = '-=' + Math.abs(scrollCalc - scrollTop) + 'px';
	}

	if (itemNum == 0) {
	    $(scrollSelector).scrollTo(0, speed);
	}
	else if (scrollCalc > 0) {
	    $(scrollSelector).scrollTo(scrollOffset, speed);
	}
	else {
	    $(scrollSelector).scrollTo(0, speed);
	}
}

// scroll to current song if title is clicked
$('#currentsong').click(function(e) {
	if (UI.mobile) {
	    var itemnum = parseInt(MPD.json['song']);
		var centerHeight, scrollTop, scrollCalc, scrollOffset, itemPos;
		itemPos = $('#playlist ul li:nth-child(' + (itemnum + 1) + ')').position().top;
		centerHeight = parseInt($('#playlist').height()/3); // place in upper third instead of middle
	    scrollTop = $('#playlist').scrollTop();
		scrollCalc = (itemPos + 200);
	    $('html, body').animate({ scrollTop: scrollCalc }, 'fast');
	}
});

// context menus and main menu
$('.context-menu a').click(function(e) {
    var path = UI.dbEntry[0]; // file path or item num
	//console.log('path', path);

	// CONTEXT MENUS

	if ($(this).data('cmd') == 'add') {
		mpdDbCmd('add', path);
		notify('add', '');
	}
	else if ($(this).data('cmd') == 'play') {
		mpdDbCmd('play', path);
		notify('add', '');
	}
	else if ($(this).data('cmd') == 'clradd') {
		mpdDbCmd('clradd', path);
		notify('clradd', '');
		// see if its a playlist, preload the saved playlist name
		if (path.indexOf('/') == -1 && path != 'NAS' && path != 'RADIO' && path != 'SDCARD') {
			$('#pl-saveName').val(path);
		}
		else {
			$('#pl-saveName').val('');
		}
	}
	else if ($(this).data('cmd') == 'clrplay') {
		mpdDbCmd('clrplay', path);
		notify('clrplay', '');
		// see if its a playlist, preload the saved playlist name
		if (path.indexOf('/') == -1 && path != 'NAS' && path != 'RADIO' && path != 'SDCARD') {
			$('#pl-saveName').val(path);
		}
		else {
			$('#pl-saveName').val('');
		}
	}
	else if ($(this).data('cmd') == 'updmpddb') {
		mpdDbCmd('updmpddb', path);
		notify('updmpddb', path);
		libRendered = false;
	}
	/* DEPRECATE
	else if ($(this).data('cmd') == 'updradio') {
		mpdDbCmd('updmpddb', 'RADIO');
		notify('updmpddb', 'RADIO');
	}*/
	else if ($(this).data('cmd') == 'delsavedpl') {
		$('#savedpl-path').html(path);
		$('#deletesavedpl-modal').modal();
	}
	else if ($(this).data('cmd') == 'editradiostn') {
		path = path.slice(0,path.lastIndexOf('.')).substr(6); // trim 'RADIO/' and '.pls' from path
		$('#edit-station-name').val(path);
		$('#edit-station-url').val(sendMoodeCmd('POST', 'readstationfile', {'path': UI.dbEntry[0]})['File1']);
		$('#editstation-modal').modal();
	}
	else if ($(this).data('cmd') == 'delstation') {
		$('#station-path').html(path.slice(0,path.lastIndexOf('.')).substr(6)); // trim 'RADIO/' and '.pls' from path
		$('#deletestation-modal').modal();
	}
	else if ($(this).data('cmd') == 'deleteplitem') {
		$('#delete-plitem-begpos').attr('max', UI.dbEntry[4]); // max value (num pl items in list)
		$('#delete-plitem-endpos').attr('max', UI.dbEntry[4]);
		$('#delete-plitem-newpos').attr('max', UI.dbEntry[4]);
		$('#delete-plitem-begpos').val(path + 1); // num of selected item
		$('#delete-plitem-endpos').val(path + 1);
		$('#deleteplitems-modal').modal();
	}
	else if ($(this).data('cmd') == 'moveplitem') {
		$('#move-plitem-begpos').attr('max', UI.dbEntry[4]);
		$('#move-plitem-endpos').attr('max', UI.dbEntry[4]);
		$('#move-plitem-newpos').attr('max', UI.dbEntry[4]);
		$('#move-plitem-begpos').val(path + 1);
		$('#move-plitem-endpos').val(path + 1);
		$('#move-plitem-newpos').val(path + 1);
		$('#moveplitems-modal').modal();
	}
    else if ($(this).data('cmd') == 'setforclockradio' || $(this).data('cmd') == 'setforclockradio-m') {
		if ($(this).data('cmd') == 'setforclockradio-m') { // called from Configuration modal
			$('#configure-modal').modal('toggle');
		}

		$('#clockradio-mode span').text(SESSION.json['clkradio_mode']);

		if ($(this).data('cmd') == 'setforclockradio-m') {
			$('#clockradio-playname').val(SESSION.json['clkradio_name']);
			UI.dbEntry[0] = '-1'; // for update
		}
		else {
			$('#clockradio-playname').val(UI.dbEntry[3]); // called from context menu
		}

		// parse start and end values
		// HH,MM,AP,M T W T F S S
		// 06,00,AM,0,0,0,0,0,0,0
		var start = SESSION.json['clkradio_start'].split(',');
		$('#clockradio-starttime-hh').val(start[0]);
		$('#clockradio-starttime-mm').val(start[1]);
		$('#clockradio-starttime-ampm span').text(start[2]);
		$('#clockradio-start-mon').prop('checked', (start[3] == '1'))
		$('#clockradio-start-tue').prop('checked', (start[4] == '1'))
		$('#clockradio-start-wed').prop('checked', (start[5] == '1'))
		$('#clockradio-start-thu').prop('checked', (start[6] == '1'))
		$('#clockradio-start-fri').prop('checked', (start[7] == '1'))
		$('#clockradio-start-sat').prop('checked', (start[8] == '1'))
		$('#clockradio-start-sun').prop('checked', (start[9] == '1'))

		var stop = SESSION.json['clkradio_stop'].split(',');
		$('#clockradio-stoptime-hh').val(stop[0]);
		$('#clockradio-stoptime-mm').val(stop[1]);
		$('#clockradio-stoptime-ampm span').text(stop[2]);
		$('#clockradio-stop-mon').prop('checked', (stop[3] == '1'))
		$('#clockradio-stop-tue').prop('checked', (stop[4] == '1'))
		$('#clockradio-stop-wed').prop('checked', (stop[5] == '1'))
		$('#clockradio-stop-thu').prop('checked', (stop[6] == '1'))
		$('#clockradio-stop-fri').prop('checked', (stop[7] == '1'))
		$('#clockradio-stop-sat').prop('checked', (stop[8] == '1'))
		$('#clockradio-stop-sun').prop('checked', (stop[9] == '1'))

		$('#clockradio-action span').text(SESSION.json['clkradio_action']);
		$('#clockradio-volume').val(SESSION.json['clkradio_volume']);

		setClkRadioCtls(SESSION.json['clkradio_mode']);

        $('#clockradio-modal').modal();
    }

	// MAIN MENU

    // Appearance settings
    else if ($(this).data('cmd') == 'appearance') {
		// reset indicator
		bgImgChange = false;

		// general
		$('#play-history-enabled span').text(SESSION.json['playhist']);
		$('#extratag-display span').text(SESSION.json['xtagdisp']);
		$('#ashuffle-filter').val(SESSION.json['ashuffle_filter']);

		// themes and backgrounds
		$('#theme-name span').text(SESSION.json['themename']);
		var obj = sendMoodeCmd('POST', 'readthemename');
		var themelist = '';
		for (i = 0; i < obj.length; i++) {
			themelist += '<li class="modal-dropdown-text"><a href="#notarget" data-cmd="theme-name-sel" style="background-color: rgb(' + obj[i]['bg_color'] + ')"><span class="text" style="color: #' + obj[i]['tx_color'] + '">' + obj[i]['theme_name'] + '</span></a></li>';
		}
		$('#theme-name-list').html(themelist);
		$('#alpha-blend span').text(SESSION.json['alphablend']);
		$('#alpha-blend-list').html(
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">1.00</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.95</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.90</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.85</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.80</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.75</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.70</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.65</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.60</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.55</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.50</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.45</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.40</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.35</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.30</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.25</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.20</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.15</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.10</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.05</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.00</span></a></li>'
		);
		$('#adaptive-enabled span').text(SESSION.json['adaptive']);
		$('#accent-color span').text(SESSION.json['accent_color']);
		$('#accent-color-list').html(
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #c0392b; font-weight: bold;">Alizarin</span></a></li>' +
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #8e44ad; font-weight: bold;">Amethyst</span></a></li>' +
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #1a439c; font-weight: bold;">Bluejeans</span></a></li>' +
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #d35400; font-weight: bold;">Carrot</span></a></li>' +
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #27ae60; font-weight: bold;">Emerald</span></a></li>' +
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #cb8c3e; font-weight: bold;">Fallenleaf</span></a></li>' +
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #7ead49; font-weight: bold;">Grass</span></a></li>' +
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #317589; font-weight: bold;">Herb</span></a></li>' +
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #876dc6; font-weight: bold;">Lavender</span></a></li>' +
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #2980b9; font-weight: bold;">River</span></a></li>' +
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #c1649b; font-weight: bold;">Rose</span></a></li>' +
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #999999; font-weight: bold;">Silver</span></a></li>' +
			'<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #16a085; font-weight: bold;">Turquoise</span></a></li>'
		);
		$('#error-bgimage').text('');
		$.ajax({
			url:'imagesw/bgimage.jpg',
		    type:'HEAD',
		    success: function() {
				$('#current-bgimage').html('<img src="imagesw/bgimage.jpg">');
				$('#info-toggle-bgimage').css('margin-left','60px');
		    },
		    error: function() {
				$('#current-bgimage').html('');
				$('#info-toggle-bgimage').css('margin-left','5px');
		    }
		});
		$('#cover-backdrop-enabled span').text(SESSION.json['cover_backdrop']);
		$('#cover-blur span').text(SESSION.json['cover_blur']);
		$('#cover-blur-list').html(
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">0px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">5px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">10px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">15px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">20px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">30px</span></a></li>'
		);
		$('#cover-scale span').text(SESSION.json['cover_scale']);
		$('#cover-scale-list').html(
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel"><span class="text">1.0</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel"><span class="text">1.25</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel"><span class="text">1.5</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel"><span class="text">1.75</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel"><span class="text">2.0</span></a></li>'
		);

		// coverview screen saver
		$('#scnsaver-timeout span').text(screenSaverTimeout(SESSION.json['scnsaver_timeout'], 'param'));
		$('#scnsaver-timeout-list').html(
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">Never</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">1 minute</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">2 minutes</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">5 minutes</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">10 minutes</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">20 minutes</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">30 minutes</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">1 hour</span></a></li>'
		);
		$('#scnsaver-style span').text(SESSION.json['scnsaver_style']);
		$('#scnsaver-style-list').html(
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-style-sel"><span class="text">Animated</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-style-sel"><span class="text">Gradient</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-style-sel"><span class="text">Theme</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-style-sel"><span class="text">Pure Black</span></a></li>'
		);

        $('#customize-modal').modal();
    }

	// cover view (screen saver)
    else if ($(this).data('cmd') == 'scnsaver') {
		screenSaver('1');
	}

    // playback history log
    else if ($(this).data('cmd') == 'viewplayhistory') {
		var obj = sendMoodeCmd('GET', 'readplayhistory');
		var output = '';

		for (i = 1; i < obj.length; i++) {
			output += obj[i];
		}

        $('ol.playhistory').html(output);
        $('#playhistory-modal').modal();
    }

    // help
    else if ($(this).data('cmd') == 'quickhelp') {
		$('#quickhelp').load('quickhelp.html');
        $('#quickhelp-modal').modal();
    }

    // about
    else if ($(this).data('cmd') == 'aboutmoode') {
		//$('#sys-upd-pkgdate').text(SESSION.json['pkgdate']);
		$('#sys-raspbian-ver').text(SESSION.json['raspbianver']);
		$('#sys-kernel-ver').text(SESSION.json['kernelver']);
		$('#sys-processor-arch').text(SESSION.json['procarch']);
		$('#sys-hardware-rev').text(SESSION.json['hdwrrev']);
		$('#sys-mpd-ver').text(SESSION.json['mpdver']);
        $('#about-modal').modal();
    }

	// remove row highlight after selecting action menu item (Browse)
	if (UI.dbEntry[3].substr(0, 3) == 'db-') {
		$('#' + UI.dbEntry[3]).removeClass('active');
	}
});

// update clock radio settings
$('.btn-clockradio-update').click(function(e){
	SESSION.json['clkradio_mode'] = $('#clockradio-mode span').text();
	// NOTE UI.dbEntry[0] set to '-1' if modal launched from Configuration modal
	if (UI.dbEntry[0] != '-1') {
		SESSION.json['clkradio_item'] = sendMoodeCmd('GET', 'getplitemfile&songpos=' + UI.dbEntry[0]);
	}
	SESSION.json['clkradio_name'] = $('#clockradio-playname').val();

	var startHH, startMM, startDays;
	var stopHH, stopMM, stopDays;

	$('#clockradio-starttime-hh').val().length == 1 ? startHH = '0' + $('#clockradio-starttime-hh').val() : startHH = $('#clockradio-starttime-hh').val();
	$('#clockradio-starttime-mm').val().length == 1 ? startMM = '0' + $('#clockradio-starttime-mm').val() : startMM = $('#clockradio-starttime-mm').val();
	$('#clockradio-stoptime-hh').val().length == 1 ? stopHH = '0' + $('#clockradio-stoptime-hh').val() : stopHH = $('#clockradio-stoptime-hh').val();
	$('#clockradio-stoptime-mm').val().length == 1 ? stopMM = '0' + $('#clockradio-stoptime-mm').val() : stopMM = $('#clockradio-stoptime-mm').val();

	startDays = ($('#clockradio-start-mon').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-start-tue').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-start-wed').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-start-thu').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-start-fri').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-start-sat').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-start-sun').prop('checked') === true ? '1' : '0');
	stopDays = ($('#clockradio-stop-mon').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-stop-tue').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-stop-wed').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-stop-thu').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-stop-fri').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-stop-sat').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-stop-sun').prop('checked') === true ? '1' : '0');

	SESSION.json['clkradio_start'] = startHH + ',' + startMM + ',' + $('#clockradio-starttime-ampm span').text() + ',' + startDays;
	SESSION.json['clkradio_stop'] = stopHH +  ',' + stopMM + ',' + $('#clockradio-stoptime-ampm span').text() + ',' + stopDays;

	SESSION.json['clkradio_volume'] = $('#clockradio-volume').val();
	SESSION.json['clkradio_action'] = $('#clockradio-action span').text();

	var result = sendMoodeCmd('POST', 'updcfgsystem',
		{'clkradio_mode': SESSION.json['clkradio_mode'],
		 'clkradio_item': SESSION.json['clkradio_item'].replace(/'/g, "''"), // use escaped single quotes for sql i.e., two single quotes,
		 'clkradio_name': SESSION.json['clkradio_name'].replace(/'/g, "''"),
		 'clkradio_start': SESSION.json['clkradio_start'],
		 'clkradio_stop': SESSION.json['clkradio_stop'],
		 'clkradio_volume': SESSION.json['clkradio_volume'],
		 'clkradio_action': SESSION.json['clkradio_action']
		}
	);

	// update header and menu icon color
	if (SESSION.json['clkradio_mode'] == 'Clock Radio' || SESSION.json['clkradio_mode'] == 'Sleep Timer') {
		$('#clockradio-icon').removeClass('clockradio-off');
		$('#clockradio-icon').addClass('clockradio-on');
	}
	else {
		$('#clockradio-icon').removeClass('clockradio-on');
		$('#clockradio-icon').addClass('clockradio-off');
	}

	// update globals within worker loop
	var result = sendMoodeCmd('GET', 'updclockradio');

    notify('updclockradio', '');
});

// Update appearance options
$('.btn-appearance-update').click(function(e){
	// detect certain changes
	var xtagdispChange = false;
	var accentColorChange = false;
	var themeSettingsChange = false;
	var scnSaverTimeoutChange = false;
	var scnSaverStyleChange = false;
    var playHistoryChange = false;
	// general
	if (SESSION.json['xtagdisp'] != $('#extratag-display span').text()) {xtagdispChange = true;}
	if (SESSION.json['scnsaver_timeout'] != screenSaverTimeout($('#scnsaver-timeout span').text(), 'value')) {scnSaverTimeoutChange = true;}
	if (SESSION.json['scnsaver_style'] != $('#scnsaver-style span').text()) {scnSaverStyleChange = true;}
	// theme and backgrounds
	if (SESSION.json['themename'] != $('#theme-name span').text()) {themeSettingsChange = true;}
	if (SESSION.json['accent_color'] != $('#accent-color span').text()) {themeSettingsChange = true; accentColorChange = true;}
	if (SESSION.json['alphablend'] != $('#alpha-blend span').text()) {themeSettingsChange = true;};
	if (SESSION.json['adaptive'] != $('#adaptive-enabled span').text()) {themeSettingsChange = true;};
	if (SESSION.json['cover_backdrop'] != $('#cover-backdrop-enabled span').text()) {themeSettingsChange = true;};
	if (SESSION.json['cover_blur'] != $('#cover-blur span').text()) {themeSettingsChange = true;};
	if (SESSION.json['cover_scale'] != $('#cover-scale span').text()) {themeSettingsChange = true;};
    // Other
    if (SESSION.json['playhist'] != $('#play-history-enabled span').text()) {playHistoryChange = true;};

	// general
	SESSION.json['playhist'] = $('#play-history-enabled span').text();
	SESSION.json['xtagdisp'] = $('#extratag-display span').text();
	SESSION.json['ashuffle_filter'] = $('#ashuffle-filter').val().trim() == '' ? 'None' : $('#ashuffle-filter').val();
	// theme and backgrounds
	SESSION.json['themename'] = $('#theme-name span').text();
	SESSION.json['accent_color'] = $('#accent-color span').text();
	SESSION.json['alphablend'] = $('#alpha-blend span').text();
	SESSION.json['adaptive'] = $('#adaptive-enabled span').text();
	SESSION.json['cover_backdrop'] = $('#cover-backdrop-enabled span').text();
	SESSION.json['cover_blur'] = $('#cover-blur span').text();
	SESSION.json['cover_scale'] = $('#cover-scale span').text();
	// covreview screen saver
	SESSION.json['scnsaver_timeout'] = screenSaverTimeout($('#scnsaver-timeout span').text(), 'value');
	SESSION.json['scnsaver_style'] = $('#scnsaver-style span').text();

	// update cfg_system and session vars
	var result = sendMoodeCmd('POST', 'updcfgsystem',
		{'playhist': SESSION.json['playhist'],
		 'xtagdisp': SESSION.json['xtagdisp'],
		 'ashuffle_filter': SESSION.json['ashuffle_filter'],
		 'themename': SESSION.json['themename'],
		 'accent_color': SESSION.json['accent_color'],
		 'alphablend': SESSION.json['alphablend'],
		 'adaptive': SESSION.json['adaptive'],
		 'cover_backdrop': SESSION.json['cover_backdrop'],
		 'cover_blur': SESSION.json['cover_blur'],
		 'cover_scale': SESSION.json['cover_scale'],
		 'scnsaver_timeout': SESSION.json['scnsaver_timeout'],
		 'scnsaver_style': SESSION.json['scnsaver_style']
		}
	);

	if (scnSaverTimeoutChange == true) {
		var result = sendMoodeCmd('GET', 'resetscnsaver');
	}
	if (accentColorChange == true) {
		var accentColor = themeToColors(SESSION.json['accent_color']);
		var radio1 = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='30' height='30'><circle fill='%23" + accentColor.substr(1) + "' cx='14' cy='14.5' r='11.5'/></svg>";
		var test = getCSSRule('.toggle .toggle-radio');
		test.style.backgroundImage='url("' + radio1 + '")';
		$('.playbackknob').trigger('configure',{"fgColor":accentColor});
		$('.volumeknob').trigger('configure',{"fgColor":accentColor});
	}
	if (themeSettingsChange == true) {
		themeColor = str2hex(THEME.json[SESSION.json['themename']]['tx_color']);
		themeBack = 'rgba(' + THEME.json[SESSION.json['themename']]['bg_color'] + ',' + SESSION.json['alphablend'] + ')';
		var temprgba = splitColor(adaptBack);
		adaptBack = 'rgba(' + temprgba[0] + ',' + temprgba[1] + ',' + temprgba[2] + ',' + SESSION.json['alphablend'] + ')';
		themeMcolor = str2hex(THEME.json[SESSION.json['themename']]['tx_color']);

		tempcolor = (THEME.json[SESSION.json['themename']]['mbg_color']).split(",");
		themeMback = 'rgba(' + tempcolor[0] + ',' + tempcolor[1] + ',' + tempcolor[2] + ',' + themeOp + ')';
		/*themeMback = 'rgba(' + THEME.json[SESSION.json['themename']]['bg_color'] + ',' + themeOp + ')';*/

		//lastYIQ = 7;
		lastYIQ = '';
		if(SESSION.json['cover_backdrop'] == 'Yes' && MPD.json['coverurl'].indexOf('default-cover-v6') === -1) {
			$('#cover-backdrop').html('<img class="ss-backdrop" ' + 'src="' + MPD.json['coverurl'] + '">');
			$('#cover-backdrop').css('filter', 'blur(' + SESSION.json['cover_blur'] + ')');
			$('#cover-backdrop').css('transform', 'scale(' + SESSION.json['cover_scale'] + ')');
		}
		else {
			$('#cover-backdrop').html('');
		}
		setColors();
	}

	// auto-reload page if indicated
	if (xtagdispChange == true || scnSaverStyleChange == true || playHistoryChange == true || UI.bgImgChange == true) {
	    notify('updcustomize', 'Auto-refresh in 3 seconds');
		setTimeout(function() {
			location.reload(true);
		}, 3000);
	}
	else {
	    notify('updcustomize', '');
	}
});

function screenSaverTimeout (key, returnType) {
	var scnSaverParam = ['Never','1 minute','2 minutes','5 minutes','10 minutes','20 minutes','30 minutes','1 hour'];
	var scnSaverValue = ['Never','60','120','300','600','1200','1800','3600'];

	if (returnType == 'param') {
		return scnSaverParam[scnSaverValue.indexOf(key)];
	}
	if (returnType == 'value') {
		return scnSaverValue[scnSaverParam.indexOf(key)];
	}
}

// remove bg image (NOTE choose bg image is in footer.php)
$('#remove-bgimage').click(function(e) {
	e.preventDefault();
	if ($('#current-bgimage').html() != '') {
		var result = sendMoodeCmd('POST', 'rmbgimage'); // change from GET to POST so modal stays open
		$('#current-bgimage').html('');
		$('#info-toggle-bgimage').css('margin-left','5px');
		UI.bgImgChange = true;
	}
	return false; // so modal stays open
});
// import bg image to server
function importBgImage(files) {
	//console.log('files[0].size=(' + files[0].size + ')');
	if (files[0].size > 1000000) {
		$('#error-bgimage').text('Image must be less than 1MB in size');
		return;
	}
	else if (files[0].type != 'image/jpeg') {
		$('#error-bgimage').text('Image format must be JPEG');
		return;
	}
	else {
		$('#error-bgimage').text('');
	}

	// import image
	UI.bgImgChange = true;
	imgUrl = (URL || webkitURL).createObjectURL(files[0]);
	$('#current-bgimage').html("<img src='" + imgUrl + "' />");
	$('#info-toggle-bgimage').css('margin-left','60px');
	URL.revokeObjectURL(imgUrl);
	var reader = new FileReader();
	reader.onload = function(e) {
		var dataURL = reader.result;
		// strip off the header from the dataURL: 'data:[<MIME-type>][;charset=<encoding>][;base64],<data>'
		var data = dataURL.match(/,(.*)$/)[1];
		var result = sendMoodeCmd('POST', 'setbgimage', {'blob': data});
	}
	reader.readAsDataURL(files[0]);
}

// import station logo image to server
function importLogoImage(files) {
	if (files[0].size > 1000000) {
		$('#error-logoimage').text('Image must be less than 1MB in size');
		return;
	}
	else if (files[0].type != 'image/jpeg') {
		$('#error-logoimage').text('Image format must be JPEG');
		return;
	}
	else {
		$('#error-logoimage').text('');
	}

	// import image
	imgUrl = (URL || webkitURL).createObjectURL(files[0]);
	$('#current-logoimage').html("<img src='" + imgUrl + "' />");
	$('#info-toggle-logoimage').css('margin-left','60px');
	var stationName = $('#new-station-name').val();
	URL.revokeObjectURL(imgUrl);
	var reader = new FileReader();
	reader.onload = function(e) {
		var dataURL = reader.result;
		// strip off the header from the dataURL: 'data:[<MIME-type>][;charset=<encoding>][;base64],<data>'
		var data = dataURL.match(/,(.*)$/)[1];
		var result = sendMoodeCmd('POST', 'setlogoimage', {'name': stationName, 'blob': data});
	}
	reader.readAsDataURL(files[0]);
}

function setClkRadioCtls(ctlValue) {
	if (ctlValue == 'Disabled') {
		$('#clockradio-ctl-grp1 *').prop('disabled', true);
		$('#clockradio-ctl-grp2 *').prop('disabled', true);
		$('#clockradio-ctl-grp3 *').prop('disabled', true);
	}
	else if (ctlValue == 'Sleep Timer') {
		$('#clockradio-ctl-grp1 *').prop('disabled', true);
		$('#clockradio-ctl-grp2 *').prop('disabled', false);
		$('#clockradio-ctl-grp3 *').prop('disabled', true);
	}
	else {
		$('#clockradio-ctl-grp1 *').prop('disabled', false);
		$('#clockradio-ctl-grp2 *').prop('disabled', false);
		$('#clockradio-ctl-grp3 *').prop('disabled', false);
	}
}

// custom select controls
$('body').on('click', '.dropdown-menu .custom-select a', function(e) {
	// clock radio
	if ($(this).data('cmd') == 'clockradio-mode-sel') {
		$('#clockradio-mode span').text($(this).text());
		setClkRadioCtls($(this).text());
	}
	else if ($(this).data('cmd') == 'clockradio-starttime-ampm') {
		$('#clockradio-starttime-ampm span').text($(this).text());
	}
	else if ($(this).data('cmd') == 'clockradio-stoptime-ampm') {
		$('#clockradio-stoptime-ampm span').text($(this).text());
	}
	else if ($(this).data('cmd') == 'clockradio-action-sel') {
		$('#clockradio-action span').text($(this).text());
	}
	// appearance: themes and backgrounds
	else if ($(this).data('cmd') == 'theme-name-sel') {
		$('#theme-name span').text($(this).text());
	}
	else if ($(this).data('cmd') == 'accent-color-sel') {
		$('#accent-color span').text($(this).text());
	}
	else if ($(this).data('cmd') == 'adaptive-enabled-yn') {
		$('#adaptive-enabled span').text($(this).text());
	}
	else if ($(this).data('cmd') == 'alpha-blend-sel') {
		$('#alpha-blend span').text($(this).text());
	}
	else if ($(this).data('cmd') == 'cover-backdrop-enabled-yn') {
		$('#cover-backdrop-enabled span').text($(this).text());
	}
	else if ($(this).data('cmd') == 'cover-blur-sel') {
		$('#cover-blur span').text($(this).text());
	}
	// appearance: coverview options
	else if ($(this).data('cmd') == 'scnsaver-timeout-sel') {
		$('#scnsaver-timeout span').text($(this).text());
	}
	else if ($(this).data('cmd') == 'scnsaver-style-sel') {
		$('#scnsaver-style span').text($(this).text());
	}
	else if ($(this).data('cmd') == 'cover-scale-sel') {
		$('#cover-scale span').text($(this).text());
	}
	// appearance: other options
	else if ($(this).data('cmd') == 'extratag-display-yn') {
		$('#extratag-display span').text($(this).text());
	}
	else if ($(this).data('cmd') == 'play-history-enabled-yn') {
		$('#play-history-enabled span').text($(this).text());
	}
});

$('#syscmd-reboot').click(function(e) {
	UI.restart = 'reboot';
	var result = sendMoodeCmd('GET', 'reboot');
	notify('reboot', '', 8000);
});

$('#syscmd-poweroff').click(function(e) {
	UI.restart = 'poweroff';
	var result = sendMoodeCmd('GET', 'poweroff');
	notify('shutdown', '', 8000);
});

// https: //github.com/Qix-/color-convert/blob/master/conversions.js
function rgbToHsl(color){
    var color = color.substr(4);
    var color = color.slice(0, (color.length - 1));
	var colors = color.split(",");
	var r = colors[0] / 255;
	var g = colors[1] / 255;
	var b = colors[2] / 255;
	var min = Math.min(r, g, b);
	var max = Math.max(r, g, b);
	var delta = max - min;
	var h;
	var s;
	var l;

	if (max === min) {
		h = 0;
	} else if (r === max) {
		h = (g - b) / delta;
	} else if (g === max) {
		h = 2 + (b - r) / delta;
	} else if (b === max) {
		h = 4 + (r - g) / delta;
	}

	h = Math.min(h * 60, 360);

	if (h < 0) {
		h += 360;
	}

	l = (min + max) / 2;

	if (max === min) {
		s = 0;
	} else if (l <= 0.5) {
		s = delta / (max + min);
	} else {
		s = delta / (2 - max - min);
	}
	h = h / 360;
	return [h, s, l];
}

function hslToRgb(color){

	var h = color[0];
	var s = color[1];
	var l = color[2];
	var t1;
	var t2;
	var t3;
	var rgb;
	var val;

	if (s === 0) {
		val = Math.round(l * 255);
		return [val, val, val];
	}

	if (l < 0.5) {
		t2 = l * (1 + s);
	} else {
		t2 = l + s - l * s;
	}

	t1 = 2 * l - t2;

	rgb = [0, 0, 0];
	for (var i = 0; i < 3; i++) {
		t3 = h + 1 / 3 * -(i - 1);
		if (t3 < 0) {
			t3++;
		}
		if (t3 > 1) {
			t3--;
		}

		if (6 * t3 < 1) {
			val = t1 + (t2 - t1) * 6 * t3;
		} else if (2 * t3 < 1) {
			val = t2;
		} else if (3 * t3 < 2) {
			val = t1 + (t2 - t1) * (2 / 3 - t3) * 6;
		} else {
			val = t1;
		}

		rgb[i] = Math.round(val * 255);
	}
	return rgb;
}

function hslToRgba(color, alpha){

	var h = color[0];
	var s = color[1];
	var l = color[2];
	var t1;
	var t2;
	var t3;
	var rgb;
	var val;

	if (s === 0) {
		val = Math.round(l * 255);
		return [val, val, val];
	}

	if (l < 0.5) {
		t2 = l * (1 + s);
	} else {
		t2 = l + s - l * s;
	}

	t1 = 2 * l - t2;

	rgb = [0, 0, 0];
	for (var i = 0; i < 3; i++) {
		t3 = h + 1 / 3 * -(i - 1);
		if (t3 < 0) {
			t3++;
		}
		if (t3 > 1) {
			t3--;
		}

		if (6 * t3 < 1) {
			val = t1 + (t2 - t1) * 6 * t3;
		} else if (2 * t3 < 1) {
			val = t2;
		} else if (3 * t3 < 2) {
			val = t1 + (t2 - t1) * (2 / 3 - t3) * 6;
		} else {
			val = t1;
		}

		rgb[i] = Math.round(val * 255);
	}
	var tempa = 'rgba('.concat(rgb[0],',',rgb[1],',',rgb[2],',',alpha,')');
	return tempa;
}

// rgba to rgb
function rgbaToRgb(a, rgba, rgb) {
	var r3 = Math.round(((1 - a) * rgb[0]) + (a * rgba[0]));
	var g3 = Math.round(((1 - a) * rgb[1]) + (a * rgba[1]));
	var b3 = Math.round(((1 - a) * rgb[2]) + (a * rgba[2]));
	return [r3,g3,b3];
}

function splitColor(tempcolor) {
    //var color = tempcolor.substr(5);
	var color = tempcolor.slice(5, (tempcolor.length - 1));
	var colors = color.split(",");
	var r = colors[0];
	var g = colors[1];
	var b = colors[2];
	var a = colors[3];
	return [r, g, b, a];
}

function splitRGB(tempcolor) {
    var color = tempcolor.substr(4);
	var color = color.slice(0, (color.length - 1));
	var colors = color.split(",");
	var r = colors[0];
	var g = colors[1];
	var b = colors[2];
	return [r, g, b];
}

function hexToRgb(hex) {
    // Expand shorthand form (e.g. "03F") to full form (e.g. "0033FF")
    var shorthandRegex = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
    hex = hex.replace(shorthandRegex, function(m, r, g, b) {
        return r + r + g + g + b + b;
    });

    var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return [parseInt(result[1], 16),parseInt(result[2], 16),parseInt(result[3], 16)];

}

// always use rgba now newui2
function str2rgba(tempcolor) {
	var temp = 'rgba(' + tempcolor + ')';
	return temp;
}
function str2hex(tempcolor) {
	var temp = '#' + tempcolor;
	return temp;
}

// sekrit search function
function dbFastSearch() {
	$('#dbsearch-alltags').val($('#dbfs').val());
	$('#db-search-submit').click();
	return false;
}

// set colors for tab bar and tab bar fade
function btnbarfix(temp1,temp2) {
	var temprgba = splitColor(temp1);
	var temprgb = hexToRgb(temp2);
	var temprgb2 = hexToRgb(temp2);

	/*var color4 = rgbaToRgb(.4, temprgba, temprgb);*/
	var color4 = rgbaToRgb(.35, temprgba, temprgb);

	var tempx = 0;
	if ((SESSION.json['alphablend']) < .85) {
		tempx = ((.9 - (SESSION.json['alphablend'])));
		if (tempx > .4) {tempx = .4}
	}
	var colors = rgbaToRgb(.7 - tempx, temprgba, temprgb2);
	var color2 = rgbaToRgb(.73 - tempx, temprgba, temprgb2);
	var color3 = rgbaToRgb(.8 - tempx, temprgba, temprgb2);
	blurrr == true ? tempback = '0.75' : tempback = themeOp; // was 0.65

	if (getYIQ(temp1) > 127) {
		var tempa = 'rgba(128,128,128,0.15)';
		var color5 = 'rgba(32,32,32,0.1)'; // new
	}
	else {
		var tempa = 'rgba(32,32,32,0.25)';
		var color5 = 'rgba(208,208,208,0.2)'; // new

	}
	// set middle color to a dark variant of the adaptive bg one
	var tempx = splitColor(temp1);
	var tempy = 'rgb('+tempx[0]+','+tempx[1]+','+tempx[2]+')';
	tempx = rgbToHsl(tempy);
	if (tempx[2] > .3) {tempx[2]=.3}
	//console.log(tempy, tempx);
	tempy = hslToRgb(tempx);
	document.body.style.setProperty('--shiftybg', 'rgba('+tempy[0]+','+tempy[1]+','+tempy[2]+',0.8)');
	// end

	document.body.style.setProperty('--btnbarcolor', 'rgba('+colors[0]+','+colors[1]+','+colors[2]+','+tempback+')');
	document.body.style.setProperty('--btnshade', 'rgba('+color2[0]+','+color2[1]+','+color2[2]+','+'0.15)');
	document.body.style.setProperty('--btnshade2', 'rgba('+color3[0]+','+color3[1]+','+color3[2]+','+'0.6)');

	document.body.style.setProperty('--btnshade4', color5);

	document.body.style.setProperty('--textvariant', 'rgba('+color4[0]+','+color4[1]+','+color4[2]+',1.0)');
	var tempb = 'rgba('+temprgba[0]+','+temprgba[1]+','+temprgba[2]+','+'0.25)';
	document.body.style.setProperty('--btnshade3', tempb);
	blurrr == true ? tempb = tempb : tempb = temp1;
	document.body.style.setProperty('--btnbarback', tempb);
}

function getYIQ(color) {
  var rgb = color.match(/\d+/g);
  return parseInt(((rgb[0] * 299) + (rgb[1] * 587) + (rgb[2] * 114)) / 1000);
}

function setColors() {
	document.body.style.setProperty('--themebg', themeBack);
	var yiqBool = getYIQ(themeBack) > 127 ? true : false;

	if (currentView.indexOf('playback') !== -1 && SESSION.json['adaptive'] == 'Yes') {
		yiqBool = getYIQ(adaptBack) > 127 ? true : false;
		document.body.style.setProperty('--adaptbg', adaptBack);
		document.body.style.setProperty('--adapttext', adaptMcolor);
		document.body.style.setProperty('--adaptmbg', adaptMback);
		btnbarfix(adaptBack, adaptColor);
	}
	else {
		document.body.style.setProperty('--adaptbg', themeBack);
		document.body.style.setProperty('--adapttext', themeMcolor);
		document.body.style.setProperty('--adaptmbg', themeMback);
		btnbarfix(themeBack, themeColor);
	}

	if (lastYIQ !== yiqBool) {
		lastYIQ = yiqBool;
		if (yiqBool) {
			document.body.style.setProperty('--timethumb', 'url("' + thumbd + '")');
			document.body.style.setProperty('--timecolor', 'rgba(96,96,96,0.25)');
			document.body.style.setProperty('--trackfill', 'rgba(48,48,48,1.0)');
			document.body.style.setProperty('--radiobadge', 'url("../images/radio-d.svg")');
			//document.body.style.setProperty('--defcover', 'images/default-cover-v6-d.svg'); // TEST
			if (!UI.mobile) {
				setTimeout(function() {
					$('.playbackknob').trigger('configure',{"bgColor":"rgba(32,32,32,0.10)"});
					$('.volumeknob').trigger('configure',{"bgColor":"rgba(32,32,32,0.10)"});
				}, 250);
			}
		}
		else {
			document.body.style.setProperty('--timethumb', 'url("' + thumbw + '")');
			document.body.style.setProperty('--timecolor', 'rgba(240,240,240,0.25)');
			document.body.style.setProperty('--trackfill', 'rgba(240,240,240,1.0)');
			document.body.style.setProperty('--radiobadge', 'url("../images/radio-l.svg")');
			//document.body.style.setProperty('--defcover', 'images/default-cover-v6-l.svg'); // TEST
			if (!UI.mobile) {
				setTimeout(function() {
					$('.playbackknob').trigger('configure',{"bgColor":"rgba(240,240,240,0.10)"});
					$('.volumeknob').trigger('configure',{"bgColor":"rgba(240,240,240,0.10)"});
				}, 250);
			}
		}
	}
}

// Graphic eq
function updEqgFreq(selector, value) {
    $(selector).html(value);
}
// Parametric eq
function updEqpMasterGain(selector, value) {
    $(selector).html(value + ' dB');
}
function updEqpFreq(selector1, selector2, value) {
    $(selector1).html(value + ' Hz');
    $(selector2).val(value);
}
function updEqpMasterGainSlider(selector) {
    var step = selector == 'master-gain-up' ? 0.1 : -0.1;
    var new_val = (parseFloat($('#master-gain-slider').val()) + step).toFixed(1);
    new_val = new_val > 24 ? 24 : (new_val < -24 ? -24 : new_val);
    $('#master-gain-slider').val(new_val);
    $('#master-gain').html(new_val + ' dB');
}

// Manages toolbar when scrolling
$(window).on('scroll', function(e) {
	//console.log('window scroll');
	if ($(window).scrollTop() > 1 && !showMenuTopW) {
		//console.log('window scroll, scrollTop() > 1 && !showMenuTopW');
		if (UI.mobile) {
			$('#mobile-toolbar').css('display', 'none');
			$('#container-playlist').css('visibility','visible');
			$('#menu-bottom').show();
		}
		$('#menu-top').css('height', $('#menu-top').css('line-height'));
		$('#menu-top').css('backdrop-filter', 'blur(20px)');
		showMenuTopW = true;
	}
	else if ($(window).scrollTop() == '0' ) {
		//console.log('window scroll, scrollTop() = 0');
		if (UI.mobile) {
			$('#container-playlist').css('visibility','hidden');
			$('#mobile-toolbar').css('display', 'block');
			if (currentView.indexOf('playback') !== -1) {
				$('#menu-bottom').hide();
			}
		}
		$('#menu-top').css('height', '0');
		$('#menu-top').css('backdrop-filter', '');
		showMenuTopW = false;
	}
});

// Get CSS rule object
function getCSSRule(ruleName) {
	for (var i = 0; i < document.styleSheets.length; i++) {
		var styleSheet = document.styleSheets[i];

		if (styleSheet.href && styleSheet.href.indexOf('flat-ui.css') != -1 ) {
			var cssRules = styleSheet.cssRules;
			for (var j = 0; j < cssRules.length; j++) {
				var cssRule = cssRules[j];
				if (cssRule.selectorText == ruleName) {
					return cssRule;
				}
			}
		}
	}
}

// set theme colors
function themeToColors(accentColor) {
	switch (accentColor) {
		case 'Amethyst':
			var ac1 = '#8e44ad', ac2 = 'rgba(142,68,173,0.71)';
			break;
		case 'Bluejeans':
			var ac1 = '#1a439c', ac2 = 'rgba(26,67,156,0.71)';
			break;
		case 'Carrot':
			var ac1 = '#d35400', ac2 = 'rgba(211,84,0,0.71)';
			break;
		case 'Emerald':
			var ac1 = '#27ae60', ac2 = 'rgba(39,174,96,0.71)';
			break;
		case 'Fallenleaf':
			var ac1 = '#cb8c3e', ac2 = 'rgba(203,140,62,0.71)';
			break;
		case 'Grass':
			var ac1 = '#7ead49', ac2 = 'rgba(126,173,73,0.71)';
			break;
		case 'Herb':
			var ac1 = '#317589', ac2 = 'rgba(49,117,137,0.71)';
			break;
		case 'Lavender':
			var ac1 = '#876dc6', ac2 = 'rgba(135,109,198,0.71)';
			break;
		case 'River':
			var ac1 = '#2980b9', ac2 = 'rgba(41,128,185,0.71)';
			break;
		case 'Rose':
			var ac1 = '#c1649b', ac2 = 'rgba(193,100,155,0.71)';
			break;
		case 'Silver':
			var ac1 = '#999999', ac2 = 'rgba(153,153,153,0.71)';
			break;
		case 'Turquoise':
			var ac1 = '#16a085', ac2 = 'rgba(22,160,133,0.71)';
			break;
		default: // Alizarin
			var ac1 = '#c0392b', ac2 = 'rgba(192,57,43,0.71)';
			break;
	}

	document.body.style.setProperty('--accentxts', ac1);
	document.body.style.setProperty('--accentxta', ac2);
	return ac1;
}

// alphabits quick scroll
$('#index-genres li').on('click', function(e) {
	listLook('genresList li', 'genres', $(this).text());
});
$('#index-artists li').on('click', function(e) {
	listLook('artistsList li', 'artists', $(this).text());
});
$('#index-albums li').on('click', function(e) {
	listLook('albumsList li', 'albums', $(this).text());
});
$('#index-albumcovers li').on('click', function(e) {
	listLook('albumcovers li div span', 'albumcovers', $(this).text());
});
$('#index-browse li').on('click', function(e) {
	listLook('database li', 'db', $(this).text());
});
$('#index-radio li').on('click', function(e) {
	listLook('radiocovers li', 'radiocovers', $(this).text());
});
function listLook(list, name, search) {
	alphabitsFilter = 0;
	if (search != '#') {
		$('#' + list).each(function(){
			var text = removeArticles($(this).text().toLowerCase());
			if (text.indexOf(search) == 0) {
				return false;
			}
			alphabitsFilter++;
		});
	}

	if (alphabitsFilter != $('#' + list).length) {
		customScroll(name, alphabitsFilter, 100);
	}
}

// radio pos
function storeRadioPos(pos) {
	//console.log('radio_pos', pos);
	var result = sendMoodeCmd('POST', 'updcfgsystem', {'radio_pos': pos}); // sync
}
// library pos
function storeLibPos(pos) {
	//console.log('lib_pos', pos[0], pos[1], pos[2]);
	var result = sendMoodeCmd('POST', 'updcfgsystem', {'lib_pos': pos[0] + ',' + pos[1] + ',' + pos[2]}); // sync
}

// switch to library / playbar panel
$("#coverart-url, #playback-switch").click(function(e){
	currentView = currentView.split(',')[1];
	$('#menu-bottom').show();
	$('.viewswitch').css('display', 'flex');
	$('#library-panel').addClass('active');
	$('#playback-panel').removeClass('active');
	$('#playback-switch').hide();
	$('html, body').animate({ scrollTop: 0 }, 100);

	if (currentView == 'tag') {
		$('.folder-view-btn, .album-view-btn, .radio-view-btn').removeClass('active');
		$('.tag-view-btn').addClass('active');
		$('#lib-albumcover, #lib-albumcover-header, #index-albumcovers').hide();
		setColors();

        if (SESSION.json['show_genres'] == 'Yes') {
            $('#top-columns').removeClass('nogenre');
        }
        else {
            $('#top-columns').addClass('nogenre');
        }

		$('#top-columns, #bottom-row').show();
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'current_view': currentView}, true); // async
		if (UI.tagViewCovers) {
			setTimeout(function() {
				if (UI.tagViewCovers) {
					$('img.lazy-tagview').lazyload({
					    container: $('#lib-album')
					});
				}
				if (UI.libPos[0] >= 0) {
					customScroll('albums', UI.libPos[0], 200);
					$('#albumsList .lib-entry').eq(UI.libPos[0]).click();
				}
			}, 250);
		}
	}
	else if (currentView == 'album') {
		$('.folder-view-btn, .tag-view-btn, .radio-view-btn').removeClass('active');
		$('.album-view-btn').addClass('active');
		setColors();
		$('#lib-albumcover, #lib-albumcover-header').show();
		$('#top-columns').css('display', 'none');
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'current_view': currentView}, true);
		setTimeout(function() {
			$('img.lazy-albumview').lazyload({
			    container: $('#lib-albumcover')
			});
			if (UI.libPos[1] >= 0) {
				customScroll('albumcovers', UI.libPos[1], 200);
			}
		}, 250);
	}
	else if (currentView == 'radiolist' || currentView == 'radiocover') {
		$('#library-panel').removeClass('active');
		$('#radio-panel').addClass('active');
		$('.folder-view-btn, .tag-view-btn, .album-view-btn').removeClass('active');
		$('.radio-view-btn').addClass('active');
		setColors();
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'current_view': currentView}, true);

		if (currentView == 'radiolist') {
			$('#ra-toggle-view i').removeClass('fa-bars').addClass('fa-th');
			$('#radiocovers').addClass('database-radiolist');
		}
		else {
			$('#ra-toggle-view i').removeClass('fa-th').addClass('fa-bars');
			$('#radiocovers').removeClass('database-radiolist');
		}

		setTimeout(function() {
			$('img.lazy-radioview').lazyload({
			    container: $('#radiocovers')
			});
			if (UI.radioPos >= 0) {
				customScroll('radiocovers', UI.radioPos, 200);
			}
		}, 500);
	}
	// default to folder view
	else {
		$('#library-panel').removeClass('active');
		$('#folder-panel').addClass('active');
		$('.tag-view-btn, .album-view-btn, .radio-view-btn').removeClass('active');
		$('.folder-view-btn').addClass('active');
		//mpdDbCmd('lsinfo', '');
		currentView = 'folder';
		setColors();
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'current_view': currentView}, true);
	}
});

// switch to playback panel
$('#playbar-switch, #playbar-cover').click(function(e){
	if (currentView.indexOf('playback') !== -1) {
		$('html, body').animate({ scrollTop: 0 }, 100);
	}
	else {
		currentView = 'playback,' + currentView;
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'current_view': currentView}, true); // async
		setColors();
		$('#playback-switch').show();
		if (currentView.indexOf('playback') !== -1) {$('#menu-bottom, .viewswitch').css('display', 'none');}
		$('#folder-panel, #radio-panel, #library-panel').removeClass('active');
		$('.volume-display').css('margin-top', '');
		$('#playback-panel').addClass('active');
		if (!UI.mobile) { // don't need to scroll playlist on
			setTimeout(function() { // wait a bit for panel to load
				customScroll('pl', parseInt(MPD.json['song']), 200);
			}, 500);
		}
	}
});
