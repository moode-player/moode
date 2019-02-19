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
 * - bump release and date
 * - add error-bgimage div to customize
 * - add raspbian version to About
 * - clean up some help text
 * - remove data-validate="parsley"
 * 2018-07-11 TC moOde 4.2
 * - bump release and date
 * - font-awesome 5
 * - remove blu from config since its on main menu
 * - replace some inline styles with classes
 * - deprecate search auto-focus
 * - rm unused libs parsley.min.js, bootstrap-fileupload.js, jquery.countdown-it.js
 * - fix reconnect/reboot/poweroff overlays
 * - add screen saver timeout to Customize
 * - rm Use from the Artist/AlbumArtist setting
 * - fix various bgimage issues
 * 2018-07-18 TC moOde 4.2 update
 * - add Customize setting for Music tab default
 * 2018-09-27 TC moOde 4.3
 * - bump release and date
 * - uniform button size for config and players
 * - TouchSwipe js for Library panel
 * - library utf8 character filter
 * - favorites feature
 * - album cover view
 * - spotify
 * 2018-10-19 TC moOde 4.3 update
 * - album cover backdrop
 * 2018-12-09 TC moOde 4.4
 * - add days to Clock radio
 * - add compilation rollup and compilation excludes to Customize
 * - auto and manual hires thumbnail settings
 * - bump release and date
 *
 */
-->
<!-- ABOUT -->	
<div id="about-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="about-modal-label" aria-hidden="true">
	<div class="modal-body">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<p style="text-align:center;font-size:40px;font-weight:600;letter-spacing:-2px;margin-top:2px">m<span style="color:#d35400;line-height:12px">oO</span>de<span style="font-size:12px;position:relative;top:-15px;left:-3px;">™</span></p>
		<p style="text-align:center;font-size:14px;font-weight:600;letter-spacing:-1px;line-height: 0px;margin-top: -12px; margin-bottom: 22px;">audio player</p>
			<p>Moode Audio Player is a derivative of the wonderful WebUI audio player client for MPD originally designed and coded by Andrea Coiutti and Simone De Gregori, and subsequently enhanced by early efforts from the RaspyFi/Volumio projects.</p>
			<h4>Release Information</h4>			
			<ul>
				<li>Release: 4.4 2018-12-09 <a class="moode-about-link1" href="./relnotes.txt" target="_blank">View relnotes</a></li>
				<li>Update: (<span id="sys-upd-pkgdate"></span>)</li>
				<li>Setup guide: <a class="moode-about-link1" href="./setup.txt" target="_blank">View guide</a></li>
				<li>Coding:	Tim Curtis &copy; 2014 <a class="moode-about-link1" href="http://moodeaudio.org" target="_blank">Moode Audio</a>, <a class="moode-about-link1" href="https://twitter.com/MoodeAudio" target="_blank">Twitter</a></li>
				<li>Contributors: <a class="moode-about-link1" href="./CONTRIBS.html" target="_blank">View contributors</a></li>
				<li>License: <a class="moode-about-link1" href="./COPYING.html" target="_blank">View GPLv3</a></li>
			</ul>
		</p>
		<p>
			<h4>Platform Information</h4>			
			<ul>
				<li>Raspbian ver: <span id="sys-raspbian-ver"></span></li>
				<li>Linux kernel: <span id="sys-kernel-ver"></span>, <span id="sys-processor-arch"></span></li>
				<li>Hdwr revision: <span id="sys-hardware-rev"></span></li>
				<li>MPD version: <span id="sys-mpd-ver"></span></li>
			</ul>
		</p>
	</div>
	<div class="modal-footer">
		<button class="btn singleton" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- CONFIGURE -->	
