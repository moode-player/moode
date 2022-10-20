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
 */

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/sql.php';

// Image to use when no cover found
const NOT_FOUND_JPG = '/var/www/images/notfound.jpg';

//
// MAIN
//

workerLog('thumb-gen: Start');
session_id(phpSession('get_sessionid'));

phpSession('open');
$search_pri = $_SESSION['library_covsearchpri'];
$hires_thm = $_SESSION['library_hiresthm'];
$pixel_ratio = floor($_SESSION['library_pixelratio']);
phpSession('close');

// Auto: Uses the device Pixel Ratio to set an optimum resolution and quality while maintaining the smallest file size (fastest image load time).
// NOTE: Manual should be used for Desktops
/*
Device		Physical	Pixel	Logical
			Res (px) 	Ratio	Res (px)
---------------------------------------------------
iPhone 3g	320×480		1		320×480
iPhone 4s	640×960		2		320×480
iPhone 5	640×1136	2		320×568
iPhone 6	750×1334	2		375×667
iPad 2		768×1024	1		768×1024
iPad 3		1536×2048	2		768×1024
Samsung GS3	720×1280	2		360×640
Samsung GS4	1080×1920	3		360×640
iMac 27"	2560x1440	1		2560x1440
iMac 27" R	5120x2880 	2		2560x1440
*/

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
// Manual: Use the specified resolution and quality factor.
else {
	$hires_thm_wq = explode(',', $hires_thm);
	$thm_w = substr($hires_thm_wq[0], 0, 3); // The numeric part ex: "400" from "400px"
	$thm_q = $hires_thm_wq[1];
}

workerLog('thumb-gen: Priority: ' . $search_pri);
workerLog('thumb-gen: Res,Qual: ' . $hires_thm);
workerLog('thumb-gen: Px ratio: ' . $pixel_ratio);
workerLog('thumb-gen: Th width: ' . $thm_w);
workerLog('thumb-gen: Thm qual: ' . $thm_q);

// Ensure cache dir exists
if (!file_exists(THMCACHE_DIR)) {
	workerLog('thumb-gen: Info: Missing thmcache dir, new one created');
	sysCmd('mkdir ' . THMCACHE_DIR);
}

// List the dirs in /mnt and /media directories
$mnt_dirs = str_replace("\n", ', ', shell_exec('ls /mnt'));
$media_dirs = str_replace("\n", ', ', shell_exec('ls /media'));
!empty($media_dirs) ? $dirs = $mnt_dirs . substr($media_dirs, 0, -2) : $dirs = substr($mnt_dirs, 0, -2);
$dirs = str_replace('moode-player, ', '', $dirs); // This mount point is only present in dev
workerLog('thumb-gen: Scanning: ' . $dirs);

// Generate the file list
$result = shell_exec('/var/www/util/list-songfiles.sh | sort');
if (is_null($result) || substr($result, 0, 2) == 'OK') {
	workerLog('thumb-gen: Exit: no files found');
	phpSession('open');
	$_SESSION['thmcache_status'] = 'No files found';
	phpSession('close');
	exit(0);
}

// Generate thumbnails
// - Compare the containing dir paths for file (file_a) and file+1 (file_b)
// - When they are different we create a thumb using file_a and dir_a
$folder_cnt = 0;	// Folder count
$new_thms = 0;		// Number of new thumbs created
$cached_thms = 0;	// Number of thumbs that already exist in the cache

