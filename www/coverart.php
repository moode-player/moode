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
 * 2018-04-02 TC moOde 4.1
 * - set search priority to 0
 * 2018-07-18 TC moOde 4.2
 * - bump coverlink
 * 2018-09-27 TC moOde 4.3
 * - change glob to search only for image files
 * - use session vars for search pri and musicroot
 * - rep l/r bracket with backslash l/r bracket in path for glob
 *
 */

set_include_path('inc');
require_once 'playerlib.php';

function outImage($mime, $data) {
	//workerLog('coverart: outImage(): ' . $mime . ', ' . strlen($data) . ' bytes');
	switch ($mime) {
		case "image/gif":
		case "image/jpg":
		case "image/jpeg":
		case "image/png":
		case "image/tif":
		case "image/tiff":
			header("Content-Type: " . $mime);
			echo $data;
			exit(0);
		default :
			break;
	}

	return false;
}

function getImage($path) {
	//workerLog('coverart: getImage(): ' . $path);
	if (!file_exists($path)) {
		//workerLog('coverart: getImage(): ' . $path . ' (does not exist)');
		return false;
	}

	$ext = pathinfo($path, PATHINFO_EXTENSION);

	switch (strtolower($ext)) {
		// image file -> redirect
		case 'gif':
		case 'jpg':
		case 'jpeg':
		case 'png':
		case 'tif':
		case 'tiff':
			$path = '/' . $GLOBALS['musicroot_ext'] . substr($path, strlen(MPD_MUSICROOT)-1);
			$path = str_replace('#', '%23', $path);
			header('Location: ' . $path);
			exit(0);

		// embedded images
		case 'mp3':
			require_once 'Zend/Media/Id3v2.php';
			try {
				$id3 = new Zend_Media_Id3v2($path);

				if (isset($id3->apic)) {
					outImage($id3->apic->mimeType, $id3->apic->imageData);
				}
			}
			catch (Zend_Media_Id3_Exception $e) {
				//workerLog('coverart: Zend media exception: ' . $e->getMessage()); 
			}

			require_once 'Zend/Media/Id3v1.php';
			try {
				$id3 = new Zend_Media_Id3v1($path);

				if (isset($id3->apic)) {
					outImage($id3->apic->mimeType, $id3->apic->imageData);
				}
			}
			catch (Zend_Media_Id3_Exception $e) {
				//workerLog('coverart: Zend media exception: ' . $e->getMessage()); 
			}
			break;

		case 'flac':
			require_once 'Zend/Media/Flac.php';
			try {
				$flac = new Zend_Media_Flac($path);

				if ($flac->hasMetadataBlock(Zend_Media_Flac::PICTURE)) {
					$picture = $flac->getPicture();
					outImage($picture->getMimeType(), $picture->getData());
				}
			}
			catch (Zend_Media_Flac_Exception $e) {
				//workerLog('coverart: Zend media exception: ' . $e->getMessage()); 
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
                    outImage($mime, $picture->getValue());
                }
            }
            catch (Zend_Media_Iso14496_Exception $e) {
				//workerLog('coverart: Zend media exception: ' . $e->getMessage()); 
            }
            break;
	}

	return false;
}

function parseFolder($path) {
	//workerLog('coverart: parseFolder(): ' . $path);
	// default cover files
	$covers = array(
		'Cover.jpg', 'cover.jpg', 'Cover.jpeg', 'cover.jpeg', 'Cover.png', 'cover.png', 'Cover.tif', 'cover.tif', 'Cover.tiff', 'cover.tiff',
		'Folder.jpg', 'folder.jpg', 'Folder.jpeg', 'folder.jpeg', 'Folder.png', 'folder.png', 'Folder.tif', 'folder.tif', 'Folder.tiff', 'folder.tiff'
	);
	foreach ($covers as $file) {
		getImage($path . $file);
	}
	// all other image files
	$extensions = array('jpg', 'jpeg', 'png', 'tif', 'tiff');
	$path = str_replace('[', '\[', $path);
	$path = str_replace(']', '\]', $path);
	foreach (glob($path . '*') as $file) {
		//workerLog('coverart: parseFolder(): glob: ' . $file);
		if (is_file($file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $extensions)) {
			getImage($file);
		}
	}
	/*// all other files
	foreach (glob($path . '*') as $file) {
		//workerLog('coverart: parseFolder(): glob' . $file);
		if (is_file($file)) {
			getImage($file);
		}
	}*/

	return false;
}

/*
 * MAIN
 */

session_id(playerSession('getsessionid'));
session_start();
$search_pri = $_SESSION['library_covsearchpri'];
$musicroot_ext = $_SESSION['musicroot_ext'];
session_write_close();
//workerLog('coverart: $search_pri=' . $search_pri);
//workerLog('coverart: $musicroot_ext=' . $musicroot_ext);

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
	$path = MPD_MUSICROOT . $path;
}

// file: embedded cover
if ($search_pri == 'Embedded cover') { // embedded first
	getImage($path);
}

// dir: cover image file
if (is_dir($path)) {
	if (substr($path, -1) !== '/') {$path .= '/';}
	parseFolder($path);
}
else {
	// file: cover image file in containing dir
	$dirpath = pathinfo($path, PATHINFO_DIRNAME) . '/';
	parseFolder($dirpath);

	if ($search_pri == 'Cover image file') { // embedded last
		getImage($path);
	}
}

// nothing found: default cover
//workerLog('coverart: default cover');
header('Location: /images/default-cover-v6.svg');
