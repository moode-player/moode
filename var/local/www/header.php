<!--
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * tsunamp player ui (C) 2013 Andrea Coiutti & Simone De Gregori
 * http://www.tsunamp.com
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
 * 2018-01-26 TC moOde 4.0
 * 2018-04-02 TC moOde 4.1
 * - minor cleanup
 * - remove accumulated  code
 * 2018-07-11 TC moOde 4.2
 * - add BlueZ to menu
 * - new tabs, other code for newui v2
 * - CoverView
 * - font-awesome 5
 * 2018-09-27 TC moOde 4.3
 * - rm <meta name="apple-mobile-web-app-status-bar-style" content="black">
 * - fix external link for Music tab
 * - comment out manifest link cuz it breaks IOS Add to Home
 * 2018-10-19 TC moOde 4.3 update
 * - album cover backdrop
 *
 */
-->

<!DOCTYPE html>
<html lang="en">
<head>
	<title>moOde Player</title>

	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0 user-scalable=no">

<!-- versioned resources -->
	<?php
	versioned_resource ('css/bootstrap.min.css');
	versioned_resource ('cssw/bootstrap-select.css');
	versioned_resource ('cssw/flat-ui.css');
	versioned_resource ('css/fontawesome-moode.css');
	if ($section == 'index') {
		versioned_resource ('css/jquery.countdown.css');
	}
	versioned_resource ('css/jquery.pnotify.default.css');
	versioned_resource ('cssw/panels.css');
	versioned_resource ('css/moode.css');
	?>
	<!-- Apple -->
	<meta name="apple-mobile-web-app-capable" content="yes">
	<link rel="apple-touch-icon" sizes="180x180" href="/v5-apple-touch-icon.png">
	<link rel="mask-icon" href="/v5-safari-pinned-tab.svg" color="#5bbad5">
	<!-- Android/Chrome -->
	<link rel="icon" type="image/png" sizes="32x32" href="/v5-favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/v5-favicon-16x16.png">
	<!--link rel="manifest" href="/site.webmanifest"-->
	<meta name="theme-color" content="#ffffff"-->
	<!-- Microsoft -->
	<meta name="msapplication-TileColor" content="#da532c">
</head>

<body onorientationchange="javascript:location.reload(true); void 0;">
	<!-- ALBUM COVER BACKDROP -->
	<div id="cover-backdrop"></div>

	<div id="menu-top" class="ui-header ui-bar-f ui-header-fixed slidedown" data-position="fixed" data-role="header" role="banner">
		<div class="dropdown">
			<a class="dropdown-toggle btn" id="menu-settings" role="button" data-toggle="dropdown" data-target="#" href="#notarget" title="System menu">m<i class="fas fa-chevron-down"></i></a>
			<ul class="dropdown-menu" role="menu" aria-labelledby="menu-settings">
				<?php if ($section == 'index') { ?>
					<li><a href="#configure-modal" data-toggle="modal"><i class="fas fa-cog sx"></i> Configure</a></li>
					<li class="context-menu menu-separator"><a href="#notarget" data-cmd="customize"><i class="fas fa-edit sx"></i> Customize</a></li>
					<li><a href="blu-config.php"><i class="fas fa-wifi sx"></i> BlueZ</a></li-->
					<li class="context-menu"><a href="#notarget" data-cmd="scnsaver"><i class="fas fa-tv sx"></i> CoverView</a></li>
					<li><a href="javascript:$('#players-modal .modal-body').load('players.php',function(e){$('#players-modal').modal('show');}); void 0"><i class="fas fa-forward sx"></i> Players</a></li>
					<li><a href="javascript:$('#audioinfo-modal .modal-body').load('audioinfo.php',function(e){$('#audioinfo-modal').modal('show');}); void 0"><i class="fas fa-music sx"></i> Audio info</a></li>
					<li class="context-menu"><a href="#notarget" data-cmd="viewplayhistory"><i class="fas fa-book sx"></i> Play history</a></li>
					<li class="context-menu menu-separator"><a href="javascript:location.reload(true); void 0"><i class="fas fa-redo sx"></i> Refresh</a></li>
					<li><a href="#restart-modal" data-toggle="modal"><i class="fas fa-power-off sx"></i> Restart</a></li>
				<?php } else { ?>
					<li class="context-menu menu-separator"><a href="#configure-modal" data-toggle="modal"><i class="fas fa-cog sx"></i> Configure</a></li>
					<li><a href="src-config.php"><i class="fas fa-database sx"></i> Sources</a></li>
					<li><a href="snd-config.php"><i class="fas fa-volume-up sx"></i> Audio</a></li>
					<li><a href="net-config.php"><i class="fas fa-sitemap sx"></i> Network</a></li>
					<li class="context-menu menu-separator"><a href="sys-config.php"><i class="fas fa-desktop-alt sx"></i> System</a></li>
					<li><a href="javascript:$('#audioinfo-modal .modal-body').load('audioinfo.php',function(e){$('#audioinfo-modal').modal('show');}); void 0"><i class="fas fa-music sx"></i> Audio info</a></li>
					<li><a href="javascript:$('#sysinfo-modal .modal-body').load('sysinfo.php',function(e){$('#sysinfo-modal').modal('show');}); void 0"><i class="fas fa-file-alt sx"></i> System info</a></li>
					<li class="context-menu menu-separator"><a href="#notarget" data-cmd="aboutmoode"><i class="fas fa-info sx"></i> About</a></li>
					<li><a href="javascript:location.reload(true); void 0"><i class="fas fa-redo sx"></i> Refresh</a></li>
					<li><a href="#restart-modal" data-toggle="modal"><i class="fas fa-power-off sx"></i> Restart</a></li>
				<?php } ?>
			</ul>
		</div>
		<div class="menu-top">
			<span id="clockradio-icon" class="clockradio-off" title="Clock radio indicator">•</span>
		</div>
	</div>
	
	<div id="menu-bottom" class="btn-group btn-list ui-footer ui-bar-f ui-footer-fixed slidedown" data-position="fixed" data-role="footer" role="banner">
		<ul>
			<?php if ($section == 'index') { ?>
				<li id="open-browse-panel" class="btn"><a href="#radio-panel" class="open-browse-panel" data-toggle="tab">Radio</a></li>
				<li id="open-library-panel" class="btn"><a href="#library-panel" class="open-library-panel" data-toggle="tab">Music</a></li>
				<li id="open-playback-panel" class="btn active"><a href="#playback-panel" class="close-panels" data-toggle="tab">Playback</a></li>
			<?php } elseif ($_SESSION['musictab_default'] == 'Browse') { ?>
				<li id="open-browse-panel" class="btn"><a href="index.php#radio-panel" class="open-browse-panel">Radio</a></li>
				<li id="open-library-panel" class="btn"><a href="index.php#browse-panel" class="open-library-panel">Music</a></li>
				<li id="open-playback-panel" class="btn"><a href="index.php#playback-panel" class="close-panels">Playback</a></li>
			<?php } else { ?>
				<li id="open-browse-panel" class="btn"><a href="index.php#radio-panel" class="open-browse-panel">Radio</a></li>
				<li id="open-library-panel" class="btn"><a href="index.php#library-panel" class="open-library-panel">Music</a></li>
				<li id="open-playback-panel" class="btn"><a href="index.php#playback-panel" class="close-panels">Playback</a></li>
			<?php } ?>
		</ul>
	</div>
