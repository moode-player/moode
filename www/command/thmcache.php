#!/usr/bin/php
<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * Cover art extraction routines (C) 2015 Andreas Goetz
 * cpuidle@gmx.de
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
 * 2020-07-09 TC moOde 6.6.0
 *
 */

set_include_path('/var/www/inc');
require_once 'playerlib.php';

define('NOT_FOUND', '/var/www/images/notfound.jpg');

//
// MAIN
//

workerLog('thmcache: Start');
session_id(playerSession('getsessionid'));
session_start();
$search_pri = $_SESSION['library_covsearchpri'];
$hires_thm = $_SESSION['library_hiresthm'];
$pixel_ratio = floor($_SESSION['library_pixelratio']);
session_write_close();

// Automatic
if ($hires_thm == 'Auto') {
	if ($pixel_ratio == 2) {
		$thm_w = 200;
		$thm_q = 75;
	}
	elseif ($pixel_ratio >= 3) {
		$thm_w = 400;
		$thm_q = 50;
	}
	else {
		$thm_w = 100;
		$thm_q = 75;
	}
}
// Manual
else {
	$thm_w = substr($hires_thm, 0, 3); // only the numeric part ex: 100px
	$thm_q = 75;
}

//workerLog('thmcache: $search_pri=' . $search_pri);
//workerLog('thmcache: $hires_thm=' . $hires_thm);
//workerLog('thmcache: $pixel_ratio=' . $pixel_ratio);
//workerLog('thmcache: $thm_w=' . $thm_w);
//workerLog('thmcache: $thm_q=' . $thm_q);

// Ensure cache dir exists
if (!file_exists(THMCACHE_DIR)) {
	workerLog('thmcache: info: Missing thmcache dir, new one created');
	sysCmd('mkdir ' . THMCACHE_DIR);
}

// List the dirs in /mnt and /media directories
$mnt_dirs = str_replace("\n", ', ', shell_exec('ls /mnt'));
$media_dirs = str_replace("\n", ', ', shell_exec('ls /media'));
!empty($media_dirs) ? $dirs = $mnt_dirs . substr($media_dirs, 0, -2) : $dirs = substr($mnt_dirs, 0, -2);
$dirs = str_replace('moode-player, ', '', $dirs); // This mount point is only present in dev
workerLog('thmcache: Scanning: ' . $dirs);

// Generate the file list
$result = shell_exec('/var/www/command/listall.sh | sort');
if (is_null($result) || substr($result, 0, 2) == 'OK') {
	workerLog('thmcache: exit: no files found');
	session_start();
	$_SESSION['thmcache_status'] = 'No files found';
	session_write_close();
	exit(0);
}

// Generate thumbnails
// - Compare the containing dir paths for file (file_a) and file+1 (file_b)
// - When they are different we create a thumb using file_a and dir_a
$count = 0;
$line = strtok($result, "\n");
while ($line) {
	$file_a = explode(': ', $line, 2)[1];
	$dir_a = dirname($file_a);

	$line = strtok("\n");

	$file_b = explode(': ', $line, 2)[1];
	$dir_b = dirname($file_b);

	if ($dir_a != $dir_b) {
		session_start();
		$_SESSION['thmcache_status'] = 'Processing album ' . ++$count . ' ' . $dir_a;
		session_write_close();

		if (!file_exists(THMCACHE_DIR . md5($dir_a) . '.jpg')) {
			createThumb($file_a, $dir_a, $search_pri, $thm_w, $thm_q);
		}
	}
}

session_start();
$_SESSION['thmcache_status'] = 'Done: '  . $count . ' albums processed';
session_write_close();
workerLog('thmcache: Done: ' . $count . ' album dirs processed');

// Create thumbnail image
function createThumb($file, $dir, $search_pri, $thm_w, $thm_q) {
	$path = MPD_MUSICROOT . $file;
	$img_str = false;
	//workerlog('thmcache: path: ' . $path);

	if ($search_pri == 'Embedded cover') {
		// Check for embedded cover in file
		$img_str = getImage($path);
	}

	if ($img_str === false) {
		// Check for cover image file in containing dir
		$dirpath = pathinfo($path, PATHINFO_DIRNAME) . '/';
		$img_str = parseFolder($dirpath);

		if ($img_str === false) {
			if ($search_pri == 'Cover image file') {
				// Check for embedded cover
				$img_str = getImage($path);
			}
		}

		if ($img_str === false) {
			// Nothing found
			$img_str = NOT_FOUND;
		}
	}

	// Image file path, convert image to string
	if (strlen($img_str) < 256) {
		$img_str = file_get_contents($img_str);
	}
	else {
		//workerlog('thmcache: embedded image');
	}

	$image = imagecreatefromstring($img_str);
	// Image h/w
	$img_w = imagesx($image);
	$img_h = imagesy($image);
	// Thumbnail height
	$thm_h = ($img_h / $img_w) * $thm_w;

	if (($thumb = imagecreatetruecolor($thm_w, $thm_h)) === false) {
		workerLog('thmcache: error 1: ' . $file);
		return;
	}
	if (imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thm_w, $thm_h, $img_w, $img_h) === false) {
		workerLog('thmcache: error 2: ' . $file);
		return;
	}
	if (imagedestroy($image) === false) {
		workerLog('thmcache: error 3: ' . $file);
		return;
	}
	if (imagejpeg($thumb, THMCACHE_DIR . md5($dir) . '.jpg', $thm_q) === false) {
		workerLog('thmcache: error 4: ' . $file);
		return;
	}
	if (imagedestroy($thumb) === false) {
		workerLog('thmcache: error 5: ' . $file);
		return;
	}
}

