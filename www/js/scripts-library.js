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
 * This is the @chris-rudmin rewrite of the library group/filter routines
 * including modifications to all dependant functions and event handlers.
 * Refer to https://github.com/moode-player/moode/pull/16 for more info.
 *
 */

var LIB = {
    albumClicked: false,
    recentlyAddedClicked: false,
    currentDate: null,
    totalTime: 0,
    totalSongs: 0,
    artistClicked: '',
    filters: {artists: [], genres: [], albums: [], year: []}
};

var allGenres = [];
var allAlbums = [];
var allSongs = [];
var allAlbumCovers = [];

var filteredGenres = [];
var filteredArtists = [];
var filteredAlbums = [];
var filteredSongs = [];
var filteredSongsDisc = [];
var filteredAlbumCovers = [];

// Shim for older Browsers that don't support Object.values
if (!Object.values) {
    Object.defineProperty(Object, 'values', {
        configurable: true,
        enumerable: false,
        value: function (object) {
            return Object.keys(object).map(function (key) {
                return object[key];
            });
        },
        writable: true
    });
}

function loadLibrary() {
    //console.log('loadLibrary(): loading=' + GLOBAL.libLoading, currentView);
    GLOBAL.libLoading = true;
    if (currentView == 'tag' || currentView == 'album') {
        notify('library_loading');
    }

	$.post('command/moode.php?cmd=loadlib', function(data) {
        $('#lib-content').show();
		renderLibrary(data);
        GLOBAL.libRendered = true;
        GLOBAL.libLoading = false;

	}, 'json');
}

function renderLibrary(data) {
	groupLib(data);
	filterLib();

	LIB.totalSongs = allSongs.length;

	renderGenres();
}

function reduceGenres(acc, track) {
	var genre = track.genre.toLowerCase();
	if (!acc[genre]) {
		acc[genre] = [];
		acc[genre].genre = track.genre;
	}
	acc[genre].push(track);
	return acc;
}

function reduceArtists(acc, track) {
	var artist = (track.album_artist || track.artist).toLowerCase();
	if (!acc[artist]) {
		acc[artist] = [];
		acc[artist].artist = track.album_artist || track.artist;
	}
	acc[artist].push(track);
	return acc;
}

function reduceAlbums(acc, track) {
	var key = track.key;
	if (!acc[key]) {
		acc[key] = [];
	}
	acc[key].push(track);
	return acc;
}

function findAlbumProp(albumTracks, prop) {
	var firstTrackWithProp = albumTracks.find(function(track) { return track[prop]; });
	return firstTrackWithProp && firstTrackWithProp[prop];
}

function getLastModified(albumTracks){
	var allLastModified = albumTracks.map(function(track){
		return new Date(track.last_modified);
	});
	return new Date(Math.max.apply(null, allLastModified));
}

function getYear(albumTracks){
	var allYear = albumTracks.map(function(track){
        return track.year;
	});
	return Math.max.apply(null, allYear);
}

