/*1
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
 * 2020-05-03 TC moOde 6.5.2
 *
 */
jQuery(document).ready(function($){ 'use strict';
    GLOBAL.scriptSection = 'configs';
	$('#config-back').show();
	$('#config-tabs').css('display', 'flex');
	$('#menu-bottom').css('display', 'none');
	$('#configure .row2-btns').hide();

	// compensate for Android popup kbd changing the viewport, also for notch phones
	$("meta[name=viewport]").attr("content", "height=" + $(window).height() + ", width=" + $(window).width() + ", initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover");
	// store device pixel ratio
    $.post('command/moode.php?cmd=updcfgsystem', {'library_pixelratio': window.devicePixelRatio});

	// load current cfg
    $.getJSON('command/moode.php?cmd=read_cfgs_no_radio', function(result) {
    	SESSION.json = result['cfg_system'];
    	THEME.json = result['cfg_theme'];
        NETWORK.json = result['cfg_network'];

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

        // setup pines notify
        $.pnotify.defaults.history = false;

    	// Connect to server engines
        engineMpdLite();
        engineCmdLite();

        // Busy spinner for Thumbcache update/re-gen initiated
        if (GLOBAL.thmUpdInitiated == true) {
            $('.busy-spinner').show();
        }
});
	//
	// EVENT HANDLERS
	//

	// back button on header
	$('#config-back a').click(function() {
		if ($(this).attr('href') == '/index.php') {
			$('#config-tabs').hide();
		}
	});

	// display spinner when form submitted
	$('.btn-submit').click(function() {
		$('.busy-spinner').show();
	});

	// EQ configs
	$('#eqp-curve-name').change(function() {
		//console.log('http://' + location.host + 'eqp-config.php?curve=' + $(this).val());
		location.assign('http://' + location.host + '/eqp-config.php?curve=' + $(this).val());
	});
	$('#eqg-curve-name').change(function() {
		//console.log('http://' + location.host + 'eqg-config.php?curve=' + $(this).val());
		location.assign('http://' + location.host + '/eqg-config.php?curve=' + $(this).val());
	});
    $('#master-gain-up, #master-gain-dn').on('mousedown mouseup click', function(e) {
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

	// network config show static on page load/reload
	if ($('#eth0-method').length && $('#eth0-method').val() == 'static') {
		$('#eth0-static').show();
	}
	if ($('#wlan0-method').length && $('#wlan0-method').val() == 'static') {
		$('#wlan0-static').show();
	}
	// show/hide static
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
			if ($('#wlan0ssid').val() != '' && $('#wlan0ssid').val() != 'None (activates AP mode)') {
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
	// wlan0 ssid
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
    // apd0 ssid
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

	// music source protocols (type)
	if ($('#type').length) {
		$('#mounttype').val($('#type').val()); // hidden input on manual server entry
	}
	$('#type').change(function() {
		$('#mounttype').val($(this).val()); // hidden input on manual server entry
		if ($(this).val() == 'cifs') {
			$('#userid-password').show();
			$('#options').val('vers=1.0,sec=ntlm,ro,dir_mode=0777,file_mode=0777');
			$('#info-mount-flags').html('vers=2.0 or 3.0 may be needed and/or sec=ntlm removed depending on what the NAS requires.');
			$('#scan-btn').show();
			$('#edit-server').show();
			$('#advanced-options').show();
		}
		else if ($(this).val() == 'nfs') {
			$('#userid-password').hide();
			$('#options').val('ro,nolock');
			$('#info-mount-flags').html('vers=1.0 or higher may be needed depending on what the NAS requires.');
			$('#scan-btn').hide();
			$('#edit-server').show();
			$('#advanced-options').show();
		}
		else if ($(this).val() == 'upnp') {
			$('#userid-password').hide();
			$('#scan-btn').show();
			$('#edit-server').hide();
			$('#advanced-options').hide();
		}
	});

	// nas config pre-load manual server entry
	$('#manual-server').on('shown.bs.modal', function() {
		$('#manualserver').focus();
	});
	$('#editserver').click(function(e) {
		$('#manualserver').val($('#address').val().trim());
	});

	// view thmcache status
    $('#view-thmcache-status').click(function(e) {
        $.getJSON('command/moode.php?cmd=thmcachestatus', function(result) {
            $('#thmcache-status').html(result);
        });
	});

    // info button (i) show/hide toggle
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