$line = strtok($result, "\n");
while ($line) {
	$file_a = explode(': ', $line, 2)[1];
	$dir_a = dirname($file_a);

	$line = strtok("\n");

	$file_b = explode(': ', $line, 2)[1];
	$dir_b = dirname($file_b);

	// if we are on a virtual directory, swap things around
	if ('cue' == getFileExt($file_a)) {
		$dir_a = $file_a;
		$file_a = ensureAudioFile($file_a);
	}
	if ('cue' == getFileExt($file_b)) {
		$dir_b = $file_b;
		$file_b = ensureAudioFile($file_b);
	}

	if ($dir_a != $dir_b) {
		phpSession('open');
		$_SESSION['thmcache_status'] = 'Scanning folder ' . ++$folder_cnt . ' ' . $dir_a;
		phpSession('close');

		if (!file_exists(THMCACHE_DIR . md5($dir_a) . '.jpg')) {
			createThumb($file_a, $dir_a, $search_pri, $thm_w, $thm_q);
		}
		else {
			$cached_thms++;
		}
	}
}

$msg = 'Done: ' . $folder_cnt . ' folders scanned, ' . $new_thms . ' thumbs created, ' . $cached_thms . ' already in cache.';
phpSession('open');
$_SESSION['thmcache_status'] = $msg;
phpSession('close');
workerLog('thumb-gen: ' . $msg);

// Create thumbnail image
function createThumb($file, $dir, $search_pri, $thm_w, $thm_q) {
	$path = MPD_MUSICROOT . $file;
	$img_str = false;
	//workerLog('thumb-gen: Path: ' . $path);

	if ($search_pri == 'Embedded cover') {
		// Check for embedded cover in file
		$img_str = getImage($path, $file);
	}

	if ($img_str === false) {
		// Check for cover image file in containing dir
		$dirpath = pathinfo($path, PATHINFO_DIRNAME) . '/';
		$img_str = parseFolder($dirpath);

		if ($img_str === false) {
			if ($search_pri == 'Cover image file') {
				// Check for embedded cover
				$img_str = getImage($path, $file);
			}
		}

		if ($img_str === false) {
			// Nothing found
			$img_str = NOT_FOUND_JPG;
		}
	}

	// Image file path, convert image to string
	if (strlen($img_str) < 512) {
		//workerLog('thumb-gen: Image file: ' . $img_str);
		$img_str = file_get_contents($img_str);
	}
	else {
		//workerLog('thumb-gen: Embedded image: ' . $file);
	}

	// NOTE: imagecreatefromstring() Supported formats: JPEG, PNG, GIF, BMP, WBMP, GD2, and WEBP
	if (false === ($image = imagecreatefromstring($img_str))) {
		workerLog('thumb-gen: Error: imagecreatefromstring() failed: ' . $file);

		// Use default moOde cover
		$img_str = file_get_contents(NOT_FOUND_JPG);
		if (false === ($image = imagecreatefromstring($img_str))) {
			workerLog('thumb-gen: Error: imagecreatefromstring() failed: ' . NOT_FOUND_JPG);
			return;
		}
	}

	// Image h/w
	$img_w = imagesx($image);
	$img_h = imagesy($image);
	// Thumbnail height
	$thm_h = round(($img_h / $img_w) * $thm_w);

	// Copy or resample
	if ($img_w * $img_h <= $thm_w * $thm_h) {
		$resample = false;
		$thm_h = $img_h;
		$thm_w = $img_w;
	}
	else {
		$resample = true;
	}

	// Standard thumbnail
	if (($thumb = imagecreatetruecolor($thm_w, $thm_h)) === false) {
		workerLog('thumb-gen: Error: imagecreatetruecolor(thumb): ' . $file);
		return;
	}
	if ($resample === true) {
		//workerLog('thumb-gen: Resample: '. $file);
		if (imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thm_w, $thm_h, $img_w, $img_h) === false) {
			workerLog('thumb-gen: Error: imagecopyresampled(thumb): ' . $file);
			return;
		}
	}
	else {
		//workerLog('thumb-gen: Copy: '. $file);
		if (imagecopy($thumb, $image, 0, 0, 0, 0, $img_w, $img_h) === false) {
			workerLog('thumb-gen: Error: imagecopy(thumb): ' . $file);
			return;
		}
	}
	if (imagejpeg($thumb, THMCACHE_DIR . md5($dir) . '.jpg', $thm_q) === false) {
		workerLog('thumb-gen: Error: imagejpeg(thumb): ' . $file);
		return;
	}
	else {
		$GLOBALS['new_thms']++;
	}
	if (imagedestroy($thumb) === false) {
		workerLog('thumb-gen: Error: imagedestroy(thumb): ' . $file);
		return;
	}

	// Small thumbnail
	if (($thumb_sm = imagecreatetruecolor(THM_SM_W, THM_SM_H)) === false) {
		workerLog('thumb-gen: Error: imagecreatetruecolor(thumb_sm): ' . $file);
		return;
	}
	if (imagecopyresampled($thumb_sm, $image, 0, 0, 0, 0, THM_SM_W, THM_SM_H, $img_w, $img_h) === false) {
		workerLog('thumb-gen: Error: imagecopyresampled(thumb_sm): ' . $file);
		return;
	}
	if (imagedestroy($image) === false) {
		workerLog('thumb-gen: Error: imagedestroy(thumb_sm): ' . $file);
		return;
	}
	if (imagejpeg($thumb_sm, THMCACHE_DIR . md5($dir) . '_sm.jpg', THM_SM_Q) === false) {
		workerLog('thumb-gen: Error: imagejpeg(thumb_sm): ' . $file);
		return;
	}
	if (imagedestroy($thumb_sm) === false) {
		workerLog('thumb-gen: Error: imagedestroy(thumb_sm): ' . $file);
		return;
	}

	// DEBUG
	//$size = getimagesize(THMCACHE_DIR . md5($dir) . '.jpg');
	//workerLog('COVER_WH:' . $img_w . '|' . $img_h . ' DESIRED_WH:' . $thm_w . '|' . $thm_h . ' ACTUAL_WH:' . $size[0] . '|' . $size[1] . '|' . $dir . '|' . md5($dir));
}