function groupLib(fullLib) {
	allSongs = fullLib.map(function(track){
		var modifiedTrack = track;
		modifiedTrack.key = keyAlbum(track);
		return modifiedTrack;
	});

	allGenres = Object.values(allSongs.reduce(reduceGenres, {})).map(function(group){ return group.genre; });
	allGenres.sort();

	allAlbums = Object.values(allSongs.reduce(reduceAlbums, {})).map(function(albumTracks){
		var file = findAlbumProp(albumTracks, 'file');
		var md5 = $.md5(file.substring(0,file.lastIndexOf('/')));
		var artist = findAlbumProp(albumTracks, 'artist');
		var albumArtist = findAlbumProp(albumTracks, 'album_artist');
        //var year = SESSION.json['library_albumview_sort'] == 'Year' ? getYear(albumTracks) : '';
        var year = getYear(albumTracks);
		return {
			last_modified: getLastModified(albumTracks),
            year: year,
			album: findAlbumProp(albumTracks, 'album'),
			genre: findAlbumProp(albumTracks, 'genre'),
			all_genres: Object.keys(albumTracks.reduce(reduceGenres, {})),
			artist: albumArtist || artist,
			imgurl: '/imagesw/thmcache/' + encodeURIComponent(md5) + '.jpg',
            encoded_at: findAlbumProp(albumTracks, 'encoded_at')
		};
	});

	allAlbumCovers = allAlbums.slice();

    // Natural ordering
	try {
		var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
		allSongs.sort(function(a, b) {
			return collator.compare(removeArticles(a['album_artist'] || a['artist']), removeArticles(b['album_artist'] || b['artist']));
		});

        switch (SESSION.json['library_albumview_sort']) {
            case 'Album':
                allAlbumCovers.sort(function(a, b) {
                    return collator.compare(removeArticles(a['album']), removeArticles(b['album']));
                });
                break;
            case 'Artist':
                allAlbumCovers.sort(function(a, b) {
                    return (collator.compare(removeArticles(a['artist']), removeArticles(b['artist'])) || collator.compare(removeArticles(a['album']), removeArticles(b['album'])));
        		});
                break;
            case 'Artist/Year':
                allAlbumCovers.sort(function(a, b) {
                    return (collator.compare(removeArticles(a['artist']), removeArticles(b['artist'])) || collator.compare(a['year'],b['year']));
                });
                break;

            case 'Year':
                allAlbumCovers.sort(function(a, b) {
                    return (collator.compare(a['year'], b['year']) || collator.compare(removeArticles(a['album']), removeArticles(b['album'])));
                });
                break;
        }

        switch (SESSION.json['library_tagview_sort']) {
            case 'Album':
            case 'Album/Year':
                allAlbums.sort(function(a, b) {
                    return collator.compare(removeArticles(a['album']), removeArticles(b['album']));
                });
                break;
            case 'Artist':
                allAlbums.sort(function(a, b) {
    				return (collator.compare(removeArticles(a['artist']), removeArticles(b['artist'])) || collator.compare(removeArticles(a['album']), removeArticles(b['album'])));
    			});
                break;
            case 'Artist/Year':
                allAlbums.sort(function(a, b) {
                    return (collator.compare(removeArticles(a['artist']), removeArticles(b['artist'])) || collator.compare(a['year'],b['year']));
                });
                break;
            case 'Year':
                allAlbums.sort(function(a, b) {
                    return (collator.compare(a['year'], b['year']) || collator.compare(removeArticles(a['album']), removeArticles(b['album'])));
                });
                break;
        }
	}
    // Fallback to default ordering
	catch (e) {
		allSongs.sort(function(a, b) {
			a = removeArticles((a['album_artist'] || a['artist']).toLowerCase());
			b = removeArticles((b['album_artist'] || b['artist']).toLowerCase());
			return a > b ? 1 : (a < b ? -1 : 0);
		});

        switch (SESSION.json['library_albumview_sort']) {
            case 'Album':
                allAlbumCovers.sort(function(a, b) {
                    a = removeArticles(a['album'].toLowerCase());
        			b = removeArticles(b['album'].toLowerCase());
        			return a > b ? 1 : (a < b ? -1 : 0);
        		});
                break;
            case 'Artist':
                allAlbumCovers.sort(function(a, b) {
        			var x1 = removeArticles(a['artist']).toLowerCase(), x2 = removeArticles(b['artist']).toLowerCase();
        			var y1 = removeArticles(a['album']).toLowerCase(), y2 = removeArticles(b['album']).toLowerCase();
        			return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
        		});
                break;
            case 'Artist/Year':
                allAlbumCovers.sort(function(a, b) {
                    var x1 = removeArticles(a['artist']).toLowerCase(), x2 = removeArticles(b['artist']).toLowerCase();
                    var y1 = a['year'], y2 = b['year'];
                    return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
                });
                break;

            case 'Year':
                allAlbumCovers.sort(function(a, b) {
         			var x1 = a['year'], x2 = b['year'];
         			var y1 = removeArticles(a['album']).toLowerCase(), y2 = removeArticles(b['album']).toLowerCase();
         			return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
         		});
                break;
        }

        switch (SESSION.json['library_tagview_sort']) {
            case 'Album':
            case 'Album/Year':
                allAlbums.sort(function(a, b) {
                    a = removeArticles(a['album'].toLowerCase());
        			b = removeArticles(b['album'].toLowerCase());
        			return a > b ? 1 : (a < b ? -1 : 0);
        		});
                break;
            case 'Artist':
                allAlbums.sort(function(a, b) {
        			var x1 = removeArticles(a['artist']).toLowerCase(), x2 = removeArticles(b['artist']).toLowerCase();
        			var y1 = removeArticles(a['album']).toLowerCase(), y2 = removeArticles(b['album']).toLowerCase();
        			return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
        		});
                break;
            case 'Artist/Year':
                allAlbums.sort(function(a, b) {
        			var x1 = removeArticles(a['artist']).toLowerCase(), x2 = removeArticles(b['artist']).toLowerCase();
        			var y1 = a['year'], y2 = b['year'];
        			return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
        		});
                break;
            case 'Year':
                allAlbums.sort(function(a, b) {
        			var x1 = a['year'], x2 = b['year'];
        			var y1 = removeArticles(a['album']).toLowerCase(), y2 = removeArticles(b['album']).toLowerCase();
        			return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
        		});
                break;
        }
	}
}

function filterByGenre(item) {
	var genre = item.genre.toLowerCase();
	return LIB.filters.genres.find(function(genreFilter){
		return genre === genreFilter.toLowerCase();
	});
}

function filterByAllGenres(album) {
	return LIB.filters.genres.find(function(genreFilter){
		return album.all_genres.includes(genreFilter.toLowerCase());
	});
}

function filterByArtist(item) {
	var artist = item.artist.toLowerCase();
	var album_artist = item.album_artist && item.album_artist.toLowerCase();
	return LIB.filters.artists.find(function(artistFilter){
		var artistFilterLower = artistFilter.toLowerCase();
        if (LIB.filters.artists == SESSION.json['library_comp_id']) {
            return artist === artistFilterLower || album_artist === artistFilterLower;
        }
        else if (album_artist != SESSION.json['library_comp_id'].toLowerCase()) {
            return artist === artistFilterLower || album_artist === artistFilterLower;
        }
	});
}

function filterByAlbum(item) {
	return LIB.filters.albums.includes(item.key);
}

function filterAlbumsByDate(item) {
    // NOTE: library_recently_added is in milliseconds, 1 day = 86400000 ms
    return LIB.currentDate.getTime() - item.last_modified.getTime() <= parseInt(SESSION.json['library_recently_added']);
}
function filterSongsByDate(item) {
    itemDateObj = new Date(item.last_modified);
    return LIB.currentDate.getTime() - itemDateObj.getTime() <= parseInt(SESSION.json['library_recently_added']);
}

function filterByYear(item) {
	item.year == LIB.filters.year[0] ? a = true : item.year >= LIB.filters.year[0] && item.year <= LIB.filters.year[1] ? a = true : a = false;
	return a;
}

function filterArtists() {
	// Filter artists by genre
	var songsfilteredByGenre = allSongs;
	if (LIB.filters.genres.length) {
		songsfilteredByGenre = songsfilteredByGenre.filter(filterByGenre);
	}
	filteredArtists = Object.values(songsfilteredByGenre.reduce(reduceArtists, {})).map(function(group){ return group.artist; });
}

