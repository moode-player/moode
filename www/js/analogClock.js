"use strict";

// An Analog clock entirely in JavaScript (no images, just html tags).
// Can be configured to have the seconds-hand move at 1 second (default)
// or 1/20th of a second interval, or have the seconds-hand not shown
// at all.
// 
// (C) 2022 @Nutul (albertonarduzzi@gmail.com)
//
// This Program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 3, or (at your option)
// any later version.
//
// This Program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

const ANALOGCLOCK_REFRESH_INTERVAL_NORMAL = 1000;
const ANALOGCLOCK_REFRESH_INTERVAL_SMOOTH = 50;

class AnalogClock {

    constructor(aContainerId, aInterval = ANALOGCLOCK_REFRESH_INTERVAL_NORMAL, aShowSeconds = true) {
        this.timer = null;
        this.hands = { hours: null, minutes: null, seconds: null };
        this.lastDateTime = null;
        this.showSeconds = aShowSeconds;
        // if not showing the seconds, no need for smooth seconds-hand
        this.refreshInterval = this.showSeconds ? aInterval : ANALOGCLOCK_REFRESH_INTERVAL_NORMAL;
        this.case = document.getElementById(aContainerId);
        // create the clock
        this.clock = this.case.appendChild(this.newElement("div", aContainerId + "_case", [ "analogclock" ]));
        if (this.clock) {
            // create the faceplate
            this.faceplate = this.clock.appendChild(this.newElement("div", aContainerId + "_face", ["analogclock_face"]));
            var radius = this.faceplate.clientWidth / 2;
            for (var m = 0; m < 60; m++) {
                var angle = m * 6 - 90;
                var tickclass = m % 5 ? "analogclock_mtick" : "analogclock_htick";
                var tick = this.faceplate.appendChild(this.newElement("div", "hm_" + m, ["analogclock_tick", tickclass]));
                tick.style.top = radius + (radius - 6) * Math.sin(angle * Math.PI / 180) + "px";
                tick.style.left = radius + (radius - 6) * Math.cos(angle * Math.PI / 180) - (tick.clientWidth / 2) + "px";
                tick.style.transform = `rotate(${angle + 90}deg)`;
            }
            // create the hands
            this.hands.h = this.faceplate.appendChild(this.newElement("div", aContainerId + "_hh", [ "analogclock_hand", "analogclock_hh" ]));
            this.hands.m = this.faceplate.appendChild(this.newElement("div", aContainerId + "_mm", [ "analogclock_hand", "analogclock_mm" ]));
            if (this.showSeconds) {
                this.hands.s = this.faceplate.appendChild(this.newElement("div", aContainerId + "_ss", [ "analogclock_hand", "analogclock_ss" ]));
                this.hands.s.appendChild(this.newElement("div", aContainerId + "_ss_tip", [ "analogclock_hand", "analogclock_ss_tip" ]));
            }
        }
    }

    newElement(aTag, aId, aClassSet) {
        var aElement = document.createElement(aTag);
        aElement.id = aId;
        for (var aClass of aClassSet) {
            aElement.classList.add(aClass);
        }
    
        return aElement;
    }

    draw() {
        this.lastDateTime = new Date();
        let h = this.lastDateTime.getHours() % 12;
        let m = this.lastDateTime.getMinutes();
        let s = this.lastDateTime.getSeconds();

        /// calculate the angles of the hands...
        h = h * 30 + Math.trunc(m / 2);
        m = m * 6 + Math.trunc(s / 10);
        s = s * 6;

        if (this.refreshInterval != ANALOGCLOCK_REFRESH_INTERVAL_NORMAL)
        {
            s += Math.trunc(this.lastDateTime.getMilliseconds() / 50) * 0.3;
        }

        this.hands.h.style.transform = `rotate(${h}deg)`;
        this.hands.m.style.transform = `rotate(${m}deg)`;
        if (this.showSeconds) {
            this.hands.s.style.transform = `rotate(${s}deg)`;
        }
    }

    start() {
        this.draw();
        this.timer = window.setInterval(this.draw.bind(this), this.refreshInterval);
    }

    stop() {
        window.clearInterval(this.timer);
        this.timer = null;
    }

    destroy() {
        this.stop();
        this.case.removeChild(this.clock);
    }
}
