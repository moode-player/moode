<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * (C) 2021 @bitlab (@bitkeeper Git)
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

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');

/**
 * Post parameter processing
 */
if( isset($_POST['backup_create']) && $_POST['backup_create'] == '1' ) {
	$backupOptions = '';
	if( isset($_POST['backup_system']) && $_POST['backup_system'] == '1' ) {
		$backupOptions .= $backupOptions ? ' config' : 'config';
	}
	if( isset($_POST['backup_camilladsp']) && $_POST['backup_camilladsp'] == '1' ) {
		$backupOptions .= $backupOptions ? ' cdsp' : 'cdsp';
	}
	if( isset($_POST['backup_radiostations_moode']) && $_POST['backup_radiostations_moode'] == '1' ) {
		$backupOptions .= $backupOptions ? ' r_sys' : 'r_sys';
	}
	if( isset($_POST['backup_radiostations_other']) && $_POST['backup_radiostations_other'] == '1' ) {
		$backupOptions .= $backupOptions ? ' r_other' : 'r_other';
	}

	if($backupOptions) {
		$backupOptions = '--what ' . $backupOptions . ' ';
	}

	if( isset($_POST['backup_wlan0pwd']) && $_POST['backup_wlan0pwd']  ) {
		$backupOptions .= '--wlanpwd ' .$_POST['backup_wlan0pwd'] .' ';
	}

	$tempBackupFileName = '/tmp/backup.zip';
	sysCmd('sudo -u pi /var/www/command/backupmanager.py ' . $backupOptions . '--backup ' . $tempBackupFileName);
	// print('/var/www/command/backupmanager.py ' . $backupOptions . '--backup ' . $tempBackupFileName);

	// create name for backup file in browser
	$dt = new DateTime('NOW');
	$backupFileName = 'moode_'. $_SESSION['hostname'].'_'. $dt->format('ymd_Hi').'.zip';

	header("Content-Description: File Transfer");
	header("Content-type: application/octet-stream");
	header("Content-Transfer-Encoding: binary");
	header("Content-Disposition: attachment; filename=" . $backupFileName);
	header("Content-length: " . filesize($tempBackupFileName));
	header("Pragma: no-cache");
	header("Expires: 0");
	readfile ($tempBackupFileName);

	// remove leftovers after backup
	unlink($tempBackupFileName);
	exit();
}
else if( isset($_POST['restore_start']) && $_POST['restore_start'] == '1' ) {
	if( isset($_FILES['restore_backupfile']) ) {
		$restoreOptions = '';
		if( isset($_POST['restore_system']) && $_POST['restore_system'] == '1' ) {
			$restoreOptions .= $restoreOptions ? ',config' : 'config';
		}
		if( isset($_POST['restore_camilladsp']) && $_POST['restore_camilladsp'] == '1' ) {
			$restoreOptions .= $restoreOptions ? ',cdsp' : 'cdsp';
		}
		if( isset($_POST['restore_radiostations_moode']) && $_POST['restore_radiostations_moode'] == '1' ) {
			$restoreOptions .= $restoreOptions ? ',r_sys' : 'r_sys';
		}
		if( isset($_POST['restore_radiostations_other']) && $_POST['restore_radiostations_other'] == '1' ) {
			$restoreOptions .= $restoreOptions ? ',r_other' : 'r_other';
		}

		if($restoreOptions) {
			$restoreOptions = '--what=' . $restoreOptions . ' ';
		}

		$tempBackupFileName = '/tmp/restore.zip';
		$configFileBaseName = $_FILES["restore_backupfile"]["name"];

		// move_uploaded_file($_FILES["restore_backupfile"]["tmp_name"], '/tmp/'.$configFileBaseName);
		move_uploaded_file($_FILES["restore_backupfile"]["tmp_name"], $tempBackupFileName);

		// print('/var/www/backupmananger.py ' . $restoreOptions . '--restore ' . $tempBackupFileName);
		sysCmd('/var/www/backupmananger.py ' . $restoreOptions . '--restore ' . $tempBackupFileName);

		//TODO: maybe reset file rights?

		unlink($tempBackupFileName);
		sysCmd('reboot');
	}
	else {
		print('no file');
	}
}

/**
 * Generate data for html templating
 */

 // Helper method to generate html code for toggle button
function genToggleButton($id, $value, $disabled) {
	$template = '
	<div class="toggle">
		<label class="toggle-radio" for="toggle_%id2">YES</label>
		<input type="radio" name="%id" id="toggle_%id1" value="1" %checked1>
		<label class="toggle-radio" for="toggle_%id1">NO</label>
		<input type="radio" name="%id" id="toggle_%id2" value="0" %checked0>
	</div>
	<div style="display: inline-block; vertical-align: top; margin-top: 2px;">
	  <a aria-label="Help" class="info-toggle" data-cmd="info-%id" href="#notarget"><i class="fas fa-info-circle"></i></a>
	</div>';

	return strtr($template , [
		'%id' => $id,
		' %checked1' => ($value == True ? 'checked="checked"': ''),
		' %checked0' => ($value != True ? 'checked="checked"': '')
	]);
}

$_backup_visible = (!(isset($_GET['action']) && $_GET['action'] == 'restore')) ? '': 'hidden';
$_restore_visible = (isset($_GET['action']) && $_GET['action'] == 'restore') ? '': 'hidden';


// toggle buttons for backup
$_togglebtn_backup_system = genToggleButton('backup_system', True, True);
$_togglebtn_backup_camilladsp = genToggleButton('backup_camilladsp', True, True);
$_togglebtn_backup_radiostations_moode = genToggleButton('backup_radiostations_moode', False, True);
$_togglebtn_backup_radiostations_other = genToggleButton('backup_radiostations_other', True, True);

// toggle buttons for restore
$_togglebtn_restore_system = genToggleButton('restore_system', True, True);
$_togglebtn_restore_camilladsp = genToggleButton('restore_camilladsp', True, True);
$_togglebtn_restore_radiostations_moode = genToggleButton('restore_radiostations_moode', False, True);
$_togglebtn_restore_radiostations_other = genToggleButton('restore_radiostations_other', True, True);


session_write_close();

waitWorker(1, 'backup');

$tpl = "backup.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
