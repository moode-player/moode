/*! jshint debug: true, expr: true */

/* 2018-07-11 TC moOde 4.2
 * - css vars, other css for newui v2
 * - adj method for dominance
 * - clean up tabbing
 * 2018-09-27 TC moOde 4.3
 * - minor fixes
 * 2018-12-09 TC moOde 4.4
 * - improvements for btnBarFix()
 * 2019-05-30 TC moOde 5.3
 * - Add parseInt to getYiq()
 *
 * 2020-MM-DD TC moOde 7.0.0
 */

;
(function ($) {
	/* Constants & defaults. */
	var DATA_COLOR = 'data-ab-color';
	var DATA_PARENT = 'data-ab-parent';
	var DATA_CSS_BG = 'data-ab-css-background';
	var EVENT_CF = 'ab-color-found';
	var BLEND = 'blend';

	var DEFAULTS = {
		selector: '[data-adaptive-background]',
		parent: '#playback-panel',
		exclude: ['rgb(0,0,0)', 'rgb(255,255,255)'],
		normalizeTextColor: true,
		normalizedTextColors: {light: "#eee", dark: "#333"},
		transparent: null
	};

	// Include RGBaster - https://github.com/briangonzalez/rgbaster.js
	/* jshint ignore:start */
	!function (n, t) {
		"use strict";
		var t = function () {
		  	return document
		  	.createElement("canvas")
		  	.getContext("2d")
		},
		e = function (n, e) {
			var a = new Image,
			o = n.src || n;
			"data:" !== o.substring(0, 5) && (a.crossOrigin = "Anonymous"),
			a.onerror = function() {
				adaptMcolor = themeMcolor;
				adaptMback = themeMback;
				adaptColor = themeColor;
				adaptBack = themeBack;
			},
			a.onload = function () {
				var n = t("2d");
				n.drawImage(a, 0, 0);
				var o = n.getImageData(0, 0, a.width, a.height);
				e && e(o.data)
			},
			a.src = o
		},
		a = function (n) {
			return ["rgb(", n, ")"].join("")
		},
		o = function (n) {
			return n.map(function (n) {
				return a(n.name)
			})
		},
		r = 5,
		i = 10,
		c = {};
		c.colors = function (n, t) {
			t = t || {};
			var c = t.exclude || [],
			u = t.paletteSize || i;
			e(n, function (e) {
				for (var i = n.width * n.height || e.length, m = {}, s = "", dk = [], d = [], f = {
					dominant: {
						name: "",
						count: 0
					},
					palette: Array
						.apply(null, new Array(u))
						.map(Boolean)
						.map(function () {
							return {name: "0,0,0", count: 0}
						})
				}, l = 0; i > l;) {
						if (d[0] = e[l], d[1] = e[l + 1], d[2] = e[l + 2], s = d.join(","), m[s] = s in m ? m[s] + 1 : 1, -1 === c.indexOf(a(s))) {
							var g = m[s];
							g > f.dominant.count ? (f.dominant.name = s, f.dominant.count = g) : f.palette.some(function (n) {
								return g > n.count ? (n.name = s, n.count = g, !0) : void 0
							})
						}
						//}
					l += 4 * r
				}
				if (t.success) {
					var p = o(f.palette);
					t.success({
						dominant: p[0],
						secondary: p[1],
						palette: p
					})
				}
			})
		},
		n.RGBaster = n.RGBaster || c
	}

	(window);

	/* jshint ignore:end */

	/*
	Our main function declaration.
	*/
	$.adaptiveBackground = {
		run: function (options) {
			var opts = $.extend({}, DEFAULTS, options);

			//$(opts.selector).each(function (index, el) {
				var $this = $('img.coverart');

				/*  Small helper functions which applies
				    colors, attrs, triggers events, etc.
				*/
				var handleColors = function () {
					var img = $this[0];

					RGBaster.colors(img, {
						paletteSize: 20,
						exclude: opts.exclude,
						success: function (colors) {
							//$this.attr(DATA_COLOR, colors.dominant);
							$this.trigger(EVENT_CF, {
								color: colors.dominant,
								palette: colors.palette
							});
						}
					});
				};

				// Helper function to calculate yiq - http://en.wikipedia.org/wiki/YIQ
				var getYIQ = function (color) {
					var rgb = color.match(/\d+/g);
					return parseInt(((rgb[0] * 299) + (rgb[1] * 587) + (rgb[2] * 114)) / 1000);
				};

				/* Subscribe to our color-found event. */
				$this.on(EVENT_CF, function (ev, data) {
					// stash adaptive bg color
					shadeOp(data.color);
					var getNormalizedTextColor = function (color) {
						return getYIQ(color) >= 128 ? opts.normalizedTextColors.dark : opts.normalizedTextColors.light;
					};

					// Normalize the text color based on luminance.
					if (opts.normalizeTextColor) {
						//$parent.css({color: getNormalizedTextColor(data.color)});
						// fix menu colors
						adaptMcolor = getNormalizedTextColor(data.color);
						adaptColor = adaptMcolor;
						if (SESSION.json['adaptive'] == 'Yes') {
							//document.body.style.setProperty('--adaptbg', newbg);
							setColors();
						}
					}
					// Add a class based on luminance.
					//$parent.addClass(getLumaClass(data.color))
					//.attr('data-ab-yaq', getYIQ(data.color));
					opts.success && opts.success($this, data);
				});

				/* Handle the colors. */
				handleColors();
				//});
		}
	};
})
(jQuery);
