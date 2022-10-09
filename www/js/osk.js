"use strict";

// On-screen keyboard for use on devices whose OS does not provide one,
// or the installation of one would require too many resources, such as
// moOde, for which it was developed.
// It automatically installs itself if it is being run under X11, in Chrome
// for Linux for ARM architecture.
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

// FontAwesome icons for some special keys
const KS_BACKS     = '&#xf177;';
const KS_SHIFT     = '&#xf062;';
const KS_ENTER     = '&#xf3be;';

const KS_INC       = '&#xf077;';
const KS_DEC       = '&#xf078;';
const KS_MAX       = '&#xf325;';
const KS_MIN       = '&#xf322;';

const LOS_LATIN    = 'abc';
const LOS_ACCENT   = 'âèö';
const LOS_CYRIL    = 'ябш';
const LON_LATIN    =  0;
const LON_ACCENT   =  1;
const LON_CYRIL    =  2;
const LON_NUMBER   =  3;
const MAX_ROWS     =  4;
const MAX_COLUMNS  = 11;
const SM_LOWERCASE =  0;
const SM_UPPERCASE =  1;

var OSK = {
    LAYOUTS: [
        [ // LON_LATIN
            [ [ '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', KS_BACKS ], // LOWERCASE
              [ 'q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p', '[' ],
              [ 'a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l', ';', '\''],
              [ 'z', 'x', 'c', 'v', 'b', 'n', 'm', ',', '.', '/', '\\'] ],
            [ [ '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', KS_BACKS ], // UPPERCASE
              [ 'Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P', ']' ],
              [ 'A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', ':', '"' ],
              [ 'Z', 'X', 'C', 'V', 'B', 'N', 'M', '<', '>', '?', '|' ] ] ],
        [ // LON_ACCENT
            [ [ '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', KS_BACKS ], // LOWERCASE
              [ 'à', 'á', 'â', 'ä',  '', 'è', 'é', 'ë', 'ê',  '',  '' ],
              [ 'ì', 'í', 'î', 'ï',  '', 'ò', 'ó', 'ô', 'ö',  '',  '' ],
              [ 'ù', 'ú', 'û', 'ü',  '', 'ñ', 'ç', 'ß', 'ć',  '',  '' ] ],
            [ [ '{', '}', '¡', '¿', '€', '£', '-', '+', '_', '=', KS_BACKS ], // UPPERCASE
              [ 'À', 'Á', 'Â', 'Ä',  '', 'È', 'É', 'Ê', 'Ë',  '',  '' ],
              [ 'Ì', 'Í', 'Î', 'Ï',  '', 'Ò', 'Ó', 'Ô', 'Ö',  '',  '' ],
              [ 'Ù', 'Ú', 'Û', 'Ü',  '', 'Ñ', 'Ç', '§', 'Ć',  '',  '' ] ] ],
        [ // LON_CYRIL
            [ [ '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', KS_BACKS ], // LOWERCASE
              [ 'я', 'в', 'е', 'р', 'т', 'ъ', 'у', 'и', 'о', 'п', 'ш' ],
              [ 'а', 'с', 'д', 'ф', 'г', 'х', 'й', 'к', 'л', 'ю', 'щ' ],
              [ 'з', 'ь', 'ц', 'ж', 'б', 'н', 'м', 'ч', 'ѝ', 'э', 'ы' ] ],
            [ [  '',  '',  '',  '',  '',  '',  '',  '',  '', '№', KS_BACKS ], // UPPERCASE
              [ 'Я', 'В', 'Е', 'Р', 'Т', 'Ъ', 'У', 'И', 'О', 'П', 'Ш' ],
              [ 'А', 'С', 'Д', 'Ф', 'Г', 'Х', 'Й', 'К', 'Л', 'Ю', 'Щ' ],
              [ 'З', 'Ь', 'Ц', 'Ж', 'Б', 'Н', 'М', 'ч', 'Ѝ', 'Э', 'Ы' ] ] ],
        [ // LON_NUMBER - always leave as last, as this is not selectable... (renumber as needed...)
            [ [ '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', KS_BACKS ], // LOWERCASE
              [  '',  '',  '',  '',  '',  '',  '',  '',  '',  '', ''  ],
              [  '',  '',  '',  '',  '',  '',  '',  '',  '',  KS_MAX, KS_INC ],
              [  '',  '',  '',  '',  '',  '',  '',  '',  '',  KS_MIN, KS_DEC ] ],
            [ [  '',  '',  '',  '',  '',  '',  '',  '',  '',  '', KS_BACKS ], // UPPERCASE
              [  '',  '',  '',  '',  '',  '',  '',  '',  '',  '', ''  ],
              [  '',  '',  '',  '',  '',  '',  '',  '',  '',  '', ''  ],
              [  '',  '',  '',  '',  '',  '',  '',  '',  '',  '', ''  ] ] ],
    ],

    tag: null,
    linkedInput: null,

    keyBackspace: null,
    keySetMax: null,
    keyIncrease: null,
    keySetMin: null,
    keyDecrease: null,
    keyShift: null,
    keyLayout: null,
    keySpace: null,
    keyEnter: null,

    shiftMode: SM_LOWERCASE,
    layoutNdx: LON_LATIN,

    containerToShrink: null,

    initialized: false,
};

