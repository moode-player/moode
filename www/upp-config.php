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
 */

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$dbh = sqlConnect();
phpSession('open');

// Apply setting changes
if (isset($_POST['save']) && $_POST['save'] == '1') {
	// Set service params
	$_POST['config']['upnpav'] = $_POST['config']['svctype'] == 'upnpav' ? '1' : '0';
	$_POST['config']['openhome'] = $_POST['config']['svctype'] == 'openhome' ? '1' : '0';

	// Update sql table and conf file
	foreach ($_POST['config'] as $key => $value) {
		sqlUpdate('cfg_upnp', $dbh, $key, $value);

		if ($value != '') {
			sysCmd("sed -i '/" . $key . ' =' . '/c\\' . $key . ' = ' . $value . "' /etc/upmpdcli.conf");
		}
		else {
			sysCmd("sed -i '/" . $key . ' =' . '/c\\' . '#' . $key . ' = ' . $value . "' /etc/upmpdcli.conf");
		}
	}

	// Restart if indicated
	submitJob('upnpsvc', '', 'Settings updated', ($_SESSION['upnpsvc'] == '1' ? 'UPnP restarted' : ''));
}

phpSession('close');

// Load settings
$result = sqlRead('cfg_upnp', $dbh);
$cfg_upnp = array();

foreach ($result as $row) {
	$cfg_upnp[$row['param']] = $row['value'];
}

// GENERAL
$_select['checkcontentformat'] .= "<option value=\"1\" " . (($cfg_upnp['checkcontentformat'] == '1') ? "selected" : "") . ">Yes</option>\n";
$_select['checkcontentformat'] .= "<option value=\"0\" " . (($cfg_upnp['checkcontentformat'] == '0') ? "selected" : "") . ">No</option>\n";
$_select['svctype'] .= "<option value=\"upnpav\" " . (($cfg_upnp['upnpav'] == '1') ? "selected" : "") . ">UPnP-A/V</option>\n";
$_select['svctype'] .= "<option value=\"openhome\" " . (($cfg_upnp['openhome'] == '1') ? "selected" : "") . ">OpenHome</option>\n";

/*DELETE
$_select['upnpav'] .= "<option value=\"1\" " . (($cfg_upnp['upnpav'] == '1') ? "selected" : "") . ">Yes</option>\n";
$_select['upnpav'] .= "<option value=\"0\" " . (($cfg_upnp['upnpav'] == '0') ? "selected" : "") . ">No</option>\n";
$_select['openhome'] .= "<option value=\"1\" " . (($cfg_upnp['openhome'] == '1') ? "selected" : "") . ">Yes</option>\n";
$_select['openhome'] .= "<option value=\"0\" " . (($cfg_upnp['openhome'] == '0') ? "selected" : "") . ">No</option>\n";

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
*/
waitWorker(1, 'upp-config');

$tpl = "upp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