function filterAlbums() {
	filteredAlbums = allAlbums;
	filteredAlbumCovers = allAlbumCovers;

	// Filter by genre
	if (LIB.filters.genres.length) {
		filteredAlbums = filteredAlbums.filter(filterByAllGenres);
		filteredAlbumCovers = filteredAlbumCovers.filter(filterByAllGenres);
	}
	// Filter by artist
	if (LIB.filters.artists.length) {
		filteredAlbums = filteredAlbums.filter(filterByArtist);
		filteredAlbumCovers = filteredAlbumCovers.filter(filterByArtist);
	}
    // Filter by file last-updated timestamp
    if (LIB.recentlyAddedClicked) {
        LIB.currentDate = new Date();
        filteredAlbums = filteredAlbums.filter(filterAlbumsByDate);
        filteredAlbumCovers = filteredAlbumCovers.filter(filterAlbumsByDate);

        // Sort descending
        try {
            var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
            filteredAlbums.sort(function(a, b) {
                return collator.compare(b['last_modified'].getTime(), a['last_modified'].getTime());
            });
            filteredAlbumCovers.sort(function(a, b) {
                return collator.compare(b['last_modified'].getTime(), a['last_modified'].getTime());
            });
        }
        catch (e) {
            filteredAlbums.sort(function(a, b) {
                a = a['last_modified'].getTime();
                b = b['last_modified'].getTime();
                return b > a ? 1 : (b < a ? -1 : 0);
            });
            filteredAlbumCovers.sort(function(a, b) {
                a = a['last_modified'].getTime();
                b = b['last_modified'].getTime();
                return b > a ? 1 : (b < a ? -1 : 0);
            });
        }
    }
	// Filter by year(s)
	if (LIB.filters.year.length) {
		filteredAlbums = filteredAlbums.filter(filterByYear);
		filteredAlbumCovers = filteredAlbumCovers.filter(filterByYear);
	}
}

function filterSongs() {
	filteredSongs = allSongs;

	if (LIB.filters.genres.length) {
		filteredSongs = filteredSongs.filter(filterByGenre);
	}
	if (LIB.filters.artists.length) {
		filteredSongs = filteredSongs.filter(filterByArtist);
	}
	if (LIB.filters.albums.length) {
		filteredSongs = filteredSongs.filter(filterByAlbum);
	}
    if (LIB.recentlyAddedClicked) {
        filteredSongs = filteredSongs.filter(filterSongsByDate);
    }
	if (LIB.filters.year.length) {
		filteredSongs = filteredSongs.filter(filterByYear);
	}
}

function filterLib() {
	filteredSongsDisc.length = 0;
	filterArtists();
	filterAlbums();
	filterSongs();
}

// Remove artcles from beginning of string
function removeArticles(string) {
	return SESSION.json['library_ignore_articles'] != 'None' ? string.replace(GLOBAL.regExIgnoreArticles, '$2') : string;
}

// Generate album/artist key
function keyAlbum(obj) {
	return obj.album.toLowerCase() + '@' + (obj.album_artist || obj.artist).toLowerCase();
}

// Return numeric song time
function parseSongTime (songTime) {
	var time = parseInt(songTime);
	return isNaN(time) ? 0 : time;
}

// For the meta area cover art
function makeCoverUrl(filepath) {
	return '/coverart.php/' + encodeURIComponent(filepath);
}

// Default post-click handler for lib items
function clickedLibItem(event, item, currentFilter, renderFunc) {
	if (item == undefined) {
		// all
		currentFilter.length = 0;
	}
	else if (event.ctrlKey) {
		currentIndex = currentFilter.indexOf(item);
		if (currentIndex >= 0) {
			currentFilter.splice(currentIndex, 1);
		}
		else {
			currentFilter.push(item);
		}
	}
	else {
		currentFilter.length = 0;
		currentFilter.push(item);
	}

	filterLib();

    // Special case where we sort album list by year
	if (SESSION.json['library_tagview_sort'] == 'Album/Year' && LIB.artistClicked == true) {
		filteredAlbums.sort(function(a, b) {
		    return parseInt(a.year) - parseInt(b.year);
		});
	}

	renderFunc();
}

var renderGenres = function() {
	var output = '';

	for (var i = 0; i < allGenres.length; i++) {
		output += '<li class="lib-entry'
			+ (LIB.filters.genres.indexOf(allGenres[i]) >= 0 ? ' active' : '')
			+ '">' + allGenres[i] + '</li>';
	}

	$('#genresList').html(output);
	if (UI.libPos[0] == -2) {
		$('#lib-genre').scrollTo(0, 200);
	}

	renderArtists();
}

var renderArtists = function() {
	var output = '';

	for (var i = 0; i < filteredArtists.length; i++) {
		// Add "|| filteredArtists.length = 1" to automatically highlight if only 1 artist in list
		output += '<li class="lib-entry'
			+ ((LIB.filters.artists.indexOf(filteredArtists[i]) >= 0 || filteredArtists.length == 1) ? ' active' : '')
			+ '">' + filteredArtists[i] + '</li>';
	}

	$('#artistsList').html(output);

	if (UI.libPos[0] == -2) {
		$('#lib-artist').scrollTo(0, 200);
	}

	renderAlbums();
}

