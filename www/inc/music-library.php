<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * tsunamp player ui (C) 2013 Andrea Coiutti & Simone De Gregori
 * http://www.tsunamp.com
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
 * This is based on the @chris-rudmin 2019-08-08 rewrite of the GenLibrary()
 * function to support the new Library renderer /var/www/js/scripts-library.js
 * Refer to https://github.com/moode-player/moode/pull/16 for more info.
 *
 */

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/mpd.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/sql.php';

// Caching library loader for Tag and Album views
function loadLibrary($sock) {
	if (filesize(libcacheFile()) != 0) {
		return file_get_contents(libcacheFile());
	} else {
		$flat = genFlatList($sock);
		if ($flat != '') {
			// Normal or UTF8 replace
			if ($_SESSION['library_utf8rep'] == 'No') {
				$jsonLib = genLibrary($flat);
			} else {
				$jsonLib = genLibraryUTF8Rep($flat);
			}

			return $jsonLib;
		} else {
			return '';
		}
	}
}

// Generate flat list from MPD tag database
function genFlatList($sock) {
	// Get root list
	sendMpdCmd($sock, 'lsinfo');
	$resp = readMpdResp($sock);
	$dirs = array();
	$line = strtok($resp, "\n");
	$i = 0;

	// Use directories only and exclude RADIO
	while ($line) {
		list($param, $value) = explode(': ', $line, 2);

		if ($param == 'directory' && $value != 'RADIO') {
			$dirs[$i] = $value;
			$i++;
		}

		$line = strtok("\n");
	}

	// Get metadata
	$resp = '';
	foreach ($dirs as $dir) {
		// NOTE: MPD must be compiled with libpcre++-dev to make use of PERL compatible regex
		// NOTE: Logic in genLibrary() determines whether M4A format is Lossless or Lossy
		switch ($_SESSION['library_flatlist_filter']) {
			// Return full library
			case 'full_lib':
			// These filters are in genLibrary()
			case 'encoded':
			case 'hdonly':
			case 'year':
				$cmd = "search base \"" . $dir . "\"";
				break;
			// Advanced search dialog
			case 'tags':
				$cmd = "search \"((base '" . $dir . "') AND (" . $_SESSION['library_flatlist_filter_str'] . "))\"";
				break;
			// Filter on specific tag containing the string or if string is empty perform an 'any' filter
			case 'album':
			case 'albumartist':
			case 'any':
			case 'artist':
			case 'composer':
			case 'conductor':
			case 'file':
			case 'genre':
			case 'label':
			case 'performer':
			case 'title':
			case 'work':
				$tag = empty($_SESSION['library_flatlist_filter_str']) ? 'any' : $_SESSION['library_flatlist_filter'];
				$str = empty($_SESSION['library_flatlist_filter_str']) ? $_SESSION['library_flatlist_filter'] : $_SESSION['library_flatlist_filter_str'];
				$cmd = "search \"((base '" . $dir . "') AND (" . $tag . " contains '" . $str . "'))\"";
				break;
			// Filter on file path or extension
			// NOTE: Lossless and Lossy have an additional M4A probe in genLibrary()
			case 'folder':
			case 'format':
				$cmd = "search \"((base '" . $dir . "') AND (file contains '" . $_SESSION['library_flatlist_filter_str'] . "'))\"";
				break;
			case 'lossless':
				$cmd = "search \"((base '" . $dir . "') AND (file =~ 'm4a$|flac$|aif$|aiff$|wav$|dsf$|dff$'))\"";
				break;
			case 'lossy':
				$cmd = "search \"((base '" . $dir . "') AND (file !~ 'flac$|aif$|aiff$|wav$|dsf$|dff$'))\"";
				break;
		}

		sendMpdCmd($sock, $cmd);
		$resp .= readMpdResp($sock);
	}

	if (!is_null($resp)) {
		$lines = explode("\n", $resp);
		$item = 0;
		$flat = array();
		$lineCount = count($lines);

		for ($i = 0; $i < $lineCount; $i++) {
			list($element, $value) = explode(': ', $lines[$i], 2);

			if ($element == 'OK') {
				// NOTE: Skip any ACK's
			} else if ($element == 'file') {
				$item = count($flat);
				$flat[$item][$element] = $value;
			} else if ($element == 'Genre') {
				if ($_SESSION['library_tagview_genre'] == 'Genres') {
					if ($flat[$item]['Genre']) {
						array_push($flat[$item]['Genre'], $value);
					} else {
						$flat[$item]['Genre'] = array($value);
					}
				}
			} else if ($element == 'Composer') {
 				if ($_SESSION['library_tagview_genre'] == 'Composers') {
					if ($flat[$item]['Genre']) {
						array_push($flat[$item]['Genre'], $value);
					} else {
						$flat[$item]['Genre'] = array($value);
					}
				}
				$flat[$item][$element] = $value; // NOTE: Uncomment if Composer is included in output of GenLibrary()
			} else if ($element == 'Artist') {
				if ($flat[$item]['Artist']) {
					array_push($flat[$item]['Artist'], $value);
				} else {
					$flat[$item]['Artist'] = array($value);
				}
			} else if ($element == 'Performer') { // @Atair: Add performers to artists
				if ($flat[$item]['Artist']) {
					array_push($flat[$item]['Artist'], $value);
				} else {
					$flat[$item]['Artist'] = array($value);
				}
				//$flat[$item][$element] = $value; // NOTE: Uncomment if Performer is included in output of GenLibrary()
			} else if ($element == 'Conductor') { // @Atair: Add conductor to artists
				if ($flat[$item]['Artist']) {
					array_push($flat[$item]['Artist'], $value);
				} else {
					$flat[$item]['Artist'] = array($value);
				}
				//$flat[$item][$element] = $value; // NOTE: Uncomment if Conductor is included in output of GenLibrary()
			} else {
				$flat[$item][$element] = $value;
			}
		}

		return $flat;
	} else {
		return '';
	}
}

