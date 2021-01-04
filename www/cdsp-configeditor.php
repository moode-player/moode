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
 * 2021-MM-DD TC moOde 7.x.x
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';
require_once dirname(__FILE__) . '/inc/cdsp.php';

playerSession('open', '' ,'');

$_camillagui_url = 'http://'. $_SERVER['HTTP_HOST'] . ':5000';
session_write_close();

waitWorker(1, 'cdsp-configeditor');

$tpl = "cdsp-configeditor.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