var renderAlbums = function() {
	var output = '';
	var output2 = '';
	var activeFlag = '';
	var tagViewYear = '';   // For display of Artist (Year) in Tag View
	var albumViewYear = '';  // For display of Artist (Year) in Album View

    // Clear search filter and results
	$('#lib-album-filter').val('');

    if (GLOBAL.nativeLazyLoad) {
    	var tagViewLazy = '<img loading="lazy" src="';
        var albumViewLazy = tagViewLazy;
    }
    else {
    	var tagViewLazy = '<img class="lazy-tagview" data-original="';
    	var albumViewLazy = '<img class="lazy-albumview" data-original="';
    }

    // SESSION.json['library_encoded_at']
    // 0 = No (searchable), 1 = HD only, 2 = Text, 3 = Badge, 9 = No
    var encodedAtOption = parseInt(SESSION.json['library_encoded_at']);
    var tagViewHdDiv = '';
    var tagViewNvDiv = '';
    var albumViewNvDiv = '';
    var albumViewHdDiv = '';
    var albumViewTxDiv= '';
    var albumViewBgDiv = '';

	for (var i = 0; i < filteredAlbums.length; i++) {
        filteredAlbums[i].year ? tagViewYear = '(' + filteredAlbums[i].year + ')' : tagViewYear = '';
        filteredAlbumCovers[i].year ? albumViewYear = '(' + filteredAlbumCovers[i].year + ')' : albumViewYear = '';

        // filteredAlbums[i].encoded_at
        // [0] bits/rate format. [1] flag: "l" lossy, "s" standard def or "h" high def
        if (encodedAtOption != 9) {
            // Tag view
            var tagViewHdDiv = encodedAtOption == 1 && filteredAlbums[i].encoded_at.split(',')[1] == 'h' ? '<div class="encoded-at-hdonly-tagview">HD</div>' : '';
            var tagViewNvDiv = encodedAtOption <= 1 ? '<div class="encoded-at-notvisible">' + filteredAlbums[i].encoded_at.split(',')[0] + '</div>' : '';
            // Album view
            var encodedAt = filteredAlbumCovers[i].encoded_at.split(',');
            var albumViewNvDiv = encodedAtOption <= 1 ? '<div class="encoded-at-notvisible">' + filteredAlbumCovers[i].encoded_at.split(',')[0] + '</div>' : '';
            var albumViewHdDiv = encodedAtOption == 1 && encodedAt[1] == 'h' ? '<div class="encoded-at-hdonly">HD</div>' : '';
            var albumViewTxDiv = encodedAtOption == 2 ? '<div class="encoded-at-text">' + encodedAt[0] + '</div>' : '';
            var albumViewBgDiv = encodedAtOption == 3 ? '<div class="encoded-at-badge">' + encodedAt[0] + '</div>' : '';
        }

		if (SESSION.json['library_tagview_covers'] == 'Yes') {
			output += '<li class="lib-entry">'
                + tagViewLazy + filteredAlbums[i].imgurl + '">'
                + tagViewHdDiv
                + '<div class="tag-cover-text"><span class="album-name-art">' + filteredAlbums[i].album + '</span>'
                + '<span class="artist-name-art">' + filteredAlbums[i].artist + '</span>'
                + '<span class="album-year">' + tagViewYear + '</span></div>'
                + tagViewNvDiv
                + '</li>';
        }
		else {
			output += '<li class="lib-entry no-tagview-covers">'
                + '<span class="album-name">' + filteredAlbums[i].album
                + '</span><span class="artist-name-art">' + filteredAlbums[i].artist + '</span><span class="album-year">' + tagViewYear + '</span></li>'
        }

		output2 += '<li class="lib-entry">'
            + albumViewLazy + filteredAlbumCovers[i].imgurl + '">'
            + '<div class="cover-menu" data-toggle="context" data-target="#context-menu-lib-all"></div>'
			+ albumViewHdDiv
			+ albumViewBgDiv
            + '<span class="album-name">' + filteredAlbumCovers[i].album + '</span>'
            + '<div class="artyear"><span class="artist-name">' + filteredAlbumCovers[i].artist + '</span><span class="album-year">' + albumViewYear + '</span></div>'
            + albumViewTxDiv
            + albumViewNvDiv
            + '</li>';
	}

    // Output the lists
	$('#albumsList').html(output);
	$('#albumcovers').html(output2);

	// If only 1 album automatically highlight and display tracks
	if (filteredAlbums.length == 1) {
	    $('#albumsList li').addClass('active');
		LIB.albumClicked = true;
	}

    // Set ellipsis text
	if (SESSION.json["library_ellipsis_limited_text"] == "Yes") {
		$('#library-panel').addClass('limited');
	}

	// Headers clicked
	if (UI.libPos[0] == -2) {
		// only scroll the visible list
		if ($('.tag-view-btn').hasClass('active')) {
			$('#lib-album').scrollTo(0, 200);
		}
		else {
			$('#lib-albumcover').scrollTo(0, 200);
		}
	}

	// Start lazy load
	if ($('.album-view-btn').hasClass('active')) {
		$('img.lazy-albumview').lazyload({
			container: $('#lib-albumcover')
		});
	}
	else if ($('.tag-view-btn').hasClass('active') && SESSION.json['library_tagview_covers'] == 'Yes') {
		$('img.lazy-tagview').lazyload({
		    container: $('#lib-album')
		});
	}

	renderSongs();
}

