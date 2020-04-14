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
 * 2020-MM-DD TC moOde 6.5.0
 *
 */

jQuery(document).ready(function($) { 'use strict';
	//console.log($(window).width() + 'x' + $(window).height());

    // Initiate loads
    loadLibrary();
    mpdDbCmd('lsinfo', '');
    mpdDbCmd('lsinfo_radio', 'RADIO');

    GLOBAL.scriptSection = 'panels';
	$('#config-back').hide();
	$('#config-tabs').css('display', 'none');
	$('#menu-bottom').css('display', 'flex');

	// compensate for Android popup kbd changing the viewport, also for notch phones
	$("meta[name=viewport]").attr("content", "height=" + $(window).height() + ", width=" + $(window).width() + ", initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover");
	// store device pixel ratio
	var result = sendMoodeCmd('POST', 'updcfgsystem', {'library_pixelratio': window.devicePixelRatio});
	// store scrollbar width (it will be 0 for overlay scrollbars and > 0 for always on scrollbars
	var hiddenDiv = $("<div style='position:absolute; top:-10000px; left:-10000px; width:100px; height:100px; overflow:scroll;'></div>").appendTo("body");
	var sbw = hiddenDiv.width() - hiddenDiv[0].clientWidth;
	$("body").get(0).style.setProperty("--sbw", sbw + 'px');
	//console.log(hiddenDiv.width() - hiddenDiv[0].clientWidth + 'px');

	// load current cfg
	var result = sendMoodeCmd('GET', 'read_cfgs');
	SESSION.json = result['cfg_system'];
	THEME.json = result['cfg_theme'];
	RADIO.json = result['cfg_radio'];

	// load current view
	currentView = SESSION.json['current_view'];

	// radio
	UI.radioPos = parseInt(SESSION.json['radio_pos']);
	// library
	var tmpStr = SESSION.json['lib_pos'].split(',');
	UI.libPos[0] = parseInt(tmpStr[0]); // album list
	UI.libPos[1] = parseInt(tmpStr[1]); // album cover
	UI.libPos[2] = parseInt(tmpStr[2]); // artist list

	// mobile
	UI.mobile = $(window).width() < 480 ? true : false; /* mobile-ish */

    // Set volume knob max
    $('#volume, #volume-2').attr('data-max', SESSION.json['volume_mpd_max']);

	// set the font size
	setFontSize();

	// compile the ignore articles regEx
	if (SESSION.json['library_ignore_articles'] != 'None') {
		GLOBAL.regExIgnoreArticles = new RegExp('^(' + SESSION.json['library_ignore_articles'].split(',').join('|') + ') (.*)', 'gi');
		//console.log (GLOBAL.regExIgnoreArticles);
	}

	// touch device detection
	if (!('ontouchstart' in window || navigator.msMaxTouchPoints)) {
		$('body').addClass('no-touch');
	}

	// set theme
	themeColor = str2hex(THEME.json[SESSION.json['themename']]['tx_color']);
	themeBack = 'rgba(' + THEME.json[SESSION.json['themename']]['bg_color'] + ',' + SESSION.json['alphablend'] +')';
	themeMcolor = str2hex(THEME.json[SESSION.json['themename']]['tx_color']);
	if (SESSION.json['adaptive'] == "No") {document.body.style.setProperty('--adaptmbg', themeBack);}
	blurrr == true ? themeOp = .85 : themeOp = .95;

	// only display transparency related theme options if alphablend is < 1
	$('#alpha-blend').on('DOMSubtreeModified',function(){
		if ($('#alpha-blend span').text() < 1) {
			$('#cover-options').show();
		}
        else {
			$('#cover-options').css('display', '');
		}
	});

	/*themeMback = 'rgba(' + THEME.json[SESSION.json['themename']]['bg_color'] + ',' + themeOp +')';
	tempcolor = splitColor($('.dropdown-menu').css('background-color'));*/
	tempcolor = (THEME.json[SESSION.json['themename']]['mbg_color']).split(",")
	themeMback = 'rgba(' + tempcolor[0] + ',' + tempcolor[1] + ',' + tempcolor[2] + ',' + themeOp + ')';

	accentColor = themeToColors(SESSION.json['accent_color']);
	document.body.style.setProperty('--themetext', themeMcolor);
	var radio1 = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='30' height='30'><circle fill='%23" + accentColor.substr(1) + "' cx='14' cy='14.5' r='11.5'/></svg>";
	var test = getCSSRule('.toggle .toggle-radio');
	test.style.backgroundImage='url("' + radio1 + '")';
	adaptColor = themeColor;
	adaptBack = themeBack;
	adaptMhalf = themeMback;
	adaptMcolor = themeMcolor;
	adaptMback = themeMback;
	tempback = themeMback;
	abFound = false;
	showMenuTopW = false
	showMenuTopR = false
	setColors();

    $('.ralbum').html('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M475.31 364.144L288 256l187.31-108.144c5.74-3.314 7.706-10.653 4.392-16.392l-4-6.928c-3.314-5.74-10.653-7.706-16.392-4.392L272 228.287V12c0-6.627-5.373-12-12-12h-8c-6.627 0-12 5.373-12 12v216.287L52.69 120.144c-5.74-3.314-13.079-1.347-16.392 4.392l-4 6.928c-3.314 5.74-1.347 13.079 4.392 16.392L224 256 36.69 364.144c-5.74 3.314-7.706 10.653-4.392 16.392l4 6.928c3.314 5.74 10.653 7.706 16.392 4.392L240 283.713V500c0 6.627 5.373 12 12 12h8c6.627 0 12-5.373 12-12V283.713l187.31 108.143c5.74 3.314 13.079 1.347 16.392-4.392l4-6.928c3.314-5.74 1.347-13.079-4.392-16.392z"/></svg>');

	// Connect to server engines
    engineMpd();
    engineCmd();

    // NOTE: We may use this in the future
    //UI.clientIP = sendMoodeCmd('GET', 'clientip');
	//console.log(UI.clientIP);

    // setup pines notify
    $.pnotify.defaults.history = false;

	// show button bars
	if (!UI.mobile) {
		$('#playbtns, #togglebtns').show();
	}

	// Screen saver backdrop style
	if (SESSION.json['scnsaver_style'] == 'Animated') {
		$('#ss-style').css('background', '');
		$('#ss-style').css('animation', 'colors2 60s infinite');
		$('#ss-style, #ss-backdrop').css('display', '');
	}
    else if (SESSION.json['scnsaver_style'] == 'Theme') {
		$('#ss-style, #ss-backdrop').css('display', 'none');
	}
	else if (SESSION.json['scnsaver_style'] == 'Gradient (Linear)') {
		$('#ss-style').css('animation', 'initial');
		$('#ss-style').css('background', 'linear-gradient(to bottom, rgba(0,0,0,0.15) 0%,rgba(0,0,0,0.60) 25%,rgba(0,0,0,0.75) 40%, rgba(0,0,0,.8) 60%, rgba(0,0,0,.9) 100%)');
		$('#ss-style, #ss-backdrop').css('display', '');
	}
    else if (SESSION.json['scnsaver_style'] == 'Gradient (Radial)') {
		$('#ss-style').css('animation', 'initial');
		$('#ss-style').css('background', 'radial-gradient(circle at 50%, rgba(128, 128, 128, 1) 5%, rgba(0, 0, 0, 1) 75%)');
		$('#ss-style, #ss-backdrop').css('display', '');
	}
	else if (SESSION.json['scnsaver_style'] == 'Pure Black') {
		$('#ss-style').css('animation', 'initial');
		$('#ss-style').css('background', 'rgba(0,0,0,1)');
		$('#ss-style, #ss-backdrop').css('display', '');
	}

 	// set clock radio icon state
	if (SESSION.json['clkradio_mode'] == 'Clock Radio' || SESSION.json['clkradio_mode'] == 'Sleep Timer') {
		$('#clockradio-icon').removeClass('clockradio-off')
		$('#clockradio-icon').addClass('clockradio-on')
	}
	else {
		$('#clockradio-icon').removeClass('clockradio-on')
		$('#clockradio-icon').addClass('clockradio-off')
	}

	// set volume control state
	if (SESSION.json['mpdmixer'] == 'disabled') {
		disableVolKnob();
		SESSION.json['volknob'] = '0';
		SESSION.json['volmute'] = '0';
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'volknob': '0', 'volmute': '0'});
	}
	else {
		$('#volume').val(SESSION.json['volknob']);
		$('#volume-2').val(SESSION.json['volknob']);
		$('.volume-display').css('opacity', '');
		$('.volume-display div').text(SESSION.json['volknob']);
	}

    // Show or hide Play history item on system menu
    if (SESSION.json['playhist'] == 'Yes') {
        $('#playhistory-hide').css('display', 'block');
    }
    else {
        $('#playhistory-hide').css('display', 'none');
    }

    // Set header text for Tag and Album views
    setTagViewHeaderText();
    setAlbumViewHeaderText();

    // Hide alphabits index if indicated
    if (SESSION.json['library_albumview_sort'] == 'Year') {
        $('#index-albums, #index-albumcovers').hide();
    }

	// Load swipe handler for top columns in library (mobile)
	if (UI.mobile && SESSION.json['library_show_genres'] == 'Yes') {
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

    //
    // ACTIVE VIEW
    //

    // Reset active state
    $('#folder-panel, #radio-panel').removeClass('active');

    // Full screen playback
	if (currentView.indexOf('playback') != -1) {
		$('#playback-panel').addClass('active');
		$(window).scrollTop(0); // make sure it's scrolled to top
		if (UI.mobile) {
			$('#container-playlist').css('visibility','hidden');
			$('#playback-controls').show();
		}
        else {
			setTimeout(function() {
		        customScroll('pl', parseInt(MPD.json['song']));
			}, SCROLLTO_TIMEOUT);
		}
		$('#menu-bottom').hide();
	}
    // Library with Playbar
	else {
		$('#menu-bottom, #viewswitch').css('display', 'flex');
		$('#playback-switch').hide();
	}

    // Radio view
	if (currentView == 'radiolist' || currentView == 'radiocovers') {
		makeActive('.radio-view-btn','#radio-panel',currentView);

		if (currentView == 'radiolist') {
			$('#ra-toggle-view i').removeClass('fa-bars').addClass('fa-th');
			$('#radiocovers').addClass('database-radiolist');
		}
		else {
			$('#ra-toggle-view i').removeClass('fa-th').addClass('fa-bars');
			$('#radiocovers').removeClass('database-radiolist');
		}

		setTimeout(function() {
			if (UI.radioPos >= 0) {
				customScroll('radiocovers', UI.radioPos, 200);
			}
		}, SCROLLTO_TIMEOUT);
	}
    // Folder view
	else if (currentView == 'folder') {
		makeActive('.folder-view-btn','#folder-panel', 'folder');
		mpdDbCmd('lsinfo', '');
	}
    // Tag view
	else if (currentView == 'tag'){
		makeActive('.tag-view-btn','#library-panel', 'tag');
        SESSION.json['library_show_genres'] == 'Yes' ? $('#top-columns').removeClass('nogenre') : $('#top-columns').addClass('nogenre');

        if (!GLOBAL.libRendered) {
			loadLibrary();
		}

		setTimeout(function() {
			if (UI.libPos[0] >= 0) {
			    $('#albumsList .lib-entry').removeClass('active');
				$('#albumsList .lib-entry').eq(UI.libPos[0]).addClass('active');
				customScroll('albums', UI.libPos[0], 200);
				$('#albumsList .lib-entry').eq(UI.libPos[0]).click();
			}
		}, SCROLLTO_TIMEOUT);
	}
	// Album view
	else if (currentView == 'album'){
		makeActive('.album-view-btn','#library-panel', 'album');

        if (!GLOBAL.libRendered) {
			loadLibrary();
		}

		setTimeout(function() {
			if (UI.libPos[1] >= 0) { // lib entry clicked or random album
			    $('#albumcovers .lib-entry').removeClass('active');
				$('#albumcovers .lib-entry').eq(UI.libPos[1]).addClass('active');
				customScroll('albumcovers', UI.libPos[1], 0);
			}
		}, SCROLLTO_TIMEOUT);
	}

	//
	// EVENT HANDLERS
	//

    // Radio view
	$('.radio-view-btn').click(function(e){
        if ($('ra-toggle-view i').hasClass('fa-bars')) {
            makeActive('.radio-view-btn','#radio-panel','radiolist')
        }
        else {
            makeActive('.radio-view-btn','#radio-panel','radiocovers');
            setTimeout(function() {
    			if (UI.radioPos >= 0) {
    				customScroll('radiocovers', UI.radioPos, 0);
    			}
    		}, SCROLLTO_TIMEOUT);
        }
	});
    // Folder view
	$('.folder-view-btn').click(function(e){
		makeActive('.folder-view-btn','#folder-panel','folder');
		mpdDbCmd('lsinfo', '');
	});
    // Tag view
	$('.tag-view-btn').click(function(e){
		makeActive('.tag-view-btn','#library-panel','tag');
        SESSION.json['library_show_genres'] == 'Yes' ? $('#top-columns').removeClass('nogenre') : $('#top-columns').addClass('nogenre');

		if (!GLOBAL.libRendered) {
			loadLibrary();
		}

		setTimeout(function() {
			if (UI.libPos[0] >= 0) { // Lib entry clicked or random album
			    $('#albumsList .lib-entry').removeClass('active');
				$('#albumsList .lib-entry').eq(UI.libPos[0]).addClass('active');
				customScroll('albums', UI.libPos[0], 0);
				$('#albumsList .lib-entry').eq(UI.libPos[0]).click();
			}
			else if (UI.libPos[0] == -2) { // Lib headers clicked
			    $('#albumsList .lib-entry').removeClass('active');
				$('#lib-album').scrollTo(0, 0);
				$('#lib-artist').scrollTo(0, 0);
				UI.libPos[0] = -1;
				storeLibPos(UI.libPos);
			}
			else if (UI.libPos[0] == -3) { // Lib search performed
			    $('#albumsList .lib-entry').removeClass('active');
				$('#lib-album').scrollTo(0, 0);
				$('#lib-coverart-img').html('<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-all">' + '<img class="lib-coverart" ' + 'src="' + UI.defCover + '"></a>');
				$('#lib-albumname, #lib-artistname, #lib-albumyear, #lib-numtracks, #songsList').html('');
				UI.libPos[0] = -1;
				storeLibPos(UI.libPos);
			}
		}, SCROLLTO_TIMEOUT);
	});
    // Album view
	$('.album-view-btn').click(function(e){
		$('#library-panel').addClass('covers').removeClass('tag');
		GLOBAL.lazyCovers = false;
		makeActive('.album-view-btn','#library-panel','album');

		if (!GLOBAL.libRendered) {
			loadLibrary();
		}

		setTimeout(function() {
			if (UI.libPos[1] >= 0) { // Lib entry clicked or random album
			    $('#albumcovers .lib-entry').removeClass('active');
				$('#albumcovers .lib-entry').eq(UI.libPos[1]).addClass('active');
				customScroll('albumcovers', UI.libPos[1], 0);
			}
			else if (UI.libPos[1] == -2 || UI.libPos[1] == -3) { // Lib headers clicked or search performed
			    $('#albumcovers .lib-entry').removeClass('active');
				$('#lib-albumcover').scrollTo(0, 0);
				UI.libPos[1] = -1;
				storeLibPos(UI.libPos);
			}
		}, SCROLLTO_TIMEOUT);
	});

	// mute toggle
	$('.volume-display').click(function(e) {
		if (SESSION.json['mpdmixer'] == 'disabled') {
			return false;
		}
		volMuteSwitch();
	});

	// volume control popup (for mobile and playbar)
	$('.volume-popup').live('click', function(e) {
		if ($('#volume-popup').css('display') == 'block') {
			$('#volume-popup').modal('toggle');
		}
		else {
			//$('.volume-display').css('margin-top', '-16px');
			if (SESSION.json['mpdmixer'] == 'disabled') {
				$('.volume-display').css('opacity', '.3');
			}
			$('#volume-popup').modal();
		}
	});

	// playback button handlers
	$('.play').click(function(e) {
		if (MPD.json['state'] == 'play') {
			$('#playbar-mcount').countdown('pause'); // new
			if (UI.mobile) {
				$('#m-countdown').countdown('pause');
			}
			else {
				$('#countdown-display, #playbar-countdown').countdown('pause');
			}

			if (MPD.json['file'].substr(0, 4).toLowerCase() == 'http') {
				var cmd = MPD.json['artist'] == 'Radio station' ? 'stop' : 'pause'; // pause if for upnp url
			}
			else {
			    var cmd = 'pause'; // song file
			}
		}
		else if (MPD.json['state'] == 'pause') {
			$('#playbar-mcount').countdown('resume'); // new
			if (UI.mobile) {
				$('#m-countdown').countdown('resume');
			}
			else {
				$('#countdown-display, #playbar-countdown, #playbar-mcount').countdown('resume'); // add #playbar-mcount, same for below
				customScroll('pl', parseInt(MPD.json['song']), 200);
			}
			var cmd = 'play';
		}
		else if (MPD.json['state'] == 'stop') {
			if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
				$('#countdown-display').countdown({since: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
				$('#m-countdown, #playbar-countdown, #playbar-mcount').countdown({since: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			}
			else {
				$('#countdown-display').countdown({until: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
				$('#m-countdown, #playbar-countdown, #playbar-mcount').countdown({until: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			}
			if (!UI.mobile) {
		        customScroll('pl', parseInt(MPD.json['song']), 200);
			}
			var cmd = 'play';
		}
		window.clearInterval(UI.knob);
		sendMpdCmd(cmd);
		return false;
	});

	$('.next').click(function(e) {
		var cmd = $(".playlist li").length == (parseInt(MPD.json['song']) + 1).toString() ? 'play 0' : 'next';
		sendMpdCmd(cmd);
		return false;
	});
	$('.prev').click(function(e) {
        if (parseInt(MPD.json['time']) > 0 && parseInt(sendMoodeCmd('GET', 'getmpdstatus')['elapsed']) > 0) {
			window.clearInterval(UI.knob);
			//UI.mobile ? refreshTimer(0, 0, 'stop') : refreshTimer(0, 0, 'stop');
			refreshTimer(0, 0, 'stop')
	        sendMpdCmd('seek ' + MPD.json['song'] + ' 0');
			if (MPD.json['state'] != 'pause') {
				sendMpdCmd('pause');
			}
        }
		else {
			sendMpdCmd('previous');
		}
		return false;
	});
	$('#volumeup,#volumeup-2').click(function(e) {
		SESSION.json['volmute'] == '1' ? volMuteSwitch() : '';
		var curVol = parseInt(SESSION.json['volknob']);
		var newVol = curVol < 100 ? curVol + 1 : 100;
		setVolume(newVol, '');
		return false
	});
	$('#volumedn,#volumedn-2').click(function(e) {
		SESSION.json['volmute'] == '1' ? volMuteSwitch() : '';
		var curVol = parseInt(SESSION.json['volknob']);
		var newVol = curVol > 0 ? curVol - 1 : 0;
		setVolume(newVol, '');
		return false
	});
	$('.btn-toggle').click(function(e) {
		//var id = $(this).attr('id');
		var cmd = $(this).data('cmd');
		var toggle = $(this).hasClass('btn-primary') ? '0' : '1';
		if (cmd == 'random' && SESSION.json['ashufflesvc'] == '1') {
			var result = sendMoodeCmd('GET', 'ashuffle', {'ashuffle':toggle}); // toggle ashuffle on/off
		    $('.random').toggleClass('btn-primary');
		    $('.consume').toggleClass('btn-primary');
			sendMpdCmd('consume ' + toggle);
		}
		else {
			if (cmd == 'consume') {
				$('#menu-check-consume').toggle();
			}
		    $('.' + cmd).toggleClass('btn-primary');
			sendMpdCmd(cmd + ' ' + toggle);
		}
	});

    // countdown time knob
    $('.playbackknob').knob({
		configure: {'fgColor':accentColor},
        inline: false,
		change : function(value) {
            if (MPD.json['state'] != 'stop') {
				window.clearInterval(UI.knob)
				// update time display when changing slider
				var seekto = Math.floor((value * parseInt(MPD.json['time'])) / 1000);
				if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
					$('#countdown-display').html(formatSongTime(seekto)); // count up
				}
                else {
					$('#countdown-display').html(formatSongTime(parseInt(MPD.json['time']) - seekto)); // count down
				}
			}
			else {
				$('#time').val(0);
			}

			// repaint not needed
			UI.knobPainted = false;
        },
        release : function(value) {
			if (MPD.json['state'] != 'stop') {
				window.clearInterval(UI.knob);
				var seekto = Math.floor((value * parseInt(MPD.json['time'])) / 1000);
				sendMpdCmd('seek ' + MPD.json['song'] + ' ' + seekto);
				if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
					$('#countdown-display').countdown({since: -seekto, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
				}
				else {
					$('#countdown-display').countdown({until: seekto, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
				}
			}

			UI.knobPainted = false;
        }
		/*
        cancel : function() {},
        draw : function() {}
		*/
    });

    // Volume control knob
    $('.volumeknob').knob({
		configure: {'fgColor':accentColor},
        change : function(value) {
			value = value > 100 ? 100 : value;
            setVolume(value, 'change');
        }
		/*
        release : function() {}
        cancel : function() {},
        draw : function() {}
		*/
    });

	if ($('#playback-panel').hasClass('newui')) {
		$('.playbackknob, .volumeknob').trigger('configure',{"thickness":'.09'});
	}

	// toggle count up/down and direction icon, radio always counts up
	$('#countdown-display, #m-countdown').click(function(e) {
		if (MPD.json['artist'] != 'Radio station') {
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
		}
    });

    // Click on playlist entry
    $('.playlist, .cv-playlist').on('click', '.pl-entry', function(e) {
        if (GLOBAL.plActionClicked) {
            GLOBAL.plActionClicked = false;
            return;
        }

        var selector = $(this).parent().hasClass('playlist') ? '.playlist' : '.cv-playlist';
        var pos = $(selector + ' .pl-entry').index(this);

        sendMpdCmd('play ' + pos);
        $(this).parent().addClass('active');

		/*if (UI.mobile) { // for mobile scroll to top
			$('html, body').animate({ scrollTop: 0 }, 'fast');
		}*/
    });

	// Click on playlist action menu button
    $('.playlist').on('click', '.pl-action', function(e) {
        GLOBAL.plActionClicked = true;

		// store posn for later use by action menu selection
        UI.dbEntry[0] = $('.playlist .pl-action').index(this);
		// store clock radio play name in UI.dbEntry[3]
		if ($('#pl-' + (UI.dbEntry[0] + 1) + ' .pll2').html().substr(0, 2) == '<i') { // has icon (fa-microphone)
			// radio station
			var line2 = $('#pl-' + (UI.dbEntry[0] + 1) + ' .pll2').html();
			var station = line2.substr((line2.indexOf('</i>') + 4));
			UI.dbEntry[3] = station.trim();
		}
		else {
			// song file
			var title = $('#pl-' + (UI.dbEntry[0] + 1) + ' .pll1').html().trim();
			var line2 = $('#pl-' + (UI.dbEntry[0] + 1) + ' .pll2').text(); // artist - album
			var artist = line2.substr(0, (line2.indexOf('-') - 1)); // strip off album
			UI.dbEntry[3] = title + ', ' + artist;
		}
    });

	// save playlist
    $('#pl-btnSave').click(function(e){
		var plname = $('#pl-saveName').val();

		if (plname) {
			if (~plname.indexOf('NAS') || ~plname.indexOf('RADIO') || ~plname.indexOf('SDCARD')) {
				notify('plnameerror', '');
			}
			else {
				var result = sendMoodeCmd('GET', 'savepl&plname=' + plname, '', true);
				notify('savepl', '');
			}
		}
		else {
			notify('needplname', '');
		}
    });
    $('#savepl-modal').on('shown.bs.modal', function(e) {
        $('#pl-saveName').focus();
    });
	// set favorites
    $('#pl-btnSetFav').click(function(e){
		var favname = $('#pl-favName').val();

		if (favname) {
			if (~favname.indexOf('NAS') || ~favname.indexOf('RADIO') || ~favname.indexOf('SDCARD')) {
				notify('plnameerror', '');
			}
			else {
				var result = sendMoodeCmd('GET', 'setfav&favname=' + favname, '', true);
				notify('favset', '');
			}
		}
		else {
			notify('needplname', '');
		}
    });
    $('#setfav-modal').on('shown.bs.modal', function() {
        $('#pl-favName').focus();
    });
	// add item to favorites
    $('.addfav').click(function(e){
		// pulse the btn
		$('.addfav i').addClass('pulse');
		$('.addfav i').addClass('fas');
		setTimeout(function() {
			$('.addfav i').removeClass('fas');
			$('.addfav i').removeClass('pulse');
		}, 1000);

		// add current pl item to favorites playlist
		if (MPD.json['file'] != null) {
			var result = sendMoodeCmd('GET', 'addfav&favitem=' + encodeURIComponent(MPD.json['file']), '', true);
			notify('favadded', '');
		}
		else {
			notify('nofavtoadd', '');
		}
    });

	// click on artist name in lib meta area
	$('#lib-artistname').click(function(e) {
		$('#artistsList .lib-entry').filter(function() {return $(this).text() == $('#lib-artistname').text()}).click();
		customScroll('artists', UI.libPos[2], 200);
	});

    // Click on title in playback or cv
    $('#playback-panel').click(function(e) {
        if ($('#playback-panel').hasClass('cv')) {
            e.preventDefault();
        }
	});

	// Click on artist or station name in playback
	$('#currentalbum').click(function(e) {
        if (!$('#playback-panel').hasClass('cv')) {
            // Radio station
    		if (MPD.json['artist'] == 'Radio station') {
    			$('.database-radio li').each(function(index){
    				if ($(this).text().search(RADIO.json[MPD.json['file']]['name']) != -1) {
    					UI.radioPos = index + 1;
    				}
    			});
                currentView = 'playback,radiocovers';
    			$('#playback-switch').click();
    			if (!$('.radio-view-btn').hasClass('active')) {
    				$('.radio-view-btn').click();
    			}
    		}
    		// Song file
    		else {
    			$('#playback-switch').click();
    			$('.tag-view-btn').click();
    			setTimeout(function() {
    				$('#artistsList .lib-entry').filter(function() {return $(this).text() == MPD.json['artist'];}).click();
    				customScroll('artists', UI.libPos[2], 200);
    			}, 300);
    		}
        }
	});

	// browse panel
	$('.database').on('click', '.db-browse', function(e) {
	    if ($(this).hasClass('db-folder') || $(this).hasClass('db-savedplaylist')) {
			var cmd = $(this).hasClass('db-folder') ? 'lsinfo' : 'listsavedpl';
			UI.dbPos[UI.dbPos[10]] = $(this).parent().attr('id').replace('db-','');
			++UI.dbPos[10];
			mpdDbCmd(cmd, $(this).parent().data('path'));
		}
	});
	$('#db-back').click(function(e) {
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
	$('#db-home').click(function(e) {
		$('#db-search-results').hide();
		$('#dbfs').val('');
		UI.dbPos.fill(0);
		UI.path = '';
		mpdDbCmd('lsinfo', '');
	});
	$('#db-refresh').click(function(e) {
		UI.dbCmd = UI.dbCmd != 'lsinfo' && UI.dbCmd != 'listsavedpl' ? 'lsinfo' : UI.dbCmd;
		if (UI.dbCmd == 'lsinfo' || UI.dbCmd == 'listsavedpl') {
			mpdDbCmd(UI.dbCmd, UI.path);
		}
	});
	$('#db-search-submit').click(function(e) {
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
			searchStr += $('#dbsearch-comment').val() == '' ? '' : ' comment "' + $('#dbsearch-comment').val() + '"'
			searchStr += $('#dbsearch-file').val() == '' ? '' : ' file "' + $('#dbsearch-file').val() + '"'
			if (searchStr != '') {
				$.post('command/moode.php?cmd=search' + '&tagname=specific', {'query': searchStr}, function(data) {renderBrowse(data, '', searchStr);}, 'json');
			}
		}
	});
	$('#db-search-reset').click(function(e) {
		$('#dbsearch-alltags, #dbsearch-genre, #dbsearch-artist, #dbsearch-album, #dbsearch-title, #dbsearch-albumartist, #dbsearch-date, #dbsearch-composer, #dbsearch-performer, #dbsearch-comment, #dbsearch-file').val('');
		$('#dbsearch-alltags').focus();
	});
	$('#dbsearch-modal').on('shown.bs.modal', function() {
		$('#db-search-results').css('font-weight', 'normal');
		$('.database li').removeClass('active');
		$('#dbsearch-alltags').focus();
	});
	$('#db-search-results').click(function(e) {
		$('.database li').removeClass('active');
		$('#db-search-results').css('font-weight', 'bold');
		dbFilterResults = [];
		$('.database li').each(function() {
			dbFilterResults.push($(this).attr('data-path'));
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
	// Radio panel sub folders
	$('.database-radio').on('click', '.db-browse', function(e) {
		if ($(this).hasClass('db-radiofolder') || $(this).hasClass('db-radiofolder-icon')) {
			e.stopImmediatePropagation();
			UI.raFolderLevel[UI.raFolderLevel[4]] = $(this).parent().attr('id').replace('db-','');
			++UI.raFolderLevel[4];
			mpdDbCmd('lsinfo_radio', $(this).parent().data('path'));
            lazyLode('radio');
            setTimeout(function() {
                $('#database-radio').scrollTo(0, 200);
            }, SCROLLTO_TIMEOUT);
		}
	});
    // Radio folder back button
    $('#ra-back').click(function(e) {
        if (UI.pathr != 'RADIO') {
			var pathr = UI.pathr;
			var cutpos = pathr.lastIndexOf('/');
			if (cutpos != -1) {
				var pathr = pathr.slice(0,cutpos);
			}
			else {
				pathr = '';
			}
            mpdDbCmd('lsinfo_radio', pathr);
            lazyLode('radio');
            setTimeout(function() {
                $('#database-radio').scrollTo(0, 200);
            }, SCROLLTO_TIMEOUT);
		}
	});
	$('#ra-home').click(function(e) {
		UI.raFolderLevel[4] = 0;
		UI.pathr = '';
		mpdDbCmd('lsinfo_radio', 'RADIO');
        lazyLode('radio');
        setTimeout(function() {
            $('#database-radio').scrollTo(0, 200);
        }, SCROLLTO_TIMEOUT);

		UI.radioPos = -1;
		storeRadioPos(UI.radioPos)
        $('#ra-filter-results').hide();
        $("#searchResetRa").hide();
        showSearchResetRa = false;
	});
	// refresh panel
	$('#ra-refresh').click(function(e) {
		mpdDbCmd('lsinfo_radio', UI.pathr);
        lazyLode('radio');
        setTimeout(function() {
            $('#database-radio').scrollTo(0, 200);
        }, SCROLLTO_TIMEOUT);

		UI.radioPos = -1;
		storeRadioPos(UI.radioPos)
        $('#ra-filter-results').hide();
        $("#searchResetRa").hide();
        showSearchResetRa = false;
	});
	// create new station
	$('#ra-new').click(function(e) {
		$('#new-station-name').val('New station');
		$('#new-station-url').val('http://');
        $('#new-logoimage').val('');
		$('#preview-new-logoimage').html('');
        $('#info-toggle-new-logoimage').css('margin-left','unset');
		$('#newstation-modal').modal();
	});
    $('#newstation-modal').on('shown.bs.modal', function() {
        $('#new-station-name').focus();
    });
	// radio search
	$('#ra-filter').keyup(function(e){
		if (!showSearchResetRa) {
            $('#ra-filter-results').show();
			$('#searchResetRa').show();
			showSearchResetRa = true;
		}

		clearTimeout(searchTimer);

		var selector = this;
		searchTimer = setTimeout(function(){
			var filter = $(selector).val()
			var count = 0;

			if (filter == '') {
				$("#searchResetRa").hide();
				showSearchResetRa = false;
			}

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
            lazyLode('radio');
            $('#database-radio').scrollTo(0, 200);

		}, RASEARCH_TIMEOUT);
	});
	$('#searchResetRa').click(function(e) {
		$('.database-radio li').css('display', 'inline-block');
		$('#ra-filter-results').html('');
        $("#searchResetRa").hide();
		showSearchResetRa = false;
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

			if (filter == '') {
				$("#searchResetPl").hide();
				showSearchResetPl = false;
			}

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
		}, PLSEARCH_TIMEOUT);
	});
	$('#searchResetPl').click(function(e) {
		$("#searchResetPl").hide();
		showSearchResetPl = false;
		$('.playlist li').css('display', 'block');
		$('#pl-filter-results').html('');
	});

    // library search
    // NOTE: This performs typedown search and a special year or year range search
	$('#lib-album-filter').keyup(function(e){
		e.preventDefault();

		if (!showSearchResetLib) {
			$('#searchResetLib').show();
			showSearchResetLib = true;
		}

		clearTimeout(searchTimer);

		var selector = this;
		searchTimer = setTimeout(function(){
			var filter = $(selector).val()
			var count = 0;

			if (filter == '') {
				$('#searchResetLib').hide();
				showSearchResetLib = false;
			}

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
				$('#menu-header').text((+count) + ' albums found');
				GLOBAL.searchLib = $('#menu-header').text(); // Save for #menu-header
			}
			else {
				$('#lib-album-filter-results').html('');
			}
			if ($('.tag-view-btn').hasClass('active')) {
				lazyLode('tag');
			}
			else {
				lazyLode('album');
				$('#bottom-row').css('display', '');
			}

		    $('#albumcovers .lib-entry').removeClass('active');
		    $('#albumsList .lib-entry').removeClass('active');
			$('#lib-albumcover').css('height', '100%');
			UI.libPos.fill(-3);
		}, LIBSEARCH_TIMEOUT);

        var filter = $(this).val();
		if (e.key == 'Enter' || filter.slice(filter.length - 2) == '!r') {
            if (filter.slice(filter.length - 2) == '!r') {
                filter = filter.slice(0, filter.length - 2);
            }

            LIB.filters.year = filter.split('-'); // [year 1][year 2 if present]
            if (!(parseInt(LIB.filters.year[0]) || parseInt(LIB.filters.year[1]))) {
                // For now do nothing for non integer (year) input
                return false;
            }

		    LIB.recentlyAddedClicked = false;
			LIB.filters.albums.length = 0;
			$('#menu-header').text('Albums from ' + LIB.filters.year[0] + (LIB.filters.year[1] ? ' to ' + LIB.filters.year[1] : ''));
			GLOBAL.searchLib = $('#menu-header').text(); // Save for #menu-header
			$('#viewswitch span').hide();
			UI.libPos.fill(-2);
			filterLib();
		    renderAlbums();
			$('#lib-album-filter').blur();
			$('#viewswitch').click();
			if (currentView == 'tag' && SESSION.json['tag_view_covers'] == 'Yes') {
				lazyLode('tag');
			}
			else if (currentView == 'album') {
				lazyLode('album');
			}
			$('#lib-album-filter').blur();
			$('#viewswitch').click();
		}
	});

	$('#searchResetLib').click(function(e) {
		e.preventDefault();
		GLOBAL.searchLib = '';
		setLibMenuHeader();
		LIB.filters.albums.length = 0;
		UI.libPos.fill(-2);
		storeLibPos(UI.libPos);
	    clickedLibItem(undefined, undefined, LIB.filters.albums, renderAlbums);
		$("#searchResetLib").hide();
		showSearchResetLib = false;
		document.getElementById("lib-album-filter").focus();
		return false;
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
		}, PHSEARCH_TIMEOUT);
	});
	$('#searchResetPh').click(function(e) {
		$("#searchResetPh").hide();
		showSearchResetPh = false;
		$('.playhistory li').css('display', 'list-item');
		$('#ph-filter-results').html('');
	});

	// browse, radio action menus
	$('.database, .database-radio').on('click', '.db-action', function(e) {
		UI.dbEntry[0] = $(this).parent().attr('data-path');
		UI.dbEntry[3] = $(this).parent().attr('id'); // used in .context-menu a click handler to remove highlight
		$('#db-search-results').css('font-weight', 'normal');
		$('.database li, .database-radio li').removeClass('active');
		$(this).parent().addClass('active');
		// set new pos
		UI.radioPos = parseInt(UI.dbEntry[3].substr(3));
		storeRadioPos(UI.radioPos)
	});

	// remove highlight from station logo
	$('.btnlist-top-ra').click(function(e) {
		//console.log('click .btnlist-top-ra')
		if (UI.dbEntry[3].substr(0, 3) == 'db-') {
			$('#' + UI.dbEntry[3]).removeClass('active');
		}
	});

	// buttons on modals
	$('.btn-del-savedpl').click(function(e){
		mpdDbCmd('delsavedpl', UI.dbEntry[0]);
		notify('delsavedpl', '');
	});
	$('.btn-new-station').click(function(e){
		if ($('#new-station-name').val().trim() == '' || $('#new-station-url').val().trim() == '') {
			notify('blankentries', 'Station not created');
		}
		else {
			mpdDbCmd('newstation', $('#new-station-name').val() + '\n' + $('#new-station-url').val() + '\n');
			notify('newstation', '');
		}
	});
	$('.btn-upd-station').click(function(e){
		if ($('#edit-station-name').val().trim() == '' || $('#edit-station-url').val().trim() == '') {
			notify('blankentries', 'Station not updated');
		}
		else {
			mpdDbCmd('updstation', $('#edit-station-name').val() + '\n' + $('#edit-station-url').val() + '\n');
			notify('updstation', '');
		}
	});
	$('.btn-del-station').click(function(e){
		mpdDbCmd('delstation', UI.dbEntry[0]);
		notify('delstation', '');
	});
	$('.btn-delete-plitem').click(function(e){
		var cmd = '';
		var begpos = $('#delete-plitem-begpos').val() - 1;
		var endpos = $('#delete-plitem-endpos').val() - 1;
		// NOTE: format for single or multiple, endpos not inclusive so must be bumped for multiple
		begpos == endpos ? cmd = 'delplitem&range=' + begpos : cmd = 'delplitem&range=' + begpos + ':' + (endpos + 1);
		notify('remove', '');
		var result = sendMoodeCmd('GET', cmd, '', true);
	});
	// speed btns on delete modal
	$('#btn-delete-setpos-top').click(function(e){
		$('#delete-plitem-begpos').val(1);
		return false;
	});
	$('#btn-delete-setpos-bot').click(function(e){
		$('#delete-plitem-endpos').val(UI.dbEntry[4]);
		return false;
	});
	$('.btn-move-plitem').click(function(e){
		var cmd = '';
		var begpos = $('#move-plitem-begpos').val() - 1;
		var endpos = $('#move-plitem-endpos').val() - 1;
		var newpos = $('#move-plitem-newpos').val() - 1;
		// NOTE: format for single or multiple, endpos not inclusive so must be bumped for multiple
		// move begpos newpos or move begpos:endpos newpos
		begpos == endpos ? cmd = 'moveplitem&range=' + begpos + '&newpos=' + newpos : cmd = 'moveplitem&range=' + begpos + ':' + (endpos + 1) + '&newpos=' + newpos;
		notify('move', '');
		var result = sendMoodeCmd('GET', cmd, '', true);
	});
	// speed btns on move modal
	$('#btn-move-setpos-top').click(function(e){
		$('#move-plitem-begpos').val(1);
		return false;
	});
	$('#btn-move-setpos-bot').click(function(e){
		$('#move-plitem-endpos').val(UI.dbEntry[4]);
		return false;
	});
	$('#btn-move-setnewpos-top').click(function(e){
		$('#move-plitem-newpos').val(1);
		return false;
	});
	$('#btn-move-setnewpos-bot').click(function(e){
		$('#move-plitem-newpos').val(UI.dbEntry[4]);
		return false;
	});

	// speed buttons on plaback history log
	$('.ph-firstPage').click(function(e){
		$('#container-playhistory').scrollTo(0 , 200);
	});
	$('.ph-lastPage').click(function(e){
		$('#container-playhistory').scrollTo('100%', 200);
	});

	// playback panel context menu
	$('.playback-context-menu').click(function(e) {
		var color = $('.consume').hasClass('btn-primary') ? 'var(--accentxts)' : 'inherit';
		$('#consume-menu-icon').css('color', color);
	});

	// playbar coverview btn
	$('.coverview').on('click', function(e) {
		e.stopImmediatePropagation();
		screenSaver('1');
	});

    // Coverview playlist
	$('#cv-playlist-btn').on('click', function(e) {
        $('#cv-playlist').toggle();

        if ($('#cv-playlist').css('display') == 'block') {
            setTimeout(function() {
                customScroll('pbpl', parseInt(MPD.json['song']));
            }, SCROLLTO_TIMEOUT);

            GLOBAL.playbarPlaylistTimer = setTimeout(function() {
                $('#cv-playlist').hide();
            }, 20000);
        }
        else {
            e.preventDefault();
            window.clearTimeout(GLOBAL.playbarPlaylistTimer);
        }
	});

	// disconnect active renderer
	$('.disconnect-renderer').live('click', function(e) {
		$('.disconnect-renderer').css('opacity', '.5');
		var job = $(this).data('job');
		var result = sendMoodeCmd('POST', 'disconnect-renderer', {'job':job}, true);
	});

    // Screen saver (CoverView) reset (r642 variant)
    $('#screen-saver, #playback-panel, #library-panel, #folder-panel, #radio-panel, #menu-bottom').click(function(e) {
        //console.log('resetscnsaver: timeout (' + SESSION.json['scnsaver_timeout'] + ', currentView: ' + currentView + ')');
        if ($(this).attr('id') == 'menu-bottom') {
            return;
        }

        if (coverView || SESSION.json['scnsaver_timeout'] != 'Never') {
			$('body').removeClass('cv');
			coverView = false;
            setColors();

            /*TEST*/$('#cv-playlist').hide();
            /*TEST*/$('#lib-coverart-img').show();
            /*TEST*/$('#playback-queue').css('width', '38.1%'); // Fix Playlist sometimes not being visable after returning from cv
            setTimeout(function() {
                /*TEST*/$('#playback-queue').css('width', '38%'); // Restore correct width
                customScroll('pl', parseInt(MPD.json['song']));
            }, SCROLLTO_TIMEOUT);

            // Reset screen saver timeout global
            setTimeout(function() { // wait a bit to allow other job that may be queued to be processed
                var result = sendMoodeCmd('GET', 'resetscnsaver', '', true);
            }, 3000);
        }
    });

	// Info button (i) show/hide toggle
	$('.info-toggle').click(function(e) {
		var spanId = '#' + $(this).data('cmd');
		if ($(spanId).hasClass('hide')) {
			$(spanId).removeClass('hide');
		}
		else {
			$(spanId).addClass('hide');
		}
	});
});
