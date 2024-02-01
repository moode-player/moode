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

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/cdsp.php';
require_once __DIR__ . '/../inc/mpd.php';

const CAMILLA_CONFIG_DIR = '/usr/share/camilladsp';

switch ($_GET['cmd']) {
	case 'cdsp_set_config':
		if (isset($_POST['cdspconfig']) && !empty($_POST['cdspconfig'])) {
			phpSession('open');
			$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
			$currentMode = $_SESSION['camilladsp'];
			$newMode = $_POST['cdspconfig'];
			phpSession('write', 'camilladsp', $newMode);
			phpSession('close');

			$cdsp->selectConfig($newMode);

			if ($_SESSION['cdsp_fix_playback'] == 'Yes') {
				$cdsp->setPlaybackDevice($_SESSION['cardnum'], $_SESSION['alsa_output_mode']);
			}

			updCDSPConfig($newMode, $currentMode, $cdsp);
		} else {
			sendEngCmd('cdsp_config_update_failed');
			workerLog('camilla.php: Error: $_POST[cdspconfig] missing or empty');
		}
		break;
	case 'cdsp_get_config_desc':
		$ymlConfig = yaml_parse_file(CAMILLA_CONFIG_DIR . '/configs/'. $_GET['selected_config'] . '.yml');
		echo json_encode(key_exists('description', $ymlConfig) ? $ymlConfig['description'] : '');
		break;
	default:
		echo 'Unknown command';
		break;
}
