<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * Shairport-sync meta data engine
 * Copyright (C) 2016 Andreas Goetz <cpuidle@gmx.de>
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
 * 2018-01-26 TC moOde 4.0
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

const SPS_METADATA_PIPE = '/tmp/shairport-sync-metadata';
const MODE_ANY = 0;
const MODE_DATA = 1;

const DEF_COVER = 'images/default-cover-v6.svg';
const SPS_COVER = 'imagesw/spscover.';
const SPS_CACHE = '/var/local/www/spscache.json';

$meta = array();
$mode = MODE_ANY;
$buf = null;
$type = null;
$code = null;

// decode mode_any strings
function decode($str) {
    $res = "";
    $i = 0;

    while ($i+2 <= strlen($str)) {
        $res .= chr(hexdec(substr($str, $i, 2)));
        $i += 2;
    }

    return $res;
}

session_id(playerSession('getsessionid'));
session_start();
session_write_close();

debugLog('engine-sps: Connect');
debugLog('engine-sps: Opening pipe...');

// this will block until shairport-sync opens the pipe
if (false === ($stream = @fopen(SPS_METADATA_PIPE, 'r'))) {
	debugLog('engine-sps: Could not open pipe');
    return;
}
else {
	debugLog('engine-sps: Pipe opened');
	stream_set_blocking($stream, 1); // so fget waits for data
}

// check cache
if (filesize(SPS_CACHE) == 0) {
	debugLog('engine-sps: Cache size=0');
	initSpsCache();
	debugLog('engine-sps: Cache initialized');
}

// load cache
$spscache = json_decode(file_get_contents(SPS_CACHE), true);

// get airplay active flag
if (false === ($array = sdbquery('select value from cfg_system where param="airplayactv"', cfgdb_connect()))) {
	debugLog('engine-sps: Query airplay active flag failed');
    return;
}
else {
	$airplayactv = $array[0]['value'];
	debugLog('engine-sps: Airplay active flag=(' . $airplayactv . ')');
}

debugLog('engine-sps: $_GET[state]=(' . $_GET['state'] . ')');
debugLog('engine-sps: Cache[state]=(' . $spscache['state'] . ')');

// at first connect or browser refresh, return cache data if airplay session active
if ($_GET['state'] != $spscache['state']) {
	if ($airplayactv == '1') {
		debugLog('engine-sps: Cache data returned to client');
		echo json_encode($spscache);
		return;
	}
	else {
		debugLog('engine-sps: Airplay session not active');
	}
}

debugLog('engine-sps: Fget waiting for pipe data...');

