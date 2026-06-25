<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * moode-nopi: cross-cutting helpers for the non-Pi port.
 *
 * Kept in a dedicated file (require'd from common.php, so available to the whole
 * web app and the daemons) rather than editing upstream moOde sources - which
 * keeps the diff small and the rebases onto new upstream tags clean. Worker-only
 * fork hooks live separately in nopi-worker.php (require'd from worker.php).
 */

//----------------------------------------------------------------------------//
// PLATFORM DETECTION
//----------------------------------------------------------------------------//

// Returns true when running on Raspberry Pi hardware, false on generic x86/other
// platforms. Used to skip Pi-only boot/hardware logic (config.txt overlays,
// vcgencmd, GPIO HATs, LED/fan control) so the same codebase runs on Pi, x86 and
// other SBCs. Lives here (not common.php) to keep the upstream diff minimal.
function isPi() {
	static $isPi = null;
	if ($isPi === null) {
		// Detect a Raspberry Pi by its device-tree model ("Raspberry Pi 4 Model B ...").
		// The previous test (any "Revision" line in /proc/cpuinfo) is true on EVERY
		// 32-bit ARM board: the armhf cpuinfo format always carries Revision/Hardware/
		// Serial lines, so non-Pi SBCs (e.g. Allwinner H3 / Orange Pi) tested as a Pi and
		// ran the Pi-only boot-config logic in worker.php, which reboot-loops. (arm64
		// cpuinfo omits those lines, which is why this only bit 32-bit boards.) Matching
		// the model keeps every real Pi true and all other boards false; on x86 the file
		// is absent -> false. Falls back to the cpuinfo Model line if device-tree is unread.
		$model = @file_get_contents('/proc/device-tree/model');
		if ($model === false) {
			$model = @shell_exec("awk -F': ' '/^Model/{print \$2}' /proc/cpuinfo");
		}
		$isPi = ($model !== false && $model !== null && strpos($model, 'Raspberry Pi') !== false);
	}
	return $isPi;
}

// Our fork's repo, queried anonymously over HTTPS for the nopi update check (a
// deployed box has no SSH credentials, and the local clone's origin may be SSH).
const NOPI_REPO_URL      = 'https://github.com/Gjuju/moode-nopi.git';
// Daily caches (by file mtime) so the System config page never makes a network
// call more than once a day per check.
const NOPI_LATEST_CACHE  = '/var/local/www/nopi_latest';
const MOODE_UPDATE_CACHE = '/var/local/www/moode_update';
const NOPI_LATEST_MAXAGE = 86400; // 1 day

// Running moode-nopi release = the git tag, stamped into a plain file by
// install.sh at deploy time (`git describe`). Read offline at runtime, no git
// or network dependency. Returns '' when the file is absent (e.g. a real Pi,
// where there is no nopi version) so callers can simply hide the UI line.
function getNopiRel() {
	$f = '/var/local/www/nopi_version';
	return is_file($f) ? trim(file_get_contents($f)) : '';
}

// --- moode-nopi update (our fork; tags 'X.Y.Z-nopi.N' via git ls-remote) -------

// Parse 'X.Y.Z-nopi.N' into a comparable [major, minor, patch, nopi] tuple,
// ignoring any trailing '-<n>-g<hash>' that `git describe` adds when the
// deployed tree is ahead of its tag. Returns null when it does not match.
function nopiVerTuple($v) {
	if (preg_match('/(\d+)\.(\d+)\.(\d+)-nopi\.(\d+)/', (string)$v, $m)) {
		return [(int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4]];
	}
	return null;
}

// Compare two nopi version strings: -1 if $a < $b, 0 if equal/uncomparable, 1 if greater.
function nopiVerCmp($a, $b) {
	$ta = nopiVerTuple($a);
	$tb = nopiVerTuple($b);
	if ($ta === null || $tb === null) {
		return 0;
	}
	for ($i = 0; $i < 4; $i++) {
		if ($ta[$i] !== $tb[$i]) {
			return $ta[$i] < $tb[$i] ? -1 : 1;
		}
	}
	return 0;
}

// Latest nopi tag on the remote, cached by file mtime so the config page makes
// at most one (bounded) network call per MAXAGE. Returns '' when unknown
// (offline with no prior cache). Safe as www-data: the cache lives in the
// www-data-owned /var/local/www.
function getNopiLatest() {
	$cache = NOPI_LATEST_CACHE;
	if (is_file($cache) && (time() - filemtime($cache)) < NOPI_LATEST_MAXAGE) {
		return trim(file_get_contents($cache));
	}
	// `timeout` keeps a dead network from hanging the page.
	$cmd = 'timeout 8 git ls-remote --tags --refs ' . escapeshellarg(NOPI_REPO_URL) .
		" 2>/dev/null | sed -n 's#.*refs/tags/##p' | grep -E '\\-nopi\\.[0-9]+\$' | sort -V | tail -1";
	$latest = trim(sysCmd($cmd)[0] ?? '');
	if ($latest === '' && is_file($cache)) {
		$latest = trim(file_get_contents($cache)); // keep last known on failure
	}
	// (Re)write so mtime advances even on failure -> bounded to one probe/MAXAGE.
	@file_put_contents($cache, $latest . "\n");
	return $latest;
}

// If a newer nopi tag than the running one exists, return it; otherwise ''.
function getNopiUpdate() {
	$latest = getNopiLatest();
	return nopiVerCmp($latest, getNopiRel()) === 1 ? $latest : '';
}

// --- upstream moOde update (reuse moOde's own official channel) -----------------
// moOde already ships an update check: checkForUpd() fetches update-<pkgid>.txt
// from res_software_upd_url (a public GitHub-raw file, platform-agnostic) and it
// works on x86 too. We reuse it here purely informationally - to flag when a
// newer official moOde release exists (a candidate to rebase the port onto) -
// using the same available-vs-running Date comparison as sys-config.php's
// "Check for update" handler. Result cached daily so the page stays snappy.
function getMoodeUpdate() {
	$cache = MOODE_UPDATE_CACHE;
	if (is_file($cache) && (time() - filemtime($cache)) < NOPI_LATEST_MAXAGE) {
		return trim(file_get_contents($cache));
	}
	$out = '';
	if (!empty($_SESSION['res_software_upd_url'])) {
		$avail = checkForUpd($_SESSION['res_software_upd_url'] . '/');
		$availDate = isset($avail['Date']) ? strtotime($avail['Date']) : false;
		$runParts  = explode(' ', getMoodeRel('verbose'));
		$runDate   = isset($runParts[1]) ? strtotime($runParts[1]) : false;
		if ($availDate !== false && $runDate !== false && $availDate > $runDate && !empty($avail['Release'])) {
			$out = $avail['Release'];
		}
	}
	@file_put_contents($cache, $out . "\n");
	return $out;
}

//----------------------------------------------------------------------------//
// SYSTEM DRIVES (protect the OS disk from being offered as a music source)
//----------------------------------------------------------------------------//

// Base block devices (e.g. 'sda', 'nvme0n1', 'mmcblk0') that carry the running
// OS - whatever device backs /, /boot, /boot/efi or an active swap. moOde assumes
// the OS lives on the SD card (mmcblk, which the NVMe/SATA scans never match); on
// generic x86/other hardware it usually sits on a SATA SSD or NVMe, which must
// NEVER be offered as a formattable/mountable music source (a Format would mkfs
// the boot disk). Returns whole-disk kernel names to exclude. Used by
// nvmeListDrives()/sataListDrives() in music-source.php (isPi()-guarded).
function getSystemDrives() {
	$bases = array();
	// Query each mountpoint separately: a single multi-target findmnt returns
	// nothing (rc=1) as soon as one target isn't a mountpoint (e.g. /boot folded
	// into /). Swap devices come from /proc/swaps (no swapon/PATH dependency).
	$sources = sysCmd('findmnt -no SOURCE / 2>/dev/null; findmnt -no SOURCE /boot 2>/dev/null; findmnt -no SOURCE /boot/efi 2>/dev/null; awk \'NR>1{print $1}\' /proc/swaps 2>/dev/null');
	foreach ($sources as $src) {
		$src = trim($src);
		if (strpos($src, '/dev/') !== 0) {
			continue; // skip non-block backings (tmpfs, zram, overlay, swapfiles)
		}
		// A partition resolves to its parent whole disk via PKNAME; a whole disk
		// (or a device with no parent) reports an empty PKNAME -> use its own name.
		$pkname = trim(sysCmd('lsblk -no PKNAME ' . $src . ' 2>/dev/null')[0] ?? '');
		$bases[$pkname !== '' ? $pkname : basename($src)] = true;
	}

	return array_keys($bases);
}

// True if $device (a /dev basename like 'nvme0n1', 'nvme0n1p2' or 'sda1') is, or
// is a partition of, one of the system whole disks in $systemDrives.
function isSystemDrive($device, $systemDrives) {
	foreach ($systemDrives as $base) {
		if ($device === $base || preg_match('/^' . preg_quote($base, '/') . 'p?[0-9]+$/', $device)) {
			return true;
		}
	}

	return false;
}
