/*!
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

var htmlEntityMap = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
  '/': '&#x2F;',
  '`': '&#x60;',
  '=': '&#x3D;'
};

function encodeHTMLEntities(str) {
    return String(str).replace(/[&<>"'`=\/]/g, function (s) {
        return htmlEntityMap[s];
    });
}
function decodeHTMLEntities(str) {
    var element = document.createElement('div');

    element.innerHTML = str;
    str = element.textContent;
    element.textContent = '';

    return str;
}