// Render songs
var renderSongs = function(albumPos) {
	var output = '';
    var lastAlbum = '';
	var lastDisc = '';
    var albumDiv = '';
	var discDiv = '';
	LIB.totalTime = 0;

    if (LIB.artistClicked == true || LIB.albumClicked == true) {
        // Order the songs according the the order of the albums
        var orderedSongs = [];
        for (i = 0; i < filteredAlbums.length; i++) {
            for (j = 0; j < filteredSongs.length; j++) {
                if (filteredSongs[j].album == filteredAlbums[i].album) {
                    orderedSongs.push(filteredSongs[j]);
                }
            }
        }
        filteredSongs = orderedSongs;

        // Flag multi-disc albums
        lastAlbum = '';
        lastDisc = '';
        var multiDisc = [];
        for (i = 0; i < filteredSongs.length; i++) {
            if (filteredSongs[i].album == lastAlbum && filteredSongs[i].disc != lastDisc) {
                console.log('Multi-disc: ' + filteredSongs[i].album);
                multiDisc.push(filteredSongs[i].album);
            }
            lastAlbum = filteredSongs[i].album;
            lastDisc = filteredSongs[i].disc;
        }

        // Render the song list
        lastAlbum = '';
        lastDisc = '';
		for (i = 0; i < filteredSongs.length; i++) {
			var songyear = filteredSongs[i].year ? filteredSongs[i].year.slice(0,4) : ' ';

            if (filteredSongs[i].album != lastAlbum) {
                albumDiv = '<div class="lib-album-heading">' + filteredSongs[i].album + '</div>';
                lastAlbum = filteredSongs[i].album;
            }
            else {
                albumDiv = '';
            }

            if (multiDisc.indexOf(filteredSongs[i].album) != -1) {
                if (filteredSongs[i].disc != lastDisc) {
    				discDiv = '<div id="lib-disc-' + filteredSongs[i].disc + '" class="lib-disc"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-lib-disc">Disc ' + filteredSongs[i].disc + '</a></div>'
    				lastDisc = filteredSongs[i].disc;
    			}
    			else {
    				discDiv = '';
    			}
            }

			var composer = filteredSongs[i].composer == 'Composer tag missing' ? '</span>' : '<br><span class="songcomposer">' + filteredSongs[i].composer + '</span></span>';
			var highlight = filteredSongs[i].title == MPD.json['title'] ? ' lib-track-highlight' : '';

            output += albumDiv
                + discDiv
    			+ '<li id="lib-song-' + (i + 1) + '" class="clearfix">'
    			+ '<div class="lib-entry-song"><span class="songtrack' + highlight + '">' + filteredSongs[i].tracknum + '</span>'
    			+ '<span class="songname">' + filteredSongs[i].title + '</span>'
    			+ '<span class="songtime"> ' + filteredSongs[i].time_mmss + '</span>'
    			+ '<span class="songartist"> ' + filteredSongs[i].artist + composer
    			+ '<span class="songyear"> ' + songyear + '</span></div>'
    			+ '<div class="lib-action"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-lib-item"><i class="fas fa-ellipsis-h"></i></a></div>'
    			+ '</li>';

			LIB.totalTime += parseSongTime(filteredSongs[i].time);
		}
	}
	else {
		for (i = 0; i < filteredSongs.length; i++) {
			LIB.totalTime += parseSongTime(filteredSongs[i].time);
		}
	}

	$('#songsList').html(output);

    // Display album name heading if more than 1 album for clicked artist
	if (filteredAlbums.length > 1 && LIB.artistClicked == true && LIB.albumClicked == false) {
		$('.lib-album-heading').css('display', 'block');
	}

	// Display disc num if more than 1 disc, exceot for case: Album name contains the string '[Disc' which indicates separate albums for each disc
	if (lastDisc > 1 && !filteredSongs[0].album.toLowerCase().includes('[disc')) {
		$('.lib-disc').css('display', 'block');
	}

	//console.log('filteredSongs[0].file=' + filteredSongs[0].file);
	//console.log('LIB.filters.albums=(' + LIB.filters.albums + ')');
	//console.log('pos=(' + albumPos + ')')

	// Cover art and metadata for Tag and Album views
	if (filteredAlbums.length == 1 || LIB.filters.albums.length || typeof(albumPos) !== 'undefined') {
		$('#lib-coverart-img').html('<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-all">' +
			'<img class="lib-coverart" src="' + makeCoverUrl(filteredSongs[0].file) + '" ' + 'alt="Cover art not found"' + '></a>');
		$('#lib-albumname').html(filteredSongs[0].album);

		if (albumPos && !UI.libPos[0]) {
			artist = filteredAlbums[UI.libPos[0]].artist;
		}
		else {
			artist = filteredSongs[0].album_artist || filteredSongs[0].artist;
		}
		$('#lib-artistname').html(artist);
		$('#lib-albumyear').html(filteredSongs[0].year);
		$('#lib-numtracks').html(filteredSongs.length + ((filteredSongs.length == 1) ? ' track, ' : ' tracks, ') + formatTotalTime(LIB.totalTime));
		$('#lib-encoded-at').html(filteredSongs[0].encoded_at.split(',')[0]);
	}
	else {
		var album = LIB.filters.genres.length ? LIB.filters.genres : (LIB.filters.artists.length ? LIB.filters.artists : 'Music Library');
		var artist = LIB.filters.genres.length ? LIB.filters.artists : '';

        if (LIB.filters.artists.length > 0) {
            $('#lib-coverart-img').html('<button class="btn" id="tagview-text-cover" data-toggle="context" data-target="#context-menu-lib-all">' + LIB.filters.artists[0] + '</button>');
            artist = '';
        }
        else if (LIB.filters.genres.length > 0) {
            $('#lib-coverart-img').html('<button class="btn" id="tagview-text-cover" data-toggle="context" data-target="#context-menu-lib-all">' + LIB.filters.genres[0] + '</button>');
        }
        else {
            $('#lib-coverart-img').html('<button class="btn" id="tagview-text-cover" data-toggle="context" data-target="#context-menu-lib-all">' + 'Music Collection' + '</button>');
        }
		$('#lib-albumname').html(album);
		$('#lib-artistname').html(artist);
		$('#lib-albumyear').html('');
		$('#lib-numtracks').html(formatNumCommas(filteredAlbums.length) + ' albums<br>' + formatNumCommas(filteredSongs.length) + ((filteredSongs.length == 1) ? ' track<br>' : ' tracks<br>') + formatTotalTime(LIB.totalTime));
		$('#lib-encoded-at').html('');
	}
}

