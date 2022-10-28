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

var analogClockInstance = null;

class AnalogClock {

    constructor(aContainerId, aInterval = ANALOGCLOCK_REFRESH_INTERVAL_NORMAL, aShowSeconds = true) {
        this.timer = null;
        this.hands = { hours: null, minutes: null, seconds: null };
        this.lastDateTime = null;
        this.showSeconds = aShowSeconds;
        this.drawing = false;
        this.lastAngles = { hh: 1000, mm: 1000, ss: 1000 };
        // if not showing the seconds, no need for smooth seconds-hand
        this.refreshInterval = this.showSeconds ? aInterval : ANALOGCLOCK_REFRESH_INTERVAL_NORMAL;
        this.case = document.getElementById(aContainerId);
        // create the clock
        this.clock = this.case.appendChild(this.newElement("div", aContainerId + "_case", [ "analogclock" ]));
        if (this.clock) {
            // create the faceplate
            this.faceplate = this.clock.appendChild(this.newElement("div", aContainerId + "_face", ["analogclock_face"]));
            var faceRadius = this.faceplate.clientWidth / 2;
            var ticksRadius = faceRadius / 8 * 7;
            var tick = null;
            for (var m = 0; m < 60; m += 5) {
                var angle = m * 6 - 90;
                var hourNumber = m / 5 == 0 ? 12 : m / 5;
                if (m % 15 == 0) {
                    tick = this.faceplate.appendChild(this.newElement("div", "hm_" + m, ["analogclock_hnumber", "hnum_" + hourNumber]));
                    tick.innerText = hourNumber;
                } else {
                    tick = this.faceplate.appendChild(this.newElement("div", "hm_" + m, ["analogclock_tick"]));
                    tick.style.transform = `rotate(${angle + 90}deg)`;
                }
                tick.style.top = faceRadius + (ticksRadius - 6) * Math.sin(angle * Math.PI / 180) + "px";
                tick.style.left = faceRadius + (ticksRadius - 6) * Math.cos(angle * Math.PI / 180) - (tick.clientWidth / 2) + "px";
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
        if (this.drawing) {
            return;
        }
        this.drawing = true;
        this.lastDateTime = new Date();
        let h = this.lastDateTime.getHours() % 12;
        let m = this.lastDateTime.getMinutes();
        let s = this.lastDateTime.getSeconds();

        /// calculate the angles of the hands...
        h = h * 30 + Math.trunc(m / 2);
        m = m * 6 + Math.trunc(s / 10);
        s = s * 6;

        if (this.showSeconds && this.refreshInterval != ANALOGCLOCK_REFRESH_INTERVAL_NORMAL)
        {
            s += Math.trunc(this.lastDateTime.getMilliseconds() / 50) * 0.3;
        }

        if (this.lastAngles.hh != h) {
            this.hands.h.style.transform = `translateX(-50%) rotate(${h}deg)`;
            this.lastAngles.hh = h;
        }
        if (this.lastAngles.mm != m) {
            this.hands.m.style.transform = `translateX(-50%) rotate(${m}deg)`;
            this.lastAngles.mm = m;
        }
        if (this.showSeconds && this.lastAngles.ss != s) {
            this.hands.s.style.transform = `translateX(-50%) rotate(${s}deg)`;
            this.lastAngles.ss = s;
        }
        this.drawing = false;
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

function showAnalogClock(aContainer, aInterval, aHasSeconds) {
    if (!analogClockInstance) {
        analogClockInstance = new AnalogClock(aContainer, aInterval, aHasSeconds);
        analogClockInstance.start();
    }

}

function hideAnalogClock() {
    if (analogClockInstance) {
        analogClockInstance.destroy();
        analogClockInstance = null;
    }
}