<div id="configure-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="configure-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="configure-modal-label">Configuration settings</h3>
	</div>
	<div class="modal-body">
		<div id="players">
			<ul>
				<li><a href="src-config.php" class="btn btn-large"><i class="fas fa-database"></i><br>Sources</a></li>
				<li><a href="snd-config.php" class="btn btn-large"><i class="fas fa-volume-up"></i><br>Audio</a></li>
				<li><a href="net-config.php" class="btn btn-large"><i class="fas fa-sitemap"></i><br>Network</a></li>
				<li><a href="sys-config.php" class="btn btn-large"><i class="fas fa-desktop-alt"></i><br>System</a></li>
			</ul>
		</div>
	</div>

	<div class="modal-footer">
		<div class="moode-config-settings-div context-menu">
			<a href="mpd-config.php" class="moode-config-settings-link2">MPD</a>
			<a href="eqp-config.php" class="moode-config-settings-link2">EQP</a>
			<a href="eqg-config.php" class="moode-config-settings-link2">EQG</a>
			<?php if ($_SESSION['feat_bitmask'] & $FEAT_AIRPLAY) { ?>
				<a href="apl-config.php" class="moode-config-settings-link2">AIR</a>
			<?php } ?>
			<?php if ($_SESSION['feat_bitmask'] & $FEAT_SPOTIFY) { ?>
				<a href="spo-config.php" class="moode-config-settings-link2">SPO</a>
			<?php } ?>
			<?php if ($_SESSION['feat_bitmask'] & $FEAT_SQUEEZELITE) { ?>				
				<a href="sqe-config.php" class="moode-config-settings-link2">SQE</a>
			<?php } ?>
			<?php if ($_SESSION['feat_bitmask'] & $FEAT_UPMPDCLI) { ?>
				<a href="upp-config.php" class="moode-config-settings-link2">UPP</a>
			<?php } ?>
			<a href="#notarget" class="moode-config-settings-link2" data-cmd="setforclockradio-m">CLK</a>
			<?php if ($_SESSION['feat_bitmask'] & $FEAT_INPUTSEL) { ?>
				<a href="sel-config.php" class="moode-config-settings-link2">SEL</a>
			<?php } ?>
		</div>
		<br>
		<button class="btn singleton" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- CUSTOMIZE -->	
