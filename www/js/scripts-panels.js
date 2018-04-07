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
 * - new volume control
 * - new playback button handlers
 * - add engineCmd()
 * - remove engineSps() and related code
 * - remove accumulated  code
 *
 */

var libRendered = false; // trigger library load

jQuery(document).ready(function($) { 'use strict';
    
    SESSION.json = sendMoodeCmd('GET', 'readcfgengine'); // load session vars
    RADIO.json = sendMoodeCmd('GET', 'readcfgradio'); // load radio stations
    THEME.json = sendMoodeCmd('GET', 'readcfgtheme'); // load themes

	// connect to mpd engine
    engineMpd();
	// connect to cmd engine
	if (SESSION.json['btsvc'] == '1' || SESSION.json['airplaysvc'] == '1') {
	    engineCmd();
	}
	
 	// update state/color of clock radio icons
	if (SESSION.json['ckrad'] == 'Clock Radio' || SESSION.json['ckrad'] == 'Sleep Timer') {
		$('#clockradio-icon').removeClass('clockradio-off')
		$('#clockradio-icon').addClass('clockradio-on')
	} else {
		$('#clockradio-icon').removeClass('clockradio-on')
		$('#clockradio-icon').addClass('clockradio-off')
	}
    
    // populate browse panel root 
    MpdDbCmd('filepath', UI.path, 'file');
    
    // setup pines notify
    $.pnotify.defaults.history = false;

	// volume disabled, 0dB output
	if (SESSION.json['mpdmixer'] == 'disabled') {
		volumeCtrl('disable');

		SESSION.json['volknob'] = '0';
		var result = sendMoodeCmd('POST', 'updcfgengine', {'volknob': SESSION.json['volknob']});
	} 
	// software or hardware volume
	else {
		if ($('#mobile-toolbar').css('display') == 'flex') {
			$('#volume-2').val(SESSION.json['volknob']); // popup knob
		}
		else {
			$('#volume').val(SESSION.json['volknob']); // knob
		}	
		$('.volume-display').css('opacity', '');
		$('.volume-display').text(SESSION.json['volknob']);
	}

	// mute toggle
	$('.volume-display').click(function() {
		if (SESSION.json['mpdmixer'] == 'disabled') {return false;}

        if (SESSION.json['volmute'] == '0') {
			SESSION.json['volmute'] = '1' // toggle to mute
			$('.volume-display').css('opacity', '.3');
			var newVol = 0;
			var volEvent = 'mute';
        }
		else {
			SESSION.json['volmute'] = '0' // toggle to unmute
			$('.volume-display').css('opacity', '');
			var newVol = SESSION.json['volknob'];
			var volEvent = 'unmute';
        }
		
		var result = sendMoodeCmd('POST', 'updcfgengine', {'volmute': SESSION.json['volmute']});
		setVolume(newVol, volEvent);
	});

	// mobile volume control
    $('#btn-mvol-popup').click(function() {
		$('.volume-display').css('margin-top', '-16px');

		if (SESSION.json['mpdmixer'] == 'disabled') {
			$('.volume-display').css('opacity', '.3');
		}
		else {
	        SESSION.json['volmute'] == '1' ? $('.volume-display').css('opacity', '.3') : $('.volume-display').css('opacity', '');
		}

		$('#volume-popup').modal();
	});

	// playback button handlers
	$('#play').click(function() {
		if (MPD.json['state'] == 'play') {
			$('#play i').removeClass('icon-play').addClass('icon-pause');
			$('#countdown-display').countdown('pause');
			if (MPD.json['file'].substr(0, 4).toLowerCase() == 'http') {
				var cmd = MPD.json['artist'] == 'Radio station' ? 'stop' : 'pause'; // pause if for upnp url
			}
			else {
			    var cmd = 'pause'; // song file
			}
		}
		else if (MPD.json['state'] == 'pause') {
			$('#play i').removeClass('icon-pause').addClass('icon-play');
			$('#countdown-display').countdown('resume');
			var current = parseInt(MPD.json['song']);
			customScroll('pl', current, 200);
			var cmd = 'play';
		}
		else if (MPD.json['state'] == 'stop') {
		if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
		    $('#countdown-display').countdown({since: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
		}
		else {
		    $('#countdown-display').countdown({until: parseInt(MPD.json['time']), onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
		}
			var current = parseInt(MPD.json['song']);
			customScroll('pl', current, 200);
			var cmd = 'play';
		}
		window.clearInterval(UI.knob);
		sendMpdCmd(cmd);
	});
	$('#mplay').click(function() {
		if (MPD.json['state'] == 'play') {
			$('#mplay i').removeClass('icon-play').addClass('icon-pause');
			$('#m-countdown').countdown('pause');
			if (MPD.json['file'].substr(0, 4).toLowerCase() == 'http') {
				var cmd = MPD.json['artist'] == 'Radio station' ? 'stop' : 'pause'; // pause if for upnp url
			}
			else {
			    var cmd = 'pause'; // song file
			}
		}
		else if (MPD.json['state'] == 'pause') {
			$('#mplay i').removeClass('icon-pause').addClass('icon-play');
			$('#m-countdown').countdown('resume');
			var cmd = 'play';
		}
		else if (MPD.json['state'] == 'stop') {
			if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
			    $('#m-countdown').countdown({since: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			}
			else {
			    $('#m-countdown').countdown({until: parseInt(MPD.json['time']), compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			}
			var cmd = 'play';
		}
		window.clearInterval(UI.knob);
		sendMpdCmd(cmd);
	});

	$('#next,#mnext').click(function() {
        sendMpdCmd('next');
	});
	$('#prev,#mprev').click(function() {
        if (parseInt(MPD.json['time']) > 0 && parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed']) > 0) {
			window.clearInterval(UI.knob);
			if ($('#mobile-toolbar').css('display') == 'flex') {
	            refreshMTimer(0, 0, 'stop'); // reset to beginning of song and pause
			}
			else {
	            refreshTimer(0, 0, 'stop');
			}
	        sendMpdCmd('seek ' + MPD.json['song'] + ' 0');
			if (MPD.json['state'] != 'pause') {			
				sendMpdCmd('pause');
			}
        }
		else {
			sendMpdCmd('previous');
		}
	});
	$('#volumeup,#volumeup-2').click(function() {
		var curVol = parseInt(SESSION.json['volknob']);
		var newVol = curVol < 100 ? curVol + 1 : 100;
		setVolume(newVol, '');
	});
	$('#volumedn,#volumedn-2').click(function() {
		var curVol = parseInt(SESSION.json['volknob']);
		var newVol = curVol > 0 ? curVol - 1 : 0;
		setVolume(newVol, '');
	});
	$('.btn-toggle').click(function() {
		var id = $(this).attr('id');
		var cmd = id.startsWith('m') ? id.substr(1) : id;
		var toggle = $(this).hasClass('btn-primary') ? '0' : '1';
		if (cmd == 'random' && SESSION.json['ashufflesvc'] == '1') {
			var resp = sendMoodeCmd('GET', 'ashuffle', {'ashuffle':toggle}); // toggle ashuffle on/off
		    $('random').toggleClass('btn-primary');
		    $('consume').toggleClass('btn-primary');
			sendMpdCmd('consume ' + toggle);
		}
		else {
		    $(this).toggleClass('btn-primary');
			sendMpdCmd(cmd + ' ' + toggle);
		}
	});

    // countdown time knob
    $('.playbackknob').knob({
        inline: false,
		change : function (value) {
            if (MPD.json['state'] != 'stop') {
				window.clearInterval(UI.knob)
				// update time display when changing slider
				var seekto = Math.floor((value * parseInt(MPD.json['time'])) / 1000);
				if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
					$('#countdown-display').html(formatSongTime(seekto)); // count up
				} else {
					$('#countdown-display').html(formatSongTime(parseInt(MPD.json['time']) - seekto)); // count down
				}
			} else {
				$('#time').val(0);
			}
			
			// repaint needed 
			UI.knobPainted = false;
        },
        release : function (value) {
			if (MPD.json['state'] != 'stop') {
				window.clearInterval(UI.knob);
				var seekto = Math.floor((value * parseInt(MPD.json['time'])) / 1000);
				sendMpdCmd('seek ' + MPD.json['song'] + ' ' + seekto);
				if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
					$('#countdown-display').countdown({since: -seekto, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
				} else {
					$('#countdown-display').countdown({until: seekto, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
				}
			}
			
			// repaint needed 
			UI.knobPainted = false;
        }
		/*
        cancel : function () {},
        draw : function () {}
		*/
    });

    // volume control knob
    $('.volumeknob').knob({
        change : function (value) {
			value = value > 100 ? 100 : value;
			if (value - parseInt(SESSION.json['volknob']) > 10) {
				value = parseInt(SESSION.json['volknob']) + 10;
				setVolume(value, 'change');
			}
			else {
	            setVolume(value, 'change');
			}
        }
		/*
        release : function () {}
        cancel : function () {},
        draw : function () {}
		*/
    });

	// toggle count up/down and direction icon, radio always counts up
	// newui todo: maybe make sure it doesn't count the wrong way for mobile
	$('#countdown-display').click(function() {
		SESSION.json['timecountup'] == '1' ? SESSION.json['timecountup'] = '0' : SESSION.json['timecountup'] = '1';
		var result = sendMoodeCmd('POST', 'updcfgengine', {'timecountup': SESSION.json['timecountup']});

		// update time and direction indicator
		// use sendMoodeCmd('GET', 'getmpdstatus') to obtain exact elapsed time
		if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
			refreshTimer(parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed']), parseInt(MPD.json['time']), MPD.json['state']); // count up
			$('#total').html(formatSongTime(MPD.json['time']) + '<i class="icon-caret-up countdown-caret"></i>');
		} 
		else {
			refreshTimer(parseInt(MPD.json['time'] - parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed'])), 0, MPD.json['state']); // count down
			$('#total').html(formatSongTime(MPD.json['time']) + '<i class="icon-caret-down countdown-caret"></i>');
		}
    });

    // click on playlist entry
	// newui add animation to scroll page to top if a playlist item is pressed
    $('.playlist').on('click', '.pl-entry', function() {
        var pos = $('.playlist .pl-entry').index(this);
        var cmd = MPD.json['song'] == pos ? 'stop,play ' + pos : 'play ' + pos;        

        sendMpdCmd(cmd);
        $(this).parent().addClass('active');

		if ($('#mobile-toolbar').css('display') == 'flex') {
			$('html, body').animate({ scrollTop: 0 }, 'fast');
		}
    });

	// click on playlist action menu button
    // adjust menu position so its always visible
    $('.playlist').on('click', '.pl-action', function() {
	    var posTop = '-92px'; // new btn pos 
	    var relOfs = 212;  // btn offset relative to window
		
		if ($(window).height() - ($(this).offset().top - $(window).scrollTop()) <= relOfs) {
			$('#context-menus .dropdown-menu').css('top', posTop); // 3 menu items
		}
		else {
			$('#context-menus .dropdown-menu').css('top', '0px');
		}

		// store posn for later use by action menu selection
        UI.dbEntry[0] = $('.playlist .pl-action').index(this);

		// radio ststion
		// for clock radio, reuse UI.dbEntry[3] which is also used on the Browse panel 
		if ($('#pl-' + (UI.dbEntry[0] + 1) + ' .pl-entry .pll2').html().substr(0, 2) == '<i') { // has icon-microphone
			UI.dbEntry[3] = $('#pl-' + (UI.dbEntry[0] + 1) + ' .pl-entry .pll2').text();
		}
		// songfile title, artist
		else {
			var txt = $('#pl-' + (UI.dbEntry[0] + 1) + ' .pl-entry .pll2').text();
			UI.dbEntry[3] = $('#pl-' + (UI.dbEntry[0] + 1) + ' .pl-entry .pll1').text() + ', ' + txt.substr(0, (txt.indexOf('-') - 1));
		}
    });

	// save playlist
    $('#pl-btnSave').click(function(){
		var plname = $('#pl-saveName').val();

		if (plname) {
			if (~plname.indexOf('NAS') || ~plname.indexOf('RADIO') || ~plname.indexOf('SDCARD')) {
				notify('plnameerror', '');
			}
			else {
				sendMoodeCmd('GET', 'savepl&plname=' + plname,'',true);
				notify('savepl', '');
			}
		}
		else {
			notify('needplname', '');
		}
    });

	// click on browse tab
	// newui set colors back to theme standard, show/hide, todo: reset search results
	$('#open-browse-panel a').click(function(){
		$('.tab-content').css({color: themeColor});
		$('.tab-content').css({backgroundColor: themeBack});
		$('#menu-top').css('color', themeMcolor);
		$('#menu-bottom').css('color', themeMcolor);
		$('#menu-top').css('background-color', themeMback);
		$('#menu-bottom').css('background-color', themeMback);
		$('#context-menu-playlist-item.dropdown-menu').css('color', themeMcolor);
		$('#context-menu-playlist-item.dropdown-menu').css('background-color', themeMback);
		$('#context-menus .dropdown-menu').css('color', themeMcolor);
		$('#context-menus .dropdown-menu').css('background-color', themeMback);
		$('#menu-top .dropdown-menu').css({color: themeMcolor});
		$('#menu-top .dropdown-menu').css({backgroundColor: themeMback});			
	});

    // click on library tab
	// newui set colors back to theme standard, show/hide, todo: reset search results
	$('#open-library-panel a').click(function(){
		$('#lib-album-filter').css('display', 'none');
		$('.tab-content').css({color: themeColor});
		$('.tab-content').css({backgroundColor: themeBack});
		$('#menu-top').css('color', themeMcolor);
		$('#menu-bottom').css('color', themeMcolor);
		$('#menu-top').css('background-color', themeMback);
		$('#menu-bottom').css('background-color', themeMback);
		$('#context-menu-playlist-item.dropdown-menu').css('color', themeMcolor);
		$('#context-menu-playlist-item.dropdown-menu').css('background-color', themeMback);
		$('#context-menus .dropdown-menu').css('color', themeMcolor);
		$('#context-menus .dropdown-menu').css('background-color', themeMback);
		$('#menu-top .dropdown-menu').css({color: themeMcolor});
		$('#menu-top .dropdown-menu').css({backgroundColor: themeMback});
		// render library
		if (!libRendered) {
		    $('#lib-content').hide();
		    $('#lib-loader').show();
			$.post('command/moode.php?cmd=loadlib', {}, function(data) {
		        $('#lib-loader').hide();
		        $('#lib-content').show();
		        renderLibrary(data);
		        libRendered = true;
		    }, 'json');
		}
	});

	// click on playback tab
	//newui switch to adapative colors saved to element, todo: could probably refactor to also use global variables and save the slower css manipulation, reset search results
	$('#open-playback-panel a').click(function(){
		$('#pl-filter').css('display', 'none');
		$('#menu-top').css('color', adaptMcolor);
		$('#menu-bottom').css('color', adaptMcolor);
		$('#menu-top').css('background-color', adaptMback);
		$('#menu-bottom').css('background-color', adaptMback);
		$('#context-menu-playlist-item .dropdown-menu').css('color', adaptMcolor);
		$('#context-menu-playlist-item .dropdown-menu').css('background-color', adaptMback);
		$('#menu-top .dropdown-menu').css({color: adaptMcolor});
		$('#menu-top .dropdown-menu').css({backgroundColor: adaptMback});
		// auto-scroll playlist
		var current = parseInt(MPD.json['song']);  // scrollto when click
		customScroll('pl', current, 200);
	});
    
	// click on back btn
	$('#db-back').click(function() {
		--UI.dbPos[10];
		var path = UI.path;
		var cutpos=path.lastIndexOf('/');
		if (cutpos !=-1) {
			var path = path.slice(0,cutpos);
		}
		else {
			path = '';
		}
		MpdDbCmd('filepath', path, UI.browsemode, 1);
	});

	// click on home btn
	$('#db-home').click(function() {
		$('.database li').removeClass('active');
		--UI.dbPos[10];
		var path = UI.path;
		path = '';
		MpdDbCmd('filepath', path, UI.browsemode, 1);
	});

	// click on database entry 
	// newui get rid of loop and inline css, use show/hide
	$('.database').on('click', '.db-browse', function() {
		$('.btnlist-top-db').show();
		
		if (!$(this).hasClass('sx')) {
		    if ($(this).hasClass('db-folder')) {
				var path = $(this).parent().data('path');
				var entryID = $(this).parent().attr('id');
				entryID = entryID.replace('db-','');
				UI.dbPos[UI.dbPos[10]] = entryID;
				++UI.dbPos[10];
				MpdDbCmd('filepath', path, 'file', 0);
				
				if (path == 'RADIO') {
					$('#db-search').addClass('db-form-hidden');
					$('#db-search-input').addClass('hidden');
					$('#rs-search-input').removeClass('hidden');
					if (SESSION.json['autofocus'] == 'Yes') {$('#rs-filter').focus();}
				}
				else {
					$('#rs-search-input').addClass('hidden');
					$('#db-search').removeClass('db-form-hidden');
					$('#db-search-input').removeClass('hidden');
					if (SESSION.json['autofocus'] == 'Yes') {$('#db-search-keyword').focus();}
				}
			} 
			else if ($(this).hasClass('db-savedplaylist')) {
				var path = $(this).parent().data('path');
				var entryID = $(this).parent().attr('id');
				entryID = entryID.replace('db-','');
				UI.dbPos[UI.dbPos[10]] = entryID;
				++UI.dbPos[10];
				MpdDbCmd('listsavedpl', path, 'file', 0);
				$('#db-search').addClass('db-form-hidden');
				$('#db-search-input').addClass('hidden');
				$('#rs-search-input').removeClass('hidden');
				if (SESSION.json['autofocus'] == 'Yes') {$('#rs-filter').focus();}
			}
		}
	});

	// click on browse action menu button
	$('.database').on('click', '.db-action', function() {
		UI.dbEntry[0] = $(this).parent().attr('data-path');
		UI.dbEntry[3] = $(this).parent().attr('id'); // used in .context-menu a click handler to remove highlight
		$('.database li').removeClass('active');
		$(this).parent().addClass('active');
		
		// adjust menu position so its always visible
		var posTop = ''; // new btn pos
		var relOfs = 0;  // btn offset relative to window
		var menuId = $('.db-action a').attr('data-target');
		
		if (menuId == '#context-menu-savedpl-item' || menuId == '#context-menu-folder-item') { // 3 menu items
			posTop = '-92px';
			relOfs = 212;	
		}
		else if (menuId == '#context-menu' || menuId == '#context-menu-root') { // 4 menu items	
			posTop = '-132px';
			relOfs = 252;	
		}
		else if (menuId == '#context-menu-radio-item') { // 6 menu items
			posTop = '-212px';
			relOfs = 332;	
		}
		
		if ($(window).height() - ($(this).offset().top - $(window).scrollTop()) <= relOfs) {
			$('#context-menus .dropdown-menu').css('top', posTop);
		}
		else {
			$('#context-menus .dropdown-menu').css('top', '0px');
		}        
	});

	// chiudi i risultati di ricerca nel DB
	$('.database').on('click', '.search-results', function() {
		MpdDbCmd('filepath', UI.path);
	});

	// click on action menu or main menu items
	$('.context-menu a').click(function(){
	    var path = UI.dbEntry[0]; // File path or item num
	
		if ($(this).data('cmd') == 'add') {
			MpdDbCmd('add', path);
			notify('add', '');
		} 
		else if ($(this).data('cmd') == 'play') {
			MpdDbCmd('play', path);
			notify('add', '');
		}
		else if ($(this).data('cmd') == 'clrplay') {
			MpdDbCmd('clrplay', path);
			notify('clrplay', '');
			if (path.indexOf('/') == -1) {  // its a playlist, preload the saved playlist name
				$('#pl-saveName').val(path);
			}
			else {
				$('#pl-saveName').val('');
			}
		}        
		else if ($(this).data('cmd') == 'update') {
			MpdDbCmd('update', path);
			notify('update', path);
			libRendered = false;
		}
		else if ($(this).data('cmd') == 'delsavedpl') {
			$('#savedpl-path').html(path);        	        
			$('#deletesavedpl-modal').modal();
		}
		else if ($(this).data('cmd') == 'delstation') {
			// trim 'RADIO/' and '.pls' from path
			$('#station-path').html(path.slice(0,path.lastIndexOf('.')).substr(6));
			$('#deletestation-modal').modal();
		}
		else if ($(this).data('cmd') == 'addstation') {
			$('#add-station-name').val('New Station');
			$('#add-station-url').val('http://');
			$('#addstation-modal').modal();
		}
		else if ($(this).data('cmd') == 'editradiostn') {
			// trim 'RADIO/' and '.pls' from path
			path = path.slice(0,path.lastIndexOf('.')).substr(6);
			$('#edit-station-name').val(path);
			$('#edit-station-url').val(sendMoodeCmd('POST', 'readstationfile', {'path': UI.dbEntry[0]})['File1']);
			$('#editstation-modal').modal();
		}
		else if ($(this).data('cmd') == 'deleteplitem') {
			// max value (num pl items in list)
			$('#delete-plitem-begpos').attr('max', UI.dbEntry[4]);
			$('#delete-plitem-endpos').attr('max', UI.dbEntry[4]);
			$('#delete-plitem-newpos').attr('max', UI.dbEntry[4]);
			// num of selected item
			$('#delete-plitem-begpos').val(path + 1);
			$('#delete-plitem-endpos').val(path + 1);
			$('#deleteplitems-modal').modal();
		}
		else if ($(this).data('cmd') == 'moveplitem') {
			// max value (num pl items in list)
			$('#move-plitem-begpos').attr('max', UI.dbEntry[4]);
			$('#move-plitem-endpos').attr('max', UI.dbEntry[4]);
			$('#move-plitem-newpos').attr('max', UI.dbEntry[4]);
			// num of selected item
			$('#move-plitem-begpos').val(path + 1);
			$('#move-plitem-endpos').val(path + 1);
			$('#move-plitem-newpos').val(path + 1);
			$('#moveplitems-modal').modal();
		}
		
		// remove row highlight after selecting action menu item (Browse)
		if (UI.dbEntry[3].substr(0, 3) == 'db-') {
			$('#' + UI.dbEntry[3]).removeClass('active');
		}
	});
    
	// buttons on modals
	$('.btn-del-savedpl').click(function(){
		MpdDbCmd('delsavedpl', UI.dbEntry[0]);
		notify('delsavedpl', '');
	});
	$('.btn-del-radiostn').click(function(){
		MpdDbCmd('delstation', UI.dbEntry[0]);
		notify('delstation', '');
	});
	$('.btn-add-radiostn').click(function(){
		MpdDbCmd('addstation', $('#add-station-name').val() + '\n' + $('#add-station-url').val() + '\n');
		notify('addstation', '');
	});
	$('.btn-update-radiostn').click(function(){
		MpdDbCmd('updstation', $('#edit-station-name').val() + '\n' + $('#edit-station-url').val() + '\n');
		notify('updstation', '');
	});
	$('.btn-delete-plitem').click(function(){
		var cmd = '';
		var begpos = $('#delete-plitem-begpos').val() - 1;
		var endpos = $('#delete-plitem-endpos').val() - 1;
		// NOTE: format for single or multiple, endpos not inclusive so must be bumped for multiple
		begpos == endpos ? cmd = 'delplitem&range=' + begpos : cmd = 'delplitem&range=' + begpos + ':' + (endpos + 1);
		notify('remove', '');
		sendMoodeCmd('GET', cmd,'',true); // async
	});
	// speed btns on delete modal
	$('#btn-delete-setpos-top').click(function(){
		$('#delete-plitem-begpos').val(1);
		return false;
	});
	$('#btn-delete-setpos-bot').click(function(){
		$('#delete-plitem-endpos').val(UI.dbEntry[4]);
		return false;
	});
	$('.btn-move-plitem').click(function(){
		var cmd = '';
		var begpos = $('#move-plitem-begpos').val() - 1;
		var endpos = $('#move-plitem-endpos').val() - 1;
		var newpos = $('#move-plitem-newpos').val() - 1;
		// NOTE: format for single or multiple, endpos not inclusive so must be bumped for multiple
		// move begpos newpos or move begpos:endpos newpos 
		begpos == endpos ? cmd = 'moveplitem&range=' + begpos + '&newpos=' + newpos : cmd = 'moveplitem&range=' + begpos + ':' + (endpos + 1) + '&newpos=' + newpos;
		notify('move', '');
		sendMoodeCmd('GET', cmd,'',true); // async
	});
	// speed btns on move modal
	$('#btn-move-setpos-top').click(function(){
		$('#move-plitem-begpos').val(1);
		return false;
	});
	$('#btn-move-setpos-bot').click(function(){
		$('#move-plitem-endpos').val(UI.dbEntry[4]);
		return false;
	});
	$('#btn-move-setnewpos-top').click(function(){
		$('#move-plitem-newpos').val(1);
		return false;
	});
	$('#btn-move-setnewpos-bot').click(function(){
		$('#move-plitem-newpos').val(UI.dbEntry[4]);
		return false;
	});
	// remove highlight when clicking off-row
	$('.database').on('click', '.db-song', function() {
		$('.database li').removeClass('active');
	});
	// save playlist modal //newui - add click handler for playlist save button
	$('#playlistSave').click(function() {
		$('#savepl-modal').modal();
	});

	// speed buttons on plaback history log
	$('.ph-firstPage').click(function(){
		$('#container-playhistory').scrollTo(0 , 500);
	});
	$('.ph-lastPage').click(function(){
		$('#container-playhistory').scrollTo('100%', 500);
	});

	// open a tab from external link
	var url = document.location.toString();
	if (url.match('#')) {
		$('#menu-bottom a[href=#'+url.split('#')[1]+']').tab('show') ;
	}

    // library typedown search
	// newui add click handler for magnifying glass button in library
    $('#lib-search').click(function(e){		
		if ($('#lib-album-filter').css('display') == 'inline-block') {
			$('#lib-album-filter').hide();
		    $('input').blur();
			$('#lib-album-filter').val('');
			$('.btnlist-top-lib').css('width', '50px');
			clickedLibItem(e, undefined, LIB.filters.artists, renderArtists);
		}
		else {
			$('#lib-album-filter').show();
			$('.btnlist-top-lib').css('width', '300px');
			$('#lib-album-filter').focus();
		}	
		$('#lib-album-filter-results').toggle();
	});
	$('#lib-album-filter').keyup(function(){
		$.scrollTo(0 , 500);
		var filter = $(this).val(), count = 0;	    
		$('.albumslist li').each(function() {
			if ($(this).text().search(new RegExp(filter, 'i')) < 0) {
				$(this).hide();
			}
			else {
				$(this).show();
				count++;
			}
		});
		var s = (count == 1) ? '' : 's';
		if (filter != '') {
			$('#lib-album-filter-results').html((+count) + '&nbsp;album' + s);
		}
		else {
			$('#lib-album-filter-results').html('');
		}
	});
	
    // playlist typedown search
	// newui add click handler for magnifying glass button in playback
	$('#play-search').click(function(){
		if ($('#pl-filter').css('display') == 'inline-block') {
			$('#pl-filter').css('display', 'none');
		    $('input').blur();
			$('#pl-filter').val('');
		}
		else {
			$('#pl-filter').css('display', 'inline-block');
			$('#pl-filter').focus();
		}	
	});
	$('#pl-filter').keyup(function(){
		$.scrollTo(0 , 500);
		var filter = $(this).val(), count = 0;
		$('.playlist li').each(function(){
			if ($(this).text().search(new RegExp(filter, 'i')) < 0) {
				$(this).hide();
			}
			else {
				$(this).show();
				count++;
			}
		});
	    var s = (count == 1) ? '' : 's';
	    if (filter != '') {
			$('#pl-filter-results').html((+count) + '&nbsp;item' + s);
	    }
		else {
			$('#pl-filter-results').html('');
	    }
	});

	// radio station typedown search
	$('#rs-filter').keyup(function(){
		$.scrollTo(0 , 500);
		var filter = $(this).val(), count = 0;
		$('.database li').each(function(){
			if ($(this).text().search(new RegExp(filter, 'i')) < 0) {
				$(this).hide();
			}
			else {
				$(this).show();
				count++;
			}
		});	
	    var s = (count == 1) ? '' : 's';
	    if (filter != '') {
			$('#db-filter-results').html((+count) + '&nbsp;station' + s);
	    }
		else {
			$('#db-filter-results').html('');
	    }
	});

	// playback history typedown search
	$('#ph-filter').keyup(function(){
		$.scrollTo(0 , 500);
		var filter = $(this).val(), count = 0;
		
		$('.playhistory li').each(function(){
			if ($(this).text().search(new RegExp(filter, 'i')) < 0) {
				$(this).hide();
			}
			else {
				$(this).show();
				count++;
			}
		});
		var s = (count == 1) ? '' : 's';
		if (filter != '') {
			$('#ph-filter-results').html((+count) + '&nbsp;item' + s);
		}
		else {
			$('#ph-filter-results').html('');
		}
	});

	// panels
	// newui show/hide, make sure theme colors are active
	if ($('#open-browse-panel').hasClass('active')) {
		$('#toolbar-btn').removeClass('hidden');
		setColors();
	}
	else if ($('#open-library-panel').hasClass('active')) {
		$('#lib-album-filter').hide();
		setColors();
	}
	else if ($('#open-playback-panel').hasClass('active')) {		
		$('#pl-filter').hide();
		$('#playlistSave').hide();
		$('#menu-top').css('color', adaptMcolor);
		$('#menu-bottom').css('color', adaptMcolor);
		$('#menu-top').css('background-color', adaptMback);
		$('#menu-bottom').css('background-color', adaptMback);
		$('#context-menu-playlist-item .dropdown-menu').css({color: adaptMcolor});
		$('#context-menu-playlist-item .dropdown-menu').css({backgroundColor: adaptMback});
		$('#menu-top .dropdown-menu').css({color: adaptMcolor});
		$('#menu-top .dropdown-menu').css({backgroundColor: adaptMback});
	    var current = parseInt(MPD.json['song']);
	    customScroll('pl', current, 200);
	}

	// control when library loads
	if ($('#open-library-panel').hasClass('active')) {
		$('#lib-loader').show();
		$.post('command/moode.php?cmd=loadlib', {}, function(data) {
			$('#lib-loader').hide();
			$('#lib-content').show();
			renderLibrary(data);
			libRendered = true;
		}, 'json');
	}

	// info button (i) show/hide toggle
	$('.info-toggle').click(function() {
		var spanId = '#' + $(this).data('cmd');
		if ($(spanId).hasClass('hide')) {
			$(spanId).removeClass('hide');
		}
		else {
			$(spanId).addClass('hide');
		}
	});
});
