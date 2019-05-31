/*!
 * Lazy Load - jQuery plugin for lazy loading images
 *
 * Copyright (c) 2007-2015 Mika Tuupola
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Project home:
 *   http://www.appelsiini.net/projects/lazyload
 *
 * Version:  1.9.7
 *
 * 2019-05-30 TC moOde 5.3
 *
 */

(function($, window, document, undefined) {
    var $window = $(window);

    $.fn.lazyload = function(options) {
        var elements = this;
        var $container;
        var settings = {
            //threshold       : 0,
            threshold       : 200, // was 0
            failure_limit   : 0,
            event           : "scroll.lazyload",
            effect          : "show",
            container       : window,
            data_attribute  : "original",
            data_srcset     : "srcset",
            skip_invisible  : false,
            appear          : null,
            load            : null,
			// r43k gray square			
			placeholder     : "data:image/gif;base64,R0lGODdhAQABAPAAAMPDwwAAACwAAAAAAQABAAACAkQBADs="
			// r43k moOde png 
			//placeholder		: 'data:image/png;base64,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'
        };

        function update() {
            var counter = 0;

            elements.each(function() {
                var $this = $(this);
                if (settings.skip_invisible && !$this.is(":visible")) {
                    return;
                }
                if ($.abovethetop(this, settings) ||
                    $.leftofbegin(this, settings)) {
                        /* Nothing. */
                } else if (!$.belowthefold(this, settings) &&
                    !$.rightoffold(this, settings)) {
                        $this.trigger("appear");
                        /* if we found an image we'll load, reset the counter */
                        counter = 0;
                } else {
                    if (++counter > settings.failure_limit) {
                        return false;
                    }
                }
            });

        }

        if(options) {
            /* Maintain BC for a couple of versions. */
            if (undefined !== options.failurelimit) {
                options.failure_limit = options.failurelimit;
                delete options.failurelimit;
            }
            if (undefined !== options.effectspeed) {
                options.effect_speed = options.effectspeed;
                delete options.effectspeed;
            }

            $.extend(settings, options);
        }

        /* Cache container as jQuery as object. */
        $container = (settings.container === undefined ||
                      settings.container === window) ? $window : $(settings.container);

        /* Fire one scroll event per scroll. Not one scroll event per image. */
        if (0 === settings.event.indexOf("scroll")) {
            $container.off(settings.event).on(settings.event, function() {
                return update();
            });
        }

        this.each(function() {
            var self = this;
            var $self = $(self);

            self.loaded = false;

            /* If no src attribute given use data:uri. */
            if ($self.attr("src") === undefined || $self.attr("src") === false) {
                if ($self.is("img")) {
                    $self.attr("src", settings.placeholder);
                }
            }

            /* When appear is triggered load original image. */
            $self.one("appear", function() {
                if (!this.loaded) {
                    if (settings.appear) {
                        var elements_left = elements.length;
                        settings.appear.call(self, elements_left, settings);
                    }
                    $("<img />")
						.one("error", function() { // r43k
							$self.attr("src", "images/default-cover-v6.svg");
						})
                        .one("load", function() {
                            var original = $self.attr("data-" + settings.data_attribute);
                            var srcset = $self.attr("data-" + settings.data_srcset);

                            if (original != $self.attr("src")) {
                                $self.hide();
                                if ($self.is("img")) {
                                    $self.attr("src", original);
                                    if (srcset != null) {
                                        $self.attr("srcset", srcset);
                                    }
                                }
								/*if ($self.is("video")) { // not needed
                                    $self.attr("poster", original);
                                } else {
                                    $self.css("background-image", "url('" + original + "')");
                                }*/
                                $self[settings.effect](settings.effect_speed);
                            }

                            self.loaded = true;

                            /* Remove image from array so it is not looped next time. */
                            var temp = $.grep(elements, function(element) {
                                return !element.loaded;
                            });
                            elements = $(temp);

                            if (settings.load) {
                                var elements_left = elements.length;
                                settings.load.call(self, elements_left, settings);
                            }
                        })
                        .attr({
                            "src": $self.attr("data-" + settings.data_attribute),
                            "srcset": $self.attr("data-" + settings.data_srcset) || ""
                        });
                }
            });

            /* When wanted event is triggered load original image */
            /* by triggering appear.                              */
            if (0 !== settings.event.indexOf("scroll")) {
                $self.off(settings.event).on(settings.event, function() {
                    if (!self.loaded) {
                        $self.trigger("appear");
                    }
                });
            }
        });

        /* Check if something appears when window is resized. */
        $window.off("resize.lazyload").bind("resize.lazyload", function() {
            update();
        });

        /* With IOS5 force loading images when navigating with back button. */
        /* Non optimal workaround. */
        if ((/(?:iphone|ipod|ipad).*os 5/gi).test(navigator.appVersion)) {
            $window.on("pageshow", function(event) {
                if (event.originalEvent && event.originalEvent.persisted) {
                    elements.each(function() {
                        $(this).trigger("appear");
                    });
                }
            });
        }

        /* Force initial check if images should appear. */
        $(function() {
            update();
        });

        return this;
    };

    /* Convenience methods in jQuery namespace.           */
    /* Use as  $.belowthefold(element, {threshold : 100, container : window}) */

    $.belowthefold = function(element, settings) {
        var fold;

        if (settings.container === undefined || settings.container === window) {
            fold = (window.innerHeight ? window.innerHeight : $window.height()) + $window.scrollTop();
        } else {
            fold = $(settings.container).offset().top + $(settings.container).height();
        }

        return fold <= $(element).offset().top - settings.threshold;
    };

    $.rightoffold = function(element, settings) {
        var fold;

        if (settings.container === undefined || settings.container === window) {
            fold = $window.width() + $window.scrollLeft();
        } else {
            fold = $(settings.container).offset().left + $(settings.container).width();
        }

        return fold <= $(element).offset().left - settings.threshold;
    };

    $.abovethetop = function(element, settings) {
        var fold;

        if (settings.container === undefined || settings.container === window) {
            fold = $window.scrollTop();
        } else {
            fold = $(settings.container).offset().top;
        }

        return fold >= $(element).offset().top + settings.threshold  + $(element).height();
    };

    $.leftofbegin = function(element, settings) {
        var fold;

        if (settings.container === undefined || settings.container === window) {
            fold = $window.scrollLeft();
        } else {
            fold = $(settings.container).offset().left;
        }

        return fold >= $(element).offset().left + settings.threshold + $(element).width();
    };

    $.inviewport = function(element, settings) {
         return !$.rightoffold(element, settings) && !$.leftofbegin(element, settings) &&
                !$.belowthefold(element, settings) && !$.abovethetop(element, settings);
     };

    /* Custom selectors for your convenience.   */
    /* Use as $("img:below-the-fold").something() or */
    /* $("img").filter(":below-the-fold").something() which is faster */

    $.extend($.expr[":"], {
        "below-the-fold" : function(a) { return $.belowthefold(a, {threshold : 0}); },
        "above-the-top"  : function(a) { return !$.belowthefold(a, {threshold : 0}); },
        "right-of-screen": function(a) { return $.rightoffold(a, {threshold : 0}); },
        "left-of-screen" : function(a) { return !$.rightoffold(a, {threshold : 0}); },
        "in-viewport"    : function(a) { return $.inviewport(a, {threshold : 0}); },
        /* Maintain BC for couple of versions. */
        "above-the-fold" : function(a) { return !$.belowthefold(a, {threshold : 0}); },
        "right-of-fold"  : function(a) { return $.rightoffold(a, {threshold : 0}); },
        "left-of-fold"   : function(a) { return !$.rightoffold(a, {threshold : 0}); }
    });

})(jQuery, window, document);