<div id="customize-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="customize-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="customize-modal-label">Customization settings</h3>
	</div>
	<div class="modal-body" id="container-customize">
		<form class="form-horizontal" action="" method="">
			<h5>General settings</h5>
	    	<fieldset>
				<div class="control-group">
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
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="play-history-enabled-yn"><span class="text">Yes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="play-history-enabled-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-play-history" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-play-history" class="help-block hide">
	                    	Log each song played to the playback history log. Songs in the log can be clicked to launch a Google search. The log can be cleared from System config.<br>
	                    </span>
	                </div>					

   	                <label class="control-label" for="extratag-display">Show extra metadata</label>
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
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="extratag-display-yn"><span class="text">Yes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="extratag-display-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-extratag-display" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-extratag-display" class="help-block hide">
	                    	Display additional metadata under the cover art on the Playback panel.<br>
	                    </span>
	                </div>

   	                <label class="control-label" for="musictab-default">Music tab default</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="musictab-default" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="musictab-default-sel"><span class="text">Browse</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="musictab-default-sel"><span class="text">Library</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="musictab-default-sel"><span class="text">Albums</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-musictab-default" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-musictab-default" class="help-block hide">
	                    	Display Browse, Library or Album panel as the default when clicking the Music tab.<br>
	                    </span>
	                </div>

   	                <label class="control-label" for="scnsaver-timeout">CoverView display</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="scnsaver-timeout" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul id="scnsaver-timeout-list" class="dropdown-menu custom-select inner" role="menu"></ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-scnsaver-timeout" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-scnsaver-timeout" class="help-block hide">
	                    	Automatically display a fullscreen view of cover art and song data after the specified number of minutes.<br>
	                    </span>
	                </div>

				</div>
	    	</fieldset>

			<h5>Music library</h5>
	    	<fieldset>
				<div class="control-group">
  	                <label class="control-label" for="library-artist">Artist list ordering</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="library-artist" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-artist-sel"><span class="text">Artist</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-artist-sel"><span class="text">AlbumArtist</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-library-artist" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-library-artist" class="help-block hide">
	                    	Use Artist or AlbumArtist tag for populating the Library Artists list.<br>
	                    </span>
	                </div>

   	                <label class="control-label" for="library-comp-rollup">Compilation rollup</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="library-comp-rollup" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-comp-rollup-yn"><span class="text">Yes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-comp-rollup-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-library-comp-rollup" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-library-comp-rollup" class="help-block hide">
							Automatically rollup albums with same album name but different artists into a single compilation album.<br>
						</span>
	                </div>

	                <label class="control-label" for="library-comp-excludes">Compilation excludes</label>
	                <div class="controls">
	                    <input id="library-comp-excludes" class="input-xlarge input-height-x" type="text">
						<a class="info-toggle" data-cmd="info-library-comp-excludes" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-library-comp-excludes" class="help-block hide">
							Comma separated list of album names that are excluded from Compilation rollup.<br>
						</span>
	                </div>

   	                <label class="control-label" for="library-utf8rep">UTF8 character filter</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="library-utf8rep" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-utf8rep-yn"><span class="text">Yes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-utf8rep-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-library-utf8rep" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-library-utf8rep" class="help-block hide">
							Many Chinese songs and song directories have characters that are not UTF8 encoded causing the Library loader to fail. Replacing the non-UTF8 characters with a UTF8 compliant character solves this problem.<br>
							NOTE: setting this to Yes may impact the performance of the Library loader.<br>
						</span>
	                </div>

   	                <label class="control-label" for="library-hiresthm">Hi-res thumbnails</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="library-hiresthm" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open"> <!-- list generated in playerlib.js -->
								<ul id="library-hiresthm-list" class="dropdown-menu custom-select inner" role="menu"></ul> <!-- r44d -->
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-library-hiresthm" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-library-hiresthm" class="help-block hide">
							Tell the thumbnail generator to output high-resolution images suitable for large displays or high DPI (Retina) screens. The Auto setting will use the device's pixel ratio to determine an optimum image size and quality while maintaining the smallest file size. Manual settings will result in images of the specified size using a quality factor of 75.<br>
							NOTE: larger images may result in slower loading of thumbnail images into the Album Cover panel.<br>
						</span>
	                </div>

   	                <label class="control-label" for="library-covsearchpri">Cover search priority</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="library-covsearchpri" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-covsearchpri-sel"><span class="text">Embedded cover</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="library-covsearchpri-sel"><span class="text">Cover image file</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-library-covsearchpri" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-library-covsearchpri" class="help-block hide">
							This setting determines whether the Cover Art extractor looks first for Embedded cover art or a Cover image file.<br>
						</span>
	                </div>
	            </div>
	    	</fieldset>

			<h5>Theme and backrounds</h5>
	    	<fieldset>
				<div class="control-group">
   	                <label class="control-label" for="theme-name">Theme</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="theme-name" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open"> <!-- list generated in playerlib.js -->
								<ul id="theme-name-list" class="dropdown-menu custom-select inner" role="menu"></ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-themecolor" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-themecolor" class="help-block hide">
	                    	Sets the text and background color of the Browse, Library and Playback panels.<br>
	                    </span>
	                </div>

   	                <label class="control-label" for="theme-color">Accent color</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium"> <!-- handler in playerlib.js -->
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
						<a class="info-toggle" data-cmd="info-accentcolor" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-accentcolor" class="help-block hide">
	                    	Sets the color of the the knobs and other active elements.<br>
	                    </span>
	                </div>

   	                <label class="control-label" for="alpha-blend">Alpha blend</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="alpha-blend" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open"> <!-- list generated in playerlib.js -->
								<ul id="alpha-blend-list" class="dropdown-menu custom-select inner" role="menu"></ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-alphablend" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-alphablend" class="help-block hide">
	                    	Sets the opacity of the background color from 0.00 (fully transparent) to 1.00 (fully opaque).<br>
	                    </span>
	                </div>

   	                <label class="control-label" for="adaptive-enabled">Adaptive coloring</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="adaptive-enabled" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="adaptive-enabled-yn"><span class="text">Yes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="adaptive-enabled-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-adaptive" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-adaptive" class="help-block hide">
	                    	Sets the Playback panel color scheme based on the dominant color in the album artwork.<br>
	                    </span>
	                </div>

					<label class="control-label" for="choose-file">Image backdrop</label>
					<div class="controls">
						<div style="display:inline-block;">
							<label for="import-bgimage" class="btn btn-primary btn-small" style="font-size: 12px; margin-top: 2px; color: #333;">Choose</label>
							<input type="file" id="import-bgimage" accept="image/jpeg" style="display:none" onchange="importBgImage(this.files)">
							<br>
							<button id="remove-bgimage" class="btn btn-primary btn-small" style="font-size: 12px; margin-top: 2px; margin-bottom:.5em;color: #333;">Remove</button> 
						</div>
						<div id="current-bgimage" style="width:50px;display:inline-block;position:absolute;margin: 2px 0 0 5px;"></div>
						<a class="info-toggle" id="info-toggle-bgimage" data-cmd="info-bgimage" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<div id="error-bgimage"></div>
						<div id="info-bgimage" class="help-block hide">
							Sets the backdrop to the choosen JPEG image<br>
						</div>
					</div>

   	                <label class="control-label" for="cover-backdrop-enabled">Cover backdrop</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="cover-backdrop-enabled" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-backdrop-enabled-yn"><span class="text">Yes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-backdrop-enabled-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-cover-backdrop" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-cover-backdrop" class="help-block hide">
	                    	Sets the backdrop to the currently displayed album cover.<br>
	                    </span>
	                </div>

   	                <label class="control-label" for="cover-blur">Cover blur</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="cover-blur" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open"> <!-- list generated in playerlib.js -->
								<ul id="cover-blur-list" class="dropdown-menu custom-select inner" role="menu"></ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-cover-blur" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-cover-blur" class="help-block hide">
	                    	Sets the amount of blur to apply to the cover backdrop.<br>
	                    </span>
	                </div>

   	                <label class="control-label" for="cover-scale">Cover scale</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="cover-scale" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open"> <!-- list generated in playerlib.js -->
								<ul id="cover-scale-list" class="dropdown-menu custom-select inner" role="menu"></ul>
							</div>
						</div>
						<a class="info-toggle" data-cmd="info-cover-scale" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-cover-scale" class="help-block hide">
	                    	Increases the size of the cover backdrop.<br>
	                    </span>
	                </div>

				</div>
	    	</fieldset>

			<h5>Audio device description</h5>
	    	<fieldset>
				<div class="control-group">
   	                <label class="control-label" for="audio-device-name">Device</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-large2"> <!-- handler in playerlib.js -->
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
						<a class="info-toggle" data-cmd="info-device-name" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-device-name" class="help-block hide">
	                    	Select a device to have its description show on Audio info. I2S devices are automatically populated from System config. If device is not listed select "USB audio device".<br>
	                    </span>
	                </div>
					
	                <label class="control-label" for="audio-device-dac">Chip</label>
	                <div class="controls">
	                    <input id="audio-device-dac" class="input-xlarge input-height-x" type="text" name="audio_device_dac" value="" readonly>
	                </div>
	                <label class="control-label" for="audio-device-arch">Architecture</label>
	                <div class="controls">
	                    <input id="audio-device-arch" class="input-xlarge input-height-x" type="text" name="audio_device_arch" value="" readonly>
	                </div>
	                <label class="control-label" for="audio-device-iface">Interface</label>
	                <div class="controls">
	                    <input id="audio-device-iface" class="input-xlarge input-height-x" type="text" name="audio_device_iface" value="" readonly>
	                </div>
	            </div>
	    	</fieldset>
		</form>
	</div>

	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
		<button class="btn btn-customize-update btn-primary" data-dismiss="modal">Update</button>
	</div>