// Generate library array (based on @chris-rudmin 2019-08-08 rewrite)
function genLibrary($flat) {
	$lib = array();

	// Break out misc lib options
	// [0] = Include comment tag: Yes | No
	// [1] = Album Key: Album@Artist (Default) | Album@Artist@AlbumID | FolderPath | FolderPath@AlbumID
	$incCommentTag = explode(',', $_SESSION['library_misc_options'])[0];
	$incMbrzAlbumid = strpos(explode(',', $_SESSION['library_misc_options'])[1], 'AlbumID') !== false ? 'Yes' : 'No';

	// Setup date range filter
	if ($_SESSION['library_flatlist_filter'] == 'year') {
		$filterYear = explode('-', $_SESSION['library_flatlist_filter_str']);
		if (!isset($filterYear[1])) {
	 		$filterYear[1] = $filterYear[0];
		}
	}

	foreach ($flat as $flatData) {
		// M4A probe for ALAC when filtering by Lossless/Lossy
		if (strpos($_SESSION['library_flatlist_filter'], 'loss') !== false && getFileExt($flatData['file']) == 'm4a') {
			$fh = fopen(MPD_MUSICROOT . $flatData['file'], "rb");
			$alacFound = strpos(fread($fh, 512), 'alac');
			fclose($fh);

			if ($_SESSION['library_flatlist_filter'] == 'lossless' && $alacFound !== false) {
				$push = true;
			} elseif ($_SESSION['library_flatlist_filter'] == 'lossy' && $alacFound === false) {
				$push = true;
			} else {
				$push = false;
			}
		} else {
			$push = true;
		}

		// Date range filter
		if ($_SESSION['library_flatlist_filter'] == 'year') {
			$trackYear = getTrackYear($flatData);
			$push = ($trackYear >= $filterYear[0] && $trackYear <= $filterYear[1]) ? true : false;
		}

		// Encoded or HD filter
		if ($_SESSION['library_flatlist_filter'] == 'encoded' || $_SESSION['library_flatlist_filter'] == 'hdonly') {
			$encodedAt = getEncodedAt($flatData, 'default', true);

			if ($_SESSION['library_flatlist_filter'] == 'encoded') {
				$push = strpos($encodedAt, $_SESSION['library_flatlist_filter_str']) !== false ? true : false;
			} else {
				$push = strpos($encodedAt, 'h', -1) !== false ? true : false;
			}
		}

		if ($push === true) {
			$songData = array(
				'file' => $flatData['file'],
				'tracknum' => ($flatData['Track'] ? $flatData['Track'] : ''),
				'title' => ($flatData['Title'] ? $flatData['Title'] : 'Unknown Title'),
				'disc' => ($flatData['Disc'] ? $flatData['Disc'] : '1'),
				//@Atair:
				// 1. artist can safely be empty, because it is no longed used as substitute for missing album_artist
				// 2. album_artist shall never be empty, otherwise the sort routines in scripts-library.js complain,
				//    because they expect artist as string and not as array in album_artist || artist constructs
				// 3. When AlbumArtist is not defined and artist contains a single value, it is assumed that Artist should be a surrogate for ALbumArtist.
				//    otherwise, when Artist is an array of two and more values or empty, the AlbumArtist is set to 'Unknown' (this is regarded as bad tagging)
				'artist' => ($flatData['Artist'] ? $flatData['Artist'] : array()), //@Atair: array is expected in scripts-library.js even when empty
				'album_artist' => ($flatData['AlbumArtist'] ? $flatData['AlbumArtist'] : (count($flatData['Artist']) == 1 ? $flatData['Artist'][0] : 'Unknown AlbumArtist')),
				'composer' => ($flatData['Composer'] ? $flatData['Composer'] : 'Composer tag missing'),
				//'performer' => ($flatData['Performer'] ? $flatData['Performer'] : 'Performer tag missing'),
				//'conductor' => ($flatData['Conductor'] ? $flatData['Conductor'] : 'Conductor tag missing'),
				'year' => getTrackYear($flatData),
				'time' => $flatData['Time'],
				'album' => ($flatData['Album'] ? htmlspecialchars($flatData['Album']) : 'Unknown Album'),
				'genre' => ($flatData['Genre'] ? $flatData['Genre'] : array('Unknown')), // @Atair: 'Unknown' genre has to be an array
				'time_mmss' => formatSongTime($flatData['Time']),
				'last_modified' => $flatData['Last-Modified'],
				'encoded_at' => getEncodedAt($flatData, 'default', true),
				'comment' => (($flatData['Comment'] && $incCommentTag == 'Yes') ? $flatData['Comment'] : ''),
				'mb_albumid' => (($flatData['MUSICBRAINZ_ALBUMID'] && $incMbrzAlbumid == 'Yes') ? $flatData['MUSICBRAINZ_ALBUMID'] : '0')
			);

			array_push($lib, $songData);
		}
	}

	// No filter results or empty MPD database
	if (count($lib) == 1 && empty($lib[0]['file'])) {
		$lib[0]['file'] = '';
		$lib[0]['title'] = '';
		$lib[0]['artist'] = array('');
		$lib[0]['album'] = 'Nothing found';
		$lib[0]['album_artist'] = '';
		$lib[0]['genre'] = array('');
		$lib[0]['time_mmss'] = '';
		$lib[0]['encoded_at'] = '0';
	}

	if (false === ($jsonLib = json_encode($lib, JSON_INVALID_UTF8_SUBSTITUTE))) {
		workerLog('genLibrary(): Error: json_encode($lib) failed');
	}

	if (false === (file_put_contents(libcacheFile(), $jsonLib))) {
		workerLog('genLibrary(): Error: file create failed: ' . libcacheFile());
	}

	debugLog('genLibrary(): jsonErrorMessage(): ' . jsonErrorMessage());
	return $jsonLib;
}

