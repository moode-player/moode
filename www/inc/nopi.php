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

// Upstream repo, queried anonymously over HTTPS for the update check (the local
// clone's origin may be SSH, which a deployed box cannot authenticate to).
const NOPI_REPO_URL      = 'https://github.com/Gjuju/moode-nopi.git';
// Cache of the latest remote tag, refreshed at most once per MAXAGE so the
// System config page never makes a network call more than daily.
const NOPI_LATEST_CACHE  = '/var/local/www/nopi_latest';
const NOPI_LATEST_MAXAGE = 86400; // 1 day

// Running moode-nopi release = the git tag, stamped into a plain file by
// install.sh at deploy time (`git describe`). Read offline at runtime, no git
// or network dependency. Returns '' when the file is absent (e.g. a real Pi,
// where there is no nopi version) so callers can simply hide the UI line.
function getNopiRel() {
	$f = '/var/local/www/nopi_version';
	return is_file($f) ? trim(file_get_contents($f)) : '';
}

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

// Latest moode-nopi tag on the remote, cached by file mtime so the config page
// makes at most one (bounded) network call per MAXAGE. Returns '' when unknown
// (offline with no prior cache). Safe to call as www-data: the cache lives in
// the www-data-owned /var/local/www, and sysCmd() runs the bounded git probe.
function getNopiLatest() {
	$cache = NOPI_LATEST_CACHE;
	if (is_file($cache) && (time() - filemtime($cache)) < NOPI_LATEST_MAXAGE) {
		return trim(file_get_contents($cache));
	}
	// `timeout` keeps a dead network from hanging the page; the pipe stages run
	// as the web user (only the git probe needs no privilege anyway).
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