function setShiftMode(aShiftMode) {
    OSK.shiftMode = aShiftMode;
    if (SM_UPPERCASE == OSK.shiftMode) {
        OSK.keyShift.classList.add("oskEngaged");
    } else {
        OSK.keyShift.classList.remove("oskEngaged");
    }
    for (var r = 0; r < MAX_ROWS; r++) {
        for (var c = 0; c < MAX_COLUMNS; c++) {
            var key = document.getElementById("osk_" + r + "_" + c);
            key.innerHTML = OSK.LAYOUTS[OSK.layoutNdx][OSK.shiftMode][r][c];
            if (!key.innerHTML) {
                key.classList.add('oskVoid');
            } else {
                key.classList.remove('oskVoid');
            }
        }
    }
}

function setLayout(aLayout, aShiftMode) {
    OSK.layoutNdx = aLayout;
    switch (OSK.layoutNdx) {
        case LON_LATIN: OSK.keyLayout.innerHTML = LOS_ACCENT; break;

        case LON_ACCENT: OSK.keyLayout.innerHTML = LOS_CYRIL; break;

        case LON_CYRIL: OSK.keyLayout.innerHTML = LOS_LATIN; break;

        default: OSK.keyLayout.innerHTML = ""; break;
    }

    setShiftMode(aShiftMode);
}

function setNumberMode(numbersOnly) {
    setLayout(numbersOnly ? LON_NUMBER : LON_LATIN, SM_LOWERCASE);

    if (numbersOnly) {
        OSK.keyShift.classList.add('oskVoid');
        OSK.keyLayout.classList.add('oskVoid');
        OSK.keySpace.classList.add('oskVoid');

        OSK.keyIncrease = document.getElementById("osk_2_10");
        OSK.keyDecrease = document.getElementById("osk_3_10");
        OSK.keySetMax = document.getElementById("osk_2_9");
        OSK.keySetMin = document.getElementById("osk_3_9");    

        OSK.keyIncrease.classList.add("oskInc");
        OSK.keyDecrease.classList.add("oskDec");
        OSK.keySetMax.classList.add("oskMax");
        OSK.keySetMin.classList.add("oskMin");
    } else {
        OSK.keyShift.classList.remove('oskVoid');
        OSK.keyLayout.classList.remove('oskVoid');
        OSK.keySpace.classList.remove('oskVoid');

        if (OSK.keyIncrease) {
            OSK.keyIncrease.classList.remove("oskInc");
            OSK.keyIncrease = null;
        }
        if (OSK.keyDecrease) {
            OSK.keyDecrease.classList.remove("oskDec");
            OSK.keyDecrease = null;
        }
        if (OSK.keySetMax) {
            OSK.keySetMax.classList.remove("oskMax");
            OSK.keySetMax = null;
        }
        if (OSK.keySetMin) {
            OSK.keySetMin.classList.remove("oskMin");
            OSK.keySetMin = null;    
        }
    }
}

function fingerDown() {
    if (!this.classList.contains("oskVoid")) {
        this.classList.add("oskPressed");
    }
}

function fingerUp() {
    var aKey = this;
    window.setTimeout(() => { aKey.classList.remove("oskPressed"); }, 150);
    // this.classList.remove("oskPressed");
}

function injectKeyPress(aKey) {
    var recipient = OSK.linkedInput;
    recipient.dispatchEvent(new KeyboardEvent('keydown', { key: aKey, bubbles: true, cancellable: true }));
    recipient.dispatchEvent(new KeyboardEvent('keyup', { key: aKey, bubbles: true, cancellable: true }));
}