function libcacheFile() {
	switch ($_SESSION['library_flatlist_filter']) {
		case 'full_lib':
			$suffix = '_all.json';
			break;
		case 'folder':
		case 'format':
		case 'hdonly':
		case 'lossless':
		case 'lossy':
			$suffix = '_' . strtolower($_SESSION['library_flatlist_filter']) . '.json';
			break;
		case 'album':
		case 'any':
		case 'artist':
		case 'composer':
		case 'conductor':
		case 'encoded':
		case 'file':
		case 'genre':
		case 'label':
		case 'performer':
		case 'tags': // Indicates filter was submitted via Adv search
		case 'title':
		case 'work':
		case 'year':
			$suffix = '_tag.json';
			break;
	}

	return LIBCACHE_BASE . $suffix;
}

function jsonErrorMessage() {
	switch (json_last_error()) {
		case JSON_ERROR_NONE:
			$msg = 'No errors';
			break;
		case JSON_ERROR_DEPTH:
			$msg = 'Maximum stack depth exceeded';
			break;
		case JSON_ERROR_STATE_MISMATCH:
			$msg = 'Invalid or malformed JSON';
			break;
		case JSON_ERROR_CTRL_CHAR:
			$msg = 'Control character error, possibly incorrectly encoded';
			break;
		case JSON_ERROR_SYNTAX:
			$msg = 'Syntax error, malformed JSON';
			break;
		case JSON_ERROR_UTF8:
			$msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
		case JSON_ERROR_RECURSION:
			$msg = 'One or more recursive references in the value to be encoded';
			break;
		case JSON_ERROR_INF_OR_NAN:
			$msg = 'One or more NAN or INF values in the value to be encoded';
			break;
		case JSON_ERROR_UNSUPPORTED_TYPE:
			$msg = 'A value of a type that cannot be encoded was given';
			break;
		case JSON_ERROR_INVALID_PROPERTY_NAME:
			$msg = 'A property name that cannot be encoded was given';
			break;
		case JSON_ERROR_UTF16:
			$msg = 'Malformed UTF-16 characters, possibly incorrectly encoded';
			break;
		default:
			$msg = 'Unknown error';
			break;
	}

	return $msg;
}