// Modified versions of coverart.php functions
// (C) 2015 Andreas Goetz
function outImage($mime, $data) {
	//workerLog('thmcache: outImage(): ' . $mime . ', ' . strlen($data) . ' bytes');
	switch ($mime) {
		case "image/gif":
		case "image/jpg":
		case "image/jpeg":
		case "image/png":
		case "image/tif":
		case "image/tiff":
			header("Content-Type: " . $mime);
			return $data;
		default :
			break;
	}

	return false;
}
function getImage($path) {
	//workerLog('thmcache: getImage(): ' . $path);
	if (!file_exists($path)) {
		//workerLog('thmcache: getImage(): ' . $path . ' (does not exist)');
		return false;
	}

	$image = false;
	$ext = pathinfo($path, PATHINFO_EXTENSION);

	switch (strtolower($ext)) {
		// Image file
		case 'gif':
		case 'jpg':
		case 'jpeg':
		case 'png':
		case 'tif':
		case 'tiff':
			header('Location: ' . $path);
			$image = $path;
			break;

		// Embedded images
		case 'mp3':
			require_once 'Zend/Media/Id3v2.php';
			try {
				$id3v2 = new Zend_Media_Id3v2($path, array('hash_only' => false)); // r44a

				if (isset($id3v2->apic)) {
					//workerLog('thmcache; mp3: id3v2: apic->imageData: length: ' . strlen($id3v2->apic->imageData));
					$image = outImage($id3v2->apic->mimeType, $id3v2->apic->imageData); // r44d chg id3 to id3v2 for mimeType
				}
			}
			catch (Zend_Media_Id3_Exception $e) {
				workerLog('thmcache: mp3: ' . $path);
				workerLog('thmcache: mp3: Zend media exception: ' . $e->getMessage());
			}
			break;

		case 'flac':
			require_once 'Zend/Media/Flac.php';
			try {
				$flac = new Zend_Media_Flac($path, $hash_only = false); // r44a

				if ($flac->hasMetadataBlock(Zend_Media_Flac::PICTURE)) {
					$picture = $flac->getPicture();
					$image = outImage($picture->getMimeType(), $picture->getData());
				}
			}
			catch (Zend_Media_Flac_Exception $e) {
				workerLog('thmcache: flac: ' . $path);
				workerLog('thmcache: flac: Zend media exception: ' . $e->getMessage());
			}
			break;

        case 'm4a':
            require_once 'Zend/Media/Iso14496.php';
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
                    $image = outImage($mime, $picture->getValue());
                }
            }
            catch (Zend_Media_Iso14496_Exception $e) {
				workerLog('thmcache: m4a: ' . $path);
				workerLog('thmcache: m4a: Zend media exception: ' . $e->getMessage());
            }
            break;
	}

	return $image;
}
function parseFolder($path) {
	//workerLog('thmcache: parseFolder(): ' . $path);
	// Default cover files
	$covers = array(
		'Cover.jpg', 'cover.jpg', 'Cover.jpeg', 'cover.jpeg', 'Cover.png', 'cover.png', 'Cover.tif', 'cover.tif', 'Cover.tiff', 'cover.tiff',
		'Folder.jpg', 'folder.jpg', 'Folder.jpeg', 'folder.jpeg', 'Folder.png', 'folder.png', 'Folder.tif', 'folder.tif', 'Folder.tiff', 'folder.tiff'
	);
	foreach ($covers as $file) {
		$result = getImage($path . $file);
		if ($result !== false) {
			break;
		}
	}

	if ($result === false) { // r44a
		// All other image files
		$extensions = array('jpg', 'jpeg', 'png', 'tif', 'tiff');
		$path = str_replace('[', '\[', $path);
		$path = str_replace(']', '\]', $path);
		foreach (glob($path . '*') as $file) {
			//workerLog('thmcache: parseFolder(): glob' . $file);
			if (is_file($file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $extensions)) {
				$result = getImage($file);
				if ($result !== false) {
					break;
				}
			}
		}
	}

	return $result;
}
