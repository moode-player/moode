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
 * 2018-07-18 TC moOde 4.2 update
 * - set default panel for Music tab
 * - set Lib artists column heading
 * 2018-09-27 TC moOde 4.3
 * - fixes to tab btns
 * - favorites feature
 * - refresh btn for browse and radio panels
 * - home btn for radio panel
 * - radio folders, radioRendering flag
 * - album cover view
 * - HUD playlist
 * - Android soft kbd fix
 * - loadLibrary()
 * - click on artist name in lib meta area
 * - minor code cleanup and refactoring
 *
 */

jQuery(document).ready(function($) { 'use strict';
	//console.log($(window).width() + 'x' + $(window).height());
	
	// r43g to compensate for Android popup kbd changing the viewport
	$("meta[name=viewport]").attr("content", "height=" + $(window).height() + ", width=" + $(window).width() + ", initial-scale=1.0, maximum-scale=1.0");
	// store device pixel ratio
	var result = sendMoodeCmd('POST', 'updcfgsystem', {'library_pixelratio': window.devicePixelRatio});

	// load session vars
    SESSION.json = sendMoodeCmd('GET', 'readcfgsystem');
    RADIO.json = sendMoodeCmd('GET', 'readcfgradio');
    THEME.json = sendMoodeCmd('GET', 'readcfgtheme');

	// set theme
	themeColor = str2hex(THEME.json[SESSION.json['themename']]['tx_color']);
	themeBack = 'rgba(' + THEME.json[SESSION.json['themename']]['bg_color'] + ',' + SESSION.json['alphablend'] +')';
	themeMcolor = str2hex(THEME.json[SESSION.json['themename']]['tx_color']);
	tempcolor = splitColor($('.dropdown-menu').css('background-color'));
	themeOp = tempcolor[3];
	themeMback = 'rgba(' + THEME.json[SESSION.json['themename']]['bg_color'] + ',' + themeOp +')';
	document.body.style.setProperty('--btnbarback', themeMback);
	document.body.style.setProperty('--themetext', themeMcolor);
	adaptColor = themeColor;
	adaptBack = themeBack;
	adaptMhalf = themeMback;
	adaptMcolor = themeMcolor;
	adaptMback = themeMback;
	tempback = themeMback;
	abFound = false; // add boolean for whether a adaptive background has been found
	showMenuTopW = false
	showMenuTopR = false	
	setColors();

	// get client up address
    UI.clientIP = sendMoodeCmd('GET', 'clientip');
	//console.log(UI.clientIP);

	// connect to mpd engine
    engineMpd();

	// connect to cmd engine
    engineCmd();

	// set default panel for Music tab
	if (SESSION.json['musictab_default'] == 'Browse') {
		$('.browse-panel-btn').addClass('active');
		$('.library-panel-btn').removeClass('active');
		$('.album-panel-btn').removeClass('active');
		$('#open-library-panel').html('<a href="#browse-panel" class="open-library-panel" data-toggle="tab">Music</a>');
	}
	else if (SESSION.json['musictab_default'] == 'Library'){
		$('.browse-panel-btn').removeClass('active');
		$('.library-panel-btn').addClass('active');
		$('.album-panel-btn').removeClass('active');
		$('#lib-albumcover, #lib-albumcover-header').hide(); // r43f
		$('#open-library-panel').html('<a href="#library-panel" class="open-library-panel" data-toggle="tab">Music</a>');
		//console.log('musictab_default (Library), libRendered=' + libRendered);
		loadLibrary(); // r43p
	}
	else if (SESSION.json['musictab_default'] == 'Albums'){
		$('.library-panel-btn').removeClass('active');
		$('.browse-panel-btn').removeClass('active');
		$('.album-panel-btn').addClass('active');
		$('#top-columns').css('display', 'none');
		$('#lib-albumcover, #lib-albumcover-header').show();
		$('#bottom-row').css('display', 'none');
		$('#open-library-panel').html('<a href="#library-panel" class="open-library-panel" data-toggle="tab">Music</a>');
		//console.log('musictab_default (Albums), libRendered=' + libRendered);
		loadLibrary(); // r43p
	}

 	// update state/color of clock radio icons
	if (SESSION.json['ckrad'] == 'Clock Radio' || SESSION.json['ckrad'] == 'Sleep Timer') {
		$('#clockradio-icon').removeClass('clockradio-off')
		$('#clockradio-icon').addClass('clockradio-on')
	}
	else {
		$('#clockradio-icon').removeClass('clockradio-on')
		$('#clockradio-icon').addClass('clockradio-off')
	}
    
    // populate browse panel root 
    mpdDbCmd('lsinfo', '');
    
    // populate radio panel
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
		if ($('#mt2').css('display') == 'block') {
			$('#volume-2').val(SESSION.json['volknob']); // popup knob
		}
		else {
			$('#volume').val(SESSION.json['volknob']); // knob
		}	
		$('.volume-display').css('opacity', '');
		$('.volume-display').text(SESSION.json['volknob']);
	}

	// mute toggle
	$('.volume-display,#ssvolume').click(function() {
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
	$('#ssplay').click(function() {
		if (MPD.json['state'] == 'play') {
			$('#ssplay i').removeClass('fas fa-play').addClass('fas fa-pause');
			if (MPD.json['file'].substr(0, 4).toLowerCase() == 'http') {
				var cmd = MPD.json['artist'] == 'Radio station' ? 'stop' : 'pause';
			}
			else {
			    var cmd = 'pause';
			}
		}
		else if (MPD.json['state'] == 'pause') {
			$('#ssplay i').removeClass('fas fa-pause').addClass('fas fa-play');
			var cmd = 'play';
		}
		else if (MPD.json['state'] == 'stop') {
			var cmd = 'play';
		}
		window.clearInterval(UI.knob);
		sendMpdCmd(cmd);
	});


	$('#next,#mnext,#ssnext').click(function() {
        sendMpdCmd('next');
	});
	$('#prev,#mprev,#ssprev').click(function() {
        if (parseInt(MPD.json['time']) > 0 && parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed']) > 0) {
			window.clearInterval(UI.knob);
			if ($('#mt2').css('display') == 'block') {
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
	$('#volumeup,#volumeup-2,#ssvolup').click(function() {
		var curVol = parseInt(SESSION.json['volknob']);
		var newVol = curVol < 100 ? curVol + 1 : 100;
		setVolume(newVol, '');
	});
	$('#volumedn,#volumedn-2,#ssvoldn').click(function() {
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
	$('#countdown-display').click(function() {
		SESSION.json['timecountup'] == '1' ? SESSION.json['timecountup'] = '0' : SESSION.json['timecountup'] = '1';
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'timecountup': SESSION.json['timecountup']});
		if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
			refreshTimer(parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed']), parseInt(MPD.json['time']), MPD.json['state']); // count up
			$('#total').html(formatSongTime(MPD.json['time']) + '<i class="fas fa-caret-up countdown-caret"></i>');
		} 
		else {
			refreshTimer(parseInt(MPD.json['time'] - parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed'])), 0, MPD.json['state']); // count down
			$('#total').html(formatSongTime(MPD.json['time']) + '<i class="fas fa-caret-down countdown-caret"></i>');
		}
    });
	$('#m-countdown').click(function() {
		SESSION.json['timecountup'] == '1' ? SESSION.json['timecountup'] = '0' : SESSION.json['timecountup'] = '1';
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'timecountup': SESSION.json['timecountup']});
		if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
			refreshMTimer(parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed']), parseInt(MPD.json['time']), MPD.json['state']); // count up
		} 
		else {
			refreshMTimer(parseInt(MPD.json['time'] - parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed'])), 0, MPD.json['state']); // count down
		}
    });

    // click on playlist entry
    $('.playlist').on('click', '.pl-entry', function() {
        var pos = $('.playlist .pl-entry').index(this);
        var cmd = MPD.json['song'] == pos ? 'stop,play ' + pos : 'play ' + pos;        
		$('#ss-hud').fadeOut();
		$('.ss-playlist li.active').removeClass('active');	

        sendMpdCmd(cmd);
        $(this).parent().addClass('active');

		if ($('#mt2').css('display') == 'block') { // for mobile scroll to top
			$('html, body').animate({ scrollTop: 0 }, 'fast');
		}
    });
    // click on ss-playlist entry
    $('.ss-playlist').on('click', '.pl-entry', function() {
        var pos = $('.ss-playlist .pl-entry').index(this);
        var cmd = MPD.json['song'] == pos ? 'stop,play ' + pos : 'play ' + pos;        

        sendMpdCmd(cmd);
        $(this).parent().addClass('active');

		if ($('#mt2').css('display') == 'block') { // for mobile scroll to top
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
			UI.dbEntry[3] = $('#pl-' + (UI.dbEntry[0] + 1) + ' .pl-entry .pll2').text().trim();
		}
		// songfile title, artist
		else {
			var txt = $('#pl-' + (UI.dbEntry[0] + 1) + ' .pl-entry .pll2').text();
			UI.dbEntry[3] = $('#pl-' + (UI.dbEntry[0] + 1) + ' .pl-entry .pll1').text().trim() + ', ' + txt.substr(0, (txt.indexOf('-') - 1));
		}
    });

	// save playlist modal
	$('#plSave').click(function() {
		$('#savepl-modal').modal();
	});
	// set favorites modal 
	$('#setFav').click(function() {
		var favname = sendMoodeCmd('GET', 'getfavname');
		$('#pl-favName').val(favname);
		$('#setfav-modal').modal();
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
	// set favorites
    $('#pl-btnSetFav').click(function(){
		var favname = $('#pl-favName').val();

		if (favname) {
			if (~favname.indexOf('NAS') || ~favname.indexOf('RADIO') || ~favname.indexOf('SDCARD')) {
				notify('plnameerror', '');
			}
			else {
				sendMoodeCmd('GET', 'setfav&favname=' + favname,'',true);
				notify('favset', '');
			}
		}
		else {
			notify('needplname', '');
		}
    });
	// add item to favorites
    $('#addfav, #maddfav').click(function(){
		// pulse the btn
		$('#addfav i, #maddfav i').addClass('pulse');
		$('#addfav i, #maddfav i').addClass('fas');
		setTimeout(function() {
			$('#addfav i, #maddfav i').removeClass('fas');
			$('#addfav i, #maddfav i').removeClass('pulse');
		}, 1000);

		// add current pl item to favorites playlist
		if (MPD.json['file'] != null) {
			sendMoodeCmd('GET', 'addfav&favitem=' + MPD.json['file'],'',true);
			notify('favadded', '');
		}
		else {
			notify('nofavtoadd', '');
		}
    });

	// click on browse tab, switch to non-adaptive colors
	$('#open-browse-panel a').click(function(){
		btnbarfix(themeBack, themeBack);
		$('#open-library-panel').css('border-left','none');
		$('#open-playback-panel').css('border-left','1px solid var(--btnbarcolor)');
		$('.tab-content').css({color: themeColor});
		$('.tab-content').css({backgroundColor: themeBack});
		$('#menu-top').css('color', themeMcolor);
		$('#menu-bottom').css('color', themeMcolor);
		SESSION.json['alphablend'] != '1.00' ? $('#menu-top').css('background-color', 'rgba(0,0,0,0)') : $('#menu-top').css('background-color', themeMback);
		document.body.style.setProperty('--btnbarback', themeMback);
		$('#context-menu-playlist-item.dropdown-menu').css('color', themeMcolor);
		$('#context-menu-playlist-item.dropdown-menu').css('background-color', themeMback);
		$('#context-menus .dropdown-menu').css('color', themeMcolor);
		$('#context-menus .dropdown-menu').css('background-color', themeMback);
		$('#menu-top .dropdown-menu').css({color: themeMcolor});
		$('#menu-top .dropdown-menu').css({backgroundColor: themeMback});			
	});
    // click on library tab, switch to non-adaptive colors
	// use delegate because browse-panel-btn and library-panel-btn change the html
	$("#menu-bottom").delegate("#open-library-panel a", 'click', function(){
		btnbarfix(themeBack, themeBack);
		$('#open-library-panel').css('border-left','none');
		$('#open-playback-panel').css('border-left','none');
		$('.tab-content').css({color: themeColor});
		$('.tab-content').css({backgroundColor: themeBack});
		$('#menu-top').css('color', themeMcolor);
		$('#menu-bottom').css('color', themeMcolor);
		SESSION.json['alphablend'] != '1.00' ? $('#menu-top').css('background-color', 'rgba(0,0,0,0)') : $('#menu-top').css('background-color', themeMback);
		document.body.style.setProperty('--btnbarback', themeMback);
		$('#context-menu-playlist-item.dropdown-menu').css('color', themeMcolor);
		$('#context-menu-playlist-item.dropdown-menu').css('background-color', themeMback);
		$('#context-menus .dropdown-menu').css('color', themeMcolor);
		$('#context-menus .dropdown-menu').css('background-color', themeMback);
		$('#menu-top .dropdown-menu').css({color: themeMcolor});
		$('#menu-top .dropdown-menu').css({backgroundColor: themeMback});
		if ($('.album-panel-btn').hasClass('active')) {
			setTimeout(function() {
				//console.log('lazyload started');
				$('img.lazy').lazyload({
				    container: $('#lib-albumcover')
				});
			}, 250);
		}
	});
	// click on playback tab, switch to adaptive colors
	$('#open-playback-panel a').click(function(){
		$('#open-library-panel').css('border-left','1px solid var(--btnbarcolor)');
		$('#open-playback-panel').css('border-left','none');
		if (abFound == 'true') {$('.tab-content').css({backgroundColor: 'unset'});}
		$('#menu-top').css('color', adaptMcolor);
		$('#menu-bottom').css('color', adaptMcolor);
		SESSION.json['alphablend'] != '1.00' ? $('#menu-top').css('background-color', 'rgba(0,0,0,0)') : $('#menu-top').css('background-color', adaptMback);
		themeOp < .74 ? tempback = adaptMhalf : tempback = adaptMback;
		document.body.style.setProperty('--btnbarback', tempback);
		$('#context-menu-playlist-item .dropdown-menu').css('color', adaptMcolor);
		$('#context-menu-playlist-item .dropdown-menu').css('background-color', adaptMback);
		$('#menu-top .dropdown-menu').css({color: adaptMcolor});
		$('#menu-top .dropdown-menu').css({backgroundColor: adaptMback});
		btnbarfix(adaptBack, adaptBack);
		// auto-scroll playlist
		setTimeout(function() { // wait a bit for panel to load
			customScroll('pl', parseInt(MPD.json['song']), 200);
		}, 500);
	});

    // switch between Browse, Library and Album panels
	$('.browse-panel-btn').click(function(){
		$('.browse-panel-btn').addClass('active');
		$('.library-panel-btn').removeClass('active');
		$('.album-panel-btn').removeClass('active');
		$('#lib-albumcover, #lib-albumcover-header').hide();
		$('#open-library-panel').html('<a href="#browse-panel" class="open-library-panel" data-toggle="tab">Music</a>');
	});
	$('.library-panel-btn').click(function(){
		$('.browse-panel-btn').removeClass('active');
		$('.library-panel-btn').addClass('active');
		$('.album-panel-btn').removeClass('active');
		$('#lib-albumcover, #lib-albumcover-header').hide();
		$('#top-columns, #bottom-row').css('display', 'flex');
		$('#open-library-panel').html('<a href="#library-panel" class="open-library-panel" data-toggle="tab">Music</a>');		
		// render library
		if (!libRendered) {
		    $('#lib-content').hide();
		    $('#lib-loader').show();
			//console.log('.library-panel-btn (click), libRendered=' + libRendered);
			loadLibrary(); // r43p
		}
		if (UI.libPos[0] >= 0) { // lib entry clicked or random album
		    $('#albumsList .lib-entry').removeClass('active');
			$('#albumsList .lib-entry').eq(UI.libPos[0]).addClass('active');
			customScroll('albums', UI.libPos[0], 200);
		}
		else if (UI.libPos[0] == -2) { // lib headers clicked
		    $('#albumsList .lib-entry').removeClass('active');
			$('#lib-album').scrollTo(0, 200); //r43q
			$('#lib-artist').scrollTo(0, 200); //r43q
			UI.libPos[0] = -1;
		}
		else if (UI.libPos[0] == -3) { // lib search performed
		    $('#albumsList .lib-entry').removeClass('active');
			$('#lib-album').scrollTo(0, 200); //r43q
			$('#lib-coverart-img').html('<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-all">' + '<img class="lib-coverart" ' + 'src="' + UI.defCover + '"></a>');
			$('#lib-albumname, #lib-artistname, #lib-albumyear, #lib-numtracks, #songsList').html('');
			UI.libPos[0] = -1;
		}
	});
	$('.album-panel-btn').click(function(){
		$('.browse-panel-btn').removeClass('active');
		$('.library-panel-btn').removeClass('active');
		$('.album-panel-btn').addClass('active');
		$('#top-columns, #bottom-row').css('display', 'none');
		$('#lib-albumcover, #lib-albumcover-header').show();
		$('#lib-albumcover').css('height', '100%');
		$('#open-library-panel').html('<a href="#library-panel" class="open-library-panel" data-toggle="tab">Music</a>');
		// render library
		if (!libRendered) {
		    $('#lib-content').hide();
		    $('#lib-loader').show();
			//console.log('.album-panel-btn (click), libRendered=' + libRendered);
			loadLibrary(); // r43p
		}
		setTimeout(function() {
			//console.log('lazyload started');
			$('img.lazy').lazyload({
			    container: $('#lib-albumcover')
			});
			if (UI.libPos[1] >= 0) { // lib entry clicked or random album
			    $('#albumcovers .lib-entry').removeClass('active');
				$('#albumcovers .lib-entry').eq(UI.libPos[1]).addClass('active');
				customScroll('albumcovers', UI.libPos[1], 200);	
			}
			else if (UI.libPos[1] == -2 || UI.libPos[1] == -3) { // lib headers clicked or search performed
			    $('#albumcovers .lib-entry').removeClass('active');
				$('#lib-albumcover').scrollTo(0, 200); //r43q
				UI.libPos[1] = -1;	
			}
		}, 250);
	});

	// click on artist name in lib meta area
	$('#lib-artistname').click(function(e) {
		$("#artistsList li .lib-entry:contains('" + $('#lib-artistname').text() + "')").click();
		customScroll('artists', UI.libPos[2], 200);
	});

	// browse panel
	$('.database').on('click', '.db-browse', function() {
	    if ($(this).hasClass('db-folder') || $(this).hasClass('db-savedplaylist')) {
			var cmd = $(this).hasClass('db-folder') ? 'lsinfo' : 'listsavedpl';
			UI.dbPos[UI.dbPos[10]] = $(this).parent().attr('id').replace('db-','');
			++UI.dbPos[10];
			mpdDbCmd(cmd, $(this).parent().data('path'));				
		} 
	});
	$('#db-back').click(function() {
		$('#db-search-results').hide();
		$('#dbfs').val('');
		if (UI.dbPos[10] > 0) {
			--UI.dbPos[10];
			var path = UI.path;
			var cutpos = path.lastIndexOf('/');
			if (cutpos !=-1) {
				var path = path.slice(0,cutpos);
			}
			else {
				path = '';
			}
			mpdDbCmd('lsinfo', path);

			if (UI.dbPos[10] == 0) {
				UI.dbPos.fill(0); 
			}
		}
	});
	$('#db-home').click(function() {
		$('#db-search-results').hide();
		$('#dbfs').val('');
		UI.dbPos.fill(0);
		UI.path = '';
		mpdDbCmd('lsinfo', '');
	});
	$('#db-refresh').click(function() {
		UI.dbCmd = UI.dbCmd != 'lsinfo' && UI.dbCmd != 'listsavedpl' ? 'lsinfo' : UI.dbCmd;
		if (UI.dbCmd == 'lsinfo' || UI.dbCmd == 'listsavedpl') {
			mpdDbCmd(UI.dbCmd, UI.path);
		}		
	});
	$('#db-search-submit').click(function() {
		var searchStr = '';
		if ($('#dbsearch-alltags').val() != '') {
			searchStr = $('#dbsearch-alltags').val();
			$.post('command/moode.php?cmd=search' + '&tagname=any', {'query': searchStr}, function(data) {renderBrowse(data, '', searchStr);}, 'json');
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
				$.post('command/moode.php?cmd=search' + '&tagname=specific', {'query': searchStr}, function(data) {renderBrowse(data, '', searchStr);}, 'json');
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

	// radio panel
	$('.database-radio').on('click', '.db-browse', function() {
		if ($(this).hasClass('db-radiofolder')) {
			UI.raPos[UI.raPos[5]] = $(this).parent().attr('id').replace('db-','');
			++UI.raPos[5];
			mpdDbCmd('lsinfo_radio', $(this).parent().data('path'));				
		} 
	});
	$('#ra-home').click(function() {
		$('#ra-filter-results').hide();
		UI.raPos[5] = 0;
		UI.pathr = '';
		mpdDbCmd('lsinfo_radio', 'RADIO');
	});
	// refresh panel
	$('#ra-refresh').click(function() {
		mpdDbCmd('lsinfo_radio', UI.pathr);
	});
	// radio search
	$('#ra-filter').keyup(function(e){
		if (!showSearchResetRa) {
			$('#searchResetRa').show();
			showSearchResetRa = true;
		}

		clearTimeout(searchTimer);

		var selector = this;
		searchTimer = setTimeout(function(){
			var filter = $(selector).val()
			var count = 0;

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
			$('#database-radio').scrollTo(0, 200);
		}, 750);
	});
	$('#searchResetRa').click(function() {
		$("#searchResetRa").hide();
		showSearchResetRa = false;
		$('.database-radio li').css('display', 'inline-block');
		$('#ra-filter-results').html('');
	});

    // playlist search
	$('#pl-filter').keyup(function(e){
		if (!showSearchResetPl) {
			$('#searchResetPl').show();
			showSearchResetPl = true;
		}

		clearTimeout(searchTimer);

		var selector = this;
		searchTimer = setTimeout(function(){
			var filter = $(selector).val()
			var count = 0;

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
			$('#container-playlist').scrollTo(0, 200);
		}, 750);
	});
	$('#searchResetPl').click(function() {
		$("#searchResetPl").hide();
		showSearchResetPl = false;
		$('.playlist li').css('display', 'block');
		$('#pl-filter-results').html('');
	});

    // library search
	$('#lib-album-filter').keyup(function(e){
		if (!showSearchResetLib) {
			$('#searchResetLib').show();
			showSearchResetLib = true;
		}

		clearTimeout(searchTimer);

		var selector = this;
		searchTimer = setTimeout(function(){
			var filter = $(selector).val()
			var count = 0;

			$('.albumslist li').each(function() {
				if ($(this).text().search(new RegExp(filter, 'i')) < 0) {
					$(this).hide();
				}
				else {
					$(this).show();
					count++;
				}
			});
			$('.albumcovers li').each(function() {
				if ($(this).text().search(new RegExp(filter, 'i')) < 0) {
					$(this).hide();
				}
				else {
					$(this).show();
				}
			});
			var s = (count == 1) ? '' : 's';
			if (filter != '') {
				$('#lib-album-filter-results').html((+count) + '&nbsp;album' + s);
			}
			else {
				$('#lib-album-filter-results').html('');
			}

			if ($('.album-panel-btn').hasClass('active')) {
				$('#bottom-row').css('display', 'none');
			}
		    $('#albumcovers .lib-entry').removeClass('active');
		    $('#albumsList .lib-entry').removeClass('active');
			$('#lib-albumcover').css('height', '100%');
			$('#lib-album').scrollTo(0, 200);
			$('#lib-albumcover').scrollTo(0, 200);
			UI.libPos.fill(-3);

			if ($('.album-panel-btn').hasClass('active')) {
				//console.log('lazyload started');
				$('img.lazy').lazyload({
				    container: $('#lib-albumcover')
				});
			}
		}, 750);
	});

	// playback history search
	$('#ph-filter').keyup(function(e){
		if (!showSearchResetPh) {
			$('#searchResetPh').show();
			showSearchResetPh = true;
		}

		clearTimeout(searchTimer);

		var selector = this;
		searchTimer = setTimeout(function(){
			var filter = $(selector).val()
			var count = 0;
			
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
			$('#container-playhistory').scrollTo(0, 200);
		}, 750);
	});
	$('#searchResetPh').click(function() {
		$("#searchResetPh").hide();
		showSearchResetPh = false;
		$('.playhistory li').css('display', 'block');
		$('#ph-filter-results').html('');
	});

	// browse, radio action menus
	$('.database, .database-radio').on('click', '.db-action', function() {
		UI.dbEntry[0] = $(this).parent().attr('data-path');
		UI.dbEntry[3] = $(this).parent().attr('id'); // used in .context-menu a click handler to remove highlight
		$('#db-search-results').css('font-weight', 'normal');
		$('.database li, .database-radio li').removeClass('active');
		$(this).parent().addClass('active');
	});
    
	// remove highlight from station logo
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

	// speed buttons on plaback history log
	$('.ph-firstPage').click(function(){
		$('#container-playhistory').scrollTo(0 , 200);
	});
	$('.ph-lastPage').click(function(){
		$('#container-playhistory').scrollTo('100%', 200);
	});

	// for open tab from external link
	var url = document.location.toString();
	if (url.match('#')) {
		//console.log('open tab from external link');
		$('#menu-bottom a[href=#'+url.split('#')[1]+']').tab('show');
	}

	// panels
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
		SESSION.json['alphablend'] != '1.00' ? $('#menu-top').css('background-color', 'rgba(0,0,0,0)') : $('#menu-top').css('background-color', adaptMback);
		document.body.style.setProperty('--btnbarback', adaptMback);
		$('#context-menu-playlist-item .dropdown-menu').css({color: adaptMcolor});
		$('#context-menu-playlist-item .dropdown-menu').css({backgroundColor: adaptMback});
		$('#menu-top .dropdown-menu').css({color: adaptMcolor});
		$('#menu-top .dropdown-menu').css({backgroundColor: adaptMback});
		// auto-scroll playlist, wait a bit for playlist to load 
		setTimeout(function() { 
			customScroll('pl', parseInt(MPD.json['song']), 200);
		}, 500);
	}

	// control when library loads
	if ($('.library-panel-btn').hasClass('active') || $('.album-panel-btn').hasClass('active')) {
		//console.log('library or album panel-btn (active), libRendered=' + libRendered);
		if (!libRendered) {
			$('#lib-loader').show();
			loadLibrary(); // r43p
		}
	}

	// scrollto current song, mobile
	// NOTE see renderUI() and renderPlaylist() for how #currentsong html is populated
	$('#currentsong').click(function() {
        customScroll('pl', parseInt(MPD.json['song']), 200);
	});

	// HUD for screen saver playback controls
	$('#ss-coverart').on('click', '.coverart', function() {
		event.stopImmediatePropagation();
		if ($('#inpsrc-msg').text() == '') { // imput source (renderer) is not active
			if ($('#ss-hud').css('display') == 'none') {
				$('#ss-container-playlist').css('display', 'none')
				$('#ssplbtn i').removeClass('fa-chevron-up');
				$('#ss-hud').fadeIn();
				hudTimer = setTimeout(function() {
					$('#ss-hud').fadeOut();
				}, 5000);
			}
			else {
				clearTimeout(hudTimer);
				$('#ss-hud').fadeOut();
			}
		}
	});
	$('#ss-hud').on('click', function() {
		event.stopImmediatePropagation();
		clearTimeout(hudTimer);
		hudTimer = setTimeout(function() {
			$('#ss-hud').fadeOut();
		}, 5000);
	});
	$('#ssplbtn').click(function(){
		event.stopImmediatePropagation();
		if ($('#ssplbtn i').hasClass('fa-chevron-up')) {
			$('#ssplbtn i').removeClass('fa-chevron-up');
			clearTimeout(hudTimer);
			hudTimer = setTimeout(function() {
				$('#ss-hud').fadeOut();
			}, 5000);
		}
		else {
			$('#ssplbtn i').addClass('fa-chevron-up');
			clearTimeout(hudTimer);
		}
		$('#ss-container-playlist').slideToggle(200, function() {
			customScroll('ss-pl', parseInt(MPD.json['song']), 200);
		});
	});

	// screen saver reset
	$('#screen-saver, #playback-panel, #library-panel, #browse-panel, #radio-panel, #menu-bottom').click(function() {
		//console.log('resetscnsaver: timeout (' + SESSION.json['scnsaver_timeout'] + ')');
		//console.log($(this));

		if ($('#playback-panel').hasClass('hidden')) {
			notify('scnsaverexit','',3000);
		}

		//console.log('resetscnsaver: wait 3 secs');
		// wait a bit to allow other job that may be queued to be processed
		setTimeout(function() {
			//var resp = sendMoodeCmd('GET', 'resetscnsaver'); // sync
			//console.log('resetscnsaver: ' + resp);
			sendMoodeCmd('GET', 'resetscnsaver', '', true); // async
			//console.log('resetscnsaver');
			if ($('#playback-panel').hasClass('hidden')) {
				$('#screen-saver').hide();
				$('#ss-hud').hide();
				$('#playback-panel, #library-panel, #radio-panel').removeClass('hidden');
				$('#menu-bottom, #menu-top').show();
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