// Modified versions of coverart.php functions
// (C) 2015 Andreas Goetz
function outImage($mime, $data) {
	//workerLog('thumb-gen: outImage(): ' . $mime . ', ' . strlen($data) . ' bytes');
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
function getImage($path, $file = '') {
	//workerLog('thumb-gen: getImage(): ' . $file);
	if (!file_exists($path)) {
		//workerLog('thumb-gen: getImage(): File does not exist: ' . $file);
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
			require_once __DIR__ . '/../inc/Zend/Media/Id3v2.php';
			try {
				$id3v2 = new Zend_Media_Id3v2($path, array('hash_only' => false)); // r44a

				if (isset($id3v2->apic)) {
					//workerLog('thmcache; mp3: id3v2: apic->imageData: length: ' . strlen($id3v2->apic->imageData));
					$image = outImage($id3v2->apic->mimeType, $id3v2->apic->imageData);
				}
			}
			catch (Zend_Media_Id3_Exception $e) {
				workerLog('thumb-gen: Error: ' . $e->getMessage() . ': ' . $file);
			}
			break;

		case 'flac':
			require_once __DIR__ . '/../inc/Zend/Media/Flac.php';
			try {
				$flac = new Zend_Media_Flac($path, $hash_only = false); // r44a

				if ($flac->hasMetadataBlock(Zend_Media_Flac::PICTURE)) {
					$picture = $flac->getPicture();
					$image = outImage($picture->getMimeType(), $picture->getData());
				}
			}
			catch (Zend_Media_Flac_Exception $e) {
				workerLog('thumb-gen: Error: ' . $e->getMessage() . ': ' . $file);
			}
			break;

        case 'm4a':
            require_once __DIR__ . '/../inc/Zend/Media/Iso14496.php';
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
				workerLog('thumb-gen: Error: ' . $e->getMessage() . ': ' . $file);
            }
            break;
	}

	return $image;
}
function parseFolder($path) {
	//workerLog('thumb-gen: parseFolder(): ' . $path);
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
			//workerLog('thumb-gen: parseFolder(): glob' . $file);
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
