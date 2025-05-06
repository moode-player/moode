<!--
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/
-->
<!--removeIf(GENINDEXDEV)-->

<?php
	$result = sqlRead('cfg_system', sqlConnect(), 'sessionid');
	session_id($result[0]['value']);
	$returnVal = session_start();
	//debugLog('header.php: session_start() = ' . (($returnVal) ? 'true' : 'false') . ', sessionid = ' . $result[0]['value']);
?>
<!--endRemoveIf(GENINDEXDEV)-->
<!DOCTYPE html>
<html lang="en">
<head>
	<title><?php echo $_SESSION['browsertitle']; ?></title>

	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">

	<!-- RESOURCES -->
	<!-- Common CSS -->
	<!-- build:css css/styles.min.css -->
	<link href="css/bootstrap.css" rel="stylesheet">
	<link href="css/bootstrap-select.css" rel="stylesheet">
	<link href="css/flat-ui.css" rel="stylesheet">
	<link href="css/jquery.pnotify.default.css" rel="stylesheet">
	<link href="css/fa-brands.css" rel="stylesheet">
	<!--link href="css/fa-duotone.css" rel="stylesheet"-->
	<link href="css/fa-fontawesome.css" rel="stylesheet">
	<link href="css/fa-light.css" rel="stylesheet">
	<link href="css/fa-regular.css" rel="stylesheet">
	<link href="css/fa-sharp-light.css" rel="stylesheet">
	<link href="css/fa-sharp-regular.css" rel="stylesheet">
	<link href="css/fa-sharp-solid.css" rel="stylesheet">
	<link href="css/fa-solid.css" rel="stylesheet">
	<<!--link href="css/fa-thin.css" rel="stylesheet"-->
	<link href="css/panels.css" rel="stylesheet">
	<link href="css/configs.css" rel="stylesheet">
	<link href="css/moode.css" rel="stylesheet">
	<link href="css/media.css" rel="stylesheet">
	<link href="css/osk.css" rel="stylesheet">
	<link href="css/analog-clock.css" rel="stylesheet">
	<!-- endbuild -->

	<!-- Common JS -->
	<!-- build:js js/lib.min.js defer -->
	<script src="js/jquery-1.8.2.js" /></script>
	<script src="js/jquery-ui/jquery-ui.js" ></script>
	<script src="js/jquery-ui/jquery.ui.core.js" defer></script>
	<script src="js/jquery-ui/jquery.ui.widget.js" defer></script>
	<script src="js/jquery-ui/jquery.ui.mouse.js" defer></script>
	<script src="js/jquery-ui/jquery.ui.position.js" defer></script>
	<script src="js/jquery-ui/jquery.ui.datepicker.js" defer></script>
	<script src="js/jquery-ui/jquery.ui.slider.js" defer></script>
	<script src="js/jquery-ui/jquery.ui.tooltip.js" defer></script>
	<script src="js/jquery-ui/jquery.ui.effect.js" defer></script>

	<script src="js/bootstrap.js" defer></script>
	<script src="js/bootstrap-select.js" defer></script>
	<script src="js/jquery.pnotify.js" defer></script>
	<script src="js/notify.js" defer></script>
	<script src="js/playerlib.js" defer></script>
	<script src="js/links.js" defer></script>
	<script src="js/osk.js" defer></script>
	<script src="js/analog-clock.js" defer></script>
	<!-- endbuild -->

	<!-- Playback / Library -->
	<!--removeIf(GENINDEXDEV)-->
	<?php if ($section == 'index') { ?>
	<!--endRemoveIf(GENINDEXDEV)-->
		<!-- build:css css/main.min.css -->
		<link href="css/jquery.countdown.css" rel="stylesheet">
		<!-- endbuild -->
		<!-- build:js js/main.min.js defer -->
		<script src="js/jquery.countdown.js" defer></script>
		<script src="js/jquery.scrollTo.js" defer></script>
		<script src="js/jquery.touchSwipe.js" defer></script>
		<script src="js/jquery.lazyload.js" defer></script>
		<script src="js/jquery.md5.js" defer></script>
		<script src="js/jquery.knob.js" defer></script>
		<script src="js/bootstrap-contextmenu.js" defer></script>
		<script src="js/scripts-library.js" defer></script>
		<script src="js/scripts-panels.js" defer></script>
		<!-- endbuild -->
	<!-- Configs -->
	<!--removeIf(GENINDEXDEV)-->
	<?php } else { ?>
	<!--endRemoveIf(GENINDEXDEV)-->
		<!--removeIf(NOCONFIGSECTION)-->
		<!-- build:js js/config.min.js defer -->
		<!-- CONFIGBLOCKSECTION_BEGIN -->
		<script src="js/custom_radio.js" defer></script>
		<script src="js/jquery.tagsinput.js" defer></script>
		<script src="js/jquery.placeholder.js" defer></script>
		<script src="js/i18n/_messages.en.js" defer></script>
		<script src="js/application.js" defer></script>
		<script src="js/scripts-configs.js" defer></script>
		<!-- CONFIGBLOCKSECTION_END -->
		<!-- endbuild -->
		<!--endRemoveIf(NOCONFIGSECTION)-->

	<!--removeIf(GENINDEXDEV)-->
	<?php }
		// INSTALL DISPLAY MESSAGES FUNCTION, IS ACTUALY CALLED AFTER onready by applicatio.js  |scripts-panels.js
		if (isset($_SESSION['notify']['title']) && $_SESSION['notify']['title'] != '') {
			uiNotify($_SESSION['notify']);
			$_SESSION['notify']['title'] = '';
			$_SESSION['notify']['msg'] = '';
			$_SESSION['notify']['duration'] = NOTIFY_DURATION_DEFAULT;
		}
	?>
	<!--endRemoveIf(GENINDEXDEV)-->

	<!-- MOBILE APP ICONS -->
	<!-- Apple -->
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black">
	<meta name="theme-color" content="rgb(32,32,32)">
	<link rel="apple-touch-icon" sizes="180x180" href="/v5-apple-touch-icon.png">
	<link rel="mask-icon" href="/v5-safari-pinned-tab.svg" color="#5bbad5">
	<!-- Android/Chrome -->
	<link rel="icon" type="image/png" sizes="32x32" href="/v5-favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/v5-favicon-16x16.png">
	<!--link rel="manifest" href="/site.webmanifest"-->
	<!-- Microsoft -->
	<meta name="msapplication-TileColor" content="#da532c">
