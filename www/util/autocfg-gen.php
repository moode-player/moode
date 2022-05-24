#!/usr/bin/php
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
 */

 /**
  * autocfg-gen exports the supported autoconfig settings to the screen.
  * Don't use this file directly but instead use the moodeutl -e option
  * (C) 2020 @bitlab (@bitkeeper Git)
  */

require_once __DIR__ . '/../inc/common.php';
require_once __DIR__ . '/../inc/sql.php';
require_once __DIR__ . '/../inc/autocfg.php';

$_SESSION = [];
$params = sqlRead('cfg_system', sqlConnect());
foreach ($params as $row) {
    $_SESSION[$row['param']] = $row['value'];
}

print(autoconfigExtract());
?>
