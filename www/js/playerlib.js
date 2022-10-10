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

// Features availability bitmask
const FEAT_KERNEL       = 1;        // y Kernel architecture option on System Config
const FEAT_AIRPLAY      = 2;        // y Airplay renderer
const FEAT_MINIDLNA     = 4;        // y DLNA server
const FEAT_RECORDER     = 8;        //   Stream recorder
const FEAT_SQUEEZELITE  = 16;       // y Squeezelite renderer
const FEAT_UPMPDCLI     = 32;       // y UPnP client for MPD
const FEAT_SQSHCHK      = 64;       //   Require squashfs for software update
const FEAT_ROONBRIDGE	= 128;		// y RoonBridge renderer
const FEAT_LOCALUI      = 256;      // y Local display
const FEAT_INPSOURCE    = 512;      // y Input source select
const FEAT_UPNPSYNC     = 1024;     //   UPnP volume sync
const FEAT_SPOTIFY      = 2048;     // y Spotify Connect renderer
const FEAT_GPIO         = 4096;     // y GPIO button handler
const FEAT_RESERVED     = 8192;     // y Reserved for future use
const FEAT_BLUETOOTH    = 16384;    // y Bluetooth renderer
const FEAT_DEVTWEAKS	= 32768;	//   Developer tweaks
const FEAT_MULTIROOM	= 65536;	// y Multiroom audio
//						-------
//						  97207

// For setTimout() in milliseconds
const DEFAULT_TIMEOUT   = 250;
const CLRPLAY_TIMEOUT   = 500;
const LAZYLOAD_TIMEOUT  = 500;
const SEARCH_TIMEOUT    = 750;
const RALBUM_TIMEOUT    = 500;
const ENGINE_TIMEOUT    = 3000;

// Album and Radio HD parameters
const ALBUM_HD_BADGE_TEXT           = 'HD';
const ALBUM_BIT_DEPTH_THRESHOLD     = 16;
const ALBUM_SAMPLE_RATE_THRESHOLD   = 44100;
const RADIO_HD_BADGE_TEXT           = 'HiRes';
const RADIO_BITRATE_THRESHOLD       = 128;

// For legacy Radio Manager station export
const STATION_EXPORT_DIR = '/'; // var/www

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
    dbPos: [0,0,0,0,0,0,0,0,0,0,0],
    // - Used in Folder view
    dbEntry: ['', '', '', '', '', ''],
    // [0]: Item number or name used in various routines
	// [1]: Used in bootstrap.contextmenu.js
    // [2]: Used in bootstrap.contextmenu.js
	// [3]: UI row num of song item so highlight can be removed after context menu action
	// [4]: Num playlist items for use by delete/move item modals
    // [5]: Playname for clock radio
	dbCmd: '',
	// Either 'lsinfo' or 'get_pl_items_fv'
	libPos: [-1,-1,-1],
	// [0]: albums list pos
	// [1]: album cover pos
	// [2]: artist list pos
	// special values for [0] and [1]: -1 = full lib displayed, -2 = lib headers clicked, -3 = search performed
	radioPos: -1,
    playlistPos: -1,
	libAlbum: '',
	mobile: false,
	npIcon: 'url("../images/4band-npicon/audiod.svg")',
	npIconPaused: 'url("../images/4band-npicon/audiod-flat.svg")',
	thumbHW: '0px'
};

// MPD state and metadata
var MPD = {
	json: 0
};

// Session vars (cfg_system table)
var SESSION = {
	json: 0
};
// Radio stations (cfg_radio table)
var RADIO = {
	json: 0
};
// Themes (cfg_theme table)
var THEME = {
	json: 0
};
// Networks (cfg_network table)
var NETWORK = {
	json: 0
};

// TODO: Eventually migrate all global vars here
var GLOBAL = {
	musicScope: 'all', // Or not defined if saved, but prob don't bother saving...
	searchRadio: '',
	searchFolder: '',
    searchPlaylist: '',
    scriptSection: 'panels',
	regExIgnoreArticles: '',
    libRendered: false,
    libLoading: false,
    playbarPlaylistTimer: '',
    pqActionClicked: false,
    mpdMaxVolume: 0,
    lastTimeCount: 0,
    editStationId: '',
    nativeLazyLoad: false,
    playqueueChanged: false,
	initTime: 0,
    oneArgFilters: ['full_lib', 'hdonly', 'lossless', 'lossy'],
    twoArgFilters: ['album', 'albumartist', 'any', 'artist', 'composer', 'conductor', 'encoded', 'file', 'folder', 'format', 'genre', 'label', 'performer', 'title', 'work', 'year'],
    allFilters: [],
    sbw: 0,
    backupCreate: false,
    busySpinnerSVG: "<svg xmlns='http://www.w3.org/2000/svg' width='42' height='42' viewBox='0 0 42 42' stroke='#fff'><g fill='none' fill-rule='evenodd'><g transform='translate(3 3)' stroke-width='4'><circle stroke-opacity='.35' cx='18' cy='18' r='18'/><path d='M36 18c0-9.94-8.06-18-18-18'><animateTransform attributeName='transform' type='rotate' from='0 18 18' to='360 18 18' dur='1s' repeatCount='indefinite'/></path></g></g></svg>",
    thisClientIP: '',
    chromium: false,
    ssClockIntervalID: ''
};
GLOBAL.allFilters = GLOBAL.oneArgFilters.concat(GLOBAL.twoArgFilters);

// Live timeline
var timeSliderMove = false;

// Adaptive theme
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

