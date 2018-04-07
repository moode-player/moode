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
 * - #type, #editserver for new nas-config
 * - set focus to manual ssid and server input fields
 * - update help text for nas mounts
 * - remove accumulated  code
 *
 */

jQuery(document).ready(function($){ 'use strict';

	SESSION.json = sendMoodeCmd('GET', 'readcfgengine'); // load session vars
	THEME.json = sendMoodeCmd('GET', 'readcfgtheme'); // load themes    // setup pines notify
	
	// newui
	var tempOp = themeOp;
	if (themeOp == 0.74902) {tempOp = 0.1};
	$('.container2 .dropdown-menu.open').css({backgroundColor: 'rgba(50,50,50,' + themeOp + ')'});
	
    $.pnotify.defaults.history = false;

	// connect to mpd engine
    engineMpd();

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
	$('#manual-ssid').on('shown.bs.modal', function () {
		$('#wlan0otherssid').focus(); // tpc r41
	});  
	$('#wlan0ssid').change(function() {
		if ($('#wlan0-method').val() == 'static') {
			if ($(this).val() == '' || $(this).val() == 'blank (activates AP mode)') {
				notify('needdhcp', '');
			}
		}                      
	});	                        

	// nas config protocol type flags
	// tpc r41 enhance for new nas-config
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
	$('#manual-server').on('shown.bs.modal', function () {
		$('#manualserver').focus(); // tpc r41
	});  
	$('#editserver').click(function() {
		$('#manualserver').val($('#address').val().trim());
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
