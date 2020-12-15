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
 * 2020-12-15 TC moOde 7.0.0
 *
 */

 /**
  * autocfggen exports the supported autoconfig settings to the screen.
  * Don't use this file directly but instead use the moodeutl -e option
  * (C) 2020 @bitlab (@bitkeeper Git)
  */

require_once dirname(__FILE__) . '/../inc/playerlib.php';

playerSession('open', '' ,'');
session_write_close();

print(autoconfigExtract());
?>
