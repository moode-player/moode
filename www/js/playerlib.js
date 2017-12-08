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
 * 2017-11-26 TC moOde 4.0
 *
 */

var UI = {
    playList: null,
    knob: null,
    path: '',
	bootTicker: '',
	restart: '',
	pagePos: 'playlist', // page position on playback panel when ui is vertical
	lastSong: 'fxdnjkfw',
	defCover: 'images/default-cover-v5.jpg',
	knobPainted: false,
	chipOptions: '',
    dbPos: [0,0,0,0,0,0,0,0,0,0,0],
    dbEntry: ['', '', '', '', '']

	// dbEntry[1] and [2] used in bootstrap.contextmenu.js
	// dbEntry[3] ui row num of song item so highlight can be removed after context menu action
	// dbEntry[4] num playlist items for use by delete/move item modals
};

// mpd state and metadata
var MPD = {
	json: 0	
};

// shairport-sync state and metadata
var SPS = {
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

// library
var LIB = {
	totalSongs: 0,
	albumClicked: false,
	totalTime: 0,
	filters: {artists: [], genres: [], albums: []}
};

function debugLog(msg)  {
	if (SESSION.json['debuglog'] == '1') {
		console.log(Date.now() + ': ' + msg);
	}
}

// coverart.php by AG
function makeCoverUrl(filepath) {
    var cover = '/coverart.php/' + encodeURIComponent(filepath);
    return cover;            
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

// moode commands
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
			obj = JSON.parse(result);
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
        // server returned some data
		success: function(data) {
			debugLog('engineMpd: success branch: data=(' + data + ')');
			
			// always have valid json
			try {
				MPD.json = JSON.parse(data);
			}			
			catch (e) {
				MPD.json['error'] = e;
			}

			// no errors			
			if (typeof(MPD.json['error']) === 'undefined') {
				hideReconnect();
				debugLog('engineMpd: idle_timeout_event=(' + MPD.json['idle_timeout_event'] + ')');

				// mpd restarted via udev usb audio plug-in event rule
				if (MPD.json['idle_timeout_event'] === '') {
					location.reload(true);
				}
				// volume change, update knob if Airplay not active
				else if (MPD.json['idle_timeout_event'] === 'changed: mixer' && SESSION.json['airplayactv'] == '0') {
					renderUIVol();
				} 
				else {
					renderUI();
				}

				engineMpd();

			}
			// error of some sort
			else {
				setTimeout(function() {
					// - client connects before mpd started by worker
					// - various other network issues
					debugLog('engineMpd: success branch: error=(' + MPD.json['error'] + '), module=(' + MPD.json['module'] + ')');
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

// shairport-sync metadata engine
function engineSps() {	
    $.ajax({
		type: 'GET',
		url: 'engine-sps.php?state=' + SPS.json['state'],
		async: true,
		cache: false,
		success: function(data) {
			debugLog('engineSps: success branch: data=(' + data + ')');

			// always have valid json
			try {
				SPS.json = JSON.parse(data);
			}			
			catch (e) {
				SPS.json['error'] = e;
			}

			renderSpsUI();
			engineSps();
		},

		error: function(data) {
			debugLog('engineSps: error branch: data=(' + JSON.stringify(data) + ')');
		}

    });
}

// reconnect, reboot/poweroff with boot-ready ticker
function renderReconnect() {
	debugLog('renderReconnect: UI.restart=(' + UI.restart + ')');

	if (UI.restart == 'reboot') {
		$('#reboot').show();
		$('#bootready').html(UI.bootTicker += '<i class="icon-stop"> </i>');
	}
	else if (UI.restart == 'poweroff') {
		$('#poweroff').show();
	}
	else {
		$('#reconnect').show(); 
	}
	
	$('#countdown-display').countdown('pause');
	window.clearInterval(UI.knob);	
}

function hideReconnect() {
	$('#reconnect').hide();
	$('#reboot').hide();
	$('#poweroff').hide();
	$('#bootready').html('');
}

// update UI with airplay metadata
function renderSpsUI() {
	debugLog('renderSpsUI');

	// time knob
	$('#countdown-display').css({"font-size":"24px"});
	$('#countdown-display').css({"margin-top":"-13px"});
	$('#countdown-display').html('AIRPLAY');

	// playlist
	$('.playlist li').removeClass('active');

	// cover art
	$('#coverart-url').html('<img class="coverart" ' + 'src="' + SPS.json['imgurl'] + '" ' + 'alt="Cover art not found"' + '>');

	// metadata
	$('#extratags').html('Airplay session active');
	$('#currentartist').html(SPS.json['artist']);
	$('#currentsong').html(SPS.json['title']);
	$('#currentalbum').html(SPS.json['album']);

	// reset this so cover art is redisplayed when resuming MPD playback
	UI.lastSong = '';
}

// update UI volume and mute only 
function renderUIVol() {	
	debugLog('renderUIVol');

	// load session vars (required for multi-client)
	var resp = sendMoodeCmd('GET', 'readcfgengine');
	if (resp !== false) {
		SESSION.json = resp;
	}

/* ORIGINAL CODE
	// NOTE mpd sets vol to -1 when volume mixer 'disabled'
    $('#volume').val((MPD.json['volume'] === '-1') ? 0 : SESSION.json['volknob']).trigger('change');
	//$('#volume-2').val((MPD.json['volume'] === '-1') ? 0 : SESSION.json['volknob']).trigger('change');
*/
	// NOTE mpd sets vol to -1 when volume mixer 'disabled'
	if (MPD.json['volume'] != '-1') {
	    $('#volume').val(SESSION.json['volknob']).trigger('change');
	}

   	// mute state
	if (SESSION.json['volmute'] === '0') {
		$('#volumemute').removeClass('btn-primary');
		//$('#volumemute-2').removeClass('btn-primary');
	} else {
		$('#volumemute').addClass('btn-primary');
		//$('#volumemute-2').addClass('btn-primary');
	}
}

// update UI with mpd metadata
function renderUI() {
	debugLog('renderUI');

	var searchStr, searchEngine;

	// load session vars (required for multi-client)
	var resp = sendMoodeCmd('GET', 'readcfgengine');
	if (resp !== false) {
		SESSION.json = resp;
	}

/* ORIGINAL CODE
	// NOTE mpd sets vol to -1 when volume mixer 'disabled'
    $('#volume').val((MPD.json['volume'] === '-1') ? 0 : SESSION.json['volknob']).trigger('change');
	//$('#volume-2').val((MPD.json['volume'] === '-1') ? 0 : SESSION.json['volknob']).trigger('change');
*/
	// NOTE mpd sets vol to -1 when volume mixer 'disabled'
	if (MPD.json['volume'] != '-1') {
	    $('#volume').val(SESSION.json['volknob']).trigger('change');
	}
    //$('#volume').val(val).trigger('change');
	//$('#volume-2').val((MPD.json['volume'] === '-1') ? 0 : SESSION.json['volknob']).trigger('change');

   	// mute state
	if (SESSION.json['volmute'] === '0') {
		$('#volumemute').removeClass('btn-primary');
		//$('#volumemute-2').removeClass('btn-primary');
	} else {
		$('#volumemute').addClass('btn-primary');
		//$('#volumemute-2').addClass('btn-primary');
	}

	// playback controls, playlist highlight, knob #total + icon
    if (MPD.json['state'] === 'play') {
		$("#play i").removeClass("icon-play").addClass("icon-pause");
        $('#total').html(updKnobSongTime(MPD.json['time']));
		$('.playlist li').removeClass('active');
        $('.playlist li:nth-child(' + (parseInt(MPD.json['song']) + 1) + ')').addClass('active');
    } else if (MPD.json['state'] === 'pause') {
		$("#play i").removeClass("icon-pause").addClass("icon-play");
        $('#total').html(updKnobSongTime(MPD.json['time']));
    } else if (MPD.json['state'] === 'stop') {
        $('#play i').removeClass('icon-pause').addClass('icon-play');
        $('#total').html(updKnobSongTime(MPD.json['time']));
    }

	// if neither airplay nor squeezelite are active then go ahead with these mpd ui updates
	if (SESSION.json['airplayactv'] != '1') {
		// coverart and search url
		if (MPD.json['file'] !== UI.lastSong) { // prevent unnecessary image reloads (clicking on same item)
			if (MPD.json['title'].substr(0, 4) === 'http' || MPD.json['coverurl'] === UI.defCover) {
				$('#coverart-url').html('<img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'alt="Cover art not found"' + '>');
			} else {
				if (MPD.json['artist'] === 'Radio station') {
					searchStr = MPD.json['title'].replace(/-/g, " ");
					searchStr = searchStr.replace(/&/g, " ");
					searchStr = searchStr.replace(/\s+/g, "+");
				} else {
					searchStr = MPD.json['artist'] + "+" + MPD.json['album']				
				}
				searchEngine = "http://www.google.com/search?q=";
				$('#coverart-url').html('<a id="coverart-link" href=' + '"' + searchEngine + searchStr + '"' + 
				' target="_blank"> <img class="coverart" ' + 'src="' + MPD.json['coverurl'] + '" ' + 'alt="Cover art not found"' + '></a>');
			}
		}
	
		// extra metadata
		if (SESSION.json['xtagdisp'] === 'Yes') {
			var extraTags = MPD.json['track'] ? 'Track ' + MPD.json['track'] : '';
			extraTags += MPD.json['date'] ? '&nbsp;&bull; Year ' + MPD.json['date'] : '';
			extraTags += MPD.json['composer'] ? '&nbsp;&bull; ' + MPD.json['composer'] : '';
			
			if (MPD.json['artist'] === 'Radio station') {
				extraTags += MPD.json['bitrate'] ? '&nbsp;&bull; ' + MPD.json['bitrate'] : '';
			} else {
				// with just sample rate
				//extraTags += MPD.json['encoded'] ? (MPD.json['encoded'] != 'Unknown' ? '&nbsp&bull; ' + MPD.json['encoded'] : '') : '';

				// with bitrate and audio format added, see getEncodedAt()
				extraTags += MPD.json['encoded'] ? (MPD.json['encoded'] != 'Unknown' ? '&nbsp;&bull;&nbsp;' + MPD.json['encoded'] : '') : '';
			}

			$('#extratags').html(extraTags);	
		} else {
			$('#extratags').html('');	
		}
		
		// default metadata
		$('#currentartist').html(MPD.json['artist']);
		$('#currentsong').html(MPD.json['title']);
		$('#currentalbum').html(MPD.json['album']);
	}

    // scrollto if song change
    if (MPD.json['file'] !== UI.lastSong) {
        countdownRestart(0);
        if ($('#open-playback-panel').hasClass('active')) {
			// ORIGINAL
            var current = parseInt(MPD.json['song']);
            customScroll('pl', current);
        }
    }
	
	UI.lastSong = MPD.json['file'];

    // playback option btns 
    MPD.json['repeat'] === '1' ? $('#repeat').addClass('btn-primary') : $('#repeat').removeClass('btn-primary');
    MPD.json['consume'] === '1' ? $('#consume').addClass('btn-primary') : $('#consume').removeClass('btn-primary');
    MPD.json['single'] === '1' ? $('#single').addClass('btn-primary') : $('#single').removeClass('btn-primary');
	// auto-shuffle
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

	// time knob
	// count up or down, radio stations always have song time = 0
	if (SESSION.json['timecountup'] === '1' || parseInt(MPD.json['time']) === 0) {
		refreshTimer(parseInt(MPD.json['elapsed']), parseInt(MPD.json['time']), MPD.json['state']);
	} else {
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

	// update playlist if indicated
	if (MPD.json['playlist'] !== UI.playList) {
		renderPlaylist();
		UI.playList = MPD.json['playlist'];
	}

	// if airplay active, ensure sps ui in case mpd ui updates get there first after browser refresh
	if (SESSION.json['airplayactv'] == '1') {
		renderSpsUI();
	}

	// squeezelite indicator
	if (SESSION.json['slsvc'] == '1') {
		$('#countdown-display').css({"font-size":"20px"});
		$('#countdown-display').css({"margin-top":"-13px"});
		$('#countdown-display').html('Squeezelite');
		$('#extratags').html('Squeezelite session active');

		// playlist
		$('.playlist li').removeClass('active');
	
		// metadata
		$('#currentartist').html(SPS.json['artist']);
		$('#currentsong').html(SPS.json['title']);
		$('#currentalbum').html(SPS.json['album']);
	
		// reset this so cover art is redisplayed when resuming MPD playback
		UI.lastSong = '';
	}

	// TEST
	// bluetooth indicator
	if (MPD.json['btactive'] == '1') {
		$('#countdown-display').css({"font-size":"20px"});
		$('#countdown-display').css({"margin-top":"-13px"});
		$('#countdown-display').html('Bluetooth');
		$('#extratags').html('Bluetooth session active');

		// playlist
		$('.playlist li').removeClass('active');

		// reset this so cover art is redisplayed when resuming MPD playback
		UI.lastSong = '';
	}

	// show/clear db update in progress icon
	if (typeof MPD.json['updating_db'] !== 'undefined') {
		$('.open-browse-panel').html('<i class="icon-refresh icon-spin"></i> Updating');
	} else {
		$('.open-browse-panel').html('Browse');
	}

	// for small screens	
    if (UI.pagePos === 'knobs') {
		$('html, body').animate({scrollTop: $('#timeknob').offset().top - 30}, 200);
    } else if (UI.pagePos === 'coverart') {
		$('html, body').animate({scrollTop: $('.covers').offset().top - 30}, 200);
    }
}

function renderPlaylist() {
	debugLog('renderPlaylist');

	// legacy code: turn playlist off if random play through huge pl in browser causes performance issue
	if (SESSION.json['pldisp'] == "No") {
		$('.playlist').html('<div style="height: 600px; vertical-align: middle; font-size: 18px; color: #2c3e50;">PLAYLIST DISPLAY OFF</div>');
		//$('.playlist').html('<img src="/images/some-image.png">');
		return;
	}

    $.getJSON('command/moode.php?cmd=playlist', function(data) {
		var output = '';
        
        // save item num for use in delete/move modals
        UI.dbEntry[4] = typeof(data.length) === 'undefined' ? 0 : data.length;

		// format playlist items
        if (data) {
            for (i = 0; i < data.length; i++) {
	            // item active state, don't highlight if airplay session active
                if (i == parseInt(MPD.json['song']) && SESSION.json['airplayactv'] != '1') {
                    output += '<li id="pl-' + (i + 1) + '" class="active clearfix">';
                    //output += '<li id="pl-' + (i + 1) + '" class="active clearfix" draggable="true" ondragstart="drag(event)">';
                } else {
                    output += '<li id="pl-' + (i + 1) + '" class="clearfix">';
                    //output += '<li id="pl-' + (i + 1) + '" class="clearfix" draggable="true" ondragstart="drag(event)">';
                }
				// action menu
				output += '<div class="pl-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-playlist-item"><i class="icon-ellipsis-horizontal"></i></a></div>';

				// itunes aac file
				if (typeof(data[i].Name) !== 'undefined' && data[i].file.substr(data[i].file.lastIndexOf('.') + 1).toLowerCase() == 'm4a') {
	                // line 1 title
	                output += '<div class="pl-entry">';
                    output += data[i].Name + (typeof(data[i].Time) == 'undefined' ? '<em class="songtime"></em>' : ' <em class="songtime">' + formatSongTime(data[i].Time) + '</em>');
					// line 2 artist, album
					output += ' <span>';
					output += (typeof(data[i].Artist) === 'undefined') ? 'Unknown artist' : data[i].Artist;
					output += " - ";
					output += (typeof(data[i].Album) === 'undefined') ?  'Unknown album' : data[i].Album;
					
				// radio station
				} else if (typeof(data[i].Name) !== 'undefined' || (data[i].file.substr(0, 4) == 'http' && typeof(data[i].Artist) === 'undefined')) {
	                // line 1 title
	                output += '<div class="pl-entry">';

					// use custom name for particular station
	                if (typeof(data[i].Title) === 'undefined' || data[i].Title.trim() == '' || data[i].file == 'http://stream.radioactive.fm:8000/ractive') {
						output += 'Streaming source';
					} else {
						output += data[i].Title;
						if (i == parseInt(MPD.json['song'])) { // active
							if (SESSION.json['airplayactv'] != '1') { // airplay session not active
								$('#currentsong').html(data[i].Title); // TC update in case mpd did not get Title tag at initial play
							}
						}
					}
					
					// line 2, station name
					output += ' <span>';
					output += '<i class="icon-microphone"></i> ';
					
					if (typeof(RADIO.json[data[i].file]) === 'undefined') {
						output += (typeof(data[i].Name) === 'undefined') ? 'Radio station' : data[i].Name;
					} else {
						output +=  RADIO.json[data[i].file]['name'];
					}
					
				// song file or upnp url	
				} else {
	                // line 1 title
	                output += '<div class="pl-entry">';
					if (typeof(data[i].Title) === 'undefined') { // use file name
						var pos = data[i].file.lastIndexOf('.');
						
						if (pos == -1) {
							output += data[i].file; // some upnp url's have no file ext
						} else {
							var filename = data[i].file.slice(0, pos);
							pos = filename.lastIndexOf('/');
							output += filename.slice(pos + 1); // song filename (strip .ext)
						}
					} else { // use title
	                    output += data[i].Title + (typeof(data[i].Time) === 'undefined' ? '<em class="songtime"></em>' : ' <em class="songtime">' + formatSongTime(data[i].Time) + '</em>');
					}	                
					// line 2 artist, album
					output += ' <span>';
					output += (typeof(data[i].Artist) === 'undefined') ? 'Unknown artist' : data[i].Artist;
					output += ' - ';
					output += (typeof(data[i].Album) === 'undefined') ?  'Unknown album' : data[i].Album;
				}

                output += '</span></div></li>';
            } // end loop
        }
		
		// render playlist
        $('ul.playlist').html(output);

		// TEST
		// scroll to current song
		//var current = parseInt(MPD.json['song']);
		//customScroll('pl', current, 200);
    });
}

// MPD database commands (tag_Cache, playlist, radio stations, saved playlists, search)
function MpdDbCmd(cmd, path, browsemode, uplevel) {
	var cmds = ['add', 'play', 'clrplay', 'addall', 'playall', 'clrplayall', 'update'];

	if (cmds.indexOf(cmd) != -1 ) {
		$.post('command/moode.php?cmd=' + cmd, {'path': path}, function(path) {}, 'json');

	} else if (cmd == 'filepath' || cmd == 'listsavedpl') {
		$.post('command/moode.php?cmd=' + cmd, {'path': path}, function(data) {renderBrowse(data, path, uplevel);}, 'json');

	} else if (cmd == 'delsavedpl') {
		$.post('command/moode.php?cmd=' + cmd, {'path': path}, function(data) {}, 'json');
		$.post('command/moode.php?cmd=filepath', {'path': ''}, function(data) {renderBrowse(data, '', 0);}, 'json');

	} else if (cmd == 'addstation' || cmd == 'updstation') {
		var arg = path.split("\n");
		var rtn = sendMoodeCmd('POST', 'addstation', {'path': arg[0], 'url': arg[1]});
		$.post('command/moode.php?cmd=filepath', { 'path': 'RADIO' }, function(data) {renderBrowse(data, 'RADIO', 0);}, 'json');

	} else if (cmd == 'delstation') {
		var rtn = sendMoodeCmd('POST', cmd, {'path': path});
		$.post('command/moode.php?cmd=filepath', {'path': 'RADIO'}, function(data) {renderBrowse(data, 'RADIO', 0);}, 'json');

	// if no search keyword, dont post, clear search tally
	} else if (cmd == 'search') {
		var keyword = $('#db-search-keyword').val();

		if (keyword != '') {
			$.post('command/moode.php?querytype=' + browsemode + '&cmd=' + cmd, {'query': keyword}, function(data) {renderBrowse(data, path, uplevel, keyword);}, 'json');
		} else {
			$('#db-filter-results').html('');		
		}
	}	
}

function renderBrowse(data, path, uplevel, keyword){
	if (path) {
		UI.path = path;
	}
	// sort radio station list	
	if (typeof(data[0]) != 'undefined') {
		if (typeof(data[0].file) != 'undefined' && data[0].file.substring(0, 5) == "RADIO") {
			sortJsonArrayByProperty(data, 'file')
		}
	}

	// format search tally, clear results and search field when back btn
	var dbList = $('ul.database');
	dbList.html('');
	
	if (keyword) {
		var results = (data.length) ? data.length : '0';
		var s = (data.length == 1) ? '' : 's';
		var text = results + ' item' + s;
		$("#db-back").show();
		$("#db-filter-results").html(text);
	} else if (path != '') {
		$("#db-back").show();
		$("#db-filter-results").html('');
		$("#db-search-keyword").val('');
		$("#rs-filter").val('');
	} else { // back to db root
        $("#db-back").hide();
		$("#db-filter-results").html('');
		$("#db-search-keyword").val('');
		$("#rs-filter").val('');
		// unhide db search field
        $('#rs-search-input').addClass('hidden');
        $('#db-search-input').removeClass('hidden');
        $('#db-search').removeClass('db-form-hidden');
		// close toolbars when back to db root        
		$('.btnlist-top-db').addClass('hidden');
		$('.btnlist-bottom-db').addClass('hidden');
		//$('#database').css({"padding":"40px 0"});
		$('#database').css({"top":"40px"}); // TC testing
		$('#lib-content').css({"top":"40px"});
	}

	var output = '';
	
	// render browse panel
	for (i = 0; i < data.length; i++) {
		output = parseMpdResp(data, path, i);
	 	dbList.append(output);
	}

	// scroll
	$('#db-currentpath span').html(path);
	customScroll('db', UI.dbPos[UI.dbPos[10]], 100);
	$('#db-' + UI.dbPos[UI.dbPos[10]].toString()).addClass('active');
}

// json object sort by property
// author: Anthony Ryan Delorie, stackoverflow.com
function sortJsonArrayByProperty(obj, prop, direction) {
    if (arguments.length<2) throw new Error("sortJsonArrayByProp requires 2 arguments");
    var direct = arguments.length>2 ? arguments[2] : 1; //Default to ascending

    if (obj && obj.constructor===Array) {
        var propPath = (prop.constructor===Array) ? prop : prop.split(".");
        obj.sort(function(a,b) {
            for (var p in propPath) {
                if (a[propPath[p]] && b[propPath[p]]) {
                    a = a[propPath[p]].toLowerCase();
                    b = b[propPath[p]].toLowerCase();
					//debugLog('a, b=', a + ', ' + b);
                }
            }
            // convert numeric strings to integers
            a = a.match(/^\d+$/) ? +a : a;
            b = b.match(/^\d+$/) ? +b : b;
            return ((a < b) ? -1*direct : ((a > b) ? 1*direct : 0));
        });
    }
}

// parse response from mpd db commands
function parseMpdResp(data, path, i) {
	var output = '';
		
	if (path == '' && typeof data[i].file != 'undefined') {
		var pos = data[i].file.lastIndexOf('/');
		
		if (pos == -1) {
			path = '';
		} else {
			path = data[i].file.slice(0, pos);
		}
	}
	
	if (typeof data[i].file != 'undefined') {
		// for cue sheet and future extensions
		var fileExt = data[i].file.substr(data[i].file.lastIndexOf('.') + 1).toLowerCase();
	
		if (typeof data[i].Title != 'undefined') {
			output = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';
			output += data[i].file;
			output += '"><div class="db-icon db-song db-browse"><i class="icon-music sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-folder-item"><i class="icon-ellipsis-horizontal"></i></a></div><div class="db-entry db-song db-browse">';
			output += data[i].Title + ' <em class="songtime">' + formatSongTime(data[i].Time) + '</em>';
			output += ' <span>';
			output +=  data[i].Artist;
			output += ' - ';
			output +=  data[i].Album;
			output += '</span></div></li>';
		} else {
			output = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';

			// remove file extension, except if its url (savedplaylist can contain url's)
			var filename = '';

			if (data[i].file.substr(0,4) == "http") {
				filename = data[i].file;
			} else {
				cutpos = data[i].file.lastIndexOf(".");
	            if (cutpos !=-1) {
	            	filename = data[i].file.slice(0,cutpos);
				}
	        }

			output += data[i].file;

			// different icon for song file vs radio station in saved playlist
			var itemType = '';

			if(data[i].file.substr(0, 5) == "RADIO") {
				output += '"><div class="db-icon db-song db-browse"><i class="icon-microphone sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-radio-item"><i class="icon-ellipsis-horizontal"></i></a></div><div class="db-entry db-song db-browse">';
				itemType = "Radio station";
			// cue sheet, use song file action menu
			} else if (fileExt == "cue") {
				output += '"><div class="db-icon db-song db-browse"><i class="icon-list-ul icon-root sx"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-folder-item"><i class="icon-ellipsis-horizontal"></i></a></div><div class="db-entry db-song db-browse">';
				itemType = "Cue sheet";
			} else {
				// different icon and file type text
				if (data[i].file.substr(0,4) == "http") {
					output += '"><div class="db-icon db-song db-browse"><i class="icon-microphone sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-savedpl-item"><i class="icon-ellipsis-horizontal"></i></a></div><div class="db-entry db-song db-browse">';
					itemType = "Radio station";
				} else {
					output += '"><div class="db-icon db-song db-browse"><i class="icon-music sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-savedpl-item"><i class="icon-ellipsis-horizontal"></i></a></div><div class="db-entry db-song db-browse">';
					itemType = "Song file";
				}
			}

			output += filename.replace(path + '/', '');
			output += ' <span>';
			output += itemType;
			output += '</span></div></li>';
		}
	// handle saved playlist 
	} else if (typeof data[i].playlist != 'undefined') {
		// skip .wv (WavPack) files, apparently they can contain embedded playlist
		if (data[i].playlist.substr(data[i].playlist.lastIndexOf('.') + 1).toLowerCase() == 'wv') {
			output= '';
		} else {
			output = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';
			output += data[i].playlist;
			output += '"><div class="db-icon db-folder db-browse"><i class="icon-list-ul icon-root sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-savedpl-root"><i class="icon-ellipsis-horizontal"></i></a></div><div class="db-entry db-savedplaylist db-browse">';
			output += data[i].playlist;
			output += '</div></li>';
		}
	} else {
		output = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';
		output += data[i].directory;
		
		if (path != '') {
			output += '"><div class="db-icon db-folder db-browse"><i class="icon-folder-open sx"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu"><i class="icon-ellipsis-horizontal"></i></a></div><div class="db-entry db-folder db-browse">';
		} else {
			output += '"><div class="db-icon db-folder db-browse"><i class="icon-hdd icon-root sx"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-root"><i class="icon-ellipsis-horizontal"></i></a></div><div class="db-entry db-folder db-browse">';
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

	output = songTime + (SESSION.json['timecountup'] == "1" || parseInt(MPD.json['time']) == 0 ? '<i class="icon-caret-up countdown-caret"></i>' : '<i class="icon-caret-down countdown-caret"></i>');

    return output;
}

// update countdown
// - count up or down depending on conf setting, radio always couts up
// - add onTick and chg format 'MS' to 'hMS'
function refreshTimer (startFrom, stopTo, state) {
    if (state == 'play' || state == 'pause') {
        $('#countdown-display').countdown('destroy');
		if (SESSION.json['timecountup'] == "1" || parseInt(MPD.json['time']) == 0) {
	    	$('#countdown-display').countdown({since: -(startFrom), onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    } else {
	        $('#countdown-display').countdown({until: startFrom, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
	    if (state == 'pause') {
	        $('#countdown-display').countdown('pause');
		}
    } else if (state == 'stop') {
        $('#countdown-display').countdown('destroy');
		if (SESSION.json['timecountup'] == "1" || parseInt(MPD.json['time']) == 0) {
        	$('#countdown-display').countdown({since: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    } else {
	        $('#countdown-display').countdown({until: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
	    }
        $('#countdown-display').countdown('pause');
    }
}

// automatic font size adjustment (onTick callback)
function watchCountdown(period) {
	// hours > 0 reduce font-size so time fits nicely within knob
	if (period[4] > 0) {
		if (period[4] > 9) { // 2 digits
			$('#countdown-display').css({"font-size":"24px"});
			$('#countdown-display').css({"margin-top":"-13px"});
		}
		else { // 1 digit
			$('#countdown-display').css({"font-size":"28px"});
			$('#countdown-display').css({"margin-top":"-15px"});
		}
	}
	else {
		$('#countdown-display').css({"font-size":"36px"});
		$('#countdown-display').css({"margin-top":"-19px"});
	}
}

// update time knob
function refreshTimeKnob() {
	var initTime, delta;
	
    window.clearInterval(UI.knob)
    initTime = parseInt(MPD.json['song_percent']);
    delta = parseInt(MPD.json['time']) / 1000;

    $('#time').val(initTime * 10).trigger('change');
    if (MPD.json['state'] === 'play') {
        UI.knob = setInterval(function() {
            delta === 0 ? initTime = initTime + 0.5 : initTime = initTime + 0.1; // fast paint when radio station playing
            if (delta === 0 && initTime > 100) { // stops painting when radio (delta = 0) and knob fully painted
				window.clearInterval(UI.knob)
				UI.knobPainted = true;
            }
            $('#time').val(initTime * 10).trigger('change');
        }, delta * 1000);
    }
}

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

// reset countdown time
function countdownRestart(startFrom) {
    $('#countdown-display').countdown('destroy');
    $('#countdown-display').countdown({since: (startFrom), onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
}

// volume control
function setVolume(level, event) {
	level = parseInt(level); // ensure numeric
	
	// unmuted, set volume (incl 0 vol)
	if (SESSION.json['volmute'] == '0') {
		SESSION.json['volknob'] = level.toString();
		var result = sendMoodeCmd('POST', 'updcfgengine', {'volknob': SESSION.json['volknob']});
	    sendMpdCmd('setvol ' + level.toString());
    }
	// muted
	else {	    
		if (level == 0 && event == 'mute')	{
		    sendMpdCmd('setvol 0');
		} 
		else {
			// vol up/dn btns pressed, just store the volume for display
			SESSION.json['volknob'] = level.toString();
		}

		var result = sendMoodeCmd('POST', 'updcfgengine', {'volknob': SESSION.json['volknob']});
    }
}

// scroll item so visible
function customScroll(list, itemnum, speed) {
	var centerHeight, scrollTop, itemHeight, scrollCalc, scrollOffset, itemPos;

    if (typeof(speed) === 'undefined') {
	    speed = 500;
	}
	
    if (list == 'db') {
	    centerHeight = parseInt($(window).height()/3);
	    scrollTop = $(window).scrollTop();
	    itemHeight = parseInt(1 + $('#db-1').height());
        scrollCalc = parseInt((itemnum * itemHeight) - centerHeight + 40);
        
	    if (scrollCalc > 0) {
	        $('#database').scrollTo(scrollCalc , speed);
	    }
		else {
	        $('#database').scrollTo(0 , speed);
	    }
    }
	else if (list == 'pl') {		
		if (isNaN(itemnum)) { // exit if last item in pl ended
	        return;
		}			    
        
        if ($('#playlist ul li:nth-child(' + (itemnum + 1) + ')').position() != undefined) {
			itemPos = $('#playlist ul li:nth-child(' + (itemnum + 1) + ')').position().top;
        }
		else {
			itemPos = 0;
        }

	    centerHeight = parseInt($('#container-playlist').height()/3); // place in upper third instead of middle
	    scrollTop = $('#container-playlist').scrollTop();
        scrollCalc = (itemPos + scrollTop) - centerHeight;

        if (scrollCalc > scrollTop) {
            scrollOffset = '+=' + Math.abs(scrollCalc - scrollTop) + 'px';
        }
		else {
            scrollOffset = '-=' + Math.abs(scrollCalc - scrollTop) + 'px';
        }
	    if (scrollCalc > 0) {
	        $('#container-playlist').scrollTo( scrollOffset , speed );
	    }
		else {
	        $('#container-playlist').scrollTo( 0 , speed );
	    }
    }
}

// change Library tab icon to provide feedback when code is long running
// NOTE needed anymore?
function libbtnIcon(type) {
	if (type == "working") {
		$('.open-library-panel').html('<i class="icon-refresh icon-spin"></i> Library'); // spinner
	} else if (type == "done") {
		$('.open-library-panel').html('Library'); // default
	} else {
		$('.open-library-panel').html('Library'); // place holder
	}
}

// render library
function renderLibrary(data) {
    fullLib = data;
	//debugLog(data);

	// generate library array
    filterLib();
    
    // store total song count
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
    allSongs = [];
	allFiles = [];
    
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
                        var objAlbum = {"album": album, "artist": artist};

                        allAlbumsTmp.push(objAlbum);

                        if (LIB.filters.albums.length == 0 || LIB.filters.albums.indexOf(keyAlbum(objAlbum)) >= 0) {
                            for (var i in fullLib[genre][artist][album]) {
                                var song = fullLib[genre][artist][album][i];

                                song.album = album;
                                song.artist = artist;

                                allSongs.push(song);
                                allFiles.push({'file': song.file});
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
    } else {
        // sorting
        allGenres.sort();
		allArtists.sort(function(a, b) {return removeArticles(a) > removeArticles(b) ? 1 : -1;});
        allAlbumsTmp.sort(function(a, b) {return removeArticles(a['album']) > removeArticles(b['album']) ? 1 : -1;});
		
		// rollup and tag all compilation albums, use allAlbumsTmp to improve efficiency
        var compAlbumStored = false;
		var objCompilationAlbum = {"album": '', "artist": '', "compilation": '1'}; // NOTE the "compilation" tag is used in the onClick for Albums

		if (allAlbumsTmp.length > 1) {			       
			for (var i = 1; i < allAlbumsTmp.length; i++) { // start at 1 since first album starts at 0 now			
				if (allAlbumsTmp[i].album == allAlbumsTmp[i - 1].album && allAlbumsTmp[i].album.toLowerCase() != "greatest hits") { // current = prev -> compilation album
					if (compAlbumStored == false) {
		                objCompilationAlbum = {"album": allAlbumsTmp[i].album, "artist": "Various Artists", "compilation": '1'}; // store compilation album only once (rollup)
		                allAlbums.push(objCompilationAlbum);
		                compAlbumStored = true;
					}
				} else { // current != prev -> lets check 
					if (allAlbumsTmp[i - 1].album == objCompilationAlbum.album) { // prev = last compilation album stored
						objCompilationAlbum = {"album": '', "artist": '', "compilation": '1'}; // don't store it, just reset and move on
					} else {
		                var objRegularAlbum = {"album": allAlbumsTmp[i - 1].album, "artist": allAlbumsTmp[i - 1].artist, "compilation": '0'}; // prev is a regular album, store it 
						allAlbums.push(objRegularAlbum);
					}
	
					if (i == allAlbumsTmp.length - 1) { // last album
						var objRegularAlbum = {"album": allAlbumsTmp[i].album, "artist": allAlbumsTmp[i].artist, "compilation": '0'}; // store last album
						allAlbums.push(objRegularAlbum);
					}
					
					compAlbumStored = false; // reset flag
				}
			}
			
		} else if (allAlbumsTmp.length == 1) { // only one album in list
			var objRegularAlbum = {"album": allAlbumsTmp[0].album, "artist": allAlbumsTmp[0].artist, "compilation": '0'}; // store the one and only album

			allAlbums.push(objRegularAlbum);
		} else {
			// array length is 0 (empty) -> no music source defined
		}
    }
}

// remove artcles from beginning of string
function removeArticles(string) {
	//return string.replace(/^[^a-z0-9]*/gi, '').replace(/^(a|an|the) (.*)/gi, '$2');

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
    return objAlbum.album + "@" + objAlbum.artist;
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
    
    renderAlbums();
}

// render albums
var renderAlbums = function() {
	// clear search filter and results
	$("#lib-album-filter").val('');
	$('#lib-album-filter-results').html('');
	
    var output = '';
    var tmp = '';
    
    for (var i = 0; i < allAlbums.length; i++) {
		// add || allAlbums.length = 1 to automatically highlight if only 1 album in list
	    if (LIB.filters.albums.indexOf(keyAlbum(allAlbums[i])) >= 0 || allAlbums.length == 1) {
		    tmp = ' active';
		    LIB.albumClicked = true; // for renderSongs() so it can decide whether to display tracks	    
	    } else {
		    tmp = '';
	    }
        output += '<li class="clearfix"><div class="lib-entry'
        	+ tmp
			+ '">' + allAlbums[i].album + ' <span> ' + allAlbums[i].artist + '</span></div></li>';
    }

    $('#albumsList').html(output);

    renderSongs();
}

// render songs
var renderSongs = function() {
    var output = '';
	LIB.totalTime = 0;
	
    //if (allSongs.length < LIB.totalSongs) { // only display tracks if less than the whole library
    if (LIB.albumClicked == true) { // only display tracks if album selected
	    LIB.albumClicked = false;

	    for (i = 0; i < allSongs.length; i++) {
	        output += '<li id="lib-song-' + (i + 1) + '" class="clearfix"><div class="lib-entry-song">' + allSongs[i].display
	        	+ '<span class="songtime"> ' + allSongs[i].time2 + '</span>'
	            + '<br><span> ' + allSongs[i].artist + '</span></div>'
	            + '<div class="lib-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-lib-item"><i class="icon-ellipsis-horizontal"></i></a></div>'
	            + '</li>';

				LIB.totalTime += parseSongTime(allSongs[i].time);
	    }
	} else {
	    for (i = 0; i < allSongs.length; i++) {
			LIB.totalTime += parseSongTime(allSongs[i].time);
	    }
	}

    $('#songsList').html(output);
    
  	// Library panel cover art and metadata
	if (allAlbums.length == 1 || LIB.filters.albums != '') {
		$('#lib-albumname').html(allSongs[0].album);
		$('#lib-artistname').html(allSongs[0].artist);		
		$('#lib-numtracks').html(allSongs.length + ((allSongs.length == 1) ? ' track, ' : ' tracks, ') + formatTotalTime(LIB.totalTime));
		$('#lib-coverart-img').html(
			'<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-all">' +
			'<img class="lib-coverart" src="' + makeCoverUrl(allSongs[0].file) + '" ' + 'alt="Cover art not found"' + '></a>'
		);
	} else {
		if (LIB.filters.genres == '') {
			if (LIB.filters.artists == '') {
				var album = 'Music Library';
				var artist = '';
			} else {
				var album = LIB.filters.artists; 
				var artist = '';
			}
		} else {
			var album = LIB.filters.genres;
			var artist = LIB.filters.artists;
		}
		
		$('#lib-albumname').html(album);
		$('#lib-artistname').html(artist);
		$('#lib-numtracks').html(allSongs.length + ((allSongs.length == 1) ? ' track, ' : ' tracks, ') + formatTotalTime(LIB.totalTime));
		$('#lib-coverart-img').html(
			'<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-all">' +
			'<img class="lib-coverart" ' + 'src="' + UI.defCover + '"></a>'
		);
	}
}

// return numeric song time
function parseSongTime (songTime) {
	var time = parseInt(songTime);
	return isNaN(time) ? 0 : time;	
}

// default post-click handler for lib items
function clickedLibItem(event, item, currentFilter, renderFunc) {
	// change to animated icon
	// force a new call stack for potentially long running code, allowing repaint to complete before the new call stack begins execution
	// cred to Brad Daily at Stack Overflow for the window.setTimeout approach
	// http://stackoverflow.com/questions/4005096/force-immediate-dom-update-modified-with-jquery-in-long-running-function
	
	libbtnIcon("working");
	window.setTimeout(function() {
	 
		// begin original code
	    if (item == undefined) {
	        // all
	        currentFilter.length = 0;
	    } else if (event.ctrlKey) {
	        currentIndex = currentFilter.indexOf(item);
	        if (currentIndex >= 0) {
	            currentFilter.splice(currentIndex, 1);
	        } else {
	            currentFilter.push(item);
	        }
	    } else {
	        currentFilter.length = 0;
	        currentFilter.push(item);
	    }
	    	    
	    filterLib();
	    renderFunc();
		// end original code

		// back to default icon
		libbtnIcon("done");
    }, 0);
}

// click on genres header
$('#genreheader').on('click', '.lib-heading', function(e) {
    clickedLibItem(e, undefined, LIB.filters.genres, renderGenres);
	$('#lib-numtracks').css({'color': '#eee'}); // reset color
});

// click on artists header
$('#artistheader').on('click', '.lib-heading', function(e) {
    clickedLibItem(e, undefined, LIB.filters.artists, renderArtists);    
	$('#lib-numtracks').css({'color': '#eee'}); // reset color
});

// click on albums header
$('#albumheader').on('click', '.lib-heading', function(e) {
    clickedLibItem(e, undefined, LIB.filters.albums, renderAlbums);    
	$('#lib-numtracks').css({'color': '#eee'}); // reset color
});

// click on genre
$('#genresList').on('click', '.lib-entry', function(e) {
    var pos = $('#genresList .lib-entry').index(this);
    clickedLibItem(e, allGenres[pos], LIB.filters.genres, renderGenres);
});

// click on artist
$('#artistsList').on('click', '.lib-entry', function(e) {
    var pos = $('#artistsList .lib-entry').index(this);
    clickedLibItem(e, allArtists[pos], LIB.filters.artists, renderArtists);    
});

// click on album
$('#albumsList').on('click', '.lib-entry', function(e) {
    var pos = $('#albumsList .lib-entry').index(this);
    
	// for renderSongs() so it can decide whether to display tracks	    
    LIB.albumClicked = true;

	// different way to handle highlight, avoids regenerating the albums list
    $('#albumsList li').removeClass('active');
    $(this).parent().addClass('active');

	// generate song list for compilation album
    if (allAlbums[pos].compilation == "1") { 
		allCompilationSongs = [];
		renderFunc = renderSongs;

		LIB.filters.albums = [];

		filterLib();
		
		allFiles.length = 0;
		LIB.totalTime = 0;
		
		for (i = 0; i < allSongs.length; i++) {
			if (allSongs[i].album == allAlbums[pos].album) {
				allCompilationSongs.push(allSongs[i]);
				allFiles.push({'file': allSongs[i].file});
				LIB.totalTime += parseSongTime(allSongs[i].time);
			}
		}

		// sort by filepath then render the songs
		allSongs = allCompilationSongs.sort(function(a, b) {return a.file.toLowerCase() > b.file.toLowerCase() ? 1 : -1;});
		allFiles = allFiles.sort(function(a, b) {return a.file.toLowerCase() > b.file.toLowerCase() ? 1 : -1;});

		renderFunc();

		// cover art and metadata
		if (pos > 0) {
			$('#lib-albumname').html(allAlbums[pos].album);
			$('#lib-artistname').html(allAlbums[pos].artist);			
			$('#lib-numtracks').html(allSongs.length + ((allSongs.length == 1) ? ' track, ' : ' tracks, ') + formatTotalTime(LIB.totalTime));
			$('#lib-coverart-img').html(
				'<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-all">' +
				'<img class="lib-coverart" src="' + makeCoverUrl(allSongs[0].file) + '" ' + 'alt="Cover art not found"' + '></a>'
			);
		}
    } else {
		// generate song list for regular album		
	    clickedLibItem(e, keyAlbum(allAlbums[pos]), LIB.filters.albums, renderSongs);
	}
});

// click lib action menu
$('#songsList').on('click', '.lib-action', function() {
    UI.dbEntry[0] = $('#songsList .lib-action').index(this); // store pos for use in action menu item click
    $('#songsList li').removeClass('active');
    $(this).parent().addClass('active');

	// adjust menu position so its always visible
    var posTop = "-90px"; // new fake btn pos
    var relOfs = 310; // btn offset relative to window (minus lib meta area)
    
	if ($(window).height() - ($(this).offset().top - $(window).scrollTop()) <= relOfs) {
		$('#context-menus .dropdown-menu').css({"top":posTop});
		$('#context-menus .dropdown-menu > li > a').css({'line-height':'40px'});
	} else {
		$('#context-menus .dropdown-menu').css({"top":"0px"});
		$('#context-menus .dropdown-menu > li > a').css({'line-height':'40px'});
	}
});

// click lib coverart
$('#lib-coverart-img').click(function() {
    $('#songsList li').removeClass('active');
	$('#context-menus .dropdown-menu > li > a').css({'line-height':'35px'});
	$('#lib-numtracks').css({'color': '#f1c40f'}); // highlight Sunflower
});

// click tracks action menu item 
$('#context-menu-lib-item a').click(function(e) {
    if ($(this).data('cmd') == 'add') {
        MpdDbCmd('add', allSongs[UI.dbEntry[0]].file);
        notify('add', '');
    }
    if ($(this).data('cmd') == 'play') {
        MpdDbCmd('play', allSongs[UI.dbEntry[0]].file);
        notify('add', '');
    }
    if ($(this).data('cmd') == 'clrplay') {
        MpdDbCmd('clrplay', allSongs[UI.dbEntry[0]].file);
        notify('clrplay', '');        
        $("#pl-saveName").val(""); // clear saved playlist name if any
	}
	
	// remove highlight
    $('#lib-song-' + (UI.dbEntry[0] + 1).toString()).removeClass('active');
	$('#lib-numtracks').css({'color': '#eee'}); // reset color
});

// click coverart action menu item
$('#context-menu-lib-all a').click(function(e) {
    if ($(this).data('cmd') == 'addall') {
        MpdDbCmd('addall', allFiles);
        notify('add', '');
	}
    if ($(this).data('cmd') == 'playall') {
        MpdDbCmd('playall', allFiles);
        notify('add', '');
	}
    if ($(this).data('cmd') == 'clrplayall') {
        MpdDbCmd('clrplayall', allFiles);
        notify('clrplay', '');
	}
	
	// remove highlight
    $('#lib-song-' + (UI.dbEntry[0] + 1).toString()).removeClass('active');
	$('#lib-numtracks').css({'color': '#eee'}); // reset color
});

// remove highlight when clicking off-row
$('#songsList').on('click', '.lib-entry-song', function() {
    $('#songsList li').removeClass('active');    
});

// reset color
$('#lib-genre').click(function(e) {
	$('#lib-numtracks').css({'color': '#eee'}); // reset color
});
$('#lib-artist').click(function(e) {
	$('#lib-numtracks').css({'color': '#eee'}); // reset color
});
$('#lib-album').click(function(e) {
	$('#lib-numtracks').css({'color': '#eee'}); // reset color
});
$('#lib-file').click(function(e) {
	$('#lib-numtracks').css({'color': '#eee'}); // reset color
});
$('#lib-meta-summary').click(function(e) {
	$('#lib-numtracks').css({'color': '#eee'}); // reset color
});

// main menu
$('.context-menu a').click(function(){
    var path = UI.dbEntry[0]; // file path or item num

    if ($(this).data('cmd') == 'setforclockradio' || $(this).data('cmd') == 'setforclockradio-m') {		
		$('#clockradio-enabled span').text(SESSION.json['ckrad']);
		
		if ($(this).data('cmd') == 'setforclockradio-m') {
			$('#clockradio-playname').val(SESSION.json['ckradname']); // called from system menu 
			UI.dbEntry[0] = '-1'; // TC for update
		} else {
			$('#clockradio-playname').val(UI.dbEntry[3]); // called from action menu
		}
		
		$('#clockradio-starttime-hh').val(SESSION.json['ckradstart'].substr(0, 2));
		$('#clockradio-starttime-mm').val(SESSION.json['ckradstart'].substr(2, 2));
		$('#clockradio-starttime-ampm span').text(SESSION.json['ckradstart'].substr(5, 2));
		$('#clockradio-stoptime-hh').val(SESSION.json['ckradstop'].substr(0, 2));
		$('#clockradio-stoptime-mm').val(SESSION.json['ckradstop'].substr(2, 2));
		$('#clockradio-stoptime-ampm span').text(SESSION.json['ckradstop'].substr(5, 2));

		$('#clockradio-volume').attr('max', SESSION.json['volwarning']);
		if (parseInt(SESSION.json['volwarning']) < 100) {
			$('#clockradio-volume-aftertext').html('warning limit ' + SESSION.json['volwarning']);
		} else {
			$('#clockradio-volume-aftertext').html('');
		}
		$('#clockradio-volume').val(SESSION.json['ckradvol']);
		$('#clockradio-shutdown span').text(SESSION.json['ckradshutdn']);
		
        $('#clockradio-modal').modal();
    }
    
    // customize popup
    if ($(this).data('cmd') == 'customize') {
	    
		// General settings
		$('#volume-warning-limit').val(SESSION.json['volwarning']);
		
		if (parseInt(SESSION.json['volwarning']) == 100) {
			$('#volume-warning-limit-aftertext').html('warning disabled');
		} else {
			$('#volume-warning-limit-aftertext').html('');
		}
		
		$('#search-autofocus-enabled span').text(SESSION.json['autofocus']);
		
		$('#theme-color-list').html(		
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #e74c3c; font-weight: bold;">Alizarin</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #9b59b6; font-weight: bold;">Amethyst</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #335db6; font-weight: bold;">Bluejeans</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #e67e22; font-weight: bold;">Carrot</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #27ae60; font-weight: bold;">Emerald</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #e5a646; font-weight: bold;">Fallenleaf</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #90be5d; font-weight: bold;">Grass</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #48929b; font-weight: bold;">Herb</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #9a83d4; font-weight: bold;">Lavender</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #2980b9; font-weight: bold;">River</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #d479ac; font-weight: bold;">Rose</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #aaaaaa; font-weight: bold;">Silver</span></a></li>' +
			'<li><a href="#notarget" data-cmd="theme-color-sel"><span class="text" style="color: #16a085; font-weight: bold;">Turquoise</span></a></li>'
		);
		
		$('#theme-color span').text(SESSION.json['themecolor']);
		
		$('#play-history-enabled span').text(SESSION.json['playhist']);
		$('#extratag-display span').text(SESSION.json['xtagdisp']);
		$('#library-artist span').text(SESSION.json['libartistcol']);

		// audio device description
		var obj = sendMoodeCmd('POST', 'readaudiodev');
		var devlist = '';
		
		// load device list into <ul>
		for (i = 0; i < obj.length; i++) {
			devlist += '<li><a href="#notarget" data-cmd="audio-device-name-sel"><span class="text">' + obj[i]['name'] + '</span></a></li>';
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
    
    // playback history log
    if ($(this).data('cmd') == 'viewplayhistory') {
		var obj = sendMoodeCmd('GET', 'readplayhistory');
		var output = '';
		
		for (i = 1; i < obj.length; i++) {
			output += obj[i];
		}

        $('ol.playhistory').html(output);
        $('#playhistory-modal').modal();
    }
    
    // about
    if ($(this).data('cmd') == 'aboutmoode') {
		$('#sys-upd-pkgdate').text(SESSION.json['pkgdate']);
		$('#sys-moodeos-ver').text(SESSION.json['moodeosver']);
		$('#sys-kernel-ver').text(SESSION.json['kernelver']);
		$('#sys-processor-arch').text(SESSION.json['procarch'].replace("arm", "ARM")); // uppercase for display
		$('#sys-mpd-ver').text(SESSION.json['mpdver']);
		$('#sys-hardware-rev').text(SESSION.json['hdwrrev']);
		
        $('#about-modal').modal();
    }    
});

// update clock radio settings
$('.btn-clockradio-update').click(function(){	
	SESSION.json['ckrad'] = $('#clockradio-enabled span').text();
	
	// update header and menu icon color
	if (SESSION.json['ckrad'] == "Clock Radio" || SESSION.json['ckrad'] == "Sleep Timer") {
		$('#clockradio-icon').removeClass("clockradio-off")
		$('#clockradio-icon').addClass("clockradio-on")
		$('#clockradio-icon-m').removeClass("clockradio-off-m")
		$('#clockradio-icon-m').addClass("clockradio-on-m")
	} else {
		$('#clockradio-icon').removeClass("clockradio-on")
		$('#clockradio-icon').addClass("clockradio-off")
		$('#clockradio-icon-m').removeClass("clockradio-on-m")
		$('#clockradio-icon-m').addClass("clockradio-off-m")
	}

	// NOTE UI.dbEntry[0] set to '-1' if modal launched from system menu
	if (UI.dbEntry[0] != '-1') {
		SESSION.json['ckraditem'] = sendMoodeCmd('GET', 'getplitemfile&songpos=' + UI.dbEntry[0]);
		//SESSION.json['ckraditem'] = UI.dbEntry[0];
	}

	SESSION.json['ckradname'] = $('#clockradio-playname').val();

	var startHH, startMM, stopHH, stopMM;
	
	$('#clockradio-starttime-hh').val().length == 1 ? startHH = '0' + $('#clockradio-starttime-hh').val() : startHH = $('#clockradio-starttime-hh').val();
	$('#clockradio-starttime-mm').val().length == 1 ? startMM = '0' + $('#clockradio-starttime-mm').val() : startMM = $('#clockradio-starttime-mm').val();
	$('#clockradio-stoptime-hh').val().length == 1 ? stopHH = '0' + $('#clockradio-stoptime-hh').val() : stopHH = $('#clockradio-stoptime-hh').val();
	$('#clockradio-stoptime-mm').val().length == 1 ? stopMM = '0' + $('#clockradio-stoptime-mm').val() : stopMM = $('#clockradio-stoptime-mm').val();

	SESSION.json['ckradstart'] = startHH + startMM + " " + $('#clockradio-starttime-ampm span').text();
	SESSION.json['ckradstop'] = stopHH + stopMM + " " + $('#clockradio-stoptime-ampm span').text();
	
	SESSION.json['ckradvol'] = $('#clockradio-volume').val();
	SESSION.json['ckradshutdn'] = $('#clockradio-shutdown span').text();

	var result = sendMoodeCmd('POST', 'updcfgengine',
		{'ckrad': SESSION.json['ckrad'],
		 'ckraditem': SESSION.json['ckraditem'],		
		 'ckradname': SESSION.json['ckradname'].replace("'", "''"), // just in case, use escaped single quotes for sql i.e., two single quotes
		 'ckradstart': SESSION.json['ckradstart'],		
		 'ckradstop': SESSION.json['ckradstop'],		
		 'ckradvol': SESSION.json['ckradvol'],		
		 'ckradshutdn': SESSION.json['ckradshutdn']
		}
	);

	// update globals within worker loop
	sendMoodeCmd('GET', 'reloadclockradio');
	
    notify('updclockradio', '');
});

// update customize settings
$('.btn-customize-update').click(function(){

	// general settings
    SESSION.json['volwarning'] = $('#volume-warning-limit').val();	
    if (parseInt(SESSION.json['ckradvol']) > parseInt($('#volume-warning-limit').val())) {
	    SESSION.json['ckradvol'] = $('#volume-warning-limit').val(); // adj clock radio vol
    }
	SESSION.json['autofocus'] = $('#search-autofocus-enabled span').text();

	// detect theme change
	if (SESSION.json['themecolor'] != $('#theme-color span').text()) {
		var themeChange = true;
	}
	else {
		var themeChange = false;
	}
	// detect lib artist col change
	if (SESSION.json['libartistcol'] != $('#library-artist span').text()) {
		var libartistcolChange = true;
	}
	else {
		var libartistcolChange = false;
	}

	SESSION.json['themecolor'] = $('#theme-color span').text();
	SESSION.json['playhist'] = $('#play-history-enabled span').text();
	SESSION.json['xtagdisp'] = $('#extratag-display span').text();
	SESSION.json['libartistcol'] = $('#library-artist span').text();

	// device description
	SESSION.json['adevname'] = $('#audio-device-name span').text();
	
	// update sql
	var result = sendMoodeCmd('POST', 'updcfgengine',
		{'volwarning': SESSION.json['volwarning'],
		 'autofocus': SESSION.json['autofocus'],		
		 'themecolor': SESSION.json['themecolor'],		
		 'playhist': SESSION.json['playhist'],		
		 'xtagdisp': SESSION.json['xtagdisp'],		
		 'libartistcol': SESSION.json['libartistcol'],		
		 'adevname': SESSION.json['adevname']		
		}
	);

    if (parseInt(SESSION.json['volknob']) > parseInt($('#volume-warning-limit').val())) {
	    setVolume($('#volume-warning-limit').val()); // lower player vol to match new limit
    }

	if (libartistcolChange == true || themeChange == true) {
		if (libartistcolChange == true) {
			sendMoodeCmd('GET', 'truncatelibcache');
		    notify('liboptionchange', 'Refresh Browser to activate');
		}

		if (themeChange == true) {
			sendMoodeCmd('GET', SESSION.json['themecolor'].toLowerCase());
		    notify('themechange', 'Refresh Browser to activate');
		}
	}
	else {
	    notify('updcustomize', '');
	}
});

// custom select controls
// use .on('click') because some of the lists are generated dynamically
$('body').on('click', '.dropdown-menu .custom-select a', function() {
	if ($(this).data('cmd') == 'clockradio-enabled-yn') {
		$('#clockradio-enabled span').text($(this).text());		
	} else if ($(this).data('cmd') == 'clockradio-starttime-ampm') {
		$('#clockradio-starttime-ampm span').text($(this).text());		
	} else if ($(this).data('cmd') == 'clockradio-stoptime-ampm') {
		$('#clockradio-stoptime-ampm span').text($(this).text());		
	} else if ($(this).data('cmd') == 'clockradio-shutdown-yn') {
		$('#clockradio-shutdown span').text($(this).text());		
	} else if ($(this).data('cmd') == 'search-autofocus-enabled-yn') {
		$('#search-autofocus-enabled span').text($(this).text());		
	} else if ($(this).data('cmd') == 'theme-color-sel') {
		$('#theme-color span').text($(this).text());		
	} else if ($(this).data('cmd') == 'play-history-enabled-yn') {
		$('#play-history-enabled span').text($(this).text());
	} else if ($(this).data('cmd') == 'extratag-display-yn') {
		$('#extratag-display span').text($(this).text());
	} else if ($(this).data('cmd') == 'library-artist-sel') {
		$('#library-artist span').text($(this).text());
	} else if ($(this).data('cmd') == 'audio-device-name-sel') {
		$('#audio-device-name span').text($(this).text());
		var obj = sendMoodeCmd('POST', 'readaudiodev', {'name': $(this).text()});
		$('#audio-device-dac').val(obj['dacchip']);
		$('#audio-device-arch').val(obj['arch']);
		$('#audio-device-iface').val(obj['iface']);
	}
});

// set volume warning limit text
$("#volume-warning-limit").keyup(function() {
	if (parseInt($('#volume-warning-limit').val()) == 100) {
		$('#volume-warning-limit-aftertext').html('warning disabled');
	} else {
		$('#volume-warning-limit-aftertext').html('');
	}
});
$("#volume-warning-limit").change(function() {
	if (parseInt($('#volume-warning-limit').val()) == 100) {
		$('#volume-warning-limit-aftertext').html('warning disabled');
	} else {
		$('#volume-warning-limit-aftertext').html('');
	}
});

$('#syscmd-reboot').click(function(){
	UI.restart = 'reboot';
	sendMoodeCmd('GET', 'reboot');
	notify('reboot', '', 8000);	
});

$('#syscmd-poweroff').click(function(){
	UI.restart = 'poweroff';
	sendMoodeCmd('GET', 'poweroff');
	notify('shutdown', '', 8000);	
});
	
// TC drag & drop handlers for playlist
/*
function allowDrop(ev) {
    ev.preventDefault();
}

function drag(ev) {
    ev.dataTransfer.setData("text", ev.target.id);
}

function drop(ev) {
    ev.preventDefault();
    var data = ev.dataTransfer.getData("text");
    ev.target.appendChild(document.getElementById(data));
}
*/