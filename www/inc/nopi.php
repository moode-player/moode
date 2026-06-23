<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * moode-nopi: helpers specific to the non-Pi port.
 *
 * Kept in a dedicated file (require'd from common.php) so the port adds whole
 * new files rather than editing upstream moOde sources - which keeps the diff
 * small and the rebases onto new upstream tags clean.
 */

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
