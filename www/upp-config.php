<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
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
 * 2020-12-09 TC moOde 7.0.0
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');
$dbh = cfgdb_connect();

// apply setting changes
if (isset($_POST['save']) && $_POST['save'] == '1') {
	foreach ($_POST['config'] as $key => $value) {
		cfgdb_update('cfg_upnp', $dbh, $key, $value);

		if ($value != '') {
			sysCmd("sed -i '/" . $key . ' =' . '/c\\' . $key . ' = ' . $value . "' /etc/upmpdcli.conf");
		}
		else {
			sysCmd("sed -i '/" . $key . ' =' . '/c\\' . '#' . $key . ' = ' . $value . "' /etc/upmpdcli.conf");
		}
	}

	// restart if indicated
	submitJob('upnpsvc', '', 'Changes saved', ($_SESSION['upnpsvc'] == '1' ? 'UPnP renderer restarted' : ''));
}

session_write_close();

// load settings
$result = cfgdb_read('cfg_upnp', $dbh);
$cfg_upnp = array();

foreach ($result as $row) {
	$cfg_upnp[$row['param']] = $row['value'];
}

// GENERAL
$_select['checkcontentformat'] .= "<option value=\"1\" " . (($cfg_upnp['checkcontentformat'] == '1') ? "selected" : "") . ">Yes</option>\n";
$_select['checkcontentformat'] .= "<option value=\"0\" " . (($cfg_upnp['checkcontentformat'] == '0') ? "selected" : "") . ">No</option>\n";

// TIDAL
$_select['tidaluser'] = $cfg_upnp['tidaluser'];
$_select['tidalpass'] = $cfg_upnp['tidalpass'];
$_select['tidalquality'] .= "<option value=\"lossless\" " . (($cfg_upnp['tidalquality'] == 'lossless') ? "selected" : "") . ">FLAC (Lossless)</option>\n";
$_select['tidalquality'] .= "<option value=\"high\" " . (($cfg_upnp['tidalquality'] == 'high') ? "selected" : "") . ">AAC (High bitrate)</option>\n";
$_select['tidalquality'] .= "<option value=\"low\" " . (($cfg_upnp['tidalquality'] == 'low') ? "selected" : "") . ">AAC (Low bitrate)</option>\n";

// QOBUZ
$_select['qobuzuser'] = $cfg_upnp['qobuzuser'];
$_select['qobuzpass'] = $cfg_upnp['qobuzpass'];
$_select['qobuzformatid'] .= "<option value=\"7\" " . (($cfg_upnp['qobuzformatid'] == '7') ? "selected" : "") . ">FLAC (up to 96K)</option>\n";
$_select['qobuzformatid'] .= "<option value=\"27\" " . (($cfg_upnp['qobuzformatid'] == '27') ? "selected" : "") . ">FLAC (up to 192K)</option>\n";
$_select['qobuzformatid'] .= "<option value=\"5\" " . (($cfg_upnp['qobuzformatid'] == '5') ? "selected" : "") . ">MP3 (320K)</option>\n";

waitWorker(1, 'upp-config');

$tpl = "upp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.min.php');
