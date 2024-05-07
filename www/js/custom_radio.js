/*!
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/
var toggleHandler = function(toggle) {
    var toggle = toggle;
    var radio = $(toggle).find("input");

    var checkToggleState = function() {
        if (radio.eq(0).is(":checked")) {
            $(toggle).removeClass("toggle-off");
        } else {
            $(toggle).addClass("toggle-off");
        }
    };

    checkToggleState();

    radio.eq(0).click(function() {
        $(toggle).toggleClass("toggle-off");
    });

    radio.eq(1).click(function() {
        $(toggle).toggleClass("toggle-off");
    });
};

$(document).ready(function() {
    $(".toggle").each(function(index, toggle) {
        toggleHandler(toggle);
    });
});