</div>

<!-- CLOCK RADIO -->	
<div id="clockradio-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="clockradio-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="clockradio-modal-label">Clock radio settings</h3>
	</div>
	<div class="modal-body" id="container-clockradio">
		<form class="form-horizontal" action="" method="">
	    	<fieldset>
				<div class="control-group">
	                <label class="control-label" for="clockradio-mode">Mode</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select" style="width: 120px;"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="clockradio-mode" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="clockradio-mode-sel"><span class="text">Disabled</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="clockradio-mode-sel"><span class="text">Clock Radio</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="clockradio-mode-sel"><span class="text">Sleep Timer</span></a></li>
								</ul>
							</div>
						</div>
	                </div>


					<div id="clockradio-ctl-grp1">

	                
	                <label class="control-label" for="clockradio-playname">Play</label>
	                <div class="controls">
	                    <input id="clockradio-playname" class="input-xlarge input-height-x" type="text" name="clockradio_playname" value="" readonly>
						<a class="info-toggle" data-cmd="info-playname" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-playname" class="help-block hide">
	                    	Use 'Set for clock radio' on the Playlist item menu to populate this read-only field.
	                    </span>
	                </div>
	                
	                <label class="control-label" for="clockradio-starttime-hh">Start time</label>
	                <div class="controls">
	                    <input id="clockradio-starttime-hh" class="input-mini input-height-x" type="number" maxlength="2" min="1" max="12" name="clockradio_starttime-hh" value="">
	                    <span>:</span>
	                    <input id="clockradio-starttime-mm" class="input-mini input-height-x" type="number" maxlength="2" min="0" max="59" name="clockradio_starttime-mm" value="">
						
						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="clockradio-starttime-ampm" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="clockradio-starttime-ampm"><span class="text">AM</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="clockradio-starttime-ampm"><span class="text">PM</span></a></li>
								</ul>
							</div>
						</div>
	                </div>

					<!-- r44d -->
	                <label class="control-label" for="clockradio-start-mon"></label>
	                <div class="controls">
						<div class="checkbox-grp">
							<input id="clockradio-start-mon" class="checkbox-ctl" type="checkbox" name="clockradio-start-mon">Mon
							<input id="clockradio-start-tue" class="checkbox-ctl" type="checkbox" name="clockradio-start-tue">Tue
							<input id="clockradio-start-wed" class="checkbox-ctl" type="checkbox" name="clockradio-start-wed">Wed
							<input id="clockradio-start-thu" class="checkbox-ctl" type="checkbox" name="clockradio-start-thu">Thu
							<input id="clockradio-start-fri" class="checkbox-ctl" type="checkbox" name="clockradio-start-fri">Fri
							<span>&nbsp;&nbsp;&nbsp;</span>
							<input id="clockradio-start-sat" class="checkbox-ctl" type="checkbox" name="clockradio-start-sat">Sat
							<input id="clockradio-start-sun" class="checkbox-ctl" type="checkbox" name="clockradio-start-sun">Sun
						</div>
	                </div>


					</div>
					<div id="clockradio-ctl-grp2">


	                <label class="control-label" for="clockradio-stoptime-hh">Stop time</label>
	                <div class="controls">
	                    <input id="clockradio-stoptime-hh" class="input-mini input-height-x" type="number" maxlength="2" min="1" max="12" name="clockradio_stoptime-hh" value="">
	                    <span>:</span>
	                    <input id="clockradio-stoptime-mm" class="input-mini input-height-x" type="number" maxlength="2" min="0" max="59" name="clockradio_stoptime-mm" value="">
						
						<div class="btn-group bootstrap-select bootstrap-select-mini"> <!-- handler in playerlib.js -->
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="clockradio-stoptime-ampm" class="filter-option pull-left">
									<span></span> <!-- selection from dropdown gets placed here -->
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="clockradio-stoptime-ampm"><span class="text">AM</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="clockradio-stoptime-ampm"><span class="text">PM</span></a></li>
								</ul>
							</div>
						</div>
	                </div>
	                
					<!-- r44d -->
	                <label class="control-label" for="clockradio-stop-mon"></label>
	                <div class="controls">
						<div class="checkbox-grp">
							<input id="clockradio-stop-mon" class="checkbox-ctl" type="checkbox" name="clockradio-stop-mon">Mon
							<input id="clockradio-stop-tue" class="checkbox-ctl" type="checkbox" name="clockradio-stop-tue">Tue
							<input id="clockradio-stop-wed" class="checkbox-ctl" type="checkbox" name="clockradio-stop-wed">Wed
							<input id="clockradio-stop-thu" class="checkbox-ctl" type="checkbox" name="clockradio-stop-thu">Thu
							<input id="clockradio-stop-fri" class="checkbox-ctl" type="checkbox" name="clockradio-stop-fri">Fri
							<span>&nbsp;&nbsp;&nbsp;</span>
							<input id="clockradio-stop-sat" class="checkbox-ctl" type="checkbox" name="clockradio-stop-sat">Sat
							<input id="clockradio-stop-sun" class="checkbox-ctl" type="checkbox" name="clockradio-stop-sun">Sun
						</div>
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
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="clockradio-shutdown-yn"><span class="text">Yes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="clockradio-shutdown-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
						</div>
						<span class="control-aftertext">after stop</span>
	                </div>


					</div>
					<div id="clockradio-ctl-grp3">


	                <label class="control-label" for="clockradio-volume">Volume</label>
	                <div class="controls">
	                    <input id="clockradio-volume" class="input-mini input-height-x" type="number" min="1" max="100" name="clockradio_volume" value="">
	                </div>
	                

					</div>


	            </div>
	    	</fieldset>
		</form>
	</div>
	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
		<button class="btn btn-clockradio-update btn-primary" data-dismiss="modal">Update</button>
	</div>