function keyPress(aEvent) {
    aEvent.stopPropagation();
    if (!OSK.linkedInput || this.classList.contains("oskVoid")) {
        return;
    }
    var caretPos = OSK.linkedInput.selectionStart;
    var isNumeric = "number" == OSK.linkedInput.type.toLowerCase();
    var numVal  = 1 * OSK.linkedInput.value;
    var stepVal = 1 * OSK.linkedInput.step || 1;
    var minVal  = 1 * OSK.linkedInput.min;
    var maxVal  = 1 * OSK.linkedInput.max;
    switch (this) {

        case OSK.keyBackspace:
            if (isNumeric) {
                var valueS = "" + OSK.linkedInput.value;
                OSK.linkedInput.value = 1 * valueS.substring(0, valueS.length - 1) || minVal;
            } else {
                OSK.linkedInput.value = OSK.linkedInput.value.substring(0, caretPos - 1) + OSK.linkedInput.value.substring(caretPos, OSK.linkedInput.value.length);
                if (caretPos > 0) {
                    OSK.linkedInput.selectionStart = caretPos - 1;
                    OSK.linkedInput.selectionEnd = caretPos - 1;
                }
            }
            break;

        case OSK.keyEnter:
            injectKeyPress('Enter');
            if (OSK.linkedInput) {
                OSK.linkedInput.blur();
            }
            break;

        case OSK.keyShift:
            setShiftMode(OSK.shiftMode == SM_LOWERCASE ? SM_UPPERCASE : SM_LOWERCASE);
            break;

        case OSK.keyLayout:
            switch (OSK.layoutNdx) {
                case LON_LATIN:  OSK.layoutNdx = LON_ACCENT; break;
                case LON_ACCENT: OSK.layoutNdx = LON_CYRIL;  break;
                case LON_CYRIL:  OSK.layoutNdx = LON_LATIN;  break;
                default:         OSK.layoutNdx = LON_LATIN;  break;
            }
            setLayout(OSK.layoutNdx, OSK.shiftMode);
            break;

        case OSK.keyIncrease:
            numVal = Math.trunc(numVal / stepVal) * stepVal + stepVal;
            OSK.linkedInput.value = numVal > maxVal ? maxVal : numVal;
            break;

        case OSK.keyDecrease:
            numVal = Math.trunc(numVal / stepVal) * stepVal - stepVal;
            OSK.linkedInput.value = numVal < minVal ? minVal : numVal;
            break;
    
        case OSK.keySetMax:
            OSK.linkedInput.value = maxVal;
            break;

        case OSK.keySetMin:
            OSK.linkedInput.value = minVal;
            break;
    
        default:
            if (isNumeric) {
                numVal = 1 * ("" + OSK.linkedInput.value + this.innerHTML);
                if (numVal > maxVal) { numVal = maxVal; }
                if (numVal < minVal) { numVal = minVal; }
                OSK.linkedInput.value = numVal;
            } else {
                OSK.linkedInput.value = OSK.linkedInput.value.substring(0, caretPos) + this.innerHTML + OSK.linkedInput.value.substring(caretPos, OSK.linkedInput.value.length);
                OSK.linkedInput.selectionStart = caretPos + 1;
                OSK.linkedInput.selectionEnd = caretPos + 1;
            }
            break;
    }
}

function addKeyRow(aId) {
    var tagR = OSK.tag.appendChild(document.createElement("div"));
    tagR.id = "osk_" + aId;
    tagR.classList.add("oskRow");
}

function addKey(aIdR, aIdC, classes, text) {
    classes = classes || [];
    classes.push("oskKey");
    var tagR = document.getElementById("osk_" + aIdR);
    var tagK = tagR.appendChild(document.createElement("div"));
    tagK.id = tagR.id + "_" + aIdC;
    tagK.innerHTML = text ? text : "";
    tagK.onmousedown = fingerDown.bind(tagK);
    tagK.onmouseup = fingerUp.bind(tagK);
    tagK.ontouchstart = fingerDown.bind(tagK);
    tagK.ontouchend = fingerUp.bind(tagK);
    tagK.onmouseleave = fingerUp.bind(tagK);
    tagK.onclick = keyPress.bind(tagK);
    for (var c of classes) {
        tagK.classList.add(c);
    }

    return tagK;
}

function acquireContainerToShrink() {
    if (!OSK.containerToShrink) {
        for (var aContainer of document.querySelectorAll(".modal-body, .container2")) {
            if (aContainer.classList.contains("container2")) {
                OSK.containerToShrink = { type: "lonely", tag: aContainer, originalHeight: "", shrunk: false };
                break;
            }
            else if (aContainer.parentElement.classList.contains("in")) {
                OSK.containerToShrink = { type: "nested", tag: aContainer, originalHeight: "", shrunk: false };
                break;
            }
        }
    }
}