// Cleck genre or menu header (reset all)
$('#genreheader, #menu-header').on('click', function(e) {
	LIB.filters.genres.length = 0;
	LIB.filters.artists.length = 0;
	LIB.filters.albums.length = 0;
	LIB.filters.year.length = 0;
	if (currentView == 'tag' || currentView == 'album') {
		LIB.artistClicked = false;
        LIB.albumClicked = false;
		$("#searchResetLib").hide();
		showSearchResetLib = false;
		if (GLOBAL.musicScope == 'recent' && !GLOBAL.searchLib) { // if recently added and not search reset to all
			GLOBAL.musicScope = 'all';
		}
		GLOBAL.searchLib = '';
		if (currentView == 'album') {
			$('#albumcovers .lib-entry').removeClass('active');
			$('#bottom-row').css('display', '');
			$('#lib-albumcover').css('height', '100%');
		}
		setLibMenuHeader();
	}
	if (currentView == 'radio') {
		GLOBAL.searchRadio = '';
		$('#searchResetRa').click();
		setLibMenuHeader();
	}
	UI.libPos.fill(-2);
	storeLibPos(UI.libPos);
	clickedLibItem(e, undefined, LIB.filters.genres, renderGenres);
});

// Click artists header
$('#artistheader').on('click', '.lib-heading', function(e) {
    LIB.artistClicked = false;
    LIB.albumClicked = false;
	LIB.filters.artists.length = 0;
	LIB.filters.albums.length = 0;
	UI.libPos.fill(-2);
	storeLibPos(UI.libPos);
	clickedLibItem(e, undefined, LIB.filters.artists, renderArtists);
	$("#searchResetLib").hide();
	showSearchResetLib = false;
    setLibMenuHeader();
});

// Click album or album cover header
$('#albumheader').on('click', '.lib-heading', function(e) {
    LIB.albumClicked = false;
	LIB.filters.albums.length = 0;
    UI.libPos[0] = -2;
    UI.libPos[1] = -2;
	clickedLibItem(e, undefined, LIB.filters.albums, renderAlbums);
	storeLibPos(UI.libPos);
	$("#searchResetLib").hide();
	showSearchResetLib = false;
});

// Click genre
$('#genresList').on('click', '.lib-entry', function(e) {
	var pos = $('#genresList .lib-entry').index(this);
    LIB.artistClicked = false;
    LIB.albumClicked = false;
	LIB.filters.artists.length = 0;
	LIB.filters.albums.length = 0;
	UI.libPos[0] = -1;
	storeLibPos(UI.libPos);
	clickedLibItem(e, allGenres[pos], LIB.filters.genres, renderGenres);
    setLibMenuHeader();
});

// Click artist
$('#artistsList').on('click', '.lib-entry', function(e) {
	var pos = $('#artistsList .lib-entry').index(this);
	UI.libPos[0] = -1;
	UI.libPos[2] = pos;
	LIB.filters.albums.length = 0;
	storeLibPos(UI.libPos);
    LIB.artistClicked = true;
    LIB.albumClicked = false;
	clickedLibItem(e, filteredArtists[pos], LIB.filters.artists, renderArtists);
	if (UI.mobile) {
		$('#top-columns').animate({left: '-50%'}, 200);
	}
	setLibMenuHeader();
});

// Click album
$('#albumsList').on('click', '.lib-entry', function(e) {
	var pos = $('#albumsList .lib-entry').index(this);
    LIB.albumClicked = true;
	UI.libPos[0] = pos;
	UI.libPos[1] = filteredAlbumCovers.map(function(e) {return e.album;}).indexOf(filteredAlbums[pos].album);
	var albumobj = filteredAlbums[pos];
	var album = filteredAlbums[pos].album;
	storeLibPos(UI.libPos);
	$('#albumsList .lib-entry').removeClass('active');
	$('#albumsList .lib-entry').eq(pos).addClass('active');
	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);
	$('#bottom-row').css('display', 'flex')
	$('#lib-file').scrollTo(0, 200);
	UI.libAlbum = album;
});

// Click random album button
$('#random-album').click(function(e) {
	var array = new Uint16Array(1);
    LIB.albumClicked = true;
	window.crypto.getRandomValues(array);
	pos = Math.floor((array[0] / 65535) * filteredAlbums.length);

	if (currentView == 'tag') {
		var itemSelector = '#albumsList .lib-entry';
		var scrollSelector = 'albums';
		UI.libPos[0] = pos;
		UI.libPos[1] = filteredAlbumCovers.map(function(e) {return e.album;}).indexOf(filteredAlbums[pos].album);
		var albumobj = filteredAlbums[pos];
	}
	else {
		var itemSelector = '#albumcovers .lib-entry';
		var scrollSelector = 'albumcovers';
		UI.libPos[0] = filteredAlbums.map(function(e) {return e.album;}).indexOf(filteredAlbumCovers[pos].album);
		UI.libPos[1] = pos;
		var albumobj = filteredAlbumCovers[pos];
	}

	storeLibPos(UI.libPos);

	$(itemSelector).removeClass('active');
	$(itemSelector).eq(pos).addClass('active');
	customScroll(scrollSelector, pos, 200);

	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);
});

// Click album cover menu button
$('#albumcovers').on('click', '.cover-menu', function(e) {
	var pos = $(this).parents('li').index();
    LIB.albumClicked = true;

	$('#albumcovers .lib-entry').eq(UI.libPos[1]).removeClass('active');
	$('#tracklist-toggle').show();

	UI.libPos[0] = filteredAlbums.map(function(e) {return e.album;}).indexOf(filteredAlbumCovers[pos].album);
	UI.libPos[1] = pos;
	storeLibPos(UI.libPos);
	$('#albumcovers .lib-entry').eq(UI.libPos[1]).addClass('active');

	var albumobj = filteredAlbumCovers[pos];

	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);
});

