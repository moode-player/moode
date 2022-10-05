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
 * PHP 8 equivalent functions (C) 2022 @Alberto-Bulpros (Git)
 *
 * These functions are initially used in the CUE support functions in
 * the file inc/common.php and can be removed when bumping to PHP 8.
 *
 */

function str_starts_with($haystack, $needle) {
	$result = false;
	$pos = strpos($haystack, $needle);

	if (false !== $pos) {
		$result = $pos == 0;
	}

	return $result;
}

function str_ends_with($haystack, $needle) {
	$result = false;
	$pos = strpos($haystack, $needle);

	if (false !== $pos) {
		$result = $pos == (strlen($haystack) - strlen($needle));
	}

	return $result;
}

function str_contains($haystack, $needle) {
	$result = false;
	$pos = strpos($haystack, $needle);

	if (false !== $pos) {
		$result = true;
	}

	return $result;
}
