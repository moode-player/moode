/*!
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

// NOTE: This file exists because "for of" statements fail YUV compressor minification

// 2022-08-07
// TODO: Gulp minification works for this file so it should be merged into one of the other scripts, probably during refactoring

function formatExtraTagsString () {
    //var elementDisplay = '';
    var output = '';
    var extraTags = SESSION.json['extra_tags'].replace(/ /g, '').split(','); // Strip out whitespace

    // NOTE: composer may be null, disc may be 'Disc tag missing', encoded may be 'Unknown'
    for (const tag of extraTags) {
        //console.log(tag, MPD.json[tag]);
        if (MPD.json[tag] != null && MPD.json[tag] != 'Disc tag missing' && MPD.json[tag] != 'Unknown' && MPD.json[tag] != '') {
            var displayedTag = tag == 'track' ? 'Track' : (tag == 'disc' ? 'Disc' : '');
            output += displayedTag + ' ' + MPD.json[tag] + ' • ';
        }
    }

    output = output.slice(0, -3); // Strip trailing bullet
    return output;
}

// Delete station from RADIO.json object array
function deleteRadioStationObject (stationName) {
    for (let [key, value] of Object.entries(RADIO.json)) {
        if (value.name == stationName) {
            delete RADIO.json[key];
        }
    }
}

// Return key or value from included map tables
// NOTE: This works only if all keys and values are unique
function getKeyOrValue (type, item) {
    let mapTable = new Map([
        // Screen saver timeout
        ['Never','Never'],['1 minute','60'],['2 minutes','120'],['5 minutes','300'],['10 minutes','600'],['20 minutes','1200'],['30 minutes','1800'],['1 hour','3600'],
        // Library recently added
        ['1 Week','604800000'],['1 Month','2592000000'],['3 Months','7776000000'],['6 Months','15552000000'],['1 Year','31536000000'],['No limit','3153600000000'],
        // Library cover search priority
        ['Embedded','Embedded cover'],['Cover file','Cover image file'],
        // Font size factors
        ['Smaller',.35],['Small',.40],['Normal',.45],['Large',.55],['Larger',.65],['X-Large',.75],
        // Sample rate display options
        ['No (searchable)',0],['HD only',1],['Text',2],['Badge',3],['No',9],
        // Radioview station types
        ['Regular','r'],['Favorite','f'],['Hidden','h'],
        // Thumbnail resolutions
        ['Auto','Auto'],['400px','400px,75'],['500px','500px,60'],['600px','600px,60'],
        // Players >> group actions
        ['Shutdown','poweroff'],['Restart','reboot'],['Update library','update_library'],
        // Root folder icons
        ['NAS','fa-server'],['RADIO','fa-microphone'],['SDCARD','fa-sd-card'],['USB','fa-usb-drive'],
        // Now-playing icon
        ['None','None'],['Waveform','waveform'],['Equalizer (Animated)','equalizer'],
        // View -> Item position
        ['radio','radio_pos'],['folder','folder_pos'],['tag','lib_pos'],['album','lib_pos'],['playlist','playlist_pos']
    ]);

    if (type == 'value') {
        var result = mapTable.get(item);
    } else if (type == 'key') {
        for (let [key, value] of mapTable) {
            if (value == item) {
                var result = key;
                break;
            }
        }
    }

    return result;
}
