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

$_camillagui_url = 'http://'. $_SERVER['HTTP_HOST'] . ':15000';
session_write_close();

waitWorker(1, 'cdsp-configeditor');

$tpl = "cdsp-configeditor.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);


const ACCENT_LOOKUP = Array('Amethyst' => '#8e44ad',
                        'Bluejeans' => '#1a439c',
                        'Carrot' => '#d35400',
                        'Emerald' => '#27ae60',
                        'Fallenleaf' => '#cb8c3e',
                        'Grass' => '#7ead49',
                        'Herb' => '#317589',
                        'Lavender' => '#876dc6',
                        'River'  => '#2980b9',
                        'Rose'  => '#c1649b',
                        'Silver'  => '#999999',
                        'Turquoise'  => '#16a085',
                        'Alizarin' => '#c0392b');

$accent_theme = $_SESSION['accent_color'];
// Match camillagui accent color with mooOde
if( array_key_exists($accent_theme , ACCENT_LOOKUP )) {
    $accent_color = ACCENT_LOOKUP[$accent_theme];
    $cdsp_css_file = "/opt/camillagui/build/css-variables.css";

    // Only update file if needed
    $output = sysCmd('sed -n "s/^.*accent-color[:] \s*\(\S*\).*;$/\1/p" ' . $cdsp_css_file);
    if($output[0] != $accent_color ) {
        sysCmd("sed -i -s 's/accent-color:.*;$/accent-color: " . $accent_color . ";/g' " . $cdsp_css_file);
    }
}

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
