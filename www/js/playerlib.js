/*!
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

// Features availability bitmask
const FEAT_HTTPS        = 1;        // y HTTPS mode
const FEAT_AIRPLAY      = 2;        // y AirPlay renderer
const FEAT_MINIDLNA     = 4;        // y DLNA server
const FEAT_RECORDER     = 8;        //   Stream recorder
const FEAT_SQUEEZELITE  = 16;       // y Squeezelite renderer
const FEAT_UPMPDCLI     = 32;       // y UPnP client for MPD
const FEAT_SQSHCHK      = 64;       //   Require squashfs for software update
const FEAT_ROONBRIDGE   = 128;		// y RoonBridge renderer
const FEAT_LOCALDISPLAY = 256;      // y Local display
const FEAT_INPSOURCE    = 512;      // y Input source select
const FEAT_UPNPSYNC     = 1024;     //   UPnP volume sync
const FEAT_SPOTIFY      = 2048;     // y Spotify Connect renderer
const FEAT_GPIO         = 4096;     // y GPIO button handler
const FEAT_PLEXAMP      = 8192;     // y Plexamp renderer
const FEAT_BLUETOOTH    = 16384;    // y Bluetooth renderer
const FEAT_DEVTWEAKS    = 32768;	//   Developer tweaks
const FEAT_MULTIROOM    = 65536;	// y Multiroom audio
//						-------
//						  97207

// Notifications
const NOTIFY_TITLE_INFO = '<i class="fa fa-solid fa-sharp fa-circle-check" style="color:#27ae60;"></i> Info';
const NOTIFY_TITLE_ALERT = '<i class="fa fa-solid fa-sharp fa-circle-xmark" style="color:#e74c3c;"></i> Alert';
const NOTIFY_TITLE_ERROR = '<i class="fa fa-solid fa-sharp fa-do-not-enter" style="color:#e74c3c;"></i> Error';
const NOTIFY_DURATION_SHORT = 2; // Seconds
const NOTIFY_DURATION_DEFAULT = 5;
const NOTIFY_DURATION_MEDIUM = 10;
const NOTIFY_DURATION_LONG = 30;
const NOTIFY_DURATION_INFINITE = 8640000; // 100 days
const NOTIFY_MSG_NO_USERID = 'Without a userid moOde will not function correctly.<br><br>'
    + 'Follow the '
    + '<a href="https://github.com/moode-player/docs/blob/main/setup_guide.md#4-imager-tutorial"'
    + ' class="target-blank-link" target="_blank">Imager Tutorial</a>'
    + ' to create a new image with a userid, password and SSH enabled.';
const NOTIFY_MSG_WELCOME = 'View <span class="context-menu">'
    + ' <a href="#notarget" data-cmd="quickhelp">Quick help </a></span>'
    + 'for information on using the WebUI, configuring audio devices and setting up the Library.<br>'
    + 'Quick help is also available on the Main menu which is accessed '
    + 'via the "m" icon in the upper right.<br><br>'
    + ' Read the <a href="https://moodeaudio.org/forum/forumdisplay.php?fid=17"'
    + ' class="target-blank-link" target="_blank">Release Announcement</a>'
    + ' for any special instructions or patches for this release.';

// System
const NO_USERID_DEFINED = 'userid does not exist';

// Timeouts in milliseconds
const DEFAULT_TIMEOUT   = 250;
const CLRPLAY_TIMEOUT   = 500;
const LAZYLOAD_TIMEOUT  = 1000;
const SEARCH_TIMEOUT    = 750;
const RALBUM_TIMEOUT    = 500;
const ENGINE_TIMEOUT    = 3000;
const CV_QUEUE_TIMEOUT  = 60000;

// Album and Radio HD parameters
const ALBUM_HD_BADGE_TEXT           = 'HD';
const ALBUM_BIT_DEPTH_THRESHOLD     = 16;
const ALBUM_SAMPLE_RATE_THRESHOLD   = 44100;
const RADIO_HD_BADGE_TEXT           = 'HiRes';
const RADIO_BITRATE_THRESHOLD       = 128;

// For legacy Radio Manager station export
const STATION_EXPORT_DIR = '/'; // var/www

// Library saved searches
const LIB_FULL_LIBRARY = 'Full Library (Default)';

// Library mount types
const LIB_MOUNT_TYPE_SMB = 'cifs';
const LIB_MOUNT_TYPE_NFS = 'nfs';
const LIB_MOUNT_TYPE_NVME = 'nvme';

// Default titles and covers
const DEFAULT_RADIO_TITLE = 'Radio station';
const DEFAULT_RADIO_COVER = 'images/default-album-cover.png';
const DEFAULT_ALBUM_COVER = 'images/default-album-cover.png';
const DEFAULT_UPNP_COVER = 'images/default-upnp-cover.jpg';
const DEFAULT_PLAYLIST_COVER = '/var/www/images/default-playlist-cover.jpg';
const DEFAULT_NOTFOUND_COVER = '/var/www/images/default-notfound-cover.jpg';

var UI = {
    knob: null,
    path: '',
	restart: '',
	currentFile: 'blank',
	currentHash: 'blank',
	currentSongId: 'blank',
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
    radioPos: -1,
    folderPos: -1,
	libPos: [-1,-1,-1],
    // [0]: Album list pos (tag view)
    // [1]: Album cover pos (album view)
    // [2]: Artist list pos (tag view)
    // Special values for [0] and [1]: -1 = full lib displayed, -2 = lib headers clicked, -3 = search performed
    playlistPos: -1,
	libAlbum: '',
	mobile: false,
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
// SSID's (cfg_ssid table)
var SSID = {
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
    cvQueueTimer: '',
    pqActionClicked: false,
    mpdMaxVolume: 0,
    lastTimeCount: 0,
    editStationId: '',
    nativeLazyLoad: false,
    playQueueChanged: false,
    playQueueLength: 0,
	initTime: 0,
    searchOperators: ['==', '!=', '=~', '!~'],
    oneArgFilters: ['full_lib', 'hdonly', 'lossless', 'lossy'],
    twoArgFilters: ['album', 'albumartist', 'any', 'artist', 'composer', 'conductor', 'encoded', 'file', 'folder', 'format', 'genre', 'label', 'performer', 'title', 'work', 'year'],
    allFilters: [],
    sbw: 0,
    backupCreate: false,
    busySpinnerSVG: "<svg xmlns='http://www.w3.org/2000/svg' width='42' height='42' viewBox='0 0 42 42' stroke='#fff'><g fill='none' fill-rule='evenodd'><g transform='translate(3 3)' stroke-width='4'><circle stroke-opacity='.35' cx='18' cy='18' r='18'/><path d='M36 18c0-9.94-8.06-18-18-18'><animateTransform attributeName='transform' type='rotate' from='0 18 18' to='360 18 18' dur='1s' repeatCount='indefinite'/></path></g></g></svg>",
    thisClientIP: '',
    chromium: false,
    ssClockIntervalID: '',
    reconnecting: false,
    searchTags: ['genre', 'artist', 'album', 'title', 'albumartist', 'date',
        'composer', 'conductor', 'performer', 'work', 'comment', 'file'],
    npIcon: '',
    coverViewActive: false,
    userAgent: ''
};

// All Library filters
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

// Detect chromium-browser
GLOBAL.userAgent = navigator.userAgent;
GLOBAL.userAgent.indexOf('CrOS') != -1 ? GLOBAL.chromium = true : GLOBAL.chromium = false;

function debugLog(msg) {
	if (SESSION.json['debuglog'] == '1') {
		console.log(Date.now() + ': ' + msg);
	}
}

// MPD commands
function sendMpdCmd(cmd, async) {
	if (typeof(async) === 'undefined') {async = true;}
    //console.log(cmd);

    if (cmd.includes('play') || cmd == 'pause' || cmd == 'stop') {
        $('.play').addClass('active');
    } else if (cmd == 'next' || cmd == 'previous') {
        $('.' + cmd.substring(0, 4)).addClass('active');
    }

	$.ajax({
		type: 'GET',
		url: 'command/index.php?cmd=' + cmd,
		async: async,
		cache: false,
		success: function(data) {
            //console.log(data);
		}
    });
}

// Specifically for volume
function sendVolCmd(type, cmd, data, async) {
	if (typeof(data) === 'undefined') {data = '';}
	if (typeof(async) === 'undefined') {async = true;}

    if (data['event'] == 'mute' || data['event'] == 'unmute') {
        $('.volume-display').addClass('active');
    } else if (data['event'] == 'volume_up') {
        $('#volumeup').addClass('active');
    } else if (data['event'] == 'volume_down') {
        $('#volumedn').addClass('active');
    }

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

                // MPD restarted by watchdog, worker, a2dp-autoconnect, manually via cli, etc
				if (MPD.json['idle_timeout_event'] === '') {
					// NOP
				} else {
    				if (UI.hideReconnect === true) {
    					hideReconnect();
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
                }

    			engineMpd();
			}
			// Error of some sort
			else {
				debugLog('engineMpd(): success branch: error=(' + MPD.json['error'] + '), module=(' + MPD.json['module'] + ')');

				// JSON parse errors @ohinckel https: //github.com/moode-player/moode/pull/14/files
				if (typeof(MPD.json['error']) == 'object') {
                    var errorCode = typeof(MPD.json['error']['code']) === 'undefined' ? '' : ' (' + MPD.json['error']['code'] + ')';
                    // These particular errors occur when front-end is simply trying to reconnect
                    if (MPD.json['error']['message'] == 'JSON Parse error: Unexpected EOF' ||
                        MPD.json['error']['message'] == 'Unexpected end of JSON input') {
                        if (!GLOBAL.reconnecting) {
                            notify(NOTIFY_TITLE_INFO, 'reconnect', NOTIFY_DURATION_INFINITE);
                            GLOBAL.reconnecting = true;
                        }
                    } else {
                        notify(NOTIFY_TITLE_ALERT, 'mpd_error', MPD.json['error']['message'] + errorCode);
                    }
				}
				// MPD output -> Bluetooth but no actual BT connection
				else if (MPD.json['error'] == 'Failed to open "ALSA bluetooth" (alsa); Failed to open ALSA device "btstream": No such device') {
                    notify(NOTIFY_TITLE_ALERT, 'mpd_error', 'Output is set to Bluetooth speaker but there is no connection or configured device.');
				}
				// Client connects before MPD started by worker ?
				else if (MPD.json['error'] == 'SyntaxError: JSON Parse error: Unexpected EOF') {
					notify(NOTIFY_TITLE_ALERT, 'mpd_error', 'JSON Parse error: Unexpected EOF.');
				}
				// MPD bug may have been fixed in 0.20.20 ?
				else if (MPD.json['error'] == 'Not seekable') {
					// NOP
				}
				// Other MPD or network errors
				else {
                    if (MPD.json['error'].includes('Unknown error 524') && MPD.json['error'].includes('Failed to open ALSA device')) {
                        // 'Failed to open "ALSA Default" (alsa); Failed to open ALSA device "_audioout": Unknown error 524'
                        var msg = 'Output is set to HDMI but no audio device was detected on the HDMI port.';
                    } else {
                        var msg = MPD.json['error'];
                    }
                    notify(NOTIFY_TITLE_ALERT, 'mpd_error', msg);
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
			debugLog('engineMpdLite(): success branch: data=(' + data + ')');

			// Always have valid json
			try {
				MPD.json = JSON.parse(data);
			}
			catch (e) {
				MPD.json['error'] = e;
			}

			if (typeof(MPD.json['error']) === 'undefined') {
				//console.log('engineMpdLite: idle_timeout_event=(' + MPD.json['idle_timeout_event'] + ')', 'state', MPD.json['state']);

                // MPD restarted by watchdog, worker, a2dp-autoconnect, manually via cli, etc
				if (MPD.json['idle_timeout_event'] === '') {
					// NOP
				} else {
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
                }

				engineMpdLite();
			}
			// Error of some sort
			else {
				setTimeout(function(data) {
					// Client connects before MPD started by worker, various other issues
					debugLog('engineMpdLite(): success branch: error=(' + MPD.json['error'] + '), module=(' + MPD.json['module'] + ')');

                    // TEST: Show reconnect overlay when on configs
                    if (typeof(data) !== 'undefined') {
                        if (data['statusText'] == 'error' && data['readyState'] == 0) {
        			        renderReconnect();
        				}
                    }

    				MPD.json['state'] = 'reconnect';

                    if (typeof(MPD.json['error']) == 'object') {
                        var errorCode = typeof(MPD.json['error']['code']) === 'undefined' ? '' : ' (' + MPD.json['error']['code'] + ')';
                        // These particular errors occur when front-end is simply trying to reconnect
                        if (MPD.json['error']['message'] == 'JSON Parse error: Unexpected EOF' ||
                            MPD.json['error']['message'] == 'Unexpected end of JSON input') {
                            if (!GLOBAL.reconnecting) {
                                notify(NOTIFY_TITLE_INFO, 'reconnect', NOTIFY_DURATION_INFINITE);
                                GLOBAL.reconnecting = true;
                            }
                        } else {
                            notify(NOTIFY_TITLE_ALERT, 'mpd_error', MPD.json['error']['message'] + errorCode);
                        }
    				}

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
                    var inputSourceName = typeof(cmd[1]) == 'undefined' ? 'Undefined' : cmd[1];
                    inpSrcIndicator(cmd[0],
                        '<span id="inpsrc-msg-text">' + inputSourceName +
                        ' Input Active: <button class="btn volume-popup-btn" data-toggle="modal"><i class="fa-regular fa-sharp fa-volume-up"></i></button><span id="inpsrc-preamp-volume"></span>' +
                        '</span>' +
                        '<a class="btn configure-renderer" href="inp-config.php">Input Select</a>' +
                        audioInfoBtn());
                    break;
                case 'btactive1':
                case 'btactive0':
                    inpSrcIndicator(cmd[0],
                        '<span id="inpsrc-msg-text">Bluetooth Active</span>' +
                        '<a class="btn configure-renderer" href="blu-config.php">Bluetooth Control</a>' +
                        receiversBtn() +
                        audioInfoBtn());
                    break;
                case 'aplactive1':
                case 'aplactive0':
    				inpSrcIndicator(cmd[0],
                        '<span id="inpsrc-msg-text">AirPlay Active</span>' +
                        '<button class="btn disconnect-renderer" data-job="airplaysvc">Disconnect</button>' +
                        receiversBtn() +
                        audioInfoBtn());
                    break;
                case 'spotactive1':
                case 'spotactive0':
    				inpSrcIndicator(cmd[0],
                        '<span id="inpsrc-msg-text">Spotify Active</span>' +
                        '<button class="btn spotify-renderer disconnect-spotify" data-job="spotifysvc"><i class="fa-regular fa-sharp fa-xmark"></i></button>' +
                        receiversBtn(cmd[0]) +
                        audioInfoBtn(cmd[0]) +
                        '<a id="inpsrc-spotify-refresh" class="btn spotify-renderer" href="javascript:refreshSpotmeta()"><i class="fa-regular fa-sharp fa-redo"></i></a>'
                    );
                    $('#inpsrc-spotmeta-refresh').html('');
                    break;
                case 'update_spotmeta':
                    updateSpotmeta(cmd[1]);
                    break;
                case 'slactive1':
                case 'slactive0':
    				inpSrcIndicator(cmd[0],
                        '<span id="inpsrc-msg-text">Squeezelite Active</span>' +
                        '<button class="btn turnoff-renderer" data-job="slsvc">Turn off</button>' +
                        audioInfoBtn());
                    break;
                case 'paactive1':
                case 'paactive0':
    				inpSrcIndicator(cmd[0],
                        '<span id="inpsrc-msg-text">Plexamp Active</span>' +
                        '<button class="btn turnoff-renderer" data-job="pasvc">Turn off</button>' +
                        audioInfoBtn());
                    break;
                case 'rbactive1':
                case 'rbactive0':
    				inpSrcIndicator(cmd[0],
                        '<span id="inpsrc-msg-text">RoonBridge Active</span>' +
                        '<button class="btn disconnect-renderer" data-job="rbrestart">Disconnect</button>' +
                        audioInfoBtn());
                    break;
                case 'rxactive1':
                case 'rxactive0':
                    inpSrcIndicator(cmd[0],
                        '<span id="inpsrc-msg-text">Multiroom Receiver On</span>' +
                        '<button class="btn turnoff-receiver" data-job="multiroom_rx">Turn off</button>' +
                        '<br><a class="btn configure-renderer" href="trx-config.php">Configure</a>' +
                        audioInfoBtn());
                    break;
                case 'scnactive1':
                case 'scnactive0':
    				screenSaver(cmd[0]);
                    break;
                case 'toggle_coverview1':
                case 'toggle_coverview0':
                    if (GLOBAL.chromium) {
                        screenSaver(cmd[0]);
                    }
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
                case 'cdsp_update_config':
                    notify(NOTIFY_TITLE_INFO, 'cdsp_update_config', cmd[1], NOTIFY_DURATION_DEFAULT);
                    break;
                case 'cdsp_config_updated':
                    if (typeof(cmd[1]) != 'undefined') {
                        SESSION.json['camilladsp'] = cmd[1];
                    }
                    break;
                case 'cdsp_config_update_failed':
                    notify(NOTIFY_TITLE_ALERT, 'cdsp_config_update_failed');
                    break;
                case 'recorder_tagged':
                    notify(NOTIFY_TITLE_INFO, 'recorder_tagged', cmd[1] + ' files tagged, updating library...', NOTIFY_DURATION_MEDIUM);
                    break;
                case 'recorder_nofiles':
                    notify(NOTIFY_TITLE_ALERT, 'recorder_nofiles');
                    break;
                case 'reset_view':
                case 'refresh_screen':
                    setTimeout(function() {
                        location.reload(true);
                    }, DEFAULT_TIMEOUT);
                    break;
                case 'close_notification':
                    $('.ui-pnotify-closer').click();
                    break;
                case 'reduce_fpm_pool':
                    // This functions as a dummy command which has the effect of
                    // causing engine-cmd.php to start releasing idle connections
                    console.log(cmd[0]);
                    break;
                default:
                    console.log('engineCmd(): ' + cmd[0]);
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

            switch (cmd[0]) {
                case 'libregen_done':
                    $('.busy-spinner').hide();
                    loadLibrary();
                    break;
                case 'nvme_formatting_drive':
                    notify(NOTIFY_TITLE_INFO, 'nvme_formatting_drive', NOTIFY_DURATION_INFINITE);
                    break;
                case 'cdsp_update_config':
                    notify(NOTIFY_TITLE_INFO, 'cdsp_update_config', cmd[1], NOTIFY_DURATION_INFINITE);
                    break;
                case 'cdsp_config_updated':
                    // NOP
                    break;
                case 'cdsp_config_update_failed':
                    notify(NOTIFY_TITLE_ALERT, 'cdsp_config_update_failed', NOTIFY_DURATION_MEDIUM);
                    break;
                case 'trx_discovering_receivers':
                    notify(NOTIFY_TITLE_INFO, 'trx_discovering_receivers', NOTIFY_DURATION_INFINITE);
                    break;
                case 'trx_configuring_sender':
                    notify(NOTIFY_TITLE_INFO, 'trx_configuring_sender', NOTIFY_DURATION_MEDIUM);
                    break;
                case 'trx_configuring_mpd':
                    notify(NOTIFY_TITLE_INFO, 'trx_configuring_mpd', NOTIFY_DURATION_DEFAULT);
                    break;
                case 'downgrading_chromium':
                    notify(NOTIFY_TITLE_INFO, 'downgrading_chromium', NOTIFY_DURATION_INFINITE);
                    break;
                case 'reset_view':
                case 'refresh_screen':
                    if (cmd[0] == 'reset_view') {
                        window.location.replace('/index.php');
                    }
                    setTimeout(function() {
                        location.reload(true);
                    }, DEFAULT_TIMEOUT);
                    break;
                case 'close_notification':
                    $('.ui-pnotify-closer').click();
                    break;
                case 'reduce_fpm_pool':
                    // This functions as a dummy command which has the effect of
                    // causing engine-cmd.php to start releasing idle connections
                    console.log(cmd[0]);
                    break;
                default:
                    console.log('engineCmdLite(): ' + cmd[0]);
                    break;
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
    $('#inpsrc-msg').removeClass('inpsrc-msg-spotify');
    $('#inpsrc-msg').addClass('inpsrc-msg-default');
    $('#inpsrc-spotmeta').html('');

    // Set custom backdrop (if any)
    if (SESSION.json['renderer_backdrop'] == 'Yes') {
        if (SESSION.json['cover_backdrop'] == 'Yes' && MPD.json['coverurl'].indexOf(DEFAULT_ALBUM_COVER) === -1) {
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

function refreshSpotmeta() {
    $.getJSON('command/renderer.php?cmd=get_spotmeta', function(data) {
        updateSpotmeta(data);
    });
}
function updateSpotmeta(data) {
    $('#inpsrc-msg').removeClass('inpsrc-msg-default');
    $('#inpsrc-msg').addClass('inpsrc-msg-spotify');
    $('#inpsrc-msg-text').text('');

    $('#inpsrc-backdrop').css('filter', 'blur(0px)');
    $('#inpsrc-backdrop').css('transform', 'scale(1.0)');

    // data = title;artists;album;duration;coverurl
    //DEBUG:console.log(data);
    var metadata = data.split(';');
    $('#inpsrc-backdrop').html('<img class="inpsrc-spotmeta-backdrop" ' + 'src="' + metadata[4] + '">');
    $('#inpsrc-spotmeta').html(
        metadata[0] + ' (' + formatSongTime(Math.round(parseInt(metadata[3]) / 1000)) + ')' +
        '<br>' + '<span>' + metadata[1] + '<br>' + metadata[2] + '</span>'
    );

    if (window.matchMedia("(orientation: portrait)").matches) {
        $('#inpsrc-spotmeta-refresh').html('');
    } else {
        $('#inpsrc-spotify-refresh').html('');
        $('#inpsrc-spotmeta-refresh').html('<a class="btn spotify-renderer" href="javascript:refreshSpotmeta()"><i class="fa-regular fa-sharp fa-redo"></i></a>');
    }
}

// Show/hide CoverView screen saver
function screenSaver(cmd) {
	if ($('#inpsrc-indicator').css('display') == 'block' || UI.mobile) {
        // Don't show CoverView
		return;
	} else if (cmd.slice(-1) == '1') {
        // Show CoverView
        GLOBAL.coverViewActive = true; // Reset in scripts-panels $('#screen-saver
        if (GLOBAL.chromium) {
            $.post('command/playback.php?cmd=upd_toggle_coverview', {'toggle_value': '-on'});
        }
        $('#ss-coverart-url').html($('#coverart-url').html());
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

        if (SESSION.json['scnsaver_mode'].includes('clock')) {
            $('#ss-coverart').css('display', 'none');
            $('#ss-clock').css('display', 'block');
            showSSClock();
        }
	} else if (cmd.slice(-1) == '0') {
        // Hide CoverView
        if (GLOBAL.chromium) {
            $.post('command/playback.php?cmd=upd_toggle_coverview', {'toggle_value': '-off'});
        }
        $('#screen-saver').click();
    }
}

function showSSClock() {
	switch (SESSION.json['scnsaver_mode']) {
		case 'Digital clock':
        case 'Digital clock (24-hour)':
            var showAMPM = SESSION.json['scnsaver_mode'] == 'Digital clock (24-hour)' ? false : true;
			showSSDigitalClock(showAMPM);
			GLOBAL.ssClockIntervalID = setInterval(showSSDigitalClock, 1000, showAMPM);
			break;
        // Analog clock functions are in analog-clock.js
		case 'Analog clock':
        case 'Analog clock (Sweep)':
            var showSweepSecondHand = SESSION.json['scnsaver_mode'] == 'Analog clock (Sweep)' ? true : false;
			showAnalogClock("ss-clock", ANALOGCLOCK_REFRESH_INTERVAL_SMOOTH, showSweepSecondHand);
			break;

		default: break;
	}
}

function hideSSClock() {
	switch (SESSION.json['scnsaver_mode']) {
		case 'Digital clock':
        case 'Digital clock (24-hour)':
			clearInterval(GLOBAL.ssClockIntervalID);
			$('#ss-clock').text('');
			break;
        // Analog clock functions are in analog-clock.js
		case 'Analog clock':
        case 'Analog clock (Sweep)':
			hideAnalogClock();
			break;

		default: break;
	}
}

// CoverView digital clock
function showSSDigitalClock(showAMPM = true) {
    var date = new Date();
    var h = date.getHours(); // 0 - 23
    var m = date.getMinutes(); // 0 - 59
    var s = date.getSeconds(); // 0 - 59

    var ampm = " AM";
    if (!showAMPM) {
        ampm = "";
    } else {
        if (h == 0) {
            h = 12;
        } else if (h > 12) {
            h = h - 12;
            ampm = " PM";
        }
    }

    h = (h < 10) ? "0" + h : h;
    m = (m < 10) ? "0" + m : m;
    s = (s < 10) ? "0" + s : s;

    var time = h + ':' + m + ':' + s + ampm;
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

    if (GLOBAL.scriptSection == 'panels') {
        $('#countdown-display').countdown('pause');
    }

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
	$('.volume-display div, #inpsrc-preamp-volume, #multiroom-receiver-volume, .mpd-volume-level').text('0dB');
	$('.volume-display').css('cursor', 'unset');

	if (UI.mobile) {
		$('.repeat').show();
	}
}

// When last item in Queue finishes just update a few things, called from engineCmd()
function resetPlayCtls() {
	//console.log('resetPlayCtls():');
	$('#m-total, #playbar-total, #playbar-mtotal').text(formatKnobTotal('0'));
	$('.play i').removeClass('fa-pause').addClass('fa-play');
	$('#total').html(formatKnobTotal('0'));
	$('.playqueue li.active ').removeClass('active');

	updKnobAndTimeTrack();
    updKnobStartFrom(0, MPD.json['state']);

	$('#countdown-display, #m-countdown, #playbar-countdown, #playbar-mcount').text('00:00');
    UI.mobile ? $('#playbar-mcount').css('display', 'block') : $('#playbar-mcount').css('display', 'none');

    $('#extra-tags-display, #ss-extra-metadata').text('Not playing');
    $('#countdown-sample-rate, #songsand-sample-rate').text('');
    $('#ss-extra-metadata-output-format').text('').removeClass('ss-npicon');
    $('#ss-countdown').text('');
}

function renderUIVol() {
	//console.log('renderUIVol()');

	// Load session vars (required for multi-client)
    $.getJSON('command/cfg-table.php?cmd=get_cfg_system', function(data) {
    	if (data === false) {
            console.log('renderUIVol(): No data returned from get_cfg_system');
    	} else {
            SESSION.json = data;
        }

        // Volume type
    	if (SESSION.json['mpdmixer'] == 'none') {
            // Fixed (0dB)
    		disableVolKnob();
    	} else {
            // Software, hardware or null (CamillaDSP)
            // If sync is enabled update knob volume for apps that set volume via MPD instead of vol.sh
    		if (SESSION.json['feat_bitmask'] & FEAT_UPNPSYNC) {
    			if (SESSION.json['btactive'] == '0' && SESSION.json['aplactive'] == '0' && SESSION.json['spotactive'] == '0'
                    && SESSION.json['slsvc'] == '0' && SESSION.json['paactive'] == '0' && SESSION.json['rbactive'] == '0') {
    				if ((SESSION.json['volknob'] != MPD.json['volume']) && SESSION.json['volmute'] == '0') {
    					SESSION.json['volknob'] = MPD.json['volume']
                        $.post('command/cfg-table.php?cmd=upd_cfg_system', {'volknob': SESSION.json['volknob']});
    				}
    			}
    		}

            // Hardware
            // Update MPD var for debug if needed
            if (SESSION.json['mpdmixer'] == 'hardware') {
                MPD.json['volume'] = SESSION.json['volknob'];
            }

    		// Update volume knobs
    		$('#volume').val(SESSION.json['volknob']).trigger('change');
    		$('.volume-display div, #inpsrc-preamp-volume, #multiroom-receiver-volume, .mpd-volume-level').text(SESSION.json['volknob']);
            $('.volume-display-db').text(SESSION.json['volume_db_display'] == '1' ? MPD.json['mapped_db_vol'] : '');
    		$('#volume-2').val(SESSION.json['volknob']).trigger('change');

    	   	// Update mute state
    		if (SESSION.json['volmute'] == '1') {
    			$('.volume-display div, #inpsrc-preamp-volume, #multiroom-receiver-volume').text('mute');
                $('.mpd-volume-level').text('');
                $('#playbar-volume-popup-btn i, .volume-popup-btn i').removeClass('fa-volume-off').addClass('fa-volume-xmark');
    		} else {
    			$('.volume-display div, .mpd-volume-level').text(SESSION.json['volknob']);
                $('#playbar-volume-popup-btn i, .volume-popup-btn i').removeClass('fa-volume-xmark').addClass('fa-volume-off');
    		}

            // Clear active state
            $('.volume-display, #volumeup, #volumedn').removeClass('active');
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

        // Debug notification (appears above cover art)
        // var debugText = GLOBAL.userAgent + '<br>' + (GLOBAL.chromium ? 'chromium=true' : 'chromium=false');
        var debugText = SESSION.json['debuglog'] == '1' ? 'Debug log on' : '';
        debugText += SESSION.json['xss_detect'] == 'on' ? ', XSS detect on' : '';
        if (debugText != '') {
            $('#debug-text').html('>> ' + debugText.replace(/^\,\ /, '') + ' <<');
        }

        // Volume type
    	if (SESSION.json['mpdmixer'] == 'none') {
            // Fixed (0dB)
    		disableVolKnob();
    	} else {
            // Software, hardware, null/CamillaDSP
            if (UI.mobile) {
                $('.volume-popup-btn').show();
            }

            // Update MPD var for debug if needed
            if (SESSION.json['mpdmixer'] == 'hardware') {
                MPD.json['volume'] = SESSION.json['volknob'];
            }

    		// Update volume knobs
    		$('#volume').val(SESSION.json['volknob']).trigger('change');
    		$('.volume-display div, #inpsrc-preamp-volume, #multiroom-receiver-volume, .mpd-volume-level').text(SESSION.json['volknob']);
            $('.volume-display-db').text(SESSION.json['volume_db_display'] == '1' ? MPD.json['mapped_db_vol'] : '');
    		$('#volume-2').val(SESSION.json['volknob']).trigger('change');

    	   	// Update mute state
    		if (SESSION.json['volmute'] == '1') {
    			$('.volume-display div, #inpsrc-preamp-volume, #multiroom-receiver-volume').text('mute');
                $('.mpd-volume-level').text('');
                $('#playbar-volume-popup-btn i, .volume-popup-btn i').removeClass('fa-volume-off').addClass('fa-volume-xmark');
    		} else {
    			$('.volume-display div, .mpd-volume-level').text(SESSION.json['volknob']);
                $('#playbar-volume-popup-btn i, .volume-popup-btn i').removeClass('fa-volume-xmark').addClass('fa-volume-off');
    		}
    	}

    	// Playback controls, Queue item highlight
        if (MPD.json['state'] == 'play') {
    		$('.play i').removeClass('fa-play').addClass('fa-pause');
    		$('.playqueue li.active, .cv-playqueue li.active').removeClass('active');
            $('.playqueue li.paused, .cv-playqueue li.paused').removeClass('paused');
            $('.playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('active');
            $('.cv-playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('active');
            setNpIcon();
        } else if (MPD.json['state'] == 'pause' || MPD.json['state'] == 'stop') {
    		$('.play i').removeClass('fa-pause').addClass('fa-play');
            if (typeof(MPD.json['song']) != 'undefined') {
                $('.playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('paused');
                $('.cv-playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('paused');
            }
            $('#songsList .lib-entry-song .songtrack').removeClass('lib-track-npicon');
        }
        $('.play, .next, .prev').removeClass('active');
    	$('#total').html(formatKnobTotal(MPD.json['time'] ? MPD.json['time'] : 0));
    	$('#m-total, #playbar-total').html(formatKnobTotal(MPD.json['time'] ? MPD.json['time'] : 0));
    	$('#playbar-mtotal').html('&nbsp;/&nbsp;' + formatKnobTotal(MPD.json['time']));
        $('#playbar-total').text().length > 5 ? $('#playbar-countdown, #m-countdown, #playbar-total, #m-total, #ss-countdown').addClass('long-time') :
            $('#playbar-countdown, #m-countdown, #playbar-total, #m-total, #ss-countdown').removeClass('long-time');

    	//console.log('CUR: ' + UI.currentHash);
    	//console.log('NEW: ' + MPD.json['cover_art_hash']);
    	// Compare new to current to prevent unnecessary image reloads
    	if (MPD.json['file'] !== UI.currentFile && MPD.json['cover_art_hash'] !== UI.currentHash) {
    		//console.log(MPD.json['coverurl']);
            // Standard cover for Playback
     		$('#coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'alt="Cover art not found"' + '>');
            // Thumbnail cover for Playbar
            if (MPD.json['file'] && MPD.json['coverurl'].indexOf('wimpmusic') == -1 && MPD.json['coverurl']) {
                var image_url = MPD.json['artist'] == 'Radio station' ?
                    MPD.json['coverurl'].replace('imagesw/radio-logos', 'imagesw/radio-logos/thumbs') :
                    '/imagesw/thmcache/' + encodeURIComponent(MPD.json['thumb_hash']) + '.jpg'
                $('#playbar-cover').html('<img src="' + image_url + '">');
            } else {
	     		$('#coverart-url').html('<img class="coverart" ' + 'src="' + DEFAULT_ALBUM_COVER + '" alt="Cover art not found"' + '>');
                $('#playbar-cover').html('<img src="' + DEFAULT_ALBUM_COVER + '">');
            }
    		// Cover backdrop or bgimage
    		if (SESSION.json['cover_backdrop'] == 'Yes') {
                var backDropHTML = MPD.json['coverurl'].indexOf(DEFAULT_ALBUM_COVER) === -1 ? '<img class="ss-backdrop" ' + 'src="' + MPD.json['coverurl'] + '">' : '';
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
    		if (GLOBAL.coverViewActive) {
    			$('#ss-coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'alt="Cover art not found"' + '>');
    		}
    	}

    	// Extra metadata displayed under the cover
        // (1) #countdown-sample-rate is displayed in the time knob on Ultrawide displays
        // (2) #songsand-sample-rate is displayed under the metadata in mobile portrait
    	if (MPD.json['state'] == 'stop') {
            // Radio station or end of Queue
    		$('#extra-tags-display, #ss-extra-metadata').text('Not playing');
            $('#countdown-sample-rate, #songsand-sample-rate').text('');
            $('#ss-extra-metadata-output-format').text('').removeClass('ss-npicon');
        } else if (MPD.json['state'] == 'pause') {
            // Track
            $('#extra-tags-display').text(formatExtraTagsString());
            $('#ss-extra-metadata-output-format, #countdown-sample-rate').text('Not playing');
            $('#ss-extra-metadata-output-format').removeClass('ss-npicon');
    	} else if (SESSION.json['extra_tags'].toLowerCase() == 'none' || SESSION.json['extra_tags'] == '') {
            // Play and no extra tags
            $('#extra-tags-display, #ss-extra-metadata').text('');
            $('#countdown-sample-rate').text('')
            $('#ss-extra-metadata-output-format').text('').removeClass('ss-npicon');
        } else {
            // Play
            if (MPD.json['artist'] == 'Radio station') {
                if (typeof(RADIO.json[MPD.json['file']]['format']) == 'undefined' ||
                    RADIO.json[MPD.json['file']]['format'] == '') {
                    var format = 'VBR';
                } else {
                    var format = RADIO.json[MPD.json['file']]['format'];
                }
                if (MPD.json['bitrate'] == '') {
                    var bitRate = format;
                } else {
                    var bitRate = format + ' ' + MPD.json['bitrate'];
                }
        		$('#extra-tags-display').text(bitRate + ' • ' + MPD.json['output']);
                $('#countdown-sample-rate, #songsand-sample-rate, #ss-extra-metadata').text(bitRate);
        	} else {
                $('#extra-tags-display').text(formatExtraTagsString());
                $('#ss-extra-metadata, #songsand-sample-rate').text(MPD.json['encoded']);
                $('#countdown-sample-rate').text(
                    (typeof(MPD.json['encoded']) === 'undefined' ? '' : MPD.json['encoded'].split(',')[0])
                );
        	}

            if (SESSION.json['show_npicon'] != 'None') {
                $('#ss-extra-metadata-output-format').text(MPD.json['output']).addClass('ss-npicon');
            }
        }

        // Default metadata
        if (MPD.json['artist'] == 'Radio station') {
            // For radio stations
            // - #currentalbum = ''
            // - #currentsong = MPD.json['title']
            // - #currentartist = MPD.json['album']
            // Playback
            $('#currentalbum-div').hide();
            $('#currentsong').html(genSearchUrl(MPD.json['artist'], MPD.json['title'], MPD.json['album']));
            $('#currentartist').html('<span class="playback-hd-badge"></span>' + MPD.json['album']);
            // Playbar
            $('#playbar-currentalbum').html('<span id="playbar-hd-badge"></span>' + (MPD.json['file'].indexOf('somafm') != -1 ?
                RADIO.json[MPD.json['file']]['name'] : MPD.json['album']));
            $('#playbar-currentsong').html(MPD.json['title']);
            // CoverView
            $('#ss-currentsong').text(MPD.json['title']);
            $('#ss-currentartist').text('');
            $('#ss-currentalbum').html('<span id="ss-hd-badge"></span>' + (MPD.json['file'].indexOf('somafm') != -1 ?
                RADIO.json[MPD.json['file']]['name'] : MPD.json['album']));
        } else {
            // For albums
            // - #currentalbum = MPD.json['album']
            // - #currentsong = MPD.json['title']
            // - #currentartist = MPD.json['artist']
            // Playback
            $('#currentalbum-div').show();
            $('#currentalbum').html('<span class="playback-hd-badge"></span>' + MPD.json['album']);
    		$('#currentsong').html(genSearchUrl(MPD.json['artist'] == 'Unknown artist' ? MPD.json['albumartist'] : MPD.json['artist'], MPD.json['title'], MPD.json['album']));
            $('#currentartist').html((MPD.json['artist'] == 'Unknown artist' ? MPD.json['albumartist'] : MPD.json['artist']));
            // Playbar and screen saver
            var artist = (MPD.json['artist'] == 'Unknown artist' ? MPD.json['albumartist'] : MPD.json['artist']);
            var dash = (typeof(artist) == 'undefined' || artist == '') ? '' : ' - ';
            // Playbar
            $('#playbar-currentsong').html(artist + dash + MPD.json['title']);
            $('#playbar-currentalbum').html('<span id="playbar-hd-badge"></span>' + MPD.json['album']);
            // CoverView
            if (SESSION.json['scnsaver_layout'] == 'Default') {
                $('#ss-currentsong').html(artist + dash + MPD.json['title']);
                $('#ss-currentartist').text('');
            } else {
                // Wide mode
                $('#ss-currentsong').html(MPD.json['title']);
                $('#ss-currentartist').text(artist);
            }
            $('#ss-currentalbum').html('<span id="ss-hd-badge"></span>' + MPD.json['album']);
        }

        // Set HD badge text
        if (MPD.json['artist'] == 'Radio station') {
            $('.playback-hd-badge, #playbar-hd-badge, #ss-hd-badge').text(RADIO_HD_BADGE_TEXT);
        } else {
            $('.playback-hd-badge, #playbar-hd-badge, #ss-hd-badge').text(albumHDBadge(MPD.json['audio_format']));
        }

        // Show/hide HD badge
        if (MPD.json['hidef'] == 'yes' &&
            SESSION.json['library_encoded_at'] &&
            SESSION.json['library_encoded_at'] != '9') {
            // Playback
            if (MPD.json['artist'] == 'Radio station') {
                $('#currentartist-div span.playback-hd-badge').show();
            } else {
                $('#currentalbum-div span.playback-hd-badge').show();
                $('#currentartist-div span.playback-hd-badge').hide();
            }
            // Playbar
            $('#playbar-hd-badge').show();
            // Screen saver
            if (SESSION.json['scnsaver_xmeta'] == 'Yes') {
                $('#ss-hd-badge').show();
            } else {
                $('#ss-hd-badge').hide();
            }
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
    			$('#songsList .lib-entry-song .songtrack').removeClass('lib-track-npicon');
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
            GLOBAL.playQueueChanged == true) {
            renderPlayqueue(MPD.json['state']);
        } else {
            updateActivePlayqueueItem();
        }

    	// Ensure renderer overlays get applied in case MPD UI updates get there first after browser refresh
        // Input source
    	if (SESSION.json['inpactive'] == '1') {
    		inpSrcIndicator('inpactive1',
                '<span id="inpsrc-msg-text">' + SESSION.json['audioin'] +
                ' Input Active: <button class="btn volume-popup-btn" data-toggle="modal"><i class="fa-regular fa-sharp fa-volume-up"></i></button><span id="inpsrc-preamp-volume"></span>' +
                '</span>' +
                '<a class="btn configure-renderer" href="inp-config.php">Input Select</a>' +
                audioInfoBtn());
    	}
    	// Bluetooth renderer
    	if (SESSION.json['btactive'] == '1') {
    		inpSrcIndicator('btactive1',
            '<span id="inpsrc-msg-text">Bluetooth Active</span>' +
            '<a class="btn configure-renderer" href="blu-config.php">Bluetooth Control</a>' +
            receiversBtn() +
            audioInfoBtn());
     	}
    	// AirPlay renderer
    	if (SESSION.json['aplactive'] == '1') {
    		inpSrcIndicator('aplactive1',
            '<span id="inpsrc-msg-text">AirPlay Active</span>' +
            '<button class="btn disconnect-renderer" data-job="airplaysvc">Disconnect</button>' +
            receiversBtn() +
            audioInfoBtn());
    	}
    	// Spotify renderer
    	if (SESSION.json['spotactive'] == '1') {
            inpSrcIndicator('spotactive1',
                '<span id="inpsrc-msg-text">Spotify Active</span>' +
                '<button class="btn spotify-renderer disconnect-spotify" data-job="spotifysvc"><i class="fa-regular fa-sharp fa-xmark"></i></button>' +
                receiversBtn('spotactive1') +
                audioInfoBtn('spotactive1') +
                '<a id="inpsrc-spotify-refresh" class="btn spotify-renderer" href="javascript:refreshSpotmeta()"><i class="fa-regular fa-sharp fa-redo"></i></a>'
            );

            refreshSpotmeta();
    	}
    	// Squeezelite renderer
    	if (SESSION.json['slactive'] == '1') {
    		inpSrcIndicator('slactive1',
            '<span id="inpsrc-msg-text">Squeezelite Active</span>' +
            '<button class="btn turnoff-renderer" data-job="slsvc">Turn off</button>' +
            audioInfoBtn());
    	}
        // Plexamp renderer
    	if (SESSION.json['paactive'] == '1') {
    		inpSrcIndicator('paactive1',
            '<span id="inpsrc-msg-text">Plexamp Active</span>' +
            '<button class="btn turnoff-renderer" data-job="pasvc">Turn off</button>' +
            audioInfoBtn());
    	}
        // RoonBridge renderer
    	if (SESSION.json['rbactive'] == '1') {
    		inpSrcIndicator('rbactive1',
            '<span id="inpsrc-msg-text">RoonBridge Active</span>' +
            '<button class="btn disconnect-renderer" data-job="rbrestart">Disconnect</button>' +
            audioInfoBtn());
    	}
        // Multiroom receiver
    	if (SESSION.json['rxactive'] == '1') {
            inpSrcIndicator('rxactive1',
                '<span id="inpsrc-msg-text">Multiroom Receiver On</span>' +
                '<button class="btn turnoff-receiver" data-job="multiroom_rx">Turn off</button>' +
                '<br><a class="btn configure-renderer" href="trx-config.php">Configure</a>' +
                audioInfoBtn());
    	}

    	// MPD database update
    	if (typeof(MPD.json['updating_db']) != 'undefined') {
    		$('.busy-spinner').show();
    	} else {
    		$('.busy-spinner').hide();
    	}
    });
}

// Renderer overlay buttons
// Multiroom receivers
function receiversBtn(rendererActive = '') {
    if (SESSION.json['multiroom_tx'] == 'On') {
        if (rendererActive == 'spotactive1') {
            // data-cmd: multiroom_rx_modal (full modal), multiroom_rx_modal_limited (just the on/off checkbox)
            var html = '<span class="context-menu"><a class="btn spotify-renderer" href="#notarget" data-cmd="multiroom_rx_modal"><i class="fa-regular fa-sharp fa-speakers"></i></a></span>';
        } else {
            var html = '<br><span class="context-menu"><a class="btn configure-renderer" href="#notarget" data-cmd="multiroom_rx_modal">Receivers</a></span>';
        }
    } else {
        var html = '';
    }

    return html;
}
// Audio info
function audioInfoBtn(rendererActive = '') {
    if (rendererActive == 'spotactive1') {
        var html = '<span><a class="btn spotify-renderer" href="javascript:audioInfoPlayback()"><i class="fa-regular fa-sharp fa-music"></i></a></span>';
    } else {
        var html = '<br><span><a class="btn audioinfo-renderer" href="javascript:audioInfoPlayback()">Audio info</a></span>';
    }

    return html;
}

// Generate search url
function genSearchUrl (artist, title, album) {
    // Search disabled by user
    if (SESSION.json['search_site'] == 'Disabled') {
        var returnStr = title;
    }
    // Title has no searchable info or mobile
    else if (MPD.json['coverurl'] === DEFAULT_ALBUM_COVER || UI.mobile) {
        var returnStr = MPD.json['title'];
    }
    // Station does not transmit title
    else if (title == DEFAULT_RADIO_TITLE) {
        if (RADIO.json[MPD.json['file']]['home_page'] != '') {
            var returnStr =  '<a id="coverart-link" class="target-blank-link" href=' + '"' + RADIO.json[MPD.json['file']]['home_page'] + '"' + ' target="_blank">'+ title + '</a>';
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
            case 'Amazon Music':
                var searchEngine = 'https://music.amazon.com/search/';
                break;
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
            case 'Spotify':
                var searchEngine = 'https://open.spotify.com/search/';
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

        var returnStr =  '<a id="coverart-link" class="target-blank-link" href=' + '"' + searchEngine + searchStr + '"' + ' target="_blank">'+ title + '</a>';
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
    	                if (typeof(data[i].Title) === 'undefined' ||
                            data[i].Title.trim() == '-' || // NTS can return just a dash in its Title tag
                            data[i].Title.substring(0, 4) == 'BBC ' || // BBC just returns the station name in the Title tag
                            data[i].Title.trim() == '') {
                            // Use default title
    						$('#pq-' + (parseInt(MPD.json['song']) + 1).toString() + ' .pll1').html(DEFAULT_RADIO_TITLE);
    					} else {
                            // Use station supplied title
                            $('#pq-' + (parseInt(MPD.json['song']) + 1).toString() + ' .pll1').html(data[i].Title);
    						if (i == parseInt(MPD.json['song'])) { // active
    							if (data[i].Title.substr(0, 4) === 'http' || MPD.json['coverurl'] === DEFAULT_ALBUM_COVER || UI.mobile) {
                                    // Update in case MPD did not get Title tag at initial play
    								$('#currentsong').html(data[i].Title);
    							} else {
                                    // Add search URL, see corresponding code in renderUI()
    								$('#currentsong').html(genSearchUrl(data[i].Artist, data[i].Title, data[i].Album));
    							}
                                // CoverView and Playbar
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

// DEBUG:
//var seqNum = 0;

// Render the Queue
function renderPlayqueue(state) {
	//console.log('renderPlayqueue()');
    $.getJSON('command/queue.php?cmd=get_playqueue', function(data) {
        //console.log(data);
		var output = '';
        var playqueueLazy = GLOBAL.nativeLazyLoad === true ? '<img loading="lazy" src=' : '<img class="lazy-playqueue" data-original=';
        var pausedClass = state != 'play' ? ' paused' : '';
        var noNpIconClass = SESSION.json['show_npicon'] != 'None' ? '' : ' no-npicon';

        // Save for use in delete/move modals
        GLOBAL.playQueueLength = typeof(data.length) === 'undefined' ? 0 : data.length;
        // DEBUG:
        //console.log('renderPlayqueue(' + seqNum++ + '): GLOBAL.playQueueLength: ' + GLOBAL.playQueueLength);
		var showPlayqueueThumb = SESSION.json['playlist_art'] == 'Yes' ? true : false;

		// Format playlist items
        if (data) {
            for (i = 0; i < data.length; i++) {
	            // Item highlight
	            if (i == parseInt(MPD.json['song'])) {
	                output += '<li id="pq-' + (i + 1) + '" class="active playqueue-entry' + pausedClass + noNpIconClass + '">';
	            } else {
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
				} else if (typeof(data[i].Name) !== 'undefined' || (data[i].file.substr(0, 4) == 'http' && typeof(data[i].Artist) === 'undefined' && typeof(data[i].Comment) === 'undefined')) {
                    // Radio station
                    var logoThumb = typeof(RADIO.json[data[i].file]) === 'undefined' ? '"' + DEFAULT_RADIO_COVER + '"' : '"imagesw/radio-logos/thumbs/' +
                        encodeURIComponent(RADIO.json[data[i].file]['name']) + '_sm.jpg"';
					output += showPlayqueueThumb && (typeof(data[i].Comment) === 'undefined' || data[i].Comment !== 'client=upmpdcli;')  ?
                        '<span class="playqueue-thumb">' + playqueueLazy + logoThumb + '></span>' : '';
	                // Line 1 title
                    // NOTE: See updateActivePlayqueueItem() and enhanceMetadata() for Title tag matching code
	                if (typeof(data[i].Title) === 'undefined' ||
                        data[i].Title.trim() == '-' || // NTS can return just a dash in its Title tag
                        data[i].Title.substring(0, 4) == 'BBC ' || // BBC just returns the station name in the Title tag
                        data[i].Title.trim() == '') {
                        // Use default title
						output += '<span class="playqueue-action" data-toggle="context" data-target="#context-menu-playqueue-item">' + (typeof(data[i].Time) == 'undefined' ? '' : formatSongTime(data[i].Time)) + '<br><b>&hellip;</b></span>';
						output += '<span class="pll1">' + DEFAULT_RADIO_TITLE + '</span>';
					} else {
                        // Use station supplied title
						output += '<span class="playqueue-action" data-toggle="context" data-target="#context-menu-playqueue-item">' + (typeof(data[i].Time) == 'undefined' ? '' : formatSongTime(data[i].Time)) + '<br><b>&hellip;</b></span>';
						output += '<span class="pll1">' + data[i].Title + '</span>';
						if (i == parseInt(MPD.json['song'])) { // active
							if (data[i].Title.substr(0, 4) === 'http' || MPD.json['coverurl'] === DEFAULT_ALBUM_COVER || UI.mobile) {
                                // Update in case MPD did not get Title tag at initial play
								$('#currentsong').html(data[i].Title);
							} else {
                                // Add search URL, see corresponding code in renderUI()
								$('#currentsong').html(genSearchUrl(data[i].Artist, data[i].Title, data[i].Album));
							}
                            // CoverView and Playbar
                            $('#ss-currentsong, #playbar-currentsong').html(data[i].Title);
						}
					}

					// Line 2 station name
					output += '<span class="pll2">';
					output += '<i class="fa-solid fa-sharp fa-microphone"></i> ';

					if (typeof(RADIO.json[data[i].file]) === 'undefined') {
						var name = typeof(data[i].Name) === 'undefined' ? 'Radio station' : data[i].Name;
						output += name;
						if (i == parseInt(MPD.json['song'])) { // active
							//SAVE: $('#playbar-currentalbum, #ss-currentalbum').html(name + '<span id="playbar-hd-badge"></span>');
						}
					} else {
						output += RADIO.json[data[i].file]['name'];
						if (i == parseInt(MPD.json['song'])) { // active
							//SAVE: $('#playbar-currentalbum, #ss-currentalbum').html(RADIO.json[data[i].file]['name'] + '<span id="playbar-hd-badge"></span>');
						}
					}
				} else {
                    // Song file or upnp url
					var thumb = (data[i].file.substring(0, 4) == 'http') ?
                        DEFAULT_UPNP_COVER :
                        'imagesw/thmcache/' + encodeURIComponent(data[i].cover_hash) + '_sm.jpg';
					output += showPlayqueueThumb ? '<span class="playqueue-thumb">' + playqueueLazy + '"' + thumb + '"/></span>' : '';
	                // Line 1 title
					output += '<span class="playqueue-action" data-toggle="context" data-target="#context-menu-playqueue-item">' + (typeof(data[i].Time) == 'undefined' ? '' : formatSongTime(data[i].Time)) + '<br><b>&hellip;</b></span>';
	                output += '<span class="pll1">';
					if (typeof(data[i].Title) === 'undefined') { // use file name
						var pos = data[i].file.lastIndexOf('.');
						if (pos == -1) {
							output += data[i].file; // Some upnp url's have no file ext
						} else {
							var filename = data[i].file.slice(0, pos);
							pos = filename.lastIndexOf('/');
							output += filename.slice(pos + 1); // Song filename (strip .ext)
						}
						output += '</span>';
					} else {
                        // Use supplied title
	                    output += data[i].Title + '</span>';
					}
					// Line 2 artist, album
					output += '<span class="pll2">';
					output += (typeof(data[i].Artist) === 'undefined') ? data[i].AlbumArtist : data[i].Artist;
				}

                output += '</span></div></li>';

            } // End loop
        }

		// Render Queue
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
        } else {
            // Queue is empty
            $('.playback-hd-badge, #playbar-hd-badge, #ss-hd-badge').hide();
        }

        // Reset
        GLOBAL.playQueueChanged = false;
    });
}

// Handle Queue commands
function sendQueueCmd(cmd, path) {
    GLOBAL.playQueueChanged = true;
    $.post('command/queue.php?cmd=' + cmd, {'path': path});
}

// Render Folder view
function renderFolderView(data, path, searchstr) {
	UI.path = path;
    $('#db-path').text(path);

	// Separate out dirs, playlists, files, exclude the RADIO folder
	var dirs = [];
	var playlists =[];
	var files = [];
	var j = 0, k = 0, l = 0;
	for (var i = 0; i < data.length; i++) {
		if (typeof(data[i].directory) != 'undefined' && data[i].directory != 'RADIO') {
			dirs[j] = data[i];
			j = j + 1;
		} else if (typeof(data[i].playlist) != 'undefined') {
			playlists[k] = data[i];
			k = k + 1;
		} else {
            if (typeof(data[i].file) != 'undefined' && data[i].file.indexOf('RADIO/') == -1) {
                files[l] = data[i];
    			l = l + 1;
            }
		}
	}
s
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
        $('#db-search-results').html('<span data-toggle="context" data-target="#context-menu-db-search-results">' + text +'</span>');
	}

	// Output the list
	var output = '';
	var element = document.getElementById('folderlist');
	element.innerHTML = '';

	for (i = 0; i < data.length; i++) {
		// MPD ignoring CUE parsing means that the folder contents will include them, so here we must ignore them too.
		if (SESSION.json['cuefiles_ignore'] == '1' && data[i].file && data[i].file.endsWith('.cue')) {
			continue;
		}
    	if (data[i].directory) {
            var rootFolderIcon = 'fa-circle-question';
            if (data[i].directory == 'NAS' ||
                data[i].directory == 'NVME' ||
                data[i].directory == 'SDCARD' ||
                data[i].directory == 'USB') {
                rootFolderIcon = getKeyOrValue('value', data[i].directory);
            }
            var cueVirtualDir = false;
    		output += '<li id="db-' + (i + 1) + '" data-path="' + data[i].directory + '">';
            output += '<div class="db-icon db-action">';
            output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder">';
            output += path == '' ?  '<i class="fa-solid fa-sharp ' + rootFolderIcon + ' icon-root"></i></a></div>' :
                (data[i].cover_hash == '' ? '<i class="fa-solid fa-sharp fa-folder"></i></a></div>' :
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
    			output += '<i class="fa-solid fa-sharp fa-list-music icon-root"></i></a></div>';
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
                var artist = data[i].AlbumArtist ? data[i].AlbumArtist : (data[i].Artist ? data[i].Artist : 'Artist tag undefined')
                output += '<div>' + data[i].Album + '<span>' + artist + '</span></div></div>';
                output += '</li>';
            }
    		if (data[i].Title) {
                // Song file
    			output += '<li id="db-' + (i + 1) + '" data-path="' + data[i].file + '">';
    			output += '<div class="db-icon db-song db-action">'; // Hack to enable entire line click for context menu
    			output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-folder-item">';
                output += (data[i].Track ? data[i].Track : "•") + '</a>';
                output += '</div>';
    			output += '<div class="db-entry db-song" data-toggle="context" data-target="#context-menu-folder-item"><div>';
                output += data[i].Title + ' <span class="songtime">' + data[i].TimeMMSS + '</span>';
                output += '<span>' + (data[i].Artist != data[i].AlbumArtist ? data[i].Artist : '') + '</span>';
                output += '</div>';
    		}
    		else {
                // Playlist item
    			output += '<li id="db-' + (i + 1) + '" data-path="' + data[i].file + '">';
    			if (data[i].file.substr(data[i].file.lastIndexOf('.') + 1).toLowerCase() == 'cue') {
                    var itemType = 'CUE sheet';
    				output += '<div class="db-icon db-song db-browse db-action">';
                    output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-savedpl-item">';
                    output += '<i class="fa-solid fa-sharp fa-list-music icon-root db-browse-icon"></i></a></div>';
                    output += '<div class="db-entry db-song db-browse" data-toggle="context" data-target="#context-menu-savedpl-item">';
    			}
    			else {
                    // Song file or radio station item
    				if (data[i].file.substr(0,4) == 'http') {
                        var itemType = typeof(RADIO.json[data[i].file]) === 'undefined' ? 'Radio station' : RADIO.json[data[i].file]['name'];
                        var iconClass = 'fa-microphone';
    				}
                    else {
                        var itemType = 'Music file';
                        var iconClass = 'fa-file-music';
    				}
                    output += '<div class="db-icon db-song db-browse db-action">';
                    output += '<a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-savedpl-item">';
                    output += '<i class="fa-solid fa-sharp ' + iconClass + ' db-browse db-browse-icon"></i></a></div>';
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
function renderRadioView(lazyLoad = true) {
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
            else if (sortTag == 'bitrate') {
                return collator.compare(b[sortTag], a[sortTag]);
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
            else if (sortTag == 'bitrate') {
                return collator.compare(b[sortTag], a[sortTag]);
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
        var radioViewTxDiv = '';
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
                var bitrateAndFormat = data[i].format + ' ' + data[i].bitrate + 'K ';
                var radioViewNvDiv = encodedAtOption <= 1 ? '<div class="lib-encoded-at-notvisible">' + bitrateAndFormat + '</div>' : '';
                var radioViewHdDiv = (encodedAtOption == 1 && bitrate > RADIO_BITRATE_THRESHOLD) ? '<div class="lib-encoded-at-hdonly">' + RADIO_HD_BADGE_TEXT + '</div>' : '';
                var radioViewTxDiv = encodedAtOption == 2 ? '<div class="lib-encoded-at-text">' + bitrateAndFormat + '</div>' : '';
                var radioViewBgDiv = encodedAtOption == 3 ? '<div class="lib-encoded-at-badge">' + bitrateAndFormat + '</div>' : '';
            }

            // Monitor div
            var monitorDiv = data[i].monitor == 'Yes' ? '<div style="display:none">monitored</div>' : '';

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

            output += radioViewTxDiv;
            output += radioViewNvDiv;
            output += monitorDiv;
            output += '</li>';

            lastSortTagValue = data[i][sortTag];
    	}

        // Render the list
		var element = document.getElementById('radio-covers');
		element.innerHTML = output;

        // NOTE: On page refresh (scripts-panels.js) lazyLoad is passed as false
        // otherwise it defaults to true
		if (currentView == 'radio'&& lazyLoad === true) {
            lazyLode('radio');
        }
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
		if (currentView == 'playlist') {
            lazyLode('playlist');
        }
    });
}

// Render Playlist names
function renderPlaylistNames (path) {
    $('#item-to-add').text(path.name);
    UI.dbEntry[4] = path.files;
    // DEBUG:
    //console.log('renderPlaylistNames(): UI.dbEntry[4]: ' + UI.dbEntry[4]);

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
    //console.log('formatKnobTotal()');
	if (MPD.json['artist'] == 'Radio station') {
		var formattedTotalTime = '';
		$('#total').html('').addClass('total-radio'); // Radio badge
		$('#playbar-mtime').css('display', 'flex');
		$('#playbar-mtotal').css('display', 'none');
	} else { // Song file
		var formattedTotalTime = formatSongTime(mpdTime); // This will be blank at queue end or cleared queue
		$('#total').removeClass('total-radio');
		$('#playbar-mtime').css('display', (MPD.json['file'] === null ? '' : 'flex'));
		$('#playbar-mtotal').css('display', (UI.mobile === true ? 'block' : 'none'));
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
    $('#ss-countdown').css('display', 'block');
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
		}
        else {
            $('#playbar-total, #playbar-countdown, #countdown-display').html('00:00');
            $('#playbar-timeline').css('display', 'none');
            $('#playbar-title').css('padding-bottom', '0');
            $('#ss-countdown').text('');
        }
	}
	// Radio station (never has a duration)
	else if (MPD.json['artist'] == 'Radio station' && typeof(MPD.json['duration']) === 'undefined') {
        $('#ss-countdown').css('display', 'none');

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
            $('#playbar-countdown, #ss-countdown').text($('#countdown-display').text());
            $('#playbar-title').css('padding-bottom', '1rem');
		}
	}

    if (MPD.json['state'] === 'play') {
        // Move these out of the timer
		var tt = $('#timetrack');
		var ti = $('#time');

        UI.knob = setInterval(function() {
			if (UI.mobile || $('#panel-footer').css('display') == 'flex' || GLOBAL.coverViewActive) {
				if (!timeSliderMove) {

					syncTimers();

					if (UI.mobile) {
						tt.val(GLOBAL.initTime * 10).trigger('change');
					}
				}
			}
            delta === 0 ? GLOBAL.initTime = GLOBAL.initTime + 0.5 : GLOBAL.initTime = GLOBAL.initTime + 0.1; // fast paint when radio station playing
			if (!UI.mobile && $('#panel-footer').css('display') != 'flex') {
	            if (delta === 0 && GLOBAL.initTime > 100) { // stops painting when radio (delta = 0) and knob fully painted
					window.clearInterval(UI.knob)
					UI.knobPainted = true;
	            }
           		ti.val(GLOBAL.initTime * 10).trigger('change');
			}
        }, delta * 1000);
    }
    else if (MPD.json['state'] === 'pause' ) {
		syncTimers();
		$('#time, #timetrack, #playbar-timetrack').val(GLOBAL.initTime * 10).trigger('change');
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

    var async = true;

    /*console.log('setVolume(): ' +
        'level=' + level + ', ' +
        'event=' + event + ', ' +
        'mute=' + SESSION.json['volmute'] + ', ' +
        'ajax=' + (async === true ? 'async' : 'sync'));*/

	// Not muted: Set volume (incl 0 vol)
	if (SESSION.json['volmute'] == '0') {
        //console.log('sendVolCmd(): unmute (volknob ' + SESSION.json['volknob'] + ')');
		SESSION.json['volknob'] = level.toString();
		sendVolCmd('POST', 'upd_volume', {'volknob': SESSION.json['volknob'], 'event': event}, async);
    } else {
        // Muted
		if (level == 0 && event == 'mute')	{
            // Set mute
            //console.log('sendVolCmd(): mute (volknob 0)');
            sendVolCmd('POST', 'upd_volume', {'volknob': '0', 'event': 'mute'}, async);
		} else {
			// Already muted: Vol up/dn btns pressed, just store volume for subsequent display when unmuted
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
	    var itemNum = parseInt(MPD.json['song']);
		var itemPos = $('#playqueue ul li:nth-child(' + (itemNum + 1) + ')').position().top;
		var scrollCalc = (itemPos + 200);
	    $('html, body').animate({ scrollTop: scrollCalc }, 'fast');
	}
});

