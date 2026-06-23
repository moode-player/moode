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

// Running moode-nopi release = the git tag, stamped into a plain file by
// install.sh at deploy time (`git describe`). Read offline at runtime, no git
// or network dependency. Returns '' when the file is absent (e.g. a real Pi,
// where there is no nopi version) so callers can simply hide the UI line.
function getNopiRel() {
	$f = '/var/local/www/nopi_version';
	return is_file($f) ? trim(file_get_contents($f)) : '';
}
