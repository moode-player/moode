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
 * 2017-12-07 TC moOde 4.0
 *
 */

jQuery(document).ready(function($){ 'use strict';

    // setup pines notify
    $.pnotify.defaults.history = false;

	// connect to mpd engine
    engineMpd();

	// hide some controls	
	$('.playback-controls').removeClass('playback-controls-sm');
	$('.playback-controls').addClass('hidden');
	$('#playback-page-cycle').css({"display":"none"});

	// network config page load/reload
	if ($('#eth0-method').length && $('#eth0-method').val() == 'static') {
		$('#eth0-static').show();
	}
	if ($('#wlan0-method').length && $('#wlan0-method').val() == 'static') {
		$('#wlan0-static').show();
	}
	                        
	// EQ configs
	$('#eqp-curve-name').change(function() {
		//console.log('http://' + location.host + 'eqp-config.php?curve=' + $(this).val());
		location.assign('http://' + location.host + '/eqp-config.php?curve=' + $(this).val());
	});	                        
	$('#eqg-curve-name').change(function() {
		//console.log('http://' + location.host + 'eqg-config.php?curve=' + $(this).val());
		location.assign('http://' + location.host + '/eqg-config.php?curve=' + $(this).val());
	});	                        

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
	$('#wlan0ssid').change(function() {          
		if ($('#wlan0-method').val() == 'static') {
			if ($(this).val() == '' || $(this).val() == 'blank (activates AP mode)') {
				notify('needdhcp', '');
			}
		}                      
	});	                        

	// nas config file share protocol flags
	$('#type').change(function() {          
		if ($(this).val() == 'cifs') {
			$('#userid-password').show();
			$('#options').val('ro,dir_mode=0777,file_mode=0777');
		} else {
			$('#userid-password').hide();
			$('#options').val('ro,nolock');
		}                       
	});
	
    // info show/hide toggle
    $('.info-toggle').click(function() {
		var spanId = '#' + $(this).data('cmd');
		if ($(spanId).hasClass('hide')) {
			$(spanId).removeClass('hide');
		} else {
			$(spanId).addClass('hide');
		}
    });

	// plaback history first/last page click handlers
    $('.ph-firstPage').click(function() {
        $('#container-playhistory').scrollTo(0 , 500);
    });
    $('.ph-lastPage').click(function() {
        $('#container-playhistory').scrollTo('100%', 500);
    });

	// customization settings first/last page click handlers
    $('.cs-firstPage').click(function() {
        $('#container-customize').scrollTo(0 , 500);
    });
    $('.cs-lastPage').click(function() {
        $('#container-customize').scrollTo('100%', 500);
    });

    // playlist history typedown search
    $('#ph-filter').keyup(function() {
        $.scrollTo(0 , 500);
        var filter = $(this).val(), count = 0;
        $('.playhistory li').each(function() {
            if ($(this).text().search(new RegExp(filter, 'i')) < 0) {
                $(this).hide();
            } else {
                $(this).show();
                count++;
            }
        });
        
		// change format of search results line
        var s = (count == 1) ? '' : 's';
        if (filter != '') {
            $('#ph-filter-results').html((+count) + '&nbsp;item' + s);
        } else {
            $('#ph-filter-results').html('');
        }
    });
});
