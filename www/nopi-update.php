<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * moode-nopi: install the latest moode-nopi release (Configure > System).
 * Posted to by the Install button of sys-config.html (formaction), so
 * sys-config.php itself stays untouched.
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';

const NOPI_UPDATE_UNIT = 'moode-nopi-update';

phpSession('open');

if (isset($_POST['nopi_install_update']) && getNopiUpdate() !== '') {
	if (sysCmd('systemctl is-active ' . NOPI_UPDATE_UNIT)[0] == 'active') {
		$_SESSION['notify']['title'] = NOTIFY_TITLE_ALERT;
		$_SESSION['notify']['msg'] = 'An update is already in progress.';
		phpSession('close');
		header('location: sys-config.php');
		exit();
	}
	sysCmd('systemd-run --unit=' . NOPI_UPDATE_UNIT . ' --collect --setenv=HOME=/root /var/www/util/nopi-update.sh');
	phpSession('close');
	header('location: sys-status.php');
	exit();
}

phpSession('close');
header('location: sys-config.php');
