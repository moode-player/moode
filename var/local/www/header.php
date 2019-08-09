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
 * 2019-08-08 TC moOde 6.0.0
 *
 */
-->
<?php
    //workerLog('-- header.php');
    $return = session_start();
    //workerLog('session_start=' . (($return) ? 'TRUE' : 'FALSE'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<title>moOde Player</title>

	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">

	<!-- VERSIONED RESOURCES -->
	<?php
		// Common css
		versioned_resource('css/bootstrap.min.css');
		versioned_resource('css/bootstrap-select.css');
		versioned_resource('css/flat-ui.css');
		versioned_resource('css/jquery.pnotify.default.css');
		versioned_resource('css/fontawesome-moode.css');
		versioned_resource('css/panels.css');
		versioned_resource('css/moode.css');

		// Common js
		versioned_script('js/bootstrap.min.js');
		versioned_script('js/bootstrap-select.min.js');
		versioned_script('js/jquery.pnotify.min.js');
		versioned_script('js/playerlib.js');
		versioned_script('js/links.js');

		// Playback / Library
		if ($section == 'index') {
			versioned_resource('css/jquery.countdown.css');
			versioned_script('js/jquery.countdown.js');
			versioned_script('js/jquery.scrollTo.min.js');
			versioned_script('js/jquery.touchSwipe.min.js');
			versioned_script('js/jquery.lazyload.js');
			versioned_script('js/jquery.md5.js');
			versioned_script('js/jquery.adaptive-backgrounds.js');
			versioned_script('js/notify.js');
			versioned_script('js/jquery.knob.js');
			versioned_script('js/bootstrap-contextmenu.js');
            versioned_script('js/scripts-library.js');
            versioned_script('js/scripts-panels.js');
		}
		// Configs
		else {
			versioned_script('js/custom_checkbox_and_radio.js');
			versioned_script('js/custom_radio.js');
			versioned_script('js/jquery.tagsinput.js');
			versioned_script('js/jquery.placeholder.js');
			versioned_script('js/i18n/_messages.en.js', 'text/javascript');
			versioned_script('js/application.js');
            versioned_script('js/notify.js');
			versioned_script('js/scripts-configs.js');
		}
	?>

	<!-- MOBILE APP ICONS -->
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
	<div aria-label="Album Cover Backdrop" id="cover-backdrop"></div>

	<!-- HEADER -->
	<div id="menu-top" class="ui-header ui-bar-f ui-header-fixed slidedown" data-position="fixed" data-role="header" role="banner">
		<div aria-label="Switch to Playbar" id="playback-switch"><div></div></div>

		<div id="config-back">
			<a aria-label="Back" href="<?php echo $_SESSION['http_config_back'] ?>"><i class="far fa-arrow-left"></i></a>
		</div>

		<div id="config-tabs" class="viewswitch-cfgs hide">
			<a id="lib-config-btn" class="btn" href="lib-config.php">Library</a>
			<a id="snd-config-btn" class="btn" href="snd-config.php">Audio</a>
			<a id="net-config-btn" class="btn" href="net-config.php">Network</a>
			<a id="sys-config-btn" class="btn" href="sys-config.php">System</a>
		</div>

		<div aria-label="Busy" class="busy-spinner"></div>

		<!-- MAIN MENU -->
		<div class="dropdown">
			<a aria-label="Menu" class="dropdown-toggle btn" id="menu-settings" role="button" data-toggle="dropdown" data-target="#" href="#notarget">m<i class="fas fa-chevron-down"></i></a>
			<ul class="dropdown-menu" role="menu" aria-labelledby="menu-settings">
				<?php if ($section == 'index') { ?>
					<li><a href="#configure-modal" data-toggle="modal"><i class="fas fa-cog sx"></i> Configure</a></li>
					<li class="context-menu menu-separator"><a href="#notarget" data-cmd="appearance"><i class="fas fa-eye sx"></i> Appearance</a></li>
					<li><a href="blu-config.php"><i class="fas fa-wifi sx"></i> BlueZ</a></li>
					<li><a href="javascript:$('#players-modal .modal-body').load('players.php',function(e){$('#players-modal').modal('show');}); void 0"><i class="fas fa-forward sx"></i> Players</a></li>
					<li><a href="javascript:$('#audioinfo-modal .modal-body').load('audioinfo.php',function(e){$('#audioinfo-modal').modal('show');}); void 0"><i class="fas fa-music sx"></i> Audio info</a></li>
					<li class="context-menu"><a href="#notarget" data-cmd="viewplayhistory"><i class="fas fa-book sx"></i> Play history</a></li>
					<li class="context-menu"><a href="#notarget" data-cmd="quickhelp"><i class="fas fa-info sx"></i> Quick help</a></li-->
					<li class="context-menu menu-separator"><a href="javascript:location.reload(true); void 0"><i class="fas fa-redo sx"></i> Refresh</a></li>
					<li><a href="#restart-modal" data-toggle="modal"><i class="fas fa-power-off sx"></i> Restart</a></li>
				<?php } else { ?>
					<li class="context-menu menu-separator"><a href="#configure-modal" data-toggle="modal"><i class="fas fa-cog sx"></i> Configure</a></li>
					<li><a href="javascript:$('#audioinfo-modal .modal-body').load('audioinfo.php',function(e){$('#audioinfo-modal').modal('show');}); void 0"><i class="fas fa-music sx"></i> Audio info</a></li>
					<li><a href="javascript:$('#sysinfo-modal .modal-body').load('sysinfo.php',function(e){$('#sysinfo-modal').modal('show');}); void 0"><i class="fas fa-file-alt sx"></i> System info</a></li>
					<li class="context-menu menu-separator"><a href="#notarget" data-cmd="aboutmoode"><i class="fas fa-info sx"></i> About</a></li>
					<li><a href="javascript:location.reload(true); void 0"><i class="fas fa-redo sx"></i> Refresh</a></li>
					<li><a href="#restart-modal" data-toggle="modal"><i class="fas fa-power-off sx"></i> Restart</a></li>
				<?php } ?>
			</ul>
		</div>
		<div class="menu-top">
			<span aria-label="Clock Radio" id="clockradio-icon" class="clockradio-off">•</span>
		</div>
	</div>

	<!-- PLAYBAR -->
	<div id="menu-bottom" class="btn-group btn-list ui-footer ui-bar-f ui-footer-fixed slidedown" data-position="fixed" data-role="footer" role="banner">
		<div id="playbar">
			<div aria-label="Cover" id="playbar-cover"></div>
			<div id="playbar-title">
				<div id="playbar-currentsong"></div>
				<div id="playbar-currentalbum"></div>
				<div id="playbar-mtime">
					<div id="playbar-mcount"></div>
					<div id="playbar-mtotal"></div>
				</div>
			</div>
			<div aria-label="Switch to Playback" id="playbar-switch"><div></div></div>
			<div id="playbar-controls">
				<button aria-label="Previous" class="btn btn-cmd prev"><i class="fas fa-backward"></i></button>
				<button aria-label="Play / Pause" class="btn btn-cmd play"><i class="fas fa-play"></i></button>
				<button aria-label="Next" class="btn btn-cmd next"><i class="fas fa-forward"></i></button>
			</div>
			<div id="playbar-toggles">
				<button aria-label="Context Menu" class="btn playback-context-menu" data-toggle="context" data-target="#context-menu-playback" class="btn btn-cmd"><i class="far fa-ellipsis-h"></i></button>
				<button aria-label="Random" class="btn btn-cmd btn-toggle random" data-cmd="random"><i class="fal fa-random"></i></button>
				<button aria-label="Hide" class="btn btn-cmd ralbum hide"><i class="fal fa-dot-circle"></i></button>
				<button aria-label="Cover View" class="btn btn-cmd coverview"><i class="fal fa-tv"></i></button>
				<button aria-label="Volume" class="btn volume-popup" data-toggle="modal"><i class="fal fa-volume-up"></i></button>
				<button aria-label="Consume" class="btn btn-cmd btn-toggle consume hide" id="playbar-consume" data-cmd="consume"><i class="fal fa-arrow-down"></i></button>
				<button aria-label="Add To Favourites" class="btn btn-cmd addfav"><i class="fal fa-heart"></i></button>
			</div>
			<div id="playbar-timeline">
				<div class="timeline-bg"></div>
				<div class="timeline-progress"><div class="inner-progress"></div></div>
				<div class="timeline-thm">
					<input aria-label="Timeline" id="playbar-timetrack" type="range" min="0" max="1000" value="0" step="1">
				</div>
				<div id="playbar-time">
					<div id="playbar-countdown"></div>
					<span id="playbar-div">&nbsp;/&nbsp;</span>
					<div id="playbar-total"></div>
				</div>
			</div>
			<div id="playbar-radio"></div>
		</div>
	</div>