// Many Chinese songs and song directories have characters that are not UTF8 causing json_encode to fail which leaves the
// libcache json file empty. Replacing the non-UTF8 chars in the array before json_encode solves this problem (@lazybat).
function genLibraryUTF8Rep($flat) {
	$lib = array();

	// Break out misc lib options
	// [0] = Include comment tag: Yes | No
	// [1] = Album Key: Album@Artist (Default) | Album@Artist@AlbumID | FolderPath | FolderPath@AlbumID
	$incCommentTag = explode(',', $_SESSION['library_misc_options'])[0];
	$incMbrzAlbumid = strpos(explode(',', $_SESSION['library_misc_options'])[1], 'AlbumID') !== false ? 'Yes' : 'No';

	// Setup date range filter
	if ($_SESSION['library_flatlist_filter'] == 'year') {
		$filterYear = explode('-', $_SESSION['library_flatlist_filter_str']);
		if (!isset($filterYear[1])) {
	 		$filterYear[1] = $filterYear[0];
		}
	}

	foreach ($flat as $flatData) {
		// M4A probe for ALAC when filtering by Lossless/Lossy
		if (strpos($_SESSION['library_flatlist_filter'], 'loss') !== false && getFileExt($flatData['file']) == 'm4a') {
			$fh = fopen(MPD_MUSICROOT . $flatData['file'], "rb");
			$alacFound = strpos(fread($fh, 512), 'alac');
			fclose($fh);

			if ($_SESSION['library_flatlist_filter'] == 'lossless' && $alacFound !== false) {
				$push = true;
			} else if ($_SESSION['library_flatlist_filter'] == 'lossy' && $alacFound === false) {
				$push = true;
			} else {
				$push = false;
			}
		} else {
			$push = true;
		}

		// Date range filter
		if ($_SESSION['library_flatlist_filter'] == 'year') {
			$trackYear = getTrackYear($flatData);
			$push = ($trackYear >= $filterYear[0] && $trackYear <= $filterYear[1]) ? true : false;
		}

		// Encoded or HD filter
		if ($_SESSION['library_flatlist_filter'] == 'encoded' || $_SESSION['library_flatlist_filter'] == 'hdonly') {
			$encodedAt = getEncodedAt($flatData, 'default', true);

			if ($_SESSION['library_flatlist_filter'] == 'encoded') {
				$push = strpos($encodedAt, $_SESSION['library_flatlist_filter_str']) !== false ? true : false;
			} else {
				$push = strpos($encodedAt, 'h', -1) !== false ? true : false;
			}
		}

		if ($push === true) {
			$songData = array(
				'file' => utf8rep($flatData['file']),
				'tracknum' => utf8rep(($flatData['Track'] ? $flatData['Track'] : '')),
				'title' => utf8rep(($flatData['Title'] ? $flatData['Title'] : 'Unknown Title')),
				'disc' => ($flatData['Disc'] ? $flatData['Disc'] : '1'),
				'artist' => utf8repArray(($flatData['Artist'] ? $flatData['Artist'] : array())), //@Atair: array is expected in scripts-library.js even when empty
				//@Atair:
				// 1. album_artist shall never be empty, otherwise the sort routines in scripts-library.js complain,
				//    because they expect artist as string and not as array in album_artist || artist constructs
				// 2. When AlbumArtist is not defined and artist contains a single value, it is assumed that Artist should be a surrogate for ALbumArtist.
				//    otherwise, when Artist is an array of two and more values or empty, the AlbumArtist is set to 'Unknown' (this is regarded as bad tagging)
				'album_artist' => utf8rep(($flatData['AlbumArtist'] ? $flatData['AlbumArtist'] : (count($flatData['Artist']) == 1 ? $flatData['Artist'][0] : 'Unknown AlbumArtist'))),
				'composer' => utf8rep(($flatData['Composer'] ? $flatData['Composer'] : 'Composer tag missing')),
				//'performer' => utf8rep(($flatData['Performer'] ? $flatData['Performer'] : 'Performer tag missing')),
				//'conductor' => utf8rep(($flatData['Conductor'] ? $flatData['Conductor'] : 'Conductor tag missing')),
				'year' => utf8rep(getTrackYear($flatData)),
				'time' => utf8rep($flatData['Time']),
				'album' => utf8rep(($flatData['Album'] ? htmlspecialchars($flatData['Album']) : 'Unknown Album')),
				'genre' => utf8repArray(($flatData['Genre'] ? $flatData['Genre'] : array('Unknown'))), // @Atair: 'Unknown' genre has to be an array
				'time_mmss' => utf8rep(formatSongTime($flatData['Time'])),
				'last_modified' => $flatData['Last-Modified'],
				'encoded_at' => utf8rep(getEncodedAt($flatData, 'default', true)),
				'comment' => utf8rep((($flatData['Comment'] && $incCommentTag == 'Yes') ? $flatData['Comment'] : '')),
				'mb_albumid' => utf8rep((($flatData['MUSICBRAINZ_ALBUMID'] && $incMbrzAlbumid == 'Yes') ? $flatData['MUSICBRAINZ_ALBUMID'] : '0'))
			);

			array_push($lib, $songData);
		}
	}

	// No filter results or empty MPD database
	if (count($lib) == 1 && empty($lib[0]['file'])) {
		$lib[0]['file'] = '';
		$lib[0]['title'] = '';
		$lib[0]['artist'] = array('');
		$lib[0]['album'] = 'Nothing found';
		$lib[0]['album_artist'] = '';
		$lib[0]['genre'] = array('');
		$lib[0]['time_mmss'] = '';
		$lib[0]['encoded_at'] = '0';
	}

	if (false === ($jsonLib = json_encode($lib, JSON_INVALID_UTF8_SUBSTITUTE))) {
		workerLog('genLibraryUTF8Rep(): Error: json_encode($lib) failed');
	}

	if (false === (file_put_contents(libcacheFile(), $jsonLib))) {
		workerLog('genLibraryUTF8Rep(): Error: file create failed: ' . libcacheFile());
	}

	debugLog('genLibraryUTF8Rep(): jsonErrorMessage(): ' . jsonErrorMessage());
	return $jsonLib;
}

