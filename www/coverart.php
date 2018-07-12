<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * Cover art extractor
 * Copyright (C) 2015 Andreas Goetz <cpuidle@gmx.de>
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
 * 2015-10-30: AG initial version
 * - script looks for flac, mp3 or m4a embedded art, Folder, folder, Cover, cover png/jpg/jpeg files, or any other image file.
 * - call via /coverart.php/some/local/file/name
 * - make sure client is configured to hand cover requests to /coverart.php or setup an nginx catch-all rule:
 * - try_files $uri $uri/ /coverart.php;
 *
 * 2018-01-26 TC moOde 4.0
 * 2018-04-02 TC moOde 4.1 set search priority to 0
 *
 */

set_include_path('inc');

// NOTE: uncomment this to enable workerLog() output
//require_once dirname(__FILE__) . '/inc/playerlib.php';

// image search priority
// 0: search for embedded image first (default)
// 1: search for image file first
define('SEARCH_PRI', 0);

// uncomment these if using randomized symlink in a session var
//session_start();
//session_write_close();

function outImage($mime, $data) {
	switch ($mime) {
		case "image/gif":
		case "image/jpg":
		case "image/jpeg":
		case "image/png":
		case "image/tif":
		case "image/tiff":
			header("Content-Type: " . $mime);
			echo $data;
			//workerLog('coverart: x - outImage()');
			exit(0);
			break;
		default :
			//workerLog('coverart: y - outImage()');
			break;
	}
}

function getImage($path) {
	global $getid3;

	if (!file_exists($path)) {
		return false;
	}

	$ext = pathinfo($path, PATHINFO_EXTENSION);

	switch (strtolower($ext)) {
		case 'gif':
		case 'jpg':
		case 'jpeg':
		case 'png':
		case 'tif':
		case 'tiff':
			// physical image file -> redirect
			//$path = '/' . $_SESSION['musicroot'] . substr($path, strlen('/var/lib/mpd/music'));
			$path = '/vlmm03846271' . substr($path, strlen('/var/lib/mpd/music'));
			$path = str_replace('#', '%23', $path);
			//workerLog('coverart: 1 - ' . $path);
			header('Location: ' . $path);
			die;

			// alternative -> return image file contents
			$mime = 'image/' . $ext;
			$data = file_get_contents($path);

			outImage($mime, $data);
			//workerLog('coverart: 2 - ' . $path);
			break;

		case 'mp3':
			require_once 'Zend/Media/Id3v2.php';
			try {
				$id3 = new Zend_Media_Id3v2($path);

				if (isset($id3->apic)) {
					//workerLog('coverart: 3 - ' . $path);
					outImage($id3->apic->mimeType, $id3->apic->imageData);
				}
			}
			catch (Zend_Media_Id3_Exception $e) {
				// catch any parse errors
			}

			require_once 'Zend/Media/Id3v1.php';
			try {
				$id3 = new Zend_Media_Id3v1($path);

				if (isset($id3->apic)) {
					//workerLog('coverart: 4 - ' . $path);
					outImage($id3->apic->mimeType, $id3->apic->imageData);
				}
			}
			catch (Zend_Media_Id3_Exception $e) {
				// catch any parse errors
			}
			break;

		case 'flac':
			require_once 'Zend/Media/Flac.php';
			try {
				$flac = new Zend_Media_Flac($path);

				if ($flac->hasMetadataBlock(Zend_Media_Flac::PICTURE)) {
					$picture = $flac->getPicture();
					//workerLog('coverart: 5 - ' . $path);
					outImage($picture->getMimeType(), $picture->getData());
				}
			}
			catch (Zend_Media_Flac_Exception $e) {
				// catch any parse errors
			}
			break;

        case 'm4a':
            require_once 'Zend/Media/Iso14496.php';
            try {
                $id3 = new Zend_Media_Iso14496($path);
                $picture = $id3->moov->udta->meta->ilst->covr;
                $mime = ($picture->getFlags() & Zend_Media_Iso14496_Box_Data::JPEG) == Zend_Media_Iso14496_Box_Data::JPEG
                    ? 'image/jpeg'
                    : (
                        ($picture->getFlags() & Zend_Media_Iso14496_Box_Data::PNG) == Zend_Media_Iso14496_Box_Data::PNG
                        ? 'image/png'
                        : null
                    );
                if ($mime) {
					//workerLog('coverart: 6 - ' . $path);
                    outImage($mime, $picture->getValue());
                }
            }
            catch (Zend_Media_Iso14496_Exception $e) {
                // catch any parse errors
            }
            break;
	}

	return false;
}

function parseFolder($path) {
	// default cover files
	$covers = array('Folder.jpg', 'folder.jpg', 'Folder.jpeg', 'folder.jpeg', 'Folder.png', 'folder.png', 'Folder.tif', 'folder.tif', 'Folder.tiff', 'folder.tiff',
		'Cover.jpg', 'cover.jpg', 'Cover.png', 'cover.png', 'Cover.tif', 'cover.tif', 'Cover.tiff', 'cover.tiff');
	foreach ($covers as $file) {
		getImage($path . $file);
	}

	// all other files
	foreach (glob($path . '*') as $file) {
		if (is_file($file)) {
			//workerLog('coverart: d - ' . $file);
			getImage($file);
		}
	}
}

/*
 * MAIN
 */

// Get options- cmd line or GET
$options = getopt('p:', array('path:'));
$path = isset($options['p']) ? $options['p'] : (isset($options['path']) ? $options['path'] : null);

if (null === $path) {
	$self = $_SERVER['SCRIPT_NAME'];
	$path = urldecode($_SERVER['REQUEST_URI']);
	if (substr($path, 0, strlen($self)) === $self) {
		// strip script name if called as /coverart.php/path/to/file
		$path = substr($path, strlen($self)+1);
	}
	#$path = '/mnt/' . $path;
	$path = '/var/lib/mpd/music/' . $path;
}

if (SEARCH_PRI == 0) {
	// does file exist and contain image?
	//workerLog('coverart: a - ' . $path);
	getImage($path);
}

// directory - try all files
if (is_dir($path)) {
	// make sure path ends in /
	if (substr($path, -1) !== '/') {
		$path .= '/';
	}

	//workerLog('coverart: b - ' . $path);
	parseFolder($path);
}
else {
	// file - try all files in containing folder
	$dirpath = pathinfo($path, PATHINFO_DIRNAME) . '/';

	//workerLog('coverart: c - ' . $dirpath);
	parseFolder($dirpath);

	if (SEARCH_PRI == 1) {
		// does file exist and contain image?
		//workerLog('coverart: a - ' . $path);
		getImage($path);
	}
}

// nothing found -> default cover
header('Location: /images/default-cover-v6.svg');