// Click album cover for instant play
$('#albumcovers').on('click', 'img', function(e) {
	var pos = $(this).parents('li').index();
    LIB.albumClicked = true;

	$('#albumcovers .lib-entry').eq(UI.libPos[1]).removeClass('active');

	UI.libPos[0] = filteredAlbums.map(function(e) {return e.album;}).indexOf(filteredAlbumCovers[pos].album);
	UI.libPos[1] = pos;
	storeLibPos(UI.libPos);
	$('#albumcovers .lib-entry').eq(UI.libPos[1]).addClass('active');

	var albumobj = filteredAlbumCovers[pos];

	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);

	var files = [];
	for (var i in filteredSongs) {
		files.push(filteredSongs[i].file);
	}

    if (SESSION.json['library_instant_play'] == 'No action') {return false;}

    var cmd = SESSION.json['library_instant_play'] == 'Add/Play' ? 'playall' : 'clrplayall';
	mpdDbCmd(cmd, files);
    notify(cmd, '');

	// So tracks list doesn't open
	return false;
});

// Random album instant play (button on Playback panel)
$('.ralbum').click(function(e) {
	$('.ralbum svg').attr('class', 'spin');
	setTimeout(function() {
		$('.ralbum svg').attr('class', '');
	}, RALBUM_TIMEOUT);

	var array = new Uint16Array(1);
    LIB.albumClicked = true;
	window.crypto.getRandomValues(array);
	pos = Math.floor((array[0] / 65535) * filteredAlbums.length);

    UI.libPos[0] = pos;
	UI.libPos[1] = filteredAlbumCovers.map(function(e) {return e.album;}).indexOf(filteredAlbums[pos].album);
	var albumobj = filteredAlbums[pos];

	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);

	var files = [];
	for (var i in filteredSongs) {
		files.push(filteredSongs[i].file);
	}

    if (SESSION.json['library_instant_play'] == 'Add/Play' || SESSION.json['library_instant_play'] == 'No action') {
        mpdDbCmd('playall', files);
    }
    // Clear/play using add first followed by delete.
    // We do this because clrplayall directly from the Playback panel results in missed ui and pl updates.
    else {
    	var endpos = $(".playlist li").length
    	mpdDbCmd('addall', files);
    	setTimeout(function() {
    		endpos == 1 ? cmd = 'delplitem&range=0' : cmd = 'delplitem&range=0:' + endpos;
            $.get('command/moode.php?cmd=' + cmd, function(){
                sendMpdCmd('play 0');
            });
    	}, CLRPLAY_TIMEOUT);
    }
});

// click radio cover for instant play
$('#database-radio').on('click', 'img', function(e) {
    if (SESSION.json['library_instant_play'] == 'No action') {return false;}
	var pos = $(this).parents('li').index();
	var path = $(this).parents('li').data('path');

    // Radio list view
	if ($(this).parents().hasClass('db-radiofolder-icon')) {
		UI.raFolderLevel[UI.raFolderLevel[4]] = $(this).parents('li').attr('id').replace('db-','');
		++UI.raFolderLevel[4];
		mpdDbCmd('lsinfo_radio', $(this).parents('li').data('path'));
	}
    // Radio cover view
	else {
		UI.radioPos = pos;
		storeRadioPos(UI.radioPos)

        var cmd = SESSION.json['library_instant_play'] == 'Add/Play' ? 'play' : 'clrplay';
    	mpdDbCmd(cmd, path);
        if (cmd != 'play') {
            notify(cmd, '');
        }

		setTimeout(function() {
			customScroll('radio', UI.radioPos, 200);
		}, SCROLLTO_TIMEOUT);
	}

	// Needed ?
	return false;
});

// Radio manager
$('#radio-manager-btn').click(function(e) {
    $('#import-export-msg').text('');
    $('#radio-manager-modal').modal();
});
$('#export-stations').click(function(e) {
    $('#import-export-msg').text('Exporting...');
    e.preventDefault();
    $.post('command/moode.php?cmd=export_stations', function() {
        window.location.href = '/imagesw/stations.zip';
        $('#import-export-msg').text('Export complete');
	});
});

// Click radio list item for instant play
$('#database-radio').on('click', '.db-entry', function(e) {
    if (SESSION.json['library_instant_play'] == 'No action') {return false;}
	var pos = $(this).parents('li').index();
	var path = $(this).parents('li').data('path');

	// set new pos
	UI.radioPos = pos;
	storeRadioPos(UI.radioPos)

    var cmd = SESSION.json['library_instant_play'] == 'Add/Play' ? 'play' : 'clrplay';
    mpdDbCmd(cmd, path);
    if (cmd != 'play') {
        notify(cmd, '');
    }

	setTimeout(function() {
		customScroll('radio', UI.radioPos, 200);
	}, SCROLLTO_TIMEOUT);

	return false;
});

// Click lib coverart
$('#lib-coverart-img').click(function(e) {
	UI.dbEntry[0] =  $.isNumeric(UI.dbEntry[0]) ? UI.dbEntry[0] : 0;
    $('#songsList li, #songsList .lib-disc a').removeClass('active');
	$('img.lib-coverart').addClass('active'); // add highlight
	$('#tracklist-toggle').hide();
});