while (!feof($stream)) {
    $line = fgets($stream);

    if ($mode == MODE_ANY) {
        if (preg_match('#<item><type>(\w+)</type><code>(\w+)</code><length>(\d+)</length>#', $line, $matches)) {
            $type = decode($matches[1]);
            $code = decode($matches[2]);
            $buf = '';
            $length = $matches[3];
        }
        elseif (preg_match('#<data encoding="base64">#', $line)) {
            $mode = MODE_DATA;
        }
    }
    elseif ($mode == MODE_DATA) {
        if (preg_match('#^(.*)</data>#', $line, $matches)) {
            $buf .= $matches[1];
            $buf = base64_decode($buf);
            $mode = MODE_ANY;
        }
        else {
            $data .= $line;
        }
    }

    if (preg_match('#</item>#', $line)) {
        $tag = $type . ' ' . $code;

        switch ($tag) {
            case 'core asar':
                $meta['artist'] = empty($buf) ? '***' : $buf;
				debugLog('engine-sps: Artist=(' . $meta['artist'] . ')');
                break;
            case 'core asal':
                $meta['album'] = empty($buf) ? '***' : $buf;
				debugLog('engine-sps: Album=(' . $meta['album'] . ')');
                break;
            case 'core minm':
                $meta['title'] = empty($buf) ? '***' : $buf;
				debugLog('engine-sps: Title=(' . $meta['title'] . ')');
                break;
            case 'core asgn':
                $meta['genre'] = empty($buf) ? '***' : $buf;
				debugLog('engine-sps: Genre=(' . $meta['genre'] . ')');
                break;
            case 'ssnc pvol':
                // The volume is sent as a string -- "airplay_volume,volume,lowest_volume,highest_volume",
                // where "volume", "lowest_volume" and "highest_volume" are given in dB.
                // The "airplay_volume" is what's sent by the source (e.g. iTunes) to the player,
                // and is from 0.00 down to -30.00, with -144.00 meaning "mute". This is linear on the
                // volume control slider of iTunes or iOS AirPlay
                $meta['volume'] = explode(',', $buf);
				break;
            case 'ssnc prgr':
                // This is metadata from AirPlay consisting of RTP timestamps for the start of the current play sequence,
                // the current play point and the end of the play sequence. The timestamps probabably wrap at 2^32.
                $meta['progress'] = explode('/', $buf);
				break;
            case 'ssnc PICT':
				$tmp = substr($buf, 0, 32);

				if (strpos($tmp, 'PNG') !== false) {
					$meta['imgtype'] = 'png';
				}
				elseif (strpos($tmp, 'JFIF') !== false) {
					$meta['imgtype'] = 'jpg';
				}
				else {
					$meta['imgtype'] = 'png';
				}				

				$meta['imglen'] = strlen($buf);
				$meta['imgurl'] = $meta['imglen'] > 128 ? SPS_COVER . $meta['imgtype'] : DEF_COVER;

				if ($meta['imgurl'] != DEF_COVER) {
					// create image file
					if (file_put_contents('/var/local/www/' . $meta['imgurl'], $buf) === false) {
						debugLog('engine-sps: Image file create failed');		
						break;
					}				
					else {
						debugLog('engine-sps: Image file created');
					}
				}
				
				debugLog('engine-sps: Imageurl=(' . $meta['imgurl'] . ')');
				debugLog('engine-sps: Imagelen=(' . $meta['imglen'] . ')');
				debugLog('engine-sps: Imagetype=(' . $meta['imgtype'] . ')');
				//debugLog('engine-sps: Imagetype_raw=(' . $tmp . ')');
                break;
            default:
                //debiglog('engine-sps: Mode undefined, data=(' . $data) . ')';
        }

		// when this tag is received it generally indicates all tags received after song play back starts
		if ($tag == 'ssnc prgr') {
			debugLog('engine-sps: Progress=(' . $meta['progress'] . ')');
			debugLog('engine-sps: Updating cache...');

			$spscache['state'] = 'ok';
			$spscache['artist'] = $meta['artist'];
			$spscache['album'] = $meta['album'];
			$spscache['title'] = $meta['title'];
			$spscache['genre'] = $meta['genre'];
			$spscache['progress'] = $meta['progress'];
			$spscache['volume'] = $meta['volume'];
			$spscache['imgtype'] = $meta['imgtype'];
			$spscache['imglen'] = $meta['imglen'];
			$spscache['imgurl'] = $meta['imgurl'];

			// encode the data
			$spscache = json_encode($spscache);

			if (file_put_contents(SPS_CACHE, $spscache) === false) {
				debugLog('engine-sps: Cache update failed');		
				break;
			}
			else {
				debugLog('engine-sps: Cache updated');
			}
			
			break; // break out of loop
		}

		// volume changes
		// 0=airplay_volume (0 to -30 linear, -144 mute), 1=volume (dB), 2=lowest_volume (dB), 3=highest_volume (dB)
		if ($tag == 'ssnc pvol') {
			debugLog('engine-sps: Volume=(' . $meta['volume'][0] . ',' . $meta['volume'][1] . ',' . $meta['volume'][2]  . ',' . $meta['volume'][3] . ')');
		}
    }	
}

// return data to client
debugLog('engine-sps: Pipe data returned to client');
header('Access-Control-Allow-Origin: *');
echo $spscache;