//@Atair: utf8rep for arrays
function utf8repArray($array) {
	$count = count($array);
	for ($i = 0; $i < $count; $i++) {
		$array[$i] = utf8rep($array[$i]);
	}
	return $array;
}

// UTF8 replace (@lazybat)
function utf8rep($str) {
	// Reject overly long 2 byte sequences, as well as characters above U+10000 and replace with ? (@lazybat)
	$str = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
		'|[\x00-\x7F][\x80-\xBF]+'.
		'|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
		'|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
		'|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
		'--', $str);

	// Reject overly long 3 byte sequences and UTF-16 surrogates and replace with ?
	$str = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]'.
		'|\xED[\xA0-\xBF][\x80-\xBF]/S','--', $str);

	return $str;
}

function getTrackYear($trackData) {
    if (array_key_exists('OriginalDate', $trackData)) {
        $trackYear = substr($trackData['OriginalDate'], 0, 4);
    } else if (array_key_exists('OriginalReleaseDate', $trackData)) {
        $trackYear = substr($trackData['OriginalReleaseDate'], 0, 4);
    } else if (array_key_exists('Date', $trackData)) {
        $trackYear = substr($trackData['Date'], 0, 4);
    } else {
		$trackYear = '';
	}

    return $trackYear;
}

function clearLibCacheAll() {
	sysCmd('truncate ' . LIBCACHE_BASE . '_* --size 0');
	sqlUpdate('cfg_system', sqlConnect(), 'lib_pos','-1,-1,-1');
}