function showOSK() {
    acquireContainerToShrink();
    OSK.linkedInput = this;
    if (OSK.linkedInput.tagName && OSK.linkedInput.tagName.toLowerCase() == "input") {
        setNumberMode(OSK.linkedInput.getAttribute("type").toLowerCase() == "number");
    }
    if (OSK.tag) {
        OSK.tag.classList.remove("oskHidden");
    }
}

function hideOSK() {
    OSK.linkedInput = null;
    if (OSK.tag) {
        OSK.tag.classList.add("oskHidden");
    }
}

function shrinkContainer(aContainer) {
    if (aContainer.tag && !aContainer.shrunk) {
        if (aContainer.type == "nested") { // only resize if it has the class "in" AND display:block (there are many, but this is the only one active now)
            var parentTag = aContainer.tag.parentNode;
            if (parentTag.clientHeight > (document.body.clientHeight - OSK.tag.clientHeight)) {
                var shrunkHeight = aContainer.tag.clientHeight - parentTag.clientHeight + document.body.clientHeight - OSK.tag.clientHeight;
                if (shrunkHeight < aContainer.tag.clientHeight) {
                    aContainer.originalHeight = aContainer.tag.style.height;
                    aContainer.tag.style.height = shrunkHeight + "px";
                    aContainer.shrunk = true;
                }
            }
        } else { // whole page dialog: always resize (there is only one)
            aContainer.originalHeight = aContainer.tag.style.height;
            aContainer.tag.style.height = (aContainer.tag.clientHeight + OSK.tag.clientHeight) + "px";
            aContainer.shrunk = true;
        }
    }
}

function restoreContainer(aContainer) {
    if (aContainer.tag && aContainer.shrunk) {
        aContainer.tag.style.height = aContainer.originalHeight;
        aContainer.shrunk = false;
    }
}

function resizeContainer() {
    if (OSK.containerToShrink) {
        if (this.classList.contains("oskHidden")) {
            restoreContainer(OSK.containerToShrink);
            OSK.containerToShrink = null;
        } else {
            shrinkContainer(OSK.containerToShrink);
            if (OSK.linkedInput && OSK.linkedInput.scrollIntoViewIfNeeded) {
                OSK.linkedInput.scrollIntoViewIfNeeded();
            }
        }
    }
}

function initializeOSK() {
    if (OSK.initialized) {
        return;
    }

    // the OSK itself
    OSK.tag = document.body.appendChild(document.createElement("div"));
    OSK.tag.id = "osk";
    OSK.tag.classList.add("oskKeys", "oskHidden");
    OSK.tag.onmousedown = (aEvent) => { aEvent.preventDefault() };
    OSK.tag.onmouseup = (aEvent) => { aEvent.preventDefault() };
    OSK.tag.onclick = (aEvent) => { aEvent.stopPropagation() };
    OSK.tag.addEventListener("transitionend", resizeContainer.bind(OSK.tag), false);
    // generate all the keys
    for (var r = 0; r < MAX_ROWS; r++) {
        addKeyRow(r);
        for (var c = 0; c < MAX_COLUMNS; c++) {
            addKey(r, c);
        }
    }
    for (var c = 0; c < 10; c++) {
        document.getElementById("osk_0_" + c).classList.add("oskNumber");
    }
    OSK.keyBackspace = document.getElementById("osk_0_10");
    OSK.keyBackspace.classList.add("oskSpecial");
    addKeyRow(MAX_ROWS);
        OSK.keyShift  = addKey(MAX_ROWS, 0, ["oskSpecial"], KS_SHIFT );
        OSK.keyLayout = addKey(MAX_ROWS, 1, ["oskSpecial"], LOS_ACCENT );
        OSK.keySpace  = addKey(MAX_ROWS, 2, ["oskSpace"], ' ');
        OSK.keyEnter  = addKey(MAX_ROWS, 3, ["oskSpecial", "oskEnter"], KS_ENTER );

    var allInputs = document.querySelectorAll("input[type='text'], input[type='number'], input[type='password']");
    for (var aInput of allInputs) {
        aInput.onfocus = showOSK.bind(aInput);
        aInput.onblur = hideOSK.bind(aInput);
    }

    OSK.initialized = true;
}

function installOSK() {
    if (navigator.userAgent.indexOf('X11; CrOS armv') != -1 || navigator.userAgent.indexOf('X11; CrOS aarch64') != -1) {
        initializeOSK();
    }
}

installOSK();
