/*!
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
 * 2020-07-09 TC moOde 6.6.0
 *
 */

// NOTE: This file exists because "for of" statements fail YUV compressor minification

function formatExtraTagsString () {
    var elementDisplay, extraTagsDisplay = '';
    var extraTagsArray = SESSION.json['extra_tags'].replace(/ /g, '').split(','); // Strip out whitespace

    //NOTE: composer may be = null, disc may be = 'Disc tag missing', encoded may be = 'Unknown'
    for (const element of extraTagsArray) {
        //console.log(element, MPD.json[element]);
        if (MPD.json[element] != null && MPD.json[element] != 'Disc tag missing' && MPD.json[element] != 'Unknown') {
            elementDisplay = element == 'track' ? 'Track' : (element == 'disc' ? 'Disc' : '');
            extraTagsDisplay += ((elementDisplay == '' ? '' : elementDisplay + ' ') + MPD.json[element] + ' • ');
        }
    }

    return extraTagsDisplay.slice(0, -3); // Strip trailing bullet
}

// Delete station from RADIO.json object array
function deleteRadioStationObject (station_name) {
    for (let [key, value] of Object.entries(RADIO.json)) {
        if (value.name == station_name) {
            delete RADIO.json[key];
        }
    }
}

// Return param or value from included map tables
function getParamOrValue (type, key) {
    let mapTable = new Map([
        // Screen saver timeout
        ['Never','Never'],['1 minute','60'],['2 minutes','120'],['5 minutes','300'],['10 minutes','600'],['20 minutes','1200'],['30 minutes','1800'],['1 hour','3600'],
        // Library recently added
        ['1 Week','604800000'],['1 Month','2592000000'],['3 Months','7776000000'],['6 Months','15552000000'],['1 Year','31536000000'],
        // Library cover search priority
        ['Embedded','Embedded cover'],['Cover file','Cover image file'],
        // Font size factors
        ['Smaller',.35],['Small',.40],['Normal',.45],['Large',.55],['Larger',.65],['X-Large',.75],
        // Sample rate display options
        ['No (searchable)',0],['HD only',1],['Text',2],['Badge',3],['No',9]
    ]);

    if (type == 'value') {
        var result = mapTable.get(key);
    }
    else if (type == 'param') {
        for (let [param, value] of mapTable) {
            if (value == key) {
                var result = param;
                break;
            }
        }
    }

    return result;
}
