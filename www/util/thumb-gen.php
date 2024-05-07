#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2015 Cover art extraction routines / Andreas Goetz (cpuidle@gmx.de)
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
$scanFormats = $_SESSION['library_thmgen_scan'];
$searchPri = $_SESSION['library_covsearchpri'];
$hiresThm = $_SESSION['library_hiresthm'];
$pixelRatio = floor($_SESSION['library_pixelratio']);
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

if ($hiresThm == 'Auto') {
	if ($pixelRatio == 2) {
		$thmW = 200;
		$thmQ = 75;
	}
	elseif ($pixelRatio >= 3) {
		$thmW = 400;
		$thmQ = 50;
	}
	else {
		$thmW = 100;
		$thmQ = 75;
	}
}
// Manual: Use the specified resolution and quality factor.
else {
	$hiresThmWQ = explode(',', $hiresThm);
	$thmW = substr($hiresThmWQ[0], 0, 3); // The numeric part ex: "400" from "400px"
	$thmQ = $hiresThmWQ[1];
}

workerLog('thumb-gen: Scan opt: ' . $scanFormats);
workerLog('thumb-gen: Priority: ' . $searchPri);
workerLog('thumb-gen: Res,Qual: ' . $hiresThm);
workerLog('thumb-gen: Px ratio: ' . $pixelRatio);
workerLog('thumb-gen: Th width: ' . $thmW);
workerLog('thumb-gen: Thm qual: ' . $thmQ);

// Ensure cache dir exists
if (!file_exists(THMCACHE_DIR)) {
	workerLog('thumb-gen: Info: Missing thmcache dir, new one created');
	sysCmd('mkdir ' . THMCACHE_DIR);
}

// List the dirs in /mnt and /media directories
$mntDirs = str_replace("\n", ', ', shell_exec('ls /mnt'));
$mediaDirs = str_replace("\n", ', ', shell_exec('ls /media'));
!empty($mediaDirs) ? $dirs = $mntDirs . substr($mediaDirs, 0, -2) : $dirs = substr($mntDirs, 0, -2);
$dirs = str_replace('moode-player, ', '', $dirs); // This mount point is only present in dev
workerLog('thumb-gen: Scanning: ' . $dirs);

// Generate the file list
$result = shell_exec('/var/www/util/list-songfiles.sh "' . $scanFormats . '" | sort');
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
$folderCnt = 0;	// Folder count
$newThms = 0;		// Number of new thumbs created
$cachedThms = 0;	// Number of thumbs that already exist in the cache

$line = strtok($result, "\n");
while ($line) {
	$fileA = explode(': ', $line, 2)[1];
	$dirA = dirname($fileA);

	$line = strtok("\n");

	$fileB = explode(': ', $line, 2)[1];
	$dirB = dirname($fileB);

	if ($dirA != $dirB) {
		phpSession('open');
		$_SESSION['thmcache_status'] = 'Scanning folder ' . ++$folderCnt . ' ' . $dirA;
		phpSession('close');

		if (!file_exists(THMCACHE_DIR . md5($dirA) . '.jpg')) {
			createThumb($fileA, $dirA, $searchPri, $thmW, $thmQ);
		}
		else {
			$cachedThms++;
		}
	}
}

$msg = 'Done: ' . $folderCnt . ' folders scanned, ' . $newThms . ' thumbs created, ' . $cachedThms . ' already in cache.';
phpSession('open');
$_SESSION['thmcache_status'] = $msg;
phpSession('close');
workerLog('thumb-gen: ' . $msg);

