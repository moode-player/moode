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
 * 2017-12-07 TC moOde 4.0
 *
 */
-->
<!-- ABOUT -->	
<div id="about-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="about-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="about-modal-label">About Moode</h3>
	</div>
	<div class="modal-body">
		<p>
			<img src="images/player-logotype-v4-clear.png" style="height: 48px;">
			<p>Moode Audio Player is a derivative of the wonderful WebUI audio player client for MPD originally designed and coded by Andrea Coiutti and Simone De Gregori, and subsequently enhanced by early efforts from the RaspyFi/Volumio projects.</p>
			<h4>Release Information</h4>			
			<ul>
				<li>Release: 4 BETA12 2017-12-07 <a class="moode-about-link1" href="./relnotes.txt" target="_blank">release notes</a></li>
				<li>Update: (<span id="sys-upd-pkgdate"></span>)</li>
				<li>Setup guide: <a class="moode-about-link1" href="./setup.txt" target="_blank">setup guide</a></li>
				<li>Coding:	Tim Curtis &copy; 2014 <a class="moode-about-link1" href="http://moodeaudio.org" target="_blank">moodeaudio.org</a>, <a class="moode-about-link1" href="https://twitter.com/MoodeAudio" target="_blank">twitter</a></li>
				<li>Contributors: <a class="moode-about-link1" href="./COMTRIBS.html" target="_blank">list of contributors</a></li>
				<li>License: <a class="moode-about-link1" href="./COPYING.html" target="_blank">GPLv3</a></li>
			</ul>
		</p>
		<p>
			<h4>Platform Information</h4>			
			<ul>
				<li>Linux kernel: <span id="sys-kernel-ver"></span></li>
				<li>Architecture: <span id="sys-processor-arch"></span></li>
				<li>Hdwr revision: <span id="sys-hardware-rev"></span></li>
				<li>MPD version: <span id="sys-mpd-ver"></span></li>
			</ul>
		</p>
	</div>
	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- CLOCK RADIO -->	
<div id="clockradio-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="clockradio-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="clockradio-modal-label">Clock radio settings</h3>
	</div>
	<div class="modal-body" id="container-clockradio">
		<form class="form-horizontal" data-validate="parsley" action="" method="">
	    	<fieldset>
				<div class="control-group">
	                <label class="control-label" for="clockradio-enabled">Enabled</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select" style="width: 120px;"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="clockradio-enabled" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li><a href="#notarget" data-cmd="clockradio-enabled-yn"><span class="text">No</span></a></li>
									<li><a href="#notarget" data-cmd="clockradio-enabled-yn"><span class="text">Clock Radio</span></a></li>
									<li><a href="#notarget" data-cmd="clockradio-enabled-yn"><span class="text">Sleep Timer</span></a></li>
								</ul>
							</div>
						</div>
	                </div>
	                
	                <label class="control-label" for="clockradio-playname">Play</label>
	                <div class="controls">
	                    <input id="clockradio-playname" class="input-xlarge" type="text" name="clockradio_playname" value="" readonly>
						<a class="info-toggle" data-cmd="info-playname" href="#notarget"><i class="icon-info-sign"></i></a>
						<span id="info-playname" class="help-block hide">
	                    	Use 'Set for clock radio' on the Playlist item menu to populate this read-only field.
	                    </span>
	                </div>
	                
	                <label class="control-label" for="clockradio-starttime-hh">Start time</label>
	                <div class="controls">
	                    <input id="clockradio-starttime-hh" class="input-mini" style="height: 20px;" type="number" maxlength="2" min="1" max="12" name="clockradio_starttime-hh" value="">
	                    <span>:</span>
	                    <input id="clockradio-starttime-mm" class="input-mini" style="height: 20px;" type="number" maxlength="2" min="0" max="59" name="clockradio_starttime-mm" value="">
						
						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="clockradio-starttime-ampm" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li><a href="#notarget" data-cmd="clockradio-starttime-ampm"><span class="text">AM</span></a></li>
									<li><a href="#notarget" data-cmd="clockradio-starttime-ampm"><span class="text">PM</span></a></li>
								</ul>
							</div>
						</div>
	                </div>
	                
	                <label class="control-label" for="clockradio-stoptime-hh">Stop time</label>
	                <div class="controls">
	                    <input id="clockradio-stoptime-hh" class="input-mini" style="height: 20px;" type="number" maxlength="2" min="1" max="12" name="clockradio_stoptime-hh" value="">
	                    <span>:</span>
	                    <input id="clockradio-stoptime-mm" class="input-mini" style="height: 20px;" type="number" maxlength="2" min="0" max="59" name="clockradio_stoptime-mm" value="">
						
						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="clockradio-stoptime-ampm" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li><a href="#notarget" data-cmd="clockradio-stoptime-ampm"><span class="text">AM</span></a></li>
									<li><a href="#notarget" data-cmd="clockradio-stoptime-ampm"><span class="text">PM</span></a></li>
								</ul>
							</div>
						</div>
	                </div>
	                
	                <label class="control-label" for="clockradio-volume">Volume</label>
	                <div class="controls">
	                    <input id="clockradio-volume" class="input-mini" style="height: 20px;" type="number" min="1" max="" name="clockradio_volume" value="">
						<span id="clockradio-volume-aftertext" class="control-aftertext"></span> <!-- text set in player-scripts.js -->
	                </div>
	                
	                <label class="control-label" for="clockradio-shutdown">Shutdown</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="clockradio-shutdown" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li><a href="#notarget" data-cmd="clockradio-shutdown-yn"><span class="text">Yes</span></a></li>
									<li><a href="#notarget" data-cmd="clockradio-shutdown-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
						</div>
						<span class="control-aftertext">after stop</span>
	                </div>
	            </div>
	    	</fieldset>
		</form>
	</div>
	<div class="modal-footer">
		<button class="btn btn-clockradio-update btn-primary" data-dismiss="modal">Update</button>
		<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
	</div>
