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
 * 2020-MM-DD TC moOde 7.0.0
 *
 */

// Features availability bitmask
const FEAT_KERNEL       = 1;        // y Kernel architecture option on System Config
const FEAT_AIRPLAY      = 2;        // y Airplay renderer
const FEAT_MINIDLNA     = 4;        // y DLNA server
const FEAT_MPDAS        = 8;        // y MPD audio scrobbler
const FEAT_SQUEEZELITE  = 16;       // y Squeezelite renderer
const FEAT_UPMPDCLI     = 32;       // y UPnP client for MPD
const FEAT_SQSHCHK      = 64;       //   Require squashfs for software update
const FEAT_GMUSICAPI    = 128;      // y Google Play music service
const FEAT_LOCALUI      = 256;      // y Local display
const FEAT_INPSOURCE    = 512;      // y Input source select
const FEAT_UPNPSYNC     = 1024;     //   UPnP volume sync
const FEAT_SPOTIFY      = 2048;     // y Spotify Connect renderer
const FEAT_GPIO         = 4096;     // y GPIO button handler
const FEAT_DJMOUNT      = 8192;     // y UPnP media browser
const FEAT_BLUETOOTH    = 16384;    // y Bluetooth renderer
const FEAT_DEVTWEAKS	= 32768;	//   Developer tweaks
//                      -------
//                        31679

// For setTimout() in milliseconds
const DEFAULT_TIMEOUT   = 250;
const SEARCH_TIMEOUT    = 750;
const CLRPLAY_TIMEOUT   = 500;
const RALBUM_TIMEOUT    = 1500;
const ENGINE_TIMEOUT    = 3000;

// Album and Radio HD parameters
const ALBUM_HD_BADGE_TEXT           = 'HD';
const ALBUM_BIT_DEPTH_THRESHOLD     = 16;
const ALBUM_SAMPLE_RATE_THRESHOLD   = 44100;
const RADIO_HD_BADGE_TEXT           = 'HiRes';
const RADIO_BITRATE_THRESHOLD       = 128;

var UI = {
    knob: null,
    path: '',
	restart: '',
	currentFile: 'blank',
	currentHash: 'blank',
	currentSongId: 'blank',
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
	npIcon: 'url("../images/audiod.svg")',
	npIconPaused: 'url("../images/audiod-flat.svg")',
	thumbHW: '0px'
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

// TODO: Eventually migrate all global vars here
var GLOBAL = {
	musicScope: 'all', // or not defined if saved, but prob don't bother saving...
	searchLib: '', // used to store search results (x albums found) for the menu header
	searchRadio: '',
	searchFolder: '',
    scriptSection: 'panels',
	regExIgnoreArticles: '',
    libRendered: false,
    libLoading: false,
    playbarPlaylistTimer: '',
    plActionClicked: false,
    mpdMaxVolume: 0,
    lastTimeCount: 0,
    editStationId: '',
    nativeLazyLoad: false,
    playlistChanged: false
};

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
var fatthumbw = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='13' height='20'><circle fill='%23f0f0f0' cx='6.5' cy='10' r='5.5'/></svg>";
var fatthumbd = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='13' height='20'><circle fill='%23303030' cx='6.5' cy='10' r='5.5'/></svg>";
var blurrr = CSS.supports('-webkit-backdrop-filter','blur(1px)');

// various flags and things
var dbFilterResults = [];
var searchTimer = '';
var showSearchResetPl = false;
var showSearchResetLib = false;
var showSearchResetRa = false;
var showSearchResetPh = false;
var eqGainUpdInterval = '';
var toolbarTimer = '';
var toggleSongId = 'blank';
var currentView = 'playback';
var alphabitsFilter;
var lastYIQ = ''; // last yiq value from setColors
var coverView = false; // coverview active or not to save on more expensive conditional in interval timer

function debugLog(msg) {
	if (SESSION.json['debuglog'] == '1') {
		console.log(Date.now() + ': ' + msg);
	}
}

// MPD commands
function sendMpdCmd(cmd, async) {
	if (typeof(async) === 'undefined') {async = true;}

	$.ajax({
		type: 'GET',
		url: 'command/index.php?cmd=' + cmd,
		async: async,
		cache: false,
		success: function(data) {
		}
    });
}

// Specifically for volume
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
			obj = JSON.parse(result);
			// Omit the try/catch to enable improved volume knob behavior
			// See moode.php case 'updvolume' for explanation
		},
		error: function() {
			//debugLog('sendVolCmd(): ' + cmd + ' no data returned');
			obj = false;
		}
	});

	return obj;
}

// MPD metadata engine
function engineMpd() {
    $.ajax({
		type: 'GET',
		url: 'engine-mpd.php?state=' + MPD.json['state'],
		async: true,
		cache: false,
		success: function(data) {
			//debugLog('engineMpd(): success branch: data=(' + data + ')');

			// Always have valid json
			try {
				MPD.json = JSON.parse(data);
                //console.log(MPD.json);
			}
			catch (e) {
				MPD.json['error'] = e;
			}

			if (typeof(MPD.json['error']) === 'undefined') {
				//console.log('engineMpd(): idle_timeout_event=(' + MPD.json['idle_timeout_event'] + ')', 'state', MPD.json['state']);

				if (UI.hideReconnect === true) {
					hideReconnect();
				}
				// MPD restarted by udev, watchdog, manually via cli, etc
				// Udev rule /etc/udev/rules.d/10-usb-audiodevice.rules
				if (MPD.json['idle_timeout_event'] === '') {
					// nop
				}
				// Database update
				if (MPD.json['idle_timeout_event'] == 'changed: update') {
					if (typeof(MPD.json['updating_db']) != 'undefined') {
						$('.busy-spinner').show();
					}
					else {
						$('.busy-spinner').hide();
					}
				}
				// Render volume
				else if (MPD.json['idle_timeout_event'] == 'changed: mixer') {
					renderUIVol();
				}
				// When last item in playlist finishes just update a few things
				else if (MPD.json['idle_timeout_event'] == 'changed: player' && MPD.json['file'] == null) {
					resetPlayCtls();
				}
				// Render full UI
				else {
					renderUI();
				}

				engineMpd();
			}
			// Error of some sort
			else {
				debugLog('engineMpd(): success branch: error=(' + MPD.json['error'] + '), module=(' + MPD.json['module'] + ')');

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
				// Client connects before mpd started by worker ?
				else if (MPD.json['error'] == 'SyntaxError: JSON Parse error: Unexpected EOF') {
					notify('mpderror', 'JSON Parse error: Unexpected EOF');
				}
				// MPD bug may have been fixed in 0.20.20 ?
				else if (MPD.json['error'] == 'Not seekable') {
					// nop
				}
				// Other network or MPD errors
				else {
					notify('mpderror', MPD.json['error']);
				}

				renderUI();

				setTimeout(function() {
					engineMpd();
				}, ENGINE_TIMEOUT);
			}
		},
        // Network connection interrupted or client network stack timeout
		error: function(data) {
			debugLog('engineMpd(): error branch: data=(' + JSON.stringify(data) + ')');

			setTimeout(function() {
				if (data['statusText'] == 'error' && data['readyState'] == 0) {
			        renderReconnect();
				}
				MPD.json['state'] = 'reconnect';
				engineMpd();
			}, ENGINE_TIMEOUT);
		}
    });
}

// MPD metadata engine lite (for scripts-configs)
function engineMpdLite() {
	//debugLog('engineMpdLite(): state=(' + MPD.json['state'] + ')');
    $.ajax({
		type: 'GET',
		url: 'engine-mpd.php?state=' + MPD.json['state'],
		async: true,
		cache: false,
		success: function(data) {
			//debugLog('engineMpdLite(): success branch: data=(' + data + ')');

			// Always have valid json
			try {
				MPD.json = JSON.parse(data);
			}
			catch (e) {
				MPD.json['error'] = e;
			}

			if (typeof(MPD.json['error']) === 'undefined') {
				//console.log('engineMpdLite: idle_timeout_event=(' + MPD.json['idle_timeout_event'] + ')', 'state', MPD.json['state']);

				if (UI.hideReconnect === true) {
					hideReconnect();
				}
				// Database update
				if (typeof(MPD.json['updating_db']) != 'undefined') {
					$('.busy-spinner').show();
				}
				else {
					$('.busy-spinner').hide();
				}

				engineMpdLite();

			}
			// Error of some sort
			else {
				setTimeout(function() {
					// Client connects before mpd started by worker, various other network issues
					debugLog('engineMpdLite(): success branch: error=(' + MPD.json['error'] + '), module=(' + MPD.json['module'] + ')');
					engineMpdLite();
				}, ENGINE_TIMEOUT);
			}
		},
        // Network connection interrupted or client network stack timeout
		error: function(data) {
			debugLog('engineMpdLite(): error branch: data=(' + JSON.stringify(data) + ')');
            //console.log('engineMpdLite: error branch: data=(' + JSON.stringify(data) + ')');

			setTimeout(function() {
				if (data['statusText'] == 'error' && data['readyState'] == 0) {
			        renderReconnect();
				}
				MPD.json['state'] = 'reconnect';
				engineMpdLite();
			}, ENGINE_TIMEOUT);
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

            switch (cmd[0]) {
                case 'inpactive1':
                case 'inpactive0':
                    // NOTE: cmd[1] is the input source name
    				//inpSrcIndicator(cmd[0], '<a href="inp-config.php">' + cmd[1] + ' Input Active</a>' + '<br><span><button class="btn volume-popup-btn" data-toggle="modal"><i class="fal fa-volume-up"></i></button><span id="inpsrc-preamp-volume"></span></span>');
                    inpSrcIndicator(cmd[0], cmd[1] +
                        ' Input Active: <button class="btn volume-popup-btn" data-toggle="modal"><i class="fal fa-volume-up"></i></button><span id="inpsrc-preamp-volume"></span>' +
                        '<br><a class="btn configure-renderer" href="inp-config.php">Input Source</a>'
                    );
                    break;
                case 'btactive1':
                case 'btactive0':
                    // NOTE: cmd[1] is the input source name
                    inpSrcIndicator(cmd[0], 'Bluetooth Active' + cmd[1] + '<br><a class="btn configure-renderer" href="blu-config.php">BlueZ Config</a>');
                    break;
                case 'aplactive1':
                case 'aplactive0':
    				inpSrcIndicator(cmd[0], 'Airplay Active' + '<br><button class="btn disconnect-renderer" data-job="airplaysvc">disconnect</button>');
                    break;
                case 'spotactive1':
                case 'spotactive0':
    				inpSrcIndicator(cmd[0], 'Spotify Active' + '<br><button class="btn disconnect-renderer" data-job="spotifysvc">disconnect</button>');
                    break;
                case 'slactive1':
                case 'slactive0':
    				inpSrcIndicator(cmd[0], 'Squeezelite Active' + '<br><button class="btn disconnect-renderer" data-job="slsvc">turn off</button>');
                    break;
                case 'scnactive1':
    				screenSaver(cmd[0]);
                    break;
                case 'libupd_done':
    				$('.busy-spinner').hide();
                    loadLibrary();
                    break;
                case 'set_logo_image1':
                    $('.busy-spinner').show();
                    break;
                case 'set_logo_image0':
    				$('.busy-spinner').hide();
                    break;
            }

			engineCmd();
		},

		error: function(data) {
			//console.log('engineCmd: error branch: data=(' + JSON.stringify(data) + ')');
			setTimeout(function() {
				engineCmd();
			}, ENGINE_TIMEOUT);
		}
    });
}

// Command engine lite (for scripts-configs)
function engineCmdLite() {
	var cmd;

    $.ajax({
		type: 'GET',
		url: 'engine-cmd.php',
		async: true,
		cache: false,
		success: function(data) {
			//console.log('engineCmd: success branch: data=(' + data + ')');
			cmd = JSON.parse(data).split(',');

            if (cmd[0] == 'libregen_done') {
				$('.busy-spinner').hide();
                loadLibrary();
            }

			engineCmdLite();
		},

		error: function(data) {
			//console.log('engineCmd: error branch: data=(' + JSON.stringify(data) + ')');
			setTimeout(function() {
				engineCmdLite();
			}, ENGINE_TIMEOUT);
		}
    });
}

function inpSrcIndicator(cmd, msgText) {
	UI.currentFile = 'blank';

    // Set custom backdrop (if any)
    if (SESSION.json['renderer_backdrop'] == 'Yes') {
        if (SESSION.json['cover_backdrop'] == 'Yes' && MPD.json['coverurl'].indexOf('default-cover-v6') === -1) {
            $('#inpsrc-backdrop').html('<img class="ss-backdrop" ' + 'src="' + MPD.json['coverurl'] + '">');
            $('#inpsrc-backdrop').css('filter', 'blur(' + SESSION.json['cover_blur'] + ')');
            $('#inpsrc-backdrop').css('transform', 'scale(' + SESSION.json['cover_scale'] + ')');
        }
        else if (SESSION.json['bgimage'] != '') {
            $('#inpsrc-backdrop').html('<img class="ss-backdrop" ' + 'src="' + SESSION.json['bgimage'] + '">');
            $('#inpsrc-backdrop').css('filter', 'blur(0px)');
            $('#inpsrc-backdrop').css('transform', 'scale(1.0)');
        }
    }

    // Set the button and preamp volume
    // NOTE: Preamp volume #id will only exist if audioin != Local
	if (cmd.slice(-1) == '1') {
		$('#inpsrc-indicator').css('display', 'block');
		$('#inpsrc-msg').html(msgText);
		$('#inpsrc-preamp-volume').text(SESSION.json['mpdmixer'] == 'disabled' ? '0dB' : SESSION.json['volknob']);
	}
	else {
		$('#inpsrc-msg').html('');
		$('#inpsrc-indicator').css('display', '');
	}
}