// Click Disc
$('#songsList').on('click', '.lib-disc', function(e) {
	$('img.lib-coverart, #songsList li, #songsList .lib-disc a').removeClass('active'); // Remove highlight
	var discNum = $(this).text().substr(5);
	$('#lib-disc-' + discNum + ' a').addClass('active');

	filteredSongsDisc.length = 0;
	for (var i in filteredSongs) {
		if (filteredSongs[i].disc == discNum) {
			filteredSongsDisc.push(filteredSongs[i]);
		}
	}
	//console.log('filteredSongsDisc= ' + JSON.stringify(filteredSongsDisc));
});

// Click lib track
$('#songsList').on('click', '.lib-action', function(e) {
    UI.dbEntry[0] = $('#songsList .lib-action').index(this); // Store pos for use in action menu item click
	$('#songsList li, #songsList .lib-disc a').removeClass('active');
	$(this).parent().addClass('active');
	$('img.lib-coverart').removeClass('active'); // Remove highlight
});

// Playback ellipsis context menu
$('#context-menu-playback a').click(function(e) {
    //console.log($(this).data('cmd'));
	if ($(this).data('cmd') == 'save-playlist') {
		$('#savepl-modal').modal();
	}
	if ($(this).data('cmd') == 'set-favorites') {
        $.getJSON('command/moode.php?cmd=getfavname', function(favname) {
            $('#pl-favName').val(favname);
            $('#setfav-modal').modal();
        });
	}
	if ($(this).data('cmd') == 'toggle-song') {
        sendMpdCmd('playid ' + toggleSongId);
	}
	if ($(this).data('cmd') == 'consume') {
		// Menu item
		$('#menu-check-consume').toggle();
		// Button
		var toggle = $('.consume').hasClass('btn-primary') ? '0' : '1';
		$('.consume').toggleClass('btn-primary');
		sendMpdCmd('consume ' + toggle);
	}
	if ($(this).data('cmd') == 'repeat') {
		$('#menu-check-repeat').toggle();

		var toggle = $('.repeat').hasClass('btn-primary') ? '0' : '1';
		$('.repeat').toggleClass('btn-primary');
		sendMpdCmd('repeat ' + toggle);
	}
	if ($(this).data('cmd') == 'single') {
		$('#menu-check-single').toggle();

		var toggle = $('.single').hasClass('btn-primary') ? '0' : '1';
		$('.single').toggleClass('btn-primary');
		sendMpdCmd('single ' + toggle);
	}
});

// Click tracks context menu item
$('#context-menu-lib-item a').click(function(e) {
	$('#lib-song-' + (UI.dbEntry[0] + 1).toString()).removeClass('active');
	$('img.lib-coverart').removeClass('active');

	if ($(this).data('cmd') == 'add') {
		mpdDbCmd('add', filteredSongs[UI.dbEntry[0]].file);
		notify('add');
	}
	else if ($(this).data('cmd') == 'play') {
		// See moode.php: We check to see if the file is already in the playlist and then just play it
		mpdDbCmd('play', filteredSongs[UI.dbEntry[0]].file);
	}
	else if ($(this).data('cmd') == 'clrplay') {
		mpdDbCmd('clrplay', filteredSongs[UI.dbEntry[0]].file);
		notify('clrplay');
		$('#pl-saveName').val(''); // Clear saved playlist name if any
	}
    else if ($(this).data('cmd') == 'track_info_lib') {
        $.post('command/moode.php?cmd=track_info', {'path': filteredSongs[UI.dbEntry[0]].file}, function(result) {
            $('#track-info-text').html(result);
            $('#track-info-modal').modal();
        }, 'json');
	}
});

// Click coverart context menu item
$('#context-menu-lib-all a').click(function(e) {
	UI.dbEntry[0] = $.isNumeric(UI.dbEntry[0]) ? UI.dbEntry[0] : 0;
	if (!$('.album-view-button').hasClass('active')) {
		$('#lib-song-' + (UI.dbEntry[0] + 1).toString()).removeClass('active');
		$('img.lib-coverart').removeClass('active');
	}

	var files = [];
	for (var i in filteredSongs) {
		files.push(filteredSongs[i].file);
	}
	//console.log('files= ' + JSON.stringify(files));

	if ($(this).data('cmd') == 'addall') {
		mpdDbCmd('addall', files);
		notify('add');
	}
	else if ($(this).data('cmd') == 'playall') {
		mpdDbCmd('playall', files);
		notify('add');
	}
	else if ($(this).data('cmd') == 'clrplayall') {
		mpdDbCmd('clrplayall', files);
		notify('clrplay');
	}
	else if ($(this).data('cmd') == 'tracklist') {
		if ($('#bottom-row').css('display') == 'none') {
			$('#tracklist-toggle').html('<i class="fal fa-list sx"></i> Hide tracks');
			$('#bottom-row').css('display', 'flex')
			$('#lib-albumcover').css('height', 'calc(47% - 2em)'); // Was 1.75em
			$('#index-albumcovers').hide();
		}
		else {
			$('#tracklist-toggle').html('<i class="fal fa-list sx"></i> Show tracks');
			$('#bottom-row').css('display', '')
			$('#lib-albumcover').css('height', '100%');
			$('#index-albumcovers').show();
		}

		customScroll('albumcovers', UI.libPos[1], 200);
	}
});

// Click disc context menu item
$('#context-menu-lib-disc a').click(function(e) {
	$('#songsList .lib-disc a').removeClass('active');

	var files = [];
	for (var i in filteredSongsDisc) {
		files.push(filteredSongsDisc[i].file);
	}
	//console.log('files= ' + JSON.stringify(files));

	if ($(this).data('cmd') == 'addall') {
		mpdDbCmd('addall', files);
		notify('add');
	}
	if ($(this).data('cmd') == 'playall') {
		mpdDbCmd('playall', files);
		notify('add');
	}
	if ($(this).data('cmd') == 'clrplayall') {
		mpdDbCmd('clrplayall', files);
		notify('clrplay');
	}
});
