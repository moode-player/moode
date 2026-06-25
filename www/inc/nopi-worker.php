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

// Apply on/off to one discovered SBC LED node (the non-Pi analog of worker.php's
// inline ACT/PWR writes). "off" parks the trigger and clears brightness; "on"
// restores a sensible trigger - a heartbeat blink for the activity LED (the closest
// analog to the Pi's mmc0 SD-activity trigger) and steady default-on for the power
// LED (both are standard kernel LED triggers present on the supported boards).
// $node is a leaf name from nopiDetectLeds(); an empty $node is a silent no-op, so
// nothing breaks on hardware that exposes no such LED. Never reached on the Pi.
function nopiWriteLed($node, $on, $isActivity) {
	if ($node === '') {
		return; // no matching LED on this board - do nothing
	}
	$base = '/sys/class/leds/' . $node;
	if ($on) {
		$trigger = $isActivity ? 'heartbeat' : 'default-on';
		sysCmd('echo ' . $trigger . ' | sudo tee ' . $base . '/trigger > /dev/null');
	} else {
		sysCmd('echo none | sudo tee ' . $base . '/trigger > /dev/null');
		sysCmd('echo 0 | sudo tee ' . $base . '/brightness > /dev/null');
	}
}

// Initialise the SBC status/power LEDs at worker startup from the saved led_state
// "actled,pwrled" pair. Discovers the nodes at runtime (names vary by board) and
// applies the saved state; boards with no discoverable node (most x86) simply log
// n/a and change nothing. Called only off the Pi - the Pi keeps its own inline
// ACT/PWR block in worker.php.
function nopiInitLeds() {
	$leds = nopiDetectLeds();
	list($act, $pwr) = array_pad(explode(',', $_SESSION['led_state']), 2, '1');
	if ($leds['actled'] !== '') {
		nopiWriteLed($leds['actled'], $act != '0', true);
		workerLog('worker: Sys LED0:      ' . ($act == '0' ? 'off' : 'on') . ' (' . $leds['actled'] . ')');
	} else {
		workerLog('worker: Sys LED0:      n/a (no status LED on this board)');
	}
	if ($leds['pwrled'] !== '') {
		nopiWriteLed($leds['pwrled'], $pwr != '0', false);
		workerLog('worker: Sys LED1:      ' . ($pwr == '0' ? 'off' : 'on') . ' (' . $leds['pwrled'] . ')');
	} else {
		workerLog('worker: Sys LED1:      n/a (no power LED on this board)');
	}
}

// Apply a single LED toggle job ('actled' or 'pwrled') off the Pi. Re-discovers the
// node (cheap sysfs glob) and writes it; a missing node is a no-op. Called from the
// worker's job switch, replacing the Pi-only ACT/PWR writes on non-Pi hardware.
function nopiSetLed($which, $value) {
	$leds = nopiDetectLeds();
	$node = $which == 'actled' ? $leds['actled'] : $leds['pwrled'];
	nopiWriteLed($node, $value != '0', $which == 'actled');
}