</div>

<!-- PLAYERS -->	
<div id="players-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="players-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="players-modal-label">Players</h3>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn singleton" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- AUDIO INFO -->	
<div id="audioinfo-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="audioinfo-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="audioinfo-modal-label">Audio information</h3>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn singleton" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- SYSTEM INFO -->	
<div id="sysinfo-modal" class="modal modal-sm hide fade" tabindex="-1" role="dialog" aria-labelledby="sysinfo-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="sysinfo-modal-label">System information</h3>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn singleton" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- RESTART -->	
<div id="restart-modal" class="modal modal-sm2 hide fade" tabindex="-1" role="dialog" aria-labelledby="restart-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="restart-modal-label"><i class="fas fa-power-off sx"></i></h3>
	</div>
	<div class="modal-body">
		<button id="syscmd-poweroff" data-dismiss="modal" class="btn btn-primary btn-large btn-block"></i>Shutdown</button>
		<button id="syscmd-reboot" data-dismiss="modal" class="btn btn-primary btn-large btn-block" style="margin-bottom:15px;"></i>Reboot</button>
	</div>
	<div class="modal-footer">
		<button class="btn singleton" data-dismiss="modal" aria-hidden="true">Cancel</button>
	</div>
</div>

<!-- RECONNECT/REBOOT/POWEROFF -->
<div id="reconnect" class="hide">
	<div class="reconnectbg"></div>
	<div class="reconnectbtn">
		<a href="javascript:location.reload(true); void 0" class="btn btn-primary btn-large">reconnect</a>
	</div>
