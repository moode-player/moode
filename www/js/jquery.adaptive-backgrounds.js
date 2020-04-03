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
		shadeVariation: 'blend',
		shadePercentage: .15,
		shadeColors: {light: 'rgb(128,128,128)', dark: 'rgb(224,224,224)'},
		normalizeTextColor: true,
		normalizedTextColors: {light: "#eee", dark: "#333"},
		lumaClasses: {light: "ab-light", dark: "ab-dark"},
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
				for (var i = n.width * n.height || e.length, m = {}, s = "", d = [], f = {
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
					l += 4 * r
				}
				if (t.success) {
					var p = o(f.palette);
					//console.log(a(f.dominant.name) + ' - ' + p);
					if (p[1] == 'rgb(0,0,0)') {p[0] = a(f.dominant.name);}
					if (p[0] == 'rgb(255,255,255)') {p[0] = p[1]}
					var aa = rgbToHsl(p[0]);
					var bb = rgbToHsl(p[1]);
					var cc = rgbToHsl(a(f.dominant.name));
					//console.log(aa[2]);
					//console.log(bb[2]);
					//console.log(cc[2]);
					var a1 = 0;
					var a2 = 0;
					if (aa[2] > bb[2]) {a1 = 0; a2 = 1;}
					else {a1 = 1; a2 = 0;}
					if ((cc[2] + .20) > aa[2] && (cc[2] < '.98')) {p[0] = a(f.dominant.name);a1=0;a2=1;}
					if (a(f.dominant.name) == 'rgb(255,255,255)' && (p[1] == 'rgb(0,0,0)')) {p[0] = a(f.dominant.name);a1=0;a2=1;}
					//console.log(a1, a2, p[a1] + ', ' + p[a2]);
					if (cc[2] > .98) {a1=0;a2=1;}
					//console.log(a(f.dominant.name) + p);
					t.success({
						dominant: p[a1],
						secondary: p[a2],
						palette: p
					})
				}
			})
		},
		n.RGBaster = n.RGBaster || c
	}

	(window);

	function shadeRGBColor(color, percent) {
		var f=color.split(","),t=percent<0?0:255,p=percent<0?percent*-1:percent,R=parseInt(f[0].slice(4)),G=parseInt(f[1]),B=parseInt(f[2]);return "rgb("+(Math.round((t-R)*p)+R)+","+(Math.round((t-G)*p)+G)+","+(Math.round((t-B)*p)+B)+")";
	}

	function blendRGBColors(c0, c1, p) {
		var f=c0.split(","),t=c1.split(","),R=parseInt(f[0].slice(4)),G=parseInt(f[1]),B=parseInt(f[2]);return "rgb("+(Math.round((parseInt(t[0].slice(4))-R)*p)+R)+","+(Math.round((parseInt(t[1])-G)*p)+G)+","+(Math.round((parseInt(t[2])-B)*p)+B)+")";
	}
	/* jshint ignore:end */

	/*
	Our main function declaration.
	*/
	$.adaptiveBackground = {
		run: function (options) {
			var opts = $.extend({}, DEFAULTS, options);
			abfound = 'false';

			/* Loop over each element, waiting for it to load
			then finding its color, and triggering the
			color found event when color has been found.
			*/
			$(opts.selector).each(function (index, el) {
				var $this = $(this);
				/*  Small helper functions which applies
				    colors, attrs, triggers events, etc.
				*/
				var handleColors = function () {
					if ($this[0].tagName == 'PICTURE') {
						var images = $this[0].children;
						for (var image in images) {
							if (images[image].tagName == 'IMG') {
								var img = images[image];
								break;
							}
						};
						if (img.currentSrc) {
							img = img.currentSrc;
						};
					} else {
						var img = useCSSBackground() ? getCSSBackground() : $this[0];
					}

					RGBaster.colors(img, {
						paletteSize: 20,
						exclude: opts.exclude,
						success: function (colors) {
							$this.attr(DATA_COLOR, colors.dominant);
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

				var useCSSBackground = function () {
					var attr = $this.attr(DATA_CSS_BG);
					return (typeof attr !== typeof undefined && attr !== false);
				};

				var getCSSBackground = function () {
					var str = $this.css('background-image');
					var regex = /\(([^)]+)\)/;
					var match = regex.exec(str)[1].replace(/"/g, '')
					return match;
				};

				var getShadeAdjustment = function (color) {
					//console.log('datacolor' + color);
					if (color == 'rgb()'){color = 'rgb(0,0,0)';}
					if (opts.shadeVariation == true) {
						return getYIQ(color) <= 128 ? shadeRGBColor(color, opts.shadePercentage) : shadeRGBColor(color, -(opts.shadePercentage), opts.shadePercentage);
					} else if (opts.shadeVariation == BLEND) {
						return getYIQ(color) >= 128 ? blendRGBColors(color, opts.shadeColors.dark, opts.shadePercentage) : blendRGBColors(color, opts.shadeColors.light, opts.shadePercentage);
					}
				};

				/* Subscribe to our color-found event. */
				$this.on(EVENT_CF, function (ev, data) {
					// Try to find the parent.
					var $parent;
					if (opts.parent && $this.parents(opts.parent).length) {
						$parent = $this.parents(opts.parent);
					} else if ($this.attr(DATA_PARENT) && $this.parents($this.attr(DATA_PARENT)).length) {
						$parent = $this.parents($this.attr(DATA_PARENT));
					} else if (useCSSBackground()) {
						$parent = $this;
					} else if (opts.parent) {
						$parent = $this.parents(opts.parent);
					} else {
						$parent = $this.parent();
					}

					if (!!opts.shadeVariation)
						data.color = getShadeAdjustment(data.color);

					if ($.isNumeric(opts.transparent) && opts.transparent != null && opts.transparent >= 0.01 && opts.transparent <= 0.99) {
						var dominantColor = data.color;
						var rgbToRgba = dominantColor.replace("rgb", "rgba");
						var transparentColor = rgbToRgba.replace(")", ", " + opts.transparent + ")");
						$parent.css({
							backgroundColor: transparentColor
						});
					} //else {
						//$parent.css({
							//backgroundColor: data.color
							//});
					//}

					// stash adaptive bg color
					//console.log(data.color);
					var shade = rgbToHsl(data.color);
					var shade2 = shade[2];
					//console.log(shade[2]);
					var newbg = data.color;
					if (shade2 < .10) {
						shade[2] = shade2 + 0.03;
					} else if (shade2 < .45 && shade2 > .35) {
						shade[2] = shade2 - .05;
					}
					//console.log(shade[2]);
					var newshade = hslToRgb(shade);
					var newbg = 'rgba(';
					var newmb = 'rgba(';
					abFound = 'true';
					newbg = newbg.concat(newshade[0],',',newshade[1],',',newshade[2],',' + SESSION.json['alphablend'] +')');
					newmb = newmb.concat(newshade[0],',',newshade[1],',',newshade[2],',' + themeOp +')');
				    //$parent.css({backgroundColor: newbg});
					data.color = newbg;
					adaptBack = newbg;
					//console.log(adaptBack);
					var newshade = hslToRgb(shade);
					var newcolor = 'rgba(';
					newcolor = newcolor.concat(newshade[0],',',newshade[1],',',newshade[2],',',themeOp,')');
					adaptMback = newcolor;
					adaptMhalf = 'rgba('.concat(newshade[0],',',newshade[1],',',newshade[2],',0.5)');
					var getNormalizedTextColor = function (color) {
						return getYIQ(color) >= 128 ? opts.normalizedTextColors.dark : opts.normalizedTextColors.light;
					};

					var getLumaClass = function (color) {
						return getYIQ(color) <= 128 ? opts.lumaClasses.dark : opts.lumaClasses.light;
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
			});
		}
	};
})
(jQuery);