function clearLibCacheFiltered() {
	sysCmd('truncate ' . LIBCACHE_BASE . '_folder.json --size 0');
	sysCmd('truncate ' . LIBCACHE_BASE . '_format.json --size 0');
	sysCmd('truncate ' . LIBCACHE_BASE . '_tag.json --size 0');
	sqlUpdate('cfg_system', sqlConnect(), 'lib_pos','-1,-1,-1');
}

// Extract bit depth, sample rate and audio format for display
function getEncodedAt($songData, $displayFormat, $calledFromGenLib = false) {
	// $displayFormats: 'default' Ex: 16/44.1k FLAC, 'verbose' Ex: 16 bit, 44.1 kHz, Stereo FLAC
	// NOTE: Bit depth is omitted if the format is lossy

	$encodedAt = 'NULL';
	$songData['file'] = ensureAudioFile($songData['file']);
	$ext = getFileExt($songData['file']);

	// Special section to handle calls from genLibrary() to populate the element "encoded_at"
	// Uses the MPD Format tag (rate:bits:channels) for PCM and mediainfo for DSD
	// Returned string for PCM is "bits/rate format,flag" and for DSD it's "DSD rate,h"
	// Flags: l (lossy), s (standard definition), h (high definition: bits >16 || rate > 44.1 || DSD)
	if ($calledFromGenLib) {
		$mpdFormatTag = explode(':', $songData['Format']);
		// Lossy: return just the format since bit depth has no meaning and bitrate is not known until playback
		if ($ext == 'mp3' || ($mpdFormatTag[1] == 'f' && $mpdFormatTag[2] <= 2)) {
			$encodedAt = strtoupper($ext) . ',l';
		} else if ($ext == 'dsf' || $ext == 'dff') {
			// DSD
			$result = sysCmd('mediainfo --Inform="Audio;file:///var/www/mediainfo.tpl" ' . '"' . MPD_MUSICROOT . $songData['file'] . '"');
			$encodedAt = $result[1] == '' ? 'DSD,h' : formatRate($result[1]) . ' DSD,h';
		} else {
			// PCM or Multichannel PCM
			// Workaround for wrong bit depth returned for ALAC encoded m4a files
			$mpdFormatTag[1] = ($ext == 'm4a' && $mpdFormatTag[1] == '32') ? '24' : $mpdFormatTag[1];
			$hiDef = ($mpdFormatTag[1] != 'f' && $mpdFormatTag[1] > ALBUM_BIT_DEPTH_THRESHOLD) ||
				$mpdFormatTag[0] > ALBUM_SAMPLE_RATE_THRESHOLD ? ',h' : ',s';
			$encodedAt = ($mpdFormatTag[1] == 'f' ? '' : $mpdFormatTag[1] . '/') . formatRate($mpdFormatTag[0]) . ' ' . strtoupper($ext) . $hiDef;
		}
	} else if (isset($songData['Name']) || (substr($songData['file'], 0, 4) == 'http' && !isset($songData['Artist']))) {
		// Radio station
		$encodedAt = $displayFormat == 'verbose' ? 'VBR compression' : 'VBR';
	} else if (substr($songData['file'], 0, 4) == 'http' && isset($songData['Artist'])) {
		// UPnP file
		$encodedAt = 'Unknown';
	} else if ($ext == 'dsf' || $ext == 'dff') {
		// DSD file
		$result = sysCmd('mediainfo --Inform="Audio;file:///var/www/mediainfo.tpl" ' . '"' . MPD_MUSICROOT . $songData['file'] . '"');
		$encodedAt = 'DSD ' . ($result[1] == '' ? '?' : formatRate($result[1]) . ' MHz');
	} else if ($songData['file'] == '') {
		return 'Not playing';
	} else {
		// PCM file
		if (!file_exists(MPD_MUSICROOT . $songData['file'])) {
			return 'File does not exist';
		}
		// Mediainfo
		// NOTE: Mediainfo when called via exec() returns nothing if the file name contains accented characters
		$cmd = 'mediainfo --Inform="Audio;file:///var/www/mediainfo.tpl" ' . '"' . MPD_MUSICROOT . $songData['file'] . '"';
		$result = sysCmd($cmd);

		// NOTE: Exception handling for rare case where medoainfo returns blank bit depth or sample rate
		if ($result[0] == '' || $result[1] == '') {
			// Use MPD lsinfo
			$mpdEncodedAt = getMpdEncodedAt($songData['file']);
			$bitDepth = $mpdEncodedAt[1];
			$sampleRate = $mpdEncodedAt[0];
			$channels = $mpdEncodedAt[2];
			$format = strtoupper($ext);
		} else {
			// Use mediainfo
			$bitDepth = $result[0];
			$sampleRate = $result[1];
			$channels = $result[2];
			$format = $result[3];
		}

		if ($displayFormat == 'default') {
			$encodedAt = $bitDepth == '?' ?
				formatRate($sampleRate) . ' ' . $format :
				$bitDepth . '/' . formatRate($sampleRate) . ' ' . $format;
		} else {
			$encodedAt = $bitDepth == '?' ?
				formatRate($sampleRate) . ' kHz, ' . formatChannels($channels) . ' ' . $format :
				$bitDepth . ' bit, ' . formatRate($sampleRate) . ' kHz, ' . formatChannels($channels) . ' ' . $format;
		}
	}

	return $encodedAt;
}