// Show/hide CoverView screen saver
function screenSaver(cmd) {
	if ($('#inpsrc-indicator').css('display') == 'block' || UI.mobile) {
		return;
	}
	else if (cmd.slice(-1) == '1') {
		$('#ss-coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'alt="Cover art not found"' + '>');
        $('body').addClass('cv');

        // TEST: Fixes issue where some elements briefly remain on-screen when entering or returning from CoverView
        $('#lib-coverart-img').hide();

		coverView = true;
	}
}

// reconnect/reboot/restart
function renderReconnect() {
	//console.log('renderReconnect(): UI.restart=(' + UI.restart + ')');
	if (UI.restart == 'restart') {
		$('#restart').show();
	}
	else if (UI.restart == 'shutdown') {
		$('#shutdown').show();
	}
	else {
		$('#reconnect').show();
	}

	$('#countdown-display, #m-countdown, #playbar-countdown').countdown('pause');

	window.clearInterval(UI.knob);
	UI.hideReconnect = true;
}

function hideReconnect() {
	//console.log('hideReconnect(): (' + UI.hideReconnect + ')');
	$('#reconnect, #restart, #shutdown').hide();
	UI.hideReconnect = false;
}

// Disable volume knob for mpdmixer == disabled (0dB)
function disableVolKnob() {
	SESSION.json['volmute'] == '1';
    $('#volumedn, #volumeup, #volumedn-2, #volumeup-2').prop('disabled', true);
    $('#volumeup, #volumedn, #volumedn-2, #volumeup-2, .volume-display').css('opacity', '.3');
	$('.volume-display div, #inpsrc-preamp-volume, #playbar-volume-level').text('0dB');
	$('.volume-display').css('cursor', 'unset');

	if (UI.mobile) {
		$('#mvol-progress').css('width', '100%');
		$('.repeat').show();
        $('.volume-popup-btn').hide();
	}
}

// when last item in laylist finishes just update a few things, called from engineCmd()
function resetPlayCtls() {
	//console.log('resetPlayCtls():');
	$('#m-total, #playbar-total, #playbar-mtotal').html(updTimeKnob('0'));
	$('.play i').removeClass('fas fa-pause').addClass('fas fa-play');
	$('#total').html(updTimeKnob('0') + (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0 ? '<i class="fas fa-caret-up countdown-caret"></i>' : '<i class="fas fa-caret-down countdown-caret"></i>'));
	$('.playlist li.active ').removeClass('active');

	refreshTimeKnob();
    refreshTimer(0, 0, MPD.json['state']);

	$('#countdown-display, #m-countdown, #playbar-countdown, #playbar-mcount').html('00:00');
}

function renderUIVol() {
	//console.log('renderUIVol()');
	// Load session vars (required for multi-client)
    $.getJSON('command/moode.php?cmd=readcfgsystem', function(result) {
    	if (result === false) {
            console.log('renderUIVol(): No data returned from readcfgsystem');
    	}
        else {
            SESSION.json = result;
        }

    	// Disabled volume, 0dB output
    	if (SESSION.json['mpdmixer'] == 'disabled') {
    		disableVolKnob();
    	}
    	// Software or hardware volume
    	else {
    		// Sync vol and mute to UPnP controller
    		if (SESSION.json['feat_bitmask'] & FEAT_UPNPSYNC) {
    			// No renderers active
    			if (SESSION.json['btactive'] == '0' && SESSION.json['airplayactv'] == '0' && SESSION.json['slsvc'] == '0') {
    				if ((SESSION.json['volknob'] != MPD.json['volume']) && SESSION.json['volmute'] == '0') {
    					SESSION.json['volknob'] = MPD.json['volume']
                        $.post('command/moode.php?cmd=updcfgsystem', {'volknob': SESSION.json['volknob']});
    				}
    			}
    		}

    		// Update volume knobs
    		$('#volume').val(SESSION.json['volknob']).trigger('change');
    		$('.volume-display div, #inpsrc-preamp-volume, #playbar-volume-level').text(SESSION.json['volknob']);
    		$('#volume-2').val(SESSION.json['volknob']).trigger('change');
    		$('#mvol-progress').css('width', SESSION.json['volknob'] + '%');

    	   	// Update mute state
    		if (SESSION.json['volmute'] == '1') {
    			$('.volume-display div, #inpsrc-preamp-volume').text('mute');
                $('#playbar-volume-level').text('x');
    		}
    		else {
    			$('.volume-display div, #playbar-volume-level').text(SESSION.json['volknob']);
    		}
    	}
    });
}

function renderUI() {
	//console.log('renderUI()');
	var searchStr, searchEngine;

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

    // load session vars (required for multi-client)
    $.getJSON('command/moode.php?cmd=readcfgsystem', function(result) {
        if (result === false) {
            console.log('renderUI(): No data returned from readcfgsystem');
    	}
        else {
            SESSION.json = result;
        }

    	// Disabled volume, 0dB output
    	if (SESSION.json['mpdmixer'] == 'disabled') {
    		disableVolKnob();
    	}
    	// Software or hardware volume
    	else {
            // Volume button visibility
            if (UI.mobile) {
                $('.volume-popup-btn').show();
            }

    		// Update volume knobs
    		$('#volume').val(SESSION.json['volknob']).trigger('change');
    		$('.volume-display div, #inpsrc-preamp-volume, #playbar-volume-level').text(SESSION.json['volknob']);
    		$('#volume-2').val(SESSION.json['volknob']).trigger('change');
    		$('#mvol-progress').css('width', SESSION.json['volknob'] + '%');

    	   	// Update mute state
    		if (SESSION.json['volmute'] == '1') {
    			$('.volume-display div, #inpsrc-preamp-volume').text('mute');
                $('#playbar-volume-level').text('x');;
    		}
    		else {
    			$('.volume-display div, #playbar-volume-level').text(SESSION.json['volknob']);
    		}
    	}

    	// playback controls, playlist highlight
        if (MPD.json['state'] == 'play') {
    		$('.play i').removeClass('fas fa-play').addClass('fas fa-pause');
			document.body.style.setProperty('--npicon', npIcon);
    		$('.playlist li.active, .cv-playlist li.active').removeClass('active');
            $('.playlist li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('active');
            $('.cv-playlist li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('active');
        }
    	else if (MPD.json['state'] == 'pause' || MPD.json['state'] == 'stop') {
    		$('.play i').removeClass('fas fa-pause').addClass('fas fa-play');
			document.body.style.setProperty('--npicon', npIconPaused);
        }
    	//tt = updTimeKnob(MPD.json['time'] ? MPD.json['time'] : 0);
    	$('#total').html(updTimeKnob(MPD.json['time'] ? MPD.json['time'] : 0) + (MPD.json['artist'] == 'Radio station' ? '' :
            (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0 ? '<i class="fas fa-caret-up countdown-caret"></i>' : '<i class="fas fa-caret-down countdown-caret"></i>')));
    	$('#m-total, #playbar-total').html(updTimeKnob(MPD.json['time'] ? MPD.json['time'] : 0));
    	$('#playbar-mtotal').html('&nbsp;/&nbsp;' + updTimeKnob(MPD.json['time']));
        $('#playbar-total').text().length > 5 ? $('#playbar-countdown, #m-countdown, #playbar-total, #m-total').addClass('long-time') :
            $('#playbar-countdown, #m-countdown, #playbar-total, #m-total').removeClass('long-time');

    	//console.log('CUR: ' + UI.currentHash);
    	//console.log('NEW: ' + MPD.json['cover_art_hash']);
    	// compare new to current to prevent unnecessary image reloads
    	if (MPD.json['file'] !== UI.currentFile && MPD.json['cover_art_hash'] !== UI.currentHash) {
    		//console.log(MPD.json['coverurl']);
            // Original for Playback
     		$('#coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'data-adaptive-background="1" alt="Cover art not found"' + '>');
            // Thumbnail for Playbar
            if (MPD.json['file'] && !RegExp('/tidal/').test(MPD.json['file'])) {
                var image_url = MPD.json['artist'] == 'Radio station' ?
                    encodeURIComponent(MPD.json['coverurl'].replace('imagesw/radio-logos', 'imagesw/radio-logos/thumbs')) :
                    '/imagesw/thmcache/' + encodeURIComponent($.md5(MPD.json['file'].substring(0,MPD.json['file'].lastIndexOf('/')))) + '_sm.jpg'
                $('#playbar-cover').html('<img src="' + image_url + '">');
            }
            else {
	     		$('#coverart-url').html('<img class="coverart" ' + 'src="' + UI.defCover + 'data-adaptive-background="1" alt="Cover art not found"' + '>');
                $('#playbar-cover').html('<img src="' + 'images/default-cover-v6.png' + '">');
            }
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
    		if (coverView) {
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

    	// Extra metadata displayed under the cover
    	if (MPD.json['state'] === 'stop') {
    		$('#extra-tags-display').html('Not playing');
    	}
        else if (SESSION.json['extra_tags'].toLowerCase() == 'none' || SESSION.json['extra_tags'] == '') {
            $('#extra-tags-display').html('');
        }
        else if (MPD.json['artist'] == 'Radio station') {
    		$('#extra-tags-display').html((MPD.json['bitrate'] ? MPD.json['bitrate'] : 'Variable bitrate'));
    	}
    	else {
            var extraTagsDisplay = '';
            extraTagsDisplay = formatExtraTagsString();
            extraTagsDisplay ? $('#extra-tags-display').html(extraTagsDisplay) : $('#extra-tags-display').html(MPD.json['audio_sample_depth'] + '/' + MPD.json['audio_sample_rate']);
    	}

        // HD badge text
        var hdBadgeText = MPD.json['artist'] == 'Radio station' ? RADIO_HD_BADGE_TEXT : ALBUM_HD_BADGE_TEXT;
        $('#playback-hd-badge, #playbar-hd-badge, #ss-hd-badge').text(hdBadgeText);

    	// Default metadata
        if (MPD.json['album']) {
            $('#currentalbum').html(MPD.json['artist'] == 'Radio station' ? MPD.json['album'] : MPD.json['artist'] + ' - ' + MPD.json['album']);
            // For Soma FM station where we want use the short name from cfg_radio in Playbar and Coverview
            $('#playbar-currentalbum, #ss-currentalbum').html(MPD.json['artist'] == 'Radio station' ?
                (MPD.json['file'].indexOf('somafm') != -1 ? RADIO.json[MPD.json['file']]['name'] : MPD.json['album']) : MPD.json['artist'] + ' - ' + MPD.json['album']);
            //MPD.json['hidef'] == 'yes' ? $('#playback-hd-badge, #playbar-hd-badge, #ss-hd-badge').show() : $('#playback-hd-badge, #playbar-hd-badge, #ss-hd-badge').hide();
			MPD.json['hidef'] == 'yes' && SESSION.json['library_encoded_at'] && SESSION.json['library_encoded_at'] != '9' ? $('#playback-hd-badge, #playbar-hd-badge, #ss-hd-badge').show() : $('#playback-hd-badge, #playbar-hd-badge, #ss-hd-badge').hide();
        }
        else {
            $('#currentalbum, #playbar-currentalbum, #ss-currentalbum').html('');
        }

    	// Song title
    	if (MPD.json['title'] === 'Streaming source' || MPD.json['coverurl'] === UI.defCover || UI.mobile) {
    		$('#currentsong').html(MPD.json['title']);
    	}
    	// Add search url, see corresponding code in renderPlaylist()
    	else {
    		$('#currentsong').html(genSearchUrl(MPD.json['artist'], MPD.json['title'], MPD.json['album']));
    	}
    	$('#playbar-currentsong, #ss-currentsong').html(MPD.json['title']);
    	// Store songid for last track (toggle song)
        //console.log('UI.currentSongId: ' + UI.currentSongId, 'MPD.json[songid]: ' + MPD.json['songid']);
    	if (UI.currentSongId != MPD.json['songid']) {
    		toggleSongId = UI.currentSongId == 'blank' ? SESSION.json['toggle_songid'] : UI.currentSongId;
            $.post('command/moode.php?cmd=updcfgsystem', {'toggle_songid': toggleSongId});
    	}

    	// set current = new for next cycle
    	UI.currentFile = MPD.json['file'];
    	UI.currentHash = MPD.json['cover_art_hash'];
    	UI.currentSongId = MPD.json['songid'];

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

        // Render the Queue
        //console.log('ID ' + MPD.json['playlist'], MPD.json['idle_timeout_event'], MPD.json['state']);
        // Page load/reload, Queue changed (items added/removed)
        if (typeof(MPD.json['idle_timeout_event']) == 'undefined' ||
            MPD.json['idle_timeout_event'] == 'changed: playlist' ||
            GLOBAL.playlistChanged == true) {
            renderPlaylist();
        }
        //
        else {
            updateActivePlItem();
        }

    	// Ensure renderer overlays get applied in case MPD UI updates get there first after browser refresh
        // Input source
    	if (SESSION.json['inpactive'] == '1') {
    		inpSrcIndicator('inpactive1', SESSION.json['audioin'] + ' Input Active: <button class="btn volume-popup-btn" data-toggle="modal"><i class="fal fa-volume-up"></i></button><span id="inpsrc-preamp-volume"></span>' +
                '<br><a class="btn configure-renderer" href="inp-config.php">Input Source</a>'
            );
    	}
    	// Bluetooth renderer
    	if (SESSION.json['btactive'] == '1') {
    		inpSrcIndicator('btactive1', 'Bluetooth Active' + '<br><a class="btn configure-renderer" href="blu-config.php">BlueZ Config</a>');
     	}
    	// Airplay renderer
    	if (SESSION.json['airplayactv'] == '1') {
    		inpSrcIndicator('aplactive1', 'Airplay Active' + '<br><button class="btn disconnect-renderer" data-job="airplaysvc">disconnect</button>');
    	}
    	// Spotify renderer
    	if (SESSION.json['spotactive'] == '1') {
    		inpSrcIndicator('spotactive1', 'Spotify Active' + '<br><button class="btn disconnect-renderer" data-job="spotifysvc">disconnect</button>');
    	}
    	// Squeezelite renderer
    	if (SESSION.json['slactive'] == '1') {
    		inpSrcIndicator('slactive1', 'Squeezelite Active' + '<br><button class="btn disconnect-renderer" data-job="slsvc">turn off</button>');
    	}

    	// Database update
    	if (typeof(MPD.json['updating_db']) != 'undefined') {
    		$('.busy-spinner').show();
    	}
    	else {
    		$('.busy-spinner').hide();
    	}
    });
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

// Update active Playlist item
function updateActivePlItem() {
	//console.log('updateActivePlItem()');
    $.getJSON('command/moode.php?cmd=playlist', function(data) {
        if (data) {
            for (i = 0; i < data.length; i++) {
                // Current song
	            if (i == parseInt(MPD.json['song'])) {
                    // Radio station
    				if (typeof(data[i].Name) !== 'undefined' || (data[i].file.substr(0, 4) == 'http' && typeof(data[i].Artist) === 'undefined' && typeof(data[i].Comment) === 'undefined')) {
    	                // Line 1 title
    					// Csustom title for particular station
    	                if (typeof(data[i].Title) === 'undefined' || data[i].Title.trim() == '' || data[i].file == 'http://stream.radioactive.fm') {
    						$('#pl-' + (parseInt(MPD.json['song']) + 1).toString() + ' .pll1').html('Streaming source');
    					}
                        // Standard title
    					else {
                            $('#pl-' + (parseInt(MPD.json['song']) + 1).toString() + ' .pll1').html(data[i].Title);
    						if (i == parseInt(MPD.json['song'])) { // active
    							// Update in case MPD did not get Title tag at initial play
    							if (data[i].Title.substr(0, 4) === 'http' || MPD.json['coverurl'] === UI.defCover || UI.mobile) {
    								$('#currentsong').html(data[i].Title);
    							}
    							// Add search url, see corresponding code in renderUI()
    							else {
    								$('#currentsong').html(genSearchUrl(data[i].Artist, data[i].Title, data[i].Album));
    							}
    							$('#ss-currentsong, #playbar-currentsong').html(data[i].Title);
    						}
    					}
                    }
                }
            } // End loop
        }
    });

    setTimeout(function() {
        if ($('#playback-panel').hasClass('active')) {
            customScroll('playlist', parseInt(MPD.json['song']));
            if ($('#cv-playlist').css('display') == 'block') {
                customScroll('cv-playlist', parseInt(MPD.json['song']));
            }
        }
    }, DEFAULT_TIMEOUT);
}

// Render the Playlist
function renderPlaylist() {
	//console.log('renderPlaylist()');
    $.getJSON('command/moode.php?cmd=playlist', function(data) {
		var output = '';
        var playlistLazy = GLOBAL.nativeLazyLoad === true ? '<img loading="lazy" src=' : '<img class="lazy-playlistview" data-original=';

        // Save for use in delete/move modals
        UI.dbEntry[4] = typeof(data.length) === 'undefined' ? 0 : data.length;
		var option_show_playlistart = SESSION.json['playlist_art'] == 'Yes' ? true : false;

		// Format playlist items
        if (data) {
            for (i = 0; i < data.length; i++) {

	            // Item highlight
	            if (i == parseInt(MPD.json['song'])) {
	                output += '<li id="pl-' + (i + 1) + '" class="active pl-entry">';
	            }
				else {
	                output += '<li id="pl-' + (i + 1) + '" class="pl-entry">';
	            }

				if (typeof(data[i].Name) !== 'undefined' && data[i].file.substr(data[i].file.lastIndexOf('.') + 1).toLowerCase() == 'm4a') {
	                // Line 1 title
					output += '<span class="pl-action" data-toggle="context" data-target="#context-menu-playlist-item">' + (typeof(data[i].Time) == 'undefined' ? '' : formatSongTime(data[i].Time)) + '<br><b>&hellip;</b></span>';
	                output += '<span class="pll1">';
                    output += data[i].Name + '</span>';
					// Line 2 artist, album
					output += '<span class="pll2">'; // for clock radio
					output += (typeof(data[i].Artist) === 'undefined') ? 'Unknown artist' : data[i].Artist;
					output += ' - ';
					output += (typeof(data[i].Album) === 'undefined') ?  'Unknown album' : data[i].Album;
				}
				// Radio station
				else if (typeof(data[i].Name) !== 'undefined' || (data[i].file.substr(0, 4) == 'http' && typeof(data[i].Artist) === 'undefined' && typeof(data[i].Comment) === 'undefined')) {
                    var logoThumb = typeof(RADIO.json[data[i].file]) === 'undefined' ? '"images/notfound.jpg"' : '"imagesw/radio-logos/thumbs/' +
                        encodeURIComponent(RADIO.json[data[i].file]['name']) + '_sm.jpg"';
					output += option_show_playlistart && (typeof(data[i].Comment) === 'undefined' || data[i].Comment !== 'client=upmpdcli;')  ?
                        '<span class="pl-thumb">' + playlistLazy + logoThumb + '></span>' : '';
	                // Line 1 title
					// Custom name for particular station
	                if (typeof(data[i].Title) === 'undefined' || data[i].Title.trim() == '' || data[i].file == 'http://stream.radioactive.fm') {
						output += '<span class="pl-action" data-toggle="context" data-target="#context-menu-playlist-item">' + (typeof(data[i].Time) == 'undefined' ? '' : formatSongTime(data[i].Time)) + '<br><b>&hellip;</b></span>';
						output += '<span class="pll1">Streaming source</span>';
					}
                    // Standard title
					else {
						output += '<span class="pl-action" data-toggle="context" data-target="#context-menu-playlist-item">' + (typeof(data[i].Time) == 'undefined' ? '' : formatSongTime(data[i].Time)) + '<br><b>&hellip;</b></span>';
						output += '<span class="pll1">' + data[i].Title + '</span>';
						if (i == parseInt(MPD.json['song'])) { // active
							// Update in case MPD did not get Title tag at initial play
							if (data[i].Title.substr(0, 4) === 'http' || MPD.json['coverurl'] === UI.defCover || UI.mobile) {
								$('#currentsong').html(data[i].Title);
							}
							// Add search url, see corresponding code in renderUI()
							else {
								$('#currentsong').html(genSearchUrl(data[i].Artist, data[i].Title, data[i].Album));
							}
							$('#ss-currentsong, #playbar-currentsong').html(data[i].Title);
						}
					}

					// Line 2, station name
					output += '<span class="pll2">';
					output += '<i class="fas fa-microphone"></i> ';

					if (typeof(RADIO.json[data[i].file]) === 'undefined') {
						var name = typeof(data[i].Name) === 'undefined' ? 'Radio station' : data[i].Name;
						output += name;
						if (i == parseInt(MPD.json['song'])) { // active
							$('#playbar-currentalbum, #ss-currentalbum').html(name);
						}
					}
					else {
						output += RADIO.json[data[i].file]['name'];
						if (i == parseInt(MPD.json['song'])) { // active
							$('#playbar-currentalbum, #ss-currentalbum').html(RADIO.json[data[i].file]['name']);
						}
					}
				}
				// Song file or upnp url
				else {
					var thumb = RegExp('/tidal/').test(data[i].file) ? 'images/default-cover-v6.png' : 'imagesw/thmcache/' + encodeURIComponent($.md5(data[i].file.substring(0,data[i].file.lastIndexOf('/')))) + '_sm.jpg';
					output += option_show_playlistart ? '<span class="pl-thumb">' + playlistLazy + '"' + thumb + '"/></span>' : '';
	                // Line 1 title
					output += '<span class="pl-action" data-toggle="context" data-target="#context-menu-playlist-item">' + (typeof(data[i].Time) == 'undefined' ? '' : formatSongTime(data[i].Time)) + '<br><b>&hellip;</b></span>';
	                output += '<span class="pll1">';
					if (typeof(data[i].Title) === 'undefined') { // use file name
						var pos = data[i].file.lastIndexOf('.');

						if (pos == -1) {
							output += data[i].file; // Some upnp url's have no file ext
						}
						else {
							var filename = data[i].file.slice(0, pos);
							pos = filename.lastIndexOf('/');
							output += filename.slice(pos + 1); // Song filename (strip .ext)
						}
						output += '</span>';
					}
					// Use title
					else {
	                    output += data[i].Title + '</span>';
					}
					// Line 2 artist, album
					output += '<span class="pll2">';
					output += (typeof(data[i].Artist) === 'undefined') ? 'Unknown artist' : data[i].Artist;
					output += ' - ';
					output += (typeof(data[i].Album) === 'undefined') ?  'Unknown album' : data[i].Album;
				}

                output += '</span></div></li>';

            } // End loop
        }

		// Render playlist
        $('#playlist ul').html(output);
        $('#cv-playlist ul').html(output);

        if (output) {
            if (option_show_playlistart) {
    			lazyLode('playlist');
                lazyLode('cv-playlist');
    		}

            setTimeout(function() {
                if ($('#playback-panel').hasClass('active')) {
                    customScroll('playlist', parseInt(MPD.json['song']));
                    if ($('#cv-playlist').css('display') == 'block') {
                        customScroll('cv-playlist', parseInt(MPD.json['song']));
                    }
                }
            }, DEFAULT_TIMEOUT);
        }
        else {
            $('#playback-hd-badge, #playbar-hd-badge, #ss-hd-badge').hide();
        }

        // Reset
        GLOBAL.playlistChanged = false;
    });
}

// MPD commands for database, playlist, radio stations, saved playlists
function mpdDbCmd(cmd, path) {
	//console.log(cmd, path);
	var cmds = ['add_item', 'play_item', 'add_item_next', 'play_item_next', 'clear_add_item', 'clear_play_item',
        'add_group', 'play_group', 'add_group_next', 'play_group_next', 'clear_add_group', 'clear_play_group', 'update_library'];
	UI.dbCmd = cmd;

	if (cmds.indexOf(cmd) != -1 ) {
        GLOBAL.playlistChanged = true;
		$.post('command/moode.php?cmd=' + cmd, {'path': path}, function(path) {}, 'json');
	}
	else if (cmd == 'lsinfo' || cmd == 'listsavedpl') {
		$.post('command/moode.php?cmd=' + cmd, {'path': path}, function(data) {renderFolderView(data, path);}, 'json');
	}
    else if (cmd == 'lsinfo_radio') {
		renderRadioView();
	}
	else if (cmd == 'delsavedpl') {
		$.post('command/moode.php?cmd=' + cmd, {'path': path}, function(data) {}, 'json');
		$.post('command/moode.php?cmd=lsinfo', {'path': ''}, function(data) {renderFolderView(data, '');}, 'json');
	}
	else if (cmd == 'newstation' || cmd == 'updstation') {
        //RADIO.json[path['url']] = {'name': path['name'], 'type': path['type'], 'logo': path['logo']};
        cmd == 'newstation' ? notify('creating_station') : notify('updating_station');
        $.post('command/moode.php?cmd=' + cmd, {'path': path}, function(return_msg) {
            RADIO.json[path['url']] = {'name': path['name'], 'type': path['type'], 'logo': path['logo']};
            return_msg == 'OK' ? notify(cmd) : notify('validation_check', return_msg, 5000);
            $('#ra-refresh').click();
        }, 'json');
	}
	else if (cmd == 'delstation') {
        deleteRadioStationObject(path.slice(0,path.lastIndexOf('.')).substr(6));
        $.post('command/moode.php?cmd=' + cmd, {'path': path}, function() {
            notify('delstation');
            $('#ra-refresh').click();
        });
	}
}

// Render Folder view, order by dirs|playlists
function renderFolderView(data, path, searchstr) {
	//console.log('renderFolderView(): path=(' + path + ')');
	//console.log('UI.dbPos[10]= ' + UI.dbPos[10].toString());
	//console.log('UI.dbPos[' + UI.dbPos[10].toString() + ']= ' + UI.dbPos[UI.dbPos[10]].toString());
	UI.path = path;

	// Separate out dirs, playlists, files
    // NOTE: Exclude RADIO folder
	var dirs = [];
	var playlists =[];
	var files = [];
	var j = 0, k = 0, l = 0;
	for (var i = 0; i < data.length; i++) {
		if (typeof(data[i].directory) != 'undefined' && data[i].directory != 'RADIO') {
			dirs[j] = data[i];
			j = j + 1;
		}
		else if (typeof(data[i].playlist) != 'undefined') {
			playlists[k] = data[i];
			k = k + 1;
		}
		else {
            if (typeof(data[i].file) != 'undefined' && data[i].file.indexOf('RADIO') == -1) {
                files[l] = data[i];
    			l = l + 1;
            }
		}
	}

	// Sort directories and playlists
    // NOTE: Files are left in the order they appear in the MPD database and not sorted
    // Natural ordering
	try {
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
	}
    // Fallback to default ordering
	catch (e) {
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
	}

	// Merge back together
	data = dirs.concat(playlists).concat(files);

	// Output search tally if any
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

	// Output the list
    $('ul.database').html('');
	for (i = 0; i < data.length; i++) {
        $('ul.database').append(formatFolderViewEntries(data, path, i))
	}

	// Scroll and highlight
    // NOTE: Don't highlight if at root or only 1 item in list
	customScroll('folder', UI.dbPos[UI.dbPos[10]], 100);
	if (path != '' && UI.dbPos[UI.dbPos[10]] > 1) {
		$('#db-' + UI.dbPos[UI.dbPos[10]].toString()).addClass('active');
	}
}
// Format entries for Folder view
function formatFolderViewEntries(data, path, i) {
	var output = '';

	if (path == '' && typeof(data[i].file) != 'undefined') {
		var pos = data[i].file.lastIndexOf('/');
        path = pos == -1 ? '' : data[i].file.slice(0, pos);
	}

	if (typeof data[i].file != 'undefined') {
		// For CUE sheet and future extensions
		var fileExt = data[i].file.substr(data[i].file.lastIndexOf('.') + 1).toLowerCase();

		// Song files
		if (typeof data[i].Title != 'undefined') {
			output = '<li id="db-' + (i + 1) + '" data-path="' + data[i].file + '">'
			output += '<div class="db-icon db-song db-action">'; // Hack to enable entire line click for context menu
			output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder-item">';
            output += '<i class="fas fa-music sx db-browse db-browse-icon"></i></a></div>';
			output += '<div class="db-entry db-song">' + data[i].Title + ' <em class="songtime">' + data[i].TimeMMSS + '</em>';
			output += ' <span>' + data[i].Artist + ' - ' + data[i].Album + '</span></div></li>';
		}
		// Saved Playlist items
        // NOTE: File extensions are removed except for url's
		else {
			output = '<li id="db-' + (i + 1) + '" data-path="';
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

			// CUE sheet
            var itemType = '';
			if (fileExt == 'cue') {
				output += '"><div class="db-icon db-song db-browse db-action"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder-item"><i class="fas fa-list-ul icon-root sx db-browse-icon"></i></a></div><div class="db-entry db-song db-browse">';
				itemType = 'CUE sheet';
			}
			// Different icon for song file vs radio station in saved playlist
			else {
				if (data[i].file.substr(0,4) == 'http') {
					output += '"><div class="db-icon db-song db-browse db-action"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-savedpl-item" style="width:100vw;height:2em;"><i class="fas fa-microphone sx db-browse db-browse-icon"></i></a></div><div class="db-entry db-song db-browse">';
					itemType = typeof(RADIO.json[data[i].file]) === 'undefined' ? 'Radio station' : RADIO.json[data[i].file]['name'];
				}
                else {
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
			output = '';
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
		output = '<li id="db-' + (i + 1) + '" data-path="';
		output += data[i].directory;
		if (path == '') { // At the root
            output += '"><div class="db-icon db-action"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder"><i class="fas fa-hdd icon-root sx"></i></a></div><div class="db-entry db-folder db-browse">';
		}
		else {
            output += '"><div class="db-icon db-browse db-action"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder">';
            output += data[i].cover_url != '' ? '<img src="' + data[i].cover_url + '">' : '<i class="fas fa-folder sx"></i>';
            output += '</a></div>';
            output += '<div class="db-entry db-folder db-browse">'
		}
		output += data[i].directory.replace(path + '/', '');
		output += '</div></li>';
	}

	return output;
}

// Render Radio view
function renderRadioView() {
    var data = '';
    $.getJSON('command/moode.php?cmd=read_cfg_radio', function(data) {
        // Lazyload method
        var radioViewLazy = GLOBAL.nativeLazyLoad ? '<img loading="lazy" height="' + UI.thumbHW + '" width="' + UI.thumbHW + '" src="' : '<img class="lazy-radioview" height="' + UI.thumbHW + '" width="' + UI.thumbHW + 'data-original="';
        // Sort/Group and Show/Hide options
        var sortTag = SESSION.json['radioview_sort_group'].split(',')[0].toLowerCase();
        var groupMethod = SESSION.json['radioview_sort_group'].split(',')[1];
        var configuredGroupMethod = groupMethod; // NOTE: For code block "Mark the end of Favorites"
        var showHideMoodeStations = SESSION.json['radioview_show_hide'].split(',')[0];
        var showHideOtherStations = SESSION.json['radioview_show_hide'].split(',')[1];

        // Hide/Un-hide
        // NOTE: these are one-shot actions
        // Moode stations
        if (showHideMoodeStations == 'Hide all' || showHideMoodeStations == 'Un-hide all') {
            var newStationType = showHideMoodeStations == 'Hide all' ? 'h' : 'r';
            for (var i = 0; i < data.length; i++) {
                if (parseInt(data[i].id) < 499 && data[i].type != 'f') {
                    data[i].type = newStationType;
                }
            }
            if (data.length > 0) {
                $.post('command/moode.php?cmd=upd_cfg_radio_show_hide', {'stationBlock': 'Moode', 'stationType': newStationType});
            }
            // Reset
            SESSION.json['radioview_show_hide'] = 'No action,' + showHideOtherStations;
        }
        if (showHideMoodeStations == 'Hide geo-fenced' || showHideMoodeStations == 'Un-hide geo-fenced') {
            var newStationType = showHideMoodeStations == 'Hide geo-fenced' ? 'h' : 'r';
            for (var i = 0; i < data.length; i++) {
                if (parseInt(data[i].id) < 499 && data[i].type != 'f' && data[i].geo_fenced == 'Yes') {
                    data[i].type = newStationType;
                }
            }
            if (data.length > 0) {
                $.post('command/moode.php?cmd=upd_cfg_radio_show_hide', {'stationBlock': 'Moode geo-fenced', 'stationType': newStationType});
            }
            // Reset
            SESSION.json['radioview_show_hide'] = 'No action,' + showHideOtherStations;
        }
        // Other stations
        if (showHideOtherStations == 'Hide all' || showHideOtherStations == 'Un-hide all') {
            var newStationType = showHideOtherStations == 'Hide all' ? 'h' : 'r';
            for (var i = 0; i < data.length; i++) {
                if (parseInt(data[i].id) > 499 && data[i].type != 'f') {
                    data[i].type = newStationType;
                }
            }
            if (data.length > 0) {
                $.post('command/moode.php?cmd=upd_cfg_radio_show_hide', {'stationBlock': 'Other', 'stationType': newStationType});
            }
            // Reset
            SESSION.json['radioview_show_hide'] = showHideMoodeStations + ',No action';
        }

        // Generate filtered lists
        var allNonHiddenStations = [];
    	var regularStations = [];
        var favoriteStations = [];
        var hiddenMoodeStations = [];
        var hiddenOtherStations = [];
        var j = 0, k = 0, l = 0, m = 0, n = 0;
    	for (var i = 0; i < data.length; i++) {
            switch (data[i].type) {
                case 'r':
                    allNonHiddenStations[j] = data[i];
                    j = j + 1;
                    regularStations[k] = data[i];
                    k = k + 1;
                    break;
                case 'f':
                    allNonHiddenStations[j] = data[i];
                    j = j + 1;
                    favoriteStations[l] = data[i];
                    l = l + 1;
                    break;
                case 'h':
                    if (parseInt(data[i].id) < 499 ) {
                        hiddenMoodeStations[m] = data[i];
                        m = m + 1;
                    }
                    else {
                        hiddenOtherStations[n] = data[i];
                        n = n + 1;
                    }
                    break;
            }
    	}

        // Sorts
        // All non-hidden stations
        try {
    		var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
            allNonHiddenStations.sort(function(a, b) {
                if (sortTag == 'name') {
                    return collator.compare(removeArticles(a[sortTag]), removeArticles(b[sortTag]));
                }
                else if (sortTag == 'genre') {
                    return collator.compare(removeArticles(a[sortTag].split(', ')[0]), removeArticles(b[sortTag].split(', ')[0]));
                }
                else {
                    return collator.compare(a[sortTag], b[sortTag]);
                }
            });
        }
        catch (e) {
            allNonHiddenStations.sort(function(a, b) {
                if (sortTag == 'name') {
                    a = removeArticles(a[sortTag]), b = removeArticles(b[sortTag]);
                    return a > b ? 1 : (a < b ? -1 : 0);
                }
                else if (sortTag == 'genre') {
                    a = removeArticles(a[sortTag].split(', ')[0]), b = removeArticles(b[sortTag].split(', ')[0]);
                    return a > b ? 1 : (a < b ? -1 : 0);
                }
                else {
                    return collator.compare(a[sortTag], b[sortTag]);
                }
            });
        }
        // Regular stations
        try {
    		var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
            regularStations.sort(function(a, b) {
                if (sortTag == 'name') {
                    return collator.compare(removeArticles(a[sortTag]), removeArticles(b[sortTag]));
                }
                else if (sortTag == 'genre') {
                    return collator.compare(removeArticles(a[sortTag].split(', ')[0]), removeArticles(b[sortTag].split(', ')[0]));
                }
                else {
                    return collator.compare(a[sortTag], b[sortTag]);
                }
            });
        }
        catch (e) {
            regularStations.sort(function(a, b) {
                if (sortTag == 'name') {
                    a = removeArticles(a[sortTag]), b = removeArticles(b[sortTag]);
                    return a > b ? 1 : (a < b ? -1 : 0);
                }
                else if (sortTag == 'genre') {
                    a = removeArticles(a[sortTag].split(', ')[0]), b = removeArticles(b[sortTag].split(', ')[0]);
                    return a > b ? 1 : (a < b ? -1 : 0);
                }
                else {
                    return collator.compare(a[sortTag], b[sortTag]);
                }
            });
        }
        // Favorite stations
        try {
    		var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
            favoriteStations.sort(function(a, b) {
                return collator.compare(removeArticles(a['name']), removeArticles(b['name']));
            });
        }
        catch (e) {
            favoriteStations.sort(function(a, b) {
                a = removeArticles(a['name']), b = removeArticles(b['name']);
                return a > b ? 1 : (a < b ? -1 : 0);
            });
        }
        // Hidden stations
        try {
    		var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
            hiddenMoodeStations.sort(function(a, b) {
                return collator.compare(removeArticles(a['name']), removeArticles(b['name']));
            });
            hiddenOtherStations.sort(function(a, b) {
                return collator.compare(removeArticles(a['name']), removeArticles(b['name']));
            });
        }
        catch (e) {
            hiddenOtherStations.sort(function(a, b) {
                a = removeArticles(a['name']), b = removeArticles(b['name']);
                return a > b ? 1 : (a < b ? -1 : 0);
            });
            hiddenOtherStations.sort(function(a, b) {
                a = removeArticles(a['name']), b = removeArticles(b['name']);
                return a > b ? 1 : (a < b ? -1 : 0);
            });
        }

        // Set filtered list
        if (showHideMoodeStations == 'Show hidden') {
            data = hiddenMoodeStations;
        }
        else if (showHideOtherStations == 'Show hidden') {
            data = hiddenOtherStations;
        }
        else if (groupMethod == 'Favorites first') {
            data = favoriteStations.concat(regularStations);
        }
        else if (groupMethod == 'Sort tag' || groupMethod == 'No grouping') {
            data =  allNonHiddenStations;
        }

        // Encoded-at div's
        // SESSION.json['library_encoded_at']
        // 0 = No (searchable), 1 = HD only, 2 = Text, 3 = Badge, 9 = No
        var encodedAtOption = parseInt(SESSION.json['library_encoded_at']);
        var radioViewNvDiv = '';
        var radioViewHdDiv = '';
        //var radioViewTxDiv = '';
        var radioViewBgDiv = '';

        // Favorites header (if any) and end flag
        var output = '';
        var endOfFavs = true;
        if (groupMethod == 'Favorites first' && favoriteStations.length > 0) {
            output = '<li class="horiz-rule-radioview">Favorites</li>';
            endOfFavs = false;
        }

        // Clear search results (if any)
        $('.btnlist-top-ra').show();
        $("#searchResetRa").hide();
        showSearchResetRa = false;
    	$('#ra-search-keyword').val('');
    	$('#ra-filter').val('');

        // Format filtered list
        var numericHeaderPrinted = false;
        var lastSortTagValue = '';
    	for (var i = 0; i < data.length; i++) {
            // Encoded-at div's
            if (encodedAtOption != 9) {
                var bitrate = parseInt(data[i].bitrate);
                var bitrateAndFormat = data[i].bitrate + 'K ' + data[i].format;
                var radioViewNvDiv = encodedAtOption <= 1 ? '<div class="lib-encoded-at-notvisible">' + bitrateAndFormat + '</div>' : '';
                var radioViewHdDiv = (encodedAtOption == 1 && bitrate > RADIO_BITRATE_THRESHOLD) ? '<div class="lib-encoded-at-hdonly">' + RADIO_HD_BADGE_TEXT + '</div>' : '';
                //var radioViewTxDiv = encodedAtOption == 2 ? '<div class="lib-encoded-at-text">' + bitrateAndFormat + '</div>' : '';
                var radioViewBgDiv = encodedAtOption == 3 ? '<div class="lib-encoded-at-badge">' + bitrateAndFormat + '</div>' : '';
            }

            // Metadata div's
            var subGenreDiv = sortTag == 'genre' ? '<div class="radioview-metadata-text">' + data[i].genre.substr(data[i].genre.indexOf(', ') + 1) + '</div>' : '';
            var countryDiv = sortTag == 'region' ? '<div class="radioview-metadata-text">' + data[i].country + '</div>' : '';
            var bitrateDiv = (sortTag == 'bitrate' || sortTag == 'format') ? '<div class="radioview-metadata-text">' + data[i].bitrate + 'K ' + data[i].format + '</div>' : '';

            // Output Favorites first
            if (groupMethod == 'Favorites first' && data[i].type == 'f') {
                //NOP
            }
            // Change to Sort tag grouping unless method is No grouping
            else if (groupMethod != 'No grouping') {
                groupMethod = 'Sort tag';
            }

            // Mark the end of Favorites
            if (configuredGroupMethod == 'Favorites first') {
                if (endOfFavs === false && data[i].type != 'f' && lastSortTagValue != '') {
                    lastSortTagValue = '';
                    endOfFavs = true;
                }
            }

            // Construct group header
            if (groupMethod == 'Sort tag') {
                var currentChr1 = removeArticles(data[i][sortTag]).substr(0, 1).toUpperCase();
                var lastChr1 = removeArticles(lastSortTagValue).substr(0, 1).toUpperCase()
                if (sortTag == 'name' && currentChr1 != lastChr1) {
                    if (isNaN(currentChr1) === false && numericHeaderPrinted === false) {
                        output += '<li class="horiz-rule-radioview">0-9</li>';
                        numericHeaderPrinted = true;
                    }
                    else if (isNaN(currentChr1) === true) {
                        output += '<li class="horiz-rule-radioview">' + currentChr1 + '</li>';
                    }
                }
                else if (sortTag == 'genre' && data[i][sortTag].split(', ')[0] != lastSortTagValue.split(', ')[0]) {
                    output += '<li class="horiz-rule-radioview">' + data[i][sortTag].split(', ')[0] + '</li>';
                }
                else if (sortTag != 'name' && sortTag != 'genre' && data[i][sortTag] != lastSortTagValue) {
                    output += '<li class="horiz-rule-radioview">' + data[i][sortTag] + (sortTag == 'bitrate' ? ' kbps' : '') + '</li>';
                }
            }

            // Construct station entries
            var imgUrl = data[i].logo == 'local' ? 'imagesw/radio-logos/thumbs/' + data[i].name + '.jpg' : data[i].logo;
    		output += '<li id="ra-' + (i + 1) + '" data-path="' + 'RADIO/' + data[i].name + '.pls';
    		output += '"><div class="db-icon db-song db-browse db-action">' + radioViewLazy + imgUrl  + '"><div class="cover-menu" data-toggle="context" data-target="#context-menu-radio-item"></div></div><div class="db-entry db-song db-browse"></div>';
            output += radioViewHdDiv;
			output += radioViewBgDiv;
            output += '<span class="station-name">' + data[i].name + '</span>';
            output += subGenreDiv;
            output += countryDiv;
            output += bitrateDiv;
            //output += radioViewTxDiv;
            output += radioViewNvDiv;
            output += '</li>';

            lastSortTagValue = data[i][sortTag];
    	}

        // Render the list
    	$('ul.database-radio').html(output);
    });
}

// Update time knob
function updTimeKnob(mpdTime) {
	if (MPD.json['artist'] == 'Radio station' && typeof(MPD.json['duration']) === 'undefined') {
		var str = '';
		$('#total').html('').addClass('total-radio'); // radio station svg
		$('#playbar-mtime').css('display', 'block');
		$('#playbar-mtotal').hide();
	}
	else {
		var str = formatSongTime(mpdTime);
		$('#total').removeClass('total-radio'); // radio station svg
		$('#playbar-mtime').css('display', '');
		$('#playbar-mtotal').show();
	}
    return str;
}

// Initialize the countdown timers
function refreshTimer(startFrom, stopTo, state) {
	$('#countdown-display, #m-countdown, #playbar-countdown, #playbar-mcount').countdown('destroy');

    if (state == 'play' || state == 'pause') {
		if (SESSION.json['timecountup'] == "1" || parseInt(MPD.json['time']) == 0) {
			$('#countdown-display').countdown({since: -(startFrom), compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			$('#m-countdown, #playbar-countdown, #playbar-mcount').countdown({since: -(startFrom), compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
		else {
			$('#countdown-display').countdown({until: startFrom, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			$('#m-countdown, #playbar-countdown, #playbar-mcount').countdown({until: startFrom, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }

	    if (state == 'pause') {
			$('#countdown-display, #m-countdown, #playbar-countdown, #playbar-mcount').countdown('pause');
		}
    }
	else if (state == 'stop') {
		if (SESSION.json['timecountup'] == "1" || parseInt(MPD.json['time']) == 0) {
			$('#countdown-display').countdown({since: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			$('#m-countdown, #playbar-countdown, #playbar-mcount').countdown({since: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
    	}
		else {
			$('#countdown-display').countdown({until: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			$('#m-countdown, #playbar-countdown, #playbar-mcount').countdown({until: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }

		$('#countdown-display, #m-countdown, #playbar-countdown, #playbar-mcount').countdown('pause');
    }
}

// Update time knob, time track
function refreshTimeKnob() {
	var initTime, delta;
    window.clearInterval(UI.knob)
    initTime = parseInt(MPD.json['song_percent']);
    delta = parseInt(MPD.json['time']) / 1000;
	if (UI.mobile) {
		$('#timetrack').val(initTime * 10).trigger('change');
	}
    // Playback
	else if (currentView.indexOf('playback') !== -1){
		$('#time').val(initTime * 10).trigger('change');
	}
    // Library
    else {
		$('#playbar-timetrack').val(initTime * 10).trigger('change');
	}

	if (MPD.json['state'] === 'stop') {
	    $('#countdown-display').countdown('destroy');
		if (UI.mobile) {
			$('#m-total, #m-countdown, #playbar-mcount').html('00:00');
			$('#playbar-mtotal').html('&nbsp;/&nbsp;00:00');
		}
        else {
			$('#playbar-total, #playbar-countdown, #countdown-display').html('00:00');
		}
	}
	// Radio station (never has a duration)
	else if (MPD.json['artist'] == 'Radio station' && typeof(MPD.json['duration']) === 'undefined') {
    //else if (delta === 0 || isNaN(delta)) {
		if (UI.mobile) {
			$('#timeline').hide();
		}
		else {
			$('#playbar-timeline').css('display', 'none');
			$('#playbar-title').css('padding-bottom', '0');
		}
	}
	// Song file
	else {
		if (UI.mobile) {
			$('#timeline').show();
		}
		else {
			$('#playbar-timeline').show();
            $('#playbar-title').css('padding-bottom', '1rem');
		}
	}

    if (MPD.json['state'] === 'play') {
        // Move these out of the timer
		var tt = $('#timetrack');
		var pb = $('playbar-#timetrack');
		var ti = $('#time');

		var cur = currentView.indexOf('playback');
        UI.knob = setInterval(function() {
			if (UI.mobile || cur == -1 || coverView == true) {
				if (!timeSliderMove) {
					syncTimers();
					if (UI.mobile) {
						tt.val(initTime * 10).trigger('change');
					}
				}
			}
            delta === 0 ? initTime = initTime + 0.5 : initTime = initTime + 0.1; // fast paint when radio station playing
			if (!UI.mobile) {
	            if (delta === 0 && initTime > 100) { // stops painting when radio (delta = 0) and knob fully painted
					window.clearInterval(UI.knob)
					UI.knobPainted = true;
	            }
           		ti.val(initTime * 10).trigger('change');
			}
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
	    hours = ~~(seconds / 3600); // ~~ = faster Math.floor
    	seconds %= 3600;
    	minutes = ~~(seconds / 60);

        hh = hours == 0 ? '' : (hours == 1 ? hours + ' hour' : hours + ' hours');
        mm = minutes == 0 ? '' : (minutes == 1 ? minutes + ' min' : minutes + ' mins');

		if (hours > 0) {
			if (minutes > 0) {
				output = hh + ' ' + mm;
			}
            else {
				output = hh;
			}
		}
        else {
			output = mm;
		}
    }
    return formatNumCommas(output);
}

function formatNumCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function countdownRestart(startFrom) {
    $('#countdown-display, #m-countdown, #playbar-countdown, #playbar-mcount').countdown('destroy');
    $('#countdown-display').countdown({since: startFrom, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
    $('#m-countdown, #playbar-countdown, #playbar-mcount').countdown({since: startFrom, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
}

// volume control
function setVolume(level, event) {
    level = parseInt(level);
	level = level > GLOBAL.mpdMaxVolume ? GLOBAL.mpdMaxVolume : level;
	level = level < 0 ? 0 : level;
    //console.log(level, event);

	// Unmuted, set volume (incl 0 vol)
	if (SESSION.json['volmute'] == '0') {
		SESSION.json['volknob'] = level.toString();
		// Update sql value and issue mpd setvol in one round trip
		sendVolCmd('POST', 'updvolume', {'volknob': SESSION.json['volknob']}, true); // Async
    }
	// Muted
	else {
		if (level == 0 && event == 'mute')	{
			sendMpdCmd('setvol 0');
			//console.log('setvol 0');
		}
		else {
			// Vol up/dn btns pressed, just store the volume for display
			SESSION.json['volknob'] = level.toString();
		}

        $.post('command/moode.php?cmd=updcfgsystem', {'volknob': SESSION.json['volknob']});
    }
}

// Scroll item so it's visible
function customScroll(list, itemNum, speed) {
	//console.log('list=' + list + ', itemNum=(' + itemNum + '), speed=(' + speed + ')');
	var listSelector, scrollSelector, chDivisor, centerHeight, scrollTop, itemHeight, scrollCalc, scrollOffset, itemPos;
	speed = typeof(speed) === 'undefined' ? 200 : speed;

    switch (list) {
        case 'folder':
            listSelector = '#database';
    		scrollSelector = listSelector;
    		chDivisor = 4;
            break;
        case 'playlist':
            if (isNaN(itemNum)) {return;} // Exit if last item in pl ended
    		listSelector = '#playlist';
    		scrollSelector = '#container-playlist';
    		chDivisor = 6;
            break;
        case 'cv-playlist': // TODO: change to 'cvpl'
            if (isNaN(itemNum)) {return;} // Exit if last item in CoverView pl ended
    		listSelector = '#cv-playlist';
    		scrollSelector = listSelector;
    		chDivisor = 6;
            break;
        case 'genres':
    		listSelector = '#lib-genre';
    		scrollSelector = listSelector;
    		chDivisor = 6;
            break;
        case 'artists':
    		listSelector = '#lib-artist';
    		scrollSelector = listSelector;
    		chDivisor = 6;
            break;
        case 'albums':
        case 'albumcovers':
        	listSelector = list == 'albums' ? '#lib-album' : '#lib-albumcover';
    		scrollSelector = listSelector;
    		chDivisor = 6;
    		itemNum = list == 'albums' ? itemNum : itemNum + 1;
            break;
        case 'tracks':
    		listSelector = '#trackscontainer';
    		scrollSelector = '#lib-file';
    		chDivisor = 600;
            break;
        case 'radio':
        case 'radio_headers':
    		listSelector = '#database-radio';
    		scrollSelector = listSelector;
    		chDivisor = list == 'radio' ? 6 : 600;
            break;
    }

	// Item position
	//console.log($(listSelector + ' ul li:nth-child(' + itemNum + ')').position());
	if ($(listSelector + ' ul li:nth-child(' + itemNum + ')').position() != undefined) {
		itemPos = $(listSelector + ' ul li:nth-child(' + itemNum + ')').position().top;
	}
	else {
		itemPos = 0;
	}

	// Scroll to
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

// all music menu item
$('.view-all').click(function(e) {
	$('.view-recents span').hide();
	$('.view-all span').show();
	$('#menu-header').click()
	GLOBAL.musicScope = 'all';
	GLOBAL.searchLib = '';
    GLOBAL.searchRadio = false;
    LIB.recentlyAddedClicked = false;
	LIB.filters.albums.length = 0;
	LIB.filters.year.length = 0;
	UI.libPos.fill(-2);

	filterLib();
    renderAlbums();

	storeLibPos(UI.libPos);
	$("#searchResetLib").hide();
	showSearchResetLib = false;
	setLibMenuHeader();
	//LIB.filters.artists.length ? $('#menu-header').text('Albums by' + LIB.artist.filter[0]) : $('#menu-header').text('Albums by Artist');
});
// recently played menu item
$('.view-recents').click(function(e) {
	GLOBAL.musicScope = 'recent';
	$('.view-all span').hide();
	$('.view-recents span').show();
    LIB.recentlyAddedClicked = true;
	LIB.filters.albums.length = 0;
	LIB.filters.year.length = 0;
	UI.libPos.fill(-2);

	filterLib();
    renderAlbums();

    // Reverse order (NOTE: we may use this someday)
    //$('#lib-album ul').css('transform', 'rotate(180deg)');
    //$('#lib-album ul > li').css('transform', 'rotate(-180deg)');

	storeLibPos(UI.libPos);
	$("#searchResetLib").hide();
	showSearchResetLib = false;
	$('#menu-header').text('Recently Added (' + getParamOrValue('param', SESSION.json['library_recently_added']) + ')');
});

// context menus and main menu
$('.context-menu a').click(function(e) {
    var path = UI.dbEntry[0]; // file path or item num
    //console.log($(this).data('cmd'));

	// CONTEXT MENUS

	if ($(this).data('cmd') == 'add_item' || $(this).data('cmd') == 'add_item_next') {
		mpdDbCmd($(this).data('cmd'), path);
		notify($(this).data('cmd'));
	}
	else if ($(this).data('cmd') == 'play_item' || $(this).data('cmd') == 'play_item_next') {
		mpdDbCmd($(this).data('cmd'), path);
	}
	else if ($(this).data('cmd') == 'clear_add_item' || $(this).data('cmd') == 'clear_play_item') {
		mpdDbCmd($(this).data('cmd'), path);
		notify($(this).data('cmd'));
		// If its a playlist, preload the saved playlist name
		if (path.indexOf('/') == -1 && path != 'NAS' && path != 'RADIO' && path != 'SDCARD') {
			$('#pl-saveName').val(path);
		}
		else {
			$('#pl-saveName').val('');
		}
	}
    else if ($(this).data('cmd') == 'update_folder') {
        submitLibraryUpdate(path);
	}
	else if ($(this).data('cmd') == 'update_library') {
        submitLibraryUpdate();
	}
    else if ($(this).data('cmd') == 'track_info_folder') {
        $.post('command/moode.php?cmd=track_info', {'path': path}, function(result) {
            $('#track-info-text').html(result);
            $('#track-info-modal').modal();
        }, 'json');
	}
	else if ($(this).data('cmd') == 'delsavedpl') {
		$('#savedpl-path').html(path);
		$('#deletesavedpl-modal').modal();
	}
	else if ($(this).data('cmd') == 'editradiostn') {
        $.post('command/moode.php?cmd=readstationfile', {'path': UI.dbEntry[0]}, function(result) {
            var stationName = path.slice(path.lastIndexOf('/') + 1); // Trim 'RADIO/sub_directory/'
            stationName = stationName.slice(0, stationName.lastIndexOf('.')); // Trim .pls
            GLOBAL.editStationId = result['id']; // This is to pass to the update station routine so it can uniquely identify the row
    		$('#edit-station-name').val(stationName);
    		$('#edit-station-url').val(result['station']);
            $('#edit-logoimage').val('');
            $('#info-toggle-edit-logoimage').css('margin-left','60px');
            $('#preview-edit-logoimage').html('<img src="../imagesw/radio-logos/thumbs/' + stationName + '.jpg">');
            $('#edit-station-tags').css('margin-top', '30px');
            $('#edit-station-type span').text(getParamOrValue('param', result['type']));
            $('#edit-station-genre').val(result['genre']);
            $('#edit-station-broadcaster').val(result['broadcaster']);
            $('#edit-station-language').val(result['language']);
            $('#edit-station-country').val(result['country']);
            $('#edit-station-region').val(result['region']);
            $('#edit-station-bitrate').val(result['bitrate']);
            $('#edit-station-format').val(result['format']);
            $('#edit-station-geo-fenced span').text(result['geo_fenced']);
            //$('#edit-station-reserved1').val(result['reserved1']);
            //$('#edit-station-reserved2').val(result['reserved2']);

    		$('#editstation-modal').modal();
        }, 'json');
	}
	else if ($(this).data('cmd') == 'delstation') {
		$('#station-path').html(path.slice(0,path.lastIndexOf('.')).substr(6)); // Trim 'RADIO/' and '.pls'
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
		bgImgChange = false;

		// Set up disclosures
		var temp = SESSION.json['appearance_modal_state'].split(',');
		for (var x = 0; x < 5; x++) {
			if (temp[x] == '1') {
				$('#appearance-modal div.control-group').eq(x).show();
				$('#appearance-modal .accordian .dtopen').eq(x).show();
				$('#appearance-modal .accordian .dtclose').eq(x).hide();
			}
		}

		// Theme and background
		$('#theme-name span').text(SESSION.json['themename']);
        $.post('command/moode.php?cmd=readthemename', function(obj) {
            var themelist = '';
    		for (i = 0; i < obj.length; i++) {
    			themelist += '<li class="modal-dropdown-text"><a href="#notarget" data-cmd="theme-name-sel" style="background-color: rgb(' + obj[i]['bg_color'] + ')"><span class="text" style="color: #' + obj[i]['tx_color'] + '">' + obj[i]['theme_name'] + '</span></a></li>';
    		}
    		$('#theme-name-list').html(themelist);
    		$('#alpha-blend span').text(SESSION.json['alphablend']);
    		$('#adaptive-enabled span').text(SESSION.json['adaptive']);
    		$('#accent-color span').text(SESSION.json['accent_color']);
    		$('#error-bgimage').text('');
    		$.ajax({
    			url:'imagesw/bgimage.jpg',
    		    type:'HEAD',
    		    success: function() {
    				$('#remove-bgimage').show();
    				$('#choose-bgimage').hide();
    				$('#current-bgimage').html('<img src="imagesw/bgimage.jpg">');
    				$('#info-toggle-bgimage').css('margin-left','60px');
    		    },
    		    error: function() {
    				$('#remove-bgimage').hide();
    				$('#choose-bgimage').show();
    				$('#current-bgimage').html('');
    				$('#info-toggle-bgimage').css('margin-left','5px');
    		    }
    		});
    		$('#cover-backdrop-enabled span').text(SESSION.json['cover_backdrop']);
    		$('#cover-blur span').text(SESSION.json['cover_blur']);
    		$('#cover-scale span').text(SESSION.json['cover_scale']);

            // Playback options
            $('#playlist-art-enabled span').text(SESSION.json['playlist_art']);
            $('#renderer-backdrop span').text(SESSION.json['renderer_backdrop']);

            // Library options
            $('#library-flatlist-filter span').text(SESSION.json['library_flatlist_filter']);
            (SESSION.json['library_flatlist_filter'] == 'Format' || SESSION.json['library_flatlist_filter'] == 'Directory') ?
                $('#library-flatlist-filter-div').show() : $('#library-flatlist-filter-div').hide();
            $('#library-flatlist-filter-str').val(SESSION.json['library_flatlist_filter_str']);
            $('#instant-play-action span').text(SESSION.json['library_instant_play']);
            $('#show-genres-column span').text(SESSION.json['library_show_genres']);
            $('#show-tagview-covers span').text(SESSION.json['library_tagview_covers']);
            $('#show-encoded-at span').text(getParamOrValue('param', SESSION.json['library_encoded_at']));
            $('#ellipsis-limited-text span').text(SESSION.json['library_ellipsis_limited_text']);
            $('#thumbnail-columns span').text(SESSION.json['library_thumbnail_columns']);
            $('#albumview-sort-order span').text('by ' + SESSION.json['library_albumview_sort']);
            $('#tagview-sort-order span').text('by ' + SESSION.json['library_tagview_sort']);
            $('#compilation-identifier').val(SESSION.json['library_comp_id']);
            $('#recently-added span').text(getParamOrValue('param', SESSION.json['library_recently_added']));
            $('#ignore-articles').val(SESSION.json['library_ignore_articles']);
            $('#utf8-char-filter span').text(SESSION.json['library_utf8rep']);
            $('#hires-thumbnails span').text(SESSION.json['library_hiresthm']);
            $('#cover-search-priority span').text(getParamOrValue('param', SESSION.json['library_covsearchpri']));
    		// Coverview screen saver
            $('#scnsaver-timeout span').text(getParamOrValue('param', SESSION.json['scnsaver_timeout']));
    		$('#scnsaver-style span').text(SESSION.json['scnsaver_style']);
            // Other options
    		$('#font-size span').text(SESSION.json['font_size']);
    		$('#play-history-enabled span').text(SESSION.json['playhist']);
    		$('#extra-tags').val(SESSION.json['extra_tags']);

            $('#appearance-modal').modal();
        }, 'json');
    }

	// cover view (screen saver)
    else if ($(this).data('cmd') == 'scnsaver') {
		screenSaver('1');
	}

    // playback history log
    else if ($(this).data('cmd') == 'viewplayhistory') {
        $.getJSON('command/moode.php?cmd=readplayhistory', function(obj) {
    		var output = '';
    		for (i = 1; i < obj.length; i++) {
    			output += obj[i];
    		}
            $('ol.playhistory').html(output);
            $('#playhistory-modal').modal();
        });
    }

    // help
    else if ($(this).data('cmd') == 'quickhelp') {
		$('#quickhelp').load('quickhelp.html');
        $('#quickhelp-modal').modal();
    }

    // about
    else if ($(this).data('cmd') == 'aboutmoode') {
		$('#sys-raspbian-ver').text(SESSION.json['raspbianver']);
		$('#sys-kernel-ver').text(SESSION.json['kernelver']);
		$('#sys-processor-arch').text(SESSION.json['procarch']);
		$('#sys-hardware-rev').text(SESSION.json['hdwrrev']);
		$('#sys-mpd-ver').text(SESSION.json['mpdver']);
        $('#about-modal').modal();
    }

	// Remove highlight after selecting action menu item
	if (UI.dbEntry[3].substr(0, 3) == 'db-') {
		$('#' + UI.dbEntry[3]).removeClass('active');
	}
});

// Update clock radio settings
$('#btn-clockradio-update').click(function(e){
    var startHH, startMM, startDays, stopHH, stopMM, stopDays;

	SESSION.json['clkradio_mode'] = $('#clockradio-mode span').text();
    SESSION.json['clkradio_name'] = $('#clockradio-playname').val();

	$('#clockradio-starttime-hh').val().length == 1 ? startHH = '0' + $('#clockradio-starttime-hh').val() : startHH = $('#clockradio-starttime-hh').val();
	$('#clockradio-starttime-mm').val().length == 1 ? startMM = '0' + $('#clockradio-starttime-mm').val() : startMM = $('#clockradio-starttime-mm').val();
	$('#clockradio-stoptime-hh').val().length == 1 ? stopHH = '0' + $('#clockradio-stoptime-hh').val() : stopHH = $('#clockradio-stoptime-hh').val();
	$('#clockradio-stoptime-mm').val().length == 1 ? stopMM = '0' + $('#clockradio-stoptime-mm').val() : stopMM = $('#clockradio-stoptime-mm').val();

	startDays =
        ($('#clockradio-start-mon').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-start-tue').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-start-wed').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-start-thu').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-start-fri').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-start-sat').prop('checked') === true ? '1' : '0') + ',' +
		($('#clockradio-start-sun').prop('checked') === true ? '1' : '0');

	stopDays =
        ($('#clockradio-stop-mon').prop('checked') === true ? '1' : '0') + ',' +
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

 	// Header badge
	if (SESSION.json['clkradio_mode'] == 'Clock Radio' || SESSION.json['clkradio_mode'] == 'Sleep Timer') {
		$('#clockradio-icon').removeClass('clockradio-off');
		$('#clockradio-icon').addClass('clockradio-on');
	}
	else {
		$('#clockradio-icon').removeClass('clockradio-on');
		$('#clockradio-icon').addClass('clockradio-off');
	}

	// NOTE: UI.dbEntry[0] = pl song pos or -1 depending on whether modal was launched from context menu "Set for clock radio" or Configuration modal "Clock radio"
	if (UI.dbEntry[0] != '-1') {
        $.getJSON('command/moode.php?cmd=getplitemfile&songpos=' + UI.dbEntry[0], function(result) {
            SESSION.json['clkradio_item'] = result;
            updateClockRadioCfgSys();
        });
	}
    else {
        updateClockRadioCfgSys();
    }

    notify('updclockradio');
});
function updateClockRadioCfgSys() {
    // Update database
    $.post('command/moode.php?cmd=updcfgsystem',
        {
        'clkradio_mode': SESSION.json['clkradio_mode'],
        'clkradio_item': SESSION.json['clkradio_item'].replace(/'/g, "''"), // use escaped single quotes for sql i.e., two single quotes,
        'clkradio_name': SESSION.json['clkradio_name'].replace(/'/g, "''"),
        'clkradio_start': SESSION.json['clkradio_start'],
        'clkradio_stop': SESSION.json['clkradio_stop'],
        'clkradio_volume': SESSION.json['clkradio_volume'],
        'clkradio_action': SESSION.json['clkradio_action']
        },
        function(){
            // Update globals within worker loop
            $.get('command/moode.php?cmd=updclockradio');
        }
    );
}

// Update appearance options
$('#btn-appearance-update').click(function(e){
	// Detect certain changes
	var accentColorChange = false;
	var themeSettingsChange = false;
    var libraryOptionsChange = false;
	var scnSaverTimeoutChange = false;
	var scnSaverStyleChange = false;
    var extraTagsChange = false;
    var playHistoryChange = false;
	var fontSizeChange = false;
    var encodedAtChange = false;
    var playlistArtChange = false;

	// Set open/closed state for accordion headers
	var temp = [0,0,0,0,0];
	for (var x = 0; x < 5; x++) {
		if ($('#appearance-modal div.control-group').eq(x).css('display') == 'block') {
			temp[x] = 1;
		}
	}
	SESSION.json['appearance_modal_state'] = temp[0] + ',' + temp[1] + ',' + temp[2] + ',' + temp[3] + ',' + temp[4];

	// Theme and backgrounds
	if (SESSION.json['themename'] != $('#theme-name span').text()) {themeSettingsChange = true;}
	if (SESSION.json['accent_color'] != $('#accent-color span').text()) {themeSettingsChange = true; accentColorChange = true;}
	if (SESSION.json['alphablend'] != $('#alpha-blend span').text()) {themeSettingsChange = true;}
	if (SESSION.json['adaptive'] != $('#adaptive-enabled span').text()) {themeSettingsChange = true;}
	if (SESSION.json['cover_backdrop'] != $('#cover-backdrop-enabled span').text()) {themeSettingsChange = true;}
	if (SESSION.json['cover_blur'] != $('#cover-blur span').text()) {themeSettingsChange = true;}
	if (SESSION.json['cover_scale'] != $('#cover-scale span').text()) {themeSettingsChange = true;}

    // Playback options
    if (SESSION.json['playlist_art'] != $('#playlist-art-enabled span').text()) {playlistArtChange = true;}
    //if (SESSION.json['render_backdrop'] != $('#renderer-backdrop span').text()) {}

    // Library options
    if (SESSION.json['library_flatlist_filter'] != $('#library-flatlist-filter span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_flatlist_filter_str'] != $('#library-flatlist-filter-str').val()) {libraryOptionsChange = true;}
    if (SESSION.json['library_instant_play'] != $('#instant-play-action span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_show_genres'] != $('#show-genres-column span').text()) {
		$('#show-genres-column span').text() == "Yes" ? $('#top-columns').removeClass('nogenre') : $('#top-columns').addClass('nogenre');
	}
    if (SESSION.json['library_tagview_covers'] != $('#show-tagview-covers span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_encoded_at'] != getParamOrValue('value', $('#show-encoded-at span').text())) {encodedAtChange = true;}
    if (SESSION.json['library_ellipsis_limited_text'] != $('#ellipsis-limited-text span').text()) {
		$('#ellipsis-limited-text span').text() == "Yes" ? $('#library-panel').addClass('limited') : $('#library-panel').removeClass('limited');
	}
    if (SESSION.json['library_thumbnail_columns'] != $('#thumbnail-columns span').text()) {
		//setLibraryThumbnailCols($('#thumbnail-columns span').text().substring(0,1));
		libraryOptionsChange = true;
	}
    if (SESSION.json['library_albumview_sort'] != $('#albumview-sort-order span').text().replace('by ', '')) {libraryOptionsChange = true;}
    if (SESSION.json['library_tagview_sort'] != $('#tagview-sort-order span').text().replace('by ', '')) {libraryOptionsChange = true;}
    if (SESSION.json['library_comp_id'] != $('#compilation-identifier').val()) {libraryOptionsChange = true;}
    if (SESSION.json['library_recently_added'] != getParamOrValue('value', $('#recently-added span').text())) {libraryOptionsChange = true;}
    if (SESSION.json['library_ignore_articles'] != $('#ignore-articles').val()) {libraryOptionsChange = true;}
    if (SESSION.json['library_utf8rep'] != $('#utf8-char-filter span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_hiresthm'] != $('#hires-thumbnails span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_covsearchpri'] != getParamOrValue('value', $('#cover-search-priority span').text())) {libraryOptionsChange = true;}
    // Coverview screen saver
    if (SESSION.json['scnsaver_timeout'] != getParamOrValue('value', $('#scnsaver-timeout span').text())) {scnSaverTimeoutChange = true;}
	if (SESSION.json['scnsaver_style'] != $('#scnsaver-style span').text()) {scnSaverStyleChange = true;}
    // Other options
    if (SESSION.json['font_size'] != $('#font-size span').text()) {fontSizeChange = true;};
    if (SESSION.json['extra_tags'] != $('#extra-tags').val()) {extraTagsChange = true;}
    if (SESSION.json['playhist'] != $('#play-history-enabled span').text()) {playHistoryChange = true;}

	// Theme and backgrounds
	SESSION.json['themename'] = $('#theme-name span').text();
	SESSION.json['accent_color'] = $('#accent-color span').text();
	SESSION.json['alphablend'] = $('#alpha-blend span').text();
	SESSION.json['adaptive'] = $('#adaptive-enabled span').text();
	SESSION.json['cover_backdrop'] = $('#cover-backdrop-enabled span').text();
	SESSION.json['cover_blur'] = $('#cover-blur span').text();
	SESSION.json['cover_scale'] = $('#cover-scale span').text();

    // Playback options
    SESSION.json['playlist_art'] = $('#playlist-art-enabled span').text();
    SESSION.json['renderer_backdrop'] = $('#renderer-backdrop span').text();

    // Library options
    SESSION.json['library_flatlist_filter'] = $('#library-flatlist-filter span').text();
    SESSION.json['library_flatlist_filter_str'] = $('#library-flatlist-filter-str').val().trim();
    SESSION.json['library_instant_play'] = $('#instant-play-action span').text();
    SESSION.json['library_show_genres'] = $('#show-genres-column span').text();
    SESSION.json['library_tagview_covers'] = $('#show-tagview-covers span').text();
    SESSION.json['library_encoded_at'] = getParamOrValue('value', $('#show-encoded-at span').text());
    SESSION.json['library_ellipsis_limited_text'] = $('#ellipsis-limited-text span').text();
    SESSION.json['library_thumbnail_columns'] = $('#thumbnail-columns span').text();
    SESSION.json['library_albumview_sort'] = $('#albumview-sort-order span').text().replace('by ', '');
    SESSION.json['library_tagview_sort'] = $('#tagview-sort-order span').text().replace('by ', '');
    SESSION.json['library_comp_id'] = $('#compilation-identifier').val().trim();
    SESSION.json['library_recently_added'] = getParamOrValue('value', $('#recently-added span').text());
    SESSION.json['library_ignore_articles'] = $('#ignore-articles').val().trim();
    SESSION.json['library_utf8rep'] = $('#utf8-char-filter span').text();
    SESSION.json['library_hiresthm'] = $('#hires-thumbnails span').text();
    SESSION.json['library_covsearchpri'] = getParamOrValue('value', $('#cover-search-priority span').text());
    // Ccovreview screen saver
    SESSION.json['scnsaver_timeout'] = getParamOrValue('value', $('#scnsaver-timeout span').text());
	SESSION.json['scnsaver_style'] = $('#scnsaver-style span').text();
    // Other options
    SESSION.json['font_size'] = $('#font-size span').text();
	SESSION.json['playhist'] = $('#play-history-enabled span').text();
	SESSION.json['extra_tags'] = $('#extra-tags').val();

	if (fontSizeChange == true) {
		setFontSize();
		window.dispatchEvent(new Event('resize')); // resize knobs if needed
	}
	if (scnSaverTimeoutChange == true) {
        $.get('command/moode.php?cmd=resetscnsaver');
	}
	if (accentColorChange == true) {
		accentColor = themeToColors(SESSION.json['accent_color']);
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
		lastYIQ = 0;

		if (SESSION.json['cover_backdrop'] == 'Yes' && MPD.json['coverurl'].indexOf('default-cover-v6') === -1) {
			$('#cover-backdrop').html('<img class="ss-backdrop" ' + 'src="' + MPD.json['coverurl'] + '">');
			$('#cover-backdrop').css('filter', 'blur(' + SESSION.json['cover_blur'] + ')');
			$('#cover-backdrop').css('transform', 'scale(' + SESSION.json['cover_scale'] + ')');
		}
		else {
			$('#cover-backdrop').html('');
		}

		setColors();
	}
	if(playlistArtChange == true) {
		renderPlaylist();
	}

    // Update database
    $.post('command/moode.php?cmd=updcfgsystem',
        {
        'themename': SESSION.json['themename'],
        'accent_color': SESSION.json['accent_color'],
        'alphablend': SESSION.json['alphablend'],
        'adaptive': SESSION.json['adaptive'],
        'cover_backdrop': SESSION.json['cover_backdrop'],
        'cover_blur': SESSION.json['cover_blur'],
        'cover_scale': SESSION.json['cover_scale'],
        'library_instant_play': SESSION.json['library_instant_play'],
        'library_show_genres': SESSION.json['library_show_genres'],
        'library_tagview_covers': SESSION.json['library_tagview_covers'],
        'library_encoded_at': SESSION.json['library_encoded_at'],
        'library_ellipsis_limited_text': SESSION.json['library_ellipsis_limited_text'],
        'library_thumbnail_columns': SESSION.json['library_thumbnail_columns'],
        'library_albumview_sort': SESSION.json['library_albumview_sort'],
        'library_tagview_sort': SESSION.json['library_tagview_sort'],
        'library_comp_id': SESSION.json['library_comp_id'],
        'library_recently_added': SESSION.json['library_recently_added'],
        'library_ignore_articles': SESSION.json['library_ignore_articles'],
        'library_utf8rep': SESSION.json['library_utf8rep'],
        'library_hiresthm': SESSION.json['library_hiresthm'],
        'library_covsearchpri': SESSION.json['library_covsearchpri'],
        'scnsaver_timeout': SESSION.json['scnsaver_timeout'],
        'scnsaver_style': SESSION.json['scnsaver_style'],
        'font_size': SESSION.json['font_size'],
        'playhist': SESSION.json['playhist'],
        'extra_tags': SESSION.json['extra_tags'],
        'appearance_modal_state': SESSION.json['appearance_modal_state'],
        'playlist_art': SESSION.json['playlist_art'],
        'renderer_backdrop': SESSION.json['renderer_backdrop'],
        'library_flatlist_filter': SESSION.json['library_flatlist_filter'],
        'library_flatlist_filter_str': SESSION.json['library_flatlist_filter_str']
        },
        function() {
            if (extraTagsChange || scnSaverStyleChange || playHistoryChange || libraryOptionsChange ||
                (SESSION.json['bgimage'] != '' && SESSION.json['cover_backdrop'] == 'No') || UI.bgImgChange == true) {
                notify('settings_updated', 'Auto-refresh in 2 seconds');
				// set library & radio thumb image size
				getThumbHW();
                setTimeout(function() {
                    location.reload(true);
                }, 2000);
            }
            else if (encodedAtChange) {
                $('#ra-refresh').click();
                loadLibrary();
            }
            else {
                notify('settings_updated');
            }
        }
    );
});

$('#library-flatlist-filter span').on('DOMSubtreeModified',function(){
    ($('#library-flatlist-filter span').text() == 'Format' || $('#library-flatlist-filter span').text() == 'Directory') ?
        $('#library-flatlist-filter-div').show() : $('#library-flatlist-filter-div').hide();
});

/*function setLibraryThumbnailCols(cols) {
    //var map = {6:'16vw,45vw', 7:'14vw,30vw', 8:'12vw,22vw'}
    var map = {6:'15vw,45vw', 7:'13vw,30vw', 8:'12vw,22vw'}
    var css = map[cols].split(',');
    document.body.style.setProperty('--thumbcols', css[0]);
    document.body.style.setProperty('--mthumbcols', css[1]);
}*/

// Remove bg image (NOTE choose bg image is in indextpl.html)
$('#remove-bgimage').click(function(e) {
	e.preventDefault();
	if ($('#current-bgimage').html() != '') {
		$('#choose-bgimage').show();
		$('#remove-bgimage').hide();
		$('#current-bgimage').html('');
		$('#cover-backdrop').css('background-image','');
		$('#info-toggle-bgimage').css('margin-left','5px');
        $.post('command/moode.php?cmd=rmbgimage');
		UI.bgImgChange = true;
	}
    // So modal stays open
	return false;
});
// Import bg image to server
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

	UI.bgImgChange = true;
	imgUrl = (URL || webkitURL).createObjectURL(files[0]);
	$('#current-bgimage').html("<img src='" + imgUrl + "' />");
	$('#info-toggle-bgimage').css('margin-left','60px');
	URL.revokeObjectURL(imgUrl);
	var reader = new FileReader();
	reader.onload = function(e) {
		var dataURL = reader.result;
		// Strip off the header from the dataURL: 'data:[<MIME-type>][;charset=<encoding>][;base64],<data>'
		var data = dataURL.match(/,(.*)$/)[1];
        $.post('command/moode.php?cmd=setbgimage', {'blob': data});

	}
	reader.readAsDataURL(files[0]);
}

// Import station logo image to server
function newLogoImage(files) {
	if (files[0].size > 1000000) {
		$('#error-new-logoimage').text('Image must be less than 1MB in size');
		return;
	}
	else if (files[0].type != 'image/jpeg') {
		$('#error-new-logoimage').text('Image format must be JPEG');
		return;
	}
	else {
		$('#error-new-logoimage').text('');
	}

	imgUrl = (URL || webkitURL).createObjectURL(files[0]);
	$('#preview-new-logoimage').html("<img src='" + imgUrl + "' />");
	$('#info-toggle-new-logoimage').css('margin-left','60px');
    $('#new-station-tags').css('margin-top', '30px');
	var stationName = $('#new-station-name').val();
	URL.revokeObjectURL(imgUrl);
	var reader = new FileReader();
	reader.onload = function(e) {
		var dataURL = reader.result;
		// Strip off the header from the dataURL: 'data:[<MIME-type>][;charset=<encoding>][;base64],<data>'
		var data = dataURL.match(/,(.*)$/)[1];
        $.post('command/moode.php?cmd=setlogoimage', {'name': stationName, 'blob': data});
	}
	reader.readAsDataURL(files[0]);
}
function editLogoImage(files) {
	if (files[0].size > 1000000) {
		$('#error-edit-logoimage').text('Image must be less than 1MB in size');
		return;
	}
	else if (files[0].type != 'image/jpeg') {
		$('#error-edit-logoimage').text('Image format must be JPEG');
		return;
	}
	else {
		$('#error-edit-logoimage').text('');
	}

	imgUrl = (URL || webkitURL).createObjectURL(files[0]);
	$('#preview-edit-logoimage').html("<img src='" + imgUrl + "' />");
	$('#info-toggle-edit-logoimage').css('margin-left','60px');
	var stationName = $('#edit-station-name').val();
	URL.revokeObjectURL(imgUrl);
	var reader = new FileReader();
	reader.onload = function(e) {
		var dataURL = reader.result;
		// Strip off the header from the dataURL: 'data:[<MIME-type>][;charset=<encoding>][;base64],<data>'
		var data = dataURL.match(/,(.*)$/)[1];
        $.post('command/moode.php?cmd=setlogoimage', {'name': stationName, 'blob': data});
	}
	reader.readAsDataURL(files[0]);
}

// Import station zip package to server
function importStationPkg(files) {
    //console.log('files[0].size=(' + files[0].size + ')');
    if (files[0].size > 75000000) {
		$('#import-export-msg').text('ZIP file must be less than 75MB in size');
		return;
	}

    $('#import-export-msg').text('Importing...');
	objUrl = (URL || webkitURL).createObjectURL(files[0]);
	URL.revokeObjectURL(objUrl);
	var reader = new FileReader();
	reader.onload = function(e) {
		var dataURL = reader.result;
		// Strip off the header from the dataURL: 'data:[<MIME-type>][;charset=<encoding>][;base64],<data>'
        // For zip files its data:application/zip;base64,
		var data = dataURL.match(/,(.*)$/)[1];
        $.post('command/moode.php?cmd=import_stations', {'blob': data}, function() {
            $('#import-export-msg').text('Import complete');
            $('#import-station-pkg').val('');
        });
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

// Custom select controls
$('body').on('click', '.dropdown-menu .custom-select a', function(e) {
    var selector = '#' + $(this).data('cmd').substr(0, $(this).data('cmd').indexOf('-sel'));
    $(selector + ' span').text($(this).text());

    if ($(this).data('cmd') == 'clockradio-mode-sel') {
        setClkRadioCtls($(this).text());
    }
});

$('#system-restart').click(function(e) {
	UI.restart = 'restart';
	notify('restart', '', 8000);
    $.get('command/moode.php?cmd=reboot');
});

$('#system-shutdown').click(function(e) {
	UI.restart = 'shutdown';
    notify('shutdown', '', 8000);
    $.get('command/moode.php?cmd=poweroff');
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
	}
    else if (r === max) {
		h = (g - b) / delta;
	}
    else if (g === max) {
		h = 2 + (b - r) / delta;
	}
    else if (b === max) {
		h = 4 + (r - g) / delta;
	}

	h = Math.min(h * 60, 360);

	if (h < 0) {
		h += 360;
	}

	l = (min + max) / 2;

	if (max === min) {
		s = 0;
	}
    else if (l <= 0.5) {
		s = delta / (max + min);
	}
    else {
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
	}
    else {
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
		}
        else if (2 * t3 < 1) {
			val = t2;
		}
        else if (3 * t3 < 2) {
			val = t1 + (t2 - t1) * (2 / 3 - t3) * 6;
		}
        else {
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
	}
    else {
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
		}
        else if (2 * t3 < 1) {
			val = t2;
		}
        else if (3 * t3 < 2) {
			val = t1 + (t2 - t1) * (2 / 3 - t3) * 6;
		}
        else {
			val = t1;
		}

		rgb[i] = Math.round(val * 255);
	}
	var tempa = 'rgba('.concat(rgb[0],',',rgb[1],',',rgb[2],',',alpha,')');
	return tempa;
}

// take an rgb color and apply a frac(tion) of it to an rgba color, this is used to fake doing an rgba color on a
// background that would then requite additional compositing. return rgba(r,g,b,op) color string.

function rgbaToRgb(frac, op, rgba, rgb) {
	var r3 = Math.round(((1 - frac) * rgb[0]) + (frac * rgba[0]));
	var g3 = Math.round(((1 - frac) * rgb[1]) + (frac * rgba[1]));
	var b3 = Math.round(((1 - frac) * rgb[2]) + (frac * rgba[2]));
	var color = 'rgba(' + r3 + ', ' + g3 + ', ' + b3 + ', ' + op + ')';
	return color;
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

// generate a set of colors based on the background and text color for use in buttons, etc.
//
// temp1 = theme/adaptBack(ground), temp2 = theme/adaptColor, temprgba is an array holding the rgba components,
// temprgb is an array holding the rgb version of the text color

// generate a set of colors based on the background and text color for use in buttons, etc.
//
// temp1 = theme/adaptBack(ground), temp2 = theme/adaptColor, temprgba is an array holding the rgba components,
// temprgb is an array holding the rgb version of the text color

function btnbarfix(temp1,temp2) {
	var temprgba = splitColor(temp1);
	var temprgb = hexToRgb(temp2);
	var tempx = 0; // adjust the opacity if the alphablend value falls below a certain threshold to make it more visible
	if ((SESSION.json['alphablend']) < .85) {
		tempx = ((.9 - (SESSION.json['alphablend'])));
		if (tempx > .4) {tempx = .4}
	}
	tempx < .15 ? tempy = .25 : tempy = .1 + tempx;
	document.body.style.setProperty('--btnbarcolor', getYIQ(temp1) > 127 ? 'rgba(32,32,32,' + tempy + ')' : 'rgba(224,224,224,' + tempy + ')');
	tempcolor = rgbaToRgb(.7 - tempx, '0.2', temprgba, temprgb); // btnshade
	document.body.style.setProperty('--btnshade', tempcolor);
	tempcolor = rgbaToRgb(.8 - tempx, '0.6', temprgba, temprgb); // btnshade2
	document.body.style.setProperty('--btnshade2', tempcolor);
	tempcolor = rgbaToRgb(.3, '1.0', temprgba, temprgb); // textvariant
	document.body.style.setProperty('--textvariant', tempcolor);
	tempcolor = rgbaToRgb(.6, '.7', temprgba, temprgb); // textvariant
	document.body.style.setProperty('--btnshade3', tempcolor);
	document.body.style.setProperty('--btnshade4', getYIQ(temp1) > 127 ? 'rgba(32,32,32,0.10)' : 'rgba(208,208,208,0.17)');
	$('#content').hasClass('visacc') ? op = .95 : op = .9;
	document.body.style.setProperty('--modalbkdr', rgbaToRgb(.95, op, temprgba, temprgb));
	if ($('#content').hasClass('visacc')) {
		(currentView.indexOf('playback') == -1 || SESSION.json['adaptive'] == 'No') ? UI.accenta = themeMcolor : UI.accenta = adaptMcolor;
	}
    else {
		var tempa = hexToRgb(accentColor);
		UI.accenta = rgbaToRgb(.3 - tempx, .75, temprgba, tempa);
	}
	document.body.style.setProperty('--btnbarback', rgbaToRgb(.90, '.9', temprgba, temprgb));
    document.body.style.setProperty('--config_modal_btn_bg', 'rgba(128,128,128,0.12)');
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
			npIcon = 'url("../images/audiod.svg")';
			npIconPaused = 'url("../images/audiod-flat.svg")'
			document.body.style.setProperty('--timethumb', 'url("' + thumbd + '")');
			document.body.style.setProperty('--fatthumb', 'url("' + fatthumbd + '")');
			document.body.style.setProperty('--timecolor', 'rgba(96,96,96,0.25)');
			document.body.style.setProperty('--trackfill', 'rgba(48,48,48,1.0)');
			document.body.style.setProperty('--radiobadge', 'url("../images/radio-d.svg")');
			setTimeout(function() {
				$('.playbackknob, .volumeknob').trigger('configure',{"bgColor":"rgba(32,32,32,0.06)",
					"fgColor":UI.accenta
				});
			}, DEFAULT_TIMEOUT);
		}
		else {
			npIcon = 'url("../images/audiow.svg")';
			npIconPaused = 'url("../images/audiow-flat.svg")'
			document.body.style.setProperty('--timethumb', 'url("' + thumbw + '")');
			document.body.style.setProperty('--fatthumb', 'url("' + fatthumbw + '")');
			document.body.style.setProperty('--timecolor', 'rgba(240,240,240,0.25)');
			document.body.style.setProperty('--trackfill', 'rgba(240,240,240,1.0)');
			document.body.style.setProperty('--radiobadge', 'url("../images/radio-l.svg")');
			setTimeout(function() {
				$('.playbackknob, .volumeknob').trigger('configure',{"bgColor":"rgba(224,224,224,0.09)",
					"fgColor":UI.accenta
				});
				//UI.mobile ? '' : $('#playback-controls').show();
			}, DEFAULT_TIMEOUT);
		}
		document.body.style.setProperty('--npicon', npIcon);
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

// Manages Playbar when scrolling
$(window).on('scroll', function(e) {
    if (UI.mobile && GLOBAL.scriptSection == 'panels' && currentView.indexOf('playback') != -1) {
		if ($(window).scrollTop() > 1 && !showMenuTopW) {
			$('#playback-controls').hide();
			$('#container-playlist').css('visibility','visible');
			$('#menu-bottom').show();
			$('#menu-top').css('height', $('#menu-top').css('line-height'));
			$('#menu-top').css('backdrop-filter', 'blur(20px)');
			showMenuTopW = true;
		}
		else if (UI.mobile && $(window).scrollTop() == '0' ) {
			$('#container-playlist').css('visibility','hidden');
			$('#playback-controls').css('display', '');
			$('#menu-bottom').hide();
			$('#menu-top').css('height', '0');
			$('#menu-top').css('backdrop-filter', '');
			showMenuTopW = false;
		}
	}
});

// Get CSS rule object
function getCSSRule(ruleName) {
	for (var i = 0; i < document.styleSheets.length; i++) {
		var styleSheet = document.styleSheets[i];

		var cssRules = styleSheet.cssRules;
		for (var j = 0; j < cssRules.length; j++) {
			var cssRule = cssRules[j];
			if (cssRule.selectorText == ruleName) {
				return cssRule;
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

// Alphabits quick search
$('#index-genres li').on('click', function(e) {
	listLook('genresList li', 'genres', $(this).text());
});
$('#index-artists li').on('click', function(e) {
	listLook('artistsList li', 'artists', $(this).text());
});
$('#index-albums li').on('click', function(e) {
    // .artist-name or .album-name
	className = SESSION.json['library_tagview_sort'].toLowerCase().split('/');
	SESSION.json['library_tagview_covers'] == "Yes" ? classPrefix = '-name-art' : classPrefix = '-name';
    var selector = '.' + className[0] + classPrefix;
    listLook('albumsList li ' + selector, 'albums', $(this).text());
});
$('#index-albumcovers li').on('click', function(e) {
    // .artist-name or .album-name
    var selector = '.' + SESSION.json['library_albumview_sort'].toLowerCase() + '-name';
    var selector2 = selector.replace(/\/year/g, '');
    listLook('albumcovers li ' + selector2, 'albumcovers', $(this).text());
});
$('#index-browse li').on('click', function(e) {
	listLook('database li', 'folder', $(this).text());
});
$('#index-radio li').on('click', function(e) {
    list = SESSION.json['radioview_sort_group'].split(',')[1] == 'No grouping' ? 'radio' : 'radio_headers';
	listLook('radiocovers li', list, $(this).text());
});

function listLook(selector, list, searchText) {
    //console.log(selector, list, searchText);
	itemNum = 0;

	if (searchText != '#') {
        if (list == 'radio') {
            $('#' + selector).each(function() {
                var text = removeArticles($(this).children('span').text().toLowerCase());
                if (text.substr(0, 1) == searchText) {return false;}
        		itemNum++;
        	});
        }
        else if (list == 'radio_headers') {
            $('#' + selector).each(function() {
                var text = $(this).hasClass('horiz-rule-radioview') ? removeArticles($(this).text().toLowerCase()) : '';
                if (text.substr(0, 1) == searchText) {return false;}
        		itemNum++;
        	});
        }
        else {
            $('#' + selector).each(function() {
                var text = removeArticles($(this).text().toLowerCase());
                if (text.substr(0, 1) == searchText) {return false;}
        		itemNum++;
        	});
        }
	}

	if (itemNum != $('#' + selector).length) {
		customScroll(list, itemNum, 200);
	}
}

// radio pos
function storeRadioPos(pos) {
	//console.log('radio_pos', pos);
    $.post('command/moode.php?cmd=updcfgsystem', {'radio_pos': pos});
}
// library pos
function storeLibPos(pos) {
	//console.log('lib_pos', pos[0], pos[1], pos[2]);
    $.post('command/moode.php?cmd=updcfgsystem', {'lib_pos': pos[0] + ',' + pos[1] + ',' + pos[2]});
}

// Switch to Library
$('#coverart-url, #playback-switch').click(function(e){
	if ($('#playback-panel').hasClass('cv')) {
		e.stopImmediatePropagation();
		return;
	}

    // TEST: Fixes issue where some elements briefly remain on-screen when switching between Playback and Library
    $('#coverart-link').hide();

	currentView = currentView.split(',')[1];
    $('#container-playlist').css('visibility','hidden');
	$('#menu-top').css('height', '0');
	$('#menu-top').css('backdrop-filter', '');
	$('#menu-bottom, .viewswitch').css('display', 'flex');
    syncTimers();

	if (currentView == 'tag') {
		makeActive('.tag-view-btn','#library-panel','tag');
		setTimeout(function() {
			if (UI.libPos[0] >= 0) {
				customScroll('albums', UI.libPos[0], 200);
			}
		}, DEFAULT_TIMEOUT);

        if (!GLOBAL.libRendered) {
            loadLibrary();
        }
	}
	else if (currentView == 'album') {
		makeActive('.album-view-btn','#library-panel','album');
		setTimeout(function() {
			if (UI.libPos[1] >= 0) {
				customScroll('albumcovers', UI.libPos[1], 0);
                if ($('#tracklist-toggle').text().trim() == 'Hide tracks') {
                    $('#bottom-row').css('display', 'flex')
        			$('#lib-albumcover').css('height', 'calc(47% - 2em)'); // Was 1.75em
        			$('#index-albumcovers').hide();
                }
			}
		}, DEFAULT_TIMEOUT);

        if (!GLOBAL.libRendered) {
            loadLibrary();
        }
	}
	else if (currentView == 'radio') {
		makeActive('.radio-view-btn','#radio-panel',currentView);
		setTimeout(function() {
			if (UI.radioPos >= 0) {
                customScroll('radio', UI.radioPos, 0);
			}
		}, DEFAULT_TIMEOUT);
	}
	// Default to folder view
	else {
		makeActive('.folder-view-btn','#folder-panel','folder');
	}
});

// Switch to Playback
$('#playbar-switch, #playbar-cover, #playbar-title').click(function(e){
    //console.log('click playbar');
    if (coverView) {
        return;
    }
	if (currentView.indexOf('playback') == 0) {
        // Already in playback means mobile and view has scrolled, so scroll to top
		$(window).scrollTop(0);
	}
	else {
		currentView = 'playback,' + currentView;
        $.post('command/moode.php?cmd=updcfgsystem', {'current_view': currentView});

		syncTimers();
		setColors();

        // TEST: Fixes issue where some elements briefly remain on-screen when switching between Playback and Library
        $('#coverart-link').show();

		$('#menu-header').text('');
		$('#container-playlist').css('visibility','');
		$('#menu-bottom, .viewswitch').css('display', 'none');
		$('#folder-panel, #radio-panel, #library-panel').removeClass('active');
		$('#playback-panel').addClass('active');
		$('#playback-controls').css('display', '');

		if (UI.mobile) {
            // Make sure playlist is hidden and controls are showing
			showMenuTopW = false;
			$(window).scrollTop(0);
			$('#content').css('height', 'unset');
			$('#container-playlist').css('visibility','hidden');
			var a = $('#countdown-display').text() ? $('#m-countdown').text(a) : $('#m-countdown').text('00:00');
		}
        else {
			setTimeout(function() {
				customScroll('playlist', parseInt(MPD.json['song']), 0);
			}, DEFAULT_TIMEOUT);
		}
	}
});

// Click anywhere off a context menu
// Closes the menu and de-highlights the items
$('#context-backdrop').click(function(e){
	$('#context-backdrop').hide();
	$('.context-menu').removeClass('open');
	$('.context-menu-lib').removeClass('open');
    if (currentView == 'folder' || currentView == 'radio') {
        //console.log(UI.dbPos[UI.dbPos[10]], UI.dbEntry[3]);
        $('#' + UI.dbEntry[3]).removeClass('active');
    }
    else if (currentView == 'tag') {
        $('#lib-song-' + (UI.dbEntry[0] + 1).toString()).removeClass('active');
        $('img.lib-coverart').removeClass('active');
    }
    else if (currentView == 'album') {
        $('#albumcovers .lib-entry').eq(UI.libPos[1]).removeClass('active');
    }
});

$('#appearance-modal .h5').click(function(e) {
	$(this).parent().children('div.control-group').slideToggle(100);
	$(this).parent().children('.dtclose, .dtopen').toggle();
});

// Synchronize times to/from playbar so we don't have to keep countdown timers running which = ugly idle perf
function syncTimers() {
    var a = $('#countdown-display').text();
    if (a != GLOBAL.lastTimeCount) { // Only update if time has changed
        if (UI.mobile) { // Only change when needed to save work
            $('#m-countdown').text(a);
            $('#playbar-mcount').text(a);
        }
        else if (coverView || currentView.indexOf('playback') == -1) {
            $('#playbar-countdown').text(a);

            var c = a.split(':'); // (h):m:s - current
            var d = $('#playbar-total').text().split(':'); //(h):m:s - total

            switch (c.length) {
                case 1:     // ss
                    var e = parseInt(c[0]);
                    break;
                case 2:     // mm:ss
                    var e = parseInt(c[0] * 60) + parseInt(c[1]);
                    break;
                case 3:     // hh:mm:ss
                    var e = parseInt(c[0] * 3600) + parseInt(c[1] * 60) + parseInt(c[2]);
                    break;
            } // e = current position in seconds

            switch (d.length) {
                case 1:     // ss
                    var f = parseInt(d[0]);
                    break;
                case 2:     // mm:ss
                    var f = parseInt(d[0] * 60) + parseInt(d[1]);
                    break;
                case 3:     // hh:mm:ss
                    var f = parseInt(d[0] * 3600) + parseInt(d[1] * 60) + parseInt(d[2]);
                    break;
            } // f = total track length in seconds

           //console.log('current '+ e);
           //console.log('total ' + f);

            SESSION.json['timecountup'] == '1' ? g = (e / f) * 100 : g = 100 - ((e / f) * 100); // Percent of elapsed song
            $('#playbar-timetrack').val(g * 10); // min = 0, max = 1000
            g < 50 ? g = 'calc(' + g + '% + 2px)' : g = 'calc(' + g + '% - 2px)'; // Adjust for thumb

            $('#playbar-timeline .timeline-progress').css('width', g.toString());
        }
        else {
            UI.knobPainted = false;
        }

        GLOBAL.lastTimeCount = a;
    }
}

// Active/inactive for buttons and panels
function makeActive (vswitch, panel, view) {
    //const startTime = performance.now();
	if (UI.mobile) {
		$('#playback-controls').css('display', '');
		$('#content').css('height', '100vh');
	}

	$('#content .tab-pane, .viewswitch button').removeClass('active');
	$(vswitch + ',' + panel).addClass('active');
    $.post('command/moode.php?cmd=updcfgsystem', {'current_view': view});

	currentView = view;
	setColors();
	setLibMenuHeader();
	$('#viewswitch span.pane').hide();
	switch (view) {
		case 'radio':
			lazyLode('radio');
			$('#viewswitch-search, #viewswitch .view-all, #viewswitch .view-recents').hide();
			$('#viewswitch .album-view-btn').removeClass('menu-separator');
			$('.radio-view-btn .pane').show();
			break;
		case 'folder':
			$('#viewswitch-search, #viewswitch .view-all, #viewswitch .view-recents').hide();
			$('#viewswitch .album-view-btn').removeClass('menu-separator');
			$('.folder-view-btn .pane').show();
			break;
		case 'album':
			lazyLode('album');
			$('#viewswitch-search, #viewswitch .view-all, #viewswitch .view-recents').show();
			$('#viewswitch .album-view-btn').addClass('menu-separator');
			$('.album-view-btn .pane').show();
			$('#library-panel').addClass('covers').removeClass('tag');
            if ($('#tracklist-toggle').text().trim() == 'Hide tracks') {
                $('#bottom-row').css('display', 'flex')
                $('#lib-albumcover').css('height', 'calc(47% - 2em)'); // Was 1.75em
                $('#index-albumcovers').hide();
            }
            else {
                $('#bottom-row').css('display', '');
                $('#lib-albumcover').css('height', '100%');
                $('#index-albumcovers').show();
            }
			break;
		case 'tag':
			lazyLode('tag');
			$('#viewswitch-search, #viewswitch .view-all, #viewswitch .view-recents').show();
			$('#viewswitch .album-view-btn').addClass('menu-separator');
			$('.tag-view-btn .pane').show();
			$('#library-panel').addClass('tag').removeClass('covers');
			SESSION.json['library_show_genres'] == 'Yes' ? $('#top-columns').removeClass('nogenre') : $('#top-columns').addClass('nogenre');
			break;
	}
	//const duration = performance.now() - startTime;
    //console.log(duration + 'ms');
}

// Set the text in the library menu header
function setLibMenuHeader () {
    var headerText = UI.mobile ? '' : 'Browse by ';

	if (currentView == 'radio') {
		headerText += 'Radio Stations';
		if (GLOBAL.searchRadio) {
			headerText = GLOBAL.searchRadio;
		}
	}
	else if (currentView == 'folder') {
		headerText += 'Folders';
		if (GLOBAL.searchFolder) {
			headerText = GLOBAL.searchFolder;
		}
	}
	else if (currentView == 'album' || currentView == 'tag') {
		if (GLOBAL.searchLib && GLOBAL.musicScope == 'all') {
			headerText = GLOBAL.searchLib;
		}
        else {
			currentView == 'album' ? headerText += SESSION.json['library_albumview_sort'] : headerText += SESSION.json['library_tagview_sort'];
			$('.view-recents span').hide();
			$('.view-all span').hide();

			if (GLOBAL.musicScope == 'recent') {
				$('.view-recents span').show();
		        LIB.recentlyAddedClicked = true;
				headerText = 'Recently Added (' + getParamOrValue('param', SESSION.json['library_recently_added']) + ')';
			}
            else {
				$('.view-all span').show();
		        LIB.recentlyAddedClicked = false;
			}

            if (LIB.filters.genres.length) {
				headerText = 'Browse ' + LIB.filters.genres[0];
			}
			if (LIB.filters.artists.length) {
				headerText = 'Albums by ' + LIB.filters.artists[0];
			}
		}
	}

	$('#menu-header').text(headerText);
}

function lazyLode(view) {
    // If browser does not support native lazy load then fall back to JQuery lazy load
    if (!GLOBAL.nativeLazyLoad) {
 		var container, selector;

 		switch (view) {
 			case 'radio':
 				selector = 'img.lazy-radioview';
 				container = '#radiocovers';
 				break;
 			case 'tag':
 				if (SESSION.json['library_tagview_covers'] == 'Yes') {
     				selector = 'img.lazy-tagview';
     				container = '#lib-album';
                }
 				break;
 			case 'album':
 				selector = 'img.lazy-albumview';
 				container = '#lib-albumcover';
				 break;
		 	case 'playlist':
				selector = 'img.lazy-playlistview';
				container = '#playlist';
				break;
            case 'cv-playlist':
				selector = 'img.lazy-playlistview';
				container = '#cv-playlist';
				break;
 		}

        if (selector && container) {
 			setTimeout(function(){
 				$(selector).lazyload({
 					container: $(container)
 				});
 			}, DEFAULT_TIMEOUT);
        }
 	}
}

function setFontSize() {
    var sizeFactor = getParamOrValue('value',SESSION.json['font_size']);

    if (UI.mobile) {
        sizeFactor += .3;
    }

    document.body.style.setProperty('--pbfont', 'calc(' + sizeFactor + 'rem + 1vmin)');
}

function volMuteSwitch() {
    if (SESSION.json['volmute'] == '0') {
		SESSION.json['volmute'] = '1'
		var newVol = 0;
		var volEvent = 'mute';
    }
	else {
		SESSION.json['volmute'] = '0'
		var newVol = SESSION.json['volknob'];
		var volEvent = 'unmute';
    }

    $.post('command/moode.php?cmd=updcfgsystem', {'volmute': SESSION.json['volmute']}, function() {
        setVolume(newVol, volEvent);
    });
}

function submitLibraryUpdate (path) {
    if (GLOBAL.libLoading == false) {
        GLOBAL.libLoading = true;
        GLOBAL.libRendered = false;
        mpdDbCmd('update_library', path);
        notify('update_library', path);
    }
    else {
        notify('library_updating');
    }
}

function getThumbHW() {
	var cols = SESSION.json['library_thumbnail_columns'].slice(0,1);
	if (UI.mobile) cols = cols - 4;
	var divM = Math.round(2 * convertRem(1.5)); // 1.5rem l/r margin for div
	var columnW = parseInt(($(window).width() - (2 * GLOBAL.sbw) - divM) / cols);
	UI.thumbHW = columnW - (divM / 2);
	$("body").get(0).style.setProperty("--thumbimgsize", UI.thumbHW + 'px');
	$("body").get(0).style.setProperty("--thumbcols", columnW + 'px');	
}

function convertRem(value) {
  return value * getRootElementFontSize();
}

function getRootElementFontSize() {
  // Returns a number
  return parseFloat(
    // of the computed font-size, so in px
    getComputedStyle(
      // for the root <html> element
      document.documentElement
    ).fontSize
  );
}

