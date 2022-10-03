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
 * - script looks for flac, mp3 or m4a embedded art, Folder, folder, Cover, cover png/jpg/jpeg files, or any other image file.
 * - call via /coverart.php/some/local/file/name
 * - make sure client is configured to hand cover requests to /coverart.php or setup an nginx catch-all rule:
 * - try_files $uri $uri/ /coverart.php;
 *
 */

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

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
			exit(0);
		default :
			break;
	}

	return false;
}

function getImage($path) {
	if (!file_exists($path)) {
		return false;
	}

	$ext = pathinfo($path, PATHINFO_EXTENSION);

	switch (strtolower($ext)) {
		// Image file -> serve from disc
		case 'gif':
		case 'jpg':
		case 'jpeg':
		case 'png':
		case 'tif':
		case 'tiff':
			$mimeType = 'image/'.$ext;
			$fileSize = filesize($path);
			$fh = fopen($path, 'rb');
			$imageData = fread($fh, $fileSize);
			fclose($fh);
			outImage($mimeType, $imageData);
			break;
		// Embedded images
		case 'mp3':
			require_once __DIR__ . '/inc/Zend/Media/Id3v2.php';
			try {
				$id3v2 = new Zend_Media_Id3v2($path, array('hash_only' => false));

				if (isset($id3v2->apic)) {
					outImage($id3v2->apic->mimeType, $id3v2->apic->imageData);
				}
			} catch (Zend_Media_Id3_Exception $e) {
				workerLog('coverart: Error: ' . $e->getMessage() . ': ' . $path);
			}
			break;

		case 'flac':
			require_once __DIR__ . '/inc/Zend/Media/Flac.php';
			try {
				$flac = new Zend_Media_Flac($path, $hash_only = false); // r44a

				if ($flac->hasMetadataBlock(Zend_Media_Flac::PICTURE)) {
					$picture = $flac->getPicture();
					outImage($picture->getMimeType(), $picture->getData());
				}
			} catch (Zend_Media_Flac_Exception $e) {
				workerLog('coverart: Error: ' . $e->getMessage() . ': ' . $path);
			}
			break;

        case 'm4a':
            require_once __DIR__ . '/inc/Zend/Media/Iso14496.php';
            try {
                $iso14496 = new Zend_Media_Iso14496($path, array('hash_only' => false)); // r44a
                $picture = $iso14496->moov->udta->meta->ilst->covr;
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
            } catch (Zend_Media_Iso14496_Exception $e) {
				workerLog('coverart: Error: ' . $e->getMessage() . ': ' . $path);
            }
            break;
	}

	return false;
}

function parseFolder($path) {
	// Default cover files
	$covers = array(
		'Cover.jpg', 'cover.jpg', 'Cover.jpeg', 'cover.jpeg', 'Cover.png', 'cover.png', 'Cover.tif', 'cover.tif', 'Cover.tiff', 'cover.tiff',
		'Folder.jpg', 'folder.jpg', 'Folder.jpeg', 'folder.jpeg', 'Folder.png', 'folder.png', 'Folder.tif', 'folder.tif', 'Folder.tiff', 'folder.tiff'
	);

	foreach ($covers as $file) {
		getImage($path . $file);
	}

	// All other image files
	$extensions = array('jpg', 'jpeg', 'png', 'tif', 'tiff');
	$path = str_replace('[', '\[', $path);
	$path = str_replace(']', '\]', $path);
	foreach (glob($path . '*') as $file) {
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

session_id(phpSession('get_sessionid'));
phpSession('open_ro');
$searchPriority = $_SESSION['library_covsearchpri'];

// Get options- cmd line or GET
$options = getopt('p:', array('path:'));
$path = isset($options['p']) ? $options['p'] : (isset($options['path']) ? $options['path'] : null);

if (null === $path) {
	$self = $_SERVER['SCRIPT_NAME'];
	$path = urldecode($_SERVER['REQUEST_URI']);

	if (substr($path, 0, strlen($self)) === $self) {
		// Strip script name if called as /coverart.php/path/to/file
		$path = substr($path, strlen($self) + 1);
	}
	$path = MPD_MUSICROOT . $path;
}

$path = ensureAudioFile($path);

// file: embedded cover
if ($searchPriority == 'Embedded cover') { // Embedded first
	getImage($path);
}

// dir: cover image file
if (is_dir($path)) {
	if (substr($path, -1) !== '/') {$path .= '/';}
	parseFolder($path);
} else {
	// file: cover image file in containing dir
	$dirpath = pathinfo($path, PATHINFO_DIRNAME) . '/';
	parseFolder($dirpath);

	if ($searchPriority == 'Cover image file') { // Embedded last
		getImage($path);
	}
}

// Nothing found: default cover
header('Location: /images/default-cover-v6.svg');
