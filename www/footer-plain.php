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
 * 2020-MM-DD TC moOde 6.5.0
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
				<li>Release: 6.5.0 2020-MM-DD <a class="moode-about-link1" href="./relnotes.txt" target="_blank">View relnotes</a></li>
				<li>Setup guide: <a class="moode-about-link1" href="./setup.txt" target="_blank">View guide</a></li>
				<li>Coding:	Tim Curtis &copy; 2014 <a class="moode-about-link1" href="http://moodeaudio.org" target="_blank">Moode Audio</a>, <a class="moode-about-link1" href="https://twitter.com/MoodeAudio" target="_blank">Twitter</a></li>
				<li>Contributors: <a class="moode-about-link1" href="./CONTRIBS.html" target="_blank">View contributors</a></li>
				<li>License: <a class="moode-about-link1" href="./COPYING.html" target="_blank">View GPLv3</a></li>
			</ul>
		<p>
			<h4>Platform Information</h4>
			<ul>
				<li>Raspbian: <span id="sys-raspbian-ver"></span></li>
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
				<li><a href="sys-config.php" class="btn btn-large"><i class="fas fa-desktop-alt"></i><br>System</a></li>
			</ul>
			<br>
			<ul>
				<li><a href="mpd-config.php" class="btn btn-small row2-btns">MPD options</a></li>
				<li><a href="eqp-config.php" class="btn btn-small row2-btns">Parametric EQ</a></li>
				<li><a href="eqg-config.php" class="btn btn-small row2-btns">Graphic EQ</a></li>
				<li class="context-menu"><a href="#notarget" class="btn btn-small row2-btns" data-cmd="setforclockradio-m">Clock radio</a></li>
				<?php if ($_SESSION['feat_bitmask'] & $FEAT_INPSOURCE) { ?>
					<li><a href="inp-config.php" class="btn btn-small row2-btns">Input source</a></li>
				<?php } ?>
			</ul>
		</div>
	</div>

	<div class="modal-footer">
		<button aria-label="Close" class="btn singleton" data-dismiss="modal" aria-hidden="true">Close</button>
	</div>
</div>