// 'All music' menu item
$('.view-all').click(function(e) {
	$('.view-recents span').hide();
	$('.view-all span').show();
	$('#library-header').click()
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
    // DEBUG:
    //console.log('click .context-menu a: cmd|path: ' + $(this).data('cmd') + '|' + path);

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
                notify(NOTIFY_TITLE_INFO, $(this).data('cmd'), NOTIFY_DURATION_SHORT);
            }

            // If its a playlist, preload the playlist name
    		if (path.indexOf('/') == -1 && !containsBaseFolderName(path)) {
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
        // NOTE: playqueue and cv-playqueue
        case 'playqueue_top':
            if (UI.mobile) {
        		itemPos = $('#playqueue ul li:nth-child(1)').position().top;
        		scrollCalc = (itemPos + 200);
        	    $('html, body').animate({ scrollTop: scrollCalc }, 'slow');
            } else {
                customScroll('playqueue', 0, 600);
            }
            break;
        case 'playqueue_bottom':
            if (UI.mobile) {
                itemPos = $('#playqueue ul li:nth-child(' + MPD.json['playlistlength'] + ')').position().top;
        		scrollCalc = (itemPos + 200);
        	    $('html, body').animate({ scrollTop: scrollCalc }, 'slow');
            } else {
                customScroll('playqueue', parseInt(MPD.json['playlistlength']), 600);
            }
            break;
        case 'playqueue_info':
            var mins = 0;
            var secs = 0;
            var totalItems = 0
            var totalTracks = 0;
            $('#playqueue-list .playqueue-action').each(function() {
                totalItems++;
                var trackTime = $(this).text().slice(0, -1);
                if (trackTime.length > 0) {
                    var mmss = trackTime.split(':');
                    mins += parseInt(mmss[0]);
                    secs += parseInt(mmss[1]);
                    totalTracks++;
                }
            });
            var totalTrackTime = formatSongTime((mins * 60) + secs);

            // Get most recent playlist added to Queue
            $.getJSON('command/music-library.php?cmd=get_recent_playlist', function(data) {
                var playlist = data;
                // Display notification
                notify(NOTIFY_TITLE_INFO, 'playqueue_info',
                    'Items:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' + totalItems + '<br>' +
                    'Tracks:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' + totalTracks + '<br>' +
                    'Track time:&nbsp;' + totalTrackTime + '<br>' +
                    'Playlist:&nbsp;&nbsp;&nbsp;' + playlist,
                    NOTIFY_DURATION_INFINITE);
                // Styling (gets automatically reset by pnotify for other notifications)
                $('.ui-pnotify-text').attr('style', 'text-align:left;font-family:monospace;font-size:.85em');
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
                $('#edit-station-tags').css('margin-top', '20px');
                $('#edit-station-type span').text(getKeyOrValue('key', data['type']));
                $('#edit-station-genre').val(data['genre']);
                $('#edit-station-broadcaster').val(data['broadcaster']);
                $('#edit-station-home-page').val(data['home_page']);
                $('#edit-station-language').val(data['language']);
                $('#edit-station-country').val(data['country']);
                $('#edit-station-region').val(data['region']);
                $('#edit-station-bitrate').val(data['bitrate']);
                $('#edit-station-format').val(data['format']);
                $('#edit-station-geo-fenced span').text(data['geo_fenced']);
                $('#edit-station-mpd-monitor span').text(data['monitor']);

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
                $('#edit-playlist-tags').css('margin-top', '20px');
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
            $('#playlist-items-container').css('padding-top', '0');
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
            $('#playlist-items-container').css('padding-top', '0');
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
        case 'favorite_playqueue_item':
            $.getJSON('command/queue.php?cmd=get_playqueue_item&songpos=' + path, function(data) {
                notify(NOTIFY_TITLE_INFO, 'adding_favorite', NOTIFY_DURATION_SHORT);
                $.get('command/playlist.php?cmd=add_item_to_favorites&item=' + encodeURIComponent(data), function() {
                    notify(NOTIFY_TITLE_INFO, 'favorite_added', NOTIFY_DURATION_SHORT);
                });
            });
            break;
        case 'setforclockradio':
        case 'setforclockradio-m':
    		if ($(this).data('cmd') == 'setforclockradio-m') { // Called from Configuration modal
    			$('#configure-modal').modal('toggle');
    		}

    		$('#clockradio-mode span').text(SESSION.json['clkradio_mode']);

    		if ($(this).data('cmd') == 'setforclockradio-m') {
    			$('#clockradio-playname').val(SESSION.json['clkradio_name']);
                $('#info-clockradio-playname').text(SESSION.json['clkradio_name']);
    			UI.dbEntry[0] = '-1'; // For update
    		} else {
    			$('#clockradio-playname').val(UI.dbEntry[5]); // Called from context menu
                $('#info-clockradio-playname').text(UI.dbEntry[5]);
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
            if (SESSION.json['rx_hostnames'] == '-1') {
                notify(NOTIFY_TITLE_ALERT, 'trx_run_receiver_discovery');
            } else if (SESSION.json['rx_hostnames'] == 'No receivers found') {
                notify(NOTIFY_TITLE_ALERT, 'trx_no_receivers_found');
            } else {
                notify(NOTIFY_TITLE_INFO, 'trx_querying_receivers', NOTIFY_DURATION_INFINITE);

                var modalType = $(this).data('cmd') == 'multiroom_rx_modal' ? 'full' : 'limited';
                $.getJSON('command/multiroom.php?cmd=get_rx_status', function(data) {
                    //console.log(data);
                    $('.ui-pnotify-closer').click();

                    if (data == 'Discovery has not been run') {
                        notify(NOTIFY_TITLE_ALERT, 'trx_run_receiver_discovery');
                    } else if (data == 'No receivers found') {
                        notify(NOTIFY_TITLE_ALERT, 'trx_no_receivers_found');
                    } else {
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
                            // 6: multicast_addr

                            if (rxStatusParts[1] == 'Unknown' || rxStatusParts[1] == 'Disabled') {
                                output += '<div class="control-group" style="margin-bottom:3em;">';
                                // Hostname
                                output += '<label class="control-label multiroom-modal-host">' + rxStatusParts[5] + '</label>';
                                // Status
                                var statusMsg = rxStatusParts[1] == 'Unknown' ? 'Receiver offline' : 'Receiver disabled';
                                output += '<div class="controls">';
                                output += '<div style="font-style:italic;margin-top:.5em;">' + statusMsg + '</div>';
                                output += '</div>';
                                output += '</div>';
                            } else {
                                var rxChecked = rxStatusParts[1] == 'On' ? 'checked' : ''; // Status
                                var rxCheckedDisable = rxStatusParts[2] == '?' ? ' disabled' : ''; // Volume
                                var rxMuteIcon = rxStatusParts[3] == '1' ? 'fa-volume-mute' : 'fa-volume-up'; // Mute
                                var rxMasterVolOptIn = rxStatusParts[4] == '0' ? '' : '<i class="fa-regular fa-sharp fa-circle-check"></i>'; // Master vol opt-in

                                output += '<div class="control-group">';
                                // Receiver hostname
                                output += '<label class="control-label multiroom-modal-host" for="multiroom-rx-' + item + '-onoff">' + rxStatusParts[5] + '</label>';
                                output += '<div class="controls">';
                                // Receiver On/Off checkbox
                                var topMargin = modalType != 'full' ? ' multiroom-modal-onoff-xtra' : '';
                                output += '<input id="multiroom-rx-' + item + '-onoff" class="checkbox-ctl multiroom-modal-onoff' + topMargin + '" type="checkbox" data-item="' + item + '" ' + rxChecked + rxCheckedDisable + '>';

                                if (modalType == 'full') {
                                    // Volume
                                    var volDisabled = (
                                        rxStatusParts[2] == '0dB' ||
                                        rxStatusParts[2] == '?' ||
                                        (SESSION.json['btactive'] + SESSION.json['aplactive'] + SESSION.json['spotactive'] > 0)
                                    ) ? ' disabled' : '';
                                    output += '<div class="modal-button-style multiroom-modal-btn">';
                                    output += '<button id="multiroom-rx-' + item + '-vol" class="btn btn-primary btn-small multiroom-modal-vol" data-item="' + item +
                                        '"' + volDisabled + '>' + (rxStatusParts[2] == '?' ? 'n/a' : rxStatusParts[2]) + '</button>';
                                    output += '</div>';
                                    // Mute toggle
                                    output += '<div class="modal-button-style multiroom-modal-btn">';
                                    output += '<button id="multiroom-rx-' + item + '-mute" class="btn btn-primary btn-small multiroom-modal-mute" data-item="' + item +
                                        '"' + volDisabled + '><i class="fa-solid fa-sharp ' + rxMuteIcon + '"></i></button>';
                                    output += '</div>';
                                    // Master volume opt-in indicator
                                    output += '<div class="modal-button-style multiroom-modal-btn">';
                                    output += rxMasterVolOptIn;
                                    output += '</div>';
                                    output += '</div>';
                                    // Volume slider
                                    output += '<div class="controls">';
                                    output += '<input id="multiroom-rx-' + item + '-vol-slider" class="hslide2" type="range" min="0" max="' + SESSION.json['volume_mpd_max'] +
                                        '" step="1" name="multiroom-rx-' + item + '-vol-slider" value="' + (rxStatusParts[2] == '?' ? '0' :
                                            (rxStatusParts[2] == '0dB' ? 100 : rxStatusParts[2])) +
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
        case 'toggle_coverview':
            $.post('command/playback.php?cmd=toggle_coverview');
            break;
        case 'camilladsp_config':
    		var selectedConfig = $(this).data('cdspconfig');

    		$.ajax({
    			type: 'POST',
    			url: 'command/camilla.php?cmd=cdsp_set_config',
    			async: true,
    			cache: false,
    			data: {'cdspconfig': selectedConfig},
    			success: function(data) {
                    $('.dropdown-cdsp-line span').remove();
                    var selectedHTML = $('a[data-cdspconfig="' + selectedConfig + '"]').html();
                    $('a[data-cdspconfig="' + selectedConfig + '"]').html(selectedHTML +
                        '<span id="menu-check-cdsp"><i class="fa-solid fa-sharp fa-check"></i></span>');
    			},
    			error: function() {
                    notify(NOTIFY_TITLE_ALERT, 'cdsp_config_update_failed', selectedConfig, NOTIFY_DURATION_MEDIUM);
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
        				$('#info-toggle-bgimage').css('margin-left','0');
        		    }
        		});
        		$('#cover-backdrop-enabled span').text(SESSION.json['cover_backdrop']);
        		$('#cover-blur span').text(SESSION.json['cover_blur']);
        		$('#cover-scale span').text(SESSION.json['cover_scale']);
                $('#renderer-backdrop span').text(SESSION.json['renderer_backdrop']);
                $('#font-size span').text(SESSION.json['font_size']);
                $('#native-lazyload span').text(SESSION.json['native_lazyload']);

                // Playback
                $('#show-npicon span').text(SESSION.json['show_npicon']);
        		$('#extra-tags').val(SESSION.json['extra_tags']);
                $('#search_site span').text(SESSION.json['search_site']); // @Atair
                $('#play-history-enabled span').text(SESSION.json['playhist']);

                // Cover Art
                $('#cover-search-priority span').text(getKeyOrValue('key', SESSION.json['library_covsearchpri']));
                $('#thumbgen-scan span').text(SESSION.json['library_thmgen_scan']);
                $('#hires-thumbnails span').text(getKeyOrValue('key', SESSION.json['library_hiresthm']));
                $('#playqueue-art-enabled span').text(SESSION.json['playlist_art']);
                $('#show-tagview-covers span').text(SESSION.json['library_tagview_covers']);

                // Library
                $('#onetouch_album span').text(SESSION.json['library_onetouch_album']);
                $('#onetouch_radio span').text(SESSION.json['library_onetouch_radio']);
                $('#onetouch-pl span').text(SESSION.json['library_onetouch_pl']);
                $('#onetouch-ralbum span').text(SESSION.json['library_onetouch_ralbum']);

                $('#albumview-sort-order span').text('by ' + SESSION.json['library_albumview_sort']);
                $('#tagview-sort-order span').text('by ' + SESSION.json['library_tagview_sort']);
                $('#show-genres-column span').text(SESSION.json['library_show_genres']);
                $('#tag-view-genre span').text(SESSION.json['library_tagview_genre']);
                $('#tag-view-artist span').text(SESSION.json['library_tagview_artist']);

                $('#track-play span').text(SESSION.json['library_track_play']);
                $('#recently-added span').text(getKeyOrValue('key', SESSION.json['library_recently_added']));
                $('#show-encoded-at span').text(getKeyOrValue('key', SESSION.json['library_encoded_at']));
                $('#ellipsis-limited-text span').text(SESSION.json['library_ellipsis_limited_text']);
                $('#thumbnail-columns span').text(SESSION.json['library_thumbnail_columns']);

                $('#library-album-key span').text(miscLibOptions[1]);
                $('#library-inc-comment-tag span').text(miscLibOptions[0]);
                $('#ignore-articles').val(SESSION.json['library_ignore_articles']);
                $('#utf8-char-filter span').text(SESSION.json['library_utf8rep']);

        		// CoverView
                $('#scnsaver-timeout span').text(getKeyOrValue('key', SESSION.json['scnsaver_timeout']));
                $('#scnsaver-whenplaying span').text(SESSION.json['scnsaver_whenplaying']);
                $('#auto-coverview span').text(SESSION.json['auto_coverview'] == '-on' ? 'Yes' : 'No');
        		$('#scnsaver-style span').text(SESSION.json['scnsaver_style']);
                $('#scnsaver-mode span').text(SESSION.json['scnsaver_mode']);
                $('#scnsaver-layout span').text(SESSION.json['scnsaver_layout']);
                $('#show-cvpb span').text(SESSION.json['show_cvpb']);
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

    notify(NOTIFY_TITLE_INFO, 'upd_clock_radio');
});
function updateClockRadioCfgSys() {
    // Update database
    $.post('command/cfg-table.php?cmd=upd_cfg_system',
        {
        'clkradio_mode': SESSION.json['clkradio_mode'],
        'clkradio_item': SESSION.json['clkradio_item'],
        'clkradio_name': SESSION.json['clkradio_name'],
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
	if (SESSION.json['cover_backdrop'] != $('#cover-backdrop-enabled span').text()) {themeSettingsChange = true;}
	if (SESSION.json['cover_blur'] != $('#cover-blur span').text()) {themeSettingsChange = true;}
	if (SESSION.json['cover_scale'] != $('#cover-scale span').text()) {themeSettingsChange = true;}
    if (SESSION.json['render_backdrop'] != $('#renderer-backdrop span').text()) {/*NOP*/}
    if (SESSION.json['font_size'] != $('#font-size span').text()) {fontSizeChange = true;};
    if (SESSION.json['native_lazyload'] != $('#native-lazyload span').text()) {lazyLoadChange = true;};

    // Playback
    if (SESSION.json['show_npicon'] != $('#show-npicon span').text()) {showNpIconChange = true;}
    if (SESSION.json['extra_tags'] != $('#extra-tags').val()) {extraTagsChange = true;}
    if (SESSION.json['search_site'] != $('#search_site span').text()) {libraryOptionsChange = true;} // @Atair
    if (SESSION.json['playhist'] != $('#play-history-enabled span').text()) {playHistoryChange = true;}

    // Cover Art
    if (SESSION.json['library_covsearchpri'] != getKeyOrValue('value', $('#cover-search-priority span').text())) {regenThumbsReqd = true;}
    if (SESSION.json['library_thmgen_scan'] != $('#thumbgen-scan span').text()) {regenThumbsReqd = true;}
    if (SESSION.json['library_hiresthm'] != getKeyOrValue('value', $('#hires-thumbnails span').text())) {regenThumbsReqd = true;}
    if (SESSION.json['playlist_art'] != $('#playqueue-art-enabled span').text()) {playqueueArtChange = true;}
    if (SESSION.json['library_tagview_covers'] != $('#show-tagview-covers span').text()) {libraryOptionsChange = true;}

    // Library
    if (SESSION.json['library_onetouch_album'] != $('#onetouch_album span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_onetouch_radio'] != $('#onetouch_radio span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_onetouch_pl'] != $('#onetouch-pl span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_onetouch_ralbum'] != $('#onetouch-ralbum span').text()) {libraryOptionsChange = true;}

    if (SESSION.json['library_albumview_sort'] != $('#albumview-sort-order span').text().replace('by ', '')) {libraryOptionsChange = true;}
    if (SESSION.json['library_tagview_sort'] != $('#tagview-sort-order span').text().replace('by ', '')) {libraryOptionsChange = true;}
    if (SESSION.json['library_show_genres'] != $('#show-genres-column span').text()) {
		$('#show-genres-column span').text() == "Yes" ? $('#top-columns').removeClass('nogenre') : $('#top-columns').addClass('nogenre');
	}
    if (SESSION.json['library_tagview_genre'] != $('#tag-view-genre span').text()) {clearLibcacheAllReqd = true;}
    if (SESSION.json['library_tagview_artist'] != $('#tag-view-artist span').text()) {clearLibcacheAllReqd = true;}

    if (SESSION.json['library_track_play'] != $('#track-play span').text()) {libraryOptionsChange = true;}
    if (SESSION.json['library_recently_added'] != getKeyOrValue('value', $('#recently-added span').text())) {libraryOptionsChange = true;}
    if (SESSION.json['library_encoded_at'] != getKeyOrValue('value', $('#show-encoded-at span').text())) {reloadLibrary = true;}
    if (SESSION.json['library_ellipsis_limited_text'] != $('#ellipsis-limited-text span').text()) {
		$('#ellipsis-limited-text span').text() == "Yes" ?
        $('#library-panel, #radio-panel, #playlist-panel').addClass('limited') :
        $('#library-panel, #radio-panel, #playlist-panel').removeClass('limited');
	}
    if (SESSION.json['library_thumbnail_columns'] != $('#thumbnail-columns span').text()) {thumbSizeChange = true;}
    if (miscLibOptions[1] != $('#library-album-key span').text()) {clearLibcacheAllReqd = true;}
    if (miscLibOptions[0] != $('#library-inc-comment-tag span').text()) {clearLibcacheAllReqd = true;}
    if (SESSION.json['library_ignore_articles'] != $('#ignore-articles').val()) {libraryOptionsChange = true;}
    if (SESSION.json['library_utf8rep'] != $('#utf8-char-filter span').text()) {libraryOptionsChange = true;}

    // CoverView
    if (SESSION.json['scnsaver_timeout'] != getKeyOrValue('value', $('#scnsaver-timeout span').text())) {scnSaverTimeoutChange = true;}
    if (SESSION.json['auto_coverview'] != ($('#auto-coverview span').text() == 'Yes' ? '-on' : '-off')) {autoCoverViewChange = true;}
	if (SESSION.json['scnsaver_style'] != $('#scnsaver-style span').text()) {scnSaverStyleChange = true;}
    if (SESSION.json['scnsaver_mode'] != $('#scnsaver-mode span').text()) {scnSaverModeChange = true;}
    if (SESSION.json['scnsaver_layout'] != $('#scnsaver-layout span').text()) {scnSaverLayoutChange = true;}
    if (SESSION.json['scnsaver_xmeta'] != $('#scnsaver-xmeta span').text()) {extraTagsChange = true;}

	// Appearance
	SESSION.json['themename'] = $('#theme-name span').text();
	SESSION.json['accent_color'] = $('#accent-color span').text();
	SESSION.json['alphablend'] = $('#alpha-blend span').text();
	SESSION.json['cover_backdrop'] = $('#cover-backdrop-enabled span').text();
	SESSION.json['cover_blur'] = $('#cover-blur span').text();
	SESSION.json['cover_scale'] = $('#cover-scale span').text();
    SESSION.json['renderer_backdrop'] = $('#renderer-backdrop span').text();
    SESSION.json['font_size'] = $('#font-size span').text();
    SESSION.json['native_lazyload'] = $('#native-lazyload span').text();

    // Playback
    SESSION.json['show_npicon'] = $('#show-npicon span').text();
    SESSION.json['extra_tags'] = $('#extra-tags').val();
    SESSION.json['search_site'] = $('#search_site span').text(); // @Atair
    SESSION.json['playhist'] = $('#play-history-enabled span').text();

    // Cover Art
    SESSION.json['library_covsearchpri'] = getKeyOrValue('value', $('#cover-search-priority span').text());
    SESSION.json['library_thmgen_scan'] = $('#thumbgen-scan span').text();
    SESSION.json['library_hiresthm'] = getKeyOrValue('value', $('#hires-thumbnails span').text());
    SESSION.json['playlist_art'] = $('#playqueue-art-enabled span').text();
    SESSION.json['library_tagview_covers'] = $('#show-tagview-covers span').text();

    // Library
    SESSION.json['library_onetouch_album'] = $('#onetouch_album span').text();
    SESSION.json['library_onetouch_radio'] = $('#onetouch_radio span').text();
    SESSION.json['library_onetouch_pl'] = $('#onetouch-pl span').text();
    SESSION.json['library_onetouch_ralbum'] = $('#onetouch-ralbum span').text();

    SESSION.json['library_albumview_sort'] = $('#albumview-sort-order span').text().replace('by ', '');
    SESSION.json['library_tagview_sort'] = $('#tagview-sort-order span').text().replace('by ', '');
    SESSION.json['library_show_genres'] = $('#show-genres-column span').text();
    SESSION.json['library_tagview_genre'] = $('#tag-view-genre span').text();
    SESSION.json['library_tagview_artist'] = $('#tag-view-artist span').text();

    SESSION.json['library_track_play'] = $('#track-play span').text();
    SESSION.json['library_recently_added'] = getKeyOrValue('value', $('#recently-added span').text());
    SESSION.json['library_encoded_at'] = getKeyOrValue('value', $('#show-encoded-at span').text());
    SESSION.json['library_ellipsis_limited_text'] = $('#ellipsis-limited-text span').text();
    SESSION.json['library_thumbnail_columns'] = $('#thumbnail-columns span').text();

    SESSION.json['library_misc_options'] = $('#library-inc-comment-tag span').text() + ',' + $('#library-album-key span').text();
    SESSION.json['library_ignore_articles'] = $('#ignore-articles').val().trim();
    SESSION.json['library_utf8rep'] = $('#utf8-char-filter span').text();

    // CoverView
    SESSION.json['scnsaver_timeout'] = getKeyOrValue('value', $('#scnsaver-timeout span').text());
    SESSION.json['scnsaver_whenplaying'] = $('#scnsaver-whenplaying span').text();
    SESSION.json['auto_coverview'] = ($('#auto-coverview span').text() == 'Yes' ? '-on' : '-off');
	SESSION.json['scnsaver_style'] = $('#scnsaver-style span').text();
    SESSION.json['scnsaver_mode'] = $('#scnsaver-mode span').text();
    SESSION.json['scnsaver_layout'] = $('#scnsaver-layout span').text();
    SESSION.json['show_cvpb'] = $('#show-cvpb span').text();
    SESSION.json['scnsaver_xmeta'] = $('#scnsaver-xmeta span').text();

	if (fontSizeChange == true) {
		setFontSize();
		window.dispatchEvent(new Event('resize')); // Resize knobs if needed
	}

	if (scnSaverTimeoutChange == true) {
        $.post('command/playback.php?cmd=reset_screen_saver');
	}

    if (autoCoverViewChange || scnSaverStyleChange || scnSaverModeChange ||
        scnSaverLayoutChange || extraTagsChange) {
        if (SESSION.json['local_display'] == '1') {
            $.post('command/system.php?cmd=restart_local_display');
        }
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

		if (SESSION.json['cover_backdrop'] == 'Yes' && MPD.json['coverurl'].indexOf(DEFAULT_ALBUM_COVER) === -1) {
			$('#cover-backdrop').html('<img class="ss-backdrop" ' + 'src="' + MPD.json['coverurl'] + '">');
			$('#cover-backdrop').css('filter', 'blur(' + SESSION.json['cover_blur'] + ')');
			$('#cover-backdrop').css('transform', 'scale(' + SESSION.json['cover_scale'] + ')');
		} else {
			$('#cover-backdrop').html('');
		}

        lastYIQ = 0; // Reset for setColors()
		setColors();
	}

	if (playqueueArtChange == true) {
		renderPlayqueue(MPD.json['state']);
	}

    if (showNpIconChange == true) {
        setNpIcon();
        lastYIQ = 0; // Reset for setColors()
        setColors();
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
            'cover_backdrop': SESSION.json['cover_backdrop'],
            'cover_blur': SESSION.json['cover_blur'],
            'cover_scale': SESSION.json['cover_scale'],
            'renderer_backdrop': SESSION.json['renderer_backdrop'],
            'font_size': SESSION.json['font_size'],
            'native_lazyload': SESSION.json['native_lazyload'],

            // Playback
            'show_npicon': SESSION.json['show_npicon'],
            'extra_tags': SESSION.json['extra_tags'],
            'search_site': SESSION.json['search_site'], // @Atair
            'playhist': SESSION.json['playhist'],

            // Cover Art
            'library_covsearchpri': SESSION.json['library_covsearchpri'],
            'library_thmgen_scan': SESSION.json['library_thmgen_scan'],
            'library_hiresthm': SESSION.json['library_hiresthm'],
            'playlist_art': SESSION.json['playlist_art'],
            'library_tagview_covers': SESSION.json['library_tagview_covers'],

            // Library
            'library_onetouch_album': SESSION.json['library_onetouch_album'],
            'library_onetouch_radio': SESSION.json['library_onetouch_radio'],
            'library_onetouch_pl': SESSION.json['library_onetouch_pl'],
            'library_onetouch_ralbum': SESSION.json['library_onetouch_ralbum'],

            'library_albumview_sort': SESSION.json['library_albumview_sort'],
            'library_tagview_sort': SESSION.json['library_tagview_sort'],
            'library_show_genres': SESSION.json['library_show_genres'],
            'library_tagview_genre': SESSION.json['library_tagview_genre'],
            'library_tagview_artist': SESSION.json['library_tagview_artist'],

            'library_track_play': SESSION.json['library_track_play'],
            'library_recently_added': SESSION.json['library_recently_added'],
            'library_encoded_at': SESSION.json['library_encoded_at'],
            'library_ellipsis_limited_text': SESSION.json['library_ellipsis_limited_text'],
            'library_thumbnail_columns': SESSION.json['library_thumbnail_columns'],

            'library_misc_options': SESSION.json['library_misc_options'],
            'library_ignore_articles': SESSION.json['library_ignore_articles'],
            'library_utf8rep': SESSION.json['library_utf8rep'],

            // CoverView
            'scnsaver_timeout': SESSION.json['scnsaver_timeout'],
            'scnsaver_whenplaying' = SESSION.json['scnsaver_whenplaying'],
            'auto_coverview': SESSION.json['auto_coverview'],
            'scnsaver_style': SESSION.json['scnsaver_style'],
            'scnsaver_mode': SESSION.json['scnsaver_mode'],
            'scnsaver_layout': SESSION.json['scnsaver_layout'],
            'show_cvpb': SESSION.json['show_cvpb'],
            'scnsaver_xmeta': SESSION.json['scnsaver_xmeta'],

            // Internal
            'preferences_modal_state': SESSION.json['preferences_modal_state']
        },
        function() {
            if (extraTagsChange || scnSaverStyleChange || scnSaverModeChange || scnSaverLayoutChange ||
                playHistoryChange || libraryOptionsChange || clearLibcacheAllReqd || lazyLoadChange ||
                (SESSION.json['bgimage'] != '' && SESSION.json['cover_backdrop'] == 'No') || UI.bgImgChange == true) {
                notify(NOTIFY_TITLE_INFO, 'settings_updated', ' The page will automatically refresh to make the settings effective.');
                setTimeout(function() {
                    location.reload(true);
                }, (NOTIFY_DURATION_DEFAULT * 1000));
            } else if (reloadLibrary) {
                $('#btn-ra-refresh').click();
                loadLibrary();
            } else if (regenThumbsReqd) {
                notify(NOTIFY_TITLE_INFO, 'settings_updated', ' Thumbnails must be regenerated after changing this setting.');
            } else {
                notify(NOTIFY_TITLE_INFO, 'settings_updated', NOTIFY_DURATION_SHORT);
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
		$('#info-toggle-bgimage').css('margin-left','0');
        $.post('command/playback.php?cmd=remove_bg_image');
		UI.bgImgChange = true;
	}
    // So modal stays open
	return false;
});
// Import background image to server
function importBgImage(files) {
	if (files[0].size > 1000000) {
		$('#error-bgimage').text('Image must be less than 1MB in size');
		return;
	} else if (files[0].type != 'image/jpeg') {
		$('#error-bgimage').text('Image format must be JPEG');
		return;
	} else {
		$('#error-bgimage').text('');
	}

	UI.bgImgChange = true;
	imageURL = (URL || webkitURL).createObjectURL(files[0]);
	$('#current-bgimage').html("<img src='" + imageURL + "' />");
	$('#info-toggle-bgimage').css('margin-left','60px');

    /*setTimeout(function() {
        (URL || webkitURL).revokeObjectURL(imageURL);
    }, DEFAULT_TIMEOUT);*/

	var reader = new FileReader();
	reader.onload = function(e) {
		var dataURL = reader.result;
		var data = dataURL.match(/,(.*)$/)[1]; // Strip the header: 'data:[<MIME-type>][;charset=<encoding>][;base64],<data>'
        $.post('command/playback.php?cmd=set_bg_image', {'blob': data});

	}
	reader.readAsDataURL(files[0]);
}

// Import cover image to server
function newCoverImage(files, view) {
    if (view == 'radio') {
        var errorSelector = '#error-new-logoimage';
        var previewSelector = '#preview-new-logoimage';
        var infoSelector = '#info-toggle-new-logoimage';
        var tagsSelector = '#new-station-tags';
        var nameSelector = '#new-station-name';
        var cmd = 'set_ralogo_image';
        var script = 'radio.php';
    } else { // playlist
        var errorSelector = '#error-new-plcoverimage';
        var previewSelector = '#preview-new-plcoverimage';
        var infoSelector = '#info-toggle-new-plcoverimage';
        var tagsSelector = '#new-playlist-tags';
        var nameSelector = '#new-playlist-name';
        var cmd = 'set_plcover_image';
        var script = 'playlist.php';
    }

	if (files[0].size > 1000000) {
		$(errorSelector).text('Image must be less than 1MB in size');
		return;
	} else if (files[0].type != 'image/jpeg') {
		$(errorSelector).text('Image format must be JPEG');
		return;
	} else {
		$(errorSelector).text('');
	}

	imageURL = (URL || webkitURL).createObjectURL(files[0]);
	$(previewSelector).html("<img src='" + imageURL + "' />");
	$(infoSelector).css('margin-left','60px');
    $(tagsSelector).css('margin-top', '20px');
	var name = $(nameSelector).val();

    setTimeout(function() {
        (URL || webkitURL).revokeObjectURL(imageURL);
    }, DEFAULT_TIMEOUT);

	var reader = new FileReader();
	reader.onload = function(e) {
		var dataURL = reader.result;
		var data = dataURL.match(/,(.*)$/)[1]; // Strip the header: 'data:[<MIME-type>][;charset=<encoding>][;base64],<data>'
        $.post('command/' + script + '?cmd=' + cmd, {'name': name, 'blob': data});
	}
	reader.readAsDataURL(files[0]);
}
// Edit (replace/remove) existing cover image
function editCoverImage(files, view) {
    if (view == 'radio') {
        var errorSelector = '#error-edit-logoimage';
        var previewSelector = '#preview-edit-logoimage';
        var infoSelector = '#info-toggle-edit-logoimage';
        var tagsSelector = '#edit-station-tags';
        var nameSelector = '#edit-station-name';
        var cmd = 'set_ralogo_image';
        var script = 'radio.php';
    } else { // playlist
        var errorSelector = '#error-edit-plcoverimage';
        var previewSelector = '#preview-edit-plcoverimage';
        var infoSelector = '#info-toggle-edit-plcoverimage';
        var tagsSelector = '#edit-playlist-tags';
        var nameSelector = '#edit-playlist-name';
        var cmd = 'set_plcover_image';
        var script = 'playlist.php';
    }

	if (files[0].size > 1000000) {
		$(errorSelector).text('Image must be less than 1MB in size');
		return;
	} else if (files[0].type != 'image/jpeg') {
		$(errorSelector).text('Image format must be JPEG');
		return;
	} else {
		$(errorSelector).text('');
	}

	imageURL = (URL || webkitURL).createObjectURL(files[0]);
	$(previewSelector).html("<img src='" + imageURL + "' />");
	$(infoSelector).css('margin-left','60px');
	var name = $(nameSelector).val();

    /*setTimeout(function() {
        (URL || webkitURL).revokeObjectURL(imageURL);
    }, DEFAULT_TIMEOUT);*/

	var reader = new FileReader();
	reader.onload = function(e) {
		var dataURL = reader.result;
		var data = dataURL.match(/,(.*)$/)[1]; // Strip the header: 'data:[<MIME-type>][;charset=<encoding>][;base64],<data>'
        $.post('command/' + script + '?cmd=' + cmd, {'name': name, 'blob': data});
	}
	reader.readAsDataURL(files[0]);
}

function setClkRadioCtls(ctlValue) {
	if (ctlValue == 'Disabled') {
		$('#clockradio-ctl-grp-1 *').prop('disabled', true);
		$('#clockradio-ctl-grp-2 *').prop('disabled', true);
		$('#clockradio-ctl-grp-3 *').prop('disabled', true);
	}
	else if (ctlValue == 'Sleep Timer') {
		$('#clockradio-ctl-grp-1 *').prop('disabled', true);
		$('#clockradio-ctl-grp-2 *').prop('disabled', false);
		$('#clockradio-ctl-grp-3 *').prop('disabled', true);
	}
	else {
		$('#clockradio-ctl-grp-1 *').prop('disabled', false);
		$('#clockradio-ctl-grp-2 *').prop('disabled', false);
		$('#clockradio-ctl-grp-3 *').prop('disabled', false);
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
	notify(NOTIFY_TITLE_INFO, 'restart',);
    $.post('command/system.php?cmd=reboot');
});

$('#system-shutdown').click(function(e) {
	UI.restart = 'shutdown';
    notify(NOTIFY_TITLE_INFO, 'shutdown');
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

// Always use rgba now
function str2rgba(tempcolor) {
	var temp = 'rgba(' + tempcolor + ')';
	return temp;
}
function str2hex(tempcolor) {
	var temp = '#' + tempcolor;
	return temp;
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
	var tempa = hexToRgb(accentColor);
	UI.accenta = rgbaToRgb(.3 - tempx, .75, temprgba, tempa);
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

	/*DELETEif (currentView.indexOf('playback') !== -1 && SESSION.json['adaptive'] == 'Yes') {
		yiqBool = getYIQ(adaptBack) > 127 ? true : false;
		document.body.style.setProperty('--adaptbg', adaptBack);
		document.body.style.setProperty('--adapttext', adaptMcolor);
		document.body.style.setProperty('--adaptmbg', adaptMback);
		btnbarfix(adaptBack, adaptColor);
	}
	else {*/
		document.body.style.setProperty('--adaptbg', themeBack);
		document.body.style.setProperty('--adapttext', themeMcolor);
		document.body.style.setProperty('--adaptmbg', themeMback);
		btnbarfix(themeBack, themeColor);
	//}

	if (lastYIQ !== yiqBool) {
		lastYIQ = yiqBool;
		if (yiqBool) {
            document.body.style.setProperty('--npicon', 'url("../images/npicon/' + GLOBAL.npIcon + '-dark.svg")');
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
            document.body.style.setProperty('--npicon', 'url("../images/npicon/' + GLOBAL.npIcon + '-light.svg")');
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
			$('#panel-footer').show();
			$('#panel-header').css('height', $('#panel-header').css('line-height'));
			$('#panel-header').css('backdrop-filter', 'blur(20px)');
            $('#playbar-toggles .add-item-to-favorites').show();
            $('#random-album').hide();
			showMenuTopW = true;
		}
		else if (UI.mobile && $(window).scrollTop() == '0' ) {
			$('#container-playqueue').css('visibility','hidden');
			$('#playback-controls').css('display', '');
			$('#panel-footer').hide();
			$('#panel-header').css('height', '0');
			$('#panel-header').css('backdrop-filter', '');
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

// Library item position functions
function storeRadioPos(pos) {
    $.post('command/cfg-table.php?cmd=upd_cfg_system', {'radio_pos': pos});
}
function storePlaylistPos(pos) {
    $.post('command/cfg-table.php?cmd=upd_cfg_system', {'playlist_pos': pos});
}
function storeLibPos(pos) {
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
	$('#panel-header').css('height', '0');
	$('#panel-header').css('backdrop-filter', '');
	$('#panel-footer, .viewswitch').css('display', 'flex');
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
                $('#index-albums, #index-albumcovers').hide();
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
    if (GLOBAL.coverViewActive) {
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

		$('#library-header').text('');
		$('#container-playqueue').css('visibility','');
		$('#panel-footer, .viewswitch').css('display', 'none');
		$('#playback-controls').css('display', '');

        SESSION.json['multiroom_tx'] == 'On' ? $('#multiroom-sender').show() : $('#multiroom-sender').hide();
        if (SESSION.json['updater_auto_check'] == 'On' && SESSION.json['updater_available_update'].substring(0, 7) == 'Release') {
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
        $('#db-search-results').css('font-weight', 'normal');
    }
    else if (currentView == 'tag') {
        $('#lib-song-' + (UI.dbEntry[0] + 1).toString()).removeClass('active');
        $('img.lib-coverart, #lib-coverart-meta-area').removeClass('active');
        $('#songsList .lib-disc a, #songsList .lib-album-heading a').removeClass('active');
    }
    else if (currentView == 'album') {
        $('#albumcovers .lib-entry').eq(UI.libPos[1]).removeClass('active');
        $('#songsList .lib-disc a, #songsList .lib-album-heading a').removeClass('active');
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

// Folder view "search" input
$('#dbfs').on('keyup', function(e) {
	if (e.key == 'Enter') {
		e.preventDefault();
 		dbFastSearch();
	}
});
function dbFastSearch() {
	$('#dbsearch-alltags').val($('#dbfs').val());
	$('#db-search-submit').click();
	$('#dbfs').blur();
	return false;
}

// Synchronize times to/from playbar so we don't have to keep countdown timers running which = ugly idle perf
function syncTimers() {
    var a = $('#countdown-display').text();
    if (a != GLOBAL.lastTimeCount) { // Only update if time has changed
        if (UI.mobile) { // Only change when needed to save work
            $('#m-countdown, #playbar-mcount').text(a);
        }
        else if (GLOBAL.coverViewActive || currentView.indexOf('playback') == -1) {
            $('#playbar-countdown, #ss-countdown').text(a);
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

    // DEBUG: Fetch _pos cfg_system values
    /*var param = getKeyOrValue('value', view);
    $.getJSON('command/cfg-table.php?cmd=get_cfg_system_value', {'param': param}, function(data) {
        console.log('makeActive(): cfg_system:', view, data);
        console.log('makeActive(): UI.rflpPos:', UI.radioPos, UI.folderPos, UI.libPos, UI.playlistPos);
    });*/

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
            $('#random-album, .adv-search-btn, .saved-search-btn').hide();
			lazyLode('radio');
			break;
		case 'folder':
			$('#viewswitch').addClass('vf');
			$('#playbar-toggles .add-item-to-favorites').show();
            $('#random-album, .adv-search-btn, .saved-search-btn').hide();
            $('#db-refresh').click();
			break;
        case 'tag':
			$('#viewswitch').addClass('vt');
            $('#playbar-toggles .add-item-to-favorites').hide();
            $('#random-album, .adv-search-btn, .saved-search-btn').show();
			$('#library-panel').addClass('tag').removeClass('covers');
            $('#index-albumcovers').attr('style', 'display:none!important');
			SESSION.json['library_show_genres'] == 'Yes' ? $('#top-columns').removeClass('nogenre') : $('#top-columns').addClass('nogenre');
			if (SESSION.json['library_tagview_covers']) {
                lazyLode('tag');
            }
			break;
		case 'album':
			$('#viewswitch').addClass('va');
            $('#playbar-toggles .add-item-to-favorites').hide();
            $('#random-album, .adv-search-btn, .saved-search-btn').show();
			$('#library-panel').addClass('covers').removeClass('tag');
            if ($('#tracklist-toggle').text().trim() == 'Hide tracks') {
                $('#bottom-row').css('display', 'flex')
                $('#lib-albumcover').css('height', 'calc(50% - env(safe-area-inset-top) - 2.75rem)'); // Was 1.75em
                $('#index-albumcovers').attr('style', 'display:none!important');
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
            $('#random-album, .adv-search-btn, .saved-search-btn').hide();
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
			headerText = 'Added in last ' + getKeyOrValue('key', SESSION.json['library_recently_added']).toLowerCase();
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

    $('#library-header').text(headerText);
}

function lazyLode(view) {
    var selector, container;
    var scrollSpeed = 200;

    // Set selector and container
    switch (view) {
        case 'radio':
            selector = 'img.lazy-radioview';
            container = '#radio-covers';
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
        // Lazy load images
        if (GLOBAL.nativeLazyLoad) {
            // Browser native lazyloader
        } else {
            // JQuery lazy loader
            if (!$(container + ' ' + selector).attr('src')) {
                $.ensure(container + ' li').then(function() {
                    $(container + ' ' + selector).lazyload({
                        container: $(container),
                        skip_invisible: false
                    });
                });
            }
        }
    }

    // Scroll to item and for tag/album and radio, highlight the item
    // NOTE: Delay a bit so the list has time to load
    setTimeout(function() {
        // UI.libPos
        // [0]: Album list pos (tag view)
        // [1]: Album cover pos (album view)
        // [2]: Artist list pos (tag view)
        // Special values for [0] and [1]: -1 = full lib displayed, -2 = lib headers clicked, -3 = search performed
        //console.log('lazyLode(): UI.libPos', UI.libPos);
        //console.log('lazyLode(): UI.radioPos', UI.radioPos);
        var albumPos = UI.libPos[0];
        var albumCoverPos = UI.libPos[1];

        if (view == 'tag') {
            if (UI.libPos[2] >= 0) {
                customScroll('artists', UI.libPos[2], scrollSpeed);
                $('#artistsList .lib-entry').eq(UI.libPos[2]).addClass('active');
                $('#artistsList .lib-entry').eq(UI.libPos[2]).click();
            }
            customScroll('albums', albumPos, scrollSpeed);
            $('#albumsList .lib-entry').eq(albumPos).addClass('active');
            $('#albumsList .lib-entry').eq(albumPos).click();
        } else if (view == 'album') {
            if (UI.libPos[0] >= 0 && albumCoverPos >= 0) {
                customScroll('albumcovers', albumCoverPos, scrollSpeed);
                $('#albumcovers .lib-entry').eq(albumCoverPos).addClass('active');
            } else {
                customScroll('albumcovers', 0, scrollSpeed);
            }
        } else if (view == 'radio' && UI.radioPos >= 0) {
            var rvHeaderCount = getRVHeaderCount();  // NOTE: Also updates UI.radioPos and cfg_system
            customScroll('radio', (UI.radioPos), scrollSpeed);
            $('.database-radio li').removeClass('active');
            $('#ra-' + (UI.radioPos - rvHeaderCount + 1).toString()).addClass('active');
        } else if (view == 'playlist' && UI.playlistPos >= 0) {
            customScroll('playlist', UI.playlistPos, scrollSpeed);
        }
        // DEBUG:
        //console.log('lazyLode(): UI.rflpPos:', UI.radioPos, UI.folderPos, UI.libPos, UI.playlistPos);
    }, LAZYLOAD_TIMEOUT);
}

// Return number of headers before the station and update UI.radioPos
function getRVHeaderCount() {
    var count = 0;
    if (typeof(RADIO.json[MPD.json['file']]) !== 'undefined') {
        $('.database-radio li').each(function(index) {
            if ($(this).hasClass('horiz-rule-radioview')) {
                count = count + 1;
            }
            if ($(this).children('span').text() == RADIO.json[MPD.json['file']]['name']) {
                UI.radioPos = index;
                return false;
            }
        });
        storeRadioPos(UI.radioPos);
    }
    //console.log('getRVHeaderCount():', count, UI.radioPos);
    return count;
}

function setFontSize() {
    var sizeFactor = getKeyOrValue('value',SESSION.json['font_size']);
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
        // DEBUG:
        //console.log('submitLibraryUpdate(): GLOBAL.libLoading: ' + GLOBAL.libLoading);
        GLOBAL.libRendered = false;
        $.getJSON('command/music-library.php?cmd=update_library', {'path': path}, function(data) {
            //console.log(data);
        });
        notify(NOTIFY_TITLE_INFO, 'update_library', (path == '' ? '' : '<br>' + path));
    }
    else {
        notify(NOTIFY_TITLE_ALERT, 'library_updating');
    }
}

function getThumbHW() {
    var colArray = SESSION.json['library_thumbnail_columns'].split('/');
    var cols = UI.mobile ? colArray[1].slice(0,1) : colArray[0]; // Need slice to handle "6/2 (Default)"
	var divM = Math.round(2 * convertRem(1.5)); // 1.5rem l/r margin for div
    // NOTE: 5 is a fudge factor to allow for the bump in right position of the alphabits index
	var columnW = parseInt(($(window).width() - (2 * GLOBAL.sbw) - divM) / cols) - 5;
	UI.thumbHW = columnW - (divM / 2);
	$('body').get(0).style.setProperty('--thumbimagesize', UI.thumbHW + 'px');
	$('body').get(0).style.setProperty('--thumbmargin', ((columnW - UI.thumbHW) / 2) + 'px');
	$('body').get(0).style.setProperty('--thumbcols', columnW + 'px');
    // Mobile:  2, 3, 4 cols
    // Desktop: 6, 7, >=8 cols
    if (cols == 2) {
        var fontSize = '1.5rem';
    } else if (cols == 3) {
        var fontSize = '1.25rem';
    } else if (cols == 4 || cols >= 8) {
        var fontSize = '1rem';
    } else if (cols == 6) {
        var fontSize = '1.35rem';
    } else if (cols = 7) {
        var fontSize = '1.15rem';
    }
    $('body').get(0).style.setProperty('--thumbtextcoverfontsize', fontSize);
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
    // NOTE: SESSION.json['lib_active_search'] is set in the following:
    // - scripts-panels.js $('##context-menu-saved-search-contents a').click
    // - sctipts-library.js $('#genreheader, #library-header').on('click'

    // Clear filtered libcache files (_folder, _format, _tag)
    $.post('command/music-library.php?cmd=clear_libcache_filtered', function() {
        // Apply new filter
        $.post('command/cfg-table.php?cmd=upd_cfg_system',
            //{'library_flatlist_filter': filterType,
            {'library_flatlist_filter': SESSION.json['library_flatlist_filter'],
            'library_flatlist_filter_str': SESSION.json['library_flatlist_filter_str'],
            'lib_active_search': SESSION.json['lib_active_search']},
            function() {
            LIB.recentlyAddedClicked = false;
            LIB.filters.genres.length = 0;
        	LIB.filters.artists.length = 0;
        	LIB.filters.albums.length = 0;
    		LIB.artistClicked = false;
            LIB.albumClicked = false;
            $('#tracklist-toggle').html('<i class="fa-regular fa-sharp fa-list sx"></i> Show tracks');
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

// Playback info
// - Main menu, Audio info
// - Button on Renderer overlay
function audioInfoPlayback() {
    var cmd = MPD.json['artist'] == 'Radio station' ? 'station_info' : 'track_info';
    audioInfo(cmd, MPD.json['file'], 'playback');
}
// Track/Station/Playback info
// cmd = info type, path = song file path, tab = 'playback' for m menu Audio info
function audioInfo(cmd, path, activeTab = '') {
    if (!UI.mobile) {
        $('#audioinfo-modal').css('min-width', '45em');
    }

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
                var coverUrl = data[i][key] = '' ? DEFAULT_ALBUM_COVER : '/imagesw/thmcache/' + encodeURIComponent(data[i][key]) + '.jpg';
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
    GLOBAL.npIcon = getKeyOrValue('value', SESSION.json['show_npicon']);

    if (SESSION.json['show_npicon'] != 'None') {
        if (typeof(MPD.json['song']) != 'undefined') {
            $('.playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').removeClass('no-npicon');
            $('.cv-playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').removeClass('no-npicon');
            if (MPD.json['state'] == 'play') {
                $('#ss-extra-metadata-output-format').text(MPD.json['output']).addClass('ss-npicon');
            }
        }
        // Track in Library
        $('#songsList .lib-entry-song .songtrack').removeClass('lib-track-npicon');
        if (MPD.json['artist'] != 'Radio station' && $('#songsList li').length > 0) {
            for (i = 0; i < filteredSongs.length; i++) {
                if (filteredSongs[i].title == MPD.json['title'] && filteredSongs[i].album == MPD.json['album'] && 1 * filteredSongs[i].tracknum == 1 * MPD.json['track'] && (MPD.json['disc'] == "Disc tag missing" || 1 * filteredSongs[i].disc == 1 * MPD.json['disc'])) {
                    $('#lib-song-' + (i + 1) + ' .lib-entry-song .songtrack').addClass('lib-track-npicon');
                    break;
                }
            }
        }
    } else {
        if (typeof(MPD.json['song']) != 'undefined') {
            $('.playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('no-npicon');
            $('.cv-playqueue li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('no-npicon');
            $('#ss-extra-metadata-output-format').removeClass('ss-npicon');
        }
        $('#songsList .lib-entry-song .songtrack').removeClass('lib-track-npicon');
    }
}

// Receivers modal
function updateRxVolDisplay(selector, value) {
    selector = selector.slice(0, -7); // Remove '-slider' leaving ...-vol
    $('#' + selector).text(value);
}

// Auto-click SET button for Configs
// The delay allows the radio button slide animation to fully complete on touch devices
function autoClick(selector) {
    setTimeout(function() {
        $(selector).click();
    }, 250);
}

// Return HD badge text for Library and Playback views
function albumHDBadge(format) {
    format = typeof(format) == 'undefined' ? '???' : format;
    return format.slice(0, 3) == 'DSD' ? format : ALBUM_HD_BADGE_TEXT;
}

// Base folder names
function containsBaseFolderName(name) {
    return (~name.indexOf('NAS') || ~name.indexOf('NVME') || ~name.indexOf('RADIO') || ~name.indexOf('SDCARD') || ~name.indexOf('USB'));
}

// Extra metadata tags
function formatExtraTagsString () {
    //var elementDisplay = '';
    var output = '';
    var extraTags = SESSION.json['extra_tags'].replace(/ /g, '').split(','); // Strip out whitespace

    // NOTE: composer may be null, disc may be 'Disc tag missing', encoded may be 'Unknown'
    for (const tag of extraTags) {
        //console.log(tag, MPD.json[tag]);
        if (MPD.json[tag] != null && MPD.json[tag] != 'Disc tag missing' && MPD.json[tag] != 'Unknown' && MPD.json[tag] != '') {
            var displayedTag = tag == 'track' ? 'Track' : (tag == 'disc' ? 'Disc' : '');
            output += displayedTag + ' ' + MPD.json[tag] + ' • ';
        }
    }

    output = output.slice(0, -3); // Strip trailing bullet
    return output;
}

// Delete station from RADIO.json object array
function deleteRadioStationObject (stationName) {
    for (let [key, value] of Object.entries(RADIO.json)) {
        if (value.name == stationName) {
            delete RADIO.json[key];
        }
    }
}

// Return key or value from included map tables
// NOTE: This works only if all keys and values are unique
function getKeyOrValue (type, item) {
    let mapTable = new Map([
        // Screen saver timeout
        ['Never','Never'],['1 minute','60'],['2 minutes','120'],['5 minutes','300'],['10 minutes','600'],['20 minutes','1200'],['30 minutes','1800'],['1 hour','3600'],
        // Library recently added
        ['1 Week','604800000'],['1 Month','2592000000'],['3 Months','7776000000'],['6 Months','15552000000'],['1 Year','31536000000'],['No limit','3153600000000'],
        // Library cover search priority
        ['Embedded','Embedded cover'],['Cover file','Cover image file'],
        // Font size factors
        ['Smaller',.35],['Small',.40],['Normal',.45],['Large',.55],['Larger',.65],['X-Large',.75],
        // Sample rate display options
        ['No (searchable)',0],['HD only',1],['Text',2],['Badge',3],['No',9],
        // Radioview station types
        ['Regular','r'],['Favorite','f'],['Hidden','h'],
        // Thumbnail resolutions
        ['Auto','Auto'],['400px','400px,75'],['500px','500px,60'],['600px','600px,60'],
        // Players >> group actions
        ['Shutdown','poweroff'],['Restart','reboot'],['Update library','update_library'],
        // Root folder icons
        ['NAS','fa-server'],['NVME','fa-memory'],['RADIO','fa-microphone'],['SDCARD','fa-sd-card'],['USB','fa-usb-drive'],
        // Now-playing icon
        ['None','None'],['Waveform','waveform'],['Equalizer (Animated)','equalizer'],
        // View -> Item position
        ['radio','radio_pos'],['folder','folder_pos'],['tag','lib_pos'],['album','lib_pos'],['playlist','playlist_pos']
    ]);

    if (type == 'value') {
        var result = mapTable.get(item);
    } else if (type == 'key') {
        for (let [key, value] of mapTable) {
            if (value == item) {
                var result = key;
                break;
            }
        }
    }

    return result;
}
