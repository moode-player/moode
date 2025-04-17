/*!
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

jQuery(document).ready(function($) { 'use strict';
    // Call $.pnotify if created by backend
    if( window.ui_notify != undefined ) {
        ui_notify();
	}

    GLOBAL.scriptSection = 'panels';

	$('#config-back, #config-home').hide();
	$('#config-tabs').css('display', 'none');
	$('#panel-footer').css('display', 'flex');

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
    $.post('command/cfg-table.php?cmd=upd_cfg_system', {'library_pixelratio': window.devicePixelRatio});

	// Store scrollbar width (it will be 0 for overlay scrollbars and > 0 for always on scrollbars
	var hiddenDiv = $("<div style='position:absolute; top:-10000px; left:-10000px; width:100px; height:100px; overflow:scroll;'></div>").appendTo("body");
	GLOBAL.sbw = hiddenDiv.width() - hiddenDiv[0].clientWidth;
	$("body").get(0).style.setProperty("--sbw", GLOBAL.sbw + 'px');
    //console.log(hiddenDiv.width() - hiddenDiv[0].clientWidth + 'px');

    // Enable custom scroll bars unless overlay scroll bars are enabled on the platform (scroll bar width sbw = 0)
    if (GLOBAL.sbw) {
        $('body').addClass('custom-scrollbars');
    }

    // Only show Prefs transparency options if alphablend != 1.00
    var target = document.querySelector('#alpha-blend span')
    var observer = new MutationObserver(mutate);
    var config = {characterData: true, attributes: false, childList: true, subtree: false};
    observer.observe(target, config);
    function mutate(mutations) {
        mutations.forEach(function(mutation) {
            if ($('#alpha-blend span').text() != '1.00') {
                $('#cover-options').css('display', 'block');
            } else {
                $('#cover-options').css('display', '');
            }
        });
    }

	// Load current cfg
    $.getJSON('command/cfg-table.php?cmd=get_cfg_tables', function(data) {
    	SESSION.json = data['cfg_system'];
    	THEME.json = data['cfg_theme'];
    	RADIO.json = data['cfg_radio'];

        // Check for native lazy load support in Browser
        // @bitkeeper contribution: https://github.com/moode-player/moode/pull/131
        if ('loading' in HTMLImageElement.prototype && SESSION.json['native_lazyload'] == 'Yes') {
            GLOBAL.nativeLazyLoad = true;
        }

        // DEBUG: Display viewport size and pixel ratio by re-using the pkgid_suffix param
        if (SESSION.json['pkgid_suffix'] == 'viewport') {
            notify(NOTIFY_TITLE_INFO, 'viewport', window.innerWidth + 'x' + window.innerHeight + ', P/R=' + window.devicePixelRatio, NOTIFY_DURATION_MEDIUM);
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
        renderRadioView(false); // False = don't run lazylode() since it's going to be run as part of makeActive downstream
        loadLibrary(); // Tag and Album views
        renderPlaylistView();
        $.getJSON('command/music-library.php?cmd=lsinfo', {'path': ''}, function(data) {
            renderFolderView(data, '');
        });

        // Library item positions
    	// Radio view
    	UI.radioPos = parseInt(SESSION.json['radio_pos']);
    	// Tag/Album view
    	var tmpStr = SESSION.json['lib_pos'].split(',');
    	UI.libPos[0] = parseInt(tmpStr[0]); // Album list
    	UI.libPos[1] = parseInt(tmpStr[1]); // Album cover
    	UI.libPos[2] = parseInt(tmpStr[2]); // Artist list
        UI.libPos[3] = parseInt(tmpStr[3]); // Genre list
        // Playlist view
    	UI.playlistPos = parseInt(SESSION.json['playlist_pos']);
        //console.log('scripts-panels: UI.radioPos', UI.radioPos);
        //console.log('scripts-panels: UI.libPos', UI.libPos);
        //console.log('scripts-panels: UI.playlistPos', UI.playlistPos);

        // Set volume knob max
        $('#volume, #volume-2').attr('data-max', SESSION.json['volume_mpd_max']);
        GLOBAL.mpdMaxVolume = parseInt(SESSION.json['volume_mpd_max']);

    	// Set font size
    	setFontSize();

        // Show/hide first use help
        // [0]: Playback coupon, [1]: Playbar coupon, [2]: Welcome notification
        var firstUseHelp = SESSION.json['first_use_help'].split(',');
        if (firstUseHelp[0] == 'y') {
            $('#playback-firstuse-help').css('display', 'block');
        }
        if (firstUseHelp[1] == 'y') {
            $('#playbar-firstuse-help').css('display', 'block');
        }
        if (firstUseHelp[2] == 'y') {
            notify(NOTIFY_TITLE_INFO, 'firstuse_welcome', NOTIFY_MSG_WELCOME, NOTIFY_DURATION_INFINITE);
        }

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
        //themeOp = blurrr == true ? .85 : .95;
        themeOp = 0.75; // A bit less opacity for menu background
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
        GLOBAL.npIcon = getKeyOrValue('value', SESSION.json['show_npicon']);
    	setColors();

        $('.ralbum').html('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M475.31 364.144L288 256l187.31-108.144c5.74-3.314 7.706-10.653 4.392-16.392l-4-6.928c-3.314-5.74-10.653-7.706-16.392-4.392L272 228.287V12c0-6.627-5.373-12-12-12h-8c-6.627 0-12 5.373-12 12v216.287L52.69 120.144c-5.74-3.314-13.079-1.347-16.392 4.392l-4 6.928c-3.314 5.74-1.347 13.079 4.392 16.392L224 256 36.69 364.144c-5.74 3.314-7.706 10.653-4.392 16.392l4 6.928c3.314 5.74 10.653 7.706 16.392 4.392L240 283.713V500c0 6.627 5.373 12 12 12h8c6.627 0 12-5.373 12-12V283.713l187.31 108.143c5.74 3.314 13.079 1.347 16.392-4.392l4-6.928c3.314-5.74 1.347-13.079-4.392-16.392z"/></svg>');

    	// Connect to server engines
        engineMpd();
        engineCmd();

        // NOTE: We may use this in the future
        $.getJSON('command/system.php?cmd=get_client_ip', function(data) {
            GLOBAL.thisClientIP = data;
            debugLog('This client IP: ' + GLOBAL.thisClientIP);
        });

        // Setup pines notify
        $.pnotify.defaults.history = false;

    	// Show button bars
    	if (!UI.mobile) {
    		$('#playbtns, #togglebtns').show();
    	}

    	// Screen saver backdrop style
    	if (SESSION.json['scnsaver_style'] == 'Animated') {
    		$('#ss-style').css('background', '');
    		$('#ss-style, #ss-backdrop').css('display', '');
            $('#ss-style').css('animation', 'colors2 60s infinite');
    	} else if (SESSION.json['scnsaver_style'] == 'Theme') {
    		$('#ss-style, #ss-backdrop').css('display', 'none');
    	} else {
            $('#ss-style').css('animation', 'initial');
    		$('#ss-style, #ss-backdrop').css('display', '');
            if (SESSION.json['scnsaver_style'] == 'Gradient (Linear)') {
                $('#ss-style').css('background', 'linear-gradient(to bottom, rgba(0,0,0,0.15) 0%,rgba(0,0,0,0.60) 25%,rgba(0,0,0,0.75) 40%, rgba(0,0,0,.8) 60%, rgba(0,0,0,.9) 100%)');
            } else if (SESSION.json['scnsaver_style'] == 'Gradient (Radial)') {
                $('#ss-style').css('background', 'radial-gradient(circle at 50% center, rgba(64, 64, 64, .5) 5%, rgba(0, 0, 0, .85) 60%)');
            } else if (SESSION.json['scnsaver_style'] == 'Pure Black') {
                $('#ss-style').css('background', 'rgba(0,0,0,1)');
            }
    	}

        // Reset screen saver timeout global
        if (SESSION.json['scnsaver_timeout'] != 'Never') {
            $.post('command/playback.php?cmd=reset_screen_saver');
        }

     	// Set clock radio icon state
    	if (SESSION.json['clkradio_mode'] == 'Clock Radio' || SESSION.json['clkradio_mode'] == 'Sleep Timer') {
    		$('#clockradio-icon').removeClass('clockradio-off')
    		$('#clockradio-icon').addClass('clockradio-on')
    	} else {
    		$('#clockradio-icon').removeClass('clockradio-on')
    		$('#clockradio-icon').addClass('clockradio-off')
    	}

    	// Set volume control state
    	if (SESSION.json['mpdmixer'] == 'none') {
            // Fixed (0dB)
    		disableVolKnob();
            SESSION.json['volmute'] = '0';
            $.post('command/cfg-table.php?cmd=upd_cfg_system', {'volmute': '0'});
    	} else {
    		$('#volume').val(SESSION.json['volknob']);
    		$('#volume-2').val(SESSION.json['volknob']);
    		$('.volume-display').css('opacity', '');
    		$('.volume-display div').text(SESSION.json['volknob']);
    	}

        // Show/hide Play history on main menu
        if (SESSION.json['playhist'] == 'Yes') {
            $('#playhistory-hide').css('display', 'block');
        } else {
            $('#playhistory-hide').css('display', 'none');
        }
        // Show/hide Bluetoioth on main menu
        if (SESSION.json['btsvc'] == '1') {
            $('#bluetooth-hide').css('display', 'block');
        } else {
            $('#bluetooth-hide').css('display', 'none');
        }

        // Tag view header text
        $('#genreheader > div').html(SESSION.json['library_tagview_genre']);
        $('#tagview-header-text').text('Albums' +
            ((SESSION.json['library_tagview_sort'] == 'Album' || SESSION.json['library_tagview_sort'] == 'Album/Year') ?
            '' : ' by ' + SESSION.json['library_tagview_sort']));
        // Artists column header
        var artistHeader = SESSION.json['library_tagview_artist'].replace('Artist', 'Artists');
        $('#artistheader > div').html(artistHeader);
        // Hide alphabits index if indicated
        if (SESSION.json['library_albumview_sort'] == 'Year') {
            $('#index-albums').hide();
            $('#index-albumcovers').attr('style', 'display:none!important');
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

        // Multiroom Sender context menu item
        if (SESSION.json['multiroom_tx'] == 'On') {
            $('#context-menu-multiroom-sender').show();
            $('#updater-notification').css('left', '54%');
        }

        // CoverView toggle
        if (SESSION.json['local_display'] == '1') {
            $('#context-menu-coverview-toggle').show();
        }

        // Software update
        if (SESSION.json['updater_auto_check'] == 'On' && SESSION.json['updater_available_update'].substring(0, 7) == 'Release') {
            if (currentView.indexOf('playback') != -1) {
                $('#updater-notification').show();
            }
        }
        $('#updater-notification').click(function(e) {
            if (SESSION.json['updater_available_update'].substring(0, 7) == 'Release') {
                var msg = SESSION.json['updater_available_update'] + 'This notification can be turned off in System Config';
                notify(NOTIFY_TITLE_INFO, 'updater', msg, NOTIFY_DURATION_MEDIUM);
            } else {
                $('#updater-notification').hide();
            }
        });

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
    		$(window).scrollTop(0);

    		if (UI.mobile) {
    			$('#container-playqueue').css('visibility','hidden');
    			$('#playback-controls').show();
    		}
            else {
		        customScroll('playqueue', parseInt(MPD.json['song']));
    		}

            $('#panel-footer').hide();

            // Multiroom sender header icon
            SESSION.json['multiroom_tx'] == 'On' ? $('#multiroom-sender').show() : $('#multiroom-sender').hide();
    	}
        // Library
    	else {
    		$('#panel-footer, #viewswitch').css('display', 'flex');
    		$('#playback-switch').hide();
    	}

        // Radio view
    	if (currentView == 'radio') {
    		makeActive('.radio-view-btn', '#radio-panel', currentView);
    	}
        // Folder view
    	else if (currentView == 'folder') {
    		makeActive('.folder-view-btn', '#folder-panel', currentView);
    	}
        // Tag view
    	else if (currentView == 'tag'){
    		makeActive('.tag-view-btn', '#library-panel', currentView);
            SESSION.json['library_show_genres'] == 'Yes' ? $('#top-columns').removeClass('nogenre') : $('#top-columns').addClass('nogenre');
    	}
    	// Album view
    	else if (currentView == 'album'){
    		makeActive('.album-view-btn', '#library-panel', currentView);
    	}
        // Playlist view
    	else if (currentView == 'playlist') {
    		makeActive('.playlist-view-btn', '#playlist-panel', currentView);
    	}

        // CoverView auto-display
        //notify(NOTIFY_TITLE_INFO, 'debug', GLOBAL.userAgent, NOTIFY_DURATION_MEDIUM);
        if (GLOBAL.chromium && SESSION.json['local_display'] == '1' && SESSION.json['auto_coverview'] == '-on') {
            notify(NOTIFY_TITLE_INFO, 'auto_coverview', 'will be activating in ' + NOTIFY_DURATION_DEFAULT + ' seconds.', NOTIFY_DURATION_DEFAULT);
            setTimeout(function() {
                screenSaver('scnactive1');
            }, NOTIFY_DURATION_DEFAULT * 1000);
        }

        // On-screen keyboard
        if (GLOBAL.chromium && SESSION.json['on_screen_kbd'] == 'On') {
            initializeOSK();
        }

        // First boot checks
        if (SESSION.json['user_id'] == NO_USERID_DEFINED) {
             // No userid defined when image was created
            notify(NOTIFY_TITLE_ERROR, 'userid_error', NOTIFY_MSG_NO_USERID, NOTIFY_DURATION_INFINITE);
        //} else if (SESSION.json['first_use_help'].includes('y')) {
        //    // First use welcome notification
        //    notify(NOTIFY_TITLE_INFO, 'firstuse_welcome', NOTIFY_MSG_WELCOME, NOTIFY_DURATION_INFINITE);
        }
    });

	//
	// EVENT HANDLERS
	//

    // Radio view
	$('.radio-view-btn').click(function(e){
        makeActive('.radio-view-btn','#radio-panel','radio');
	});
    // Playlist view
	$('.playlist-view-btn').click(function(e){
        makeActive('.playlist-view-btn','#playlist-panel','playlist');
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
			$('#lib-coverart-img').html('<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-album">' + '<img class="lib-coverart" ' + 'src="' + DEFAULT_ALBUM_COVER + '"></a>');
            $('#lib-collection-stats, #songsList').html('');
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

	// Mute toggle
	$('.volume-display').on('click', function(e) {
		if (SESSION.json['mpdmixer'] == 'none') {
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
			if (SESSION.json['mpdmixer'] == 'none') {
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
			customScroll('playqueue', parseInt(MPD.json['song']), 200);
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
		        customScroll('playqueue', parseInt(MPD.json['song']), 200);
			}
			var cmd = 'play';
		}
		window.clearInterval(UI.knob);
		sendMpdCmd(cmd);
		return false;
	});
	$('.next').click(function(e) {
        if (MPD.json['random'] == '1' && SESSION.json['ashuffle'] == '0') {
            // Don't wrap last track to first track when MPD random play is on
            sendMpdCmd('next');
        } else {
            // Custom wrap last track to first track
    		var cmd = $(".playqueue li").length == (parseInt(MPD.json['song']) + 1).toString() ? 'play 0' : 'next';
    		sendMpdCmd(cmd);
        }
		return false;
	});
	$('.prev').click(function(e) {
        $.getJSON('command/playback.php?cmd=get_mpd_status', function(data) {
            if (parseInt(MPD.json['time']) > 0 && parseInt(data['elapsed']) > 0) {
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
		setVolume(newVol, 'volume_down');
		return false
	});
	$('.btn-toggle').click(function(e) {
		var cmd = $(this).data('cmd');
		var toggleValue = $(this).hasClass('btn-primary') ? '0' : '1';
		if (cmd == 'random' && SESSION.json['ashufflesvc'] == '1') {
            $.post('command/playback.php?cmd=toggle_ashuffle', {'toggle_value':toggleValue});
		    $('.random').toggleClass('btn-primary');
		    $('.consume').toggleClass('btn-primary');
			sendMpdCmd('consume ' + toggleValue);
		}
		else {
			if (cmd == 'consume') {
				$('#menu-check-consume').toggle();
			}
		    $('.' + cmd).toggleClass('btn-primary');
			sendMpdCmd(cmd + ' ' + toggleValue);
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

	// Toggle count up/down and direction icon, radio always counts up
	$('#countdown-display, #m-countdown').click(function(e) {
		if (MPD.json['artist'] != 'Radio station') {
			SESSION.json['timecountup'] == '1' ? SESSION.json['timecountup'] = '0' : SESSION.json['timecountup'] = '1';
            $.post('command/cfg-table.php?cmd=upd_cfg_system', {'timecountup': SESSION.json['timecountup']});
            $.getJSON('command/playback.php?cmd=get_mpd_status', function(data) {
                if (SESSION.json['timecountup'] == '1' || parseInt(MPD.json['time']) == 0) {
                    // Count up
    				updKnobStartFrom(parseInt(data['elapsed']), MPD.json['state']);
    			}
    			else {
                    // Count down
    				updKnobStartFrom(parseInt(MPD.json['time'] - parseInt(data['elapsed'])), MPD.json['state']);
    			}
                $('#total').html(formatSongTime(MPD.json['time']));
            });
		}
    });

    // Click on Queue item
    $('.playqueue, .cv-playqueue').on('click', '.playqueue-entry', function(e) {
        if (GLOBAL.pqActionClicked) {
            GLOBAL.pqActionClicked = false;
            return;
        }

        var selector = $(this).parent().hasClass('playqueue') ? '.playqueue' : '.cv-playqueue';
        var pos = $(selector + ' .playqueue-entry').index(this);

        sendMpdCmd('play ' + pos);
        $(this).parent().addClass('active');
    });

	// Click on Queue action menu button (ellipsis)
    $('.playqueue').on('click', '.playqueue-action', function(e) {
        GLOBAL.pqActionClicked = true;

		// Store posn for later use by action menu selection
        UI.dbEntry[0] = $('.playqueue .playqueue-action').index(this);
		// Store clock radio play name in UI.dbEntry[5]
		if ($('#pq-' + (UI.dbEntry[0] + 1) + ' .pll2').html().substr(0, 2) == '<i') { // Has icon (fa-microphone)
			// Radio station
			var line2 = $('#pq-' + (UI.dbEntry[0] + 1) + ' .pll2').html();
			var station = line2.substr((line2.indexOf('</i>') + 4));
			UI.dbEntry[5] = station.trim();
		}
		else {
			// Song file
			var title = $('#pq-' + (UI.dbEntry[0] + 1) + ' .pll1').html().trim();
            var artist = $('#pq-' + (UI.dbEntry[0] + 1) + ' .pll2').text();
			UI.dbEntry[5] = title + ' - ' + artist;
		}
    });

	// Save Queue to playlist
    $('#btn-save-queue-to-playlist').click(function(e){
		var plName = $('#playlist-save-name').val();

		if (plName) {
			if (containsBaseFolderName(plName)) {
				notify(NOTIFY_TITLE_ALERT, 'playlist_name_error', 'NAS, RADIO, SDCARD and USB cannot be used in the name.');
			} else {
                notify(NOTIFY_TITLE_INFO, 'saving_queue', NOTIFY_DURATION_SHORT);
                $.get('command/playlist.php?cmd=save_queue_to_playlist&name=' + plName, function() {
    				notify(NOTIFY_TITLE_INFO, 'queue_saved', NOTIFY_DURATION_SHORT);
                    $('#btn-pl-refresh').click();
                });
			}
		} else {
			notify(NOTIFY_TITLE_ALERT, 'playlist_name_needed');
		}
    });
    $('#save-queue-to-playlist-modal').on('shown.bs.modal', function(e) {
        setTimeout(function() {
            $('#playlist-save-name').focus();
        }, DEFAULT_TIMEOUT);
    });

	// Set favorites
    $('#btn-set-favorites-name').click(function(e){
		var favoritesName = $('#playlist-favorites-name').val();

		if (favoritesName) {
			if (containsBaseFolderName(favoritesName)) {
				notify(NOTIFY_TITLE_ALERT, 'playlist_name_error', 'NAS, RADIO, SDCARD and USB cannot be used in the name.');
			} else {
                notify(NOTIFY_TITLE_INFO, 'setting_favorites_name', NOTIFY_DURATION_SHORT);
                $.get('command/playlist.php?cmd=set_favorites_name&name=' + favoritesName, function(){
                    notify(NOTIFY_TITLE_INFO, 'favorites_name_set', NOTIFY_DURATION_SHORT);
                });
			}
		} else {
			notify(NOTIFY_TITLE_ALERT, 'playlist_name_needed');
		}
    });
    $('#set-favorites-playlist-modal').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('#playlist-favorites-name').focus();
        }, DEFAULT_TIMEOUT);
    });
	// Add item to favorites
    $('.add-item-to-favorites').click(function(e){
		// Pulse the btn
        $('.add-item-to-favorites i').addClass('pulse').addClass('fas');
		setTimeout(function() {
			$('.add-item-to-favorites i').removeClass('pulse').removeClass('fas');
		}, 3000);

		// Add current pl item to favorites playlist
		if (MPD.json['file'] != null) {
            notify(NOTIFY_TITLE_INFO, 'adding_favorite', NOTIFY_DURATION_SHORT);
            $.get('command/playlist.php?cmd=add_item_to_favorites&item=' + encodeURIComponent(MPD.json['file']), function() {
                notify(NOTIFY_TITLE_INFO, 'favorite_added', NOTIFY_DURATION_SHORT);
            });
		} else {
			notify(NOTIFY_TITLE_ALERT, 'no_favorite_to_add');
		}
    });

    // Click on title in Playback or CoverView
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
                currentView = 'playback,radio';
    			$('#playback-switch').click();

                if (!$('.radio-view-btn').hasClass('active')) {
    				$('.radio-view-btn').click();
    			}

                var rvHeaderCount = getRVHeaderCount(); // NOTE: Also updates UI.radioPos
                storeRadioPos(UI.radioPos);

                setTimeout(function() {
                    customScroll('radio', UI.radioPos, 200);
                    $('.database-radio li').removeClass('active');
                    $('#ra-' + (UI.radioPos - rvHeaderCount + 1).toString()).addClass('active');
                    UI.dbEntry[3] = 'ra-' + (UI.radioPos - rvHeaderCount + 1).toString();
                }, DEFAULT_TIMEOUT);
    		} else {
                // Song file
    			$('#playback-switch').click();
    			$('.tag-view-btn').click();

                var artistText = $(this).text().indexOf('...') != -1 ? $(this).text().slice(0, -3) : $(this).text();
				$('#artistsList .lib-entry').filter(function() {return $(this).text() == artistText/*MPD.json['artist']*/;}).click();
                customScroll('artists', UI.libPos[2], 200);

                $('#albumsList .lib-entry .album-name-art').filter(function() {return $(this).text() == MPD.json['album'];}).click();
                customScroll('albums', UI.libPos[0], 200);
    		}
        }
	});

    //
    // FOLDER VIEW
    //
	// Folder view item click
	$('.database').on('click', '.db-browse', function(e) {
        //console.log('Folder item click');
	    if ($(this).hasClass('db-folder') || $(this).hasClass('db-savedplaylist')) {
            UI.dbEntry[3] = $(this).parent().attr('id');
			UI.dbPos[UI.dbPos[10]] = $(this).parent().attr('id').replace('db-','');
			++UI.dbPos[10];

            var path = $(this).parent().data('path');
            if ($(this).hasClass('db-folder')) {
                $.getJSON('command/music-library.php?cmd=lsinfo', {'path': path}, function(data) {
                    UI.dbCmd = 'lsinfo';
                    renderFolderView(data, path);
                });
            } else {
                $.getJSON('command/playlist.php?cmd=get_pl_items_fv', {'path': path}, function(data) {
                    UI.dbCmd = 'get_pl_items_fv';
                    renderFolderView(data, path);
                });
            }
		}
	});
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
				path = path.slice(0,cutpos);
			}
			else {
				path = '';
			}
            $.getJSON('command/music-library.php?cmd=lsinfo', {'path': path}, function(data) {
                renderFolderView(data, path);
            });

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
        $.getJSON('command/music-library.php?cmd=lsinfo', {'path': ''}, function(data) {
            renderFolderView(data, '');
        });
	});
	$('#db-refresh').click(function(e) {
        UI.dbPos[UI.dbPos[10]] = 0;
		$.getJSON('command/music-library.php?cmd=lsinfo', {'path': UI.path}, function(data) {
			renderFolderView(data, UI.path);
        });
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
	        sendQueueCmd('add_group', dbFilterResults);
	        notify(NOTIFY_TITLE_INFO, $(this).data('cmd'), NOTIFY_DURATION_SHORT);
		}
	    if ($(this).data('cmd') == 'play_group') {
	        sendQueueCmd('play_group', dbFilterResults);
		}
	    if ($(this).data('cmd') == 'clear_play_group') {
	        sendQueueCmd('clear_play_group', dbFilterResults);
		}
	});

    //
    // RADIO VIEW
    //
    // Refresh the station list
	$('#btn-ra-refresh').click(function(e) {
        renderRadioView();
        lazyLode('radio');
        $('#database-radio').scrollTo(0, 200);
		UI.radioPos = -1;
		storeRadioPos(UI.radioPos);
        $("#btn-ra-search-reset").hide();
        showSearchResetRa = false;
	});
	// New station modal (+)
	$('#btn-ra-new').click(function(e) {
		$('#new-station-name').val('New station');
		$('#new-station-url').val('http://');
        $('#new-logoimage').val('');
		$('#preview-new-logoimage').html('');
        $('#info-toggle-new-logoimage').css('margin-left','unset');
        $('#new-station-tags').css('margin-top', '0');
        $('#new-station-type span').text('Regular');
        $('#new-station-genre').val('');
        $('#new-station-broadcaster').val('');
        $('#new-station-home-page').val('');
        $('#new-station-language').val('');
        $('#new-station-country').val('');
        $('#new-station-region').val('');
        $('#new-station-bitrate').val('');
        $('#new-station-format').val('');
        $('#new-station-geo-fenced span').text('No');
        $('#new-station-mpd-monitor span').text('No');

		$('#new-station-modal').modal();
	});
    $('#new-station-modal').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('#new-station-name').focus();
        }, DEFAULT_TIMEOUT);
    });
	// Radio search
	$('#ra-filter').keyup(function(e){
		if (!showSearchResetRa) {
			$('#btn-ra-search-reset').show();
			showSearchResetRa = true;
		}

		clearTimeout(searchTimer);

		var selector = this;
		searchTimer = setTimeout(function(){
			var filter = $(selector).val().trim();
			var count = 0;

			if (filter == '') {
				$("#btn-ra-search-reset").hide();
				showSearchResetRa = false;
			}

			$('.database-radio li').each(function(){
				if ($(this).text().search(new RegExp(filter, 'i')) < 0) {
					$(this).hide();
				} else {
					$(this).show();
					count++;
				}
			});

		    var s = (count == 1) ? '' : 's';
            lazyLode('radio');
            $('#database-radio').scrollTo(0, 200);

		}, SEARCH_TIMEOUT);
	});
	$('#btn-ra-search-reset').click(function(e) {
		$('.database-radio li').css('display', 'inline-block');
        $("#btn-ra-search-reset").hide();
		showSearchResetRa = false;
	});
    // Radio view context menu
	$('.database-radio').on('click', '.cover-menu', function(e) {
        var pos = $(this).parents('li').index();
        var path = $(this).parents('li').data('path');
        // DEBUG:
        //console.log('click .cover-menu: pos|path: ' + pos + '|' + path);

        UI.dbEntry[0] = path;
        UI.radioPos = pos;
		storeRadioPos(UI.radioPos)

        $('#' + UI.dbEntry[3]).removeClass('active');
        UI.dbEntry[3] = $(this).parents('li').attr('id');
        $(this).parents('li').addClass('active');
	});
    // Create new station
    $('#btn-create-station').click(function(e){
		if ($('#new-station-name').val().trim() == '' || $('#new-station-url').val().trim() == '') {
			notify(NOTIFY_TITLE_ALERT, 'blank_entries', 'Station not created.');
		} else {
            var path = {
                'name': $('#new-station-name').val().trim(),
                'url': $('#new-station-url').val().trim(),
                'type': getKeyOrValue('value', $('#new-station-type span').text()),
                'logo': 'local',
                'genre': $('#new-station-genre').val().trim(),
                'broadcaster': $('#new-station-broadcaster').val().trim(),
                'language': $('#new-station-language').val().trim(),
                'country': $('#new-station-country').val().trim(),
                'region': $('#new-station-region').val().trim(),
                'bitrate': $('#new-station-bitrate').val().trim(),
                'format': $('#new-station-format').val().trim(),
                'geo_fenced': $('#new-station-geo-fenced span').text(),
                'home_page': $('#new-station-home-page').val().trim(),
                'monitor': $('#new-station-mpd-monitor span').text()
            };
            notify(NOTIFY_TITLE_INFO, 'creating_station');
            $.post('command/radio.php?cmd=new_station', {'path': path}, function(msg) {
                if (msg == 'OK') {
                    RADIO.json[path['url']] = {'name': path['name'], 'type': path['type'], 'logo': path['logo'],
                    'bitrate': path['bitrate'], 'format': path['format'], 'home_page': path['home_page'],
                    'monitor': path['monitor']};
                    notify(NOTIFY_TITLE_INFO, 'new_station', NOTIFY_DURATION_SHORT);
                } else {
                    notify(NOTIFY_TITLE_ALERT, 'validation_check', msg + '.', NOTIFY_DURATION_MEDIUM);
                }
                $('#btn-ra-refresh').click();
            }, 'json');
		}
	});
    // Update station
	$('#btn-update-station').click(function(e){
		if ($('#edit-station-name').val().trim() == '' || $('#edit-station-url').val().trim() == '') {
			notify(NOTIFY_TITLE_ALERT, 'blank_entries', 'Station not updated.');
		} else {
            var path = {
                'id': GLOBAL.editStationId,
                'name': $('#edit-station-name').val().trim(),
                'url': $('#edit-station-url').val().trim(),
                'type': getKeyOrValue('value', $('#edit-station-type span').text()),
                'logo': 'local',
                'genre': $('#edit-station-genre').val().trim(),
                'broadcaster': $('#edit-station-broadcaster').val().trim(),
                'language': $('#edit-station-language').val().trim(),
                'country': $('#edit-station-country').val().trim(),
                'region': $('#edit-station-region').val().trim(),
                'bitrate': $('#edit-station-bitrate').val().trim(),
                'format': $('#edit-station-format').val().trim(),
                'geo_fenced': $('#edit-station-geo-fenced span').text(),
                'home_page': $('#edit-station-home-page').val().trim(),
                'monitor': $('#edit-station-mpd-monitor span').text()
            };
            notify(NOTIFY_TITLE_INFO, 'updating_station');
            $.post('command/radio.php?cmd=upd_station', {'path': path}, function(msg) {
                if (msg == 'OK') {
                    RADIO.json[path['url']] = {'name': path['name'], 'type': path['type'], 'logo': path['logo'],
                    'bitrate': path['bitrate'], 'format': path['format'], 'home_page': path['home_page'],
                    'monitor': path['monitor']};
                    notify(NOTIFY_TITLE_INFO, 'upd_station', NOTIFY_DURATION_SHORT);
                } else {
                    notify(NOTIFY_TITLE_ALERT, 'validation_check', msg + '.');
                }
                $('#btn-ra-refresh').click();
            }, 'json');
		}
	});
    // Delete station
	$('#btn-del-station').click(function(e){
        var stationName = UI.dbEntry[0].slice(0,UI.dbEntry[0].lastIndexOf('.')).substr(6); // Trim RADIO/ and .pls
        deleteRadioStationObject(stationName);
        $.post('command/radio.php?cmd=del_station', {'path': UI.dbEntry[0]}, function() {
            notify(NOTIFY_TITLE_INFO, 'del_station', NOTIFY_DURATION_SHORT);
            UI.radioPos = -1;
    		storeRadioPos(UI.radioPos);
            renderRadioView();
            lazyLode('radio');
            $('.busy-spinner').hide();
        });
	});

    //
    // PLAYLIST VIEW
    //
    // Refresh the playlist list
	$('#btn-pl-refresh').click(function(e) {
        renderPlaylistView();
        lazyLode('playlist');
        $('#database-playlist').scrollTo(0, 200);
		UI.playlsitPos = -1;
		storePlaylistPos(UI.playlistPos)
        $('#btn-pl-search-reset').hide();
        showSearchResetPl = false;
	});
    // New playlist modal (+)
	$('#btn-pl-new').click(function(e) {
		$('#new-playlist-name').val('New playlist');
        $('#new-plcoverimage').val('');
		$('#preview-new-plcoverimage').html('');
        $('#info-toggle-new-plcoverimage').css('margin-left','unset');
        $('#new-playlist-tags').css('margin-top', '2.5em');
        $('#new-playlist-genre').val('');
		$('#new-playlist-modal').modal();
	});
    $('#new-playlist-modal').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('#new-playlist-name').focus();
        }, DEFAULT_TIMEOUT);
    });
	// Playlist search
	$('#pl-filter').keyup(function(e){
		if (!showSearchResetPl) {
			$('#btn-pl-search-reset').show();
			showSearchResetPl = true;
		}

		clearTimeout(searchTimer);

		var selector = this;
		searchTimer = setTimeout(function(){
			var filter = $(selector).val().trim();
			var count = 0;

			if (filter == '') {
				$('#btn-pl-search-reset').hide();
				showSearchResetPl = false;
			}

			$('.database-playlist li').each(function(){
				if ($(this).text().search(new RegExp(filter, 'i')) < 0) {
					$(this).hide();
				} else {
					$(this).show();
					count++;
				}
			});

		    var s = (count == 1) ? '' : 's';
            lazyLode('playlist');
            $('#database-playlist').scrollTo(0, 200);

		}, SEARCH_TIMEOUT);
	});
	$('#btn-pl-search-reset').click(function(e) {
		$('.database-playlist li').css('display', 'inline-block');
        $("#btn-pl-search-reset").hide();
		showSearchResetPl = false;
	});
    // Playlist view context menu
	$('.database-playlist').on('click', '.cover-menu', function(e) {
        var pos = $(this).parents('li').index();
        var path = $(this).parents('li').data('path');

        UI.dbEntry[0] = path;
        UI.playlistPos = pos;
		storePlaylistPos(UI.playlistPos)

        $('#' + UI.dbEntry[3]).removeClass('active');
        UI.dbEntry[3] = $(this).parents('li').attr('id');
        $(this).parents('li').addClass('active');
	});
    // Create new playlist
    $('#btn-create-playlist').click(function(e){
		if ($('#new-playlist-name').val().trim() == '') {
			notify(NOTIFY_TITLE_ALERT, 'blank_entries', 'Playlist not created.');
		} else {
            var path = {'name': $('#new-playlist-name').val().trim(), 'genre': $('#new-playlist-genre').val().trim()};
            notify(NOTIFY_TITLE_INFO, 'creating_playlist');
            $.post('command/playlist.php?cmd=new_playlist', {'path': path}, function() {
                notify(NOTIFY_TITLE_INFO, 'new_playlist', NOTIFY_DURATION_SHORT);
                $('#btn-pl-refresh').click();
            }, 'json');
		}
	});
    // Update playlist
	$('#btn-update-playlist').click(function(e){
		if ($('#edit-playlist-name').val().trim() == '') {
			notify(NOTIFY_TITLE_ALERT, 'blank_entries', 'Playlist not updated.');
		} else {
            var items = [];
            $('#playlist-items li').each(function() {
                items.push($(this).data('path'));
            });
            var path = {
                'name': $('#edit-playlist-name').val().trim(),
                'genre': $('#edit-playlist-genre').val().trim(),
                'items': items
            };
            notify(NOTIFY_TITLE_INFO, 'updating_playlist');
            $.post('command/playlist.php?cmd=upd_playlist', {'path': path}, function() {
                notify(NOTIFY_TITLE_INFO, 'upd_playlist', NOTIFY_DURATION_SHORT);
            }, 'json');
		}
	});
    // Delete playlist
	$('#btn-del-playlist').click(function(e){
        $.post('command/playlist.php?cmd=del_playlist', {'path': UI.dbEntry[0]}, function() {
            notify(NOTIFY_TITLE_INFO, 'del_playlist', NOTIFY_DURATION_SHORT);
            $('#btn-pl-refresh').click();
        });
	});
    // Delete/Move playlist items(s)
    $('#btn-delete-plitem, #btn-move-plitem').click(function(e){
        $('#pl-item-' + (UI.dbEntry[0] + 1)).removeClass('active');

        if ($(this).attr('id') == 'btn-delete-plitem') {
            var beg_pos = $('#delete-playlist-item-begpos').val() - 1;
            var end_pos = $('#delete-playlist-item-endpos').val() - 1;
        } else {
            var beg_pos = $('#move-playlist-item-begpos').val() - 1;
            var end_pos = $('#move-playlist-item-endpos').val() - 1;
            var new_pos = $('#move-playlist-item-newpos').val() - 1;
        }
        var num_items = end_pos - beg_pos + 1;

        // Convert lines to array
        var items = [];
        $('#playlist-items li').each(function(){
            items.push($(this).prop('outerHTML'));
        });

        if ($(this).attr('id') == 'btn-delete-plitem') {
            // Delete array items
            items.splice(beg_pos, num_items);
        } else {
            // Move array items
            var moved;
            items.splice.apply(items, [new_pos, 0].concat(moved = items.splice(beg_pos, num_items)));
        }

        // Convert back to lines
        var element = document.getElementById('playlist-items');
        element.innerHTML = '';
        var lines = '';
        for (i = 0; i < items.length; i++) {
            lines += items[i];
        }
        element.innerHTML = lines;

        // For max= attribute in the Delete/Move <input> elements
        UI.dbEntry[4] = items.length;

        // Resequence id's
        var i = 1;
        $('#playlist-items li').each(function(){
            $(this).attr('id', 'pl-item-' + i.toString());
            i++;
        });

        $('#delete-playlist-item, #move-playlist-item').hide();
        $('#playlist-items').css('margin-top', '0');
	});

    //
    // MISCELLANEOUS
    //
    // Saved Searches (Tag/Album view)
    $('#saved-search-modal').on('shown.bs.modal', function(e) {
        setTimeout(function() {
            $('#saved-search-items li').removeClass('active');
            updateSavedSearchModal();
        }, DEFAULT_TIMEOUT);
	});
    // Click Subset item
    $('#saved-search-items').on('click', '.saved-search-item', function(e) {
        // Store item index for use in context menu
        UI.dbEntry[0] = $('#saved-search-items .saved-search-item').index(this);
        // Don't display context menu for active item since it can't be deleted
        if ($('#saved-search-item-' + (UI.dbEntry[0] + 1).toString()).data('name') == SESSION.json['lib_active_search']) {
            return false;
        } else {
            // Hide delete option for LIB_FULL_LIBRARY which will always be item 0
            UI.dbEntry[0] == 0 ? $('#menu-item-delete-saved-search').hide() : $('#menu-item-delete-saved-search').show();
        	$('#saved-search-items li').removeClass('active');
            $('#saved-search-item-' + (UI.dbEntry[0] + 1).toString()).addClass('active');
        }
    });
    // Activate or Delete saved search
    $('#context-menu-saved-search-contents a').click(function(e) {
        var cmd = $(this).data('cmd');
        var name = $('#saved-search-item-' + (UI.dbEntry[0] + 1).toString()).data('name');
        if (cmd == 'activate_saved_search') {
            SESSION.json['lib_active_search'] = name;
            $.post('command/music-library.php?cmd=' + cmd, {'name': name}, function(data) {
                applyLibFilter(data['filter_type'], data['filter_str'])
                updateSavedSearchModal();
            }, 'json');
        } else {
            // Delete
            $.post('command/music-library.php?cmd=' + cmd, {'name': name}, function() {
                updateSavedSearchModal();
            });
        }
	});
    // Save search
    $('#btn-save-search').click(function(e) {
        var name = $('#saved-search-name').val().trim();
        if (name == '') {
            notify(NOTIFY_TITLE_ALERT, 'search_name_blank');
        } else {
            e.stopImmediatePropagation();
            $.post('command/music-library.php?cmd=create_saved_search', {'name': name});
            // Allow worker job to complete
            setTimeout(function() {
    			updateSavedSearchModal();
    		}, 2000);

        }
    });
    // Update Saved Search items
    function updateSavedSearchModal() {
        // Name input and current search criteria
        $('#saved-search-name').val('');
        $('#lib-search-criteria').text(SESSION.json['library_flatlist_filter']
            + (SESSION.json['library_flatlist_filter_str'] == '' ? '' : ': ' + SESSION.json['library_flatlist_filter_str']));
        // Subset list
        $.post('command/music-library.php?cmd=get_saved_searches', function(data) {
            var element = document.getElementById('saved-search-items');
            element.innerHTML = '';
            if (data.length > 0) {
                var output = '';
                for (i = 0; i < data.length; i++) {
                    var active = SESSION.json['lib_active_search'] == data[i]['name'] ? '<span id="saved-search-item-check" style="float:right;"><i class="fa-solid fa-sharp fa-check"></i></span>' : '';
                    output += '<li id="saved-search-item-' + (i + 1)
                        + '" class="saved-search-item" data-toggle="context" data-target="#context-menu-saved-search-contents" '
                        + 'data-name="' + data[i]['name'] + '">';
                    output += '<span class="saved-search-item-line1">' + data[i]['name'] + active + '</span>';
                    output += '<span class="saved-search-item-line2">' + data[i]['filter'] + '</span>';
                    output += '</li>';
                }
            } else {
                // Error: No saved searches found (item 1 LIB_FULL_LIBRARY should always exist
            }
            element.innerHTML = output;
        }, 'json');
    }
    // Clear active search
    function clearActiveSearch() {
        SESSION.json['lib_active_search'] = 'None';
        $.post('command/music-library.php?cmd=clear_active_search');
    }

    // Search operators
    // NOTE: Add 'starts_with' operator when bump to MPD 0.24
    function setSearchStr(str) {
        str = str.trim();
        if (($.inArray(str.slice(0, 2), GLOBAL.searchOperators) != -1) && str.slice(2, 3) == ' ') {
            str = str.slice(0, 3) + "'" +  str.slice(3) + "'";
        } else {
            str = "contains '" + str + "'";
        }
        return str;
    }

    // Advanced search (Folder/Tag/Album views)
	$('#db-search-submit').click(function(e) {
        var searchType = '';
		var searchStr = '';

        if ($('#dbsearch-predefined-filters').val() != '') {
            // NOTE: This input field is hidden in Folder view because that view does not support predefined filters
            searchType = $('#dbsearch-predefined-filters').val().trim().toLowerCase();
            searchStr = '';
        } else if ($('#dbsearch-alltags').val() != '') {
            searchType = 'any';
            searchStr = currentView == 'folder' ? $('#dbsearch-alltags').val().trim() : '(any ' + setSearchStr($('#dbsearch-alltags').val()) + ')';
		} else {
            searchType = 'specific'; // NOTE: This searchType is for Folder view only
            GLOBAL.searchTags.forEach(function(tag) {
                searchStr += $('#dbsearch-' + tag).val() == '' ? '' : ' AND (' + tag + ' ' + setSearchStr($('#dbsearch-' + tag).val()) + ')';
            });

			if (searchStr != '') {
                searchStr = searchStr.slice(5);
			} else {
                searchType = '';
            }
		}

        if (searchType == '' && searchStr == '') {
            notify(NOTIFY_TITLE_ALERT, 'search_fields_empty', 'Search not performed.');
        } else {
            if (currentView == 'folder') {
                clearActiveSearch();
                // NOTE: searchType will be 'any' or 'specific'
                $.getJSON('command/music-library.php?cmd=search' + '&tagname=' + searchType, {'query': searchStr}, function(data) {
                    renderFolderView(data, '', searchStr);
                });
            } else if (currentView == 'tag' || currentView == 'album') {
                if (searchType == 'any' || searchType == 'specific') {
                    // Search by tags
                    clearActiveSearch();
                    applyLibFilter('tags', searchStr);
                } else {
                    // Search by predefined filter
                    var parts = splitStringAtFirstSpace(searchType);
                    if (parts.length == 2) { // Two arg filter
                        searchType = parts[0];
                        searchStr = parts[1];
                    }
                    if (GLOBAL.allFilters.includes(searchType)) {
                        clearActiveSearch();
                        applyLibFilter(searchType, searchStr);
                    } else {
                        notify(NOTIFY_TITLE_ALERT, 'predefined_filter_invalid', 'Search not performed');
                    }
                }
            }
        }
	});
    $('#db-search-reset').click(function(e) {
        var specificTags = '';
        GLOBAL.searchTags.forEach(function(tag) {
            specificTags += '#dbsearch-' + tag + ',';
        });
        specificTags = specificTags.slice(0, -1);
        $('#dbsearch-predefined-filters, #dbsearch-alltags,' + specificTags).val('');
	});
	$('#dbsearch-modal').on('shown.bs.modal', function(e) {
        setTimeout(function() {
            currentView == 'folder' ? $('#predefined-filters-div').hide() : $('#predefined-filters-div').show();
    		$('#db-search-results').css('font-weight', 'normal');
    		$('.database li').removeClass('active');
        }, DEFAULT_TIMEOUT);
	});

    // Library Tag/Album search
    // NOTE: The keydown event was added to work around an issue where Firefox steals the Enter key and keyup never happens.
    $('#lib-album-filter').on('keydown keyup', function(e){
        //console.log(e);
        if (e.type == 'keyup') {
            e.preventDefault();
        }

        $('#lib-album-filter').val().length > 0 ? $('#searchResetLib').show() : $('#searchResetLib').hide();

        if (e.key == 'Enter' && $('#lib-album-filter').val().length > 0) {
            clearActiveSearch();
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
                } else {
                    applyLibFilter(filter[0]);
                }
            } else {
                // Default to filterType = any
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

    // Queue search
	$('#playqueue-filter').keyup(function(e){
		if (!showSearchResetPq) {
			$('#searchResetPlayqueue').show();
			showSearchResetPq = true;
		}

		clearTimeout(searchTimer);

		var selector = this;
		searchTimer = setTimeout(function(){
			var filter = $(selector).val().trim();
			var count = 0;

			if (filter == '') {
				$("#searchResetPlayqueue").hide();
				showSearchResetPq = false;
			}

			$('.playqueue li').each(function(){
				if ($(this).text().search(new RegExp(filter, 'i')) < 0) {
					$(this).hide();
				}
				else {
					$(this).show();
					count++;
				}
			});
		    var s = (count == 1) ? '' : 's';
			$('#container-playqueue').scrollTo(0, 200);
		}, SEARCH_TIMEOUT);
	});
	$('#searchResetPlayqueue').click(function(e) {
		$("#searchResetPlayqueue").hide();
		showSearchResetPq = false;
		$('.playqueue li').css('display', 'block');
	});

	// Playback history search
	$('#ph-filter').keyup(function(e){
		if (!showSearchResetPh) {
			$('#search-reset-ph').show();
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
				} else {
					$(this).show();
					count++;
				}
			});
			var s = (count == 1) ? '' : 's';
			if (filter != '') {
				$('#ph-filter-results').html((+count) + '&nbsp;item' + s);
                $('#ph-filter-results').show();
			} else {
				$('#ph-filter-results').hide();
			}
			$('#container-playhistory').scrollTo(0, 200);
		}, SEARCH_TIMEOUT);
	});
	$('#search-reset-ph').click(function(e) {
		$("#search-reset-ph").hide();
		showSearchResetPh = false;
		$('.playhistory li').css('display', 'list-item');
		$('#ph-filter-results').hide();
        $('#ph-filter').val('');
	});

	// Buttons on modals
	$('.btn-delete-playqueue-item').click(function(e){
		var begPos = $('#delete-playqueue-item-begpos').val() - 1;
		var endPos = $('#delete-playqueue-item-endpos').val() - 1;

		// NOTE: format for single or multiple, endPos not inclusive so must be bumped for multiple
		if (begPos == endPos) {
            var cmd = 'delete_playqueue_item&range=' + begPos;
        } else {
            var cmd = 'delete_playqueue_item&range=' + begPos + ':' + (endPos + 1);
        }

        $.post('command/queue.php?cmd=' + cmd);
        notify(NOTIFY_TITLE_INFO, 'queue_item_removed', NOTIFY_DURATION_SHORT);
	});
	// Speed btns on delete modal
	$('#btn-delete-setpos-top').click(function(e){
		$('#delete-playqueue-item-begpos').val(1);
		return false;
	});
	$('#btn-delete-setpos-bot').click(function(e){
		$('#delete-playqueue-item-endpos').val(GLOBAL.playQueueLength);
		return false;
	});
	$('.btn-move-playqueue-item').click(function(e){
		var begPos = $('#move-playqueue-item-begpos').val() - 1;
		var endPos = $('#move-playqueue-item-endpos').val() - 1;
        var newPos = $('#move-playqueue-item-newpos').val() - 1;
        var maxPos = $('#move-playqueue-item-newpos').attr('max') - 1;
		if (begPos == endPos) {
            var cmd = 'move_playqueue_item&range=' + begPos + '&newpos=' + newPos;
        } else {
            // The endPos item is not included in range so must be bumped by 1
            // When newPos = maxPos bump back by endPos - begPos
            newPos = newPos != maxPos ? newPos : newPos - (endPos - begPos);
            var cmd = 'move_playqueue_item&range=' + begPos + ':' + (endPos + 1) + '&newpos=' + newPos;
        }

        $.post('command/queue.php?cmd=' + cmd);
        notify(NOTIFY_TITLE_INFO, 'queue_item_moved', NOTIFY_DURATION_SHORT);
	});
	// Speed btns on move modal
	$('#btn-move-setpos-top').click(function(e){
		$('#move-playqueue-item-begpos').val(1);
		return false;
	});
	$('#btn-move-setpos-bot').click(function(e){
		$('#move-playqueue-item-endpos').val(GLOBAL.playQueueLength);
		return false;
	});
	$('#btn-move-setnewpos-top').click(function(e){
		$('#move-playqueue-item-newpos').val(1);
		return false;
	});
	$('#btn-move-setnewpos-bot').click(function(e){
		$('#move-playqueue-item-newpos').val(GLOBAL.playQueueLength);
		return false;
	});

	// Speed buttons on playback history log
	$('#ph-first-page').click(function(e){
		$('#container-playhistory').scrollTo(0 , 200);
	});
	$('#ph-last-page').click(function(e){
		$('#container-playhistory').scrollTo('100%', 200);
	});

	// Playbar coverview btn
	$('.coverview').on('click', function(e) {
		e.stopImmediatePropagation();
		screenSaver('1');
	});

    // Coverview playlist
	$('#cv-playqueue-btn').on('click', function(e) {
        $('#cv-playqueue').toggle();

        if ($('#cv-playqueue').css('display') == 'block') {
            $('#cv-playqueue ul').html($('#playqueue ul').html());
            if (SESSION.json['playlist_art'] == 'Yes') {
                lazyLode('cv-playqueue');
            }
            customScroll('cv-playqueue', parseInt(MPD.json['song']));

            GLOBAL.cvQueueTimer = setTimeout(function() {
                $('#cv-playqueue ul').html('');
                $('#cv-playqueue').hide();
            }, CV_QUEUE_TIMEOUT);
        }
        else {
            e.preventDefault();
            $('#cv-playqueue ul').html('');
            window.clearTimeout(GLOBAL.cvQueueTimer);
        }
	});

	// Disconnect renderer
    $(document).on('click', '.disconnect-renderer', function(e) {
		notify(NOTIFY_TITLE_INFO, 'renderer_disconnect');
        $.post('command/renderer.php?cmd=disconnect_renderer', {'job': $(this).data('job')});
	});
    $(document).on('click', '.disconnect-airplay', function(e) {
		notify(NOTIFY_TITLE_INFO, 'renderer_disconnect');
        $.post('command/renderer.php?cmd=disconnect_renderer', {'job': $(this).data('job')});
	});
    $(document).on('click', '.disconnect-deezer', function(e) {
		notify(NOTIFY_TITLE_INFO, 'renderer_disconnect');
        $.post('command/renderer.php?cmd=disconnect_renderer', {'job': $(this).data('job')});
	});
    $(document).on('click', '.disconnect-spotify', function(e) {
		notify(NOTIFY_TITLE_INFO, 'renderer_disconnect');
        $.post('command/renderer.php?cmd=disconnect_renderer', {'job': $(this).data('job')});
	});
    // Turn off renderer
    $(document).on('click', '.turnoff-renderer', function(e) {
		notify(NOTIFY_TITLE_INFO, 'renderer_turnoff');
        $.post('command/renderer.php?cmd=disconnect_renderer', {'job': $(this).data('job')});
	});
    // Turn off multiroom receiver
    $(document).on('click', '.turnoff-receiver', function(e) {
		notify(NOTIFY_TITLE_INFO, 'trx_turning_receiver_off');
        $.post('command/renderer.php?cmd=disconnect_renderer', {'job': $(this).data('job')});
	});

    // First use help
    $('#playback-firstuse-help').click(function(e) {
        $('#playback-firstuse-help').css('display', '');
        var firstUseHelp = SESSION.json['first_use_help'].split(',');
        SESSION.json['first_use_help'] = 'n,' + firstUseHelp[1] + ',' + firstUseHelp[2];
        $.post('command/cfg-table.php?cmd=upd_cfg_system', {'first_use_help': SESSION.json['first_use_help']});
    });
    $('#playbar-firstuse-help').click(function(e) {
        $('#playbar-firstuse-help').css('display', '');
        var firstUseHelp = SESSION.json['first_use_help'].split(',');
        SESSION.json['first_use_help'] = firstUseHelp[0] + ',n,' + firstUseHelp[2];
        $.post('command/cfg-table.php?cmd=upd_cfg_system', {'first_use_help': SESSION.json['first_use_help']});
    });
    $(document).on('click', '#welcome-firstuse-help', function(e) {
        $('.ui-pnotify-closer').click();
        var firstUseHelp = SESSION.json['first_use_help'].split(',');
        SESSION.json['first_use_help'] = firstUseHelp[0] + ',' + firstUseHelp[1] + ',n';
        $.post('command/cfg-table.php?cmd=upd_cfg_system', {'first_use_help': SESSION.json['first_use_help']});
    });

    // Track info for Playback
    $('#extra-tags-display').click(function(e) {
        if ($('#currentsong').html() != '') {
            var cmd = MPD.json['artist'] == 'Radio station' ? 'station_info' : 'track_info';
            audioInfo(cmd, MPD.json['file']);
        }
    });

    // Audio info Track/Playback
    $(document).on('click', '#audioinfo-track', function(e) {
		$('#audioinfo-modal').removeClass('playback').addClass('track');
	});
    $(document).on('click', '#audioinfo-playback', function(e) {
		$('#audioinfo-modal').removeClass('track').addClass('playback');
	});

    // CoverView screen saver reset
    $('#screen-saver, #playback-panel, #library-panel, #folder-panel, #radio-panel, #playlist-panel, #panel-footer').click(function(e) {
        //console.log('reset_screen_saver: timeout (' + SESSION.json['scnsaver_timeout'] + ', currentView: ' + currentView + ')');
        if ($(this).attr('id') == 'panel-footer') {
            return;
        }

        if (GLOBAL.coverViewActive) {
			$('body').removeClass('cv');
            $('body').removeClass('cvwide');
            if (SESSION.json['show_cvpb'] == 'Yes') {
                $('body').removeClass('cvpb');
            }

            setColors();
            if (SESSION.json['scnsaver_mode'] == 'Digital clock' || SESSION.json['scnsaver_mode'] == 'Digital clock (24-hour)' ||
                SESSION.json['scnsaver_mode'].includes('Analog clock')) {
				hideSSClock();
            }

            // TEST: Fixes issue where some elements briefly remain on-screen when entering or returning from CoverView
            $('#cv-playqueue ul').html('');
            $('#cv-playqueue').hide();
            $('#lib-coverart-img').show();

            // TEST: Fixes Queue sometimes not being visible after returning from CoverView
            UI.mobile ? $('#playback-queue').css('width', '99.9%') : $('#playback-queue').css('width', '38.1%');
            setTimeout(function() {
                $('#playback-queue').css('width', ''); // TEST: Restore correct width to force Queue visible
            }, DEFAULT_TIMEOUT);
            if (SESSION.json['playlist_art'] == 'Yes') {
                lazyLode('playqueue');
            }
            customScroll('playqueue', parseInt(MPD.json['song']));
        }

        // Reset state
        GLOBAL.coverViewActive = false;
        if (SESSION.json['scnsaver_timeout'] != 'Never') {
            $.post('command/playback.php?cmd=reset_screen_saver');
        }
    });

    // Players >>
    $('#players-menu-item').click(function(e) {
        notify(NOTIFY_TITLE_INFO, 'discovering_players', 'Please wait.', NOTIFY_DURATION_INFINITE);
    });
    $('#players-modal').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('#btn-players-submit').text('Submit');
            $('#btn-players-submit').prop('disabled', false);
        }, DEFAULT_TIMEOUT);
	});
    $(document).on('click', '#btn-players-submit', function(e) {
        var command = $('#players-command span').text();
        var cmdValue = getKeyOrValue('value', command);
        var ipaddr = [];
        var host = [];
        $('#players-ul a').each(function() {
            host.push($(this).attr('data-host'));
            ipaddr.push($(this).attr('data-ipaddr'));
        });
        //console.log(ipaddr);
        if (command == 'Rediscover' || (ipaddr.length > 0 && command != 'No action')) {
            var msgText = $('#btn-players-submit').text();
            if (msgText == 'Submit') {
                $('#btn-players-submit').text('Confirm');
            } else {
                if (command == 'Rediscover') {
                    $('#players-submit-confirm-msg').html("<div class='busy-spinner-btn-players'>" + GLOBAL.busySpinnerSVG + "</div>");
                    $('#btn-players-submit').prop('disabled', true);
                    $('#players-modal .modal-body').load('players.php?cmd=' + cmdValue, {'ipaddr': ipaddr}, function() {
                        $('#players-submit-confirm-msg').text('');
                        $('#btn-players-submit').text('Submit');
                        $('#btn-players-submit').prop('disabled', false);
                    });
                } else {
                    $('#players-modal').modal('toggle');
                    notify(NOTIFY_TITLE_INFO, 'players_command_sumbitted', '<em>' + getKeyOrValue('key', cmdValue) + '</em> submitted');
                    $.post('players.php?cmd=' + cmdValue, {'ipaddr': ipaddr, 'host': host});
                }
            }
        }
    });

    // Multiroom Receiver control
    $(document).on('click', '.multiroom-modal-onoff', function(e) {
        var item = $(this).data('item');
        var onoff = $('#multiroom-rx-' + item + '-onoff').prop('checked') === true ? 'On' : 'Off';
        $.post('command/multiroom.php?cmd=set_rx_status', {'onoff': onoff, 'item': item}, function(data) {}, 'json');
    });
    $(document).on('click', '.multiroom-modal-vol', function(e) {
        var item = $(this).data('item');
        var volume = $('#multiroom-rx-' + item + '-vol').text();
        $.post('command/multiroom.php?cmd=set_rx_status', {'volume': volume, 'item': item}, function(data) {}, 'json');
        $('#multiroom-rx-' + item + '-vol').html("<div class='busy-spinner-btn-rx'>" + GLOBAL.busySpinnerSVG + "</div>");
        setTimeout(function() {
            $('#multiroom-rx-' + item + '-vol').text(volume);
        }, 1000);
    });
    $(document).on('click', '.multiroom-modal-mute', function(e) {
        var item = $(this).data('item');
        var iconClass = $('#multiroom-rx-' + item + '-mute i').hasClass('fa-volume-up') ? 'fa-volume-mute' : 'fa-volume-up';
        var mute = iconClass =='fa-volume-mute' ? 'Muted' : 'Unmuted';
        $('#multiroom-rx-' + item + '-mute').html('<i class="fa-solid fa-sharp ' + iconClass + '"></i>');
        $.post('command/multiroom.php?cmd=set_rx_status', {'mute': mute, 'item': item}, function(data) {}, 'json');
    });

    // Prevent click on the menu checkmark from causing default moOde cover to be displayed
    $(document).on('click',
        '#menu-check-cdsp, #menu-check-consume, #menu-check-repeat, #menu-check-single, #menu-check-recorder',
        function(e) {
             e.stopImmediatePropagation();
        }
    );

    // CamillaDSP config menu
    $('#dropdown-cdsp-btn').click(function(e) {
        var ul = $('#dropdown-cdsp-menu');

        // Checked item
        $('.dropdown-cdsp-line span').remove();
        $('#dropdown-cdsp-menu li').each(function () {
            var configName = SESSION.json['camilladsp'] == 'off' ? 'Off' : SESSION.json['camilladsp'].slice(0, -4);
            var selectedConfig = configName.charAt(0).toUpperCase() + configName.slice(1);
            if ($(this).text() == selectedConfig) {
                var selectedHTML = $('a[data-cdspconfig="' + SESSION.json['camilladsp'] + '"]').html();
                $('a[data-cdspconfig="' + SESSION.json['camilladsp'] + '"]').html(selectedHTML +
                    '<span id="menu-check-cdsp"><i class="fa-solid fa-sharp fa-check"></i></span>');
            }
        });

        // First items
        $('#dropdown-cdsp-menu li').each(function () {
            if ($(this).text() == 'Off' ||
                $(this).text() == 'Custom' ||
                $(this).text() == 'Quick convolution filter' ||
                $(this).html().includes('menu-check-cdsp')) {
                ul.append($(this));
            }
        });
        // Rest of items
        $('#dropdown-cdsp-menu li').each(function () {
            if ($(this).text() != 'Off' &&
                $(this).text() != 'Custom' &&
                $(this).text() != 'Quick convolution filter' &&
                !$(this).html().includes('menu-check-cdsp')) {
                ul.append($(this));
            }
        });

        $('#dropdown-cdsp-menu').scrollTo(0, 200);
    });

	// Info button (i) show/hide toggle
    $(document).on('click', '.info-toggle', function(e) {
		var spanId = '#' + $(this).data('cmd');
		if ($(spanId).hasClass('hide')) {
			$(spanId).removeClass('hide');
		}
		else {
			$(spanId).addClass('hide');
		}
	});
});
