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

define('TMP_BACKUP_ZIP', '/tmp/backup.zip');
define('TMP_MOODECFG_INI', '/tmp/moodecfg.ini');
define('TMP_RESTORE_ZIP', '/tmp/restore.zip');
define('TMP_SCRIPT_FILE', '/tmp/script');
define('BACKUP_FILE_PREFIX', 'backup_');

playerSession('open', '' ,'');

/**
 * Post parameter processing
 */

// Backup
if( isset($_POST['backup_create']) && $_POST['backup_create'] == '1' ) {
	$backupOptions = '';
	if( isset($_POST['backup_system']) && $_POST['backup_system'] == '1' ) {
		$backupOptions .= $backupOptions ? ' config' : 'config';
	}
	if( isset($_POST['backup_camilladsp']) && $_POST['backup_camilladsp'] == '1' ) {
		$backupOptions .= $backupOptions ? ' cdsp' : 'cdsp';
	}
	if( isset($_POST['backup_radiostations_moode']) && $_POST['backup_radiostations_moode'] == '1' ) {
		$backupOptions .= $backupOptions ? ' r_moode' : 'r_moode';
	}
	if( isset($_POST['backup_radiostations_other']) && $_POST['backup_radiostations_other'] == '1' ) {
		$backupOptions .= $backupOptions ? ' r_other' : 'r_other';
	}

	if($backupOptions) {
		$backupOptions = '--what ' . $backupOptions . ' ';
	}

	if( isset($_POST['backup_wlan0pwd']) && $_POST['backup_wlan0pwd']  ) {
		$backupOptions .= '--wlanpwd ' . $_POST['backup_wlan0pwd'] . ' ';
	}

	if (file_exists(TMP_SCRIPT_FILE)) {
		$backupOptions .= '--script ' . TMP_SCRIPT_FILE . ' ';
		sysCmd('chown pi:pi ' . TMP_SCRIPT_FILE);
	}

	// Generate backup zip
	sysCmd('/var/www/command/backupmanager.py ' . $backupOptions . '--backup ' . TMP_BACKUP_ZIP);
	//workerLog('Options: ' . $backupOptions);

	// Create name for backup file in browser
	$dt = new DateTime('NOW');
	$backupFileName = BACKUP_FILE_PREFIX . $_SESSION['hostname'].'_'. $dt->format('ymd_Hi').'.zip';

	header("Content-Description: File Transfer");
	header("Content-type: application/octet-stream");
	header("Content-Transfer-Encoding: binary");
	header("Content-Disposition: attachment; filename=" . $backupFileName);
	header("Content-length: " . filesize(TMP_BACKUP_ZIP));
	header("Pragma: no-cache");
	header("Expires: 0");
	readfile (TMP_BACKUP_ZIP);
	sysCmd('rm ' . TMP_BACKUP_ZIP);
	exit();
}
// Restore
else if( isset($_POST['restore_start']) && $_POST['restore_start'] == '1' ) {
	if (file_exists(TMP_RESTORE_ZIP)) {
		$restoreOptions = '';

		if( isset($_POST['restore_system']) && $_POST['restore_system'] == '1' ) {
			$restoreOptions .= $restoreOptions ? ' config' : 'config';
		}
		if( isset($_POST['restore_camilladsp']) && $_POST['restore_camilladsp'] == '1' ) {
			$restoreOptions .= $restoreOptions ? ' cdsp' : 'cdsp';
		}
		if( isset($_POST['restore_radiostations_moode']) && $_POST['restore_radiostations_moode'] == '1' ) {
			$restoreOptions .= $restoreOptions ? ' r_moode' : 'r_moode';
		}
		if( isset($_POST['restore_radiostations_other']) && $_POST['restore_radiostations_other'] == '1' ) {
			$restoreOptions .= $restoreOptions ? ' r_other' : 'r_other';
		}

		if($restoreOptions) {
			$restoreOptions = '--what ' . $restoreOptions . ' ';
		}

		// TODO: Maybe reset file rights after backupmanager.py ?
		sysCmd('/var/www/command/backupmananger.py ' . $restoreOptions . '--restore ' . TMP_RESTORE_ZIP);
		sysCmd('rm ' . TMP_RESTORE_ZIP);

		// Automatically reboot
		if( empty($restoreOptions) || (isset($_POST['restore_system']) && $_POST['restore_system'] == '1') ) {
			submitJob('reboot', '', 'Restore complete', 'System rebooting...');
		}

		// DEBUG:
		//$_SESSION['notify']['title'] = 'DEBUG';
		//$_SESSION['notify']['msg'] = $restoreOptions;
		//$_SESSION['notify']['duration'] = 10;
	}
	else {
		$_imported_backupfile = 'No file selected';
		$_SESSION['notify']['title'] = 'Select a backup file';
		$_SESSION['notify']['duration'] = 3;
	}
}
else if (isset($_POST['import_backupfile'])) {
	$_imported_backupfile = 'Uploaded: <i>' . $_FILES['restore_backupfile']['name'] . '</i>';
	rename($_FILES['restore_backupfile']['tmp_name'], TMP_RESTORE_ZIP);
	// NOTE: File stat is 0600/-rw-------, www-data:www-data
	//workerLog('Imported backup: ' . print_r($_FILES['restore_backupfile'], true));
}
else if (isset($_POST['import_scriptfile'])) {
	$_imported_scriptfile = 'Uploaded: <i>' . $_FILES['backup_scriptfile']['name'] . '</i>';
	rename($_FILES['backup_scriptfile']['tmp_name'], TMP_SCRIPT_FILE);
	// NOTE: File stat is 0600/-rw-------, www-data:www-data
	//workerLog('Imported script: ' . print_r($_FILES['backup_scriptfile'], true));
}
else if (isset($_POST['reset_options'])) {
	sysCmd('rm /tmp/backup.zip /tmp/moodecfg.ini /tmp/restore.zip /tmp/py.log /tmp/script');
	$_imported_backupfile = 'No file selected';
	$_SESSION['notify']['title'] = 'Options have been reset';
	$_SESSION['notify']['duration'] = 3;
}
else {
	$_imported_backupfile = 'No file selected';
	$_imported_scriptfile = 'No file selected';
}