</div>

<!-- CONFIG MENU -->	
<div id="configure-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="configure-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="configure-modal-label">Configuration settings</h3>
	</div>
	<div class="modal-body">
		<div style="margin-top: 20px; margin-left: 20px;">
			<div class="moode-config-settings-header"><a class="moode-config-settings-link" href="src-config.php"><i class="icon-folder-open sx"></i>Sources</a></div>
			<span class="help-block">
				Index sources containing music
            </span>
			<div class="moode-config-settings-header"><a class="moode-config-settings-link" href="snd-config.php"><i class="icon-music sx"></i>&nbsp;Audio</a></div>
			<span class="help-block">
				MPD, devices, DSP and renderers
            </span>
			<div class="moode-config-settings-header"><a class="moode-config-settings-link" href="net-config.php"><i class="icon-sitemap sx"></i>Network</a></div>
			<span class="help-block">
				LAN, WiFi and AP mode 
            </span>
			<div class="moode-config-settings-header"><a class="moode-config-settings-link" href="sys-config.php"><i class="icon-laptop sx"></i>System</a></div>
			<span class="help-block">
				OS settings and maintenence
            </span>
		</div>
	</div>

	<div class="modal-footer">
		<div style="float: left;">
			<a href="mpd-config.php" class="moode-config-settings-link2">MPD</a>
			<a href="eqp-config.php" class="moode-config-settings-link2">EQP</a>
			<a href="eqg-config.php" class="moode-config-settings-link2">EQG</a>
			<a href="blu-config.php" class="moode-config-settings-link2">BLU</a>
			<?php if ($_SESSION['feat_bitmask'] & $FEAT_AIRPLAY) { ?>
				<a href="apl-config.php" class="moode-config-settings-link2">AIR</a>
			<?php } ?>
			<?php if ($_SESSION['feat_bitmask'] & $FEAT_SQUEEZELITE) { ?>				
				<a href="sqe-config.php" class="moode-config-settings-link2">SQE</a>
			<?php } ?>
			<?php if ($_SESSION['feat_bitmask'] & $FEAT_UPMPDCLI) { ?>
				<a href="upp-config.php" class="moode-config-settings-link2">UPP</a>
			<?php } ?>
		</div>
		<br>
		<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- CUSTOMIZE -->	
