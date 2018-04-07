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
 *
 */
-->

<!DOCTYPE html>
<html lang="en">
<head>
	<title>moOde Player</title>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0 user-scalable=no">
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="cssw/flat-ui.css" rel="stylesheet">
    <link href="cssw/bootstrap-select.css" rel="stylesheet">
	<link href="css/bootstrap-fileupload.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
	<?php if ($section == 'index') { ?>
		<link href="css/jquery.countdown.css" rel="stylesheet">
	<?php } ?>
	<link href="css/jquery.pnotify.default.css" rel="stylesheet">
	<link href="cssw/panels.css" rel="stylesheet">
    <link href="css/moode.css" rel="stylesheet">

	<!-- favicons for desktop and mobile -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
	<link rel="apple-touch-icon" sizes="180x180" href="/v4-apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/v4-favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/v4-favicon-16x16.png">
	<link rel="manifest" href="/manifest.json">
	<link rel="mask-icon" href="/v4-safari-pinned-tab.svg" color="#5bbad5">
	<meta name="theme-color" content="#ffffff">
</head>
<body>

<div id="menu-top" class="ui-header ui-bar-f ui-header-fixed slidedown" data-position="fixed" data-role="header" role="banner">
	<div class="dropdown">
		<!-- // newui moOde logo -->
		<a class="dropdown-toggle btn" id="menu-settings" role="button" data-toggle="dropdown" data-target="#" href="#notarget" title="System menu" style="letter-spacing:-.5px;">m<span style="color:#e74c3c;">oO</span>de</a>
		<!--a class="dropdown-toggle btn" id="menu-settings" role="button" data-toggle="dropdown" data-target="#" href="#notarget" title="System menu" style="font-size: 18px; color: #dddddd;">Beta12</a-->
		<ul class="dropdown-menu" role="menu" aria-labelledby="menu-settings">
			<?php if ($section == 'index') { ?>
				<li><a href="#configure-modal" data-toggle="modal"><i class="icon-cogs sx"></i> Configure</a></li>
				<li class="context-menu menu-separator"><a href="#notarget" data-cmd="customize"><i class="icon-edit sx"></i> Customize</a></li>
				<li><a href="javascript:$('#players-modal .modal-body').load('players.php',function(e){$('#players-modal').modal('show');}); void 0"><i class="icon-forward sx"></i> Players</a></li>
				<li><a href="javascript:$('#audioinfo-modal .modal-body').load('audioinfo.php',function(e){$('#audioinfo-modal').modal('show');}); void 0"><i class="icon-music sx"></i> Audio info</a></li>
				<li class="context-menu"><a href="#notarget" data-cmd="viewplayhistory"><i class="icon-book sx"></i> Play history</a></li>
				<li class="context-menu menu-separator"><a href="javascript:location.reload(true); void 0"><i class="icon-repeat sx"></i> Refresh</a></li>
				<li><a href="#restart-modal" data-toggle="modal"><i class="icon-power-off sx"></i> Restart</a></li>
			<?php } else { ?>
				<li class="context-menu menu-separator"><a href="#configure-modal" data-toggle="modal"><i class="icon-cogs sx"></i> Configure</a></li>
				<li><a href="src-config.php"><i class="icon-folder-open sx"></i> Sources</a></li>
				<li><a href="snd-config.php"><i class="icon-music sx"></i> Audio</a></li>
				<li><a href="net-config.php"><i class="icon-sitemap sx"></i> Network</a></li>
				<li class="context-menu menu-separator"><a href="sys-config.php"><i class="icon-laptop sx"></i> System</a></li>
				<li><a href="javascript:$('#audioinfo-modal .modal-body').load('audioinfo.php',function(e){$('#audioinfo-modal').modal('show');}); void 0"><i class="icon-music sx"></i> Audio info</a></li>
				<li><a href="javascript:$('#sysinfo-modal .modal-body').load('sysinfo.php',function(e){$('#sysinfo-modal').modal('show');}); void 0"><i class="icon-laptop sx"></i> System info</a></li>
				<li class="context-menu menu-separator"><a href="#notarget" data-cmd="aboutmoode"><i class="icon-info sx"></i> About</a></li>
				<li><a href="javascript:location.reload(true); void 0"><i class="icon-repeat sx"></i> Refresh</a></li>
				<li><a href="#restart-modal" data-toggle="modal"><i class="icon-power-off sx"></i> Restart</a></li>
			<?php } ?>
		</ul>
	</div>
	<div class="menu-top">
		<span id="clockradio-icon" class="clockradio-off" title="Clock radio on/off indicator"><i class="icon-time"></i></span>
	</div>
</div>

<div id="menu-bottom" class="ui-footer ui-bar-f ui-footer-fixed slidedown" data-position="fixed" data-role="footer" role="banner">
	<ul>
		<?php if ($section == 'index') { ?>
			<li id="open-browse-panel"><a href="#browse-panel" class="open-browse-panel" data-toggle="tab">Browse</a></li>
			<li id="open-library-panel"><a href="#library-panel" class="open-library-panel" data-toggle="tab">Library</a></li>
			<li id="open-playback-panel" class="active"><a href="#playback-panel" class="close-panels" data-toggle="tab">Playback</a></li>
		<?php } else { ?>
			<li id="open-browse-panel"><a href="index.php#browse-panel" class="open-browse-panel">Browse</a></li>
			<li id="open-library-panel"><a href="index.php#library-panel" class="open-library-panel">Library</a></li>
			<li id="open-playback-panel"><a href="index.php#playback-panel" class="close-panels">Playback</a></li>
		<?php } ?>
	</ul>
</div>