<!-- APPEARANCE -->
<div id="appearance-modal" class="modal modal-sm hide" tabindex="-1" role="dialog" aria-labelledby="appearance-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button aria-label="Close" type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="appearance-modal-label">Appearance</h3>
	</div>
	<div class="modal-body" id="container-appearance">
		<form class="form-horizontal" action="" method="">
			<div class="accordian"><span class="h5">Theme & background</span><span class="dtclose">&nbsp;&#x25b8;</span><span class="dtopen">&nbsp;&#x25be;</span>
				<div class="control-group">
   	                <label class="control-label" for="theme-name">Theme</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="theme-name" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div id="theme-name-menu" class="dropdown-menu open">
								<ul id="theme-name-list" class="dropdown-menu custom-select inner" role="menu"></ul>
							</div>
						</div>
						<a aria-label="Help" class="info-toggle" data-cmd="info-themecolor" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-themecolor" class="help-block hide">
	                    	Sets the text and background color.
	                    </span>
	                </div>

   	                <label class="control-label" for="accent-color">Accent color</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="accent-color" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #c0392b; font-weight: bold;">Alizarin</span></a></li>
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #8e44ad; font-weight: bold;">Amethyst</span></a></li>
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #1a439c; font-weight: bold;">Bluejeans</span></a></li>
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #d35400; font-weight: bold;">Carrot</span></a></li>
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #27ae60; font-weight: bold;">Emerald</span></a></li>
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #cb8c3e; font-weight: bold;">Fallenleaf</span></a></li>
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #7ead49; font-weight: bold;">Grass</span></a></li>
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #317589; font-weight: bold;">Herb</span></a></li>
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #876dc6; font-weight: bold;">Lavender</span></a></li>
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #2980b9; font-weight: bold;">River</span></a></li>
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #c1649b; font-weight: bold;">Rose</span></a></li>
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #999999; font-weight: bold;">Silver</span></a></li>
									<li><a href="#notarget" data-cmd="accent-color-sel"><span class="text" style="color: #16a085; font-weight: bold;">Turquoise</span></a></li>
								</ul>
							</div>
						</div>
						<a aria-label="Help" class="info-toggle" data-cmd="info-accentcolor" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-accentcolor" class="help-block hide">
	                    	Sets the color of the knobs and other active elements.
	                    </span>
	                </div>

   	                <label class="control-label" for="alpha-blend">Alpha blend</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="alpha-blend" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">1.00</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.95</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.90</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.85</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.80</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.75</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.70</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.65</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.60</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.55</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.50</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.45</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.40</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.35</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.30</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.25</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.20</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.15</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.10</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.05</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="alpha-blend-sel"><span class="text">0.00</span></a></li>
								</ul>
							</div>
						</div>
						<a aria-label="Help" class="info-toggle" data-cmd="info-alphablend" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-alphablend" class="help-block hide">
	                    	Sets the opacity of the background color from 0.00 (fully transparent) to 1.00 (fully opaque). Values less than 1.00 allow cover and image backdrops to become visible.
	                    </span>
	                </div>

   	                <label class="control-label" for="adaptive-enabled">Adaptive coloring</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="adaptive-enabled" class="filter-option pull-left">
									<span></span>
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
						<a aria-label="Help" class="info-toggle" data-cmd="info-adaptive" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-adaptive" class="help-block hide">
	                    	Sets the Playback panel color scheme based on the dominant color in the album artwork.
	                    </span>
	                </div>

					<div id="cover-options">
						<label class="control-label bgimglabel" for="choose-file">Image backdrop</label>
						<div class="controls">
							<div style="display:inline-block;margin-bottom:.5em;margin-top:-1px">
								<label for="import-bgimage" id="choose-bgimage" class="btn btn-primary btn-small">Choose</label>
								<input type="file" id="import-bgimage" accept="image/jpeg" style="display:none" onchange="importBgImage(this.files)">
								<button id="remove-bgimage" class="btn btn-primary btn-small hide">Remove</button>
							</div>
							<div id="current-bgimage"></div>
							<a aria-label="Help" class="info-toggle" id="info-toggle-bgimage" data-cmd="info-bgimage" href="#notarget"><i class="fas fa-info-circle"></i></a>
							<div id="error-bgimage"></div>
							<div id="info-bgimage" class="help-block hide">
								Sets the backdrop to the choosen JPEG image. Max image size is 1MB.
							</div>
						</div>
	   	                <label class="control-label" for="cover-backdrop-enabled">Cover backdrop</label>
		                <div class="controls">
	   						<div class="btn-group bootstrap-select bootstrap-select-mini">
								<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
									<div id="cover-backdrop-enabled" class="filter-option pull-left">
										<span></span>
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
							<a aria-label="Help" class="info-toggle" data-cmd="info-cover-backdrop" href="#notarget"><i class="fas fa-info-circle"></i></a>
							<span id="info-cover-backdrop" class="help-block hide">
		                    	Sets the backdrop to the currently displayed album cover.
		                    </span>
		                </div>

	   	                <label class="control-label" for="cover-blur">Cover blur</label>
		                <div class="controls">
	   						<div class="btn-group bootstrap-select bootstrap-select-mini">
								<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
									<div id="cover-blur" class="filter-option pull-left">
										<span></span>
									</div>&nbsp;
									<div class="caret"></div>
								</button>
								<div class="dropdown-menu open">
									<ul id="cover-blur-list" class="dropdown-menu custom-select inner" role="menu">
										<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">0px</span></a></li>
										<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">5px</span></a></li>
										<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">10px</span></a></li>
										<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">15px</span></a></li>
										<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">20px</span></a></li>
										<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-blur-sel"><span class="text">30px</span></a></li>
									</ul>
								</div>
							</div>
							<a aria-label="Help" class="info-toggle" data-cmd="info-cover-blur" href="#notarget"><i class="fas fa-info-circle"></i></a>
							<span id="info-cover-blur" class="help-block hide">
		                    	Sets the amount of blur to apply to the cover backdrop.
		                    </span>
		                </div>

	   	                <label class="control-label" for="cover-scale">Cover scale</label>
		                <div class="controls">
	   						<div class="btn-group bootstrap-select bootstrap-select-mini">
								<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
									<div id="cover-scale" class="filter-option pull-left">
										<span></span>
									</div>&nbsp;
									<div class="caret"></div>
								</button>
								<div class="dropdown-menu open">
									<ul id="cover-scale-list" class="dropdown-menu custom-select inner" role="menu">
										<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel">1.0</a></li>
										<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel">1.25</a></li>
										<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel">1.5</a></li>
										<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel">1.75</a></li>
										<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-scale-sel">2.0</a></li>
									</ul>
								</div>
							</div>
							<a aria-label="Help" class="info-toggle" data-cmd="info-cover-scale" href="#notarget"><i class="fas fa-info-circle"></i></a>
							<span id="info-cover-scale" class="help-block hide">
		                    	Increases the size of the cover backdrop.
		                    </span>
		                </div>
					</div>
				</div>
			</div>

			<div class="accordian"><span class="h5">Library</span><span class="dtclose">&nbsp;&#x25b8;</span><span class="dtopen">&nbsp;&#x25be;</span>
				<div class="control-group">
   	                <label class="control-label" for="instant-play-action">Instant play action</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="instant-play-action" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="instant-play-action-sel"><span class="text">No action</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="instant-play-action-sel"><span class="text">Add/Play</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="instant-play-action-sel"><span class="text">Clear/Play</span></a></li>
								</ul>
							</div>
						</div>
						<a aria-label="Help" class="info-toggle" data-cmd="info-instant-play-action" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-instant-play-action" class="help-block hide">
	                    	Configure Instant Play to perform Clear/Play or Add/Play.
	                    </span>
	                </div>

					<label class="control-label" for="show-genres-column">Show genres column</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="show-genres-column" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="show-genres-column-yn"><span class="text">Yes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="show-genres-column-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
						</div>
						<a aria-label="Help" class="info-toggle" data-cmd="info-show-genres-column" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-show-genres-column" class="help-block hide">
	                    	Show the Genres column in Library Tag view.
	                    </span>
	                </div>

					<label class="control-label" for="show-tagview-covers">Show tagview covers</label>
 	                <div class="controls">
						<div class="btn-group bootstrap-select bootstrap-select-mini">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="show-tagview-covers" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="show-tagview-covers-yn"><span class="text">Yes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="show-tagview-covers-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
 						</div>
 						<a aria-label="Help" class="info-toggle" data-cmd="info-show-tagview-covers" href="#notarget"><i class="fas fa-info-circle"></i></a>
 						<span id="info-show-tagview-covers" class="help-block hide">
 		                    	Show covers in the Album column of Library Tag view.
 	                    </span>
 	                </div>

					<label class="control-label" for="ellipsis-limited-text">Ellipsis limited text</label>
 	                <div class="controls">
						<div class="btn-group bootstrap-select bootstrap-select-mini">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="ellipsis-limited-text" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="ellipsis-limited-text-yn"><span class="text">Yes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="ellipsis-limited-text-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
 						</div>
 						<a aria-label="Help" class="info-toggle" data-cmd="info-ellipsis-limited-text" href="#notarget"><i class="fas fa-info-circle"></i></a>
 						<span id="info-ellipsis-limited-text" class="help-block hide">
 		                    	Display ellipsis limited text underneath the cover in Album view for a more compact look.
 	                    </span>
 	                </div>

					<label class="control-label" for="albumview-sort-order">Albumview sort order</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="albumview-sort-order" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="albumview-sort-order-sel"><span class="text">by Album</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="albumview-sort-order-sel"><span class="text">by Artist</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="albumview-sort-order-sel"><span class="text">by Artist/Year</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="albumview-sort-order-sel"><span class="text">by Year</span></a></li>
								</ul>
							</div>
						</div>
						<a aria-label="Help" class="info-toggle" data-cmd="info-albumview-sort-order" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-albumview-sort-order" class="help-block hide">
							<b>by Album:</b> Albums are sorted alphabetically.<br>
	                        <b>by Artist:</b> Albums for each artist are sorted alphabetically.<br>
	                        <b>by Artist/Year:</b> Albums for each artist are sorted chronologically.<br>
	                        <b>by Year:</b> Albums are sorted chronologically.<br>
	                        NOTE: When sorted by Year if not all tracks in the album have the same Year tag then the latest year will be used. If no Year tag exists or if the Year tag is not a number then no year information will be shown for that album.
						</span>
	                </div>

					<label class="control-label" for="tagview-sort-order">Tagview sort order</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="tagview-sort-order" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="tagview-sort-order-sel"><span class="text">by Album</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="tagview-sort-order-sel"><span class="text">by Album/Year</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="tagview-sort-order-sel"><span class="text">by Artist</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="tagview-sort-order-sel"><span class="text">by Artist/Year</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="tagview-sort-order-sel"><span class="text">by Year</span></a></li>
								</ul>
							</div>
						</div>
						<a aria-label="Help" class="info-toggle" data-cmd="info-tagview-sort-order" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-tagview-sort-order" class="help-block hide">
							<b>by Album:</b> Albums are sorted alphabetically.<br>
							<b>by Album/Year:</b> Albums are sorted alphabetically in the full list. When an Artist is clicked albums for the artist are sorted chronologically<br>
	                        <b>by Artist:</b> Albums for each artist are sorted alphabetically.<br>
							<b>by Artist/Year:</b> Albums for each artist are sorted chronologically.<br>
	                        <b>by Year:</b> Albums are sorted chronologically.<br>
	                        NOTE: When sorted by Year if not all tracks in the album have the same Year tag then the latest year will be used. If no Year tag exists or if the Year tag is not a number then no year information will be shown for that album.
						</span>
	                </div>

					<label class="control-label" for="compilation-identifier">Compilation identifier</label>
	                <div class="controls">
	                    <input id="compilation-identifier" class="input-xlarge input-height-x" type="text">
						<a aria-label="Help" class="info-toggle" data-cmd="info-compilation-identifier" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-compilation-identifier" class="help-block hide">
							Enter the text that is present in the AlbumArtist tag to identify tracks as belonging to a compilation album. The default is "Various Artists".
						</span>
	                </div>

					<label class="control-label" for="recently-added">Recently added</label>
 	                <div class="controls">
    						<div class="btn-group bootstrap-select select-medium">
 							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
 								<div id="recently-added" class="filter-option pull-left">
 									<span></span>
 								</div>&nbsp;
 								<div class="caret"></div>
 							</button>
 							<div class="dropdown-menu open">
 								<ul class="dropdown-menu custom-select inner" role="menu">
 									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="recently-added-sel"><span class="text">1 Week</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="recently-added-sel"><span class="text">1 Month</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="recently-added-sel"><span class="text">3 Months</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="recently-added-sel"><span class="text">6 Months</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="recently-added-sel"><span class="text">1 Year</span></a></li>
 								</ul>
 							</div>
 						</div>
 						<a aria-label="Help" class="info-toggle" data-cmd="info-recently-added" href="#notarget"><i class="fas fa-info-circle"></i></a>
 						<span id="info-recently-added" class="help-block hide">
 		                    Time period used to filter the Library for recently added albums. The default is 1 month.
 	                    </span>
 	                </div>

					<label class="control-label" for="ignore-articles">Ignore articles</label>
	                <div class="controls">
	                    <input id="ignore-articles" class="input-xlarge input-height-x" type="text">
						<a aria-label="Help" class="info-toggle" data-cmd="info-ignore-articles" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-ignore-articles" class="help-block hide">
							Specify a comma separated list of articles to be ignored when sorting. Embedded spaces are not allowed.<br>
							NOTE: A blank list will result in "None" and will effectively disable the option.
						</span>
	                </div>

					<label class="control-label" for="utf8-char-filter">UTF8 character filter</label>
 	                <div class="controls">
						<div class="btn-group bootstrap-select bootstrap-select-mini">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="utf8-char-filter" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="utf8-char-filter-yn"><span class="text">Yes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="utf8-char-filter-yn"><span class="text">No</span></a></li>
								</ul>
							</div>
 						</div>
 						<a aria-label="Help" class="info-toggle" data-cmd="info-utf8-char-filter" href="#notarget"><i class="fas fa-info-circle"></i></a>
 						<span id="info-utf8-char-filter" class="help-block hide">
							Many Chinese songs and song directories have characters that are not UTF8 encoded causing the Library loader to fail. Replacing the non-UTF8 characters with a UTF8 compliant character solves this problem.<br>
							NOTE: setting this to Yes may impact the performance of the Library loader.
 	                    </span>
 	                </div>

					<label class="control-label" for="hires-thumbnails">Hi-res thumbnails</label>
 	                <div class="controls">
						<div class="btn-group bootstrap-select select-medium">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="hires-thumbnails" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="hires-thumbnails-sel"><span class="text">Auto</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="hires-thumbnails-sel"><span class="text">100px</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="hires-thumbnails-sel"><span class="text">200px</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="hires-thumbnails-sel"><span class="text">300px</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="hires-thumbnails-sel"><span class="text">400px</span></a></li>
								</ul>
							</div>
 						</div>
 						<a aria-label="Help" class="info-toggle" data-cmd="info-hires-thumbnails" href="#notarget"><i class="fas fa-info-circle"></i></a>
 						<span id="info-hires-thumbnails" class="help-block hide">
							Tell the thumbnail generator to output high-resolution images suitable for large displays or high DPI (Retina) screens. The Auto setting will use the device's pixel ratio to determine an optimum image size and quality while maintaining the smallest file size. Manual settings will result in images of the specified size using a quality factor of 75.<br>
							NOTE: larger images may result in slower loading of thumbnail images into the Album Cover panel.
 	                    </span>
 	                </div>

					<label class="control-label" for="cover-search-priority">Cover search priority</label>
 	                <div class="controls">
						<div class="btn-group bootstrap-select select-medium">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="cover-search-priority" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-search-priority-sel"><span class="text">Embedded</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="cover-search-priority-sel"><span class="text">Cover file</span></a></li>
								</ul>
							</div>
 						</div>
 						<a aria-label="Help" class="info-toggle" data-cmd="info-cover-search-prioritys" href="#notarget"><i class="fas fa-info-circle"></i></a>
 						<span id="info-cover-search-priority" class="help-block hide">
							This option determines whether the Cover Art extractor looks first for Embedded covers or a Cover files.
 	                    </span>
 	                </div>
				</div>
			</div>

			<div class="accordian"><span class="h5">CoverView</span><span class="dtclose">&nbsp;&#x25b8;</span><span class="dtopen">&nbsp;&#x25be;</span>
				<div class="control-group">
   	                <label class="control-label" for="scnsaver-timeout">Automatic display</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="scnsaver-timeout" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">Never</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">1 minute</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">2 minutes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">5 minutes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">10 minutes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">20 minutes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">30 minutes</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-timeout-sel"><span class="text">1 hour</span></a></li>
								</ul>
							</div>
						</div>
						<a aria-label="Help" class="info-toggle" data-cmd="info-scnsaver-timeout" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-scnsaver-timeout" class="help-block hide">
	                    	Display a fullscreen view of cover art and song data after the specified number of minutes.
	                    </span>
	                </div>

   	                <label class="control-label" for="scnsaver-timeout">Backdrop style</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="scnsaver-style" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-style-sel"><span class="text">Animated</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-style-sel"><span class="text">Theme</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-style-sel"><span class="text">Gradient (Linear)</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-style-sel"><span class="text">Gradient (Radial)</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="scnsaver-style-sel"><span class="text">Pure Black</span></a></li>
								</ul>
							</div>
						</div>
						<a aria-label="Help" class="info-toggle" data-cmd="info-scnsaver-style" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-scnsaver-style" class="help-block hide">
	                    	Set the effect used for the backdrop.<br>
							<b>Animated:</b> Color change overlay.<br>
							<b>Theme:</b> Theme color overlay.<br>
							<b>Gradient (Linear):</b> Gradient overlay, top to bottom.<br>
							<b>Gradient (Radial):</b> Gradient overlay, center to edge.<br>
							<b>Pure Black:</b> Solid black overlay.
	                    </span>
	                </div>
				</div>
			</div>

			<div class="accordian"><span class="h5">Other</span><span class="dtclose">&nbsp;&#x25b8;</span><span class="dtopen">&nbsp;&#x25be;</span>
				<div class="control-group">
   	                <label class="control-label" for="font-size">Font size</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select select-medium">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="font-size" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="font-size-sel"><span class="text">Smaller</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="font-size-sel"><span class="text">Small</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="font-size-sel"><span class="text">Normal</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="font-size-sel"><span class="text">Large</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="font-size-sel"><span class="text">Larger</span></a></li>
								</ul>
							</div>
						</div>
						<a aria-label="Help" class="info-toggle" data-cmd="info-font-size" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-font-size" class="help-block hide">
		                    	Set the size of the font used.
	                    </span>
	                </div>
	                <label class="control-label" for="ashuffle-filter">Auto-shuffle filter</label>
	                <div class="controls">
	                    <input id="ashuffle-filter" class="input-xlarge input-height-x" type="text">
						<a aria-label="Help" class="info-toggle" data-cmd="info-ashuffle-filter" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-ashuffle-filter" class="help-block hide">
							String of TAG VALUE pairs that Auto-shuffle uses to select the tracks being shuffled. Only one occurance of a given TAG is allowed. The filter is case insensitive and it performs a TAG contains VALUE substring match.<br>
							Example: genre "indie rock" artist coldplay.
						</span>
	                </div>

   	                <label class="control-label" for="extra-tags">Extra metadata</label>
	                <div class="controls">
						<input id="extra-tags" class="input-xlarge input-height-x" type="text">
						<a aria-label="Help" class="info-toggle" data-cmd="info-extra-tags" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-extra-tags" class="help-block hide">
		                    	Enter any combination of track, disc, year, composer, encoded or None for display under the cover art.
	                    </span>
	                </div>

   	                <label class="control-label" for="play-history-enabled">Playback history</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select bootstrap-select-mini">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="play-history-enabled" class="filter-option pull-left">
									<span></span>
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
						<a aria-label="Help" class="info-toggle" data-cmd="info-play-history" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-play-history" class="help-block hide">
	                    	Log each song played to the playback history log. Songs in the log can be clicked to launch a Google search. The log can be cleared from System config.
	                    </span>
	                </div>
				</div>
			</div>
		</form>
	</div>
	<div class="modal-footer">
		<button aria-label="Cancel" class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
		<button aria-label="Update" class="btn btn-appearance-update btn-primary" data-dismiss="modal">Update</button>
	</div>