<div id="customize-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="customize-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="customize-modal-label">Customization settings</h3>
	</div>
	<div class="modal-body" id="container-customize">
		<form class="form-horizontal" data-validate="parsley" action="" method="">
			<h4>General settings</h4>
	    	<fieldset>
				<div class="control-group">
	                <label class="control-label" for="volume-warning-limit">Volume warning limit</label>
	                <div class="controls">
	                    <input id="volume-warning-limit" class="input-mini" style="height: 20px;" type="number" maxlength="3" min="1" max="100" name="volume_warning_limit" value="">
						<span id="volume-warning-limit-aftertext" class="control-aftertext2"></span> <!-- text set in player-scripts.js -->
						<a class="info-toggle" data-cmd="info-volume-warning-limit" href="#notarget"><i class="icon-info-sign"></i></a>
						<span id="info-volume-warning-limit" class="help-block hide">
	                    	When the Knob volume exceeds the warning limit, a popup<br>
							appears and volume level remains unchanged. Setting the<br>
							limit to 100 disables the warning popup.<br>
							NOTE: the limit only applies to Knob changes and has no<br>
							effect on volume changes made by other applications for<br>
							example Airplay receiver, UPnP renderer or Squeezelite<br>
							renderer. These applications manage volume separately<br>
							from Moode Knob and MPD.
	                    </span>
	                </div>
	                
   	                <label class="control-label" for="search-autofocus-enabled">Search auto-focus</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="search-autofocus-enabled" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li><a href="#notarget" data-cmd="search-autofocus-enabled-yn"><span class="text">Yes</span></a></li>
									<li><a href="#notarget" data-cmd="search-autofocus-enabled-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-search-audofocus" href="#notarget"><i class="icon-info-sign"></i></a>
						<span id="info-search-audofocus" class="help-block hide">
	                    	Controls whether search fields automatically receive focus when the toolbar shows.<br>
	                    	- On Smartphone/Tablet, autofocus will cause the popup keyboard to appear.
	                    </span>
	                </div>

   	                <label class="control-label" for="theme-color">Theme color</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select" style="width: 110px;"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="theme-color" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open"> <!-- list generated in playerlib.js -->
								<ul id="theme-color-list" class="dropdown-menu custom-select inner" role="menu"></ul>
							</div>
						</div>
	                </div>
	                
   	                <label class="control-label" for="play-history-enabled">Playback history</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="play-history-enabled" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li><a href="#notarget" data-cmd="play-history-enabled-yn"><span class="text">Yes</span></a></li>
									<li><a href="#notarget" data-cmd="play-history-enabled-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-play-history" href="#notarget"><i class="icon-info-sign"></i></a>
						<span id="info-play-history" class="help-block hide">
	                    	Select Yes to log each song played to the playback history log.<br>
	                    	- Songs in the log can be clicked to launch a Google search.<br>
	                    	- The log can be cleared from the System configuration page.
	                    </span>
	                </div>					

   	                <label class="control-label" for="extratag-display">Display extra metadata</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="extratag-display" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li><a href="#notarget" data-cmd="extratag-display-yn"><span class="text">Yes</span></a></li>
									<li><a href="#notarget" data-cmd="extratag-display-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-extratag-display" href="#notarget"><i class="icon-info-sign"></i></a>
						<span id="info-extratag-display" class="help-block hide">
	                    	Select Yes to display additional metadata<br>
	                    	- Menu, refresh after changing this setting
	                    </span>
	                </div>

   	                <label class="control-label" for="library-artist">Library artist column</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select" style="width: 150px;"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="library-artist" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li><a href="#notarget" data-cmd="library-artist-sel"><span class="text">Use Artist</span></a></li>
									<li><a href="#notarget" data-cmd="library-artist-sel"><span class="text">Use AlbumArtist</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-library-artist" href="#notarget"><i class="icon-info-sign"></i></a>
						<span id="info-library-artist" class="help-block hide">
	                    	Choose whether to use Artist or AlbumArtist tag for the Library artists column<br>
	                    </span>
	                </div>
										
	            </div>
	    	</fieldset>

			<h4>Audio device description</h4>
	    	<fieldset>
				<div class="control-group">
   	                <label class="control-label" for="audio-device-name">Device</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select" style="width: 265px;"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="audio-device-name" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open"> <!-- list generated in playerlib.js -->
								<ul id="audio-device-list" class="dropdown-menu custom-select inner" role="menu"></ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-device-name" href="#notarget"><i class="icon-info-sign"></i></a>
						<span id="info-device-name" class="help-block hide">
	                    	Select a device to have its description show on Audio Info.<br>
							I2S devices are automatically populated from System config.<br>
							If device is not listed select "USB audio device".
	                    </span>
	                </div>
					
	                <label class="control-label" for="audio-device-dac">Chip</label>
	                <div class="controls">
	                    <input id="audio-device-dac" class="input-xlarge" type="text" name="audio_device_dac" value="" readonly>
	                </div>
	                <label class="control-label" for="audio-device-arch">Architecture</label>
	                <div class="controls">
	                    <input id="audio-device-arch" class="input-xlarge" type="text" name="audio_device_arch" value="" readonly>
	                </div>
	                <label class="control-label" for="audio-device-iface">Interface</label>
	                <div class="controls">
	                    <input id="audio-device-iface" class="input-xlarge" type="text" name="audio_device_iface" value="" readonly>
	                </div>
	            </div>
	    	</fieldset>
		</form>
	</div>

	<div class="modal-footer">
		<button class="btn cs-lastPage" style="float: right;"><i class="icon-double-angle-down"></i></button>
		<button class="btn cs-firstPage" style="float: right;"><i class="icon-double-angle-up"></i></button>

		<button class="btn btn-customize-update btn-primary" data-dismiss="modal">Update</button>
		<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
	</div>
