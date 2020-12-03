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
jQuery(document).ready(function($) { 'use strict';
    // Call $.pnotify if created by backend
    if( window.ui_notify != undefined ) {
        ui_notify();
	}

    GLOBAL.scriptSection = 'panels';
	$('#config-back').hide();
	$('#config-tabs').css('display', 'none');
	$('#menu-bottom').css('display', 'flex');

    // NOTE: This is a workaround for the time knob progress slider not updating correctly when the window is hidden
    document.addEventListener("visibilitychange", visChange);
    function visChange() {
        //console.log('visChange()', document.visibilityState);
        if (document.visibilityState == 'visible') {
            // This will cause MPD idle timeout and subsequent renderUI() which will refresh the time eknob using current data
    		sendMpdCmd('subscribe dumy_channel');
        }
    }

    // Resize thumbs on window resize
	$(window).bind('resize', function(e){
		UI.mobile = $(window).width() <= 480 ? true : false;
		getThumbHW();
	});

	// Compensate for Android popup kbd changing the viewport, also for notch phones
	$("meta[name=viewport]").attr("content", "height=" + $(window).height() + ", width=" + $(window).width() + ", initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover");
	// Store device pixel ratio
    $.post('command/moode.php?cmd=updcfgsystem', {'library_pixelratio': window.devicePixelRatio});

	// Store scrollbar width (it will be 0 for overlay scrollbars and > 0 for always on scrollbars
	var hiddenDiv = $("<div style='position:absolute; top:-10000px; left:-10000px; width:100px; height:100px; overflow:scroll;'></div>").appendTo("body");
	GLOBAL.sbw = hiddenDiv.width() - hiddenDiv[0].clientWidth;
	$("body").get(0).style.setProperty("--sbw", GLOBAL.sbw + 'px');
    //console.log(hiddenDiv.width() - hiddenDiv[0].clientWidth + 'px');

    // Enable custom scroll bars unless overlay scroll bars are enabled on the platform (scroll bar width sbw = 0)
    if (GLOBAL.sbw) {
        $('body').addClass('custom-scrollbars');
    }

    // Check for native lazy load support in Browser
    // @bitkeeper contribution: https://github.com/moode-player/moode/pull/131
    if ('loading' in HTMLImageElement.prototype) {
        GLOBAL.nativeLazyLoad = true;
    }

	// Load current cfg
    $.getJSON('command/moode.php?cmd=read_cfgs', function(result) {
    	SESSION.json = result['cfg_system'];
    	THEME.json = result['cfg_theme'];
    	RADIO.json = result['cfg_radio'];

        // Display viewport size for debugging by re-using the pkgid_suffix col. It's normaly used to test in-place update packages.
        if (SESSION.json['pkgid_suffix'] == 'viewport') {
            notify('viewport', window.innerWidth + 'x' + window.innerHeight, 10000);
        }

    	// Set currentView global
    	currentView = SESSION.json['current_view'];

    	// Detect mobile
    	UI.mobile = $(window).width() < 480 ? true : false;
        //console.log('window: ' + $(window).width() + 'x' + $(window).height());
        //console.log('viewport: ' + window.innerWidth + 'x' + window.innerHeight);

        // Set thumbnail columns
        getThumbHW();

        // Initiate loads
        loadLibrary();
        mpdDbCmd('lsinfo', '');
        mpdDbCmd('lsinfo_radio');

    	// Radio
    	UI.radioPos = parseInt(SESSION.json['radio_pos']);
    	// library
    	var tmpStr = SESSION.json['lib_pos'].split(',');
    	UI.libPos[0] = parseInt(tmpStr[0]); // album list
    	UI.libPos[1] = parseInt(tmpStr[1]); // album cover
    	UI.libPos[2] = parseInt(tmpStr[2]); // artist list

        // Set volume knob max
        $('#volume, #volume-2').attr('data-max', SESSION.json['volume_mpd_max']);
        GLOBAL.mpdMaxVolume = parseInt(SESSION.json['volume_mpd_max']);

    	// Set font size
    	setFontSize();

        // Show/hide first use help
        var firstUseHelp = SESSION.json['first_use_help'].split(',');
        if (firstUseHelp[0] == 'y') {$('#playback-firstuse-help').css('display', 'block');}
        if (firstUseHelp[1] == 'y') {$('#playbar-firstuse-help').css('display', 'block');}

    	// Compile the ignore articles regEx
    	if (SESSION.json['library_ignore_articles'] != 'None') {
    		GLOBAL.regExIgnoreArticles = new RegExp('^(' + SESSION.json['library_ignore_articles'].split(',').join('|') + ') (.*)', 'gi');
    		//console.log (GLOBAL.regExIgnoreArticles);
    	}

    	// Detect touch device
    	if (!('ontouchstart' in window || navigator.msMaxTouchPoints)) {
    		$('body').addClass('no-touch');
    	}

    	// Set theme
    	themeColor = str2hex(THEME.json[SESSION.json['themename']]['tx_color']);
    	themeBack = 'rgba(' + THEME.json[SESSION.json['themename']]['bg_color'] + ',' + SESSION.json['alphablend'] +')';
    	themeMcolor = str2hex(THEME.json[SESSION.json['themename']]['tx_color']);
    	if (SESSION.json['adaptive'] == "No") {document.body.style.setProperty('--adaptmbg', themeBack);}
    	blurrr == true ? themeOp = .85 : themeOp = .95;


        function mutate(mutations) {
            mutations.forEach(function(mutation) {
                $('#alpha-blend span').text() < 1 ? $('#cover-options').show() : $('#cover-options').css('display', '');
            });
        }

		jQuery(document).ready(function() {
            var target = document.querySelector('#alpha-blend span')
            var observer = new MutationObserver( mutate );
            var config = { characterData: true, attributes: false, childList: true, subtree: false };
            observer.observe(target, config);
		});


    	// Only display transparency related theme options if alphablend is < 1
    	/*$('#alpha-blend').on('DOMSubtreeModified',function(){
    		if ($('#alpha-blend span').text() < 1) {
    			$('#cover-options').show();
    		}
            else {
    			$('#cover-options').css('display', '');
    		}
    	});*/

    	tempcolor = (THEME.json[SESSION.json['themename']]['mbg_color']).split(",")
    	themeMback = 'rgba(' + tempcolor[0] + ',' + tempcolor[1] + ',' + tempcolor[2] + ',' + themeOp + ')';
    	accentColor = themeToColors(SESSION.json['accent_color']);
    	document.body.style.setProperty('--themetext', themeMcolor);
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

        /*
        // NOTE: We may use this in the future
        $.getJSON('command/moode.php?cmd=clientip', function(result) {
            UI.clientIP = result;
            console.log(UI.clientIP);
        });
        */

        // Setup pines notify
        $.pnotify.defaults.history = false;

    	// Show button bars
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
    		$('#ss-style').css('background', 'radial-gradient(circle at 50% center, rgba(64, 64, 64, .5) 5%, rgba(0, 0, 0, .85) 60%)');
    		$('#ss-style, #ss-backdrop').css('display', '');
    	}
    	else if (SESSION.json['scnsaver_style'] == 'Pure Black') {
    		$('#ss-style').css('animation', 'initial');
    		$('#ss-style').css('background', 'rgba(0,0,0,1)');
    		$('#ss-style, #ss-backdrop').css('display', '');
    	}

     	// Set clock radio icon state
    	if (SESSION.json['clkradio_mode'] == 'Clock Radio' || SESSION.json['clkradio_mode'] == 'Sleep Timer') {
    		$('#clockradio-icon').removeClass('clockradio-off')
    		$('#clockradio-icon').addClass('clockradio-on')
    	}
    	else {
    		$('#clockradio-icon').removeClass('clockradio-on')
    		$('#clockradio-icon').addClass('clockradio-off')
    	}

    	// Set volume control state
    	if (SESSION.json['mpdmixer'] == 'disabled') {
    		disableVolKnob();
    		SESSION.json['volknob'] = '0';
    		SESSION.json['volmute'] = '0';
            $.post('command/moode.php?cmd=updcfgsystem', {'volknob': '0', 'volmute': '0'});
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

        // Tag view header text
        $('#tagview-header-text').text('Albums' +
            ((SESSION.json['library_tagview_sort'] == 'Album' || SESSION.json['library_tagview_sort'] == 'Album/Year') ?
            '' : ' by ' + SESSION.json['library_tagview_sort']));
        // Artists column header
        $('#artistheader > div').html(SESSION.json['library_tagview_artist'] == 'Album Artist +' ? 'Album Artists +' :
            SESSION.json['library_tagview_artist'] + 's');
        // Hide alphabits index if indicated
        if (SESSION.json['library_albumview_sort'] == 'Year') {
            $('#index-albums, #index-albumcovers').hide();
        }

        // Stream Recorder
        if (SESSION.json['feat_bitmask'] & FEAT_RECORDER) {
            $('#radio-manager-stream-recorder').show();
            if (SESSION.json['recorder_status'] != 'Not installed') {
                $('#stream-recorder-options').show();
                $('#context-menu-stream-recorder').show();
                if (SESSION.json['recorder_status'] == 'On') {
                    $('.playback-context-menu i').addClass('recorder-on');
                    $('#menu-check-recorder').css('display', 'inline');
                }
                else {
                    $('.playback-context-menu i').removeClass('recorder-on');
                    $('#menu-check-recorder').css('display', 'none');
                }
            }
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

        // Playback
    	if (currentView.indexOf('playback') != -1) {
    		$('#playback-panel').addClass('active');
    		$(window).scrollTop(0); // make sure it's scrolled to top
    		if (UI.mobile) {
    			$('#container-playlist').css('visibility','hidden');
    			$('#playback-controls').show();
    		}
            else {
		        customScroll('playlist', parseInt(MPD.json['song']));
    		}
    		$('#menu-bottom').hide();
    	}
        // Library
    	else {
    		$('#menu-bottom, #viewswitch').css('display', 'flex');
    		$('#playback-switch').hide();
    	}

        // Radio view
    	if (currentView == 'radio') {
    		makeActive('.radio-view-btn','#radio-panel', currentView);
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
    	}
    	// Album view
    	else if (currentView == 'album'){
    		makeActive('.album-view-btn','#library-panel', 'album');
    	}
    });

	//
	// EVENT HANDLERS
	//

    // Radio view
	$('.radio-view-btn').click(function(e){
        makeActive('.radio-view-btn','#radio-panel','radio');
	});
    // Folder view
	$('.folder-view-btn').click(function(e){
		makeActive('.folder-view-btn','#folder-panel','folder');
	});
    // Tag view
	$('.tag-view-btn').click(function(e){
		makeActive('.tag-view-btn','#library-panel','tag');
        SESSION.json['library_show_genres'] == 'Yes' ? $('#top-columns').removeClass('nogenre') : $('#top-columns').addClass('nogenre');
	    $('#albumsList .lib-entry').removeClass('active');

		if (UI.libPos[0] == -2) { // Lib headers clicked
			$('#lib-album').scrollTo(0, 0);
			$('#lib-artist').scrollTo(0, 0);
			UI.libPos[0] = -1;
			storeLibPos(UI.libPos);
		}
		else if (UI.libPos[0] == -3) { // Lib search performed
		    $('#albumsList .lib-entry').removeClass('active');
			$('#lib-album').scrollTo(0, 0);
			$('#lib-coverart-img').html('<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-album">' + '<img class="lib-coverart" ' + 'src="' + UI.defCover + '"></a>');
			$('#lib-albumname, #lib-artistname, #lib-albumyear, #lib-numtracks, #songsList').html('');
			UI.libPos[0] = -1;
			storeLibPos(UI.libPos);
		}
	});
    // Album view
	$('.album-view-btn').click(function(e){
		$('#library-panel').addClass('covers').removeClass('tag');
		GLOBAL.lazyCovers = false;
		makeActive('.album-view-btn','#library-panel','album');

		if (!GLOBAL.libRendered) {
			loadLibrary();
		}
	    $('#albumcovers .lib-entry').removeClass('active');
		if (UI.libPos[1] == -2 || UI.libPos[1] == -3) { // Lib headers clicked or search performed
			$('#lib-albumcover').scrollTo(0, 0);
			UI.libPos[1] = -1;
			storeLibPos(UI.libPos);
		}
	});


    // Clear all the libcache files
    // NOTE: This is a dev option for convenience. Unhide it in indextpl.html.
	$('.btn-clear-libcache').on('click', function(e) {
        $.get('command/moode.php?cmd=clear_libcache_all');
        notify('clear_libcache', 'Auto-refresh in 2 seconds');
        setTimeout(function() {
            location.reload(true);
        }, 2000);
	});

	// Mute toggle
	$('.volume-display').on('click', function(e) {
		if (SESSION.json['mpdmixer'] == 'disabled') {
			return false;
		}
		volMuteSwitch();
	});

	// Volume control popup btn for Playbar
    $(document).on('click', '.volume-popup-btn', function(e) {
		if ($('#volume-popup').css('display') == 'block') {
			$('#volume-popup').modal('toggle');
		}
		else {
			if (SESSION.json['mpdmixer'] == 'disabled') {
				$('.volume-display').css('opacity', '.3');
			}
			$('#volume-popup').modal();
		}
	});

	// Playback button handlers
	$('.play').click(function(e) {
		if (MPD.json['state'] == 'play') {
			$('#countdown-display').countdown('pause');

			if (MPD.json['file'].substr(0, 4).toLowerCase() == 'http') {
                // Pause if upnp url
				var cmd = MPD.json['artist'] == 'Radio station' ? 'stop' : 'pause';
			}
			else {
                // Song file
			    var cmd = 'pause';
			}
		}
		else if (MPD.json['state'] == 'pause') {
			$('#countdown-display').countdown('resume');
			customScroll('playlist', parseInt(MPD.json['song']), 200);
			var cmd = 'play';
		}
		else if (MPD.json['state'] == 'stop') {
			if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
				$('#countdown-display').countdown({since: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			}
			else {
				$('#countdown-display').countdown({until: 0, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
			}
			if (!UI.mobile) {
		        customScroll('playlist', parseInt(MPD.json['song']), 200);
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
        $.getJSON('command/moode.php?cmd=getmpdstatus', function(result) {
            if (parseInt(MPD.json['time']) > 0 && parseInt(result['elapsed']) > 0) {
                // Song file
    			window.clearInterval(UI.knob);
    			if (MPD.json['state'] != 'pause') {
    				sendMpdCmd('pause');
    			}
                setTimeout(function() {
                    sendMpdCmd('seek ' + MPD.json['song'] + ' 0');
                }, DEFAULT_TIMEOUT);
            }
    		else {
                // Radio station
    			sendMpdCmd('previous');
    		}
    		return false;
        });
	});
	$('#volumeup,#volumeup-2').click(function(e) {
		SESSION.json['volmute'] == '1' ? volMuteSwitch() : '';
		var curVol = parseInt(SESSION.json['volknob']);
		var newVol = curVol < GLOBAL.mpdMaxVolume ? curVol + 1 : GLOBAL.mpdMaxVolume;
		setVolume(newVol, 'volume_up');
		return false
	});
	$('#volumedn,#volumedn-2').click(function(e) {
		SESSION.json['volmute'] == '1' ? volMuteSwitch() : '';
		var curVol = parseInt(SESSION.json['volknob']);
		var newVol = curVol > 0 ? curVol - 1 : 0;
		setVolume(newVol, 'volune_down');
		return false
	});
	$('.btn-toggle').click(function(e) {
		var cmd = $(this).data('cmd');
		var toggle = $(this).hasClass('btn-primary') ? '0' : '1';
		if (cmd == 'random' && SESSION.json['ashufflesvc'] == '1') {
            $.get('command/moode.php?cmd=ashuffle', {'ashuffle':toggle});
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

    // Countdown time knob
    $('.playbackknob').knob({
		configure: {'fgColor':accentColor},
        inline: false,
		change : function(value) {
            if (MPD.json['state'] != 'stop') {
				window.clearInterval(UI.knob)
				// Update time display when changing slider
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

			// Repaint not needed
			UI.knobPainted = false;
        },
        release : function(value) {
			if (MPD.json['state'] != 'stop') {
				window.clearInterval(UI.knob);
				var seekto = Math.floor((value * parseInt(MPD.json['time'])) / 1000);
				sendMpdCmd('seek ' + MPD.json['song'] + ' ' + seekto);
				if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
					$('#countdown-display').countdown({since: -seekto, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
				}
				else {
					$('#countdown-display').countdown({until: seekto, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
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
			value = value > GLOBAL.mpdMaxVolume ? GLOBAL.mpdMaxVolume : value;
            setVolume(value, 'knob_change');
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

	// Toggle count up/down and direction icon, radio always counts up
	$('#countdown-display, #m-countdown').click(function(e) {
		if (MPD.json['artist'] != 'Radio station') {
			SESSION.json['timecountup'] == '1' ? SESSION.json['timecountup'] = '0' : SESSION.json['timecountup'] = '1';
            $.post('command/moode.php?cmd=updcfgsystem', {'timecountup': SESSION.json['timecountup']});
            $.getJSON('command/moode.php?cmd=getmpdstatus', function(result) {
                if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
                    // Count up
    				updKnobStartFrom(parseInt(result['elapsed']), MPD.json['state']);
    			}
    			else {
                    // Count down
    				updKnobStartFrom(parseInt(MPD.json['time'] - parseInt(result['elapsed'])), MPD.json['state']);
    			}
                $('#total').html(formatSongTime(MPD.json['time']));
            });
		}
    });

    // Click on Queue item
    $('.playlist, .cv-playlist').on('click', '.pl-entry', function(e) {
        if (GLOBAL.plActionClicked) {
            GLOBAL.plActionClicked = false;
            return;
        }

        var selector = $(this).parent().hasClass('playlist') ? '.playlist' : '.cv-playlist';
        var pos = $(selector + ' .pl-entry').index(this);

        sendMpdCmd('play ' + pos);
        $(this).parent().addClass('active');
    });

	// Click on Queue action menu button (ellipsis)
    $('.playlist').on('click', '.pl-action', function(e) {
        GLOBAL.plActionClicked = true;

		// Store posn for later use by action menu selection
        UI.dbEntry[0] = $('.playlist .pl-action').index(this);
		// Store clock radio play name in UI.dbEntry[5]
		if ($('#pl-' + (UI.dbEntry[0] + 1) + ' .pll2').html().substr(0, 2) == '<i') { // Has icon (fa-microphone)
			// Radio station
			var line2 = $('#pl-' + (UI.dbEntry[0] + 1) + ' .pll2').html();
			var station = line2.substr((line2.indexOf('</i>') + 4));
			UI.dbEntry[5] = station.trim();
		}
		else {
			// Song file
			var title = $('#pl-' + (UI.dbEntry[0] + 1) + ' .pll1').html().trim();
			var line2 = $('#pl-' + (UI.dbEntry[0] + 1) + ' .pll2').text(); // Artist - album
			var artist = line2.substr(0, (line2.indexOf('-') - 1)); // Strip off album
			UI.dbEntry[5] = title + ', ' + artist;
		}
    });

	// Save playlist
    $('#pl-btnSave').click(function(e){
		var plname = $('#pl-saveName').val();

		if (plname) {
			if (~plname.indexOf('NAS') || ~plname.indexOf('RADIO') || ~plname.indexOf('SDCARD')) {
				notify('plnameerror');
			}
			else {
                $.get('command/moode.php?cmd=savepl&plname=' + plname);
				notify('savepl');
			}
		}
		else {
			notify('needplname');
		}
    });
    $('#savepl-modal').on('shown.bs.modal', function(e) {
        $('#pl-saveName').focus();
    });

	// Set favorites
    $('#pl-btnSetFav').click(function(e){
		var favname = $('#pl-favName').val();

		if (favname) {
			if (~favname.indexOf('NAS') || ~favname.indexOf('RADIO') || ~favname.indexOf('SDCARD')) {
				notify('plnameerror');
			}
			else {
                $.get('command/moode.php?cmd=setfav&favname=' + favname);
				notify('favset');
			}
		}
		else {
			notify('needplname');
		}
    });
    $('#setfav-modal').on('shown.bs.modal', function() {
        $('#pl-favName').focus();
    });
	// Add item to favorites
    $('.addfav').click(function(e){
		// pulse the btn
		$('.addfav i').addClass('pulse');
		$('.addfav i').addClass('fas');
		setTimeout(function() {
			$('.addfav i').removeClass('fas');
			$('.addfav i').removeClass('pulse');
		}, 1000);

		// Add current pl item to favorites playlist
		if (MPD.json['file'] != null) {
            $.get('command/moode.php?cmd=addfav&favitem=' + encodeURIComponent(MPD.json['file']));
			notify('favadded');
		}
		else {
			notify('nofavtoadd');
		}
    });

	// Click on artist name in lib meta area
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
	$('#currentartist').click(function(e) {
        if (!$('#playback-panel').hasClass('cv')) {
            // Radio station
    		if (MPD.json['artist'] == 'Radio station') {
                // Count number of headers before the item
                var headerCount = 0;
    			$('.database-radio li').each(function(index){
                    if ($(this).hasClass('horiz-rule-radioview')) {
                        headerCount = headerCount + 1;
                    }
                    if ($(this).children('span').text() == RADIO.json[MPD.json['file']]['name']) {
    					UI.radioPos = index + 1;
                        return false;
    				}
    			});

                currentView = 'playback,radio';
    			$('#playback-switch').click();

                if (!$('.radio-view-btn').hasClass('active')) {
    				$('.radio-view-btn').click();
    			}

                //console.log(UI.radioPos, headerCount, RADIO.json[MPD.json['file']]['name']);
                $('.database-radio li').removeClass('active');
                setTimeout(function() {
                    $('#ra-' + (UI.radioPos - headerCount)).addClass('active');
                    UI.dbEntry[3] = 'ra-' + (UI.radioPos - headerCount);
                    customScroll('radio', UI.radioPos, 200);
                }, DEFAULT_TIMEOUT);
    		}
    		// Song file
    		else {
                var thisText = $(this).text().indexOf('...') != -1 ? $(this).text().slice(0, -3) : $(this).text();
    			$('#playback-switch').click();
    			$('.tag-view-btn').click();
				$('#artistsList .lib-entry').filter(function() {return $(this).text() == thisText/*MPD.json['artist']*/;}).click();
				customScroll('artists', UI.libPos[2], 200);
    		}
        }
	});

	// Folder view item click
	$('.database').on('click', '.db-browse', function(e) {
        //console.log('Folder item click');
	    if ($(this).hasClass('db-folder') || $(this).hasClass('db-savedplaylist')) {
			var cmd = $(this).hasClass('db-folder') ? 'lsinfo' : 'listsavedpl';
            UI.dbEntry[3] = $(this).parent().attr('id');
			UI.dbPos[UI.dbPos[10]] = $(this).parent().attr('id').replace('db-','');
			++UI.dbPos[10];
			mpdDbCmd(cmd, $(this).parent().data('path'));
		}
	});

    /* ORIG
    // Play album item on click/tap (One touch play)
	$('.database').on('click', '.db-album', function(e) {
        // Folder: Alternative 1 (Like other parts of Folder view)
        var cmd = SESSION.json['library_instant_play'] == 'Clear/Play' ? 'clear_play_item' : 'play_item';
		mpdDbCmd(cmd, $(this).parent().attr('data-path'));
        if (cmd == 'clear_play_item') {
            notify(cmd);
        }

        // Group: Alternative 2 (like Tag/Album view)
        var files = [];
		for (var i = $(this).parent().index() + 1; i < $('#folderlist li').length; i++) {
			if ($('#folderlist li').eq(i).children('div').hasClass('db-song')) {
				files.push($('#folderlist li').eq(i).attr('data-path'));
			} else {
				break;
			}
		}
        var cmd = SESSION.json['library_instant_play'] == 'Clear/Play' ? 'clear_play_group' : 'play_group';
		mpdDbCmd(cmd, files);
        if (cmd == 'clear_play_item') {
            notify(cmd);
        }
	});
    */

    // Folder view context menu click
	$('.database').on('click', '.db-action, .db-album, .db-song', function(e) {
        //console.log('Folder menu click');
		$('#db-' + UI.dbPos[UI.dbPos[10]].toString()).removeClass('active');
		UI.dbEntry[0] = $(this).parent().attr('data-path');
		UI.dbEntry[3] = $(this).parent().attr('id'); // Used in .context-menu a click handler to remove highlight
		$('#db-search-results').css('font-weight', 'normal');
		//$('.database li').removeClass('active');
		$(this).parent().addClass('active');
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
        UI.dbPos[UI.dbPos[10]] = 0;
		if (UI.dbCmd == 'lsinfo' || UI.dbCmd == 'listsavedpl') {
			mpdDbCmd(UI.dbCmd, UI.path);
		}
        //console.log(UI.dbCmd, UI.path);
	});

	$('#db-search-submit').click(function(e) {
		var searchStr = '';
		if ($('#dbsearch-alltags').val() != '') {
			searchStr = $('#dbsearch-alltags').val().trim();
            if (currentView == 'folder') {
                $.post('command/moode.php?cmd=search' + '&tagname=any', {'query': searchStr}, function(data) {renderFolderView(data, '', searchStr);}, 'json');
            }
            else if (currentView == 'tag' || currentView == 'album') {
                searchStr = "(any contains '" + searchStr + "')";
                applyLibFilter('tags', searchStr);
            }
		}
		else {
			searchStr += $('#dbsearch-genre').val() == '' ? '' : " AND (genre contains '" + $('#dbsearch-genre').val().trim() + "')";
			searchStr += $('#dbsearch-artist').val() == '' ? '' : " AND (artist contains '" + $('#dbsearch-artist').val().trim() + "')";
			searchStr += $('#dbsearch-album').val() == '' ? '' : " AND (album contains '" + $('#dbsearch-album').val().trim() + "')";
			searchStr += $('#dbsearch-title').val() == '' ? '' : " AND (title contains '" + $('#dbsearch-title').val().trim() + "')";
			searchStr += $('#dbsearch-albumartist').val() == '' ? '' : " AND (albumartist contains '" + $('#dbsearch-albumartist').val().trim() + "')";
			searchStr += $('#dbsearch-date').val() == '' ? '' : " AND (date contains '" + $('#dbsearch-date').val().trim() + "')";
			searchStr += $('#dbsearch-composer').val() == '' ? '' : " AND (composer contains '" + $('#dbsearch-composer').val().trim() + "')";
            searchStr += $('#dbsearch-conductor').val() == '' ? '' : " AND (conductor contains '" + $('#dbsearch-conductor').val().trim() + "')";
			searchStr += $('#dbsearch-performer').val() == '' ? '' : " AND (performer contains '" + $('#dbsearch-performer').val().trim() + "')";
            searchStr += $('#dbsearch-work').val() == '' ? '' : " AND (work contains '" + $('#dbsearch-work').val().trim() + "')";
			searchStr += $('#dbsearch-comment').val() == '' ? '' : " AND (comment contains '" + $('#dbsearch-comment').val().trim() + "')";
			searchStr += $('#dbsearch-file').val() == '' ? '' : " AND (file contains '" + $('#dbsearch-file').val().trim() + "')";
			if (searchStr != '') {
                searchStr = searchStr.slice(5);
                if (currentView == 'folder') {
                    $.post('command/moode.php?cmd=search' + '&tagname=specific', {'query': searchStr}, function(data) {renderFolderView(data, '', searchStr);}, 'json');
                }
                else if (currentView == 'tag' || currentView == 'album') {
                    applyLibFilter('tags', searchStr);
                }
			}
		}
	});
	$('#db-search-reset').click(function(e) {
		$('#dbsearch-alltags, #dbsearch-genre, #dbsearch-artist, #dbsearch-album, #dbsearch-title, #dbsearch-albumartist, #dbsearch-date, #dbsearch-composer, #dbsearch-conductor, #dbsearch-performer, #dbsearch-comment, #dbsearch-file').val('');
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
            if ($(this).children('div').hasClass('db-song')) {
                dbFilterResults.push($(this).attr('data-path'));
            }
		});
	});
	$('#context-menu-db-search-results a').click(function(e) {
		$('#db-search-results').css('font-weight', 'normal');
	    if ($(this).data('cmd') == 'add_group') {
	        mpdDbCmd('add_group', dbFilterResults);
	        notify($(this).data('cmd'));
		}
	    if ($(this).data('cmd') == 'play_group') {
	        mpdDbCmd('play_group', dbFilterResults);
	        notify($(this).data('cmd'));
		}
	    if ($(this).data('cmd') == 'clear_play_group') {
	        mpdDbCmd('clear_play_group', dbFilterResults);
	        notify($(this).data('cmd'));
		}
	});

    // Refresh the station list
	$('#ra-refresh').click(function(e) {
		mpdDbCmd('lsinfo_radio');
        lazyLode('radio');
        $('#database-radio').scrollTo(0, 200);
		UI.radioPos = -1;
		storeRadioPos(UI.radioPos)
        $("#searchResetRa").hide();
        showSearchResetRa = false;
	});
	// Create new station
	$('#ra-new').click(function(e) {
		$('#new-station-name').val('New station');
		$('#new-station-url').val('http://');
        $('#new-logoimage').val('');
		$('#preview-new-logoimage').html('');
        $('#info-toggle-new-logoimage').css('margin-left','unset');
        $('#new-station-tags').css('margin-top', '0');
        $('#new-station-type span').text('Regular');
        $('#new-station-genre').val('');
        $('#new-station-broadcaster').val('');
        $('#new-station-language').val('');
        $('#new-station-country').val('');
        $('#new-station-region').val('');
        $('#new-station-bitrate').val('');
        $('#new-station-format').val('');
        $('#new-station-geo-fenced span').text('No');
        //$('#new-station-reserved1').val('');
        //$('#new-station-reserved2').val('');

		$('#newstation-modal').modal();
	});
    $('#newstation-modal').on('shown.bs.modal', function() {
        $('#new-station-name').focus();
    });

	// Radio search
	$('#ra-filter').keyup(function(e){
		if (!showSearchResetRa) {
			$('#searchResetRa').show();
			showSearchResetRa = true;
		}

		clearTimeout(searchTimer);

		var selector = this;

		searchTimer = setTimeout(function(){
			var filter = $(selector).val().trim();
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
            lazyLode('radio');
            $('#database-radio').scrollTo(0, 200);

		}, SEARCH_TIMEOUT);
	});
	$('#searchResetRa').click(function(e) {
		$('.database-radio li').css('display', 'inline-block');
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
			var filter = $(selector).val().trim();
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
			$('#container-playlist').scrollTo(0, 200);
		}, SEARCH_TIMEOUT);
	});
	$('#searchResetPl').click(function(e) {
		$("#searchResetPl").hide();
		showSearchResetPl = false;
		$('.playlist li').css('display', 'block');
	});

    // Library search
    // NOTE: The keydown event was added to work around an issue where Firefox steals the Enter key and keyup never happens.
    $('#lib-album-filter').on('keydown keyup', function(e){
        //console.log(e);
        if (e.type == 'keyup') {
            e.preventDefault();
        }

        $('#lib-album-filter').val().length > 0 ? $('#searchResetLib').show() : $('#searchResetLib').hide();

        if (e.key == 'Enter' && $('#lib-album-filter').val().length > 0) {
            $('#lib-album-filter').blur();

            // Parse search string
            var searchStr = $(this).val().trim().toLowerCase();
            var filter = splitStringAtFirstSpace(searchStr);
            if (filter.length == 1) {
                filter[1] = '';
            }

            // Apply filter
            if (GLOBAL.allFilters.includes(filter[0])) {
                if (GLOBAL.twoArgFilters.includes(filter[0])) {
                    applyLibFilter(filter[0], filter[1]);
                }
                else {
                    applyLibFilter(filter[0]);
                }
            }
            // Default to filterType = any
            else {
                applyLibFilter('any', filter[0] + (filter[1] ? ' ' + filter[1] : ''));
            }

            // Close menu
            $('#viewswitch').click();
        }
	});

	$('#searchResetLib').click(function(e) {
		e.preventDefault();
		document.getElementById("lib-album-filter").focus();
        $('#lib-album-filter').val('');
        $('#searchResetLib').hide();
		return false;
	});

/*	$('#searchResetLib').click(function(e) {
        $('#lib-album-filter').val('');
        $('#searchResetLib').hide();
        if (SESSION.json['library_flatlist_filter'] != 'full_lib') {
            applyLibFilter('full_lib');
        }
	});*/

	// Playback history search
	$('#ph-filter').keyup(function(e){
		if (!showSearchResetPh) {
			$('#searchResetPh').show();
			showSearchResetPh = true;
		}

		clearTimeout(searchTimer);

		var selector = this;
		searchTimer = setTimeout(function(){
			var filter = $(selector).val().trim();
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
		}, SEARCH_TIMEOUT);
	});
	$('#searchResetPh').click(function(e) {
		$("#searchResetPh").hide();
		showSearchResetPh = false;
		$('.playhistory li').css('display', 'list-item');
		$('#ph-filter-results').html('');
	});

    // Radio view context menu
	$('.database-radio').on('click', '.cover-menu', function(e) {
        var pos = $(this).parents('li').index();
        var path = $(this).parents('li').data('path');

        UI.dbEntry[0] = path;
        UI.radioPos = pos;
		storeRadioPos(UI.radioPos)

        $('#' + UI.dbEntry[3]).removeClass('active');
        UI.dbEntry[3] = $(this).parents('li').attr('id');
        $(this).parents('li').addClass('active');
	});

	// Buttons on modals
	$('.btn-del-savedpl').click(function(e){
		mpdDbCmd('delsavedpl', UI.dbEntry[0]);
		notify('delsavedpl');
	});
	$('#btn-create-station').click(function(e){
		if ($('#new-station-name').val().trim() == '' || $('#new-station-url').val().trim() == '') {
			notify('blankentries', 'Station not created');
		}
		else {
			mpdDbCmd('newstation', {
                'name': $('#new-station-name').val(),
                'url': $('#new-station-url').val(),
                'type': getParamOrValue('value', $('#new-station-type span').text()),
                'genre': $('#new-station-genre').val(),
                'broadcaster': $('#new-station-broadcaster').val(),
                'language': $('#new-station-language').val(),
                'country': $('#new-station-country').val(),
                'region': $('#new-station-region').val(),
                'bitrate': $('#new-station-bitrate').val(),
                'format': $('#new-station-format').val(),
                'geo_fenced': $('#new-station-geo-fenced span').text(),
                'reserved1': 'NULL',
                'reserved2': 'NULL'
            });
		}
	});
	$('#btn-update-station').click(function(e){
		if ($('#edit-station-name').val().trim() == '' || $('#edit-station-url').val().trim() == '') {
			notify('blankentries', 'Station not updated');
		}
		else {
            mpdDbCmd('updstation', {
                'id': GLOBAL.editStationId,
                'name': $('#edit-station-name').val(),
                'url': $('#edit-station-url').val(),
                'type': getParamOrValue('value', $('#edit-station-type span').text()),
                'genre': $('#edit-station-genre').val(),
                'broadcaster': $('#edit-station-broadcaster').val(),
                'language': $('#edit-station-language').val(),
                'country': $('#edit-station-country').val(),
                'region': $('#edit-station-region').val(),
                'bitrate': $('#edit-station-bitrate').val(),
                'format': $('#edit-station-format').val(),
                'geo_fenced': $('#edit-station-geo-fenced span').text(),
                'reserved1': 'NULL',
                'reserved2': 'NULL'
            });
		}
	});
	$('#btn-del-station').click(function(e){
		mpdDbCmd('delstation', UI.dbEntry[0]);
	});
	$('.btn-delete-plitem').click(function(e){
		var cmd = '';
		var begpos = $('#delete-plitem-begpos').val() - 1;
		var endpos = $('#delete-plitem-endpos').val() - 1;
		// NOTE: format for single or multiple, endpos not inclusive so must be bumped for multiple
		begpos == endpos ? cmd = 'delplitem&range=' + begpos : cmd = 'delplitem&range=' + begpos + ':' + (endpos + 1);
		notify('remove');
        $.get('command/moode.php?cmd=' + cmd);

	});
	// Speed btns on delete modal
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
		// Move begpos newpos or move begpos:endpos newpos
		begpos == endpos ? cmd = 'moveplitem&range=' + begpos + '&newpos=' + newpos : cmd = 'moveplitem&range=' + begpos + ':' + (endpos + 1) + '&newpos=' + newpos;
		notify('move');
        $.get('command/moode.php?cmd=' + cmd);
	});
	// Speed btns on move modal
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

	// Speed buttons on plaback history log
	$('.ph-firstPage').click(function(e){
		$('#container-playhistory').scrollTo(0 , 200);
	});
	$('.ph-lastPage').click(function(e){
		$('#container-playhistory').scrollTo('100%', 200);
	});

	// Playbar coverview btn
	$('.coverview').on('click', function(e) {
		e.stopImmediatePropagation();
		screenSaver('1');
	});

    // Coverview playlist
	$('#cv-playlist-btn').on('click', function(e) {
        $('#cv-playlist').toggle();

        if ($('#cv-playlist').css('display') == 'block') {
            $('#cv-playlist ul').html($('#playlist ul').html());
            if (SESSION.json['playlist_art'] == 'Yes') {
                lazyLode('cv-playlist');
            }
            customScroll('cv-playlist', parseInt(MPD.json['song']));

            GLOBAL.playbarPlaylistTimer = setTimeout(function() {
                $('#cv-playlist ul').html('');
                $('#cv-playlist').hide();
            }, 20000);
        }
        else {
            e.preventDefault();
            $('#cv-playlist ul').html('');
            window.clearTimeout(GLOBAL.playbarPlaylistTimer);
        }
	});

	// Disconnect active renderer
	//$('.disconnect-renderer').live('click', function(e) {
    $(document).on('click', '.disconnect-renderer', function(e) {
		var job = $(this).data('job');
        $.post('command/moode.php?cmd=disconnect-renderer', {'job':job});
	});

    // First use help
    $('#playback-firstuse-help').click(function(e) {
        $('#playback-firstuse-help').css('display', '');
        SESSION.json['first_use_help'] = 'n,' + SESSION.json['first_use_help'].split(',')[1];
        $.post('command/moode.php?cmd=updcfgsystem', {'first_use_help': SESSION.json['first_use_help']});
    });
    $('#playbar-firstuse-help').click(function(e) {
        $('#playbar-firstuse-help').css('display', '');
        SESSION.json['first_use_help'] = SESSION.json['first_use_help'].split(',')[0] + ',n';
        $.post('command/moode.php?cmd=updcfgsystem', {'first_use_help': SESSION.json['first_use_help']});
    });

    // Track info for Playback
    $('#extra-tags-display').click(function(e) {
        if ($('#currentsong').html() != '') {
            var cmd = MPD.json['artist'] == 'Radio station' ? 'station_info' : 'track_info';
            audioinfo(cmd, MPD.json['file']);
        }
    });

	$('#audioinfo-track').live('click', function(e) {
		$('#audioinfo-modal').removeClass('hardware').addClass('track');
	});

	$('#audioinfo-hardware').live('click', function(e) {
		$('#audioinfo-modal').removeClass('track').addClass('hardware');
	});

    // CoverView screen saver reset
    $('#screen-saver, #playback-panel, #library-panel, #folder-panel, #radio-panel, #menu-bottom').click(function(e) {
        //console.log('resetscnsaver: timeout (' + SESSION.json['scnsaver_timeout'] + ', currentView: ' + currentView + ')');
        if ($(this).attr('id') == 'menu-bottom') {
            return;
        }

        if (coverView) {
			$('body').removeClass('cv');
			coverView = false;
            setColors();

            // TEST: Fixes issue where some elements briefly remain on-screen when entering or returning from CoverView
            $('#cv-playlist ul').html('');
            $('#cv-playlist').hide();
            $('#lib-coverart-img').show();

            // TEST: Fixes Queue sometimes not being visable after returning from CoverView
            UI.mobile ? $('#playback-queue').css('width', '99.9%') : $('#playback-queue').css('width', '38.1%');
            setTimeout(function() {
                $('#playback-queue').css('width', ''); // TEST: Restore correct width to force Queue visable
            }, DEFAULT_TIMEOUT);
            if (SESSION.json['playlist_art'] == 'Yes') {
                lazyLode('playlist');
            }
            customScroll('playlist', parseInt(MPD.json['song']));
        }
        // Reset screen saver timeout global
        else if (SESSION.json['scnsaver_timeout'] != 'Never') {
            // Wait a bit to allow other job that may be queued to be processed
            setTimeout(function() {
                $.get('command/moode.php?cmd=resetscnsaver');
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