</div>

<!-- CLOCK RADIO -->
<div id="clockradio-modal" class="modal modal-sm hide" tabindex="-1" role="dialog" aria-labelledby="clockradio-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button aria-label="Close" type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="clockradio-modal-label">Clock radio settings</h3>
	</div>
	<div class="modal-body" id="container-clockradio">
		<form class="form-horizontal" action="" method="">
			<div class="control-group">
                <label class="control-label" for="clockradio-mode">Clock mode</label>
                <div class="controls">
					<div class="btn-group bootstrap-select" style="width: 120px;">
						<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
							<div id="clockradio-mode" class="filter-option pull-left">
								<span></span>
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
	                <label class="control-label" for="clockradio-playname">Item to play</label>
	                <div class="controls">
	                    <input id="clockradio-playname" class="input-xlarge input-height-x" type="text" name="clockradio_playname" value="" readonly>
						<a aria-label="Help" class="info-toggle" data-cmd="info-playname" href="#notarget"><i class="fas fa-info-circle"></i></a>
						<span id="info-playname" class="help-block hide">
		                    Use 'Set for clock radio' on the Playlist item menu to populate this read-only field.
	                    </span>
	                </div>

	                <label class="control-label" for="clockradio-starttime-hh">Start time</label>
	                <div class="controls">
	                    <input id="clockradio-starttime-hh" class="input-mini input-height-x" type="number" maxlength="2" min="1" max="12" name="clockradio_starttime-hh" value="">
	                    <span>:</span>
	                    <input id="clockradio-starttime-mm" class="input-mini input-height-x" type="number" maxlength="2" min="0" max="59" name="clockradio_starttime-mm" value="">

						<div class="btn-group bootstrap-select bootstrap-select-mini">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="clockradio-starttime-ampm" class="filter-option pull-left">
									<span></span>
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

						<div class="btn-group bootstrap-select bootstrap-select-mini">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="clockradio-stoptime-ampm" class="filter-option pull-left">
									<span></span>
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

	                <label class="control-label" for="clockradio-action">Action after stop</label>
	                <div class="controls">
   						<div class="btn-group bootstrap-select" style="width: 120px;">
							<button type="button" class="btn btn-inverse dropdown-toggle" data-toggle="dropdown">
								<div id="clockradio-action" class="filter-option pull-left">
									<span></span>
								</div>&nbsp;
								<div class="caret"></div>
							</button>
							<div class="dropdown-menu open">
								<ul class="dropdown-menu custom-select inner" role="menu">
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="clockradio-action-sel"><span class="text">None</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="clockradio-action-sel"><span class="text">Restart</span></a></li>
									<li class="modal-dropdown-text"><a href="#notarget" data-cmd="clockradio-action-sel"><span class="text">Shutdown</span></a></li>
								</ul>
							</div>
						</div>
						<a aria-label="Help" class="info-toggle" data-cmd="info-action" href="#notarget"><i class="fas fa-info-circle"></i></a></span>
						<span id="info-action" class="help-block hide">
							NOTE: The Restart action is initiated 45 seconds after the specified stop time.
						</span>
	                </div>
				</div>

				<div id="clockradio-ctl-grp3">
	                <label class="control-label" for="clockradio-volume">Volume</label>
	                <div class="controls">
	                    <input id="clockradio-volume" class="input-mini input-height-x" type="number" min="1" max="100" name="clockradio_volume" value="">
	                </div>
				</div>

            </div>
		</form>
	</div>
	<div class="modal-footer">
		<button aria-label="Cancel" class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
		<button aria-label="Update" class="btn btn-clockradio-update btn-primary" data-dismiss="modal">Update</button>
	</div>
