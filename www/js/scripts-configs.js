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
jQuery(document).ready(function($){ 'use strict';
    // Call $.pnotify if created by backend
    if( window.ui_notify != undefined ) {
        ui_notify();
    }

    GLOBAL.scriptSection = 'configs';

	$('#config-tabs').css('display', 'flex');
	$('#menu-bottom').css('display', 'none');
    $('.dropdown-menu > li > a').css('color', 'var(--config-text-color)');

    // For ultra-wide screens
    if ($('.container').css('margin-right') != '0px') {
        $('#menu-top').css('margin-right', '25vw');
        $('#menu-top').css('margin-left', '25vw');
    }

	// Compensate for Android popup kbd changing the viewport, also for notch phones
	$("meta[name=viewport]").attr("content", "height=" + $(window).height() + ", width=" + $(window).width() + ", initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover");
	// store device pixel ratio
    $.post('command/cfg-table.php?cmd=upd_cfg_system', {'library_pixelratio': window.devicePixelRatio});

	// Load current cfg
    $.getJSON('command/cfg-table.php?cmd=get_cfg_tables_no_radio', function(data) {
    	SESSION.json = data['cfg_system'];
    	THEME.json = data['cfg_theme'];
        NETWORK.json = data['cfg_network'];

        $('#config-back').show();
    	UI.mobile = $(window).width() < 480 ? true : false; /* mobile-ish */
    	setFontSize();

    	var tempOp = themeOp;
    	if (themeOp == 0.74902) {tempOp = 0.1};

    	// set theme
    	themeColor = str2hex(THEME.json[SESSION.json['themename']]['tx_color']);
    	themeBack = 'rgba(' + THEME.json[SESSION.json['themename']]['bg_color'] + ',' + SESSION.json['alphablend'] +')';
    	themeMcolor = str2hex(THEME.json[SESSION.json['themename']]['tx_color']);
    	tempcolor = splitColor($('.dropdown-menu').css('background-color'));
    	themeOp = tempcolor[3];
    	themeMback = 'rgba(' + THEME.json[SESSION.json['themename']]['bg_color'] + ',' + themeOp +')';
    	accentColor = themeToColors(SESSION.json['accent_color']);
    	document.body.style.setProperty('--themetext', themeMcolor);
    	var radio1 = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='30' height='30'><circle fill='%23" + accentColor.substr(1) + "' cx='14' cy='14.5' r='11.5'/></svg>";
    	var test = getCSSRule('.toggle .toggle-radio');
    	test.style.backgroundImage='url("' + radio1 + '")';

        document.body.style.setProperty('--config_modal_btn_bg', 'rgba(64,64,64,0.75)');
        $('.modal-footer .btn').css('background-color', 'rgba(128,128,128,.35)');

    	if ($('.lib-config').length) {
    		$('#lib-config-btn').addClass('active');
    	}
    	else if ($('.snd-config').length) {
    		$('#snd-config-btn').addClass('active');
    	}
    	else if ($('.net-config').length) {
    		$('#net-config-btn').addClass('active');
    	}
    	else if ($('.sys-config').length) {
    		$('#sys-config-btn').addClass('active');
    	}
        else if ($('.ren-config').length) {
    		$('#ren-config-btn').addClass('active');
    	}

        // Setup pines notify
        $.pnotify.defaults.history = false;

    	// Connect to server engines
        engineMpdLite();
        engineCmdLite();

        // Busy spinner for Thumbcache update/re-gen initiated
        if (GLOBAL.thmUpdInitiated == true) {
            $('.busy-spinner').show();
        }

        // On-screen keyboard
        $('#btn-on-screen-kbd').text(SESSION.json['on_screen_kbd']);
        if (GLOBAL.chromium && SESSION.json['on_screen_kbd'] == 'Disable') {
             initializeOSK();
        }
});
	//
	// EVENT HANDLERS
	//

	//Back button on header
	$('#config-back a').click(function() {
        // When returning to panels hide the config tabs
		if ($(this).attr('href') == '/index.php') {
			$('#config-tabs').hide();
		}
	});

	// Display spinner when form submitted
	$('.btn-submit').click(function() {
		$('.busy-spinner').show();
	});

	// EQ configs
	$('#eqp-curve-name').change(function() {
		//console.log('http://' + location.host + 'eqp-config.php?curve=' + $(this).val());
		location.assign('http://' + location.host + location.pathname +'?curve=' + $(this).val());
	});
	$('#eqg-curve-name').change(function() {
		//console.log('http://' + location.host + 'eqg-config.php?curve=' + $(this).val());
		location.assign('http://' + location.host + '/eqg-config.php?curve=' + $(this).val());
	});
    $('#master-gain-up, #master-gain-dn').on('mousedown mouseup click', function(e) {
        //e.stopPropagation();
        e.preventDefault();

    	if (e.type == 'mousedown') {
    		var selector = $(this).attr('id');
    	    eqGainUpdInterval = setInterval(function() {
    			updEqpMasterGainSlider(selector);
    	    },50); // ms
    	}
    	else if (e.type == 'mouseup') {
    		clearInterval(eqGainUpdInterval);
    	}
    	else if (e.type == 'click') {
    		updEqpMasterGainSlider($(this).attr('id'));
    	}
    });
    $('#new-curvename').on('shown.bs.modal', function() {
		$('#new-curvename-input').focus();
	});

	// Network config show static on page load/reload
	if ($('#eth0-method').length && $('#eth0-method').val() == 'static') {
		$('#eth0-static').show();
	}
	if ($('#wlan0-method').length && $('#wlan0-method').val() == 'static') {
		$('#wlan0-static').show();
	}
	// Show/hide static
	$('#eth0-method').change(function() {
		if ($(this).val() == 'static') {
			$('#eth0-static').show();
			//$('#wlan0-method').val('dhcp').change(); // prevent both from being set to 'static'
		}
		else {
			$('#eth0-static').hide();
		}
	});
	$('#wlan0-method').change(function() {
		if ($(this).val() == 'static') {
			if ($('#wlan0ssid').val() != 'None (activates AP mode)') {
			 	$('#wlan0-static').show();
				//$('#eth0-method').val('dhcp').change(); // prevent both from being set to 'static'
			}
			else {
				notify('needssid');
			}
		}
        else {
            $('#wlan0-static').hide();
        }
	});
	// wlan0 SSID
	$('#manual-ssid').on('shown.bs.modal', function() {
		$('#wlan0otherssid').focus();
	});
	$('#wlan0ssid').change(function() {
        //console.log(NETWORK.json['wlan0']['wlanssid'], NETWORK.json['wlan0']['wlan_psk']);
        if ($('#wlan0ssid').val() == NETWORK.json['wlan0']['wlanssid']) {
            $('#wlan0pwd').val(NETWORK.json['wlan0']['wlan_psk']);
        }
        else {
            $('#wlan0pwd').val('');
        }

		if ($('#wlan0-method').val() == 'static') {
			if ($(this).val() == '' || $(this).val() == 'None (activates AP mode)') {
                $('#wlan0-static').hide();
				notify('needdhcp');
			}
            else {
                $('#wlan0-static').show();
            }
		}
	});
    // apd0 SSID
    $('#apdssid').on('input', function() {
        //console.log(NETWORK.json['apd0']['wlanssid'], NETWORK.json['apd0']['wlan_psk']);
        if ($('#apdssid').val() == NETWORK.json['apd0']['wlanssid']) {
            $('#apdpwd').val(NETWORK.json['apd0']['wlan_psk']);
        }
        else {
            $('#apdpwd').val('');
        }
	});

    // Show/hide password plaintext
    $('.show-hide-password').click(function(e) {
        var password_field = document.getElementById($(this).data('id'));
        if ($('#' + $(this).data('id')).val() != '') {
            password_field.type == 'password' ? password_field.type = 'text' : password_field.type = 'password';
        }
    });

	// Music source protocols
	if ($('#type').length) {
		$('#mounttype').val($('#type').val()); // Hidden input on manual server entry
	}
	$('#type').change(function() {
		$('#mounttype').val($(this).val()); // Hidden input on manual server entry
		if ($(this).val() == 'cifs') {
			$('#userid-password').show();
			//$('#scan-btn').show();
			$('#edit-server').show();
			$('#advanced-options').show();
            $('#rw-size').show();
            $('#options').val('ro,noserverino,dir_mode=0777,file_mode=0777');
            //$('#info-mount-flags').html('vers=2.0 or 3.0 may be needed and/or sec=ntlm/ntlmssp removed depending on what the NAS requires.');
		}
		else if ($(this).val() == 'nfs') {
			$('#userid-password').hide();
			//$('#scan-btn').hide();
			$('#edit-server').show();
			$('#advanced-options').show();
            $('#rw-size').hide();
            $('#options').val('soft,timeo=10,retrans=1,ro,nolock');
            //$('#info-mount-flags').html('vers=1.0 or higher may be needed depending on what the NAS requires.');
		}
        /* DEPRECATED due to removal of obsolete and unmaintained djmount
		else if ($(this).val() == 'upnp') {
			$('#userid-password').hide();
			$('#scan-btn').show();
			$('#edit-server').hide();
			$('#advanced-options').hide();
		}*/
	});

	// NAS config pre-load manual server entry
	$('#manual-server').on('shown.bs.modal', function() {
		$('#manualserver').focus();
	});
	$('#editserver').click(function(e) {
		$('#manualserver').val($('#address').val().trim());
	});

	// View thumbnail cache generation status
    $('#view-thmcache-status').click(function(e) {
        $.getJSON('command/music-library.php?cmd=thumcache_status', function(data) {
            $('#thmcache-status').html(data);
        });
	});

    // MPD config show/hide Selective resample
    $('#sox-enabled').change(function() {
        if ($('#sox-enabled').val() == 'Yes') {
            $('#selective_resample').show();
        }
        else {
            $('#selective_resample').hide();
        }
	});
    // MPD config show Selective resample field on page load/reload
	if ($('#sox-enabled').length && $('#sox-enabled').val() == 'Yes') {
		$('#selective_resample').show();
	}
    // MPD config show/hide SoX custom recipe fields
    $('#sox_quality').change(function() {
        if ($('#sox_quality').val() == 'custom') {
            $('#sox_custom_recipe').show();
        }
        else {
            $('#sox_custom_recipe').hide();
        }
	});
    // MPD config show SoX custom recipe fields on page load/reload
	if ($('#sox_quality').length && $('#sox_quality').val() == 'custom') {
		$('#sox_custom_recipe').show();
	}

    // Sysinfo notification
    $('#sysinfo-menu-item').click(function(e) {
        notify('gathering_info', '', '3_seconds');
    });

    // Multiroom adv options show/hide
    $('#multiroom_tx_adv_options_label').click(function(e) {
        $('#multiroom_tx_adv_options').toggleClass('hide');
        var labelText = $('#multiroom_tx_adv_options_label').html() == 'Advanced (+)' ? 'Advanced (&minus;)' : 'Advanced (&plus;)'
        $('#multiroom_tx_adv_options_label').html(labelText);
        $.post('command/multiroom.php?cmd=upd_tx_adv_toggle', {'adv_toggle': labelText});
    });
    $('#multiroom_rx_adv_options_label').click(function(e) {
        $('#multiroom_rx_adv_options').toggleClass('hide');
        var labelText = $('#multiroom_rx_adv_options_label').html() == 'Advanced (+)' ? 'Advanced (&minus;)' : 'Advanced (&plus;)'
        $('#multiroom_rx_adv_options_label').html(labelText);
        $.post('command/multiroom.php?cmd=upd_rx_adv_toggle', {'adv_toggle': labelText});
    });

    // Button "Create Backup"
    // This global is used to prevent the "Reconnect" screen from being displayed while a backup zip is being created/downloaded
    // NOTE: This global is tested and reset to false in playerlib.js function renderReconnect()
    $('#backup_create').click(function(e) {
        GLOBAL.backupCreate = true;
    });

    // On-screen keyboard
    $('#btn-on-screen-kbd').click(function(e) {
        e.preventDefault();

        var btnLabel = $('#btn-on-screen-kbd').text() == 'Enable' ? 'Disable' : 'Enable';
        $.post('command/cfg-table.php?cmd=upd_cfg_system', {'on_screen_kbd': btnLabel}, function(data) {
            $('#btn-on-screen-kbd').text(btnLabel);

            setTimeout(function() {
                location.reload();
            }, DEFAULT_TIMEOUT);
        }, 'json');
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
