<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * moode-nopi: worker-daemon hooks for the non-Pi port.
 *
 * Extracted from worker.php so the fork's larger non-Pi startup blocks live in a
 * fork-only file (require'd from worker.php) instead of inline in an upstream-
 * churned file - keeping the worker.php diff small and the rebases onto new moOde
 * tags cleaner. Each function is reached only on non-Pi hardware (by an isPi()
 * guard, or a code path a Pi never enters), so Pi behaviour is byte-identical.
 * They run in worker.php's context (common.php + alsa/mpd incs already loaded),
 * using sysCmd/phpSession/sqlUpdate/updMpdConf/workerLog/getAlsaDeviceNames.
 */

// On non-Pi hardware the Pi HDMI default ALSA card does not exist, so the worker
// can land on ALSA_EMPTY_CARD with no output set. Auto-select the first real ALSA
// card (e.g. a USB DAC), else leave the output unset for the user to pick in the
// UI - never force a Pi device, which would stall MPD startup waiting on a
// non-existent output. Returns true when no audio device was found.
function nopiAutoSelectAlsaCard($dbh) {
	$deviceNames = getAlsaDeviceNames();
	$pickNum = ALSA_EMPTY_CARD;
	$pickName = '';
	foreach ($deviceNames as $num => $name) {
		if ($name != ALSA_EMPTY_CARD && $name != ALSA_LOOPBACK_DEVICE && $name != ALSA_DUMMY_DEVICE) {
			$pickNum = $num;
			$pickName = $name;
			break;
		}
	}
	if ($pickNum != ALSA_EMPTY_CARD) {
		phpSession('write', 'adevname', $pickName);
		phpSession('write', 'cardnum', $pickNum);
		sqlUpdate('cfg_mpd', $dbh, 'device', $pickNum);
		phpSession('write', 'alsa_output_mode', 'plughw');
		updMpdConf();
		sysCmd('systemctl restart mpd');
		workerLog('worker: ALSA card:     auto-selected ' . $pickName . ' (card ' . $pickNum . ')');
		return false;
	}
	workerLog('worker: ALSA card:     no audio device found; output left unset');
	return true;
}

// Reconcile an invalid Hardware mixer_type on non-Pi. On x86 the worker may
// auto-select a USB DAC (the Pi HDMI default does not exist), and after
// --reset-db the seeded Pi default mixer_type is "hardware". A DAC with no ALSA
// volume control (amixname == none) cannot do hardware volume: the UI suppresses
// the Hardware option and silently shows Software, while mpd.conf keeps "hardware"
// so the volume knob reads 0. Downgrade to software so the stored value matches
// what the device can actually do. No-op on the Pi (isPi guard) and whenever the
// device does have a hardware mixer.
function nopiReconcileMixerType($dbh) {
	if (isPi() || $_SESSION['amixname'] != 'none' || $_SESSION['mpdmixer'] != 'hardware') {
		return;
	}
	sqlUpdate('cfg_mpd', $dbh, 'mixer_type', 'software');
	phpSession('write', 'mpdmixer', 'software');
	updMpdConf();
	sysCmd('systemctl restart mpd');
	workerLog('worker: MPD mixer:     card has no hardware volume; mixer_type -> software');
}
