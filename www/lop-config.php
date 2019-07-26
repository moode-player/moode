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
 * 2019-MM-DD TC moOde 6.0.0
 *
 */

require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');
$dbh = cfgdb_connect();

// Apply setting changes
if (isset($_POST['save']) && $_POST['save'] == '1') {
	// Detect changes requiring cache delete
	if ($_POST['config']['library_artist_col'] != $_SESSION['library_artist_col'] ||
		$_POST['config']['library_album_col'] != $_SESSION['library_album_col'] ||
		$_POST['config']['ignore_articles'] != $_SESSION['ignore_articles'] ||
		$_POST['config']['compilation_rollup'] != $_SESSION['compilation_rollup'] ||
		$_POST['config']['compilation_excludes'] != $_SESSION['compilation_excludes'] ||
		$_POST['config']['library_utf8rep'] != $_SESSION['library_utf8rep']) {
		clearLibCache();
	}

	foreach ($_POST['config'] as $key => $value) {
		cfgdb_update('cfg_system', $dbh, $key, $value);
		$_SESSION[$key] = $value;
	}

	$_SESSION['notify']['title'] = 'Changes saved';
}
session_write_close();

// Artist list ordering
$_select['library_artist_col'] .= "<option value=\"Artist\" " . (($_SESSION['library_artist_col'] == 'Artist') ? "selected" : "") . ">Artist</option>\n";
$_select['library_artist_col'] .= "<option value=\"ArtistSort\" " . (($_SESSION['library_artist_col'] == 'ArtistSort') ? "selected" : "") . ">ArtistSort</option>\n";
$_select['library_artist_col'] .= "<option value=\"AlbumArtist\" " . (($_SESSION['library_artist_col'] == 'AlbumArtist') ? "selected" : "") . ">AlbumArtist</option>\n";
$_select['library_artist_col'] .= "<option value=\"AlbumArtistSort\" " . (($_SESSION['library_artist_col'] == 'AlbumArtistSort') ? "selected" : "") . ">AlbumArtistSort</option>\n";
// Album list ordering
$_select['library_album_col'] .= "<option value=\"Album\" " . (($_SESSION['library_album_col'] == 'Album') ? "selected" : "") . ">Album</option>\n";
$_select['library_album_col'] .= "<option value=\"AlbumSort\" " . (($_SESSION['library_album_col'] == 'AlbumSort') ? "selected" : "") . ">AlbumSort</option>\n";
// Show Genres column
$_select['show_genres'] .= "<option value=\"Yes\" " . (($_SESSION['show_genres'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['show_genres'] .= "<option value=\"No\" " . (($_SESSION['show_genres'] == 'No') ? "selected" : "") . ">No</option>\n";
// Ignore articles
$_select['ignore_articles'] = empty($_SESSION['ignore_articles']) ? 'None' : $_SESSION['ignore_articles'];
// Compilation rollup
$_select['compilation_rollup'] .= "<option value=\"Yes\" " . (($_SESSION['compilation_rollup'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['compilation_rollup'] .= "<option value=\"No\" " . (($_SESSION['compilation_rollup'] == 'No') ? "selected" : "") . ">No</option>\n";
// Compilation excludes
$_select['compilation_excludes'] = $_SESSION['compilation_excludes'];
// Library utf8 replace
$_select['library_utf8rep'] .= "<option value=\"Yes\" " . (($_SESSION['library_utf8rep'] == 'Yes') ? "selected" : "") . ">Yes</option>\n";
$_select['library_utf8rep'] .= "<option value=\"No\" " . (($_SESSION['library_utf8rep'] == 'No') ? "selected" : "") . ">No</option>\n";
// Hi-res thumbnails
$_select['library_hiresthm'] .= "<option value=\"Auto\" " . (($_SESSION['library_hiresthm'] == 'Auto') ? "selected" : "") . ">Auto</option>\n";
$_select['library_hiresthm'] .= "<option value=\"100px\" " . (($_SESSION['library_hiresthm'] == '100px') ? "selected" : "") . ">100 px</option>\n";
$_select['library_hiresthm'] .= "<option value=\"200px\" " . (($_SESSION['library_hiresthm'] == '200px') ? "selected" : "") . ">200 px</option>\n";
$_select['library_hiresthm'] .= "<option value=\"300px\" " . (($_SESSION['library_hiresthm'] == '300px') ? "selected" : "") . ">300 px</option>\n";
$_select['library_hiresthm'] .= "<option value=\"400px\" " . (($_SESSION['library_hiresthm'] == '400px') ? "selected" : "") . ">400 px</option>\n";
// Cover search prioroty
$_select['library_covsearchpri'] .= "<option value=\"Embedded cover\" " . (($_SESSION['library_covsearchpri'] == 'Embedded cover') ? "selected" : "") . ">Embedded cover</option>\n";
$_select['library_covsearchpri'] .= "<option value=\"Cover image file\" " . (($_SESSION['library_covsearchpri'] == 'Cover image file') ? "selected" : "") . ">Cover image file</option>\n";
// Instant play action
$_select['library_instant_play'] .= "<option value=\"Add/Play\" " . (($_SESSION['library_instant_play'] == 'Add/Play') ? "selected" : "") . ">Add/Play</option>\n";
$_select['library_instant_play'] .= "<option value=\"Clear/Play\" " . (($_SESSION['library_instant_play'] == 'Clear/Play') ? "selected" : "") . ">Clear/Play</option>\n";

waitWorker(1, 'lop-config');

$tpl = "lop-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('/var/local/www/header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