// Various flags and things
var dbFilterResults = [];
var searchTimer = '';
var showSearchResetPq = false;
var showSearchResetRa = false;
var showSearchResetPl = false;
var showSearchResetPh = false;
var eqGainUpdInterval = '';
var toolbarTimer = '';
var toggleSongId = 'blank';
var currentView = 'playback';
var alphabitsFilter;
var lastYIQ = ''; // Last yiq value from setColors
var coverView = false; // Coverview shown/hidden to save on more expensive conditional in interval timer

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
		url: 'command/playback.php?cmd=' + cmd,
		async: async,
		cache: false,
		data: data,
		success: function(data) {
            // Omit the try/catch to enable improved volume knob behavior
			obj = JSON.parse(data);
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
			debugLog('engineMpd(): success branch: data=(' + data + ')');

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
				// MPD restarted by watchdog, manually via cli, etc
				if (MPD.json['idle_timeout_event'] === '') {
					// NOP
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
					if (MPD.json['date']) MPD.json['date'] = MPD.json['date'].slice(0,4); // should fix in php but...
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
				setTimeout(function(data) {
					// Client connects before mpd started by worker, various other issues
					debugLog('engineMpdLite(): success branch: error=(' + MPD.json['error'] + '), module=(' + MPD.json['module'] + ')');

                    // TEST: Show reconnect overlay when on configs
                    if (typeof(data) !== 'undefined') {
                        if (data['statusText'] == 'error' && data['readyState'] == 0) {
        			        renderReconnect();
        				}
                    }
    				MPD.json['state'] = 'reconnect';

					engineMpdLite();
				}, ENGINE_TIMEOUT);
			}
		},
        // Network connection interrupted or client network stack timeout
		error: function(data) {
			debugLog('engineMpdLite(): error branch: data=(' + JSON.stringify(data) + ')');
            //console.log('engineMpdLite: error branch: data=(' + JSON.stringify(data) + ')');

			setTimeout(function() {
                if (typeof(data) !== 'undefined') {
                    if (data['statusText'] == 'error' && data['readyState'] == 0) {
    			        renderReconnect();
    				}
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
                    var receiversBtn = SESSION.json['multiroom_tx'] == 'On' ? '<br><div class="context-menu"><a class="btn configure-renderer" href="#notarget" data-cmd="multiroom_rx_modal_limited">receivers</a></div>' : '';
    				inpSrcIndicator(cmd[0], 'Airplay Active' + '<br><button class="btn disconnect-renderer" data-job="airplaysvc">disconnect</button>' + receiversBtn);
                    break;
                case 'spotactive1':
                case 'spotactive0':
                    var receiversBtn = SESSION.json['multiroom_tx'] == 'On' ? '<br><div class="context-menu"><a class="btn configure-renderer" href="#notarget" data-cmd="multiroom_rx_modal_limited">receivers</a></div>' : '';
    				inpSrcIndicator(cmd[0], 'Spotify Active' + '<br><button class="btn disconnect-renderer" data-job="spotifysvc">disconnect</button>' + receiversBtn);
                    break;
                case 'slactive1':
                case 'slactive0':
    				inpSrcIndicator(cmd[0], 'Squeezelite Active' + '<br><button class="btn turnoff-renderer" data-job="slsvc">turn off</button>');
                    break;
                case 'rbactive1':
                case 'rbactive0':
    				inpSrcIndicator(cmd[0], 'RoonBridge Active' + '<br><button class="btn disconnect-renderer" data-job="rbrestart">disconnect</button>');
                    break;
                case 'rxactive1':
                case 'rxactive0':
                    inpSrcIndicator(cmd[0],
                        //'Multiroom Receiver Active: ' +
                        //'<button class="btn volume-popup-btn" data-toggle="modal"><i class="fal fa-volume-up"></i></button><span id="multiroom-receiver-volume"></span><br>' +
                        'Multiroom Receiver Active<br>' +
                        '<button class="btn turnoff-renderer" data-job="multiroom_rx">turn off</button><br>' +
                        '<a class="btn configure-renderer" href="trx-config.php">configure</a>'
                    );
                    break;
                case 'scnactive1':
                case 'scnactive0':
    				screenSaver(cmd[0]);
                    break;
                case 'libupd_done':
    				$('.busy-spinner').hide();
                    loadLibrary();
                    break;
                case 'set_cover_image1':
                    $('.busy-spinner').show();
                    break;
                case 'set_cover_image0':
    				$('.busy-spinner').hide();
                    break;
                case 'refresh_screen':
                    setTimeout(function() {
                        location.reload(true);
                    }, DEFAULT_TIMEOUT);
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
		$('#inpsrc-preamp-volume').text(SESSION.json['mpdmixer'] == 'none' ? '0dB' : SESSION.json['volknob']);
        $('#multiroom-receiver-volume').text(SESSION.json['volmute'] == '1' ? 'mute' :
            (SESSION.json['mpdmixer'] == 'none' ? '0dB' : SESSION.json['volknob']));
	}
	else {
		$('#inpsrc-msg').html('');
		$('#inpsrc-indicator').css('display', '');
	}
}

// Show/hide CoverView screen saver
function screenSaver(cmd) {
	if ($('#inpsrc-indicator').css('display') == 'block' || UI.mobile) {
        // Don't show CoverView
		return;
	} else if (cmd.slice(-1) == '1') {
        // Show CoverView
        coverView = true; // NOTE: This is set to false in the screen saver reset click() handler
		$('#ss-coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'alt="Cover art not found"' + '>');
        $('body').addClass('cv')
        if (SESSION.json['show_cvpb'] == 'Yes') {
            $('body').addClass('cvpb');
        }
        if (SESSION.json['scnsaver_layout'] == 'Wide') {
            $('body').addClass('cvwide');
            if (SESSION.json['scnsaver_xmeta'] == 'Yes') {
                $('body').addClass('cvwide-xmeta');
            }
        }
        // TEST: Fixes issue where some elements briefly remain on-screen when entering or returning from CoverView
        $('#lib-coverart-img').hide();

        if (SESSION.json['scnsaver_mode'] == 'Clock') {
            $('#ss-coverart').css('display', 'none');
            $('#ss-clock').css('display', 'block');
            showSSClock();
            GLOBAL.ssClockIntervalID = setInterval(showSSClock, 1000);
        }
	} else if (cmd.slice(-1) == '0') {
        // Hide CoverView
        $('#screen-saver').click();
    }
}

// Screen saver clock
function showSSClock() {
    var date = new Date();
    var h = date.getHours(); // 0 - 23
    var m = date.getMinutes(); // 0 - 59
    var s = date.getSeconds(); // 0 - 59

    var ampm = "AM";
    if (h == 0) {
        h = 12;
    } else if (h > 12) {
        h = h - 12;
        var ampm = "PM";
    }

    h = (h < 10) ? "0" + h : h;
    m = (m < 10) ? "0" + m : m;
    s = (s < 10) ? "0" + s : s;

    var time = h + ':' + m + ':' + s + ' ' + ampm;
    $('#ss-clock').text(time);
    //console.log(time);
}

// Reconnect/reboot/restart
function renderReconnect() {
	//console.log('renderReconnect(): UI.restart=(' + UI.restart + ')');
	if (UI.restart == 'restart') {
		$('#restart').show();
	}
	else if (UI.restart == 'shutdown') {
		$('#shutdown').show();
	}
    else if (GLOBAL.backupCreate) {
        // Don't display the screen when a backup is being created/downloaded
    }
	else {
		$('#reconnect').show();
	}

	$('#countdown-display').countdown('pause');

	window.clearInterval(UI.knob);
	UI.hideReconnect = true;
    GLOBAL.backupCreate = false;
}

function hideReconnect() {
	//console.log('hideReconnect(): (' + UI.hideReconnect + ')');
	$('#reconnect, #restart, #shutdown').hide();
	UI.hideReconnect = false;
}

// Disable volume knob for mpdmixer == none (0dB)
function disableVolKnob() {
	SESSION.json['volmute'] == '1';
    $('#volumedn, #volumeup, #volumedn-2, #volumeup-2').prop('disabled', true);
    $('#volumeup, #volumedn, #volumedn-2, #volumeup-2, .volume-display').css('opacity', '.3');
	$('.volume-display div, #inpsrc-preamp-volume, #multiroom-receiver-volume, #playbar-volume-level').text('0dB');
	$('.volume-display').css('cursor', 'unset');
    $('.volume-popup-btn').hide();
	if (UI.mobile) {
		$('#mvol-progress').css('width', '100%');
		$('.repeat').show();
	}
}

// When last item in Queue finishes just update a few things, called from engineCmd()
function resetPlayCtls() {
	console.log('resetPlayCtls():');
	$('#m-total, #playbar-total, #playbar-mtotal').text(formatKnobTotal('0'));
	$('.play i').removeClass('fas fa-pause').addClass('fas fa-play');
	$('#total').html(formatKnobTotal('0'));
	$('.playqueue li.active ').removeClass('active');

	updKnobAndTimeTrack();
    updKnobStartFrom(0, MPD.json['state']);

	$('#countdown-display, #m-countdown, #playbar-countdown, #playbar-mcount').text('00:00');
}

function renderUIVol() {
	//console.log('renderUIVol()');
	// Load session vars (required for multi-client)
    $.getJSON('command/cfg-table.php?cmd=get_cfg_system', function(data) {
    	if (data === false) {
            console.log('renderUIVol(): No data returned from get_cfg_system');
    	}
        else {
            SESSION.json = data;
        }

    	// Fixed volume (0dB output)
    	if (SESSION.json['mpdmixer'] == 'none') {
    		disableVolKnob();
    	}
    	// Software or hardware volume
    	else {
    		// Sync moOde's displayed volume to that on a UPnP control point app
            // NOTE: This hack is necessary because upmpdcli set's MPD volume directly and does not use vol.sh
    		if (SESSION.json['feat_bitmask'] & FEAT_UPNPSYNC) {
    			// No renderers active
    			if (SESSION.json['btactive'] == '0' && SESSION.json['aplactive'] == '0' && SESSION.json['spotactive'] == '0'
                    && SESSION.json['slsvc'] == '0' && SESSION.json['rbsvc'] == '0') {
    				if ((SESSION.json['volknob'] != MPD.json['volume']) && SESSION.json['volmute'] == '0') {
    					SESSION.json['volknob'] = MPD.json['volume']
                        $.post('command/cfg-table.php?cmd=upd_cfg_system', {'volknob': SESSION.json['volknob']});
    				}
    			}
    		}

    		// Update volume knobs
    		$('#volume').val(SESSION.json['volknob']).trigger('change');
    		$('.volume-display div, #inpsrc-preamp-volume, #multiroom-receiver-volume, #playbar-volume-level').text(SESSION.json['volknob']);
            $('.volume-display-db').text(SESSION.json['volume_db_display'] == '1' ? MPD.json['mapped_db_vol'] : '');
    		$('#volume-2').val(SESSION.json['volknob']).trigger('change');
    		$('#mvol-progress').css('width', SESSION.json['volknob'] + '%');

    	   	// Update mute state
    		if (SESSION.json['volmute'] == '1') {
    			$('.volume-display div, #inpsrc-preamp-volume, #multiroom-receiver-volume').text('mute');
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

    // Load session vars (required for multi-client)
    $.getJSON('command/cfg-table.php?cmd=get_cfg_system', function(data) {
        if (data === false) {
            console.log('renderUI(): No data returned from get_cfg_system');
    	} else {
            SESSION.json = data;
        }

    	if (SESSION.json['mpdmixer'] == 'none') {
            // Fixed volume (0dB output)
    		disableVolKnob();
    	} else {
            // Software or hardware volume
            if (UI.mobile) {
                $('.volume-popup-btn').show();
            }

    		// Update volume knobs
    		$('#volume').val(SESSION.json['volknob']).trigger('change');
    		$('.volume-display div, #inpsrc-preamp-volume, #multiroom-receiver-volume, #playbar-volume-level').text(SESSION.json['volknob']);
            $('.volume-display-db').text(SESSION.json['volume_db_display'] == '1' ? MPD.json['mapped_db_vol'] : '');
    		$('#volume-2').val(SESSION.json['volknob']).trigger('change');
    		$('#mvol-progress').css('width', SESSION.json['volknob'] + '%');

    	   	// Update mute state
    		if (SESSION.json['volmute'] == '1') {
    			$('.volume-display div, #inpsrc-preamp-volume, #multiroom-receiver-volume').text('mute');
                $('#playbar-volume-level').text('x');
    		} else {
    			$('.volume-display div, #playbar-volume-level').text(SESSION.json['volknob']);
    		}
    	}

    	// Playback controls, Queue item highlight
        if (MPD.json['state'] == 'play') {
    		$('.play i').removeClass('fas fa-play').addClass('fas fa-pause');
			//document.body.style.setProperty('--npicon', npIcon);
    		$('.playqueue li.active, .cv-playqueue li.active').removeClass('active');
            $('.playqueue li.paused, .cv-playqueue li.paused').removeClass('paused');
            $('.playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('active');
            $('.cv-playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('active');
            setNpIcon();
        } else if (MPD.json['state'] == 'pause' || MPD.json['state'] == 'stop') {
    		$('.play i').removeClass('fas fa-pause').addClass('fas fa-play');
			//document.body.style.setProperty('--npicon', npIconPaused);
            if (typeof(MPD.json['song']) != 'undefined') {
                $('.playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('paused');
                $('.cv-playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('paused');
            }
            $('#songsList .lib-entry-song .songtrack').removeClass('lib-track-highlight');
        }
    	$('#total').html(formatKnobTotal(MPD.json['time'] ? MPD.json['time'] : 0));
    	$('#m-total, #playbar-total').html(formatKnobTotal(MPD.json['time'] ? MPD.json['time'] : 0));
    	$('#playbar-mtotal').html('&nbsp;/&nbsp;' + formatKnobTotal(MPD.json['time']));
        $('#playbar-total').text().length > 5 ? $('#playbar-countdown, #m-countdown, #playbar-total, #m-total').addClass('long-time') :
            $('#playbar-countdown, #m-countdown, #playbar-total, #m-total').removeClass('long-time');

    	//console.log('CUR: ' + UI.currentHash);
    	//console.log('NEW: ' + MPD.json['cover_art_hash']);
    	// Compare new to current to prevent unnecessary image reloads
    	if (MPD.json['file'] !== UI.currentFile && MPD.json['cover_art_hash'] !== UI.currentHash) {
    		//console.log(MPD.json['coverurl']);
            // Standard cover for Playback
     		$('#coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'data-adaptive-background="1" alt="Cover art not found"' + '>');
            // Thumbnail cover for Playbar
            if (MPD.json['file'] && MPD.json['coverurl'].indexOf('wimpmusic') == -1 && MPD.json['coverurl']) {
                var image_url = MPD.json['artist'] == 'Radio station' ?
                    MPD.json['coverurl'].replace('imagesw/radio-logos', 'imagesw/radio-logos/thumbs') :
                    //'/imagesw/thmcache/' + encodeURIComponent($.md5(MPD.json['file'].substring(0,MPD.json['file'].lastIndexOf('/')))) + '.jpg'
                    '/imagesw/thmcache/' + encodeURIComponent(MPD.json['thumb_hash']) + '.jpg'
                $('#playbar-cover').html('<img src="' + image_url + '">');
            } else {
	     		$('#coverart-url').html('<img class="coverart" ' + 'src="' + UI.defCover + '" data-adaptive-background="1" alt="Cover art not found"' + '>');
                $('#playbar-cover').html('<img src="images/default-cover-v6.svg">');
            }
    		// Cover backdrop or bgimage
    		if (SESSION.json['cover_backdrop'] == 'Yes') {
                var backDropHTML = MPD.json['coverurl'].indexOf('default-cover-v6') === -1 ? '<img class="ss-backdrop" ' + 'src="' + MPD.json['coverurl'] + '">' : '';
    			$('#cover-backdrop').html(backDropHTML);
    			$('#cover-backdrop').css('filter', 'blur(' + SESSION.json['cover_blur'] + ')');
    			$('#cover-backdrop').css('transform', 'scale(' + SESSION.json['cover_scale'] + ')');
    		} else if (SESSION.json['bgimage'] != '') {
    			$('#cover-backdrop').html('<img class="ss-backdrop" ' + 'src="' + SESSION.json['bgimage'] + '">');
    			$('#cover-backdrop').css('filter', 'blur(0px)');
    			$('#cover-backdrop').css('transform', 'scale(1.0)');
    		}
    		// Screen saver
    		$('#ss-backdrop').html('<img class="ss-backdrop" ' + 'src="' + MPD.json['coverurl'] + '">');
    		if (coverView) {
    			$('#ss-coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'alt="Cover art not found"' + '>');
    		}

    		// Adaptive UI theme engine
    		if (MPD.json['coverurl'].indexOf('default-cover-v6') === -1) {
    			$.adaptiveBackground.run();
    		} else {
    			setColors();
    		}
    	}

    	// Extra metadata displayed under the cover
    	if (MPD.json['state'] === 'stop') {
    		$('#extra-tags-display, #ss-extra-metadata').html('Not playing');
    	} else if (SESSION.json['extra_tags'].toLowerCase() == 'none' || SESSION.json['extra_tags'] == '') {
            $('#extra-tags-display, #ss-extra-metadata').html('');
        } else if (MPD.json['artist'] == 'Radio station') {
    		$('#extra-tags-display, #ss-extra-metadata').html((MPD.json['bitrate'] ? MPD.json['bitrate'] : 'Variable bitrate'));
    	} else {
            var extraTagsDisplay = '';
            extraTagsDisplay = formatExtraTagsString();
            extraTagsDisplay ? $('#extra-tags-display').html(extraTagsDisplay) :
                $('#extra-tags-display').html(MPD.json['audio_sample_depth'] + '/' + MPD.json['audio_sample_rate']);
            $('#ss-extra-metadata').html(MPD.json['encoded']);
    	}

        // HD badge text
        var hdBadgeText = MPD.json['artist'] == 'Radio station' ? RADIO_HD_BADGE_TEXT : ALBUM_HD_BADGE_TEXT;
        $('.playback-hd-badge, #playbar-hd-badge, #ss-hd-badge').text(hdBadgeText);

        // Multiple artists indicator
        var moreArtistsEllipsis = parseInt(MPD.json['artist_count']) > 1 ? '...' : '';

        // Default metadata
        if (MPD.json['artist'] == 'Radio station') {
            // For radio stations
            // - #currentalbum = ''
            // - #currentsong = MPD.json['title']
            // - #currentartist = MPD.json['album']
            // Playback
            $('#currentalbum-div').hide();
            $('#currentsong').html(genSearchUrl(MPD.json['artist'], MPD.json['title'], MPD.json['album']));
            $('#currentartist').html(MPD.json['album']);
            // Playbar
            $('#playbar-currentalbum, #ss-currentalbum').html(MPD.json['file'].indexOf('somafm') != -1 ?
                RADIO.json[MPD.json['file']]['name'] : MPD.json['album']);
            $('#playbar-currentsong, #ss-currentsong').html(MPD.json['title']);
        } else {
            // For albums
            // - #currentalbum = MPD.json['album']
            // - #currentsong = MPD.json['title']
            // - #currentartist = MPD.json['artist']
            // Playback
            $('#currentalbum-div').show();
            $('#currentalbum').html(MPD.json['album']);
    		$('#currentsong').html(genSearchUrl(MPD.json['artist'] == 'Unknown artist' ? MPD.json['albumartist'] : MPD.json['artist'], MPD.json['title'], MPD.json['album']));
            $('#currentartist').html((MPD.json['artist'] == 'Unknown artist' ? MPD.json['albumartist'] : MPD.json['artist']) + moreArtistsEllipsis);
            // Playbar
			var textArtistTitle = (MPD.json['artist'] == 'Unknown artist' ? MPD.json['albumartist'] : MPD.json['artist']);
			if ('' != textArtistTitle) {
				textArtistTitle += moreArtistsEllipsis + ' - ' + MPD.json['title'];
			}
			$('#playbar-currentsong, #ss-currentsong').html(textArtistTitle);
            $('#playbar-currentalbum, #ss-currentalbum').html(MPD.json['album']);
        }

        // Show/hide HD badge
        if (MPD.json['hidef'] == 'yes' && SESSION.json['library_encoded_at'] && SESSION.json['library_encoded_at'] != '9') {
            if (MPD.json['artist'] == 'Radio station') {
                $('#currentartist-div span.playback-hd-badge').show();
            } else {
                $('#currentartist-div span.playback-hd-badge').hide();
                $('#currentalbum-div span.playback-hd-badge').show();
            }

            $('#playbar-hd-badge, #ss-hd-badge').show();
        } else {
            $('.playback-hd-badge, #playbar-hd-badge, #ss-hd-badge').hide();
        }

        // Store songid for last track (toggle song)
    	if (UI.currentSongId != MPD.json['songid']) {
    		toggleSongId = UI.currentSongId == 'blank' ? SESSION.json['toggle_songid'] : UI.currentSongId;
            $.post('command/cfg-table.php?cmd=upd_cfg_system', {'toggle_songid': toggleSongId});
    	}

    	// Set current = new for next cycle
    	UI.currentFile = MPD.json['file'];
    	UI.currentHash = MPD.json['cover_art_hash'];
    	UI.currentSongId = MPD.json['songid'];

    	// Toggle buttons
    	if (MPD.json['consume'] === '1') {
    		$('.consume').addClass('btn-primary');
    		$('#menu-check-consume').show();
    	} else {
    		$('.consume').removeClass('btn-primary');
    		$('#menu-check-consume').hide();
    	}
        if (MPD.json['repeat'] === '1') {
    		$('.repeat').addClass('btn-primary')
    		$('#menu-check-repeat').show();
    	} else {
    		$('.repeat').removeClass('btn-primary');
    		$('#menu-check-repeat').hide();
    	}
        if (MPD.json['single'] === '1') {
    		$('.single').addClass('btn-primary')
    		$('#menu-check-single').show();
    	} else {
    		$('.single').removeClass('btn-primary');
    		$('#menu-check-single').hide();
    	}
    	if (SESSION.json['ashufflesvc'] === '1') {
    		if (SESSION.json['ashuffle'] ==='1') {
    			$('.random, .consume').addClass('btn-primary')
    			$('#songsList .lib-entry-song .songtrack').removeClass('lib-track-highlight');
    		} else {
    			$('.random').removeClass('btn-primary');
    		}
    	} else {
    	    MPD.json['random'] === '1' ? $('.random').addClass('btn-primary') : $('.random').removeClass('btn-primary');
    	}

    	// Time knob and timeline
    	// Count up or down, radio stations always have song time = 0
    	if (SESSION.json['timecountup'] === '1' || parseInt(MPD.json['time']) === 0) {
    		updKnobStartFrom(parseInt(MPD.json['elapsed']), MPD.json['state']);
    	} else {
    		updKnobStartFrom(parseInt(MPD.json['time'] - parseInt(MPD.json['elapsed'])), MPD.json['state']);
    	}

    	// Set flag if song file and knob < 100% painted
    	// NOTE radio station time will always be 0
    	if (parseInt(MPD.json['time']) !== 0) {
    		UI.knobPainted = false;
    	}
    	// Update knob if paint < 100% complete
    	if ((MPD.json['state'] === 'play' || MPD.json['state'] === 'pause') && UI.knobPainted === false) {
    		updKnobAndTimeTrack();
    	}
    	// Clear knob when stop
    	if (MPD.json['state'] === 'stop') {
    		updKnobAndTimeTrack();
    	}

        // Render the Queue
        //console.log('ID ' + MPD.json['playlist'], MPD.json['idle_timeout_event'], MPD.json['state']);
        if (typeof(MPD.json['idle_timeout_event']) == 'undefined' ||
            // Page load/reload, Queue changed (items added/removed)
            MPD.json['idle_timeout_event'] == 'changed: playlist' ||
            GLOBAL.playqueueChanged == true) {
            renderPlayqueue(MPD.json['state']);
        } else {
            updateActivePlayqueueItem();
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
    	if (SESSION.json['aplactive'] == '1') {
            var receiversBtn = SESSION.json['multiroom_tx'] == 'On' ? '<br><div class="context-menu"><a class="btn configure-renderer" href="#notarget" data-cmd="multiroom_rx_modal_limited">receivers</a></div>' : '';
    		inpSrcIndicator('aplactive1', 'Airplay Active' + '<br><button class="btn disconnect-renderer" data-job="airplaysvc">disconnect</button>' + receiversBtn);
    	}
    	// Spotify renderer
    	if (SESSION.json['spotactive'] == '1') {
            var receiversBtn = SESSION.json['multiroom_tx'] == 'On' ? '<br><div class="context-menu"><a class="btn configure-renderer" href="#notarget" data-cmd="multiroom_rx_modal_limited">receivers</a></div>' : '';
            inpSrcIndicator('spotactive1', 'Spotify Active' + '<br><button class="btn disconnect-renderer" data-job="spotifysvc">disconnect</button>' + receiversBtn);
    	}
    	// Squeezelite renderer
    	if (SESSION.json['slactive'] == '1') {
    		inpSrcIndicator('slactive1', 'Squeezelite Active' + '<br><button class="btn disconnect-renderer" data-job="slsvc">turn off</button>');
    	}
        // RoonBridge renderer
    	if (SESSION.json['rbactive'] == '1') {
    		inpSrcIndicator('rbactive1', 'RoonBridge Active' + '<br><button class="btn disconnect-renderer" data-job="rbrestart">disconnect</button>');
    	}
        // Multiroom receiver
    	if (SESSION.json['rxactive'] == '1') {
            inpSrcIndicator('rxactive1',
                //'Multiroom Receiver Active: ' +
                //'<button class="btn volume-popup-btn" data-toggle="modal"><i class="fal fa-volume-up"></i></button><span id="multiroom-receiver-volume"></span><br>' +
                'Multiroom Receiver Active<br>' +
                '<button class="btn turnoff-renderer" data-job="multiroom_rx">turn off</button><br>' +
                '<a class="btn configure-renderer" href="trx-config.php">configure</a>'
            )
    	}

    	// MPD database update
    	if (typeof(MPD.json['updating_db']) != 'undefined') {
    		$('.busy-spinner').show();
    	} else {
    		$('.busy-spinner').hide();
    	}
    });
}

// Generate search url
function genSearchUrl (artist, title, album) {
    // Search disabled by user
    if (SESSION.json['search_site'] == 'Disabled') {
        var returnStr = title;
    }
    // Title has no searchable info or mobile
    else if (MPD.json['coverurl'] === UI.defCover || UI.mobile) {
        var returnStr = MPD.json['title'];
    }
    // Station does not transmit title
    else if (title == 'Streaming source') {
        if (RADIO.json[MPD.json['file']]['home_page'] != '') {
            var returnStr =  '<a id="coverart-link" href=' + '"' + RADIO.json[MPD.json['file']]['home_page'] + '"' + ' target="_blank">'+ title + '</a>';
        }
        else {
            returnStr = title;
        }
    }
    // Title has info
    else {
        // Radio station
        if (typeof(artist) === 'undefined' || artist === 'Radio station') {
    		var searchStr = title.replace(/-/g, ' ');
    		searchStr = searchStr.replace(/&/g, ' ');
    		searchStr = searchStr.replace(/\s+/g, '+');
    	}
        // Song file
    	else {
    		var searchStr = artist + '+' + album;
    	}

        // Search engine
    	switch (SESSION.json['search_site']) {
    		case 'Bing':
    			var searchEngine = 'http://www.bing.com/search?q=';
    			break;
            case 'Discogs':
    			var searchEngine = 'http://www.discogs.com/search/?q=';
    			break;
    		case 'DuckDuckGo':
    			var searchEngine = 'http://www.duckduckgo.com/?q=';
    			break;
    		case 'Ecosia':
    			var searchEngine = 'http://www.ecosia.org/search?q=';
    			break;
            case 'Google':
    			var searchEngine = 'http://www.google.com/search?q=';
    			break;
    		case 'MusicBrainz':
    			var searchEngine = 'http://www.musicbrainz.org/taglookup?';
                // Override default search str
                if (typeof(artist) === 'undefined' || artist === 'Radio station') {
                    searchStr = 'tag-lookup.artist=' + title.split(' - ')[0].replace(/&/g, ' '); // Artist
                }
                else {
                    searchStr = 'tag-lookup.artist=' + artist + '&tag-lookup.release=' + album;
                }
    			break;
            case 'Startpage':
    			var searchEngine = 'http://www.startpage.com/do/search?q=';
    			break;
    		case 'Wikipedia':
                var searchEngine = 'http://www.wikipedia.org/wiki/';
                // Override default search str
                if (typeof(artist) === 'undefined' || artist === 'Radio station') {
                    searchStr = title.split(' - ')[0].replace(/&/g, ' '); // Artist
                }
                else {
                    searchStr = artist;
                }
    			break;
            case 'Yahoo':
    			var searchEngine = 'http://search.yahoo.com/search?p=';
    			break;
    	}

        var returnStr =  '<a id="coverart-link" href=' + '"' + searchEngine + searchStr + '"' + ' target="_blank">'+ title + '</a>';
    }

    return returnStr;
}

// Update active Queue item
function updateActivePlayqueueItem() {
	//console.log('updateActivePlayqueueItem()');
    $.getJSON('command/queue.php?cmd=get_playqueue', function(data) {
        if (data) {
            for (i = 0; i < data.length; i++) {
                // Current song
	            if (i == parseInt(MPD.json['song'])) {
                    // Radio station
    				if (typeof(data[i].Name) !== 'undefined' || (data[i].file.substr(0, 4) == 'http' && typeof(data[i].Artist) === 'undefined' && typeof(data[i].Comment) === 'undefined')) {
    	                // Line 1 title
    					// Csustom title for particular station
    	                if (typeof(data[i].Title) === 'undefined' || data[i].Title.trim() == '' || data[i].file == 'http://stream.radioactive.fm') {
    						$('#pq-' + (parseInt(MPD.json['song']) + 1).toString() + ' .pll1').html('Streaming source');
    					}
                        // Standard title
    					else {
                            $('#pq-' + (parseInt(MPD.json['song']) + 1).toString() + ' .pll1').html(data[i].Title);
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

    if ($('#playback-panel').hasClass('active')) {
        customScroll('playqueue', parseInt(MPD.json['song']));
        if ($('#cv-playqueue').css('display') == 'block') {
            customScroll('cv-playqueue', parseInt(MPD.json['song']));
        }
    }
}

// Render the Playlist
function renderPlayqueue(state) {
	//console.log('renderPlayqueue()');
    $.getJSON('command/queue.php?cmd=get_playqueue', function(data) {
        //console.log(data);
		var output = '';
        var playqueueLazy = GLOBAL.nativeLazyLoad === true ? '<img loading="lazy" src=' : '<img class="lazy-playqueue" data-original=';
        var paused = state != 'play' ? ' paused' : '';
        var npIcon = SESSION.json['show_npicon'] == 'Yes' ? '' : ' no-npicon';

        // Save for use in delete/move modals
        UI.dbEntry[4] = typeof(data.length) === 'undefined' ? 0 : data.length;
		var showPlayqueueThumb = SESSION.json['playlist_art'] == 'Yes' ? true : false;

		// Format playlist items
        if (data) {
            for (i = 0; i < data.length; i++) {
	            // Item highlight
	            if (i == parseInt(MPD.json['song'])) {
	                output += '<li id="pq-' + (i + 1) + '" class="active playqueue-entry' + paused + npIcon + '">';
	            }
				else {
	                output += '<li id="pq-' + (i + 1) + '" class="playqueue-entry">';
	            }

				if (typeof(data[i].Name) !== 'undefined' && data[i].file.substr(data[i].file.lastIndexOf('.') + 1).toLowerCase() == 'm4a') {
	                // Line 1 title
					output += '<span class="playqueue-action" data-toggle="context" data-target="#context-menu-playqueue-item">' + (typeof(data[i].Time) == 'undefined' ? '' : formatSongTime(data[i].Time)) + '<br><b>&hellip;</b></span>';
	                output += '<span class="pll1">';
                    output += data[i].Name + '</span>';
					// Line 2 artist, album
					output += '<span class="pll2">'; // for clock radio
					output += (typeof(data[i].Artist) === 'undefined') ? 'Unknown artist' : data[i].Artist;
					//output += ' - ';
					//output += (typeof(data[i].Album) === 'undefined') ?  'Unknown album' : data[i].Album;
				}
				// Radio station
				else if (typeof(data[i].Name) !== 'undefined' || (data[i].file.substr(0, 4) == 'http' && typeof(data[i].Artist) === 'undefined' && typeof(data[i].Comment) === 'undefined')) {
                    var logoThumb = typeof(RADIO.json[data[i].file]) === 'undefined' ? '"images/notfound.jpg"' : '"imagesw/radio-logos/thumbs/' +
                        encodeURIComponent(RADIO.json[data[i].file]['name']) + '_sm.jpg"';
					output += showPlayqueueThumb && (typeof(data[i].Comment) === 'undefined' || data[i].Comment !== 'client=upmpdcli;')  ?
                        '<span class="playqueue-thumb">' + playqueueLazy + logoThumb + '></span>' : '';
	                // Line 1 title
					// Custom name for particular station
	                if (typeof(data[i].Title) === 'undefined' || data[i].Title.trim() == '' || data[i].file == 'http://stream.radioactive.fm') {
						output += '<span class="playqueue-action" data-toggle="context" data-target="#context-menu-playqueue-item">' + (typeof(data[i].Time) == 'undefined' ? '' : formatSongTime(data[i].Time)) + '<br><b>&hellip;</b></span>';
						output += '<span class="pll1">Streaming source</span>';
					}
                    // Standard title
					else {
						output += '<span class="playqueue-action" data-toggle="context" data-target="#context-menu-playqueue-item">' + (typeof(data[i].Time) == 'undefined' ? '' : formatSongTime(data[i].Time)) + '<br><b>&hellip;</b></span>';
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
					var thumb = data[i].file.indexOf('/tidal/') != -1 ? 'images/default-cover-v6.png' : 'imagesw/thmcache/' +
                        encodeURIComponent(data[i].cover_hash) + '_sm.jpg';
					output += showPlayqueueThumb ? '<span class="playqueue-thumb">' + playqueueLazy + '"' + thumb + '"/></span>' : '';
	                // Line 1 title
					output += '<span class="playqueue-action" data-toggle="context" data-target="#context-menu-playqueue-item">' + (typeof(data[i].Time) == 'undefined' ? '' : formatSongTime(data[i].Time)) + '<br><b>&hellip;</b></span>';
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
					output += (typeof(data[i].Artist) === 'undefined') ? data[i].AlbumArtist : data[i].Artist;
				}

                output += '</span></div></li>';

            } // End loop
        }

		// Render playlist
		var element = document.getElementById('playqueue-list');
		element.innerHTML = output;

        if (output) {
            if (showPlayqueueThumb && currentView.indexOf('playback') == 0) {
    			lazyLode('playqueue');
                if ($('#cv-playqueue').css('display') == 'block') {
                    lazyLode('cv-playqueue');
                }
    		}

            if ($('#playback-panel').hasClass('active')) {
                customScroll('playqueue', parseInt(MPD.json['song']));
                if ($('#cv-playqueue').css('display') == 'block') {
                    customScroll('cv-playqueue', parseInt(MPD.json['song']));
                }
            }
        }
        else {
            $('#playback-hd-badge, #playbar-hd-badge, #ss-hd-badge').hide();
        }

        // Reset
        GLOBAL.playqueueChanged = false;
    });
}

// Handle Queue commands
function sendQueueCmd(cmd, path) {
    GLOBAL.playqueueChanged = true;
    $.post('command/queue.php?cmd=' + cmd, {'path': path});
}

// Render Folder view
function renderFolderView(data, path, searchstr) {
	UI.path = path;
    $('#db-path').text(path);

	// Separate out dirs, playlists, files, exclude RADIO folder
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

	// Merge back together
    // NOTE: Files are not sorted and left in the order they appear in the MPD database
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
	var output = '';
	var element = document.getElementById('folderlist');
	element.innerHTML = '';

	for (i = 0; i < data.length; i++) {
    	if (data[i].directory) {
            var cueVirtualDir = false;
    		output += '<li id="db-' + (i + 1) + '" data-path="' + data[i].directory + '">';
            output += '<div class="db-icon db-action">';
            output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder">';
            output += path == '' ?  '<i class="fas fa-hdd icon-root"></i></a></div>' :
                (data[i].cover_hash == '' ? '<i class="fas fa-folder"></i></a></div>' :
                '<img src="' + 'imagesw/thmcache/' + encodeURIComponent(data[i].cover_hash) + '_sm.jpg' + '"></img></a></div>');
            var dirName = data[i].directory.replace(path + '/', '');
            dirName = dirName.lastIndexOf('.cue') == -1 ? dirName : dirName.substr(0, dirName.lastIndexOf('.cue'));
    		output += '<div class="db-entry db-folder db-browse"><div>' + dirName + '</div></div>';
            output += '</li>';

            // Flag cue virtual directory
            cueVirtualDir = data[i].directory.lastIndexOf('.cue') != -1 ? true : false;
        }
    	else if (data[i].playlist && !cueVirtualDir) {
    		// NOTE: Skip wavpack since it may contain embedded playlist and they are not supported yet in Folder view
    		if (data[i].playlist.substr(data[i].playlist.lastIndexOf('.') + 1).toLowerCase() != 'wv') {
    			output += '<li id="db-' + (i + 1) + '" data-path="' + data[i].playlist + '">';
    			output += '<div class="db-icon db-action">';
    			output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-savedpl-root">';
    			output += '<i class="fas fa-list-ul icon-root"></i></a></div>';
    			output += '<div class="db-entry db-savedplaylist db-browse"><div>' + data[i].playlist; + '</div></div>';
    			output += '</li>';
    		}
    	}
        else if (data[i].file && !cueVirtualDir) {
            if (data[(i > 1 ? i - 1 : 0)].Album != data[i].Album || (i == 0 && data[i].Album)) {
                // Album header
    			output += '<li id="db-' + i + '" data-path="' + data[i].file.substr(0, data[i].file.lastIndexOf('/')) + '">';
    			output += '<div class="db-icon db-action">';
    			output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder">';

                var albumDir = data[i].file.substring(0,data[i].file.lastIndexOf('/'));
                albumDir = albumDir.endsWith('.cue') ? albumDir.substring(0, albumDir.lastIndexOf('/')) : albumDir;
    		    output += '<img src="' + 'imagesw/thmcache/' + encodeURIComponent($.md5(albumDir)) + '_sm.jpg' + '">';

                output += '</img></a></div>';
                output += '<div class="db-entry db-album" data-toggle="context" data-target="#context-menu-folder">';
                var artist = data[i].Artist ? data[i].Artist : (data[i].AlbumArtist ? data[i].AlbumArtist : 'Artist tag undefined')
                output += '<div>' + data[i].Album + '<span>' + artist + '</span></div></div>';
                output += '</li>';
            }
    		if (data[i].Title) {
                // Song file
    			output += '<li id="db-' + (i + 1) + '" data-path="' + data[i].file + '">';
    			output += '<div class="db-icon db-song db-action">'; // Hack to enable entire line click for context menu
    			output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder-item">';
                output += data[i].Track + '</a></div>';
    			output += '<div class="db-entry db-song" data-toggle="context" data-target="#context-menu-folder-item"><div>';
                output += data[i].Title + ' <span class="songtime">' + data[i].TimeMMSS + '</span></div>';
    		}
    		else {
                // Playlist item
    			output += '<li id="db-' + (i + 1) + '" data-path="' + data[i].file + '">';
    			if (data[i].file.substr(data[i].file.lastIndexOf('.') + 1).toLowerCase() == 'cue') {
                    var itemType = 'CUE sheet';
    				output += '<div class="db-icon db-song db-browse db-action">';
                    output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-savedpl-item">';
                    output += '<i class="fas fa-list-ul icon-root db-browse-icon"></i></a></div>';
                    output += '<div class="db-entry db-song db-browse" data-toggle="context" data-target="#context-menu-savedpl-item">';
    			}
    			else {
                    // Song file or radio station item
    				if (data[i].file.substr(0,4) == 'http') {
                        var itemType = typeof(RADIO.json[data[i].file]) === 'undefined' ? 'Radio station' : RADIO.json[data[i].file]['name'];
                        var iconClass = 'fa-microphone';
    				}
                    else {
                        var itemType = 'Song file';
                        var iconClass = 'fa-music';
    				}
                    output += '<div class="db-icon db-song db-browse db-action">';
                    output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-savedpl-item">';
                    output += '<i class="fas ' + iconClass + ' db-browse db-browse-icon"></i></a></div>';
                    output += '<div class="db-entry db-song db-browse" data-toggle="context" data-target="#context-menu-savedpl-item">';
    			}

                // File name
                if (data[i].file.substr(0,4) == 'http') {
                    var fileName = data[i].file;
                }
                else {
                    var fileName = data[i].file.replace(path + '/', '').slice(0, data[i].file.lastIndexOf('.'));
                }

                // Finish up
    			output += fileName;
    			output += ' <span>';
    			output += itemType;
    			output += '</span></div></li>';
    		}
        }
	}

    // Render page
	element.innerHTML = output;
	if (currentView == 'folder') {
		customScroll('folder', UI.dbPos[UI.dbPos[10]], 100);
	}
}

// Render Radio view
function renderRadioView() {
    var data = '';
    $.getJSON('command/radio.php?cmd=get_stations', function(data) {
        // Lazyload method
        var radioViewLazy = GLOBAL.nativeLazyLoad ? '<div class="thumbHW"><img loading="lazy" src="' : '<div class="thumbHW"><img class="lazy-radioview" data-original="';
        // Sort/Group and Show/Hide options
        var sortTag = SESSION.json['radioview_sort_group'].split(',')[0].toLowerCase();
        var groupMethod = SESSION.json['radioview_sort_group'].split(',')[1];
        var configuredGroupMethod = groupMethod; // NOTE: For code block "Mark the end of Favorites"
        var showHideMoodeStations = SESSION.json['radioview_show_hide'].split(',')[0];
        var showHideOtherStations = SESSION.json['radioview_show_hide'].split(',')[1];

        // Hide/Un-hide
        // NOTE: these are one-shot actions
        // moOde stations
        if (showHideMoodeStations == 'Hide all' || showHideMoodeStations == 'Un-hide all') {
            var newStationType = showHideMoodeStations == 'Hide all' ? 'h' : 'r';
            for (var i = 0; i < data.length; i++) {
                if (parseInt(data[i].id) < 499 && data[i].type != 'f') {
                    data[i].type = newStationType;
                }
            }
            if (data.length > 0) {
                $.post('command/radio.php?cmd=put_radioview_show_hide', {'block': 'Moode', 'type': newStationType});
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
                $.post('command/radio.php?cmd=put_radioview_show_hide', {'block': 'Moode geo-fenced', 'type': newStationType});
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
                $.post('command/radio.php?cmd=put_radioview_show_hide', {'block': 'Other', 'type': newStationType});
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

        // Regular stations
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

        // Favorite stations
		var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
        favoriteStations.sort(function(a, b) {
            return collator.compare(removeArticles(a['name']), removeArticles(b['name']));
        });

        // Hidden stations
		var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
        hiddenMoodeStations.sort(function(a, b) {
            return collator.compare(removeArticles(a['name']), removeArticles(b['name']));
        });
        hiddenOtherStations.sort(function(a, b) {
            return collator.compare(removeArticles(a['name']), removeArticles(b['name']));
        });

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
        $("#btn-ra-search-reset").hide();
        showSearchResetRa = false;
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
            var bitrateDiv = (sortTag == 'bitrate' || sortTag == 'format') ? '<div class="radioview-metadata-text">' + data[i].bitrate + 'K ' + data[i].format + '</div>' : '';
            var broadcasterDiv = (sortTag == 'broadcaster' && groupMethod == 'No grouping') ? '<div class="radioview-metadata-text">' + data[i].broadcaster + '</div>' : '';
            var countryDiv = sortTag == 'region' ? '<div class="radioview-metadata-text">' + data[i].country + '</div>' : '';
            var countryDiv = (sortTag == 'country' && groupMethod == 'No grouping') ? '<div class="radioview-metadata-text">' + data[i].country + '</div>' : '';
            var languageDiv = (sortTag == 'language' && groupMethod == 'No grouping') ? '<div class="radioview-metadata-text">' + data[i].language + '</div>' : '';
            var genreDiv = sortTag == 'genre' ? '<div class="radioview-metadata-text">' + data[i].genre + '</div>' : '';

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
    		output += '"><div class="db-icon db-song db-browse db-action">' + radioViewLazy + encodeURIComponent(imgUrl) + '"></div><div class="cover-menu" data-toggle="context" data-target="#context-menu-radio-item"></div></div><div class="db-entry db-song db-browse"></div>';
            output += radioViewHdDiv;
			output += radioViewBgDiv;
            output += '<span class="station-name">' + data[i].name + '</span>';
            output += broadcasterDiv;
            output += countryDiv;
            output += languageDiv;
            output += genreDiv;

            //output += radioViewTxDiv;
            output += radioViewNvDiv;
            output += '</li>';

            lastSortTagValue = data[i][sortTag];
    	}

        // Render the list
		var element = document.getElementById('radio-covers');
		element.innerHTML = output;
		if (currentView == 'radio') lazyLode('radio');
    });
}

// Render Playlist view
function renderPlaylistView () {
    var playlists = '';
    $.getJSON('command/playlist.php?cmd=get_playlists', function(playlists) {
        //console.log(playlists);
        // Lazyload method
        var plViewLazy = GLOBAL.nativeLazyLoad ? '<div class="thumbHW"><img loading="lazy" src="' : '<div class="thumbHW"><img class="lazy-playlistview" data-original="';

        // Sort/Group
        var sortTag = SESSION.json['plview_sort_group'].split(',')[0].toLowerCase();
        var groupMethod = SESSION.json['plview_sort_group'].split(',')[1];
        //var configuredGroupMethod = groupMethod; // NOTE: For code block "Mark the end of Favorites"

        // Sort list
		var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
        playlists.sort(function(a, b) {
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

        // Clear search results (if any)
        $('.btnlist-top-pl').show();
        $('#btn-pl-search-reset').hide();
        showSearchResetPl = false;
    	$('#pl-filter').val('');

        // Format list
        var lastSortTagValue = '';
        var numericHeaderPrinted = false;
        var output = '';
    	for (var i = 0; i < playlists.length; i++) {
            // Metadata div's
            var genreDiv = sortTag == 'genre' ? '<div class="playlistview-metadata-text">' + playlists[i].genre + '</div>' : '';

            // Change to Sort tag grouping unless method is No grouping
            if (groupMethod != 'No grouping') {
                groupMethod = 'Sort tag';
            }

            // Construct group header
            if (groupMethod == 'Sort tag') {
                var currentChr1 = removeArticles(playlists[i][sortTag]).substr(0, 1).toUpperCase();
                var lastChr1 = removeArticles(lastSortTagValue).substr(0, 1).toUpperCase()
                if (sortTag == 'name' && currentChr1 != lastChr1) {
                    if (isNaN(currentChr1) === false && numericHeaderPrinted === false) {
                        output += '<li class="horiz-rule-playlistview">0-9</li>';
                        numericHeaderPrinted = true;
                    }
                    else if (isNaN(currentChr1) === true) {
                        output += '<li class="horiz-rule-playlistview">' + currentChr1 + '</li>';
                    }
                }
                else if (sortTag == 'genre' && playlists[i][sortTag].split(', ')[0] != lastSortTagValue.split(', ')[0]) {
                    output += '<li class="horiz-rule-playlistview">' + playlists[i][sortTag].split(', ')[0] + '</li>';
                }
                else if (sortTag != 'name' && sortTag != 'genre' && playlists[i][sortTag] != lastSortTagValue) {
                    output += '<li class="horiz-rule-playlistview">' + playlists[i][sortTag] + '</li>';
                }
            }

            // Construct playlist entries
            var imgUrl = playlists[i].cover == 'local' || playlists[i].cover == 'default' ? 'imagesw/playlist-covers/' + playlists[i].name + '.jpg' : playlists[i].cover;
    		output += '<li id="pl-entry-' + (i + 1) + '" data-path="' + playlists[i].name + '">';
    		output += '<div class="db-icon db-song db-browse db-action">' + plViewLazy + encodeURIComponent(imgUrl) + '">';
            output += playlists[i].cover == 'default' ? '<div class="plview-text-cover-div"><span class="plview-text-cover">' + playlists[i].name + '</span></div>' : '';
            output += '</div><div class="cover-menu" data-toggle="context" data-target="#context-menu-playlist-item"></div></div><div class="db-entry db-song db-browse"></div>';
            output += '<span class="playlist-name">' + playlists[i].name + '</span>';
            output += genreDiv;
            output += '</li>';

            lastSortTagValue = playlists[i][sortTag];
    	}

        // Render the list
		var element = document.getElementById('playlist-covers');
		element.innerHTML = output;
		if (currentView == 'playlist') lazyLode('playlist');
    });
}

// Render Playlist names
function renderPlaylistNames (path) {
    $('#item-to-add').text(path.name);
    UI.dbEntry[4] = path.files;

    var playlists = '';
    $.getJSON('command/playlist.php?cmd=get_playlists', function(playlists) {
        if (playlists.length > 0) {
    		var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
            playlists.sort(function(a, b) {
                return collator.compare(removeArticles(a['name']), removeArticles(b['name']));
            });

            var element = document.getElementById('playlist-names');
            element.innerHTML = '';
            var output = '';

        	for (var i = 0; i < playlists.length; i++) {
        		output += '<li id="pl-name-' + (i + 1) + '" class="pl-name" data-path="' + playlists[i].name + '">';
                output += '<span>' + playlists[i].name + '</span>';
                output += '</li>';
        	}
        }
        else {
            output = 'There are no playlists';
        }

		element.innerHTML = output;
    });
}

// Return formatted total time and show/hide certain elements
function formatKnobTotal(mpdTime) {
	if (MPD.json['artist'] == 'Radio station' && typeof(MPD.json['duration']) === 'undefined') {
		var formattedTotalTime = '';
		$('#total').html('').addClass('total-radio'); // Radio badge
		$('#playbar-mtime').css('display', 'block');
		$('#playbar-mtotal').hide();
	}
	else {
		var formattedTotalTime = formatSongTime(mpdTime);
		$('#total').removeClass('total-radio');
		$('#playbar-mtime').css('display', '');
		$('#playbar-mtotal').show();
	}
    return formattedTotalTime;
}

// Update knob startFrom time
function updKnobStartFrom(startFrom, state) {
	$('#countdown-display').countdown('destroy');

    if (state == 'play' || state == 'pause') {
		if (SESSION.json['timecountup'] == "1" || parseInt(MPD.json['time']) == 0) {
			$('#countdown-display').countdown({since: -(startFrom), compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
		else {
			$('#countdown-display').countdown({until: startFrom, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }

	    if (state == 'pause') {
			$('#countdown-display').countdown('pause');
		}
    }
}

// Update time knob and time track
function updKnobAndTimeTrack() {
	var delta;
    window.clearInterval(UI.knob)
    GLOBAL.initTime = parseInt(MPD.json['song_percent']);
    delta = parseInt(MPD.json['time']) / 1000;
	if (UI.mobile) {
		$('#timetrack').val(GLOBAL.initTime * 10).trigger('change');
	}
    // Playback
	else if (currentView.indexOf('playback') !== -1){
		$('#time').val(GLOBAL.initTime * 10).trigger('change');
	}
    // Library
    else {
		$('#playbar-timetrack').val(GLOBAL.initTime * 10).trigger('change');
	}

	if (MPD.json['state'] === 'stop') {
	    $('#countdown-display').countdown('destroy');
		if (UI.mobile) {
			$('#m-total, #m-countdown, #playbar-mcount').text('00:00');
			$('#playbar-mtotal').html('&nbsp;/&nbsp;00:00');
			$('#timeline').hide();
		}
        else {
			$('#playbar-total, #playbar-countdown, #countdown-display').html('00:00');
			$('#playbar-timeline').css('display', 'none');
			$('#playbar-title').css('padding-bottom', '0');
		}
	}
	// Radio station (never has a duration)
	else if (MPD.json['artist'] == 'Radio station' && typeof(MPD.json['duration']) === 'undefined') {
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
            $('#playbar-mcount, #m-countdown').text($('#countdown-display').text());
		}
		else {
			$('#playbar-timeline').show();
            $('#playbar-countdown').text($('#countdown-display').text());
            $('#playbar-title').css('padding-bottom', '1rem');
		}
	}

    if ((MPD.json['state'] === 'play') || (MPD.json['state'] === 'pause')) {
        // Move these out of the timer
		var tt = $('#timetrack');
		var ti = $('#time');

        UI.knob = setInterval(function() {
			if (UI.mobile || $('#menu-bottom').css('display') == 'flex') {
				if (!timeSliderMove) {

					syncTimers();

					if (UI.mobile) {
						tt.val(GLOBAL.initTime * 10).trigger('change');
					}
				}
			}
            delta === 0 ? GLOBAL.initTime = GLOBAL.initTime + 0.5 : GLOBAL.initTime = GLOBAL.initTime + 0.1; // fast paint when radio station playing
			if (!UI.mobile && $('#menu-bottom').css('display') != 'flex') {
	            if (delta === 0 && GLOBAL.initTime > 100) { // stops painting when radio (delta = 0) and knob fully painted
					window.clearInterval(UI.knob)
					UI.knobPainted = true;
	            }
           		ti.val(GLOBAL.initTime * 10).trigger('change');
			}
        }, delta * 1000);
    }
}

// Fill in timeline color as song plays
$('input[type="range"]').change(function() {
	var val = ($(this).val() - $(this).attr('min')) / ($(this).attr('max') - $(this).attr('min')) * 100;
	if (val < 50) {val = val + 1;} else {val = val - 1;}
	$('.timeline-progress').css('width', val + '%');
	//console.log(val);
});

$('#timetrack, #playbar-timetrack').bind('touchstart mousedown', function(e) {
	timeSliderMove = true;
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

// Format song time for knob #total and #countdown-display and for playlist items
function formatSongTime(seconds) {
	var str, hours, minutes, hh, mm, ss;

    if(isNaN(parseInt(seconds))) {
    	str = ''; // So song time is not displayed for radio stations listed in the Queue
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

function countdownRestart(startFrom) {
    $('#countdown-display').countdown('destroy');
    $('#countdown-display').countdown({since: startFrom, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
}

// Volume control
function setVolume(level, event) {
    level = parseInt(level);
	level = level > GLOBAL.mpdMaxVolume ? GLOBAL.mpdMaxVolume : level;
	level = level < 0 ? 0 : level;
    //console.log(level, event);

	// Unmuted, set volume (incl 0 vol)
	if (SESSION.json['volmute'] == '0') {
		SESSION.json['volknob'] = level.toString();
		sendVolCmd('POST', 'upd_volume', {'volknob': SESSION.json['volknob'], 'event': event}, true); // Async
    } else {
        // Muted
		if (level == 0 && event == 'mute')	{
			sendMpdCmd('setvol 0');
			//console.log('setvol 0 | mute');
            if (SESSION.json['multiroom_tx'] == 'On') {
                sendVolCmd('POST', 'mute_rx_vol', '', true); // Async
            }
		} else {
			// Vol up/dn btns pressed, just store the volume for display
			SESSION.json['volknob'] = level.toString();
		}

        $.post('command/cfg-table.php?cmd=upd_cfg_system', {'volknob': SESSION.json['volknob']});
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
        case 'playqueue':
            if (isNaN(itemNum)) {return;} // Exit if last item in pl ended
    		listSelector = '#playqueue';
    		scrollSelector = '#container-playqueue';
    		chDivisor = 6;
            break;
        case 'cv-playqueue':
            if (isNaN(itemNum)) {return;} // Exit if last item in CoverView Queue ended
    		listSelector = '#cv-playqueue';
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
        case 'playlist':
        case 'playlist_headers':
    		listSelector = '#database-playlist';
    		scrollSelector = listSelector;
    		chDivisor = list == 'playlist' ? 6 : 600;
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

// Scroll to current song if title is clicked
$('#currentsong').click(function(e) {
	if (UI.mobile) {
	    var itemnum = parseInt(MPD.json['song']);
		var centerHeight, scrollTop, scrollCalc, scrollOffset, itemPos;
		itemPos = $('#playqueue ul li:nth-child(' + (itemnum + 1) + ')').position().top;
		centerHeight = parseInt($('#playqueue').height()/3); // Place in upper third instead of middle
	    scrollTop = $('#playqueue').scrollTop();
		scrollCalc = (itemPos + 200);
	    $('html, body').animate({ scrollTop: scrollCalc }, 'fast');
	}
});

// 'All music' menu item
$('.view-all').click(function(e) {
	$('.view-recents span').hide();
	$('.view-all span').show();
	$('#menu-header').click()
	GLOBAL.musicScope = 'all';
    GLOBAL.searchRadio = false;
    LIB.recentlyAddedClicked = false;
	LIB.filters.albums.length = 0;
	UI.libPos.fill(-2);

	filterLib();
    renderAlbums();

	storeLibPos(UI.libPos);
	setLibMenuAndHeader();
});
// 'Recently added' menu item
$('.view-recents').click(function(e) {
	GLOBAL.musicScope = 'recent';
	$('.view-all span').hide();
	$('.view-recents span').show();
    LIB.recentlyAddedClicked = true;
	LIB.filters.albums.length = 0;
	UI.libPos.fill(-2);

	filterLib();
    renderAlbums();

    // Reverse order (NOTE: we may use this someday)
    //$('#lib-album ul').css('transform', 'rotate(180deg)');
    //$('#lib-album ul > li').css('transform', 'rotate(-180deg)');

	storeLibPos(UI.libPos);
	setLibMenuAndHeader();
});

// Context and Main menus
$(document).on('click', '.context-menu a', function(e) {
    var path = UI.dbEntry[0]; // File path or item num
    //console.log($(this).data('cmd'));

    switch ($(this).data('cmd')) {
        //
        // Context menu items
        //
        case 'add_item':
        case 'play_item':
        case 'clear_play_item':
        case 'add_item_next':
        case 'play_item_next':
            if ($('#db-search-results').text() == '') {
                sendQueueCmd($(this).data('cmd'), path);
            } else {
                // Folder view search results
                var files = [];
                $('#folderlist li').each(function() {
                    if ($(this).data('path').indexOf(path) != -1) {
                        files.push($(this).data('path'));
                    }
            	});

                if (files.length > 1) {
                    files = files.slice(1);
                }

                sendQueueCmd($(this).data('cmd').replace('_item', '_group'), files);
            }

            if ($(this).data('cmd').includes('add_')) {
                notify($(this).data('cmd'));
            }

            // If its a playlist, preload the playlist name
    		if (path.indexOf('/') == -1 && path != 'NAS' && path != 'RADIO' && path != 'SDCARD' && path != 'USB') {
    			$('#playlist-save-name').val(path);
    		} else {
    			$('#playlist-save-name').val('');
    		}
            break;
        case 'update_folder':
            submitLibraryUpdate(path);
            break;
	    case 'update_library':
            submitLibraryUpdate();
            break;
        case 'track_info_folder':
            audioInfo('track_info', path);
            break;
        case 'track_info_playqueue':
            var cmd = '';
            if ($('#pq-' + (UI.dbEntry[0] + 1) + ' .pll2').html().substr(0, 2) == '<i') { // Has icon (fa-microphone)
                cmd = 'station_info';
            } else {
                cmd = 'track_info';
            }
            $.getJSON('command/queue.php?cmd=get_playqueue_item_tag&songpos=' + UI.dbEntry[0] + '&tag=file', function(data) {
             	if (data != '') {
                    audioInfo(cmd, data);
                }
            });
            break;
        case 'track_info_playback':
            if ($('#currentsong').html() != '') {
                var cmd = MPD.json['artist'] == 'Radio station' ? 'station_info' : 'track_info';
                audioInfo(cmd, MPD.json['file']);
            }
            break;
        case 'edit_station':
            $.post('command/radio.php?cmd=get_station_contents', {'path': path}, function(data) {
                GLOBAL.editStationId = data['id']; // This is to pass to the update station routine so it can uniquely identify the row
        		$('#edit-station-name').val(data['name']);
        		$('#edit-station-url').val(data['station']);
                $('#edit-logoimage').val('');
                $('#info-toggle-edit-logoimage').css('margin-left','60px');
                $('#preview-edit-logoimage').html('<img src="../imagesw/radio-logos/thumbs/' + data['name'] + '.jpg">');
                $('#edit-station-tags').css('margin-top', '30px');
                $('#edit-station-type span').text(getParamOrValue('param', data['type']));
                $('#edit-station-genre').val(data['genre']);
                $('#edit-station-broadcaster').val(data['broadcaster']);
                $('#edit-station-home-page').val(data['home_page']);
                $('#edit-station-language').val(data['language']);
                $('#edit-station-country').val(data['country']);
                $('#edit-station-region').val(data['region']);
                $('#edit-station-bitrate').val(data['bitrate']);
                $('#edit-station-format').val(data['format']);
                $('#edit-station-geo-fenced span').text(data['geo_fenced']);
                //$('#edit-station-reserved2').val(data['reserved2']);

        		$('#edit-station-modal').modal();
            }, 'json');
            break;
        case 'delete_station':
    		$('#station-path').html(path.slice(0,path.lastIndexOf('.')).substr(6)); // Trim 'RADIO/' and '.pls'
    		$('#delete-station-modal').modal();
            break;
        case 'edit_playlist':
            $.post('command/playlist.php?cmd=get_playlist_contents', {'path': path}, function(data) {
                $('#delete-playlist-item, #move-playlist-item').hide();
                $('#playlist-items').css('margin-top', '0');

                // Metadata
                $('#edit-playlist-name').val(path);
                $('#edit-plcoverimage').val('');
                $('#info-toggle-edit-plcoverimage').css('margin-left','60px');
                $('#preview-edit-plcoverimage').html('<img src="../imagesw/playlist-covers/' + path + '.jpg">');
                $('#edit-playlist-tags').css('margin-top', '2.5em');
                $('#edit-playlist-genre').val(data['genre']);

            	// Playlist items
            	var element = document.getElementById('playlist-items');
            	element.innerHTML = '';
                var output = '';

                if (data['items'].length > 0) {
                    UI.dbEntry[4] = data['items'].length;
                    for (i = 0; i < data['items'].length; i++) {
                        output += '<li id="pl-item-' + (i + 1) + '" class="pl-item" data-toggle="context" data-target="#context-menu-pl-contents" data-path="' + data['items'][i]['path'] + '">';
                        output += '<span class="pl-item-line1">' + data['items'][i]['name'] + '</span>';
                        output += '<span class="pl-item-line2">' + data['items'][i]['line2'] + '</span>';
            			output += '</li>';
                    }
                } else {
                    output = 'Playlist is empty';
                    UI.dbEntry[4] = 0;
                }

                element.innerHTML = output;

        		$('#edit-playlist-modal').modal();
            }, 'json');
            break;
        case 'delete_playlist':
    		$('#playlist-path').html(path)
    		$('#delete-playlist-modal').modal();
            break;
        case 'delete_pl_item':
            $('#move-playlist-item').hide();
            $('#playlist-items').css('margin-top', '3.5em');
    		$('#delete-playlist-item-begpos').attr('max', UI.dbEntry[4]); // Max value (num playlist items in list)
    		$('#delete-playlist-item-endpos').attr('max', UI.dbEntry[4]);
    		$('#delete-playlist-item-newpos').attr('max', UI.dbEntry[4]);
    		$('#delete-playlist-item-begpos').val(path + 1); // Num of selected item
    		$('#delete-playlist-item-endpos').val(path + 1);
            $('#delete-playlist-item').show();
            break;
        case 'move_pl_item':
            $('#delete-playlist-item').hide();
            $('#playlist-items').css('margin-top', '3.5em');
    		$('#move-playlist-item-begpos').attr('max', UI.dbEntry[4]);
    		$('#move-playlist-item-endpos').attr('max', UI.dbEntry[4]);
    		$('#move-playlist-item-newpos').attr('max', UI.dbEntry[4]);
    		$('#move-playlist-item-begpos').val(path + 1);
    		$('#move-playlist-item-endpos').val(path + 1);
    		$('#move-playlist-item-newpos').val(path + 1);
            $('#move-playlist-item').show();
            break;
        case 'get_playlist_names': // From the radio station context menu
            var stationName = path.slice(0,path.lastIndexOf('.')).substr(6); // Trim RADIO/ and .pls
            renderPlaylistNames({'name': stationName, 'files': [path]});
            $('#addto-playlist-name-new').val('');
            $('#add-to-playlist-modal').modal();
            break;
        case 'delete_playqueue_item':
    		$('#delete-playqueue-item-begpos').attr('max', UI.dbEntry[4]); // Max value (num Queue items in list)
    		$('#delete-playqueue-item-endpos').attr('max', UI.dbEntry[4]);
    		$('#delete-playqueue-item-newpos').attr('max', UI.dbEntry[4]);
    		$('#delete-playqueue-item-begpos').val(path + 1); // Num of selected item
    		$('#delete-playqueue-item-endpos').val(path + 1);
    		$('#delete-playqueue-item-modal').modal();
            break;
        case 'move_playqueue_item':
    		$('#move-playqueue-item-begpos').attr('max', UI.dbEntry[4]);
    		$('#move-playqueue-item-endpos').attr('max', UI.dbEntry[4]);
    		$('#move-playqueue-item-newpos').attr('max', UI.dbEntry[4]);
    		$('#move-playqueue-item-begpos').val(path + 1);
    		$('#move-playqueue-item-endpos').val(path + 1);
    		$('#move-playqueue-item-newpos').val(path + 1);
    		$('#move-playqueue-item-modal').modal();
            break;
        case 'setforclockradio':
        case 'setforclockradio-m':
    		if ($(this).data('cmd') == 'setforclockradio-m') { // Called from Configuration modal
    			$('#configure-modal').modal('toggle');
    		}

    		$('#clockradio-mode span').text(SESSION.json['clkradio_mode']);

    		if ($(this).data('cmd') == 'setforclockradio-m') {
    			$('#clockradio-playname').val(SESSION.json['clkradio_name']);
    			UI.dbEntry[0] = '-1'; // For update
    		}
    		else {
    			$('#clockradio-playname').val(UI.dbEntry[5]); // Called from context menu
    		}

    		// Parse start and end values
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
            break;
        case 'multiroom_rx_modal':
        case 'multiroom_rx_modal_limited':
            if (SESSION.json['rx_hostnames'] == 'No receivers found') {
                notify('no_receivers_found');
            } else {
                notify('querying_receivers', '', 'infinite');

                var modalType = $(this).data('cmd') == 'multiroom_rx_modal' ? 'full' : 'limited';
                $.getJSON('command/multiroom.php?cmd=get_rx_status', function(data) {
                    //console.log(data);
                    $('.ui-pnotify-closer').click();

                    if (data == 'Discovery has not been run') {
                        notify('run_receiver_discovery');
                    }
                    else if (data == 'No receivers found') {
                        notify('no_receivers_found');
                    }
                    else {
                        var output = '';
                        var rxStatus = data.split(':');
                        var count = rxStatus.length;
                        for (var i = 0; i < count; i++) {
                            var item = i.toString();
                            var rxStatusParts = rxStatus[i].split(',');
                            // 0: rx
                            // 1: On/Off/Disabled/Unknown
                            // 2: volume
                            // 3: volume,mute_1/0
                            // 4: mastervol_opt_in_1/0
                            // 5: hostname

                            if (rxStatusParts[1] == 'Unknown' || rxStatusParts[1] == 'Disabled') {
                                output += '<div class="control-group" style="margin-bottom:3em;">';
                                // Hostname
                                output += '<label class="control-label multiroom-modal-host">' + rxStatusParts[5] + '</label>';
                                // Status
                                var statusMsg = rxStatusParts[1] == 'Unknown' ? 'Receiver offline' : 'Receiver disabled';
                                output += '<div class="controls">';
                                output += '<div style="font-style:italic;margin-top:.25em;">' + statusMsg + '</div>';
                                output += '</div>';
                                output += '</div>';
                            }
                            else {
                                var rxChecked = rxStatusParts[1] == 'On' ? 'checked' : ''; // Status
                                var rxCheckedDisable = rxStatusParts[2] == '?' ? ' disabled' : ''; // Volume
                                var rxMuteIcon = rxStatusParts[3] == '1' ? 'fa-volume-mute' : 'fa-volume-up'; // Mute
                                var rxMasterVolOptIn = rxStatusParts[4] == '0' ? '' : '<i class="fal fa-dot-circle"></i>'; // Master vol opt-in

                                output += '<div class="control-group">';
                                // Receiver hostname
                                output += '<label class="control-label multiroom-modal-host" for="multiroom-rx-' + item + '-onoff">' + rxStatusParts[5] + '</label>';
                                output += '<div class="controls">';
                                // Receiver On/Off checkbox
                                var topMargin = modalType != 'full' ? ' multiroom-modal-onoff-xtra' : '';
                                output += '<input id="multiroom-rx-' + item + '-onoff" class="checkbox-ctl multiroom-modal-onoff' + topMargin + '" type="checkbox" data-item="' + item + '" ' + rxChecked + rxCheckedDisable + '>';

                                if (modalType == 'full') {
                                    // Volume
                                    var volDisabled = (rxStatusParts[2] == '0dB' || rxStatusParts[2] == '?') ? ' disabled' : '';
                                    output += '<div class="modal-button-style multiroom-modal-btn">';
                                    output += '<button id="multiroom-rx-' + item + '-vol" class="btn btn-primary btn-small multiroom-modal-vol" data-item="' + item +
                                        '"' + volDisabled + '>' + rxStatusParts[2] + '</button>';
                                    output += '</div>';
                                    // Mute toggle
                                    output += '<div class="modal-button-style multiroom-modal-btn">';
                                    output += '<button id="multiroom-rx-' + item + '-mute" class="btn btn-primary btn-small multiroom-modal-mute" data-item="' + item +
                                        '"' + volDisabled + '><i class="fas ' + rxMuteIcon + '"></i></button>';
                                    output += '</div>';
                                    // Master volume opt-in indicator
                                    output += '<div class="modal-button-style multiroom-modal-btn">';
                                    output += rxMasterVolOptIn;
                                    output += '</div>';
                                    output += '</div>';
                                    // Volume slider
                                    output += '<div class="controls">';
                                    output += '<input id="multiroom-rx-' + item + '-vol-slider" class="hslide2" type="range" min="0" max="' + SESSION.json['volume_mpd_max'] +
                                        '" step="1" name="multiroom-rx-' + item + '-vol-slider" value="' + rxStatusParts[2] +
                                        '" oninput="updateRxVolDisplay(this.id, this.value)"' + volDisabled + '>';
                                    output += '</div>';
                                }
                                output += '</div>';
                                output += '</div>';
                            }
                        }

                        $('#multiroom-rx-modal-receivers').html(output);
                        $('#multiroom-rx-modal').modal();
                    }
                });
            }
            break;
        case 'camilladsp_config':
    		var selectedConfig = $(this).data('cdspconfig');

            if (selectedConfig != SESSION.json['camilladsp'] && (selectedConfig == 'off' || SESSION.json['camilladsp'] == 'off')) {
                var notifyOK = true;
                notify('update_cdsp', '', 'infinite');
            } else {
                var notifyOK = false;
            }

    		$.ajax({
    			type: 'POST',
    			url: 'command/camilla.php?cmd=camilladsp_setconfig',
    			async: true,
    			cache: false,
    			data: {'cdspconfig': selectedConfig},
    			success: function(data) {
                    $('.dropdown-cdsp-line span').remove();
                    var selectedHTML = $('a[data-cdspconfig="' + selectedConfig + '"]').html();
                    $('a[data-cdspconfig="' + selectedConfig + '"]').html(selectedHTML +
                        '<span id="menu-check-cdsp"><i class="fal fa-check"></i></span>');

                    // Allow time for worker job to complete
                    if (notifyOK) {
                        setTimeout(function() {
                            notify('update_cdsp_ok');
                        }, 3500);
                    }
    			},
    			error: function() {
                    notify('update_cdsp_err', selectedConfig, '5_seconds');
    			}
    		});
            break;
        //
        // Main menu items
        //
        case 'preferences':
    		bgImgChange = false;

            // Break out misc lib options
            var miscLibOptions = getMiscLibOptions();

    		// Set up disclosures
    		var temp = SESSION.json['preferences_modal_state'].split(',');
    		for (var i = 0; i < 5; i++) {
    			if (temp[i] == '1') {
    				$('#preferences-modal .accordian').eq(i).addClass('active');
    			}
    		}

    		// Appearance
    		$('#theme-name span').text(SESSION.json['themename']);
            $.getJSON('command/cfg-table.php?cmd=get_theme_name', function(data) {
                var themelist = '';
        		for (i = 0; i < data.length; i++) {
        			themelist += '<li class="modal-dropdown-text"><a href="#notarget" data-cmd="theme-name-sel" style="background-color: rgb(' +
                     data[i]['bg_color'] + ')"><span class="text" style="color: #' + data[i]['tx_color'] + '">' +
                     data[i]['theme_name'] + '</span></a></li>';
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
                $('#renderer-backdrop span').text(SESSION.json['renderer_backdrop']);
                $('#font-size span').text(SESSION.json['font_size']);
                $('#native-lazyload span').text(SESSION.json['native_lazyload']);

                // Playback
                $('#playqueue-art-enabled span').text(SESSION.json['playlist_art']);
                $('#show-npicon span').text(SESSION.json['show_npicon']);
                $('#show-cvpb span').text(SESSION.json['show_cvpb']);
        		$('#extra-tags').val(SESSION.json['extra_tags']);
                $('#play-history-enabled span').text(SESSION.json['playhist']);
                // @Atair
                $('#search_site span').text(SESSION.json['search_site']);

                // Library
                $('#onetouch_album span').text(SESSION.json['library_onetouch_album']);
                $('#onetouch_radio span').text(SESSION.json['library_onetouch_radio']);
                $('#onetouch-pl span').text(SESSION.json['library_onetouch_pl']);
                $('#albumview-sort-order span').text('by ' + SESSION.json['library_albumview_sort']);
                $('#tagview-sort-order span').text('by ' + SESSION.json['library_tagview_sort']);
                $('#track-play span').text(SESSION.json['library_track_play']);
                $('#recently-added span').text(getParamOrValue('param', SESSION.json['library_recently_added']));
                $('#show-encoded-at span').text(getParamOrValue('param', SESSION.json['library_encoded_at']));
                $('#cover-search-priority span').text(getParamOrValue('param', SESSION.json['library_covsearchpri']));
                $('#hires-thumbnails span').text(getParamOrValue('param', SESSION.json['library_hiresthm']));
                $('#thumbnail-columns span').text(SESSION.json['library_thumbnail_columns']);

                // Library (Advanced)
                $('#tag-view-genre span').text(SESSION.json['library_tagview_genre']);
                $('#tag-view-artist span').text(SESSION.json['library_tagview_artist']);
                $('#library-album-key span').text(miscLibOptions[1]);
                $('#library-inc-comment-tag span').text(miscLibOptions[0]);
                $('#ignore-articles').val(SESSION.json['library_ignore_articles']);
                $('#show-genres-column span').text(SESSION.json['library_show_genres']);
                $('#show-tagview-covers span').text(SESSION.json['library_tagview_covers']);
                $('#ellipsis-limited-text span').text(SESSION.json['library_ellipsis_limited_text']);
                $('#utf8-char-filter span').text(SESSION.json['library_utf8rep']);

        		// CoverView
                $('#scnsaver-timeout span').text(getParamOrValue('param', SESSION.json['scnsaver_timeout']));
                $('#auto-coverview span').text(SESSION.json['toggle_coverview'] == '-on' ? 'Yes' : 'No');
        		$('#scnsaver-style span').text(SESSION.json['scnsaver_style']);
                $('#scnsaver-mode span').text(SESSION.json['scnsaver_mode']);
                $('#scnsaver-layout span').text(SESSION.json['scnsaver_layout']);
                $('#scnsaver-xmeta span').text(SESSION.json['scnsaver_xmeta']);

                $('#preferences-modal').modal();
            });
            break;
        case 'scnsaver':
            screenSaver('1');
            break;
        case 'viewplayhistory':
            $.getJSON('command/playback.php?cmd=get_play_history', function(data) {
        		var output = '';
        		for (i = 1; i < data.length; i++) {
        			output += data[i];
        		}
                $('ol.playhistory').html(output);
                $('#playhistory-modal').modal();
            });
            break;
        case 'quickhelp':
    		$('#quickhelp').load('quickhelp.html');
            $('#quickhelp-modal').modal();
            break;
        case 'aboutmoode':
    		$('#sys-raspbian-ver').text(SESSION.json['raspbianver']);
    		$('#sys-kernel-ver').text(SESSION.json['kernelver']);
    		$('#sys-processor-arch').text(SESSION.json['procarch']);
    		$('#sys-hardware-rev').text(SESSION.json['hdwrrev']);
    		$('#sys-mpd-ver').text(SESSION.json['mpdver']);
            $('#about-modal').modal();
            break;
    }
	// Remove highlight after selecting action menu item (Folder view)
	if (UI.dbEntry[3].substr(0, 3) == 'db-') {
		$('#' + UI.dbEntry[3]).removeClass('active');
	}
});

// Return misc lib options
function getMiscLibOptions () {
	// [0] = Include comment tag: Yes | No
	// [1] = Album Key: Album@Artist (Default) | Album@Artist@AlbumID | FolderPath | FolderPath@AlbumID
    return SESSION.json['library_misc_options'].split(',');
}

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

	// NOTE: UI.dbEntry[0] = Queue song pos or -1 depending on whether modal was launched from
    // context menu "Set for clock radio" or Configuration modal "Clock radio"
	if (UI.dbEntry[0] != '-1') {
        $.getJSON('command/queue.php?cmd=get_playqueue_item_tag&songpos=' + UI.dbEntry[0] + '&tag=file', function(data) {
            SESSION.json['clkradio_item'] = data;
            updateClockRadioCfgSys();
        });
	} else {
        updateClockRadioCfgSys();
    }

    notify('upd_clock_radio');
});
function updateClockRadioCfgSys() {
    // Update database
    $.post('command/cfg-table.php?cmd=upd_cfg_system',
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
            $.post('command/playback.php?cmd=upd_clock_radio');
        }
    );
}

// Update preferences
$('#btn-preferences-update').click(function(e){
	// Detect certain changes
	var accentColorChange = false;
	var themeSettingsChange = false;
    var libraryOptionsChange = false;
    var clearLibcacheAllReqd = false;
    var reloadLibrary = false
    var regenThumbsReqd = false;
	var scnSaverTimeoutChange = false;
    var autoCoverViewChange = false;
	var scnSaverStyleChange = false;
    var scnSaverModeChange = false;
    var scnSaverLayoutChange = false;
    var extraTagsChange = false;
    var playHistoryChange = false;
	var fontSizeChange = false;
    var lazyLoadChange = false;
    var playqueueArtChange = false;
    var showNpIconChange = false;
	var thumbSizeChange = false;

    // Break out misc lib options
    var miscLibOptions = getMiscLibOptions();

	// Set open/closed state for accordion headers
	var temp = [0,0,0,0,0];
	for (var i = 0; i < 5; i++) {
		if ($('#preferences-modal div.control-group').eq(i).css('display') == 'block') {
			temp[i] = 1;
		}
	}
	SESSION.json['preferences_modal_state'] = temp[0] + ',' + temp[1] + ',' + temp[2] + ',' + temp[3] + ',' + temp[4];

	// Appearance
	if (SESSION.json['themename'] != $('#theme-name span').text()) {themeSettingsChange = true;}
	if (SESSION.json['accent_color'] != $('#accent-color span').text()) {themeSettingsChange = true; accentColorChange = true;}
	if (SESSION.json['alphablend'] != $('#alpha-blend span').text()) {themeSettingsChange = true;}
	if (SESSION.json['adaptive'] != $('#adaptive-enabled span').text()) {themeSettingsChange = true;}
	if (SESSION.json['cover_backdrop'] != $('#cover-backdrop-enabled span').text()) {themeSettingsChange = true;}
	if (SESSION.json['cover_blur'] != $('#cover-blur span').text()) {themeSettingsChange = true;}
	if (SESSION.json['cover_scale'] != $('#cover-scale span').text()) {themeSettingsChange = true;}
    if (SESSION.json['render_backdrop'] != $('#renderer-backdrop span').text()) {/*NOP*/}
    if (SESSION.json['font_size'] != $('#font-size span').text()) {fontSizeChange = true;};
    if (SESSION.json['native_lazyload'] != $('#native-lazyload span').text()) {lazyLoadChange = true;};

    // Playback
    if (SESSION.json['playlist_art'] != $('#playqueue-art-enabled span').text()) {playqueueArtChange = true;}
    if (SESSION.json['show_npicon'] != $('#show-npicon span').text()) {showNpIconChange = true;}
    if (SESSION.json['extra_tags'] != $('#extra-tags').val()) {extraTagsChange = true;}
    if (SESSION.json['playhist'] != $('#play-history-enabled span').text()) {playHistoryChange = true;}

    // Library
    if (SESSION.json['library_onetouch_album'] != $('#onetouch_album span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_onetouch_radio'] != $('#onetouch_radio span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_onetouch_pl'] != $('#onetouch-pl span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_albumview_sort'] != $('#albumview-sort-order span').text().replace('by ', '')) {libraryOptionsChange = true;}
    if (SESSION.json['library_tagview_sort'] != $('#tagview-sort-order span').text().replace('by ', '')) {libraryOptionsChange = true;}
    if (SESSION.json['library_track_play'] != $('#track-play span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_recently_added'] != getParamOrValue('value', $('#recently-added span').text())) {libraryOptionsChange = true;}
    if (SESSION.json['library_encoded_at'] != getParamOrValue('value', $('#show-encoded-at span').text())) {reloadLibrary = true;}
    if (SESSION.json['library_covsearchpri'] != getParamOrValue('value', $('#cover-search-priority span').text())) {libraryOptionsChange = true;}
    if (SESSION.json['library_hiresthm'] != getParamOrValue('value', $('#hires-thumbnails span').text())) {regenThumbsReqd = true;}
    if (SESSION.json['library_thumbnail_columns'] != $('#thumbnail-columns span').text()) {thumbSizeChange = true;}

    // Library (Advanced)
    if (SESSION.json['library_tagview_genre'] != $('#tag-view-genre span').text()) {clearLibcacheAllReqd = true;}
    if (SESSION.json['library_tagview_artist'] != $('#tag-view-artist span').text()) {libraryOptionsChange = true;}
    if (miscLibOptions[1] != $('#library-album-key span').text()) {clearLibcacheAllReqd = true;}
    if (miscLibOptions[0] != $('#library-inc-comment-tag span').text()) {clearLibcacheAllReqd = true;}
    if (SESSION.json['library_ignore_articles'] != $('#ignore-articles').val()) {libraryOptionsChange = true;}
    if (SESSION.json['library_show_genres'] != $('#show-genres-column span').text()) {
		$('#show-genres-column span').text() == "Yes" ? $('#top-columns').removeClass('nogenre') : $('#top-columns').addClass('nogenre');
	}
    if (SESSION.json['library_tagview_covers'] != $('#show-tagview-covers span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_ellipsis_limited_text'] != $('#ellipsis-limited-text span').text()) {
		$('#ellipsis-limited-text span').text() == "Yes" ?
        $('#library-panel, #radio-panel, #playlist-panel').addClass('limited') :
        $('#library-panel, #radio-panel, #playlist-panel').removeClass('limited');
	}
    if (SESSION.json['library_utf8rep'] != $('#utf8-char-filter span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['search_site'] != $('#search_site span').text()) {libraryOptionsChange = true;} // @Atair

    // CoverView
    if (SESSION.json['scnsaver_timeout'] != getParamOrValue('value', $('#scnsaver-timeout span').text())) {scnSaverTimeoutChange = true;}
    if (SESSION.json['toggle_coverview'] != ($('#auto-coverview span').text() == 'Yes' ? '-on' : '-off')) {autoCoverViewChange = true;}
	if (SESSION.json['scnsaver_style'] != $('#scnsaver-style span').text()) {scnSaverStyleChange = true;}
    if (SESSION.json['scnsaver_mode'] != $('#scnsaver-mode span').text()) {scnSaverModeChange = true;}
    if (SESSION.json['scnsaver_layout'] != $('#scnsaver-layout span').text()) {scnSaverLayoutChange = true;}
    if (SESSION.json['scnsaver_xmeta'] != $('#scnsaver-xmeta span').text()) {extraTagsChange = true;}

	// Appearance
	SESSION.json['themename'] = $('#theme-name span').text();
	SESSION.json['accent_color'] = $('#accent-color span').text();
	SESSION.json['alphablend'] = $('#alpha-blend span').text();
	SESSION.json['adaptive'] = $('#adaptive-enabled span').text();
	SESSION.json['cover_backdrop'] = $('#cover-backdrop-enabled span').text();
	SESSION.json['cover_blur'] = $('#cover-blur span').text();
	SESSION.json['cover_scale'] = $('#cover-scale span').text();
    SESSION.json['renderer_backdrop'] = $('#renderer-backdrop span').text();
    SESSION.json['font_size'] = $('#font-size span').text();
    SESSION.json['native_lazyload'] = $('#native-lazyload span').text();

    // Playback
    SESSION.json['playlist_art'] = $('#playqueue-art-enabled span').text();
    SESSION.json['show_npicon'] = $('#show-npicon span').text();
    SESSION.json['show_cvpb'] = $('#show-cvpb span').text();
    SESSION.json['extra_tags'] = $('#extra-tags').val();
    SESSION.json['search_site'] = $('#search_site span').text(); // @Atair
    SESSION.json['playhist'] = $('#play-history-enabled span').text();

    // Library
    SESSION.json['library_onetouch_album'] = $('#onetouch_album span').text();
    SESSION.json['library_onetouch_radio'] = $('#onetouch_radio span').text();
    SESSION.json['library_onetouch_pl'] = $('#onetouch-pl span').text();
    SESSION.json['library_albumview_sort'] = $('#albumview-sort-order span').text().replace('by ', '');
    SESSION.json['library_tagview_sort'] = $('#tagview-sort-order span').text().replace('by ', '');
    SESSION.json['library_track_play'] = $('#track-play span').text();
    SESSION.json['library_recently_added'] = getParamOrValue('value', $('#recently-added span').text());
    SESSION.json['library_encoded_at'] = getParamOrValue('value', $('#show-encoded-at span').text());
    SESSION.json['library_covsearchpri'] = getParamOrValue('value', $('#cover-search-priority span').text());
    SESSION.json['library_hiresthm'] = getParamOrValue('value', $('#hires-thumbnails span').text());
    SESSION.json['library_thumbnail_columns'] = $('#thumbnail-columns span').text();

    // Library (Advanced)
    SESSION.json['library_tagview_genre'] = $('#tag-view-genre span').text();
    SESSION.json['library_tagview_artist'] = $('#tag-view-artist span').text();
    SESSION.json['library_misc_options'] = $('#library-inc-comment-tag span').text() + ',' + $('#library-album-key span').text();
    SESSION.json['library_ignore_articles'] = $('#ignore-articles').val().trim();
    SESSION.json['library_show_genres'] = $('#show-genres-column span').text();
    SESSION.json['library_tagview_covers'] = $('#show-tagview-covers span').text();
    SESSION.json['library_ellipsis_limited_text'] = $('#ellipsis-limited-text span').text();
    SESSION.json['library_utf8rep'] = $('#utf8-char-filter span').text();

    // CoverView
    SESSION.json['scnsaver_timeout'] = getParamOrValue('value', $('#scnsaver-timeout span').text());
    SESSION.json['toggle_coverview'] = ($('#auto-coverview span').text() == 'Yes' ? '-on' : '-off');
	SESSION.json['scnsaver_style'] = $('#scnsaver-style span').text();
    SESSION.json['scnsaver_mode'] = $('#scnsaver-mode span').text();
    SESSION.json['scnsaver_layout'] = $('#scnsaver-layout span').text();
    SESSION.json['scnsaver_xmeta'] = $('#scnsaver-xmeta span').text();

	if (fontSizeChange == true) {
		setFontSize();
		window.dispatchEvent(new Event('resize')); // Resize knobs if needed
	}
	if (scnSaverTimeoutChange == true) {
        $.post('command/playback.php?cmd=reset_screen_saver');
	}
    if (autoCoverViewChange == true) {
        $.post('command/system.php?cmd=restart_localui');
	}
    if (clearLibcacheAllReqd == true) {
        $.post('command/music-library.php?cmd=clear_libcache_all');
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
		} else {
			$('#cover-backdrop').html('');
		}

		setColors();
	}
	if (playqueueArtChange == true) {
		renderPlayqueue(MPD.json['state']);
	}

    if (showNpIconChange == true) {
        setNpIcon();
    }

	if (thumbSizeChange){
		getThumbHW();
	}

    // Update database
    $.post('command/cfg-table.php?cmd=upd_cfg_system',
        {
            // Appearance
            'themename': SESSION.json['themename'],
            'accent_color': SESSION.json['accent_color'],
            'alphablend': SESSION.json['alphablend'],
            'adaptive': SESSION.json['adaptive'],
            'cover_backdrop': SESSION.json['cover_backdrop'],
            'cover_blur': SESSION.json['cover_blur'],
            'cover_scale': SESSION.json['cover_scale'],
            'renderer_backdrop': SESSION.json['renderer_backdrop'],
            'font_size': SESSION.json['font_size'],
            'native_lazyload': SESSION.json['native_lazyload'],

            // Playback
            'playlist_art': SESSION.json['playlist_art'],
            'show_npicon': SESSION.json['show_npicon'],
            'show_cvpb': SESSION.json['show_cvpb'],
            'extra_tags': SESSION.json['extra_tags'],
            'search_site': SESSION.json['search_site'], // @Atair
            'playhist': SESSION.json['playhist'],

            // Library
            'library_onetouch_album': SESSION.json['library_onetouch_album'],
            'library_onetouch_radio': SESSION.json['library_onetouch_radio'],
            'library_onetouch_pl': SESSION.json['library_onetouch_pl'],
            'library_albumview_sort': SESSION.json['library_albumview_sort'],
            'library_tagview_sort': SESSION.json['library_tagview_sort'],
            'library_track_play': SESSION.json['library_track_play'],
            'library_recently_added': SESSION.json['library_recently_added'],
            'library_encoded_at': SESSION.json['library_encoded_at'],
            'library_covsearchpri': SESSION.json['library_covsearchpri'],
            'library_hiresthm': SESSION.json['library_hiresthm'],
            'library_thumbnail_columns': SESSION.json['library_thumbnail_columns'],

            // Library (Advanced)
            'library_tagview_genre': SESSION.json['library_tagview_genre'],
            'library_tagview_artist': SESSION.json['library_tagview_artist'],
            'library_misc_options': SESSION.json['library_misc_options'],
            'library_ignore_articles': SESSION.json['library_ignore_articles'],
            'library_show_genres': SESSION.json['library_show_genres'],
            'library_tagview_covers': SESSION.json['library_tagview_covers'],
            'library_ellipsis_limited_text': SESSION.json['library_ellipsis_limited_text'],
            'library_utf8rep': SESSION.json['library_utf8rep'],

            // CoverView
            'scnsaver_timeout': SESSION.json['scnsaver_timeout'],
            'toggle_coverview': SESSION.json['toggle_coverview'],
            'scnsaver_style': SESSION.json['scnsaver_style'],
            'scnsaver_mode': SESSION.json['scnsaver_mode'],
            'scnsaver_layout': SESSION.json['scnsaver_layout'],
            'scnsaver_xmeta': SESSION.json['scnsaver_xmeta'],

            // Internal
            'preferences_modal_state': SESSION.json['preferences_modal_state']
        },
        function() {
            if (extraTagsChange || scnSaverStyleChange || scnSaverModeChange || scnSaverLayoutChange ||
                playHistoryChange || libraryOptionsChange || clearLibcacheAllReqd || lazyLoadChange ||
                (SESSION.json['bgimage'] != '' && SESSION.json['cover_backdrop'] == 'No') || UI.bgImgChange == true) {
                notify('settings_updated', 'Auto-refresh in 2 seconds');
                setTimeout(function() {
                    location.reload(true);
                }, 2000);
            } else if (reloadLibrary) {
                $('#btn-ra-refresh').click();
                loadLibrary();
            } else if (regenThumbsReqd) {
                notify('regen_thumbs', 'Thumbnails must be regenerated after changing this setting', '5_seconds');
            } else {
                notify('settings_updated');
            }
        }
    );
});

// Remove bg image (NOTE choose bg image is in indextpl.html)
$('#remove-bgimage').click(function(e) {
	e.preventDefault();
	if ($('#current-bgimage').html() != '') {
		$('#choose-bgimage').show();
		$('#remove-bgimage').hide();
		$('#current-bgimage').html('');
		$('#cover-backdrop').css('background-image','');
		$('#info-toggle-bgimage').css('margin-left','5px');
        $.post('command/playback.php?cmd=remove_bg_image');
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
        $.post('command/playback.php?cmd=set_bg_image', {'blob': data});

	}
	reader.readAsDataURL(files[0]);
}

// Import cover image to server
function newCoverImage(files, view) {
    if (view == 'radio') {
        var error_selector = '#error-new-logoimage';
        var preview_selector = '#preview-new-logoimage';
        var info_selector = '#info-toggle-new-logoimage';
        var tags_selector = '#new-station-tags';
        var name_selector = '#new-station-name';
        var cmd = 'set_ralogo_image';
        var script = 'radio.php';
    }
    else { // playlist
        var error_selector = '#error-new-plcoverimage';
        var preview_selector = '#preview-new-plcoverimage';
        var info_selector = '#info-toggle-new-plcoverimage';
        var tags_selector = '#new-playlist-tags';
        var name_selector = '#new-playlist-name';
        var cmd = 'set_plcover_image';
        var script = 'playlist.php';
    }

	if (files[0].size > 1000000) {
		$(error_selector).text('Image must be less than 1MB in size');
		return;
	}
	else if (files[0].type != 'image/jpeg') {
		$(error_selector).text('Image format must be JPEG');
		return;
	}
	else {
		$(error_selector).text('');
	}

	imgUrl = (URL || webkitURL).createObjectURL(files[0]);
	$(preview_selector).html("<img src='" + imgUrl + "' />");
	$(info_selector).css('margin-left','60px');
    $(tags_selector).css('margin-top', '30px');
	var name = $(name_selector).val();
	URL.revokeObjectURL(imgUrl);
	var reader = new FileReader();
	reader.onload = function(e) {
		var dataURL = reader.result;
		// Strip off the header from the dataURL: 'data:[<MIME-type>][;charset=<encoding>][;base64],<data>'
		var data = dataURL.match(/,(.*)$/)[1];
        $.post('command/' + script + '?cmd=' + cmd, {'name': name, 'blob': data});
	}
	reader.readAsDataURL(files[0]);
}
// Edit (replace/remove) existing cover image
function editCoverImage(files, view) {
    if (view == 'radio') {
        var error_selector = '#error-edit-logoimage';
        var preview_selector = '#preview-edit-logoimage';
        var info_selector = '#info-toggle-edit-logoimage';
        var tags_selector = '#edit-station-tags';
        var name_selector = '#edit-station-name';
        var cmd = 'set_ralogo_image';
        var script = 'radio.php';
    }
    else { // playlist
        var error_selector = '#error-edit-plcoverimage';
        var preview_selector = '#preview-edit-plcoverimage';
        var info_selector = '#info-toggle-edit-plcoverimage';
        var tags_selector = '#edit-playlist-tags';
        var name_selector = '#edit-playlist-name';
        var cmd = 'set_plcover_image';
        var script = 'playlist.php';
    }

	if (files[0].size > 1000000) {
		$(error_selector).text('Image must be less than 1MB in size');
		return;
	}
	else if (files[0].type != 'image/jpeg') {
		$(error_selector).text('Image format must be JPEG');
		return;
	}
	else {
		$(error_selector).text('');
	}

	imgUrl = (URL || webkitURL).createObjectURL(files[0]);
	$(preview_selector).html("<img src='" + imgUrl + "' />");
	$(info_selector).css('margin-left','60px');
	var name = $(name_selector).val();
	URL.revokeObjectURL(imgUrl);
	var reader = new FileReader();
	reader.onload = function(e) {
		var dataURL = reader.result;
		// Strip off the header from the dataURL: 'data:[<MIME-type>][;charset=<encoding>][;base64],<data>'
		var data = dataURL.match(/,(.*)$/)[1];
        $.post('command/' + script + '?cmd=' + cmd, {'name': name, 'blob': data});
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
	notify('restart', '', '5_seconds');
    $.post('command/system.php?cmd=reboot');
});

$('#system-shutdown').click(function(e) {
	UI.restart = 'shutdown';
    notify('shutdown', '', '5_seconds');
    $.post('command/system.php?cmd=poweroff');
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

// Take an rgb color and apply a frac(tion) of it to an rgba color, this is used to fake doing an rgba color on a
// background that would then requite additional compositing. return rgba(r,g,b,op) color string.

function rgbaToRgb(frac, op, rgba, rgb) {
	var r3 = Math.round(((1 - frac) * rgb[0]) + (frac * rgba[0]));
	var g3 = Math.round(((1 - frac) * rgb[1]) + (frac * rgba[1]));
	var b3 = Math.round(((1 - frac) * rgb[2]) + (frac * rgba[2]));
	var color = 'rgba(' + r3 + ', ' + g3 + ', ' + b3 + ', ' + op + ')';
	return color;
}

function splitColor(tempcolor) {
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

// Always use rgba now newui2
function str2rgba(tempcolor) {
	var temp = 'rgba(' + tempcolor + ')';
	return temp;
}
function str2hex(tempcolor) {
	var temp = '#' + tempcolor;
	return temp;
}

// Sekrit search function
function dbFastSearch() {
	$('#dbsearch-alltags').val($('#dbfs').val());
	$('#db-search-submit').click();
	$('#dbfs').blur();
	return false;
}

// Generate a set of colors based on the background and text color for use in buttons, etc.
// temp1 = theme/adaptBack(ground)
// temp2 = theme/adaptColor
// temprgba is an array holding the rgba components
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
	tempcolor = rgbaToRgb(.65 - tempx, '.15', temprgba, temprgb);
	document.body.style.setProperty('--btnshade3', tempcolor);
	document.body.style.setProperty('--btnshade4', getYIQ(temp1) > 127 ? 'rgba(32,32,32,0.10)' : 'rgba(208,208,208,0.17)');
	$('#content').hasClass('visacc') ? op = .95 : op = .9;
	document.body.style.setProperty('--modalbkdr', rgbaToRgb(.95, .95, temprgba, temprgb));
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
			npIcon = 'url("../images/4band-npicon/audiod.svg")';
			npIconPaused = 'url("../images/4band-npicon/audiod-flat.svg")'
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
			npIcon = 'url("../images/4band-npicon/audiow.svg")';
			npIconPaused = 'url("../images/4band-npicon/audiow-flat.svg")'
			document.body.style.setProperty('--timethumb', 'url("' + thumbw + '")');
			document.body.style.setProperty('--fatthumb', 'url("' + fatthumbw + '")');
			document.body.style.setProperty('--timecolor', 'rgba(240,240,240,0.25)');
			document.body.style.setProperty('--trackfill', 'rgba(240,240,240,1.0)');
			document.body.style.setProperty('--radiobadge', 'url("../images/radio-l.svg")');
			setTimeout(function() {
				$('.playbackknob, .volumeknob').trigger('configure',{"bgColor":"rgba(224,224,224,0.09)",
					"fgColor":UI.accenta
				});
			}, DEFAULT_TIMEOUT);
		}
		document.body.style.setProperty('--npicon', npIcon);
	}
}

// Graphic EQ
function updEqgFreq(selector, value) {
    $(selector).html(value);
}
// Parametric EQ
function updEqpMasterGain(selector, value) {
    $(selector).html(value + ' dB');
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
			$('#container-playqueue').css('visibility','visible');
			$('#menu-bottom').show();
			$('#menu-top').css('height', $('#menu-top').css('line-height'));
			$('#menu-top').css('backdrop-filter', 'blur(20px)');
            $('#playbar-toggles .add-item-to-favorites').show();
            $('#random-album').hide();
			showMenuTopW = true;
		}
		else if (UI.mobile && $(window).scrollTop() == '0' ) {
			$('#container-playqueue').css('visibility','hidden');
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

// Set theme colors
function themeToColors(accentColor) {
	switch (accentColor) {
		case 'Amethyst':
			var ac1 = '#8e44ad', ac2 = 'rgba(142,68,173,0.71)';
			break;
		case 'Berry':
			var ac1 = '#B53471', ac2 = 'rgba(181,52,113,0.71)';
			break;
        case 'Bluejeans':
			var ac1 = '#1a439c', ac2 = 'rgba(26,67,156,0.71)';
			break;
        case 'BlueLED':
			var ac1 = '#005AFD', ac2 = 'rgba(0,90,253,0.71)';
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
        case 'Lipstick':
			var ac1 = '#eb2f06', ac2 = 'rgba(235,47,6,0.71)';
			break;
        case 'Moss':
			var ac1 = '#218c74', ac2 = 'rgba(33,140,116,0.71)';
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
    // NOTE: .artist-name or .album-name
	className = SESSION.json['library_tagview_sort'].toLowerCase().split('/');
	SESSION.json['library_tagview_covers'] == "Yes" ? classPrefix = '-name-art' : classPrefix = '-name';
    var selector = '.' + className[0] + classPrefix;
    listLook('albumsList li ' + selector, 'albums', $(this).text());
});
$('#index-albumcovers li').on('click', function(e) {
    // NOTE: .artist-name or .album-name
    var selector = '.' + SESSION.json['library_albumview_sort'].toLowerCase() + '-name';
    var selector2 = selector.replace(/\/year/g, '');
    listLook('albumcovers li ' + selector2, 'albumcovers', $(this).text());
});
$('#index-browse li').on('click', function(e) {
	listLook('database li', 'folder', $(this).text());
});
$('#index-radio li').on('click', function(e) {
    list = SESSION.json['radioview_sort_group'].split(',')[1] == 'No grouping' ? 'radio' : 'radio_headers';
	listLook('radio-covers li', list, $(this).text());
});
$('#index-playlist li').on('click', function(e) {
    list = SESSION.json['plview_sort_group'].split(',')[1] == 'No grouping' ? 'playlist' : 'playlist_headers';
	listLook('playlist-covers li', list, $(this).text());
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
                if (text != 'favorites' && text.substr(0, 1) == searchText) {return false;}
        		itemNum++;
        	});
        }
        else if (list == 'playlist_headers') {
            $('#' + selector).each(function() {
                var text = $(this).hasClass('horiz-rule-playlistview') ? removeArticles($(this).text().toLowerCase()) : '';
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

// Radio pos
function storeRadioPos(pos) {
	//console.log('radio_pos', pos);
    $.post('command/cfg-table.php?cmd=upd_cfg_system', {'radio_pos': pos});
}
// Playlist pos
function storePlaylistPos(pos) {
	//console.log('playlist_pos', pos);
    $.post('command/cfg-table.php?cmd=upd_cfg_system', {'playlist_pos': pos});
}
// Library pos
function storeLibPos(pos) {
	//console.log('lib_pos', pos[0], pos[1], pos[2]);
    $.post('command/cfg-table.php?cmd=upd_cfg_system', {'lib_pos': pos[0] + ',' + pos[1] + ',' + pos[2], 'lib_scope': GLOBAL.musicScope});
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
    $('#container-playqueue').css('visibility', 'hidden');
	$('#menu-top').css('height', '0');
	$('#menu-top').css('backdrop-filter', '');
	$('#menu-bottom, .viewswitch').css('display', 'flex');
    $('#multiroom-sender,  #updater-notification').hide();

    syncTimers();

    if (currentView == 'radio') {
		makeActive('.radio-view-btn', '#radio-panel', currentView);
	}
	else if (currentView == 'tag') {
		makeActive('.tag-view-btn', '#library-panel', currentView);

        if (!GLOBAL.libRendered) {
            loadLibrary();
        }
	}
	else if (currentView == 'album') {
		makeActive('.album-view-btn', '#library-panel', currentView);
		if (UI.libPos[1] >= 0) {
            if ($('#tracklist-toggle').text().trim() == 'Hide tracks') {
                $('#bottom-row').css('display', 'flex')
    			$('#lib-albumcover').css('height', 'calc(50% - env(safe-area-inset-top) - 2.75rem)'); // Was 1.75em
    			$('#index-albumcovers').hide();
            }
		}

        if (!GLOBAL.libRendered) {
            loadLibrary();
        }
	}
    else if (currentView == 'playlist') {
		makeActive('.playlist-view-btn', '#playlist-panel', currentView);
	}
	// Default to folder view
	else {
		makeActive('.folder-view-btn', '#folder-panel', 'folder');
	}
});

// Switch to Playback
$('#playbar-switch, #playbar-cover, #playbar-title').click(function(e){
    //console.log('click playbar');
    if (coverView) {
        return;
    }
	if (SESSION.json['playlist_art'] == 'Yes') lazyLode('playqueue');

	if (currentView.indexOf('playback') == 0) {
        // Already in playback means mobile and view has scrolled, so scroll to top
		$(window).scrollTop(0);
	}
	else {
		currentView = 'playback,' + currentView;
        $.post('command/cfg-table.php?cmd=upd_cfg_system', {'current_view': currentView});

		syncTimers();
		setColors();

        // TEST: Fixes issue where some elements briefly remain on-screen when switching between Playback and Library
        $('#coverart-link').show();

		$('#menu-header').text('');
		$('#container-playqueue').css('visibility','');
		$('#menu-bottom, .viewswitch').css('display', 'none');
		$('#playback-controls').css('display', '');

        SESSION.json['multiroom_tx'] == 'On' ? $('#multiroom-sender').show() : $('#multiroom-sender').hide();
        if (SESSION.json['updater_auto_check'] == 'On' && SESSION.json['updater_available_update'].includes('Release')) {
            $('#updater-notification').show();
        } else {
            $('#updater-notification').hide();
        }

		if (UI.mobile) {
            // Make sure playlist is hidden and controls are showing
			showMenuTopW = false;
			$(window).scrollTop(0);
			$('#content').css('height', 'unset');
			$('#container-playqueue').css('visibility','hidden');
			var a = $('#countdown-display').text() ? $('#m-countdown').text(a) : $('#m-countdown').text('00:00');
		}
        else {
			customScroll('playqueue', parseInt(MPD.json['song']), 0);
		}
		$('#folder-panel, #radio-panel, #playlist-panel, #library-panel').removeClass('active');
		$('#playback-panel').addClass('active');
	}
});

// Click anywhere off a context menu
// Closes the menu and de-highlights the items
$('#context-backdrop').click(function(e){
	$('#context-backdrop').hide();
	$('.context-menu').removeClass('open');
	$('.context-menu-lib').removeClass('open');
    if (currentView == 'folder' || currentView == 'radio' || currentView == 'playlist') {
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

// Remove highlight from playlist item
$('#edit-playlist-modal').click(function(e) {
    if (isNaN(UI.dbEntry[0])) {
        return;
    }
    else if (typeof($(e.target).attr('class')) == 'undefined' || !$(e.target).attr('class').includes('pl-item')) {
        $('#pl-item-' + (UI.dbEntry[0] + 1).toString()).removeClass('active');
        $('#playlist-items').css('margin-top', '0');
        $('#delete-playlist-item').hide();
        $('#move-playlist-item').hide();
    }
});

// Toggle the accordian lists on Preferences
$('#preferences-modal .h5').click(function(e) {
	$(this).parent('div.accordian').toggleClass('active');
});

// Synchronize times to/from playbar so we don't have to keep countdown timers running which = ugly idle perf
function syncTimers() {
    var a = $('#countdown-display').text();
    if (a != GLOBAL.lastTimeCount) { // Only update if time has changed
        if (UI.mobile) { // Only change when needed to save work
            $('#m-countdown, #playbar-mcount').text(a);
        }
        else if (coverView || currentView.indexOf('playback') == -1) {
            $('#playbar-countdown').text(a);
            GLOBAL.initTime < 50 ? g = GLOBAL.initTime + 1 : g = GLOBAL.initTime - 1; // Adjust for thumb
            $('#playbar-timetrack').val(GLOBAL.initTime * 10); // min = 0, max = 1000
            $('#playbar-timeline .timeline-progress').css('width', g + '%');
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
	$('#viewswitch').removeClass('vr vp vf vt va');
    $.post('command/cfg-table.php?cmd=upd_cfg_system', {'current_view': view});
	currentView = view;
	setColors();

    if (view == 'tag' || view == 'album') {
        if ($('#lib-album-filter').val() != '') {
            $('#searchResetLib').show();
        }
    }

	switch (view) {
		case 'radio':
			$('#viewswitch').addClass('vr');
			$('#playbar-toggles .add-item-to-favorites').show();
            $('#random-album, .adv-search-btn').hide();
			lazyLode('radio');
			break;
		case 'folder':
			$('#viewswitch').addClass('vf');
			$('#playbar-toggles .add-item-to-favorites').show();
            $('#random-album, .adv-search-btn').hide();
			break;
        case 'tag':
			$('#viewswitch').addClass('vt');
            $('#playbar-toggles .add-item-to-favorites').hide();
            $('#random-album, .adv-search-btn').show();
			$('#library-panel').addClass('tag').removeClass('covers');
            $('#index-albumcovers').hide();
			SESSION.json['library_show_genres'] == 'Yes' ? $('#top-columns').removeClass('nogenre') : $('#top-columns').addClass('nogenre');
			if (SESSION.json['library_tagview_covers']) lazyLode('tag');
			break;
		case 'album':
			$('#viewswitch').addClass('va');
            $('#playbar-toggles .add-item-to-favorites').hide();
            $('#random-album, .adv-search-btn').show();
			$('#library-panel').addClass('covers').removeClass('tag');
            if ($('#tracklist-toggle').text().trim() == 'Hide tracks') {
                $('#bottom-row').css('display', 'flex')
                $('#lib-albumcover').css('height', 'calc(50% - env(safe-area-inset-top) - 2.75rem)'); // Was 1.75em
                $('#index-albumcovers').hide();
            }
            else {
                $('#bottom-row').css('display', '');
                $('#lib-albumcover').css('height', '100%');
                $('#index-albumcovers').show();
            }
			lazyLode('album');
			break;
        case 'playlist':
			$('#viewswitch').addClass('vp');
			$('#playbar-toggles .add-item-to-favorites').show();
            $('#random-album, .adv-search-btn').hide();
			lazyLode('playlist');
			break;
	}
	setLibMenuAndHeader();
	$(vswitch + ',' + panel).addClass('active');
	//const duration = performance.now() - startTime;
    //console.log(duration + 'ms');
}

// Set the Library menu and header
function setLibMenuAndHeader () {
    var headerText = (UI.mobile || currentView.indexOf('playback') != -1) ? '' : 'Browse by ';

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
	else if (currentView == 'tag' || currentView == 'album') {
		currentView == 'album' ? headerText += SESSION.json['library_albumview_sort'] : headerText += SESSION.json['library_tagview_sort'];
		$('.view-recents span').hide();
		$('.view-all span').hide();

		if (GLOBAL.musicScope == 'recent') {
			$('.view-recents span').show();
	        LIB.recentlyAddedClicked = true;
			headerText = 'Added in last ' + getParamOrValue('param', SESSION.json['library_recently_added']).toLowerCase();
		}
        else {
			$('.view-all span').show();
	        LIB.recentlyAddedClicked = false;
		}

        // Set the header text
        if (LIB.filters.genres.length) {
			headerText = 'Browse ' + LIB.filters.genres[0];
		}
		if (LIB.filters.artists.length) {
			headerText = 'Albums by ' + LIB.filters.artists[0];
		}
        if (SESSION.json['library_flatlist_filter'] != 'full_lib') {
            var filterCapitilized = SESSION.json['library_flatlist_filter'].charAt(0).toUpperCase() + SESSION.json['library_flatlist_filter'].slice(1);
            // Advanced search
            if (SESSION.json['library_flatlist_filter'] == 'tags') {
                headerText = 'Filtered by Advanced search';
            }
            // Lossless or Lossy
            else if (GLOBAL.oneArgFilters.includes(SESSION.json['library_flatlist_filter'])) {
                headerText = 'Filtered by (' + filterCapitilized + ')';
            }
            // Two arg filter with emoty second arg
            else if (GLOBAL.twoArgFilters.includes(SESSION.json['library_flatlist_filter']) && SESSION.json['library_flatlist_filter_str'] == '') {
                headerText = 'Filtered by Any: ' + filterCapitilized;
            }
            // Two arg filter with second arg
            else {
                headerText = 'Filtered by '+ filterCapitilized + ': ' + SESSION.json['library_flatlist_filter_str'];
            }
        }
	}
    else if (currentView == 'playlist') {
		headerText += 'Playlists';
		if (GLOBAL.searchPlaylist) {
			headerText = GLOBAL.searchPlaylist;
		}
	}

    $('#menu-header').text(headerText);
}

function lazyLode(view, skip, force) {
    //const startTime = performance.now();
	//console.log(view);
    // If browser does not support native lazy load then fall back to JQuery lazy load
    if (!GLOBAL.nativeLazyLoad) {
 		var container, selector;
		skip = skip ? true : false; // skip_invisible

 		switch (view) {
 			case 'radio':
 				selector = 'img.lazy-radioview';
 				container = '#radio-covers';
 				break;
 			case 'tag':
 				if (SESSION.json['library_tagview_covers'] == 'Yes') {
     				selector = 'img.lazy-tagview';
     				container = '#lib-album';
					//skip = true;
                }
 				break;
 			case 'album':
 				selector = 'img.lazy-albumview';
 				container = '#lib-albumcover';
				//skip = true;
				break;
            case 'playlist':
 				selector = 'img.lazy-playlistview';
 				container = '#playlist-covers';
 				break;
		 	case 'playqueue':
				selector = 'img.lazy-playqueue';
				container = '#playqueue';
				break;
            case 'cv-playqueue':
				selector = 'img.lazy-playqueue';
				container = '#cv-playqueue';
				break;
 		}

        if (selector && container) {
			if (!$(container + ' ' + selector).attr('src') || force) {
				$.ensure(container + ' li').then(function(){
					$(container + ' ' + selector).lazyload({
						container: $(container),
						skip_invisible: skip
					});
					if (UI.libPos[1] >= 0 && currentView == 'album') {
						customScroll('albumcovers', UI.libPos[1], 0);
						$('#albumcovers .lib-entry').eq(UI.libPos[1]).addClass('active');
					}
					if (UI.libPos[0] >= 0 && currentView == 'tag') {
						customScroll('albums', UI.libPos[0], 0);
						$('#albumsList .lib-entry').eq(UI.libPos[0]).addClass('active');
	    				$('#albumsList .lib-entry').eq(UI.libPos[0]).click();
					}
					if (UI.radioPos >= 0 && currentView == 'radio') {
                        customScroll('radio', UI.radioPos, 0);
                    }
                    if (UI.playlistPos >= 0 && currentView == 'playlist') {
                        customScroll('playlist', UI.playlistPos, 0);
                    }
				});
	        }
		}
 	}
	//const duration = performance.now() - startTime;
    //console.log(duration + 'ms');
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

    $.post('command/cfg-table.php?cmd=upd_cfg_system', {'volmute': SESSION.json['volmute']}, function() {
        setVolume(newVol, volEvent);
    });
}

function submitLibraryUpdate (path = '') {
    if (GLOBAL.libLoading == false) {
        GLOBAL.libLoading = true;
        GLOBAL.libRendered = false;
        $.getJSON('command/music-library.php?cmd=update_library', {'path': path}, function(data) {
            //console.log(data);
        });
        notify('update_library', path);
    }
    else {
        notify('library_updating');
    }
}

function getThumbHW() {
	var cols = SESSION.json['library_thumbnail_columns'].slice(0,1);
	if (UI.mobile) cols -= 4;
	var divM = Math.round(2 * convertRem(1.5)); // 1.5rem l/r margin for div
	var columnW = parseInt(($(window).width() - (2 * GLOBAL.sbw) - divM) / cols);
	UI.thumbHW = columnW - (divM / 2);
	$("body").get(0).style.setProperty("--thumbimagesize", UI.thumbHW + 'px');
	$("body").get(0).style.setProperty("--thumbmargin", ((columnW - UI.thumbHW) / 2) + 'px');
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

// jquery.ensure.js - https://stackoverflow.com/a/48191803 - Matheus Dal'Pizzol
$.ensure = function (selector) {
    //const startTime = performance.now();
    var promise = $.Deferred();
    var interval = setInterval(function () {
        if ($(selector)[0]) {
            clearInterval(interval);
            promise.resolve();
        }
    }, 1);
	//const duration = performance.now() - startTime;
    //console.log(duration + 'ms');
    return promise;
};

function applyLibFilter(filterType, filterStr = '') {
    //console.log(filterType, filterStr);
    SESSION.json['library_flatlist_filter'] = filterType;
    SESSION.json['library_flatlist_filter_str'] = filterStr;

    // Clear filtered libcache files (_folder, _format, _tag)
    $.post('command/music-library.php?cmd=clear_libcache_filtered', function() {
        // Apply new filter
        $.post('command/cfg-table.php?cmd=upd_cfg_system',
            {'library_flatlist_filter': filterType,
            'library_flatlist_filter_str': SESSION.json['library_flatlist_filter_str']},
            function() {
            LIB.recentlyAddedClicked = false;
            LIB.filters.genres.length = 0;
        	LIB.filters.artists.length = 0;
        	LIB.filters.albums.length = 0;
    		LIB.artistClicked = false;
            LIB.albumClicked = false;
            $('#tracklist-toggle').html('<i class="fal fa-list sx"></i> Show tracks');
			GLOBAL.musicScope = 'all';

    		if (currentView == 'album') {
    			$('#albumcovers .lib-entry').removeClass('active');
    			$('#bottom-row').css('display', '');
    			$('#lib-albumcover').css('height', '100%');
    		}

            UI.libPos.fill(-2);
            storeLibPos(UI.libPos);

            GLOBAL.libRendered = false;
            loadLibrary();
        });
    });
}

function splitStringAtFirstSpace (str) {
    strArray = [];

    if (str.indexOf(' ') == -1) {
        strArray[0] = str;
    }
    else {
        strArray[0] = str.substr(0, str.indexOf(' '));
        strArray[1] = str.substr(str.indexOf(' ') + 1);
    }

    return strArray;
}

// For menu item in header.php
function audioPlayback() {
    var cmd = MPD.json['artist'] == 'Radio station' ? 'station_info' : 'track_info';
    audioInfo(cmd, MPD.json['file'], 'playback');
}
// Track/Station/Playback info
// cmd = info type, path = song file path, tab = 'playback' for m menu Audio info
function audioInfo(cmd, path, activeTab = '') {
	$('#audioinfo-modal .modal-body').load('audioinfo.php', function() {
        var tabText = cmd == 'station_info' ? 'Station' : 'Track';
        var className = activeTab == 'playback' ? 'playback' : 'track';

        // Display Playback info (no tabs) when launched from configs
        var value = GLOBAL.scriptSection == 'configs' ? 'none' : 'flex';
        $('#audioinfo-tabs').css('display', value);

	    $.getJSON('command/audioinfo.php?cmd=' + cmd, {'path': path}, function(data) {
			itemInfoModal('trackdata', data);
			$('#audioinfo-track').text(tabText);
			$('#audioinfo-modal').removeClass('track playback');
			$('#audioinfo-modal').addClass(className);
			$('#audioinfo-modal').modal('show');
	    });
	});
}

// Item metadata: id = div id in audioinfo.html, data = metadata
function itemInfoModal(id, data) {
    var lines = '';

    for (i = 0; i < data.length; i++) {
        var key = Object.keys(data[i]);
        if (typeof(data[i][key]) != 'undefined') {
            if (key == 'Covers') { // Note: data[i]['Covers'] is the md5 hash or ''
                var coverUrl = data[i][key] = '' ? '/var/www/images/notfound.jpg' : '/imagesw/thmcache/' + encodeURIComponent(data[i][key]) + '.jpg';
                lines += '<li><span class="left">' + key + '</span><span class="ralign">' + '<img src="' + coverUrl + '" style="width:60px;"' + '</span></li>';
            } else if (key == 'Logo') {
                lines += '<li><span class="left">' + key + '</span><span class="ralign">' + '<img src="' + data[i][key] + '" style="width:60px;"' + '</span></li>';
            } else if (key == 'Home page') {
                if (data[i][key].length > 0) {
                    lines += '<li><span class="left">' + key + '</span><span class="ralign">' + data[i][key] + '</span></li>';
                }
            } else if (key == 'Comment') {
                lines += '<li><span class="left">' + key + '</span><br><span>' + data[i][key] + '</span></li>';
            } else {
                lines += '<li><span class="left">' + key + '</span><span class="ralign">' + data[i][key] + '</span></li>';
            }
        }
    }

    document.getElementById(id).innerHTML = lines;
}

// Now-playing icon
function setNpIcon() {
    if (SESSION.json['show_npicon'] == 'Yes') {
        if (typeof(MPD.json['song']) != 'undefined') {
            $('.playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').removeClass('no-npicon');
            $('.cv-playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').removeClass('no-npicon');
        }
        // Highlight track in Library
        $('#songsList .lib-entry-song .songtrack').removeClass('lib-track-highlight');
        if (MPD.json['artist'] != 'Radio station' && $('#songsList li').length > 0) {
            for (i = 0; i < filteredSongs.length; i++) {
                if (filteredSongs[i].title == MPD.json['title'] && filteredSongs[i].album == MPD.json['album']) {
                    $('#lib-song-' + (i + 1) + ' .lib-entry-song .songtrack').addClass('lib-track-highlight');
                    break;
                }
            }
        }
    }
    else {
        if (typeof(MPD.json['song']) != 'undefined') {
            $('.playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('no-npicon');
            $('.cv-playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('no-npicon');
        }
        $('#songsList .lib-entry-song .songtrack').removeClass('lib-track-highlight');
    }
}

// Receivers modal
function updateRxVolDisplay(selector, value) {
    selector = selector.slice(0, -7); // Remove '-slider' leaving ...-vol
    $('#' + selector).text(value);
}