session_write_close();

/**
 * Generate data for html templating
 */

 // Helper method to generate html code for toggle button
function genToggleButton($id, $value, $disabled) {
	$template = '
	<div class="toggle" %disable_style>
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
		' %checked0' => ($value != True ? 'checked="checked"': ''),
		'%disable_style' => ($disabled == True ? 'style="pointer-events:none;"': '')

	]);
}

// Backup toggles
if (isset($_GET['action']) && $_GET['action'] == 'backup') {
	$_restore_hidden = 'hidden';
	$_togglebtn_backup_system = genToggleButton('backup_system', True, False);
	$_togglebtn_backup_camilladsp = genToggleButton('backup_camilladsp', True, False);
	$_togglebtn_backup_radiostations_moode = genToggleButton('backup_radiostations_moode', True, False);
	$_togglebtn_backup_radiostations_other = genToggleButton('backup_radiostations_other', True, False);
}
// Restore toggles
else if (isset($_GET['action']) && $_GET['action'] == 'restore') {
	$_backup_hidden = 'hidden';
	$backupOptions = array();
	$backupOptions = file_exists(TMP_RESTORE_ZIP) ? sysCmd('/var/www/command/backupmanager.py --info ' . TMP_RESTORE_ZIP) : $backupOptions;
	//workerLog(print_r($backupOptions, true));
	$_togglebtn_restore_system = genToggleButton('restore_system', in_array('config', $backupOptions), !in_array('config', $backupOptions));
	$_togglebtn_restore_camilladsp = genToggleButton('restore_camilladsp', in_array('cdsp', $backupOptions), !in_array('cdsp', $backupOptions));
	$_togglebtn_restore_radiostations_moode = genToggleButton('restore_radiostations_moode', in_array('r_moode', $backupOptions), !in_array('r_moode', $backupOptions));
	$_togglebtn_restore_radiostations_other = genToggleButton('restore_radiostations_other', in_array('r_other', $backupOptions), !in_array('r_other', $backupOptions));
}

waitWorker(1, 'backup');

$tpl = "backup.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
