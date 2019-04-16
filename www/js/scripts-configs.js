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
 * 2019-04-12 TC moOde 5.0
 *
 */
jQuery(document).ready(function($){ 'use strict';
	$('#config-back').show();
	$('#config-tabs').css('display', 'flex');
	$('#menu-bottom').css('display', 'none');
	$('.moode-config-settings-div').hide();

	// compensate for Android popup kbd changing the viewport, also for notch phones
	$("meta[name=viewport]").attr("content", "height=" + $(window).height() + ", width=" + $(window).width() + ", initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover");
	// store device pixel ratio
	sendMoodeCmd('POST', 'updcfgsystem', {'library_pixelratio': window.devicePixelRatio});

	// load current cfg
	var result = sendMoodeCmd('GET', 'read_cfg_all');
	SESSION.json = result['cfg_system'];
	THEME.json = result['cfg_theme'];
	
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

	// connect to server engine (lite version that just looks for db update running / complete)
	engineMpdLite();

	//
	// EVENT HANDLERS
	//

	// back button on header
	$('#config-back a').click(function() {
		if ($(this).attr('href') == '/index.php') {
			$('#config-tabs').hide();
		}
	});

	// eq configs
	$('#eqp-curve-name').change(function() {
		//console.log('http://' + location.host + 'eqp-config.php?curve=' + $(this).val());
		location.assign('http://' + location.host + '/eqp-config.php?curve=' + $(this).val());
	});	                        
	$('#eqg-curve-name').change(function() {
		//console.log('http://' + location.host + 'eqg-config.php?curve=' + $(this).val());
		location.assign('http://' + location.host + '/eqg-config.php?curve=' + $(this).val());
	});	                        

	// network config show static on page load/reload
	if ($('#eth0-method').length && $('#eth0-method').val() == 'static') {
		$('#eth0-static').show();
	}
	if ($('#wlan0-method').length && $('#wlan0-method').val() == 'static') {
		$('#wlan0-static').show();
	}
	// network config show/hide static 
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
			if($('#wlan0ssid').val() != '' && $('#wlan0ssid').val() != 'blank (activates AP mode)') {
				$('#wlan0-static').show();
				//$('#eth0-method').val('dhcp').change(); // prevent both from being set to 'static'
			}
			else {
				notify('needssid', '');
			}                                                            
		}
	});
	// network config ssid
	$('#manual-ssid').on('shown.bs.modal', function() {
		$('#wlan0otherssid').focus();
	});  
	$('#wlan0ssid').change(function() {
		if ($('#wlan0-method').val() == 'static') {
			if ($(this).val() == '' || $(this).val() == 'blank (activates AP mode)') {
				notify('needdhcp', '');
			}
		}                      
	});	                        

	// nas config protocol type flags
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
		}
		// nfs
		else {
			$('#userid-password').hide();
			$('#options').val('ro,nolock');
			$('#info-mount-flags').html('vers=1.0 or higher may be needed depending on what the NAS requires.');
			$('#scan-btn').hide();
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
		var resp = sendMoodeCmd('GET', 'thmcachestatus');
		$('#thmcache-status').html(resp);
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
