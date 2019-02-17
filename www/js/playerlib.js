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
 * 2018-01-26 TC moOde 4.0
 * 2018-04-02 TC moOde 4.1
 * - update knob and popup volumes when UI is rendered
 * - add validation checks to importBgImage()
 * - add raspbian version to About
 * - fix adevname not being set correctly in 'update customize'
 * - don't sort by file name so mpd index order is preserved in Library
 * - sync accent colors in Customize with worker.php
 * - add features availability bitmask
 * - add logic to sync vol and mute to UPnP controller
 * - live time line for mobile 
 * - new volume controls
 * - add auto-refresh to Customize update
 * - add engineCmd()
 * - remove engineSps() and related code
 * - remove accumulated  code
 * 2018-07-11 TC moOde 4.2
 * - fix upnp vol sync broken (moved logic above // update volume knob)
 * - replace multiple renderer indocator functions with inpSrcIndicator()
 * - new disableVolKnob() code to handle mpdmixer == disabled (0dB)
 * - add slactive to engineCmd()
 * - ignore 'Not seekable' error in engineMpd() success branch (apparantly this is bug thats fixed in MPD 0.20.19)
 * - improve setVolume() by using one round trip to update volknob sql and mpd volume
 * - only auto-reload page when Customize theme settings or accent color changes
 * - add radio logo thumbs to station list
 * - new tabs, other code for newui v2
 * - use jpg instead of png for radio logos
 * - move google search from cover to song title title
 * - chg read/updcfgengine to read/updcfgsystem
 * - deprecate search auto-focus
 * - screen saver
 * - fix various bgimage issues
 * - improve detection of dbupdate complete
 * - adv browse search
 * - random album selector for library
 * - fix lib album click adding highlight to wrong selector
 * - add excludeAlbums array to lib
 * - font-awesome 5
 * 2018-07-18 TC moOde 4.2 update
 * - add musictab_default to Customize modal
 * 2018-09-27 TC moOde 4.3
 * - add composer to Lib track
 * - swipe gesture for Lib
 * - fast search for Browse
 * - fixes to tab btns, other css 
 * - clear/add for saved playlists
 * - radio panel folders
 * - improved sorts for radio and browse
 * - UI.raPos array and pathr vars for radio
 * - radioRendering flag
 * - rm legacy pldisp code from renderPlaylist()
 * - rm bootTicker
 * - library utf8 character filter on customize
 * - handle "Failed to open ALSA bluetooth" in engineMpd()
 * - fix indexOf bug in renderUI() was indexOf() == '-1', changed to indexOf() === -1
 * - loadLibrary()
 * - parameterize customScroll()
 * - sort song and file arrays in renderSongs()
 * - rewrite customScroll()
 * - store pos in artist list in UI.libPos[2]
 * - album cover view
 * - HUD playlist
 * 2018-10-19 TC moOde 4.3 update
 * - add station name to saved playlists
 * - add 'e' var to all JQuery function()
 * - fix emove image closes Customize modal (#remove-bgimage)
 * - check for cover hash change in renderUI()
 * - album cover backdrop
 * 2018-12-09 TC moOde 4.4
 * - use relative path for displaying bgimage.jpg in Customize
 * - add days to clock radio
 * - clock radio sql cols and setClkRadioCtls()
 * - chg from .attr to .prop in  disableVolKnob()
 * - add compilation_rollup and compilation_excludes to Customize
 * - move compilation rollup code block into compilationRollup()
 * - auto and manual hires thumbnail settings
 * - rm the conditional for updating the Playlist in renderUI()
 * - redo fallback sort for Albums by Artist
 * - add css variable --textvariant
 * - only load swipe if mobile
 * - improvements to btnBarFix(), new rgbaToRgb()
 * - add disc to tracks sort and tracks display in var renderSongs
 * - add disc to extraTags
 * - add array allSongsDisc to filterLib()
 * - improve performance of filterLib() etc by removing allFiles
 * - add test for 'undefined' to determine if volume is set to disabled
 * - streamline btnbarfix()
 * - add 30px blur to list of Cover blur settings
 * - camelcase dbfastsearch
 * - deprecate code block that checked '?from=makeCoverUrl'
 * - full screen input source indicator
 * 
 */

// features availability bitmask
const FEAT_ADVKERNELS =  0b0000000000000001;	//     1
const FEAT_AIRPLAY =     0b0000000000000010;	//     2
const FEAT_MINIDLNA =    0b0000000000000100;	//     4
const FEAT_MPDAS =       0b0000000000001000;	//     8 
const FEAT_SQUEEZELITE = 0b0000000000010000;	//    16
const FEAT_UPMPDCLI =    0b0000000000100000;	//    32
const FEAT_SQSHCHK =     0b0000000001000000;	//    64
const FEAT_GMUSICAPI =   0b0000000010000000;	//   128
const FEAT_LOCALUI =     0b0000000100000000;	//   256
const FEAT_INPUTSEL =    0b0000001000000000;	//   512
const FEAT_UPNPSYNC =    0b0000010000000000;	//  1024
const FEAT_SPOTIFY =     0b0000100000000000;	//  2048

var UI = {
    knob: null,
    path: '',
    pathr: '',
	restart: '',
	lastSong: 'fxdnjkfw',
	lastHash: 'fxdnjkfw', // r44a
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
	raPos: [0,0,0,0,0],
	libPos: [-1,-1,-1],
	// [0]: albums list pos
	// [1]: album covers pos
	// [2]: artist list pos
	// special values for {0} and {1}: -1 = full lib displayed, -2 = lib headers clicked, -3 = search performed
	libAlbum: ''
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

// library
var LIB = {
	totalSongs: 0,
	albumClicked: false,
	totalTime: 0,
	filters: {artists: [], genres: [], albums: []}
};

// for live timeline
var timeSliderMove = false;

// for adaptive theme
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
var abFound;
var showMenuTopW;
var showMenuTopR;

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
			// always have valid json
			try {
				MPD.json = JSON.parse(data);
			}			
			catch (e) {
				MPD.json['error'] = e;
			}

			if (typeof(MPD.json['error']) === 'undefined') {
				debugLog('engineMpd: idle_timeout_event=(' + MPD.json['idle_timeout_event'] + ')');

				if (UI.hideReconnect === true) {
					hideReconnect();
				}
				// mpd restarted via udev usb audio plug-in event rule
				if (MPD.json['idle_timeout_event'] === '') {
					location.reload(true);
				}
				// render volume
				else if (MPD.json['idle_timeout_event'] === 'changed: mixer') {
					renderUIVol();
				}
				// when last item in playlist finishes just update a few things
				else if (MPD.json['idle_timeout_event'] == 'changed: player' && MPD.json['file'] == null) {
					updPlayCtls();
				}
				// prevents brief visual anomaly when auto-shuffle is running and the track is consumed
				else if (MPD.json['idle_timeout_event'] == 'changed: playlist' && MPD.json['file'] == null && SESSION.json['ashuffle'] == '1') {
					$('.covers').hide();
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

				// mpd bug may have been fixed in 0.20.20 ?
				if (MPD.json['error'] == 'Not seekable') {					
					// nop
				}
				// MPD output --> Bluetooth but no actual BT connection
				else if (MPD.json['error'] == 'Failed to open "ALSA bluetooth" [alsa]; Failed to open ALSA device "btstream": No such device') {
					notify('mpderror', 'Failed to open ALSA bluetooth');
				}
				else {
					// client connects before mpd started by worker ?
					if (MPD.json['error'] == 'SyntaxError: JSON Parse error: Unexpected EOF') {
						// nop
					}
					// other network or MPD errors
					else {
						notify('mpderror', MPD.json['error']);
					}
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
			// always have valid json
			try {
				MPD.json = JSON.parse(data);
			}			
			catch (e) {
				MPD.json['error'] = e;
			}

			if (typeof(MPD.json['error']) === 'undefined' || MPD.json['error'] == 'Not seekable') {
				debugLog('engineMpdLite: idle_timeout_event=(' + MPD.json['idle_timeout_event'] + ')');

				if (UI.hideReconnect === true) {
					hideReconnect();
				}
				// show/clear db update in-progress icon
				if (typeof(MPD.json['updating_db']) == 'undefined' || MPD.json['idle_timeout_event'] == 'changed: database') {
					$('.open-library-panel').html('Music');
				}
				else {
					$('.open-library-panel').html('<i class="fal fa-sync fa-spin dbupdate-spinner"></i>Music');
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

// command engine
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
			if (cmd[0] == 'btactive1' || cmd[0] == 'btactive0') {				
				// cmd[1] is the connected device name
				inpSrcIndicator(cmd[0], 'Bluetooth Active<br><span>' + cmd[1] + '</span>');
			}
			if (cmd[0] == 'aplactive1' || cmd[0] == 'aplactive0') {
				inpSrcIndicator(cmd[0], 'Airplay Active');
			}
			if (cmd[0] == 'spotactive1' || cmd[0] == 'spotactive0') {
				inpSrcIndicator(cmd[0], 'Spotify Active');
			}
			if (cmd[0] == 'slactive1' || cmd[0] == 'slactive0') {
				inpSrcIndicator(cmd[0], 'Squeezelite Active');
			}
			if (cmd[0] == 'scnactive1') {
				screenSaver(cmd[0]);
			}
			// individual client deactivation
			else if (cmd[0] == 'scnactive0') {
				//console.log(UI.clientIP + ' (this client)');
				//console.log(cmd[1] + ' (sent reset)');
				if (cmd[1] == UI.clientIP || cmd[1] == 'server') {
					screenSaver(cmd[0]);
				}
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

// show/hide input source indicator, r44k full screen indicator
function inpSrcIndicator(cmd, msgText) {
	UI.lastSong = '';

	if (cmd.slice(-1) == '1') {
		var msg = '<div id="inpsrc-msg">' + msgText + '</div>';
		var opacity = '0';
		$('#inpsrc-indicator').show();
	}
	else {
		var msg = '';
		var opacity = '';
		$('#inpsrc-indicator').hide();
	}

	$('#inpsrc-indicator').html(msg);
	$('#menu-bottom, #playback-panel, #library-panel, #radio-panel, #browse-panel').css('opacity', opacity);
}

// show/hide screen saver
function screenSaver(cmd) {
	if ($('#inpsrc-indicator').css('display') == 'block') { // r44k exit if input source is active
		return;
	}
	
	if (cmd.slice(-1) == '1') {
		$('#playback-panel, #library-panel, #radio-panel').addClass('hidden');
		$('#menu-bottom, #menu-top').hide();
		$('#ss-coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'alt="Cover art not found"' + '>');
		$('#screen-saver').show()
	}
	else {
		$('#screen-saver').hide();
		$('#playback-panel, #library-panel, #radio-panel').removeClass('hidden');
		$('#menu-bottom, #menu-top').show();
	}
}

// reconnect/reboot/poweroff
function renderReconnect() {
	debugLog('renderReconnect: UI.restart=(' + UI.restart + ')');

	$('#screen-saver').hide();

	if (UI.restart == 'reboot') {
		$('#reboot').show();
	}
	else if (UI.restart == 'poweroff') {
		$('#poweroff').show();
	}
	else {
		$('#reconnect').show(); 
	}
	
	if ($('#mt2').css('display') == 'block') {
		$('#m-countdown').countdown('pause');
	}
	else {
		$('#countdown-display').countdown('pause');
	}
	window.clearInterval(UI.knob);	
	UI.hideReconnect = true;
}

function hideReconnect() {
	//console.log('hideReconnect: (' + UI.hideReconnect + ')');
	$('#reconnect, #reboot, #poweroff').hide();
	UI.hideReconnect = false;
	setTimeout(function() {
		location.reload(true);
	}, 3000);
}

// disable volume knob for mpdmixer == disabled (0dB)
function disableVolKnob() {
	if ($('#mt2').css('display') == 'block') {
		$('#volume-2').attr('data-readOnly', 'true');
		$('#volumedn-2, #volumeup-2').prop('disabled', true); // r44d
		$('#btn-mvol-popup').hide();
		$('#msingle').show();
		$('#mvol-progress').css('width', '100%');
	}
	else {
		$('#volume, #ssvolume').attr('data-readOnly', 'true');
		$('#volumedn, #volumeup, #ssvolup, #ssvoldn').prop('disabled', true); //r44d
	}

	$('.volume-display,#ssvolume,#volumeup,#volumedn').css('opacity', '.3'); // r44f add up/dn buttons
	$('.volume-display,#ssvolume').text('0dB');
}

// called from engineCmd()
// when last item in laylist finishes just update a few things
function updPlayCtls() {
	if ($('#mt2').css('display') == 'block') {
		$('#mplay i').removeClass('fas fa-pause').addClass('fas fa-play');
		$('#m-total').html(updTrackTime(0));
	}
	else {
		$('#play i').removeClass('fas fa-pause').addClass('fas fa-play');
        $('#total').html(updKnobSongTime(0));
	}

	refreshTimeKnob()
	$('.playlist li.active').removeClass('active');	
	$('.ss-playlist li.active').removeClass('active');	
	$('#ssplay i').removeClass('fas fa-pause').addClass('fas fa-play');
}

// update UI volume and mute only 
function renderUIVol() {	
	debugLog('renderUIVol');
	// load session vars (required for multi-client)
	var resp = sendMoodeCmd('GET', 'readcfgsystem');
	if (resp !== false) {
		SESSION.json = resp;
	}

	// disabled volume, 0dB output
	if (MPD.json['volume'] == '-1' || typeof(MPD.json['volume']) == 'undefined') { // r44f add typeof
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
		$('.volume-display, #ssvolume').text(SESSION.json['volknob']);
		// update mobile volume 
		if ($('#mt2').css('display') == 'block') {
	    	$('#volume-2').val(SESSION.json['volknob']).trigger('change');
			$('#mvol-progress').css('width', SESSION.json['volknob'] + '%');
		}
	   	// update mute and ss mute state
		if (SESSION.json['volmute'] == '1') {
			$('.volume-display').css('opacity', '.3');
			$('.volume-display, #ssvolume').text('mute');
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
	var resp = sendMoodeCmd('GET', 'readcfgsystem');
	if (resp !== false) {
		SESSION.json = resp;
	}

	// reset to default
	$('#playlist, #playbtns, #volknob, #volbtns, #togglebtns, .covers').css('opacity', '');
	$('.covers').show();

	// disabled volume, 0dB output
	if (MPD.json['volume'] == '-1' || typeof(MPD.json['volume']) == 'undefined') { // r44f add typeof
		disableVolKnob();
	}
	// hardware or software volume
	else {
		// update volume knob, ss volume
    	$('#volume').val(SESSION.json['volknob']).trigger('change');
		$('.volume-display, #ssvolume').text(SESSION.json['volknob']);
		// update mobile volume 
		if ($('#mt2').css('display') == 'block') {
		    $('#volume-2').val(SESSION.json['volknob']).trigger('change');
			$('#mvol-progress').css('width', SESSION.json['volknob'] + '%');
		}

	   	// update mute state
		if (SESSION.json['volmute'] == '1') {
			$('.volume-display').css('opacity', '.3');
			$('.volume-display, #ssvolume').text('mute');
		}
		else {
			$('.volume-display').css('opacity', '');
			$('.volume-display, #ssvolume').text(SESSION.json['volknob']);
		}
	}

	// playback controls, playlist highlight
    if (MPD.json['state'] === 'play') {
		$('#ssplay i').removeClass('fas fa-play').addClass('fas fa-pause');
		if ($('#mt2').css('display') == 'block') {
			$('#mplay i').removeClass('fas fa-play').addClass('fas fa-pause');
	        $('#m-total').html(updTrackTime(MPD.json['time']));
		}
		else {
			$('#play i').removeClass('fas fa-play').addClass('fas fa-pause');
	        $('#total').html(updKnobSongTime(MPD.json['time']));
		}
		$('.playlist li.active').removeClass('active');
        $('.playlist li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('active');		
		$('.ss-playlist li.active').removeClass('active');
        $('.ss-playlist li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('active');		
    } 
	else if (MPD.json['state'] === 'pause' || MPD.json['state'] === 'stop') {
		$('#ssplay i').removeClass('fas fa-pause').addClass('fas fa-play');
		if ($('#mt2').css('display') == 'block') {
			$('#mplay i').removeClass('fas fa-pause').addClass('fas fa-play');
			$('#m-total').html(updTrackTime(MPD.json['time']));
		}
		else {
			$('#play i').removeClass('fas fa-pause').addClass('fas fa-play');
	        $('#total').html(updKnobSongTime(MPD.json['time']));
		}
    }

	//console.log('UI.lastHash: ' + UI.lastHash); 
	//console.log('MPD.json: ' + MPD.json['cover_art_hash']);
	// prevent unnecessary image reloads: clicking on same item, play/pause, r44a same cover image
	if (MPD.json['file'] !== UI.lastSong && MPD.json['cover_art_hash'] !== UI.lastHash) {
		debugLog(MPD.json['coverurl']);
		$('#coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'data-adaptive-background="1" alt="Cover art not found"' + '>');
		// cover backdrop
		if(SESSION.json['cover_backdrop'] == 'Yes') {
			$('#cover-backdrop').html('<img class="ss-backdrop" ' + 'src="' + MPD.json['coverurl'] + '">');
			$('#cover-backdrop').css('filter', 'blur(' + SESSION.json['cover_blur'] + ')');
			$('#cover-backdrop').css('transform', 'scale(' + SESSION.json['cover_scale'] + ')');
		}
		// screen saver
		$('#ss-backdrop').html('<img class="ss-backdrop" ' + 'src="' + MPD.json['coverurl'] + '">');
		if ($('#screen-saver').css('display') == 'block') {
			$('#ss-coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'alt="Cover art not found"' + '>');
		}
		
		// adaptive UI theme engine
		if (SESSION.json['adaptive'] == 'Yes' && MPD.json['coverurl'].indexOf('default-cover-v6') === -1) {
		//if (SESSION.json['adaptive'] == 'Yes' && MPD.json['coverurl'].indexOf('default-cover-v6') == '-1') { // bug =='-1'
			var ab2 = { parent: '#playback-panel',
				shadeVariation: 'blend',
				shadePercentage: 0.15,
				exclude: [ 'rgb(0,0,0)', 'rgba(255,255,255)' ],
				shadeColors: {
					light: 'rgb(128,128,128)',
					dark: 'rgb(224,224,224)'
				},
				normalizeTextColor: true,
	    		normalizedTextColors: {
	      		  light: '#eee',
	      		  dark: '#333'
			    }
			};
			$.adaptiveBackground.run(ab2);
		}
		else {
			adaptMcolor = themeMcolor;
			adaptMback = themeMback;
			adaptColor = themeColor;
			adaptBack = themeBack;
			setColors();
		}
		$('.btnlist-top-db .btnlist-top-ra').css({backgroundColor: themeMback});
		$('.btnlist-top-db .btnlist-top-ra').css({color: themeMcolor});
	}
		
	// extra metadata
	if (SESSION.json['xtagdisp'] === 'Yes') {
		var extraTags = MPD.json['track'] ? 'Track ' + MPD.json['track'] : '';
		extraTags += MPD.json['disc'] ? (MPD.json['disc'] != 'Disc tag missing' ? '&nbsp;&bull; Disc ' + MPD.json['disc'] : '') : ''; // r44h
		extraTags += MPD.json['date'] ? '&nbsp;&bull; Year ' + (MPD.json['date']).slice(0,4) : '';
		extraTags += MPD.json['composer'] ? '&nbsp;&bull; ' + MPD.json['composer'] : '';
		
		if (MPD.json['artist'] === 'Radio station') {
			extraTags += MPD.json['bitrate'] ? '&nbsp;&bull; ' + MPD.json['bitrate'] : '';
		}
		else {
			// with just sample rate
			//extraTags += MPD.json['encoded'] ? (MPD.json['encoded'] != 'Unknown' ? '&nbsp&bull; ' + MPD.json['encoded'] : '') : '';
			// with bitrate and audio format added, see getEncodedAt()
			extraTags += MPD.json['encoded'] ? (MPD.json['encoded'] != 'Unknown' ? '&nbsp;&bull;&nbsp;' + MPD.json['encoded'] : '') : '';
		}
		$('#extratags, #ss-extratags').html(extraTags);	
	}
	else {
		$('#extratags, #ss-extratags').html('');	
	}
	
	// default metadata
	if (MPD.json['album']) { // artist - album format
		var artistPlaceholder = MPD.json['artist'] == 'Radio station' ? '' : MPD.json['artist'] + ' - ';
		$('#currentalbum, #ss-currentalbum').html(artistPlaceholder + MPD.json['album']);
	}
	else {
		$('#currentalbum, #ss-currentalbum').html('');
	}

	// song title
	if (MPD.json['title'] === 'Streaming source' || MPD.json['coverurl'] === UI.defCover || $('#mt2').css('display') == 'block') {
		$('#currentsong').html(MPD.json['title']);
	}
	// add search url, see corresponding code in renderPlaylist()
	else {
		$('#currentsong').html(genSearchUrl(MPD.json['artist'], MPD.json['title'], MPD.json['album']));
	}
	$('#ss-currentsong').html(MPD.json['title']);

    // scrollto if song change
    if (MPD.json['file'] !== UI.lastSong) {
        countdownRestart(0);
        if ($('#open-playback-panel').hasClass('active')) {
	        customScroll('pl', parseInt(MPD.json['song']));
        }
        if ($('#ss-hud').css('display') == 'block') {
	        customScroll('ss-pl', parseInt(MPD.json['song']));
        }
    }
	
	// store last song file and cover hash
	UI.lastSong = MPD.json['file'];
	UI.lastHash = MPD.json['cover_art_hash']; // r44a

	if ($('#mt2').css('display') == 'block') {
	    MPD.json['repeat'] === '1' ? $('#mrepeat').addClass('btn-primary') : $('#mrepeat').removeClass('btn-primary');
	    MPD.json['consume'] === '1' ? $('#mconsume').addClass('btn-primary') : $('#mconsume').removeClass('btn-primary');
	    MPD.json['single'] === '1' ? $('#msingle').addClass('btn-primary') : $('#msingle').removeClass('btn-primary');
		if (SESSION.json['ashufflesvc'] === '1') {
			if (SESSION.json['ashuffle'] ==='1') {
				$('#mrandom').addClass('btn-primary')
				$('#mconsume').addClass('btn-primary')
			}
			else {
				$('#mrandom').removeClass('btn-primary');
			}
		}
		else {
		    MPD.json['random'] === '1' ? $('#mrandom').addClass('btn-primary') : $('#mrandom').removeClass('btn-primary');
		}	
	}
	else {
	    MPD.json['repeat'] === '1' ? $('#repeat').addClass('btn-primary') : $('#repeat').removeClass('btn-primary');
	    MPD.json['consume'] === '1' ? $('#consume').addClass('btn-primary') : $('#consume').removeClass('btn-primary');
	    MPD.json['single'] === '1' ? $('#single').addClass('btn-primary') : $('#single').removeClass('btn-primary');
		if (SESSION.json['ashufflesvc'] === '1') {
			if (SESSION.json['ashuffle'] ==='1') {
				$('#random').addClass('btn-primary')
				$('#consume').addClass('btn-primary')
			}
			else {
				$('#random').removeClass('btn-primary');
			}
		}
		else {
		    MPD.json['random'] === '1' ? $('#random').addClass('btn-primary') : $('#random').removeClass('btn-primary');
		}
	}

	// time knob and timeline
	// count up or down, radio stations always have song time = 0
	if (SESSION.json['timecountup'] === '1' || parseInt(MPD.json['time']) === 0) {
		if ($('#mt2').css('display') == 'block') {
			refreshMTimer(parseInt(MPD.json['elapsed']), parseInt(MPD.json['time']), MPD.json['state']);
		}
		else {
			refreshTimer(parseInt(MPD.json['elapsed']), parseInt(MPD.json['time']), MPD.json['state']);
		}
	}
	else {
		if ($('#mt2').css('display') == 'block') {
			refreshMTimer(parseInt(MPD.json['time'] - parseInt(MPD.json['elapsed'])), 0, MPD.json['state']);
		}
		else {
			refreshTimer(parseInt(MPD.json['time'] - parseInt(MPD.json['elapsed'])), 0, MPD.json['state']);
		}
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

	// update playlist
	renderPlaylist(); // r44d rm conditional and always update the playlist

	// ensure renderer overlays get applied in case mpd ui updates get there first after browser refresh
	// bluetooth renderer
	if (SESSION.json['btactive'] == '1') {
		inpSrcIndicator('btactive1', 'Bluetooth Active');
	}
	// airplay renderer
	if (SESSION.json['airplayactv'] == '1') {
		inpSrcIndicator('aplactive1', 'Airplay Active');
	}
	// spotify renderer
	if (SESSION.json['spotactive'] == '1') {
		inpSrcIndicator('spotactive1', 'Spotify Active');
	}
	// squeezelite renderer
	if (SESSION.json['slactive'] == '1') {
		inpSrcIndicator('slactive1', 'Squeezelite Active');
	}

	// show/clear db update in-progress icon
	if (typeof(MPD.json['updating_db']) == 'undefined' || MPD.json['idle_timeout_event'] == 'changed: database') {
		$('.open-library-panel').html('Music');
	}
	else {
		$('.open-library-panel').html('<i class="fal fa-sync fa-spin dbupdate-spinner"></i>Music');
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
        
        // save item num for use in delete/move modals
        UI.dbEntry[4] = typeof(data.length) === 'undefined' ? 0 : data.length;

		// format playlist items
        if (data) {
            for (i = 0; i < data.length; i++) {
			//console.log(data[i].file);

	            // item active state
                if (i == parseInt(MPD.json['song'])) {
                    output += '<li id="pl-' + (i + 1) + '" class="active clearfix">';
                }
				else {
                    output += '<li id="pl-' + (i + 1) + '" class="clearfix">';
                }
				// action menu
				output += '<div class="pl-action">';
				output += '<a class="btn" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu-playlist-item">' + (typeof(data[i].Time) == 'undefined' ? '<em class="songtime"></em>' : ' <em class="songtime">' + formatSongTime(data[i].Time) + '</em>') + '<br><i class="fas fa-ellipsis-h"></i></a></div>';

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
							if (data[i].Title.substr(0, 4) === 'http' || MPD.json['coverurl'] === UI.defCover || $('#mt2').css('display') == 'block') {
								$('#currentsong').html(data[i].Title);
							}
							// add search url, see corresponding code in renderUI()
							else {
								$('#currentsong').html(genSearchUrl(data[i].Artist, data[i].Title, data[i].Album));
							}
							$('#ss-currentsong').html(data[i].Title);
						}
					}
					
					// line 2, station name
					output += ' <span class="pll2">';
					output += '<i class="fas fa-microphone"></i> ';
					
					if (typeof(RADIO.json[data[i].file]) === 'undefined') {
						output += (typeof(data[i].Name) === 'undefined') ? 'Radio station' : data[i].Name;
					}
					else {
						output +=  RADIO.json[data[i].file]['name'];
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

		// adjustments for mobile
		if (data.length < 3) {
			$('#playlist').css('padding-bottom', '6em');
			$('#playlistSave').css('padding-bottom', '5em');
		}
		else {
			$('#playlist').css('padding-bottom', '');
			$('#playlistSave').css('padding-bottom', '');
		}

		// render playlist
        $('ul.playlist, ul.ss-playlist').html(output);
		if (output) {
			$('#playlistSave').css('display', 'block');
		}
		else {
			$('#playlistSave').css('display', 'none');
		}
    });
}

// MPD commands for database, playlist, radio stations, saved playlists
function mpdDbCmd(cmd, path) {
	var cmds = ['add', 'play', 'clradd', 'clrplay', 'addall', 'playall', 'clrplayall', 'update'];
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
	else if (cmd == 'addstation' || cmd == 'updstation') {
		var arg = path.split('\n');
		var rtn = sendMoodeCmd('POST', 'addstation', {'path': arg[0], 'url': arg[1]});
		$.post('command/moode.php?cmd=lsinfo', { 'path': 'RADIO' }, function(data) {renderBrowse(data, 'RADIO');}, 'json');
	}
	else if (cmd == 'delstation') {
		var rtn = sendMoodeCmd('POST', cmd, {'path': path});
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
		var results = (data.length) ? data.length : '0';
		var s = (data.length == 1) ? '' : 's';
		var text = results + ' item' + s;
		$('#db-search-results').show();
		$('#db-search-results').html('<a href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu-db-search-results">' + text +'</a>');
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
	//console.log('renderRadui(): path=(' + path + ')');
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
	$('#ra-search-keyword').val('');
	$('#ra-filter').val('');
	
	// populate radio panel lazy, add some smarts for folders
	//console.log('loop1');
	numItems = data.length;
	for (var i = 0; i < 50; i++) {
		if (i == numItems) {/*console.log('break1');*/ break;}
		output = formatBrowseData(data, path, i, 'radio_panel');
	 	dbList.append(output);
	}
	
	if (numItems >= 50) {
		setTimeout(function() {
			//console.log('loop2');
			for (var i = 50; i < 100; i++) {
				if (i >= numItems) {/*console.log('break2');*/ break;}
				output = formatBrowseData(data, path, i, 'radio_panel');
			 	dbList.append(output);
			}
		}, 2000);
	}
	else {
		radioRendering = false;
	}

	if (numItems >= 100) {
		setTimeout(function() {	
			//console.log('loop3');
			for (var i = 100; i < numItems; i++) {
				if (i >= numItems) {/*console.log('break3');*/ radioRendering = false; break;}
				output = formatBrowseData(data, path, i, 'radio_panel');
			 	dbList.append(output);
			}
			radioRendering = false;
		}, 4000);
	}
	else {
		radioRendering = false;
	}
}

// format for Browse panel
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
		// for cue sheet and future extensions
		var fileExt = data[i].file.substr(data[i].file.lastIndexOf('.') + 1).toLowerCase();
	
		// song files
		if (typeof data[i].Title != 'undefined') {
			output = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="' + data[i].file + '">'
			// click on item line for menu
			output += '<div class="db-icon db-song db-action">';
			output += '<a class="btn" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu-folder-item">';
			output += '<i class="fas fa-music sx db-browse" style="float:left;"></i></a></div>';				
			output += '<div class="db-entry db-song">' + data[i].Title + ' <em class="songtime">' + data[i].TimeMMSS + '</em>';
			output += ' <span>' + data[i].Artist + ' - ' + data[i].Album + '</span></div></li>';
		}
		// radio stations, playlist items
		else {
			output = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';
			// remove file extension, except if its url (savedplaylist can contain url's)
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
					output += '"><div class="db-icon db-song db-browse db-action"><a class="sx" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu-radio-item"><img src="' + imgurl  + '"></a></div><div class="db-entry db-song db-browse">';
				}
				else {
					output += '"><div class="db-icon db-song db-browse db-action"><a class="btn" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu-radio-item"><i class="fas fa-microphone sx db-browse" style="float:left;"></i></a></div><div class="db-entry db-song db-browse">';
				}
				itemType = '';
			}
			// cue sheet, use song file action menu
			else if (fileExt == 'cue') {
				output += '"><div class="db-icon db-song db-browse db-action"><a class="btn" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu-folder-item"><i class="fas fa-list-ul icon-root sx" style="float:left;"></i></a></div><div class="db-entry db-song db-browse">';
				itemType = 'Cue sheet';
			}
			// different icon for song file vs radio station in saved playlist
			// click on item line for menu
			else {
				if (data[i].file.substr(0,4) == 'http') {
					output += '"><div class="db-icon db-song db-browse db-action"><a class="btn" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu-savedpl-item" style="width:100vw;height:2em;"><i class="fas fa-microphone sx db-browse" style="float:left;"></i></a></div><div class="db-entry db-song db-browse">';
					itemType = typeof(RADIO.json[data[i].file]['name']) === 'undefined' ? 'Radio station' : RADIO.json[data[i].file]['name']; // r44a
				} else {
					output += '"><div class="db-icon db-song db-browse db-action"><a class="btn" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu-savedpl-item" style="width:100vw;height:2em;"><i class="fas fa-music sx db-browse" style="float:left;"></i></a></div><div class="db-entry db-song db-browse">';
					itemType = 'Song file';
				}
			}
			output += filename.replace(path + '/', '');
			output += ' <span>';
			output += itemType;
			output += '</span></div></li>';
		}
	}
	// saved playlists 
	else if (typeof data[i].playlist != 'undefined') {
		// skip .wv (WavPack) files, apparently they can contain embedded playlist
		// flac files can also contain embedded playlists - and now they are handled as such, automatically
		fileext = data[i].playlist.substr(data[i].playlist.lastIndexOf('.') + 1).toLowerCase();
		if (['wv', 'flac'].indexOf(fileext) >= 0 ) {
			output = '';
		}
		else {
			output = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="' + data[i].playlist + '">';
			output += '<div class="db-icon db-action">';
			output += '<a class="btn" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu-savedpl-root">';
			output += '<i class="fas fa-list-ul icon-root sx"></i></a></div>';
			output += '<div class="db-entry db-savedplaylist db-browse">' + data[i].playlist;
			output += '</div></li>';
		}
	}
	// directories
	else {
		output = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';
		output += data[i].directory;
		if (path != '') {
			if (data[i].directory.substr(0, 5) == 'RADIO' && panel == 'radio_panel') {
				output += '"><div class="db-icon db-radiofolder-icon db-browse db-action"><a class="sx" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu"><img src="../images/radiofolder.jpg"></a></div><div class="db-entry db-radiofolder db-browse">';
			}
			else {
				output += '"><div class="db-icon db-browse db-action"><a class="btn" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu"><i class="fas fa-folder sx"></i></a></div><div class="db-entry db-folder db-browse">';
			}
		}
		else {
			output += '"><div class="db-icon db-action"><a class="btn" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu-root"><i class="fas fa-hdd icon-root sx"></i></a></div><div class="db-entry db-folder db-browse">';
		}
		output += data[i].directory.replace(path + '/', '');
		output += '</div></li>';
	}

	return output;
}

// return song time or 00:00 (radio station) plus the time count direction icon
function updKnobSongTime(mpdTime) {
	var songTime, result, output;
	
	result = formatSongTime(mpdTime);	
	songTime = result === '' ? '00:00' : result;

	output = songTime + (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0 ? '<i class="fas fa-caret-up countdown-caret"></i>' : '<i class="fas fa-caret-down countdown-caret"></i>');

    return output;
}

// equivalent of updKnobSongTime for the time track
function updTrackTime(mpdTime) {
	var songTime, result, output;
	
	result = formatSongTime(mpdTime);	
	songTime = result === '' ? '00:00' : result;

	output = songTime;

    return output;
}

// update time knob
function refreshTimer (startFrom, stopTo, state) {	
	var tick = 3; // call watchCountdown() every tick secs

    if (state == 'play' || state == 'pause') {
        $('#countdown-display').countdown('destroy');
		if (SESSION.json['timecountup'] == "1" || parseInt(MPD.json['time']) == 0) {
	    	$('#countdown-display').countdown({since: -(startFrom), onTick: watchCountdown, tickInterval: tick, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
		else {
	        $('#countdown-display').countdown({until: startFrom, onTick: watchCountdown, tickInterval: tick, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
	    if (state == 'pause') {
	        $('#countdown-display').countdown('pause');
		}
    } 
	else if (state == 'stop') {
        $('#countdown-display').countdown('destroy');
		if (SESSION.json['timecountup'] == "1" || parseInt(MPD.json['time']) == 0) {
        	$('#countdown-display').countdown({since: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
		else {
	        $('#countdown-display').countdown({until: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
        $('#countdown-display').countdown('pause');
    }
}

// update mobile timeline
function refreshMTimer (startFrom, stopTo, state) {
    if (state == 'play' || state == 'pause') {
		$('#m-countdown').countdown('destroy');
		if (SESSION.json['timecountup'] == "1" || parseInt(MPD.json['time']) == 0) {
			$('#m-countdown').countdown({since: -(startFrom), compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
		else {
	        $('#m-countdown').countdown({until: startFrom, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
	    if (state == 'pause') {
	        $('#m-countdown').countdown('pause');
		}
    } 
	else if (state == 'stop') {
        $('#m-countdown').countdown('destroy');
		if (SESSION.json['timecountup'] == "1" || parseInt(MPD.json['time']) == 0) {
        	$('#m-countdown').countdown({since: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
		else {
	        $('#m-countdown').countdown({until: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
        $('#m-countdown').countdown('pause');
    }
}

// onTick callback for automatic font sizing
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
			$('#countdown-display').css('margin-top', '-0.9em');
		}
		else if (period[4] < 10) {
			$('#countdown-display').css('font-size', '1.6em');
			$('#countdown-display').css('margin-top', '-0.85em');
			$('#countdown-display').css('left', '51%');
		}
		else {
			$('#countdown-display').css('font-size', '1.5em');
			$('#countdown-display').css('margin-top', '0.82em');
		}
	}
}

// update time knob, time track
function refreshTimeKnob() {
	var initTime, delta;
	
    window.clearInterval(UI.knob)
    initTime = parseInt(MPD.json['song_percent']);
    delta = parseInt(MPD.json['time']) / 1000;

	if ($('#mt2').css('display') == 'block') {
		$('#timetrack').val(initTime).trigger('change');
	}
	else {
	    $('#time').val(initTime * 10).trigger('change');
	}

    if (MPD.json['state'] === 'play') {
        UI.knob = setInterval(function() {			
			if (!timeSliderMove) {
				$('#timetrack').val(initTime).trigger('change');
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

// stop timeline updates
function stopTimer() {
	timeSliderMove = true;
	$('#m-countdown').countdown('pause');
}
// handle timeline slider move
function sliderTime(startFrom) {
	var delta, time;
	time = parseInt(MPD.json['time']);
	delta = time / 100;
	var seekto = Math.floor((startFrom * delta));
	if (seekto > time - 2) { seekto = time - 2;}
	sendMpdCmd('seek ' + MPD.json['song'] + ' ' + seekto);
	timeSliderMove = false;
}

// fill timeline color in as song plays
$('input[type="range"]').change(function() {
    var val = ($(this).val() - $(this).attr('min')) / ($(this).attr('max') - $(this).attr('min'));
	val = val * 100;
	if (val < 50) {
		val = val + 1;
	} else {
		val = val - 1;
	}
    
    $(this).css('background-image',
        'linear-gradient(to right, '
        + 'var(--trackfill) 0%, '
		+ 'var(--trackfill) ' + val +'%, '
		+ 'var(--timecolor) ' + val +'%, '
		+ 'var(--timecolor) 100%'
        + ')'
        );
});

// format song time for knob #total and #countdown-display and for playlist items
function formatSongTime(seconds) {
	var output, hours, minutes, hh, mm, ss;

    if(isNaN(parseInt(seconds))) {
    	output = ''; // so song time is not displayed for radio stations listed in Playlist
    } else {
	    hours = Math.floor(seconds / 3600);
    	minutes = Math.floor(seconds / 60);
    	minutes = (minutes < 60) ? minutes : (minutes % 60);
    	seconds = seconds % 60;
    	
    	hh = (hours > 0) ? (hours + ':') : '';
    	mm = (minutes < 10) ? ('0' + minutes) : minutes;
    	ss = (seconds < 10) ? ('0' + seconds) : seconds;
    	
    	output = hh + mm + ':' + ss;
    }
    
    return output;
}

// format total time for all songs in library
function formatTotalTime(seconds) {
	var output, hours, minutes, hh, mm, ss;
	
    if(isNaN(parseInt(seconds))) {
    	output = '';
    } else {
	    hours = Math.floor(seconds / 3600);
    	minutes = Math.floor(seconds / 60);
    	minutes = (minutes < 60) ? minutes : (minutes % 60); 
    	
    	if (hours == 0) {
	    	hh = '';
    	} else if (hours == 1) {
	    	hh = hours + ' hour';
    	} else {
	    	hh = hours + ' hours';
    	}

    	if (minutes == 0) {
	    	mm = '';
    	} else if (minutes == 1) {
	    	mm = minutes + ' minute';
    	} else {
	    	mm = minutes + ' minutes';
    	}
		
		if (hours > 0) {
			if (minutes > 0) {
				output = hh + ', ' + mm;
			} else {
				output = hh;
			}
		} else {
			output = mm;			
		}
    }

    return output;
}

function countdownRestart(startFrom) {
	var tick = 3; // call watchCountdown() every tick secs

	if ($('#mt2').css('display') == 'block') {
	    $('#m-countdown').countdown('destroy');
	    $('#m-countdown').countdown({since: (startFrom), compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	}
	else {
	    $('#countdown-display').countdown('destroy');
	    $('#countdown-display').countdown({since: (startFrom), onTick: watchCountdown, tickInterval: tick, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	} 
}

// volume control
function setVolume(level, event) {
	level = parseInt(level); // ensure numeric

	// unmuted, set volume (incl 0 vol)
	if (SESSION.json['volmute'] == '0') {
		SESSION.json['volknob'] = level.toString();
		// update sql value and issue mpd setvol in one round trip
		var result = sendVolCmd('POST', 'updvolume', {'volknob': SESSION.json['volknob']});
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

// r42p scroll item so visible
function customScroll(list, itemNum, speed) {
	//console.log('list=' + list + ', itemNum=(' + itemNum + '), speed=(' + speed + ')');
	var listSelector, scrollSelector, chDivisor, centerHeight, scrollTop, itemHeight, scrollCalc, scrollOffset, itemPos;
	
	speed = typeof(speed) === 'undefined' ? 500 : speed;
	
	// params
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
	else if (list == 'albums' || list == 'albumcovers') {		
		listSelector = list == 'albums' ? '#lib-album' : '#lib-albumcover';
		scrollSelector = listSelector;
		chDivisor = 6;
		itemNum = list == 'albums' ? itemNum : itemNum + 1;
	}
	else if (list == 'artists') {		
		listSelector = '#lib-artist';
		scrollSelector = listSelector;
		chDivisor = 6;
	}

	// item position
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
	//console.log('scrollSelector=' + scrollSelector + ', itemPos=' + itemPos + ', chDivisor=' + chDivisor + ', centerHeight=' + centerHeight + ', scrollTop=' + scrollTop + ', scrollCalc=' + scrollCalc);

	if (scrollCalc > scrollTop) {
	    scrollOffset = '+=' + Math.abs(scrollCalc - scrollTop) + 'px';
	}
	else {
	    scrollOffset = '-=' + Math.abs(scrollCalc - scrollTop) + 'px';
	}

	if (scrollCalc > 0) {
	    $(scrollSelector).scrollTo(scrollOffset, speed);
	}
	else {
	    $(scrollSelector).scrollTo(0, speed);
	}
}

// r43p load library
function loadLibrary() {
	$.post('command/moode.php?cmd=loadlib', {}, function(data) {
		$('#lib-loader').hide();
		$('#lib-content').show();
		renderLibrary(data);
	}, 'json');
	libRendered = true;
}

// render library
function renderLibrary(data) {
    fullLib = data;
	//debugLog(fullLib);

	// generate library array
    filterLib();
    
    // store song count
    LIB.totalSongs = allSongs.length;
    
    // start by rendering genres
    renderGenres();
}

// generate library array
function filterLib() {
    allGenres = [];
    allArtists = [];
    allAlbums = [];
    allAlbumsTmp = [];
    allAlbumCovers = [];
    allSongs = [];
	allSongsDisc = []; // r44g
    
    var needReload = false;

    for (var genre in fullLib) {
	    // AG fix dup artist appearing when Artist assigned to multiple Genres (don't push if already exist)
        if (allGenres.indexOf(genre) < 0) {
            allGenres.push(genre);
        }

        if (LIB.filters.genres.length == 0 || LIB.filters.genres.indexOf(genre) >= 0) {
            for (var artist in fullLib[genre]) {
                if (allArtists.indexOf(artist) < 0) {
                    allArtists.push(artist);
                }

                if (LIB.filters.artists.length == 0 || LIB.filters.artists.indexOf(artist) >= 0) {
                    for (var album in fullLib[genre][artist]) {
						var md5 = $.md5(fullLib[genre][artist][album][0].file.substring(0,fullLib[genre][artist][album][0].file.lastIndexOf('/')));
						var objAlbum = {'album': album, 'artist': artist, 'compilation': '0', 'imgurl': '/imagesw/thmcache/' + encodeURIComponent(md5) + '.jpg'}; // r44d repl genre with compilation
                        allAlbumsTmp.push(objAlbum);

                        if (LIB.filters.albums.length == 0 || LIB.filters.albums.indexOf(keyAlbum(objAlbum)) >= 0) {
                            for (var i in fullLib[genre][artist][album]) {
                                var song = fullLib[genre][artist][album][i];
                                song.album = album;
                                song.artist = artist;
                                allSongs.push(song);
                            }
                        }
                    }
                }
            }
        }
    }

    // check filters validity
    var newFilters = checkFilters(LIB.filters.albums, allAlbumsTmp, function(o) { return keyAlbum(o); });

    if (newFilters.length != LIB.filters.albums.length) {
        needReload = true;
        LIB.filters.albums = newFilters;
    }

    newFilters = checkFilters(LIB.filters.artists, allArtists, function(o) { return o; });

    if (newFilters.length != LIB.filters.artists.length) {
        needReload = true;
        LIB.filters.artists = newFilters;
    }

	if (needReload) {
		filterLib();
	}
	else {
		// sort Genres, Artists, Albums
		allGenres.sort();	
		try {
			// natural ordering 
			var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
			allArtists.sort(function(a, b) {
				return collator.compare(removeArticles(a), removeArticles(b));
			});
			allAlbumsTmp.sort(function(a, b) {
				return collator.compare(removeArticles(a['album']), removeArticles(b['album']));
			});
		}			
		catch (e) {
			// fallback to default ordering
			allArtists.sort(function(a, b) {
				a = removeArticles(a.toLowerCase());
				b = removeArticles(b.toLowerCase());
				return a > b ? 1 : (a < b ? -1 : 0);
			});
			allAlbumsTmp.sort(function(a, b) {
				a = removeArticles(a['album'].toLowerCase());
				b = removeArticles(b['album'].toLowerCase());
				return a > b ? 1 : (a < b ? -1 : 0);
			});
		}

		// r44d perform compilation rollup if indicated
		if (SESSION.json['compilation_rollup'] == 'Yes') {
			compilationRollup();
		}
		else {
			allAlbums = allAlbumsTmp;
		}

		// sort album covers by artist 
		allAlbumCovers = allAlbums.slice();
		try {
			// natural ordering
			allAlbumCovers.sort(function(a, b) {
				return (collator.compare(removeArticles(a['artist']), removeArticles(b['artist'])) || collator.compare(removeArticles(a['album']), removeArticles(b['album'])));
			});
		}
		catch (e) {
			// fallback to default ordering
			allAlbumCovers.sort(function(a, b) {
				var x1 = removeArticles(a['artist']).toLowerCase(), x2 = removeArticles(b['artist']).toLowerCase(); // r44d
				var y1 = removeArticles(a['album']).toLowerCase(), y2 = removeArticles(b['album']).toLowerCase();
				return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
			});
		}
    }
	//console.log(allGenres);
	//console.log(allArtists);
	//console.log(allAlbums);
	//console.log(allSongs);
}

// r44d compilation rollup
function compilationRollup() {
	//console.log('compilation rollup ' + allAlbumsTmp.length)
	// NOTE the "compilation" tag is used in the onClick for Albums
	var compAlbumStored = false;
	var objCompilationAlbum = {'album': '', 'artist': '', 'compilation': '1'};
	var excludeAlbums = SESSION.json['compilation_excludes'].split(',');

	// start at 1 since first album starts at 0
	if (allAlbumsTmp.length > 1) {			
		for (var i = 1; i < allAlbumsTmp.length; i++) {
			// current = prev -> compilation album and album name not on exclusion list
			if (allAlbumsTmp[i].album == allAlbumsTmp[i - 1].album && excludeAlbums.indexOf(allAlbumsTmp[i].album.toLowerCase()) == -1) {
				// store compilation album only once (rollup)
				if (compAlbumStored == false) {
	                objCompilationAlbum = {'album': allAlbumsTmp[i].album, 'artist': 'Various Artists', 'compilation': '1', 'imgurl': allAlbumsTmp[i].imgurl};
	                allAlbums.push(objCompilationAlbum);
	                compAlbumStored = true;
					//console.log('compilation album=' + objCompilationAlbum.album);
				}
			}
			// current != prev -> lets check 
			else {
				// prev = last compilation album stored
				if (allAlbumsTmp[i - 1].album == objCompilationAlbum.album) {
					// don't store it, just reset and move on
					objCompilationAlbum = {'album': '', 'artist': '', 'compilation': '1'};
				}
				// prev is a regular album, store it 
				else {
	                var objRegularAlbum = {'album': allAlbumsTmp[i - 1].album, 'artist': allAlbumsTmp[i - 1].artist, 'compilation': '0', 'imgurl': allAlbumsTmp[i - 1].imgurl};
					allAlbums.push(objRegularAlbum);
					//console.log('regular album=' + objRegularAlbum.album);
				}
				// last album
				if (i == allAlbumsTmp.length - 1) {
					// store last album
					var objRegularAlbum = {'album': allAlbumsTmp[i].album, 'artist': allAlbumsTmp[i].artist, 'compilation': '0', 'imgurl': allAlbumsTmp[i].imgurl};
					allAlbums.push(objRegularAlbum);
					//console.log('regular album=' + objRegularAlbum.album);
				}
				// reset flag
				compAlbumStored = false;
			}
		}	
	}
	// only one album in list
	else if (allAlbumsTmp.length == 1) {
		var objRegularAlbum = {'album': allAlbumsTmp[0].album, 'artist': allAlbumsTmp[0].artist, 'compilation': '0', 'imgurl': allAlbumsTmp[0].imgurl}; // store the one and only album
		allAlbums.push(objRegularAlbum);
	}
	// array length is 0 (empty) -> no music source defined
	else {
		// nop
	}
}

// remove artcles from beginning of string
function removeArticles(string) {
	// fixes bad sort of double-byte characters (Japanese, etc)
	return string.replace(/^(a|an|the) (.*)/gi, '$2');
}

// check for invalid filters
function checkFilters(filters, collection, func) {
    var newFilters = [];
    
    for (var filter in filters) {
        for (var obj in collection) {
            if (filters[filter] == func(collection[obj])) {
                newFilters.push(filters[filter]);
                break;
            }
        }
    }
    return newFilters;
}

// generate album/artist key
function keyAlbum(objAlbum) {
    return objAlbum.album + '@' + objAlbum.artist;
}

// return numeric song time
function parseSongTime (songTime) {
	var time = parseInt(songTime);
	return isNaN(time) ? 0 : time;	
}

// for the meta area cover art
function makeCoverUrl(filepath) {
	/* r44h deprecate
	// r44a indicate to coverart.php its being called from the Lib panel so that it will not generate an image hash
    return '/coverart.php/' + encodeURIComponent(filepath + '?from=makeCoverUrl');
	*/
    return '/coverart.php/' + encodeURIComponent(filepath); // r44h
}

// default post-click handler for lib items
function clickedLibItem(event, item, currentFilter, renderFunc) {
    if (item == undefined) {
        // all
        currentFilter.length = 0;
    }
	else if (event.ctrlKey) {
        currentIndex = currentFilter.indexOf(item);
        if (currentIndex >= 0) {
            currentFilter.splice(currentIndex, 1);
        }
		else {
            currentFilter.push(item);
        }
    }
	else {
        currentFilter.length = 0;
        currentFilter.push(item);
    }
    	    
    filterLib();
    renderFunc();
}

// render genres
var renderGenres = function() {
    var output = '';
    
    for (var i = 0; i < allGenres.length; i++) {
        output += '<li class="clearfix"><div class="lib-entry'
			+ (LIB.filters.genres.indexOf(allGenres[i]) >= 0 ? ' active' : '')
			+ '">' + allGenres[i] + '</div></li>';
    }
    
    $('#genresList').html(output);
	if (UI.libPos[0] == -2) {
		$('#lib-genre').scrollTo(0, 200);
	}
    
    renderArtists();
}

// render artists
var renderArtists = function() {
    var output = '';

    for (var i = 0; i < allArtists.length; i++) {
		// add || allArtists.length = 1 to automatically highlight if only 1 artist in list
        output += '<li class="clearfix"><div class="lib-entry'
			+ ((LIB.filters.artists.indexOf(allArtists[i]) >= 0 || allArtists.length == 1) ? ' active' : '')
			+ '">' + allArtists[i] + '</div></li>';
    }
    
    $('#artistsList').html(output);

	if (UI.libPos[0] == -2) {
		$('#lib-artist').scrollTo(0, 200);
	}
    
    renderAlbums();
}

// render albums
var renderAlbums = function() {
	// clear search filter and results
	$('#lib-album-filter').val('');
	$('#lib-album-filter-results').html('');

    var output = '';
    var output2 = '';
    var tmp = '';
	var defCover = "this.src='images/default-cover-v6.svg'";


    for (var i = 0; i < allAlbums.length; i++) {
		// add || allAlbums.length = 1 to automatically highlight if only 1 album in list
	    if (LIB.filters.albums.indexOf(keyAlbum(allAlbums[i])) >= 0 || allAlbums.length == 1) {
		    tmp = ' active';
		    LIB.albumClicked = true; // for renderSongs() so it can decide whether to display tracks	    
	    }
		else {
		    tmp = '';
	    }

	    output += '<li class="clearfix"><div class="lib-entry'
	    	+ tmp
			+ '">' + allAlbums[i].album + ' <span> ' + allAlbums[i].artist + '</span></div></li>';
        output2 += '<li class="clearfix"><div class="lib-entry'
        	+ tmp
			+ '">' + '<img class="lazy" data-original="' + allAlbumCovers[i].imgurl  + '" width="100" height="100"><div class="albumcover">' + allAlbumCovers[i].album + '</div><span> ' + allAlbumCovers[i].artist + '</span></div></li>';
    }

    $('#albumsList').html(output);
    $('#albumcovers').html(output2);

	// headers clicked
	if (UI.libPos[0] == -2) {
		// only scroll the visible list
		if ($('.library-panel-btn').hasClass('active')) {
			$('#lib-album').scrollTo(0, 200);
		}
		else {
			$('#lib-albumcover').scrollTo(0, 200);
		}
	}

	// start lazy load if on the albums panel
	if ($('.album-panel-btn').hasClass('active')) {
		//console.log('lazyload started');
		$('img.lazy').lazyload({
		    container: $('#lib-albumcover')
		});		
	}

    renderSongs();
}

// render songs
var renderSongs = function(albumPos) {
    var output = '';
	var discNum = '', discDiv = ''; // r44f
	LIB.totalTime = 0;
	
    //if (allSongs.length < LIB.totalSongs) { // only display tracks if less than the whole library
    if (LIB.albumClicked == true) { // only display tracks if album selected
	    LIB.albumClicked = false;

		// r43p sort tracks and files
		// r44f add disc number to sort
		try {
			// natural ordering
			var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
			allSongs.sort(function(a, b) {
				return (collator.compare(a['disc'], b['disc']) || collator.compare(a['tracknum'], b['tracknum']));
			});
		}			
		catch (e) {
			// fallback to default ordering
			allSongs.sort(function(a, b) {
				var x1 = a['disc'], x2 = b['disc'];
				var x1 = a['tracknum'], x2 = b['tracknum'];
				return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
			});
		}

	    for (i = 0; i < allSongs.length; i++) {
			if (allSongs[i].year) {
				var songyear = (allSongs[i].year).slice(0,4);
			} else {
				var songyear = ' ';
			}

			// r44f add disc num and context menu
			if (allSongs[i].disc != discNum) {
				discDiv = '<div id="lib-disc-' + allSongs[i].disc + '" class="lib-disc"><a class="btn" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu-lib-disc">Disc ' + allSongs[i].disc + '</a></div>'
				discNum = allSongs[i].disc;
			}
			else {
				discDiv = '';
			}

			var composer = allSongs[i].composer == 'Composer tag missing' ? '</span>' : '<br><span class="songcomposer">' + allSongs[i].composer + '</span></span>';
			
	        output += discDiv // r44g move outside of <li>
				+ '<li id="lib-song-' + (i + 1) + '" class="clearfix">'
				+ '<div class="lib-entry-song"><span class="songtrack">' + allSongs[i].tracknum + '</span>'
				+ '<span class="songname">' + allSongs[i].title+'</span>'
	        	+ '<span class="songtime"> ' + allSongs[i].time_mmss + '</span>'
	            + '<span class="songartist"> ' + allSongs[i].actual_artist + composer
				+ '<span class="songyear"> ' + songyear + '</span></div>'
	            + '<div class="lib-action"><a class="btn" href="#notarget" title="Click for menu" data-toggle="context" data-target="#context-menu-lib-item"><i class="fas fa-ellipsis-h"></i></a></div>'
	            + '</li>';

				LIB.totalTime += parseSongTime(allSongs[i].time);
	    }
	}
	else {
	    for (i = 0; i < allSongs.length; i++) {
			LIB.totalTime += parseSongTime(allSongs[i].time);
	    }
	}

    $('#songsList').html(output);
	// r44f display disc num if more than 1 disc
	// exceot for case: Album name contains the string '[Disc' which indicates separate albums for each disc
	if (discNum > 1 && !allSongs[0].album.toLowerCase().includes('[disc')) {
		$('.lib-disc').css('display', 'block');
	}

	//console.log('allSongs[0].file=' + allSongs[0].file);
	//console.log('LIB.filters.albums=(' + LIB.filters.albums + ')');
	//console.log('pos=(' + albumPos + ')')
    
  	// Library panel cover art and metadata
	if (allAlbums.length == 1 || LIB.filters.albums != '' || typeof(albumPos) !== 'undefined') {
		$('#lib-coverart-img').html('<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-all">' + 
			'<img class="lib-coverart" src="' + makeCoverUrl(allSongs[0].file) + '" ' + 'alt="Cover art not found"' + '></a>');
		$('#lib-albumname').html(allSongs[0].album);

		// r44d for compilation album when using Artist instead of AlbumArtist list ordering
		if (typeof(albumPos) !== 'undefined') {
			if (albumPos = UI.libPos[0]) {
				artist = allSongs[0].artist;
			}
			else {
				artist = allAlbums[albumPos].artist;
			}
		}
		else {
			artist = allSongs[0].artist;
		}
		$('#lib-artistname').html(artist);

		$('#lib-albumyear').html(allSongs[0].year);
		$('#lib-numtracks').html(allSongs.length + ((allSongs.length == 1) ? ' track, ' : ' tracks, ') + formatTotalTime(LIB.totalTime));
	}
	else {
		if (LIB.filters.genres == '') {
			if (LIB.filters.artists == '') {
				var album = 'Music Library';
				var artist = '';
			}
			else {
				var album = LIB.filters.artists; 
				var artist = '';
			}
		}
		else {
			var album = LIB.filters.genres;
			var artist = LIB.filters.artists;
		}
		$('#lib-coverart-img').html('<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-all">' + '<img class="lib-coverart" ' + 'src="' + UI.defCover + '"></a>');
		$('#lib-albumname').html(album);
		$('#lib-artistname').html(artist);
		$('#lib-albumyear').html('');		
		$('#lib-numtracks').html(allSongs.length + ((allSongs.length == 1) ? ' track, ' : ' tracks, ') + formatTotalTime(LIB.totalTime));
	}
}

// swipe left/right on top columns in library (mobile)
if ($('#mt2').css('display') == 'block') { // r44d
	//console.log('swipe loaded');
	$(function() {
		$("#top-columns").swipe({
		  swipeLeft:function(event, direction, distance, duration, fingerCount) {
			$('#top-columns').animate({left: '-50%'}, 100);
		  }
	  	});
		$("#top-columns").swipe({
		  swipeRight:function(event, direction, distance, duration, fingerCount) {
			$('#top-columns').animate({left: '0%'}, 100);
		  }
		});
	});
}

// click on genres header
$('#genreheader').on('click', '.lib-heading', function(e) {
	LIB.filters.genres.length = 0;
	LIB.filters.artists.length = 0;
	LIB.filters.albums.length = 0;
	UI.libPos.fill(-2);
    clickedLibItem(e, undefined, LIB.filters.genres, renderGenres);
	$("#searchResetLib").hide();
	showSearchResetLib = false;
});
// click on artists header
$('#artistheader').on('click', '.lib-heading', function(e) {
	LIB.filters.artists.length = 0;
	LIB.filters.albums.length = 0;
	UI.libPos.fill(-2);
    clickedLibItem(e, undefined, LIB.filters.artists, renderArtists);    
	$("#searchResetLib").hide();
	showSearchResetLib = false;
});
// click on albums or album covers header
$('#albumheader, #albumcoverheader').on('click', '.lib-heading', function(e) {
	//console.log($(this).parent().attr('id'));
	if ($(this).parent().attr('id') == 'albumcoverheader') {
	    $('#albumcovers .lib-entry').removeClass('active');
		$('#bottom-row').css('display', 'none');
		$('#lib-albumcover').css('height', '100%');
		// r43q
		LIB.filters.artists.length = 0;
		LIB.filters.albums.length = 0;
		UI.libPos.fill(-2);
	    clickedLibItem(e, undefined, LIB.filters.artists, renderArtists);    
	}
	// r43q
	else {
		LIB.filters.albums.length = 0;
		UI.libPos.fill(-2);
	    clickedLibItem(e, undefined, LIB.filters.albums, renderAlbums);
	}
	$("#searchResetLib").hide();
	showSearchResetLib = false;
});
$('#searchResetLib').click(function(e) {
	LIB.filters.albums.length = 0;
	UI.libPos.fill(-2);
    clickedLibItem(undefined, undefined, LIB.filters.albums, renderAlbums);
	$("#searchResetLib").hide();
	showSearchResetLib = false;
});

// click on genre
$('#genresList').on('click', '.lib-entry', function(e) {
    var pos = $('#genresList .lib-entry').index(this);
	UI.libPos[0] = -1 // r43q
    clickedLibItem(e, allGenres[pos], LIB.filters.genres, renderGenres);
});
// click on artist
$('#artistsList').on('click', '.lib-entry', function(e) {
    var pos = $('#artistsList .lib-entry').index(this);
	UI.libPos[0] = -1 // r43q
	UI.libPos[2] = pos; // r43p
    clickedLibItem(e, allArtists[pos], LIB.filters.artists, renderArtists);    
	if ($('#mt2').css('display') == 'block') {
		$('#top-columns').animate({left: '-50%'}, 200);
	}
});

// click on album or album cover
$('#albumsList, #albumcovers').on('click', '.lib-entry', function(e) {
	var itemSelector = $(this).parents('ul').attr('id') == 'albumsList' ? '#albumsList .lib-entry' : '#albumcovers .lib-entry';
    var pos = $(itemSelector).index(this);

	// store positions in array for use in scripts-panels
	if (itemSelector == '#albumsList .lib-entry') {
		UI.libPos[0] = pos;
		UI.libPos[1] = allAlbumCovers.map(function(e) {return e.album;}).indexOf(allAlbums[pos].album);
		var compilation = allAlbums[pos].compilation;
		var albumobj = allAlbums[pos];
		var album = allAlbums[pos].album;
		var artist = allAlbums[pos].artist;
	}
	else {
		UI.libPos[0] = allAlbums.map(function(e) {return e.album;}).indexOf(allAlbumCovers[pos].album);
		UI.libPos[1] = pos;
		var compilation = allAlbumCovers[pos].compilation;
		var albumobj = allAlbumCovers[pos];
		var album = allAlbumCovers[pos].album;
		var artist = allAlbumCovers[pos].artist;
	}

	// check for toggle
	if (itemSelector == '#albumcovers .lib-entry' && album == UI.libAlbum && $('#bottom-row').css('display') == 'flex') {
		$('#bottom-row').css('display', 'none');
		$('#lib-albumcover').css('height', '100%');
	    $(itemSelector).removeClass('active');
		customScroll('albumcovers', UI.libPos[1], 200);
		return;
	}

	LIB.albumClicked = true; // for renderSongs()
    $(itemSelector).removeClass('active');
	$(itemSelector).eq(pos).addClass('active');

	// song list for regular album		
    if (compilation != '1') {
		//console.log('regular album');
	    clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);
	}
	// song list for compilation album		
	else {
		//console.log('compilation album');
		allCompilationSongs = [];
		LIB.filters.albums = [];
		renderFunc = renderSongs;
		filterLib();
		LIB.totalTime = 0;
		
		for (i = 0; i < allSongs.length; i++) {
			if (allSongs[i].album == album) {
				allCompilationSongs.push(allSongs[i]);
				LIB.totalTime += parseSongTime(allSongs[i].time);
			}
		}

		allSongs = allCompilationSongs;
		renderFunc(pos);
	}

	if (itemSelector == '#albumcovers .lib-entry') {
		if (album != UI.libAlbum) {
			$('#bottom-row').css('display', 'flex')
			$('#lib-albumcover').css('height', 'calc(50% - 1.75em)');
		}
		else if ($('#bottom-row').css('display') == 'none') {
			$('#bottom-row').css('display', 'flex')
			$('#lib-albumcover').css('height', 'calc(50% - 1.75em)');
			$(itemSelector).eq(pos).addClass('active');
		}
		else {
			$('#bottom-row').css('display', 'none')
			$('#lib-albumcover').css('height', '100%');
		    $(itemSelector).removeClass('active');
		}	
		customScroll('albumcovers', UI.libPos[1], 200);
	}
	else {
		$('#bottom-row').css('display', 'flex')
	}

	$('#lib-file').scrollTo(0, 200);
	UI.libAlbum = album;
});
// click random album button
$('#random-album, #random-albumcover').click(function(e) {
	var array = new Uint16Array(1); // r43h
	window.crypto.getRandomValues(array);
	pos = Math.floor((array[0] / 65535) * allAlbums.length);

	if ($(this).attr('id') == 'random-album') {
		var itemSelector = '#albumsList .lib-entry';
		var scrollSelector = 'albums';
		UI.libPos[0] = pos;
		UI.libPos[1] = allAlbumCovers.map(function(e) {return e.album;}).indexOf(allAlbums[pos].album);
		var compilation = allAlbums[pos].compilation;
		var albumobj = allAlbums[pos];
		var album = allAlbums[pos].album;
		var artist = allAlbums[pos].artist;
	}
	else {
		var itemSelector = '#albumcovers .lib-entry';
		var scrollSelector = 'albumcovers';
		UI.libPos[0] = allAlbums.map(function(e) {return e.album;}).indexOf(allAlbumCovers[pos].album);
		UI.libPos[1] = pos;
		var compilation = allAlbumCovers[pos].compilation;
		var albumobj = allAlbumCovers[pos];
		var album = allAlbumCovers[pos].album;
		var artist = allAlbumCovers[pos].artist;
	}

    LIB.albumClicked = true; // for renderSongs()
    $(itemSelector).removeClass('active');
	$(itemSelector).eq(pos).addClass('active');
	customScroll(scrollSelector, pos, 200);	

	// song list for regular album		
    if (compilation != '1') { 
	    clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);
	}
	// song list for compilation album
	else {
		allCompilationSongs = [];
		LIB.filters.albums = [];
		renderFunc = renderSongs;
		filterLib();		
		LIB.totalTime = 0;
		
		for (i = 0; i < allSongs.length; i++) {
			if (allSongs[i].album == album) {
				allCompilationSongs.push(allSongs[i]);
				LIB.totalTime += parseSongTime(allSongs[i].time);
			}
		}

		allSongs = allCompilationSongs;
		renderFunc(pos);

		// cover art and metadata
		$('#lib-albumname').html(album);
		$('#lib-artistname').html(artist);			
		$('#lib-numtracks').html(allSongs.length + ((allSongs.length == 1) ? ' track, ' : ' tracks, ') + formatTotalTime(LIB.totalTime));
		$('#lib-coverart-img').html(
			'<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-all">' +
			'<img class="lib-coverart" src="' + makeCoverUrl(allSongs[0].file) + '" ' + 'alt="Cover art not found"' + '></a>'
		);
    }
});

// click lib coverart
$('#lib-coverart-img').click(function(e) {	
	UI.dbEntry[0] =  $.isNumeric(UI.dbEntry[0]) ? UI.dbEntry[0] : 0;
    $('#songsList li, #songsList .lib-disc a').removeClass('active');	
	$('img.lib-coverart').addClass('active'); // add highlight
});

// r44f click on Disc
$('#songsList').on('click', '.lib-disc', function(e) {
	$('img.lib-coverart, #songsList li, #songsList .lib-disc a').removeClass('active'); // rm highlight
	var discNum = $(this).text().substr(5);
	$('#lib-disc-' + discNum + ' a').addClass('active');

	// r44g
	allSongsDisc.length = 0;
	for (var i in allSongs) {
		if (allSongs[i].disc == discNum) {
			allSongsDisc.push(allSongs[i]);
		}
	}
	//console.log('allSongsDisc= ' + JSON.stringify(allSongsDisc));
});

// click lib song item
$('#songsList').on('click', '.lib-action', function(e) {
    UI.dbEntry[0] = $('#songsList .lib-action').index(this); // store pos for use in action menu item click
	$('#songsList li, #songsList .lib-disc a').removeClass('active');
	$(this).parent().addClass('active');
	$('img.lib-coverart').removeClass('active'); // rm highlight
});

// click tracks context menu item 
$('#context-menu-lib-item a').click(function(e) {
    $('#lib-song-' + (UI.dbEntry[0] + 1).toString()).removeClass('active');
	$('img.lib-coverart').removeClass('active'); // rm highlight

    if ($(this).data('cmd') == 'add') {
        mpdDbCmd('add', allSongs[UI.dbEntry[0]].file);
        notify('add', '');
    }
    if ($(this).data('cmd') == 'play') {
        mpdDbCmd('play', allSongs[UI.dbEntry[0]].file);
        notify('add', '');
    }
    if ($(this).data('cmd') == 'clrplay') {
        mpdDbCmd('clrplay', allSongs[UI.dbEntry[0]].file);
        notify('clrplay', '');        
        $('#pl-saveName').val(''); // clear saved playlist name if any
	}	
});

// click coverart context menu item
$('#context-menu-lib-all a').click(function(e) {
    $('#lib-song-' + (UI.dbEntry[0] + 1).toString()).removeClass('active');
	$('img.lib-coverart').removeClass('active');

	// r44g
	var files = [];
	for (var i in allSongs) {
		files.push(allSongs[i].file); 
	}
	//console.log('files= ' + JSON.stringify(files));

    if ($(this).data('cmd') == 'addall') {
        mpdDbCmd('addall', files);
        notify('add', '');
	}
    if ($(this).data('cmd') == 'playall') {
        mpdDbCmd('playall', files);
        notify('add', '');
	}
    if ($(this).data('cmd') == 'clrplayall') {
        mpdDbCmd('clrplayall', files);
        notify('clrplay', '');
	}
});

// click disc context menu item
$('#context-menu-lib-disc a').click(function(e) {
	$('#songsList .lib-disc a').removeClass('active');

	// r44g
	var files = [];
	for (var i in allSongsDisc) {
		files.push(allSongsDisc[i].file); 
	}
	//console.log('files= ' + JSON.stringify(files));

    if ($(this).data('cmd') == 'addall') {
        mpdDbCmd('addall', files);
        notify('add', '');
	}
    if ($(this).data('cmd') == 'playall') {
        mpdDbCmd('playall', files);
        notify('add', '');
	}
    if ($(this).data('cmd') == 'clrplayall') {
        mpdDbCmd('clrplayall', files);
        notify('clrplay', '');
	}
});

// scroll to current song if title is clicked
$('#currentsong').click(function(e) {
	if ($('#mt2').css('display') == 'block') {
	    var itemnum = parseInt(MPD.json['song']);
		var centerHeight, scrollTop, scrollCalc, scrollOffset, itemPos;
		itemPos = $('#playlist ul li:nth-child(' + (itemnum + 1) + ')').position().top;
		centerHeight = parseInt($('#playlist').height()/3); // place in upper third instead of middle
	    scrollTop = $('#playlist').scrollTop();
		scrollCalc = (itemPos + 200);
	    $('html, body').animate({ scrollTop: scrollCalc }, 'fast');
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

// main menu and context menus
$('.context-menu a').click(function(e) {
    var path = UI.dbEntry[0]; // file path or item num

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
		if (path.indexOf('/') == -1) {  // its a playlist, preload the saved playlist name
			$('#pl-saveName').val(path);
		}
		else {
			$('#pl-saveName').val('');
		}
	}        
	else if ($(this).data('cmd') == 'clrplay') {
		mpdDbCmd('clrplay', path);
		notify('clrplay', '');
		if (path.indexOf('/') == -1) {  // its a playlist, preload the saved playlist name
			$('#pl-saveName').val(path);
		}
		else {
			$('#pl-saveName').val('');
		}
	}        
	else if ($(this).data('cmd') == 'update') {
		mpdDbCmd('update', path);
		notify('update', path);
		libRendered = false;
	}
	else if ($(this).data('cmd') == 'updradio') {
		mpdDbCmd('update', 'RADIO');
		notify('update', 'RADIO');
	}
	else if ($(this).data('cmd') == 'delsavedpl') {
		$('#savedpl-path').html(path);        	        
		$('#deletesavedpl-modal').modal();
	}
	else if ($(this).data('cmd') == 'delstation') {
		$('#station-path').html(path.slice(0,path.lastIndexOf('.')).substr(6)); // trim 'RADIO/' and '.pls' from path
		$('#deletestation-modal').modal();
	}
	else if ($(this).data('cmd') == 'addstation') {
		$('#add-station-name').val('New Station');
		$('#add-station-url').val('http://');
		$('#addstation-modal').modal();
	}
	else if ($(this).data('cmd') == 'editradiostn') {		
		path = path.slice(0,path.lastIndexOf('.')).substr(6); // trim 'RADIO/' and '.pls' from path
		$('#edit-station-name').val(path);
		$('#edit-station-url').val(sendMoodeCmd('POST', 'readstationfile', {'path': UI.dbEntry[0]})['File1']);
		$('#editstation-modal').modal();
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
		
		// r44d parse start and end values
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

		$('#clockradio-shutdown span').text(SESSION.json['clkradio_shutdown']);
		$('#clockradio-volume').val(SESSION.json['clkradio_volume']);

		setClkRadioCtls(SESSION.json['clkradio_mode']);

        $('#clockradio-modal').modal();
    }
    
	// MAIN MENU

    // customize popup
    else if ($(this).data('cmd') == 'customize') {
		// reset indicator
		bgImgChange = false;

		// general settings
		$('#play-history-enabled span').text(SESSION.json['playhist']);
		$('#extratag-display span').text(SESSION.json['xtagdisp']);
		$('#musictab-default span').text(SESSION.json['musictab_default']);
		$('#library-comp-rollup span').text(SESSION.json['compilation_rollup']); // r44d
		$('#library-comp-excludes').val(SESSION.json['compilation_excludes']); // r44d
		$('#scnsaver-timeout span').text(screenSaverTimeout(SESSION.json['scnsaver_timeout'], 'param'));
		$('#library-artist span').text(SESSION.json['libartistcol']);
		$('#library-utf8rep span').text(SESSION.json['library_utf8rep']);
		$('#library-covsearchpri span').text(SESSION.json['library_covsearchpri']);
		$('#library-hiresthm span').text(SESSION.json['library_hiresthm']);

		// r44d hires thumbnail settings
		$('#library-hiresthm-list').html(		
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-hiresthm-sel"><span class="text">Auto</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-hiresthm-sel"><span class="text">100px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-hiresthm-sel"><span class="text">200px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-hiresthm-sel"><span class="text">300px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-hiresthm-sel"><span class="text">400px</span></a></li>'
		);

		// screen saver timeouts
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

		// themes
		var obj = sendMoodeCmd('POST', 'readthemename');
		var themelist = '';		
		for (i = 0; i < obj.length; i++) {
			themelist += '<li class="modal-dropdown-text"><a href="#notarget" data-cmd="theme-name-sel"><span class="text">' + obj[i]['theme_name'] + '</span></a></li>';
		}
		$('#theme-name-list').html(themelist);		
		$('#theme-name span').text(SESSION.json['themename']);

		// alpha blend values
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
		$('#alpha-blend span').text(SESSION.json['alphablend']);

		// adaptive background yes/no
		$('#adaptive-enabled span').text(SESSION.json['adaptive']);

		// accent colors
		$('#theme-color-list').html(		
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #c0392b; font-weight: bold;">Alizarin</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #8e44ad; font-weight: bold;">Amethyst</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #1a439c; font-weight: bold;">Bluejeans</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #d35400; font-weight: bold;">Carrot</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #27ae60; font-weight: bold;">Emerald</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #cb8c3e; font-weight: bold;">Fallenleaf</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #7ead49; font-weight: bold;">Grass</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #317589; font-weight: bold;">Herb</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #876dc6; font-weight: bold;">Lavender</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #2980b9; font-weight: bold;">River</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #c1649b; font-weight: bold;">Rose</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #999999; font-weight: bold;">Silver</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #16a085; font-weight: bold;">Turquoise</span></a></li>'
		);		
		$('#theme-color span').text(SESSION.json['themecolor']);
		
		// bg image
		$('#error-bgimage').text('');
		$.ajax({
			url:'imagesw/bgimage.jpg', // r44d switch from http: //hostname to relative path
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

		// cover backdrop settings
		$('#cover-backdrop-enabled span').text(SESSION.json['cover_backdrop']);		
		$('#cover-blur-list').html(		
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">0px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">5px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">10px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">15px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">20px</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">30px</span></a></li>'
		);		
		$('#cover-blur span').text(SESSION.json['cover_blur']);
		$('#cover-scale-list').html(		
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel"><span class="text">1.0</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel"><span class="text">1.25</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel"><span class="text">1.5</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel"><span class="text">1.75</span></a></li>' +
			'<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel"><span class="text">2.0</span></a></li>'
		);		
		$('#cover-scale span').text(SESSION.json['cover_scale']);

		// audio device description
		var obj = sendMoodeCmd('POST', 'readaudiodev');
		var devlist = '';
		
		// load device list into <ul>
		for (i = 0; i < obj.length; i++) {
			devlist += '<li class="modal-dropdown-text"><a href="#notarget" data-cmd="audio-device-name-sel"><span class="text">' + obj[i]['name'] + '</span></a></li>';
			if (obj[i]['name'] === SESSION.json['adevname']) {
				$('#audio-device-name span').text(obj[i]['name']);
				$('#audio-device-dac').val(obj[i]['dacchip']);
				$('#audio-device-arch').val(obj[i]['arch']);
				$('#audio-device-iface').val(obj[i]['iface']);
			}
		}

		$('#audio-device-list').html(devlist);
		
		// display modal
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
    
    // about
    else if ($(this).data('cmd') == 'aboutmoode') {
		$('#sys-upd-pkgdate').text(SESSION.json['pkgdate']);
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
	SESSION.json['clkradio_shutdown'] = $('#clockradio-shutdown span').text();

	var result = sendMoodeCmd('POST', 'updcfgsystem',
		{'clkradio_mode': SESSION.json['clkradio_mode'],
		 'clkradio_item': SESSION.json['clkradio_item'].replace(/'/g, "''"), // use escaped single quotes for sql i.e., two single quotes,		
		 'clkradio_name': SESSION.json['clkradio_name'].replace(/'/g, "''"),
		 'clkradio_start': SESSION.json['clkradio_start'],		
		 'clkradio_stop': SESSION.json['clkradio_stop'],		
		 'clkradio_volume': SESSION.json['clkradio_volume'],		
		 'clkradio_shutdown': SESSION.json['clkradio_shutdown']
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
	sendMoodeCmd('GET', 'updclockradio');

    notify('updclockradio', '');
});

// update customize settings
$('.btn-customize-update').click(function(e){
	// detect certain changes
	var xtagdispChange = false;
	var musictabChange = false;
	var libChange = false;
	var scnSaverTimeoutChange = false;
	var accentColorChange = false;
	var themeSettingsChange = false;
	if (SESSION.json['xtagdisp'] != $('#extratag-display span').text()) {xtagdispChange = true;}
	if (SESSION.json['musictab_default'] != $('#musictab-default span').text()) {musictabChange = true;}
	if (SESSION.json['scnsaver_timeout'] != screenSaverTimeout($('#scnsaver-timeout span').text(), 'value')) {scnSaverTimeoutChange = true;}
	if (SESSION.json['libartistcol'] != $('#library-artist span').text()) {libChange = true;}
	if (SESSION.json['compilation_rollup'] != $('#library-comp-rollup span').text()) {libChange = true;} // r44d
	if (SESSION.json['compilation_excludes'] != $('#library-comp-excludes').val()) {libChange = true;} // r44d
	if (SESSION.json['library_utf8rep'] != $('#library-utf8rep span').text()) {libChange = true;}
	if (SESSION.json['themecolor'] != $('#theme-color span').text()) {accentColorChange = true;}
	if (SESSION.json['themename'] != $('#theme-name span').text()) {themeSettingsChange = true;}
	if (SESSION.json['themecolor'] != $('#theme-color span').text()) {themeSettingsChange = true;}
	if (SESSION.json['alphablend'] != $('#alpha-blend span').text()) {themeSettingsChange = true;};
	if (SESSION.json['adaptive'] != $('#adaptive-enabled span').text()) {themeSettingsChange = true;};
	if (SESSION.json['cover_backdrop'] != $('#cover-backdrop-enabled span').text()) {themeSettingsChange = true;};
	if (SESSION.json['cover_blur'] != $('#cover-blur span').text()) {themeSettingsChange = true;};
	if (SESSION.json['cover_scale'] != $('#cover-scale span').text()) {themeSettingsChange = true;};

	// general settings
	SESSION.json['playhist'] = $('#play-history-enabled span').text();
	SESSION.json['xtagdisp'] = $('#extratag-display span').text();
	SESSION.json['musictab_default'] = $('#musictab-default span').text();
	SESSION.json['scnsaver_timeout'] = screenSaverTimeout($('#scnsaver-timeout span').text(), 'value');
	// library settings
	SESSION.json['libartistcol'] = $('#library-artist span').text();
	SESSION.json['compilation_rollup'] = $('#library-comp-rollup span').text(); // r44d
	SESSION.json['compilation_excludes'] = $('#library-comp-excludes').val(); // r44d
	SESSION.json['library_utf8rep'] = $('#library-utf8rep span').text();
	SESSION.json['library_covsearchpri'] = $('#library-covsearchpri span').text();
	SESSION.json['library_hiresthm'] = $('#library-hiresthm span').text();
	// theme settings
	SESSION.json['themename'] = $('#theme-name span').text();
	SESSION.json['themecolor'] = $('#theme-color span').text();
	SESSION.json['alphablend'] = $('#alpha-blend span').text();
	SESSION.json['adaptive'] = $('#adaptive-enabled span').text();
	SESSION.json['cover_backdrop'] = $('#cover-backdrop-enabled span').text();
	SESSION.json['cover_blur'] = $('#cover-blur span').text();
	SESSION.json['cover_scale'] = $('#cover-scale span').text();

	// device description
	SESSION.json['adevname'] = $('#audio-device-name span').text() == '' ? 'none' : $('#audio-device-name span').text();
	
	// update cfg_system / $_SESSION vars
	var result = sendMoodeCmd('POST', 'updcfgsystem',
		{'playhist': SESSION.json['playhist'],
		 'xtagdisp': SESSION.json['xtagdisp'],
		 'musictab_default': SESSION.json['musictab_default'],
		 'scnsaver_timeout': SESSION.json['scnsaver_timeout'],
		 'libartistcol': SESSION.json['libartistcol'],
		 'compilation_rollup': SESSION.json['compilation_rollup'], // r44d
		 'compilation_excludes': SESSION.json['compilation_excludes'], // r44d
		 'library_utf8rep': SESSION.json['library_utf8rep'],
		 'library_covsearchpri': SESSION.json['library_covsearchpri'],
		 'library_hiresthm': SESSION.json['library_hiresthm'],
		 'themename': SESSION.json['themename'],
		 'themecolor': SESSION.json['themecolor'],
		 'alphablend': SESSION.json['alphablend'],
		 'adaptive': SESSION.json['adaptive'],
		 'cover_backdrop': SESSION.json['cover_backdrop'],
		 'cover_blur': SESSION.json['cover_blur'],
		 'cover_scale': SESSION.json['cover_scale'],
		 'adevname': SESSION.json['adevname']
		}
	);

	if (scnSaverTimeoutChange == true) {
		var resp = sendMoodeCmd('GET', 'resetscnsaver');
	}

	if (libChange == true) {
		var resp = sendMoodeCmd('GET', 'truncatelibcache');
	}
	if (accentColorChange == true) {
		var resp = sendMoodeCmd('GET', SESSION.json['themecolor'].toLowerCase());
	}

	// auto-reload page if indicated
	if (xtagdispChange == true || musictabChange == true || accentColorChange == true || themeSettingsChange == true || libChange == true || UI.bgImgChange == true) {
	    notify('updcustomize', 'Auto-refresh in 3 seconds');
		setTimeout(function() {
			location.reload(true);
		}, 3000);
	}
	else {
	    notify('updcustomize', '');
	}
});

// remove bg image (NOTE choose bg image is in footer.php)
$('#remove-bgimage').click(function(e) {
	e.preventDefault(); // r44c
	if ($('#current-bgimage').html() != '') {
		var rtn = sendMoodeCmd('POST', 'rmbgimage'); // change from GET to POST so modal stays open
		$('#current-bgimage').html('');
		$('#info-toggle-bgimage').css('margin-left','5px');
		UI.bgImgChange = true;
	}
	return false; // r44c so modal stays open
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
		var rtn = sendMoodeCmd('POST', 'setbgimage', {'blob': data});
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
	if ($(this).data('cmd') == 'clockradio-mode-sel') {
		$('#clockradio-mode span').text($(this).text());
		setClkRadioCtls($(this).text());
	} else if ($(this).data('cmd') == 'clockradio-starttime-ampm') {
		$('#clockradio-starttime-ampm span').text($(this).text());		
	} else if ($(this).data('cmd') == 'clockradio-stoptime-ampm') {
		$('#clockradio-stoptime-ampm span').text($(this).text());		
	} else if ($(this).data('cmd') == 'clockradio-shutdown-yn') {
		$('#clockradio-shutdown span').text($(this).text());		
	} else if ($(this).data('cmd') == 'play-history-enabled-yn') {
		$('#play-history-enabled span').text($(this).text());
	} else if ($(this).data('cmd') == 'extratag-display-yn') {
		$('#extratag-display span').text($(this).text());
	} else if ($(this).data('cmd') == 'musictab-default-sel') {
		$('#musictab-default span').text($(this).text());
	} else if ($(this).data('cmd') == 'library-hiresthm-sel') { // r44d
		$('#library-hiresthm span').text($(this).text());
	} else if ($(this).data('cmd') == 'scnsaver-timeout-sel') {
		$('#scnsaver-timeout span').text($(this).text());
	} else if ($(this).data('cmd') == 'library-artist-sel') {
		$('#library-artist span').text($(this).text());
	} else if ($(this).data('cmd') == 'library-comp-rollup-yn') { // r44d
		$('#library-comp-rollup span').text($(this).text());
	} else if ($(this).data('cmd') == 'library-utf8rep-yn') {
		$('#library-utf8rep span').text($(this).text());
	} else if ($(this).data('cmd') == 'library-covsearchpri-sel') {
		$('#library-covsearchpri span').text($(this).text());
	} else if ($(this).data('cmd') == 'library-hiresthm-yn') {
		$('#library-hiresthm span').text($(this).text());
	} else if ($(this).data('cmd') == 'theme-name-sel') {
		$('#theme-name span').text($(this).text());		
	} else if ($(this).data('cmd') == 'theme-color-sel') {
		$('#theme-color span').text($(this).text());		
	} else if ($(this).data('cmd') == 'adaptive-enabled-yn') {
		$('#adaptive-enabled span').text($(this).text());
	} else if ($(this).data('cmd') == 'alpha-blend-sel') {
		$('#alpha-blend span').text($(this).text());		
	} else if ($(this).data('cmd') == 'cover-backdrop-enabled-yn') {
		$('#cover-backdrop-enabled span').text($(this).text());
	} else if ($(this).data('cmd') == 'cover-blur-sel') {
		$('#cover-blur span').text($(this).text());		
	} else if ($(this).data('cmd') == 'cover-scale-sel') {
		$('#cover-scale span').text($(this).text());		
	} else if ($(this).data('cmd') == 'audio-device-name-sel') {
		$('#audio-device-name span').text($(this).text());
		var obj = sendMoodeCmd('POST', 'readaudiodev', {'name': $(this).text()});
		$('#audio-device-dac').val(obj['dacchip']);
		$('#audio-device-arch').val(obj['arch']);
		$('#audio-device-iface').val(obj['iface']);
	}
});

$('#syscmd-reboot').click(function(e) {
	$('#screen-saver').hide();
	UI.restart = 'reboot';
	sendMoodeCmd('GET', 'reboot');
	notify('reboot', '', 8000);	
});

$('#syscmd-poweroff').click(function(e) {
	$('#screen-saver').hide();
	UI.restart = 'poweroff';
	sendMoodeCmd('GET', 'poweroff');
	notify('shutdown', '', 8000);	
});

/* 
https://github.com/Qix-/color-convert/blob/master/conversions.js
*/
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

// r44d1 rgba to rgb
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
function dbFastSearch() { // r44g camelcase
	$('#dbsearch-alltags').val($('#dbfs').val());
	$('#db-search-submit').click();
	return false;
}

// set colors for tab bar and tab bar fade
function btnbarfix(temp1,temp2) { // r44f simplify
	var temprgba = splitColor(temp1);
	var temprgb = hexToRgb(adaptColor);
	var temprgb2 = hexToRgb(temp2);
	var color4 = rgbaToRgb(.4, temprgba, temprgb);
	var tempx = 0;
	if ((SESSION.json['alphablend']) < .8) {
		tempx = ((.9 - (SESSION.json['alphablend'])));
		if (tempx > .4) {tempx = .4}
	}
	var colors = rgbaToRgb(.7 - tempx, temprgba, temprgb2);
	var color2 = rgbaToRgb(.75 - tempx, temprgba, temprgb2);
	var color3 = rgbaToRgb(.85 - tempx, temprgba, temprgb2);
	themeOp < .74 ? tempback = '0.55' : tempback = themeOp;

	// r44h
	if (getYIQ(themeBack) >= 128) {
		var tempa = 'rgba(128,128,128,0.15)';
	} else {
		var tempa = 'rgba(32,32,32,0.25)';
		}
	// set middle color to a dark variant of the adaptive bg one
	var tempx = splitColor(adaptBack);
	var tempy = 'rgb('+tempx[0]+','+tempx[1]+','+tempx[2]+')';
	tempx = rgbToHsl(tempy);
	if (tempx[2] > .3) {tempx[2]=.3}
	//console.log(tempy, tempx); 
	tempy = hslToRgb(tempx);
	document.body.style.setProperty('--shiftybg', 'rgba('+tempy[0]+','+tempy[1]+','+tempy[2]+',0.8)');
	// end

	document.body.style.setProperty('--btnbarcolor', 'rgba('+colors[0]+','+colors[1]+','+colors[2]+','+tempback+')');
	document.body.style.setProperty('--btnshade', 'rgba('+color2[0]+','+color2[1]+','+color2[2]+','+'0.15)');
	document.body.style.setProperty('--btnshade2', 'rgba('+color3[0]+','+color3[1]+','+color3[2]+','+'0.5)');
	document.body.style.setProperty('--textvariant', 'rgba('+color4[0]+','+color4[1]+','+color4[2]+',1.0)');
	var tempb = 'rgba('+temprgba[0]+','+temprgba[1]+','+temprgba[2]+','+'0.25)';
	document.body.style.setProperty('--btnshade3', tempb); // r44g
	themeOp < .74 ? tempb = tempb : tempb = temp1;
	document.body.style.setProperty('--btnbarback', tempb);
}

function getYIQ(color) {
  var rgb = color.match(/\d+/g);
  return ((rgb[0] * 299) + (rgb[1] * 587) + (rgb[2] * 114)) / 1000;
};

function setColors() {
	$('.tab-content').css({color: themeColor});
	$('.tab-content').css({backgroundColor: themeBack});
	$('#context-menu-playlist-item .dropdown-menu').css({color: adaptMcolor});
	$('#context-menu-playlist-item .dropdown-menu').css({backgroundColor: adaptMback});
	document.body.style.setProperty('--adapttext', themeMcolor);
	btnbarfix(adaptBack, adaptColor); // r44d1
	if (getYIQ(themeBack) >= 128) {
		document.body.style.setProperty('--timethumb', 'url(../imagesw/thumb.svg)');
		document.body.style.setProperty('--timecolor', 'rgba(96,96,96,0.25)');
		document.body.style.setProperty('--trackfill', 'rgba(48,48,48,1.0)');
	}
	else {
		document.body.style.setProperty('--timethumb', 'url(../imagesw/thumb-w.svg)');
		document.body.style.setProperty('--timecolor', 'rgba(240,240,240,0.25)');
		document.body.style.setProperty('--trackfill', 'rgba(240,240,240,1.0)');
	}
    if ($('#radio-panel').hasClass('active') || $('#browse-panel').hasClass('active') || $('#library-panel').hasClass('active')) {
		$('#menu-top .dropdown-menu').css({color: themeMcolor});
		$('#menu-top .dropdown-menu').css({backgroundColor: themeMback});
		$('#menu-top').css({color: themeColor});
		SESSION.json['alphablend'] != '1.00' ? $('#menu-top').css('background-color', 'rgba(0,0,0,0)') : $('#menu-top').css('background-color', themeMback);
		document.body.style.setProperty('--themembg', adaptMback);
		document.body.style.setProperty('--themetext', adaptMcolor);
		$('#menu-bottom').css({color: themeColor});
	}
}

// manages toolbar when scrolling
$(window).on('scroll', function(e) {
	//console.log('window scroll');
	if ((SESSION.json['alphablend'] == '1.00') || (themeOp < '.74')) {
		if ($(window).scrollTop() > 1 && !showMenuTopW) {
			//console.log('window scroll, scrollTop() > 1 && !showMenuTopW');
			if ($('#mt2').css('display') == 'block') {
				$('#mobile-toolbar').css('display', 'none'); // r44d
				$('#container-playlist').css('visibility','visible'); //r44d
			}
			$('#menu-top').css('height', $('#menu-top').css('line-height'));
			showMenuTopW = true;
		}
		else if ($(window).scrollTop() == '0' ) {
			//console.log('window scroll, scrollTop() = 0');
			if ($('#mt2').css('display') == 'block') {
				$('#container-playlist').css('visibility','hidden'); // r44d
				$('#mobile-toolbar').css('display', 'block'); // r44d
			}
			$('#menu-top').css('height', '0');
			showMenuTopW = false;
		}		
	}
});
$('#database-radio').on('scroll', function(e) {
	//console.log('#database-radio scroll');
	if ((SESSION.json['alphablend'] == '1.00') || (themeOp < '.74')) {
		if ($('#database-radio').scrollTop() > 1 && !showMenuTopR) {
			$('#menu-top').css('height', $('#menu-top').css('line-height'));
			showMenuTopR = true;
		}
		else if ($('#database-radio').scrollTop() == '0' ) {
			$('#menu-top').css('height', '0');
			showMenuTopR = false;
		}
	}
});
