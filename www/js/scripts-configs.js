/*!
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

jQuery(document).ready(function($){ 'use strict';
    // Call $.pnotify if created by backend
    if( window.ui_notify != undefined ) {
        ui_notify();
    }

    GLOBAL.scriptSection = 'configs';

	$('#config-tabs').css('display', 'flex');
	$('#panel-footer').css('display', 'none');
    $('.dropdown-menu > li > a').css('color', 'var(--config-text-color)');

    // For ultra-wide screens
    if ($('.container').css('margin-right') != '0px') {
        $('#panel-header').css('margin-right', '25vw');
        $('#panel-header').css('margin-left', '25vw');
    }

	// Compensate for Android popup kbd changing the viewport, also for notch phones
	$("meta[name=viewport]").attr("content", "height=" + $(window).height() + ", width=" + $(window).width() + ", initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover");
	// Store device pixel ratio
    $.post('command/cfg-table.php?cmd=upd_cfg_system', {'library_pixelratio': window.devicePixelRatio});

	// Load current cfg
    $.getJSON('command/cfg-table.php?cmd=get_cfg_tables_no_radio', function(data) {
    	SESSION.json = data['cfg_system'];
    	THEME.json = data['cfg_theme'];
        NETWORK.json = data['cfg_network'];
        SSID.json = data['cfg_ssid'];

        $('#config-back, #config-home').show();
    	UI.mobile = $(window).width() < 480 ? true : false; /* mobile-ish */
    	setFontSize();

    	var tempOp = themeOp;
    	if (themeOp == 0.74902) {tempOp = 0.1};

    	// Set theme
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
        else if ($('.per-config').length) {
    		$('#per-config-btn').addClass('active');
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
        if (GLOBAL.chromium && SESSION.json['on_screen_kbd'] == 'On') {
             initializeOSK();
        }

        // First boot check for userid
        if (SESSION.json['user_id'] == NO_USERID_DEFINED) {
            notify(NOTIFY_TITLE_ERROR, 'userid_error', NOTIFY_MSG_NO_USERID, NOTIFY_DURATION_INFINITE);
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

    // Dim disabled toggle controls
    $('input[type=radio]:disabled').each (function() {
        $('.' + $(this).attr('id').slice(0, -2)).css('opacity', '.5');
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
        setTimeout(function() {
            $('#new-curvename-input').focus();
        }, DEFAULT_TIMEOUT);
	});

	// Network config
    // Ethernet
	if ($('#eth0method').length && $('#eth0method').val() == 'static') {
		$('#eth0-static-section').show();
	}
	if ($('#wlan0method').length && $('#wlan0method').val() == 'static') {
		$('#wlan0-static-section').show();
	}
	$('#eth0method').change(function() {
		if ($(this).val() == 'static') {
			$('#eth0-static-section').show();
		} else {
			$('#eth0-static-section').hide();
		}
	});
    // Wireless
	$('#wlan0method').change(function() {
		if ($(this).val() == 'static') {
			if ($('#wlan0ssid').val() == 'None' || $('#wlan0ssid').val() == 'Activate Hotspot') {
                notify(NOTIFY_TITLE_ALERT, 'dhcp_required');
                $('#wlan0method').val('dhcp').change();
			} else {
                $('#wlan0-static-section').show();
			}
		} else {
            $('#wlan0-static-section').hide();
        }
	});
	$('#manual-ssid').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('#wlan0otherssid').focus();
        }, DEFAULT_TIMEOUT);
	});
	$('#wlan0ssid').change(function() {
        var ssid = $('#wlan0ssid').val();
        if (typeof(SSID.json[ssid]) != 'undefined') {
            // Set to saved SSID values
            $('#wlan0pwd').val(SSID.json[ssid]['psk']);
            $('#wlan0method').val(SSID.json[ssid]['method']).change();
            $('#wlan0ipaddr').val(SSID.json[ssid]['ipaddr']);
            $('#wlan0netmask').val(SSID.json[ssid]['netmask']);
            $('#wlan0gateway').val(SSID.json[ssid]['gateway']);
            $('#wlan0pridns').val(SSID.json[ssid]['pridns']);
            $('#wlan0secdns').val(SSID.json[ssid]['secdns']);
        } else {
            // Reset to DHCP and empty password
            $('#wlan0method').val('dhcp').change();
            $('#wlan0pwd').val('');
        }
	});
    $('#apdssid').on('input', function() {
        if ($('#apdssid').val() == NETWORK.json['apd0']['wlanssid']) {
            $('#apdpwd').val(NETWORK.json['apd0']['wlanpsk']);
        } else {
            $('#apdpwd').val('');
        }
	});

    // Show/hide password plaintext (the eye icon)
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
		else if ($(this).val() == LIB_MOUNT_TYPE_NFS) {
			$('#userid-password').hide();
			//$('#scan-btn').hide();
			$('#edit-server').show();
			$('#advanced-options').show();
            $('#rw-size').hide();
            $('#options').val('soft,timeo=10,retrans=1,ro,nolock');
            //$('#info-mount-flags').html('vers=1.0 or higher may be needed depending on what the NAS requires.');
		}
	});

	// NAS config pre-load manual server entry
	$('#manual-server').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('#manualserver').focus();
        }, DEFAULT_TIMEOUT);
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
    $('#sox-enabled, #sox-sample-rate').change(function() {
        if ($('#sox-enabled').val() == 'Yes' && $('#sox-sample-rate').val() != '*') {
            $('#selective-resample').show();
        }
        else {
            $('#selective-resample').hide();
        }
	});
    // MPD config show Selective resample field on page load/reload
	if ($('#sox-enabled').length && $('#sox-enabled').val() == 'Yes' && $('#sox-sample-rate').val() != '*') {
		$('#selective-resample').show();
	}
    // MPD config show/hide SoX custom recipe fields
    $('#sox-quality').change(function() {
        if ($('#sox-quality').val() == 'custom') {
            $('#sox-custom-recipe').show();
        }
        else {
            $('#sox-custom-recipe').hide();
        }
	});
    // MPD config show SoX custom recipe fields on page load/reload
	if ($('#sox-quality').length && $('#sox-quality').val() == 'custom') {
		$('#sox-custom-recipe').show();
	}

    // Sysinfo notification
    $('#sysinfo-menu-item').click(function(e) {
        notify(NOTIFY_TITLE_INFO, 'gathering_info', NOTIFY_DURATION_SHORT);
    });

    // Multiroom adv options show/hide
    $('#multiroom-tx-adv-options-label').click(function(e) {
        $('#multiroom-tx-adv-options').toggleClass('hide');
        var labelText = $('#multiroom-tx-adv-options-label').html() == 'Show' ? 'Hide' : 'Show'
        $('#multiroom-tx-adv-options-label').html(labelText);
        $.post('command/multiroom.php?cmd=tx_adv_toggle', {'adv_toggle': labelText});
    });
    $('#multiroom-rx-adv-options-label').click(function(e) {
        $('#multiroom-rx-adv-options').toggleClass('hide');
        var labelText = $('#multiroom-rx-adv-options-label').html() == 'Show' ? 'Hide' : 'Show'
        $('#multiroom-rx-adv-options-label').html(labelText);
        $.post('command/multiroom.php?cmd=rx_adv_toggle', {'adv_toggle': labelText});
    });

    // Button "Create Backup"
    // This global is used to prevent the "Reconnect" screen from being displayed while a backup zip is being created/downloaded
    // NOTE: This global is tested and reset to false in playerlib.js function renderReconnect()
    $('#backup-create').click(function(e) {
        GLOBAL.backupCreate = true;
    });

    // CamillaDSP 2 config description
    $('#cdsp-mode').change(function(e) {
        var selectedConfig = $('#cdsp-mode :selected').text();
        $.getJSON('command/camilla.php?cmd=cdsp_get_config_desc&selected_config=' + selectedConfig, function(data) {
            $('#cdsp-config-description').text(data);
        });
    });

    // Format NVMe drive screen
    $('#btn-format-nvme-drive').click(function(e) {
        var parts = $('#nvme-drive').val().split(',');
        $('#modal-nvme-drive-txt').text(parts[0]);
        $('#modal-nvme-drive').val($('#nvme-drive').val());
        $('#modal-nvme-drive-label').val($('#nvme-drive-label').val());
    });
    // Format NVMe drive submit (close modal)
    $('#btn-format-nvme-drive-submit').click(function(e) {
        $('#format-nvme-drive-modal').modal('toggle');
    });

    // Downgrade chromium submit (close modal)
    $('#btn-downgrade-chromium-submit').click(function(e) {
        $('#downgrade-chromium-modal').modal('toggle');
    });

    // Info button (i) show/hide toggle
    $('.config-info-toggle').click(function(e) {
		var spanId = '#' + $(this).data('cmd');
		if ($(spanId).css('display') == 'none') {
			$(spanId).css('display', 'block');
		} else {
			$(spanId).css('display', 'none');
		}
    });
});