// Return MPD format tag (rate:bits:channels)
function getMpdEncodedAt($file) {
	if (false === ($sock = openMpdSock('localhost', 6600))) {
		workerLog('getMpdEncodedAt(): Connection to MPD failed');
		$mpdEncodedAt = array('', '', '');
	}

	sendMpdCmd($sock, 'lsinfo "' . $file . '"');
	$songData = parseDelimFile(readMpdResp($sock), ': ');
	$mpdEncodedAt = explode(':', $songData['Format']);

	return $mpdEncodedAt;
}

// Auto-shuffle random play
function startAutoShuffle() {
	$filter = (!empty($_SESSION['ashuffle_filter']) && $_SESSION['ashuffle_filter'] != 'None') ?
		'mpc search ' . $_SESSION['ashuffle_filter'] . ' | ' : '';
	$file = $filter != '' ? '--file -' : '';
	$mode = $_SESSION['ashuffle_mode'] == 'Album' ? '--group-by album ' : '';
	sysCmd($filter . '/usr/bin/ashuffle --queue-buffer 1 ' . $mode . $file . ' > /dev/null 2>&1 &');
}

function stopAutoShuffle() {
	sysCmd('killall -s 9 ashuffle > /dev/null');
	phpSession('write', 'ashuffle', '0');
	if (false === ($sock = openMpdSock('localhost', 6600))) {
		workerLog('stopAutoShuffle(): Connection to MPD failed');
		exit(0);
	}
	sendMpdCmd($sock, 'consume 0');
	$resp = readMpdResp($sock);
	closeMpdSock($sock);
}
