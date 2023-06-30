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

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/cdsp.php';

phpSession('open_ro');

$_camillagui_url = '/camilladsp/gui/index.html';

waitWorker('cdsp-configeditor');

$tpl = "cdsp-configeditor.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

const ACCENT_LOOKUP = Array (
    'Alizarin' => '#c0392b',
    'Amethyst' => '#8e44ad',
    'Berry' => '#B53471',
    'Bluejeans' => '#1a439c',
    'BlueLED' => '#005AFD',
    'Carrot' => '#d35400',
    'Emerald' => '#27ae60',
    'Fallenleaf' => '#cb8c3e',
    'Grass' => '#7ead49',
    'Herb' => '#317589',
    'Lavender' => '#876dc6',
    'Lipstick' => '#eb2f06',
    'Moss' => '#218c74',
    'River'  => '#2980b9',
    'Rose'  => '#c1649b',
    'Silver'  => '#999999',
    'Turquoise'  => '#16a085');

// Set camillagui accent color
if (array_key_exists($_SESSION['accent_color'], ACCENT_LOOKUP)) {
    $accentColor = ACCENT_LOOKUP[$_SESSION['accent_color']];
    $cdspCSSFile = "/opt/camillagui/build/css-variables.css";

    // Only update file if needed
    $result = sysCmd('sed -n "s/^.*accent-color[:] \s*\(\S*\).*;$/\1/p" ' . $cdspCSSFile);
    if ($result[0] != $accentColor) {
        sysCmd("sed -i -s 's/accent-color:.*;$/accent-color: " . $accentColor . ";/g' " . $cdspCSSFile);
    }
}

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