</div>

<!-- PLAYERS -->	
<div id="players-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="players-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="players-modal-label">Players</h3>
	</div>
	<div class="modal-body" style="max-height: 450px;">
	</div>
	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- AUDIO INFO -->	
<div id="audioinfo-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="audioinfo-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="audioinfo-modal-label">Audio information</h3>
	</div>
	<div class="modal-body" style="max-height: 450px;">
	</div>
	<!-- There is a custom footer for this modal
	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
	-->
</div>

<!-- SYSTEM INFO -->	
<div id="sysinfo-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="sysinfo-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="sysinfo-modal-label">System information</h3>
	</div>
	<div class="modal-body" style="max-height: 400px;">
	</div>
	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- RESTART -->	
<div id="restart-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="restart-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="restart-modal-label"><i class="icon-power-off sx"></i></h3>
	</div>
	<div class="modal-body">
		<button id="syscmd-poweroff" data-dismiss="modal" class="btn btn-primary btn-large btn-block"></i>Shutdown</button>
		<button id="syscmd-reboot" data-dismiss="modal" class="btn btn-primary btn-large btn-block"></i>Reboot</button>
	</div>
	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
	</div>
</div>

<!-- RECONNECT/REBOOT/POWEROFF -->
<div id="reconnect" class="hide">
	<div id="rebootbg"></div>
	<div id="smartreboot">
		<a href="javascript:location.reload(true); void 0" class="btn btn-primary btn-large">reconnect</a>
	</div>
</div>

<div id="reboot" class="hide">
	<div id="rebootbg"></div>
	<div id="smartreboot">
		<a href="javascript:location.reload(true); void 0" class="btn btn-primary btn-large">reconnect</a>
		System rebooting
		<div id="bootready"></div>			
	</div>
</div>

<div id="poweroff" class="hide">
	<div id="poweroffbg"></div>
	<div id="smartpoweroff">
		<a href="javascript:location.reload(true); void 0" class="btn btn-primary btn-large">reconnect</a>
		System has been powered off
	</div>
</div>

<!-- STANDARD JS -->
<script src="js/jquery-1.8.2.min.js"></script>
<script src="js/jquery-ui-1.10.0.custom.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/bootstrap-select.min.js"></script>
<script src="js/jquery.countdown.js"></script>
<script src="js/jquery.countdown-it.js"></script>
<script src="js/jquery.scrollTo.min.js"></script>
<!-- MOODE JS -->
<script src="js/notify.js"></script>
<script src="js/playerlib.js"></script>
<script src="js/links.js"></script>

<!-- DIFFERENT SCRIPTS FOR PANELS VS CONFIGS -->
<?php if ($section == 'index') { ?>
	<script src="jsw/jquery.knob.js"></script>
	<script src="js/bootstrap-contextmenu.js"></script>
	<script src="js/jquery.pnotify.min.js"></script>
	<!-- MOODE JS -->
	<script src="js/scripts-panels.js"></script>
<?php } else { ?>
	<script src="js/custom_checkbox_and_radio.js"></script>
	<script src="js/custom_radio.js"></script>
	<script src="js/jquery.tagsinput.js"></script>
	<script src="js/jquery.placeholder.js"></script>
	<script src="js/parsley.min.js"></script>
	<script src="js/i18n/_messages.en.js" type="text/javascript"></script>
	<script src="js/application.js"></script>
	<script src="js/jquery.pnotify.min.js"></script>
	<script src="js/bootstrap-fileupload.js"></script>
	<!-- MOODE JS -->
	<script src="js/scripts-configs.js"></script>
<?php } ?>

<!-- DISPLAY MESSAGES -->
<?php
if (isset($_SESSION['notify']) && $_SESSION['notify'] != '') {
	ui_notify($_SESSION['notify']);
	session_start();
	$_SESSION['notify'] = '';
	session_write_close();
}
?>

</body>
</html>
