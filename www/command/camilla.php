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

switch ($_GET['cmd']) {
	case 'camilladsp_setconfig':
		if (isset($_POST['cdspconfig'])) {
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

			if ($_SESSION['camilladsp'] != $currentMode && ( $_SESSION['camilladsp'] == 'off' || $currentMode == 'off')) {
				submitJob('camilladsp', $newMode);
			} else {
				$cdsp->reloadConfig();
			}
		} else {
			workerLog('camilla.php Error: missing camilladsp config name');
		}
		break;
}