</div>

<div id="reboot" class="hide">
	<div class="reconnectbg"></div>
	<div class="reconnectbtn">
		<a href="javascript:location.reload(true); void 0" class="btn btn-primary btn-large">reconnect</a>
		<br>System rebooting
	</div>
</div>

<div id="poweroff" class="hide">
	<div class="reconnectbg"></div>
	<div class="reconnectbtn">
		<a href="javascript:location.reload(true); void 0" class="btn btn-primary btn-large">reconnect</a>
		<br>System powered off
	</div>
</div>

<!-- SAVE PLAYLIST -->
<div id="savepl-modal" class="modal modal-xs hide fade" tabindex="-1" role="dialog" aria-labelledby="savepl-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="moveplitems-modal-label">Save playlist</h3>
	</div>
	<div class="modal-body">
		<form id="pl-save" method="post" onsubmit="return false;">
	    	<fieldset>
				<div class="controls">
					<input id="pl-saveName" type="text" value="">
					<a class="info-toggle" data-cmd="info-savepl" href="#notarget"><i class="fas fa-info-circle"></i></a>
					<span id="info-savepl" class="help-block help-block2 hide">
	                	Saved playlists are maintained on the Browse panel.
	                </span>
	            </div>
	    	</fieldset>
		</form>
	</div>
	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
		<button id="pl-btnSave" class="btn btn-savepl btn-primary" title="Save playlist" data-dismiss="modal">Save</button>
	</div>