</head>


<body onorientationchange="javascript:location.reload(true); void 0;">
	<!-- ALBUM COVER BACKDROP -->
	<div aria-label="Album Cover Backdrop" id="cover-backdrop"></div>
	<div id="context-backdrop"></div>
	<div id="splash"><div>moOde</div></div>

	<!-- INPUT SOURCE INDICATOR -->
	<div id="inpsrc-indicator" class="inpsrc">
		<div id="inpsrc-backdrop"></div>
		<div id="inpsrc-style"></div>
		<div id="inpsrc-cover"></div>
		<div id="inpsrc-msg" class="inpsrc-msg-default"></div>
		<div id="inpsrc-metadata"></div>
		<div id="inpsrc-metadata-refresh"></div>
	</div>

	<!-- HEADER -->
	<div id="panel-header" class="ui-header ui-bar-f ui-header-fixed slidedown" data-position="fixed" data-role="header" role="banner">
		<div aria-label="Switch to Playbar" id="playback-switch"><div></div></div>

		<div id="config-back">
			<a aria-label="Back" href="<?php echo $_SESSION['config_back_link'] ?>"><i class="fa-regular fa-sharp fa-angle-left"></i></a>
		</div>
		<div id="config-home">
			<a aria-label="Home" href="/index.php"><i class="fa-solid fa-sharp fa-home"></i></a>
		</div>

		<div id="config-tabs" class="viewswitch-cfgs hide">
			<a id="lib-config-btn" class="btn" href="lib-config.php"><span>Library</span><i class="fa-solid fa-sharp fa-database"></i></a>
			<a id="snd-config-btn" class="btn" href="snd-config.php"><span>Audio</span><i class="fa-solid fa-sharp fa-volume-up"></i></a>
			<a id="net-config-btn" class="btn" href="net-config.php"><span>Network</span><i class="fa-solid fa-sharp fa-sitemap"></i></a>
			<a id="sys-config-btn" class="btn" href="sys-config.php"><span>System</span><i class="fa-solid fa-sharp fa-gears"></i></a>
			<a id="ren-config-btn" class="btn" href="ren-config.php"><span>Renderers</span><i class="fa-solid fa-sharp fa-play-circle"></i></a>
			<a id="per-config-btn" class="btn" href="per-config.php"><span>Peripherals</span><i class="fa-solid fa-sharp fa-display"></i></a>
		</div>

		<div id="library-header"></div>
		<div id="multiroom-sender" class="context-menu"><a class="btn" href="#notarget" data-cmd="multiroom_rx_modal"><i class="fa-solid fa-sharp fa-speakers"></i></a></div>

		<?php
			if ($section == 'index' && $_SESSION['camilladsp'] != "off") {
				require_once __DIR__ . '/inc/cdsp.php';
				$cdsp = new CamillaDsp($_SESSION['camilladsp'], $_SESSION['cardnum'], $_SESSION['camilladsp_quickconv']);
				$cdspConfigs = $cdsp->getAvailableConfigs();
		?>
		<div class="dropdown" id="dropdown-cdsp-btn">
			<a aria-label="Menu" class="dropdown-toggle btn" id="menu-cdsp" role="button" data-toggle="dropdown" data-target="#" href="#notarget">
				<i class="fa-solid fa-sharp fa-square-sliders-vertical"></i>
			</a>
			<ul id="dropdown-cdsp-menu" class="dropdown-menu cdsp-menu-background" role="menu" aria-labelledby="menu-settings_x">
			<?php
				foreach ($cdspConfigs as $configFile=>$configName) {
					$menuSeparator = $configName == 'Quick convolution filter' ? ' menu-separator' : '';
					$checkMark = $_SESSION['camilladsp'] == $configFile ? '<span id="menu-check-cdsp"><i class="fa-solid fa-sharp fa-check"></i></span>' : '';
					echo '<li class="context-menu dropdown-cdsp-line' . $menuSeparator .
						'"><a href="#notarget" data-cmd="camilladsp_config" data-cdspconfig="' .
						$configFile . '" data-cdsplabel="' . $configName . '">' . ucfirst($configName) .
						$checkMark . '</a></li>';
				}
			?>
			</ul>
		</div>
		<?php } ?>

		<div aria-label="Update notification" id="updater-notification"><a class="btn" href="#notarget"><i class="fa-solid fa-sharp fa-info-circle"></i></a></div>
		<div aria-label="Busy" class="busy-spinner"><svg xmlns='http://www.w3.org/2000/svg' width='42' height='42' viewBox='0 0 42 42' stroke='#fff'><g fill='none' fill-rule='evenodd'><g transform='translate(3 3)' stroke-width='4'><circle stroke-opacity='.35' cx='18' cy='18' r='18'/><path d='M36 18c0-9.94-8.06-18-18-18'><animateTransform attributeName='transform' type='rotate' from='0 18 18' to='360 18 18' dur='1s' repeatCount='indefinite'/></path></g></g></svg></div>

		<!-- MAIN MENU -->
		<div class="dropdown">
			<a aria-label="Menu" class="dropdown-toggle btn target-blank-link" id="menu-settings" role="button" data-toggle="dropdown" data-target="#" href="#notarget"><div id="mblur">mm</div><div id="mbrand">m</div></a>
			<ul class="dropdown-menu" role="menu" aria-labelledby="menu-settings">
				<li id="menu-header-player-name" class="context-menu menu-separator"><a href="#notarget" data-cmd="player_info"><?php echo $_SESSION['browsertitle']; ?></a></li>
				<?php if ($section == 'index') { ?>
					<li><a href="#configure-modal" data-toggle="modal"><i class="fa-solid fa-sharp fa-gear-complex sx"></i>Configure</a></li>
					<li class="context-menu menu-separator"><a href="#notarget" data-cmd="preferences"><i class="fa-solid fa-sharp fa-pen sx"></i>Preferences</a></li>
					<li class="context-menu"><a href="#notarget" data-cmd="update_library"><i class="fa-solid fa-sharp fa-sync sx"></i>Update library</a></li>
					<li id="dashboard-menu-item"><a href="javascript:$('#dashboard-modal .modal-body').load('dashboard.php',function(e){$('#dashboard-modal').modal('show');}); void 0"><i class="fa-solid fa-sharp fa-grid sx"></i>Dashboard</a></li>
					<?php if ($_SESSION['camilladsp'] != "off") {?>
						<li><a href="cdsp-config.php"><i class="fa-solid fa-sharp fa-square-sliders-vertical sx"></i>CamillaDSP</a></li>
					<?php } ?>
					<li id="bluetooth-hide"><a href="blu-config.php"><i class="fa-light fa-brands fa-bluetooth sx"></i>Bluetooth</a></li>
					<li><a href="javascript:audioInfoPlayback()"><i class="fa-solid fa-sharp fa-music sx"></i>Audio info</a></li>
					<li id="playhistory-hide" class="context-menu"><a href="#notarget" data-cmd="viewplayhistory"><i class="fa-solid fa-sharp fa-book sx"></i>Play history</a></li>
					<li class="context-menu"><a href="#notarget" data-cmd="quickhelp"><i class="fa-solid fa-sharp fa-info sx"></i>Quick help</a></li>
					<li class="menu-separator"><a href="javascript:location.reload(true); void 0"><i class="fa-solid fa-sharp fa-redo sx"></i>Refresh</a></li>
					<li><a href="#power-modal" data-toggle="modal"><i class="fa-solid fa-sharp fa-power-off sx"></i>Power</a></li>
				<?php } else { ?>
					<li class="context-menu menu-separator"><a href="#configure-modal" data-toggle="modal"><i class="fa-solid fa-sharp fa-gear-complex sx"></i>Configure</a></li>
					<li><a href="javascript:audioInfoPlayback()"><i class="fa-solid fa-sharp fa-music sx"></i>Audio info</a></li>
					<li id="sysinfo-menu-item"><a href="javascript:$('#sysinfo-modal .modal-body').load('sysinfo.php',function(e){$('#sysinfo-modal').modal('show');}); void 0"><i class="fa-solid fa-sharp fa-file-alt sx"></i>System info</a></li>
					<li class="context-menu"><a href="#notarget" data-cmd="quickhelp"><i class="fa-solid fa-sharp fa-info sx"></i>Quick help</a></li>
					<li class="context-menu"><a href="https://github.com/moode-player/docs/blob/main/setup_guide.md#setup-guide-" class="target-blank-link" target="_blank"><i class="fa-solid fa-sharp fa-info sx"></i>Setup guide</a></li>
					<li class="context-menu menu-separator"><a href="#notarget" data-cmd="aboutmoode"><i class="fa-solid fa-sharp fa-info sx"></i>About</a></li>
					<li><a href="javascript:location.reload(true); void 0"><i class="fa-solid fa-sharp fa-redo sx"></i>Refresh</a></li>
					<li><a href="#power-modal" data-toggle="modal"><i class="fa-solid fa-sharp fa-power-off sx"></i>Power</a></li>
				<?php } ?>
			</ul>
		</div>
		<div class="panel-header">
			<span aria-label="Clock Radio" id="clockradio-icon" class="clockradio-off">•</span>
		</div>
	</div>

	<!-- PLAYBAR -->
	<div id="panel-footer" class="btn-group btn-list ui-footer ui-bar-f ui-footer-fixed slidedown" data-position="fixed" data-role="footer" role="banner">
		<div id="playbar">
			<div aria-label="Cover" id="playbar-cover"></div>
			<div aria-label="First use help" id="playbar-firstuse-help"><div></div></div>
			<div aria-label="Switch to Playback" id="playbar-switch"><div></div></div>
			<div id="playbar-controls">
				<button aria-label="Previous" class="btn btn-cmd prev"><i class="fa-solid fa-sharp fa-step-backward"></i></button>
				<button aria-label="Play / Pause" class="btn btn-cmd play"><i class="fa-solid fa-sharp fa-play"></i></button>
				<button aria-label="Next" class="btn btn-cmd next"><i class="fa-solid fa-sharp fa-step-forward"></i></button>
			</div>
			<div id="playbar-title">
				<div id="playbar-currentsong"></div>
				<div id="playbar-title-line-2">
					<span id="playbar-currentalbum"></span>
				</div>
				<div id="playbar-mtime">
					<div id="playbar-mcount"></div>
					<div id="playbar-mtotal"></div>
				</div>
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
			<div id="playbar-toggles">
				<button class="btn playback-context-menu" data-toggle="context" data-target="#context-menu-playback" class="btn btn-cmd" aria-label="Context Menu"><i class="fa-regular fa-sharp fa-ellipsis-h"></i></button>
				<button class="btn btn-cmd btn-toggle random" data-cmd="random" aria-label="Random"><i class="fa-regular fa-sharp fa-random"></i></button>
				<button class="btn btn-cmd btn-toggle hide" id="cv-playqueue-btn" aria-label="Queue"><i class="fa-regular fa-sharp fa-list"></i></button>
				<button class="btn btn-cmd coverview" aria-label="Cover View"><i class="fa-regular fa-sharp fa-tv"></i></button>
				<button class="btn volume-popup-btn" id="playbar-volume-popup-btn" data-toggle="modal" aria-label="Volume">
					<i class="fa-solid fa-sharp fa-volume-off"></i>
					<span class="mpd-volume-level"></span>
				</button>
				<button class="btn btn-cmd hide" id="random-album" aria-label="Random Album"><i class="fa-regular fa-sharp fa-dot-circle"></i></button>
				<button class="btn btn-cmd add-item-to-favorites hide" aria-label="Add To Favorites"><i class="fa-regular fa-sharp fa-heart"></i></button>
			</div>
		</div>
	</div>

	<!-- COVERVIEW QUEUE -->
	<div id="cv-playqueue">
		<ul class="cv-playqueue"></ul>
	</div>

	<!-- Only included when generate index.html for developmed purpose -->
	<!--=include templates/indextpl.html -->
	<!--=include footer.php -->

<!-- make wellformed html; correct unclosed body and html (normally done by footer ) -->
<!-- GEN_DEV_INDEX_TAG
	</body>
</html>
GEN_DEV_INDEX_TAG -->
