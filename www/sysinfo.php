<?php
/**
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';

$file = '/var/local/www/sysinfo.txt';
sysCmd('/var/www/util/sysinfo.sh html > ' . $file);

$fh = fopen($file, 'r');
$text = fread($fh, filesize($file));
fclose($fh);

$tpl = 'sysinfo.html';
eval('echoTemplate("' . getTemplate("templates/$tpl") . '");');