// Create thumbnail image
function createThumb($file, $dir, $searchPri, $thmW, $thmQ) {
	$path = MPD_MUSICROOT . $file;
	$imgStr = false;
	//workerLog('thumb-gen: Path: ' . $path);

	if ($searchPri == 'Embedded cover') {
		// Check for embedded cover in file
		$imgStr = getImage($path, $file);
	}

	if ($imgStr === false) {
		// Check for cover image file in containing dir
		$dirPath = pathinfo($path, PATHINFO_DIRNAME) . '/';
		$imgStr = parseFolder($dirPath);

		if ($imgStr === false) {
			if ($searchPri == 'Cover image file') {
				// Check for embedded cover
				$imgStr = getImage($path, $file);
			}
		}

		if ($imgStr === false) {
			// Nothing found
			$imgStr = NOT_FOUND_JPG;
		}
	}

	// Image file path, convert image to string
	if (strlen($imgStr) < 512) {
		//workerLog('thumb-gen: Image file: ' . $imgStr);
		$imgStr = file_get_contents($imgStr);
	}
	else {
		//workerLog('thumb-gen: Embedded image: ' . $file);
	}

	// NOTE: imagecreatefromstring() Supported formats: JPEG, PNG, GIF, BMP, WBMP, GD2, and WEBP
	if (false === ($image = imagecreatefromstring($imgStr))) {
		workerLog('thumb-gen: Error: imagecreatefromstring() failed: ' . $file);

		// Use default moOde cover
		$imgStr = file_get_contents(NOT_FOUND_JPG);
		if (false === ($image = imagecreatefromstring($imgStr))) {
			workerLog('thumb-gen: Error: imagecreatefromstring() failed: ' . NOT_FOUND_JPG);
			return;
		}
	}

	// Image h/w
	$imgW = imagesx($image);
	$imgH = imagesy($image);
	// Thumbnail height
	$thmH = round(($imgH / $imgW) * $thmW);

	// Copy or resample
	if ($imgW * $imgH <= $thmW * $thmH) {
		$resample = false;
		$thmH = $imgH;
		$thmW = $imgW;
	}
	else {
		$resample = true;
	}

	// Standard thumbnail
	if (($thumb = imagecreatetruecolor($thmW, $thmH)) === false) {
		workerLog('thumb-gen: Error: imagecreatetruecolor(thumb): ' . $file);
		return;
	}
	if ($resample === true) {
		//workerLog('thumb-gen: Resample: '. $file);
		if (imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thmW, $thmH, $imgW, $imgH) === false) {
			workerLog('thumb-gen: Error: imagecopyresampled(thumb): ' . $file);
			return;
		}
	}
	else {
		//workerLog('thumb-gen: Copy: '. $file);
		if (imagecopy($thumb, $image, 0, 0, 0, 0, $imgW, $imgH) === false) {
			workerLog('thumb-gen: Error: imagecopy(thumb): ' . $file);
			return;
		}
	}
	if (imagejpeg($thumb, THMCACHE_DIR . md5($dir) . '.jpg', $thmQ) === false) {
		workerLog('thumb-gen: Error: imagejpeg(thumb): ' . $file);
		return;
	}
	else {
		$GLOBALS['newThms']++;
	}
	if (imagedestroy($thumb) === false) {
		workerLog('thumb-gen: Error: imagedestroy(thumb): ' . $file);
		return;
	}

	// Small thumbnail
	if (($thumbSm = imagecreatetruecolor(THM_SM_W, THM_SM_H)) === false) {
		workerLog('thumb-gen: Error: imagecreatetruecolor(thumb_sm): ' . $file);
		return;
	}
	if (imagecopyresampled($thumbSm, $image, 0, 0, 0, 0, THM_SM_W, THM_SM_H, $imgW, $imgH) === false) {
		workerLog('thumb-gen: Error: imagecopyresampled(thumb_sm): ' . $file);
		return;
	}
	if (imagedestroy($image) === false) {
		workerLog('thumb-gen: Error: imagedestroy(thumb_sm): ' . $file);
		return;
	}
	if (imagejpeg($thumbSm, THMCACHE_DIR . md5($dir) . '_sm.jpg', THM_SM_Q) === false) {
		workerLog('thumb-gen: Error: imagejpeg(thumb_sm): ' . $file);
		return;
	}
	if (imagedestroy($thumbSm) === false) {
		workerLog('thumb-gen: Error: imagedestroy(thumb_sm): ' . $file);
		return;
	}

	// DEBUG
	//$size = getimagesize(THMCACHE_DIR . md5($dir) . '.jpg');
	//workerLog('COVER_WH:' . $imgW . '|' . $imgH . ' DESIRED_WH:' . $thmW . '|' . $thmH . ' ACTUAL_WH:' . $size[0] . '|' . $size[1] . '|' . $dir . '|' . md5($dir));
}

// Modified versions of coverart.php functions
// (C) 2015 Andreas Goetz
function outImage($mime, $data) {
	//workerLog('thumb-gen: outImage(): ' . $mime . ', ' . strlen($data) . ' bytes');
	switch (strtolower($mime)) {
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
		case 'dsf':
			require_once __DIR__ . '/../inc/Zend/Media/Dsd.php';
			try {
				$Dsd = new ZendEx_Media_Dsd($path, array('hash_only' => false));

				if (isset($Dsd->id3v2()->apic)) {
					$image = outImage($Dsd->id3v2()->apic->mimeType, $Dsd->id3v2()->apic->imageData);
				}
			} catch (ZendEx_Media_Dsd_Exception $e) {
				workerLog('thumb-gen: Error: ' . $e->getMessage() . ': ' . $file);
			}
			break;

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

	if ($result === false) {
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