</div>

<!-- PLAYERS -->
<div id="players-modal" class="modal modal-sm hide" tabindex="-1" role="dialog" aria-labelledby="players-modal-label" aria-hidden="true">
	<div class="modal-header">
		<button aria-label="Close" type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3 id="players-modal-label">Other Players</h3>
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
		<button aria-label="Shutdown" id="syscmd-poweroff" data-dismiss="modal" class="btn btn-primary btn-large btn-block">Shutdown</button>
		<button aria-label="Restart" id="syscmd-reboot" data-dismiss="modal" class="btn btn-primary btn-large btn-block" style="margin-bottom:15px;">Restart</button>
	</div>
	<div class="modal-footer">
		<button aria-label="Cancel" class="btn singleton" data-dismiss="modal" aria-hidden="true">Cancel</button>
	</div>
</div>

<!-- RECONNECT/RESTART/POWEROFF -->
<div id="reconnect" class="hide">
	<div class="reconnectbg"></div>
	<div class="reconnectbtn">
		<a href="javascript:location.reload(true); void 0" class="btn btn-primary btn-large">reconnect</a>
	</div>
</div>

<div id="restart" class="hide">
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

<!-- DISPLAY MESSAGES -->
<script src="js/jquery-1.8.2.min.js"></script>
<script src="js/jquery-ui-1.10.0.custom.min.js"></script>

<?php
    if (isset($_SESSION['notify']['title']) && $_SESSION['notify']['title'] != '') {
        ui_notify($_SESSION['notify']);
        $_SESSION['notify']['title'] = '';
        $_SESSION['notify']['msg'] = '';
        $_SESSION['notify']['duration'] = '3';
    }

    //workerLog('-- footer.php');
    $return = session_write_close();
    //workerLog('session_write_close=' . (($return) ? 'TRUE' : 'FALSE'));
?>

</body>
</html>
