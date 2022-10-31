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
 */
-->
<!-- ABOUT -->
<div id="about-modal" class="modal modal-sm hide" tabindex="-1" role="dialog" aria-labelledby="about-modal-label" aria-hidden="true">
	<div class="modal-body">
		<button aria-label="Close" type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<p style="text-align:center;font-size:40px;font-weight:500;letter-spacing:-2px;margin-top:2px">m<span style="color:#d35400;line-height:12px">oO</span>de<span style="font-size:12px;position:relative;top:-15px;left:-3px;">™</span></p>
			<p>Moode Audio Player is a derivative of the wonderful WebUI audio player client for MPD originally designed and coded by Andrea Coiutti and Simone De Gregori, and subsequently enhanced by early efforts from the RaspyFi/Volumio projects.</p>
			<h4>Release Information</h4>
			<ul>
				<li>Release: 8.2.3 2022-MM-DD</li> <!-- NOTE: getMoodeRel() parses this  -->
				<li>Maintainer: Tim Curtis &copy; 2014</li>
				<li>Documentation: <a class="moode-about-link" href="./relnotes.txt" target="_blank">View release notes,</a>&nbsp<a class="moode-about-link" href="./setup.txt" target="_blank">View setup guide</a></li>
				<li>Contributors: <a class="moode-about-link" href="./CONTRIBS.html" target="_blank">View contributors</a></li>
				<li>License: <a class="moode-about-link" href="./COPYING.html" target="_blank">View GPLv3</a></li>
			</ul>
		<p>
			<h4>Platform Information</h4>
			<ul>
				<li>RaspiOS: <span id="sys-raspbian-ver"></span></li>
				<li>Linux kernel: <span id="sys-kernel-ver"></span></li>
				<li>Platform: <span id="sys-hardware-rev"></span></li>
				<li>Architecture: <span id="sys-processor-arch"></span></li>
				<li>MPD version: <span id="sys-mpd-ver"></span></li>
			</ul>
		</p>
	</div>
	<div class="modal-footer">
		<button aria-label="Close" class="btn singleton" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- CONFIGURE -->
<div id="configure-modal" class="modal modal-sm hide" tabindex="-1" role="dialog" aria-labelledby="configure-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button aria-label="Close" type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="configure-modal-label">Configuration settings</h3>
	</div>
	<div class="modal-body">
		<div id="configure">
			<ul>
				<li><a href="lib-config.php" class="btn btn-large"><i class="fas fa-database"></i><br>Library</a></li>
				<li><a href="snd-config.php" class="btn btn-large"><i class="fas fa-volume-up"></i><br>Audio</a></li>
				<li><a href="net-config.php" class="btn btn-large"><i class="fas fa-sitemap"></i><br>Network</a></li>
				<li><a href="sys-config.php" class="btn btn-large"><i class="fas fa-cogs"></i><br>System</a></li>
				<li><a href="ren-config.php" class="btn btn-large"><i class="fas fa-play-circle"></i><br>Renderers</a></li>
				<li><a href="mpd-config.php" class="btn btn-large"><i class="fas fa-play"></i><br>MPD</a></li>
				<li><a href="cdsp-config.php" class="btn btn-large"><i class="fas fa-sliders-v-square"></i><br>CamillaDSP</a></li>
				<?php if ($_SESSION['feat_bitmask'] & FEAT_MULTIROOM) { ?>
					<li><a href="trx-config.php" class="btn btn-large"><i class="fas fas fa-rss"></i><br>Multiroom</a></li>
				<?php } ?>
				<?php if ($section == 'index') { ?>
					<li class="context-menu"><a href="#notarget" class="btn btn-large" data-cmd="setforclockradio-m"><i class="fas fa-alarm-clock"></i><br>Clock radio</a></li>
				<?php } ?>
				<?php if ($_SESSION['feat_bitmask'] & FEAT_INPSOURCE) { ?>
					<li><a href="inp-config.php" class="btn btn-large"><i class="far fa-scrubber"></i><br>Input source</a></li>
				<?php } ?>
			</ul>
		</div>
	</div>

	<div class="modal-footer">
		<button aria-label="Close" class="btn singleton" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- PLAYERS -->
<div id="players-modal" class="modal modal-sm hide" tabindex="-1" role="dialog" aria-labelledby="players-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button aria-label="Close" type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="players-modal-label">Players</h3>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button aria-label="Close" class="btn singleton" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- AUDIO INFO -->
<div id="audioinfo-modal" class="modal modal-sm hide" tabindex="-1" role="dialog" aria-labelledby="audioinfo-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button aria-label="Close" type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="audioinfo-modal-label">Audio information</h3>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button aria-label="Close" class="btn singleton" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- SYSTEM INFO -->
<div id="sysinfo-modal" class="modal modal-sm hide" tabindex="-1" role="dialog" aria-labelledby="sysinfo-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button aria-label="Close" type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="sysinfo-modal-label">System information</h3>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button aria-label="Close" class="btn singleton" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- QUICK HELP -->
<div id="quickhelp-modal" class="modal modal-sm hide" tabindex="-1" role="dialog" aria-labelledby="help-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button aria-label="Close" type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="help-modal-label">Quick Help</h3>
	</div>
	<div class="modal-body">
		<div id="quickhelp"></div>
	</div>
	<div class="modal-footer">
		<button aria-label="Close" class="btn singleton" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- POWER -->
<div id="power-modal" class="modal modal-sm2 hide" tabindex="-1" role="dialog" aria-labelledby="power-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button aria-label="Close" type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="power-modal-label">Power Options</h3>
	</div>
	<div class="modal-body">
		<button aria-label="Shutdown" id="system-shutdown" data-dismiss="modal" class="btn btn-primary btn-large btn-block">Shutdown</button>
		<button aria-label="Restart" id="system-restart" data-dismiss="modal" class="btn btn-primary btn-large btn-block" style="margin-bottom:15px;">Restart</button>
	</div>
	<div class="modal-footer">
		<button aria-label="Cancel" class="btn singleton" data-dismiss="modal" aria-hidden="true">Cancel</button>
	</div>
</div>

<!-- RECONNECT/RESTART/SHUTDOWN -->
<div id="reconnect" class="hide">
	<div class="reconnect-bg"></div>
	<a href="javascript:location.reload(true); void 0" class="btn reconnect-btn">Reconnect</a>
</div>

<div id="restart" class="hide">
	<div class="reconnect-bg"></div>
	<a href="javascript:location.reload(true); void 0" class="btn reconnect-btn">Reconnect</a>
	<span class="reconnect-msg">System restarted</span>
</div>

<div id="shutdown" class="hide">
	<div class="reconnect-bg"></div>
	<a href="javascript:location.reload(true); void 0" class="btn reconnect-btn">Reconnect</a>
	<span class="reconnect-msg">System shut down</span>
</div>

<?php
    //workerLog('-- footer.php');
    $return_val = session_write_close();
	//workerLog('session_write_close=' . (($return_val) ? 'TRUE' : 'FALSE'));
	echo "</body></html>";
?>