</div>

<!-- SET FAVORITES -->
<div id="setfav-modal" class="modal modal-xs hide fade" tabindex="-1" role="dialog" aria-labelledby="newpl-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="setfav-modal-label">Set Favorites Name</h3>
	</div>
	<div class="modal-body">
		<form id="set-fav" method="post" onsubmit="return false;">
	    	<fieldset>
				<div class="controls">
					<input id="pl-favName" type="text" value="">
					<a class="info-toggle" data-cmd="info-favname" href="#notarget"><i class="fas fa-info-circle"></i></a>
					<span id="info-favname" class="help-block help-block2 hide">
		            	Sets the playlist that will be used to store favorited items. If the playlist does not already exist it will be created. Playlists are maintained on the Browse panel.
		            </span>
	            </div>
	    	</fieldset>
		</form>
	</div>
	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
		<button id="pl-btnSetFav" class="btn btn-savepl btn-primary" title="Set favorites name" data-dismiss="modal">Set</button>
	</div>
</div>

<!-- FRAMEWORK LIBS -->
<?php
versioned_script('js/jquery-1.8.2.min.js');
versioned_script('js/jquery-ui-1.10.0.custom.min.js');
versioned_script('js/jquery.countdown.js');
versioned_script('js/jquery.scrollTo.min.js');
versioned_script('js/bootstrap.min.js');
versioned_script('js/bootstrap-select.min.js');
?>
<!-- MOODE LIBS -->
<?php
versioned_script('js/jquery.adaptive-backgrounds.js');
versioned_script('js/notify.js');
versioned_script('js/playerlib.js');
versioned_script('js/links.js');
versioned_script('js/jquery.touchSwipe.min.js');
versioned_script('js/jquery.lazyload.js');
versioned_script('js/jquery.md5.js');
?>

<!-- LIBS FOR PANELS OR CONFIGS -->
<?php if ($section == 'index') {
	versioned_script('jsw/jquery.knob.js');
	versioned_script('js/bootstrap-contextmenu.js');
	versioned_script('js/jquery.pnotify.min.js');
	versioned_script('js/scripts-panels.js');
} else {
	versioned_script('js/custom_checkbox_and_radio.js');
	versioned_script('js/custom_radio.js');
	versioned_script('js/jquery.tagsinput.js');
	versioned_script('js/jquery.placeholder.js');
	versioned_script('js/i18n/_messages.en.js', 'text/javascript');
	versioned_script('js/application.js');
	versioned_script('js/jquery.pnotify.min.js');
	versioned_script('js/scripts-configs.js');
} ?>

<!-- DISPLAY MESSAGES -->
<?php if (isset($_SESSION['notify']) && $_SESSION['notify'] != '') {
	ui_notify($_SESSION['notify']);
	session_start();
	$_SESSION['notify'] = '';
	session_write_close();
} ?>

</body>
</html>
