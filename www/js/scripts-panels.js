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
 * 2018-07-11 TC moOde 4.2
 * - minor cleanup
 * - TEST add console.log to 'open a tab from external link' code to see if its ever used (yes, it is)
 * - new disableVolKnob() code to handle mpdmixer == disabled (0dB)
 * - add slsvc to engineCmd() start conditions
 * - new tabs, other code for newui v2
 * - chg readcfgengine to readcfgsystem
 * - fix search
 * - fix auto-scroll for Playback panel click
 * - context menu cmd for mpd updradio
 * - rm context menu position code
 * - screen saver
 * - adv browse search
 * - font-awesome 5
 *
 */

var libRendered = false; // trigger library load
var dbFilterResults = []; // r42y


jQuery(document).ready(function($) { 'use strict';

	// load session vars
    SESSION.json = sendMoodeCmd('GET', 'readcfgsystem');
    RADIO.json = sendMoodeCmd('GET', 'readcfgradio');
    THEME.json = sendMoodeCmd('GET', 'readcfgtheme');

	// r42w get client up address
    UI.clientIP = sendMoodeCmd('GET', 'clientip');
	//console.log(UI.clientIP);

	// connect to mpd engine
    engineMpd();

	// connect to cmd engine
    engineCmd();

 	// update state/color of clock radio icons
	if (SESSION.json['ckrad'] == 'Clock Radio' || SESSION.json['ckrad'] == 'Sleep Timer') {
		$('#clockradio-icon').removeClass('clockradio-off')
		$('#clockradio-icon').addClass('clockradio-on')
	} else {
		$('#clockradio-icon').removeClass('clockradio-on')
		$('#clockradio-icon').addClass('clockradio-off')
	}
    
    // populate browse panel root 
    mpdDbCmd('lsinfo', UI.path);
    
    // populate radio panel r42e
    mpdDbCmd('lsinfo_radio', 'RADIO');

    // setup pines notify
    $.pnotify.defaults.history = false;

	// volume disabled, 0dB output
	if (SESSION.json['mpdmixer'] == 'disabled') {
		disableVolKnob();
		SESSION.json['volknob'] = '0';
		SESSION.json['volmute'] = '0';
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'volknob': '0'});
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'volmute': '0'});
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
		if (SESSION.json['mpdmixer'] == 'disabled') {
			return false;
		}

        if (SESSION.json['volmute'] == '0') {
			SESSION.json['volmute'] = '1' // toggle to mute
			var newVol = 0;
			var volEvent = 'mute';
        }
		else {
			SESSION.json['volmute'] = '0' // toggle to unmute
			var newVol = SESSION.json['volknob'];
			var volEvent = 'unmute';
        }
		
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'volmute': SESSION.json['volmute']});
		setVolume(newVol, volEvent);
	});

	// mobile volume control
    $('#btn-mvol-popup').click(function() {
		if ($('#btn-mvol-popup').hasClass('disabled') == false) {
			$('.volume-display').css('margin-top', '-16px');

			if (SESSION.json['mpdmixer'] == 'disabled') {
				$('.volume-display').css('opacity', '.3');
			}
			$('#volume-popup').modal();
		}
	});

	// playback button handlers
	$('#play').click(function() {
		if (MPD.json['state'] == 'play') {
			$('#play i').removeClass('fas fa-play').addClass('fas fa-pause');
			$('#countdown-display').countdown('pause');
			if (MPD.json['file'].substr(0, 4).toLowerCase() == 'http') {
				var cmd = MPD.json['artist'] == 'Radio station' ? 'stop' : 'pause'; // pause if for upnp url
			}
			else {
			    var cmd = 'pause'; // song file
			}
		}
		else if (MPD.json['state'] == 'pause') {
			$('#play i').removeClass('fas fa-pause').addClass('fas fa-play');
			$('#countdown-display').countdown('resume');
			customScroll('pl', parseInt(MPD.json['song']), 200);
			var cmd = 'play';
		}
		else if (MPD.json['state'] == 'stop') {
		if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
		    $('#countdown-display').countdown({since: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
		}
		else {
		    $('#countdown-display').countdown({until: parseInt(MPD.json['time']), onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
		}
	        customScroll('pl', parseInt(MPD.json['song']), 200);
			var cmd = 'play';
		}
		window.clearInterval(UI.knob);
		sendMpdCmd(cmd);
	});
	$('#mplay').click(function() {
		if (MPD.json['state'] == 'play') {
			$('#mplay i').removeClass('fas fa-play').addClass('fas fa-pause');
			$('#m-countdown').countdown('pause');
			if (MPD.json['file'].substr(0, 4).toLowerCase() == 'http') {
				var cmd = MPD.json['artist'] == 'Radio station' ? 'stop' : 'pause'; // pause if for upnp url
			}
			else {
			    var cmd = 'pause'; // song file
			}
		}
		else if (MPD.json['state'] == 'pause') {
			$('#mplay i').removeClass('fas fa-pause').addClass('fas fa-play');
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
			
			// repaint not needed 
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
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'timecountup': SESSION.json['timecountup']});

		// update time and direction indicator
		// use sendMoodeCmd('GET', 'getmpdstatus') to obtain exact elapsed time
		if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
			refreshTimer(parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed']), parseInt(MPD.json['time']), MPD.json['state']); // count up
			$('#total').html(formatSongTime(MPD.json['time']) + '<i class="fas fa-caret-up countdown-caret"></i>');
		} 
		else {
			refreshTimer(parseInt(MPD.json['time'] - parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed'])), 0, MPD.json['state']); // count down
			$('#total').html(formatSongTime(MPD.json['time']) + '<i class="fas fa-caret-down countdown-caret"></i>');
		}
    });

	// toggle count up/down and direction icon, radio always counts up
	$('#m-countdown').click(function() {
		SESSION.json['timecountup'] == '1' ? SESSION.json['timecountup'] = '0' : SESSION.json['timecountup'] = '1';
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'timecountup': SESSION.json['timecountup']});

		// update time and direction indicator
		// use sendMoodeCmd('GET', 'getmpdstatus') to obtain exact elapsed time
		if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
			refreshMTimer(parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed']), parseInt(MPD.json['time']), MPD.json['state']); // count up
		} 
		else {
			refreshMTimer(parseInt(MPD.json['time'] - parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed'])), 0, MPD.json['state']); // count down
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
    $('.playlist').on('click', '.pl-action', function() {
		// store posn for later use by action menu selection
        UI.dbEntry[0] = $('.playlist .pl-action').index(this);

		// radio ststion
		// for clock radio, reuse UI.dbEntry[3] which is also used on the Browse panel 
		if ($('#pl-' + (UI.dbEntry[0] + 1) + ' .pl-entry .pll2').html().substr(0, 2) == '<i') { // has fa-microphone
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
	// newui set colors back to theme standard, show/hide
	$('#open-browse-panel a').click(function(){
		btnbarfix(themeMcolor, themeBack);
		$('#open-library-panel').css('border-left','none');
		$('#open-playback-panel').css('border-left','1px solid var(--btnbarcolor)');
		$('.tab-content').css({color: themeColor});
		$('.tab-content').css({backgroundColor: themeBack});
		$('#menu-top').css('color', themeMcolor);
		$('#menu-bottom').css('color', themeMcolor);
		SESSION.json['alphablend'] != '1.00' ? $('#menu-top').css('background-color', 'rgba(0,0,0,0)') : $('#menu-top').css('background-color', themeMback); // r42p
		$('#menu-bottom').css('background-color', themeMback);
		$('#context-menu-playlist-item.dropdown-menu').css('color', themeMcolor);
		$('#context-menu-playlist-item.dropdown-menu').css('background-color', themeMback);
		$('#context-menus .dropdown-menu').css('color', themeMcolor);
		$('#context-menus .dropdown-menu').css('background-color', themeMback);
		$('#menu-top .dropdown-menu').css({color: themeMcolor});
		$('#menu-top .dropdown-menu').css({backgroundColor: themeMback});			
	});

    // click on library tab
	// newui set colors back to theme standard, show/hide
	// r42e use delegate because browse-panel-btn and library-panel-btn change the html
	$("#menu-bottom").delegate("#open-library-panel a", 'click', function(){
		btnbarfix(themeMcolor, themeBack);
		$('#open-library-panel').css('border-left','none');
		$('#open-playback-panel').css('border-left','none');
		$('.tab-content').css({color: themeColor});
		$('.tab-content').css({backgroundColor: themeBack});
		$('#menu-top').css('color', themeMcolor);
		$('#menu-bottom').css('color', themeMcolor);
		SESSION.json['alphablend'] != '1.00' ? $('#menu-top').css('background-color', 'rgba(0,0,0,0)') : $('#menu-top').css('background-color', themeMback); // r42p
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
	// newui switch to adapative colors saved to element
	$('#open-playback-panel a').click(function(){
		$('#open-library-panel').css('border-left','1px solid var(--btnbarcolor)');
		$('#open-playback-panel').css('border-left','none');
		if (abFound == 'true') {$('.tab-content').css({backgroundColor: 'unset'});}
		$('#menu-top').css('color', adaptMcolor);
		$('#menu-bottom').css('color', adaptMcolor);
		SESSION.json['alphablend'] != '1.00' ? $('#menu-top').css('background-color', 'rgba(0,0,0,0)') : $('#menu-top').css('background-color', adaptMback); // r42p
		themeOp < '.74' ? $('#menu-bottom').css({backgroundColor: adaptMhalf}) : $('#menu-bottom').css({backgroundColor: adaptMback});
		$('#context-menu-playlist-item .dropdown-menu').css('color', adaptMcolor);
		$('#context-menu-playlist-item .dropdown-menu').css('background-color', adaptMback);
		$('#menu-top .dropdown-menu').css({color: adaptMcolor});
		$('#menu-top .dropdown-menu').css({backgroundColor: adaptMback});
		btnbarfix(adaptMcolor, adaptMback);
		// auto-scroll playlist
		setTimeout(function() { // r42m wait a bit for panel to load
			customScroll('pl', parseInt(MPD.json['song']), 200);
		}, 500);
	});

    // r42e toggle between Browse and Library panels
	$('.browse-panel-btn').click(function(){
		$('.library-panel-btn').removeClass('active');
		$('.browse-panel-btn').addClass('active');
		$('#open-library-panel').html('<a href="#browse-panel" class="open-library-panel" data-toggle="tab">Music</a>');
	});
	$('.library-panel-btn').click(function(){
		$('.library-panel-btn').addClass('active');
		$('.browse-panel-btn').removeClass('active');
		$('#open-library-panel').html('<a href="#library-panel" class="open-library-panel" data-toggle="tab">Music</a>');
	});

	// browse panel btns
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
		mpdDbCmd('lsinfo', path, '', 1); // r42q repl UI.browsemode with ''
	});
	$('#db-home').click(function() {
		UI.dbPos[10] = '1'; // r42y
		UI.path = '';
		mpdDbCmd('lsinfo', ''); // r42q repl UI.browsemode with ''
	});
	$('#db-search-submit').click(function() {
		var searchStr = '';
		if ($('#dbsearch-alltags').val() != '') {
			searchStr = $('#dbsearch-alltags').val();
			$.post('command/moode.php?cmd=search' + '&tagname=any', {'query': searchStr}, function(data) {renderBrowse(data, '', '', searchStr);}, 'json');
		}
		else {
			searchStr = $('#dbsearch-genre').val() == '' ? '' : 'genre "' + $('#dbsearch-genre').val() + '"'
			searchStr += $('#dbsearch-artist').val() == '' ? '' : ' artist "' + $('#dbsearch-artist').val() + '"'
			searchStr += $('#dbsearch-album').val() == '' ? '' : ' album "' + $('#dbsearch-album').val() + '"'
			searchStr += $('#dbsearch-title').val() == '' ? '' : ' title "' + $('#dbsearch-title').val() + '"'
			searchStr += $('#dbsearch-albumartist').val() == '' ? '' : ' albumartist "' + $('#dbsearch-albumartist').val() + '"'
			searchStr += $('#dbsearch-date').val() == '' ? '' : ' date "' + $('#dbsearch-date').val() + '"'
			searchStr += $('#dbsearch-composer').val() == '' ? '' : ' composer "' + $('#dbsearch-composer').val() + '"'
			searchStr += $('#dbsearch-performer').val() == '' ? '' : ' performer "' + $('#dbsearch-performer').val() + '"'
			searchStr += $('#dbsearch-file').val() == '' ? '' : ' file "' + $('#dbsearch-file').val() + '"'
			if (searchStr != '') {
				$.post('command/moode.php?cmd=search' + '&tagname=specific', {'query': searchStr}, function(data) {renderBrowse(data, '', '', searchStr);}, 'json');
			}
		}
	});
	$('#db-search-reset').click(function() {
		$('#dbsearch-alltags, #dbsearch-genre, #dbsearch-artist, #dbsearch-album, #dbsearch-title, #dbsearch-albumartist, #dbsearch-date, #dbsearch-composer, #dbsearch-performer, #dbsearch-file').val('');
		$('#dbsearch-alltags').focus();
	});  
	$('#dbsearch-modal').on('shown.bs.modal', function () {
		$('#db-search-results').css('font-weight', 'normal');
		$('.database li').removeClass('active');
		$('#dbsearch-alltags').focus();
	});
	$('#db-search-results').click(function() {
		$('.database li').removeClass('active');
		$('#db-search-results').css('font-weight', 'bold');
		dbFilterResults = [];
		$('.database li').each(function() {
			dbFilterResults.push({'file': $(this).attr('data-path')});
		});
	});
	// context menu
	$('#context-menu-db-search-results a').click(function(e) {
		$('#db-search-results').css('font-weight', 'normal');
	    if ($(this).data('cmd') == 'addall') {
	        mpdDbCmd('addall', dbFilterResults);
	        notify('add', '');
		}
	    if ($(this).data('cmd') == 'playall') {
	        mpdDbCmd('playall', dbFilterResults);
	        notify('add', '');
		}
	    if ($(this).data('cmd') == 'clrplayall') {
	        mpdDbCmd('clrplayall', dbFilterResults);
	        notify('clrplay', '');
		}
	});

	// click on browse entry 
	// newui rm loop and inline css, use show/hide
	// r42e rm code referencing RADIO, r42q rm autofocus on search
	$('.database').on('click', '.db-browse', function() { // r42x
		//console.log('click db-browse');		
	    if ($(this).hasClass('db-folder')) {
			var path = $(this).parent().data('path');
			var entryID = $(this).parent().attr('id');
			entryID = entryID.replace('db-','');
			UI.dbPos[UI.dbPos[10]] = entryID;
			++UI.dbPos[10];
			mpdDbCmd('lsinfo', path, '', 0);				
		} 
		else if ($(this).hasClass('db-savedplaylist')) {
			var path = $(this).parent().data('path');
			var entryID = $(this).parent().attr('id');
			entryID = entryID.replace('db-','');
			UI.dbPos[UI.dbPos[10]] = entryID;
			++UI.dbPos[10];
			mpdDbCmd('listsavedpl', path, '', 0);
		}
	});

	// browse, radio action menus
	$('.database, .database-radio').on('click', '.db-action', function() {
		//console.log('click db-action');
		UI.dbEntry[0] = $(this).parent().attr('data-path');
		UI.dbEntry[3] = $(this).parent().attr('id'); // used in .context-menu a click handler to remove highlight
		$('#db-search-results').css('font-weight', 'normal'); // r42y
		$('.database li, .database-radio li').removeClass('active');
		$(this).parent().addClass('active');
	});
    
	// r42x remove highlight from station logo
	$('.btnlist-top-ra').click(function() {
		if (UI.dbEntry[3].substr(0, 3) == 'db-') {
			$('#' + UI.dbEntry[3]).removeClass('active');
		}
	});

	// buttons on modals
	$('.btn-del-savedpl').click(function(){
		mpdDbCmd('delsavedpl', UI.dbEntry[0]);
		notify('delsavedpl', '');
	});
	$('.btn-del-radiostn').click(function(){
		mpdDbCmd('delstation', UI.dbEntry[0]);
		notify('delstation', '');
	});
	$('.btn-add-radiostn').click(function(){
		mpdDbCmd('addstation', $('#add-station-name').val() + '\n' + $('#add-station-url').val() + '\n');
		notify('addstation', '');
	});
	$('.btn-update-radiostn').click(function(){
		mpdDbCmd('updstation', $('#edit-station-name').val() + '\n' + $('#edit-station-url').val() + '\n');
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

	// TEST is this code ever used (yes)
	// open a tab from external link
	var url = document.location.toString();
	if (url.match('#')) {
		//console.log('open tab from external link');
		$('#menu-bottom a[href=#'+url.split('#')[1]+']').tab('show');
	}

    // playlist typedown search
	// newui add click handler for magnifying glass button in playback
	$('#pl-search-btn').click(function(){
		if ($('#pl-filter').css('display') == 'inline-block') {
			$('#pl-filter, #pl-filter-results').css('display', 'none');
			$('#pl-filter').val('');
			$('#pl-filter-results').html('');
			$('.playlist li').each(function(){$(this).show();});
			$.scrollTo(0 , 500);
		}
		else {
			$('#pl-filter').css('display', 'inline-block');
			$('#pl-filter').focus();
			if ($(window).width() > 479) {
				$('#pl-filter-results').css('display', 'inline-block');
			}
			else {
				$('#pl-filter-results').css('display', 'none');
			}
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

    // library typedown search
	// newui add click handler for magnifying glass button in library
    $('#lib-search-btn').click(function(e){		
		if ($('#lib-album-filter').css('display') == 'inline-block') {
			$('#lib-album-filter, #lib-album-filter-results').css('display', 'none');
			$('#lib-album-filter').val('');
			$('#lib-album-filter-results').html('');
			clickedLibItem(e, undefined, LIB.filters.artists, renderArtists);
		}
		else {
			$('#lib-album-filter').css('display', 'inline-block');
			$('#lib-album-filter').focus();
			if ($(window).width() > 479) {
				$('#lib-album-filter-results').css('display', 'inline-block');
			}
			else {
				$('#lib-album-filter-results').css('display', 'none');
			}
		}	
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
	
	// radio station typedown search
	// newui add click handler for magnifying glass button in radio panel
	$('#ra-search-btn').click(function(){
		if ($('#ra-filter').css('display') == 'inline-block') {
			$('#ra-filter, #ra-filter-results').css('display', 'none');
			$('#ra-filter').val('');
			$('#ra-filter-results').html('');
			$('.database-radio li').each(function(){$(this).show();});	
		}
		else {
			$('#ra-filter').css('display', 'inline-block');
			$('#ra-filter').focus();
			if ($(window).width() > 479) {
				$('#ra-filter-results').css('display', 'inline-block');
			}
			else {
				$('#ra-filter-results').css('display', 'none');
			}
		}	
	});
	$('#ra-filter').keyup(function(){
		$.scrollTo(0 , 500);
		var filter = $(this).val(), count = 0;
		$('.database-radio li').each(function(){
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
			$('#ra-filter-results').html((+count) + '&nbsp;station' + s);
	    }
		else {
			$('#ra-filter-results').html('');
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
	$('#pl-filter, #lib-album-filter, #ra-filter').css('display', 'none'); /* r42m */
	// newui show/hide, make sure theme colors are active
	if ($('#open-browse-panel').hasClass('active')) {
		$('#toolbar-btn').removeClass('hidden');
		setColors();
	}
	else if ($('#open-library-panel').hasClass('active')) {
		setColors();
	}
	else if ($('#open-playback-panel').hasClass('active')) {		
		$('#playlistSave').hide();
		$('#menu-top').css('color', adaptMcolor);
		$('#menu-bottom').css('color', adaptMcolor);
		SESSION.json['alphablend'] != '1.00' ? $('#menu-top').css('background-color', 'rgba(0,0,0,0)') : $('#menu-top').css('background-color', adaptMback); // r42p
		$('#menu-bottom').css('background-color', adaptMback);
		$('#context-menu-playlist-item .dropdown-menu').css({color: adaptMcolor});
		$('#context-menu-playlist-item .dropdown-menu').css({backgroundColor: adaptMback});
		$('#menu-top .dropdown-menu').css({color: adaptMcolor});
		$('#menu-top .dropdown-menu').css({backgroundColor: adaptMback});
		// auto-scroll playlist
		setTimeout(function() { // r42m wait a bit for panel to load
			customScroll('pl', parseInt(MPD.json['song']), 200);
		}, 500);
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

	// r42k scrollto current song, mobile
	// see renderUI() and renderPlaylist() for how #currentsong html is populated
	$('#currentsong').click(function() {
        customScroll('pl', parseInt(MPD.json['song']), 200);
	});

	// r42u screen saver reset
	$('#screen-saver, #playback-panel, #library-panel, #browse-panel, #radio-panel, #menu-bottom').click(function() {
		//console.log('resetscnsaver: timeout (' + SESSION.json['scnsaver_timeout'] + ')');
		if ($('#playback-panel').hasClass('hidden')) {
			notify('scnsaverexit','',3000);
		}

		//console.log('resetscnsaver: wait 3 secs');
		setTimeout(function() { // r42w wait a bit to allow other job that may be queued to be processed
			var resp = sendMoodeCmd('GET', 'resetscnsaver'); // sync
			//console.log('resetscnsaver: ' + resp);
			if ($('#playback-panel').hasClass('hidden')) {
				$('#screen-saver').hide();
			}
		}, 3000);
	});

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
