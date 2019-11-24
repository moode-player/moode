/**
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
 * 2019-11-24 TC moOde 6.4.0
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
    filters: {artists: [], genres: [], albums: []}
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
	$.post('command/moode.php?cmd=loadlib', {}, function(data) {
		$('#lib-loader').hide();
		$('#lib-content').show();
		renderLibrary(data);
	}, 'json');

	libRendered = true;
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
        //var year = SESSION.json['library_album_grouping'] == 'Year' ? getYear(albumTracks) : '';
        var year = getYear(albumTracks);
		return {
			last_modified: getLastModified(albumTracks),
            year: year,
			album: findAlbumProp(albumTracks, 'album'),
			genre: findAlbumProp(albumTracks, 'genre'),
			all_genres: Object.keys(albumTracks.reduce(reduceGenres, {})),
			artist: albumArtist || artist,
			imgurl: '/imagesw/thmcache/' + encodeURIComponent(md5) + '.jpg'
		};
	});

	allAlbumCovers = allAlbums.slice();

    // Natural ordering
	try {
		var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
		allSongs.sort(function(a, b) {
			return collator.compare(removeArticles(a['album_artist'] || a['artist']), removeArticles(b['album_artist'] || b['artist']));
		});

        if (SESSION.json['library_album_grouping'] == 'Artist') {
            allAlbums.sort(function(a, b) {
                return (collator.compare(removeArticles(a['artist']), removeArticles(b['artist'])) || collator.compare(removeArticles(a['album']), removeArticles(b['album'])));
    		});
            allAlbumCovers.sort(function(a, b) {
                return (collator.compare(removeArticles(a['artist']), removeArticles(b['artist'])) || collator.compare(removeArticles(a['album']), removeArticles(b['album'])));
    		});
        }
        else if (SESSION.json['library_album_grouping'] == 'Album') {
            allAlbums.sort(function(a, b) {
                return collator.compare(removeArticles(a['album']), removeArticles(b['album']));
            });
            allAlbumCovers.sort(function(a, b) {
                return collator.compare(removeArticles(a['album']), removeArticles(b['album']));
            });
        }
        else if (SESSION.json['library_album_grouping'] == 'Year') {
            allAlbums.sort(function(a, b) {
                return (collator.compare(a['year'], b['year']) || collator.compare(removeArticles(a['album']), removeArticles(b['album'])));
            });
            allAlbumCovers.sort(function(a, b) {
                return (collator.compare(a['year'], b['year']) || collator.compare(removeArticles(a['album']), removeArticles(b['album'])));
            });
        }
	}
    // Fallback to default ordering
	catch (e) {
		allSongs.sort(function(a, b) {
			a = removeArticles((a['album_artist'] || a['artist']).toLowerCase());
			b = removeArticles((b['album_artist'] || b['artist']).toLowerCase());
			return a > b ? 1 : (a < b ? -1 : 0);
		});

        if (SESSION.json['library_album_grouping'] == 'Artist') {
            allAlbums.sort(function(a, b) {
    			var x1 = removeArticles(a['artist']).toLowerCase(), x2 = removeArticles(b['artist']).toLowerCase();
    			var y1 = removeArticles(a['album']).toLowerCase(), y2 = removeArticles(b['album']).toLowerCase();
    			return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
    		});
            allAlbumCovers.sort(function(a, b) {
    			var x1 = removeArticles(a['artist']).toLowerCase(), x2 = removeArticles(b['artist']).toLowerCase();
    			var y1 = removeArticles(a['album']).toLowerCase(), y2 = removeArticles(b['album']).toLowerCase();
    			return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
    		});
        }
        else if (SESSION.json['library_album_grouping'] == 'Album') {
            allAlbums.sort(function(a, b) {
                a = removeArticles(a['album'].toLowerCase());
    			b = removeArticles(b['album'].toLowerCase());
    			return a > b ? 1 : (a < b ? -1 : 0);
    		});
            allAlbumCovers.sort(function(a, b) {
                a = removeArticles(a['album'].toLowerCase());
    			b = removeArticles(b['album'].toLowerCase());
    			return a > b ? 1 : (a < b ? -1 : 0);
    		});
        }
        else if (SESSION.json['library_album_grouping'] == 'Year') {
            allAlbums.sort(function(a, b) {
    			var x1 = a['year'], x2 = b['year'];
    			var y1 = removeArticles(a['album']).toLowerCase(), y2 = removeArticles(b['album']).toLowerCase();
    			return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
    		});
            allAlbumCovers.sort(function(a, b) {
    			var x1 = a['year'], x2 = b['year'];
    			var y1 = removeArticles(a['album']).toLowerCase(), y2 = removeArticles(b['album']).toLowerCase();
    			return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
    		});
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
}

function filterLib() {
	filteredSongsDisc.length = 0;
	filterArtists();
	filterAlbums();
	filterSongs();
}

// Remove artcles from beginning of string
function removeArticles(string) {
	return SESSION.json['ignore_articles'] != 'None' ? string.replace(GLOBAL.regExIgnoreArticles, '$2') : string;
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
	renderFunc();
}

var renderGenres = function() {
	var output = '';

	for (var i = 0; i < allGenres.length; i++) {
		output += '<li><div class="lib-entry'
			+ (LIB.filters.genres.indexOf(allGenres[i]) >= 0 ? ' active' : '')
			+ '">' + allGenres[i] + '</div></li>';
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
		output += '<li><div class="lib-entry'
			+ ((LIB.filters.artists.indexOf(filteredArtists[i]) >= 0 || filteredArtists.length == 1) ? ' active' : '')
			+ '">' + filteredArtists[i] + '</div></li>';
	}

	$('#artistsList').html(output);

	if (UI.libPos[0] == -2) {
		$('#lib-artist').scrollTo(0, 200);
	}

	renderAlbums();
}

var renderAlbums = function() {
	// Clear search filter and results
	$('#lib-album-filter').val('');
	$('#lib-album-filter-results').html('');

	var output = '';
	var output2 = '';
	var tmp = '';
	var defCover = "this.src='images/default-cover-v6.svg'";

	for (var i = 0; i < filteredAlbums.length; i++) {
		// Add "|| filteredAlbums.length = 1" to automatically highlight if only 1 album in list
		if (LIB.filters.albums.indexOf(keyAlbum(filteredAlbums[i])) >= 0 || filteredAlbums.length == 1) {
			tmp = ' active';
			LIB.albumClicked = true; // For renderSongs() so it can decide whether to display tracks
		}
		else {
			tmp = '';
		}

        var album_year = filteredAlbums[i].year;
        var album_year2 = filteredAlbumCovers[i].year;

        //UI.tagViewCovers = false;
		if (UI.tagViewCovers) {
			output += '<li><div class="lib-entry'
				+ tmp
				+ '">' + '<img class="lazy-tagview" data-original="' + filteredAlbums[i].imgurl + '"><div class="album-name">' + filteredAlbums[i].album + '<br><span class="album-year">' + album_year + '</span><span class="artist-name">' + filteredAlbums[i].artist + '</span></div></div></li>';
			output2 += '<li><div class="lib-entry'
				+ tmp
				+ '">' + '<img class="lazy-albumview" data-original="' + filteredAlbumCovers[i].imgurl + '"><div class="cover-menu" data-toggle="context" data-target="#context-menu-lib-all"></div><div class="albumcover">' + '<span class="album-year">' + album_year2 + '</span>' + '<span class="album-name">' + filteredAlbumCovers[i].album + '</span></div><span class="artist-name">' + filteredAlbumCovers[i].artist + '</span></div></li>';
		}
		else {
			output += '<li><div class="lib-entry'
				+ tmp
				+ '">' + filteredAlbums[i].album + '<span>' + ' - ' + filteredAlbums[i].artist + ', ' + album_year + '</span></div></li>';
			output2 += '<li><div class="lib-entry'
				+ tmp
				+ '">' + '<img class="lazy-albumview" data-original="' + filteredAlbumCovers[i].imgurl + '"><div class="cover-menu" data-toggle="context" data-target="#context-menu-lib-all"></div><div class="albumcover">' + '<span class="album-year">' + album_year2 + '</span>' + '<span class="album-name">' + filteredAlbumCovers[i].album + '</span></div><span class=artist-name>' + filteredAlbumCovers[i].artist + '</span></div></li>';
		}
	}

    // Output the lists
	$('#albumsList').html(output);
	$('#albumcovers').html(output2);

    // Control whether to display album year
    if (SESSION.json['library_album_grouping'] == 'Year') {
        $('#albumsList .lib-entry .album-year').css('display', 'contents');
        $('#albumcovers .lib-entry .album-year').css('display', 'block');
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
	else if ($('.tag-view-btn').hasClass('active') && UI.tagViewCovers) {
		$('img.lazy-tagview').lazyload({
		    container: $('#lib-album')
		});
	}

	renderSongs();
}

// Render songs
var renderSongs = function(albumPos) {
	var output = '';
	var discNum = '';
	var discDiv = '';
	LIB.totalTime = 0;

    //if (allSongs.length < LIB.totalSongs) { // Only display tracks if less than the whole library
	if (LIB.albumClicked == true) { // Only display tracks if album selected
		LIB.albumClicked = false;

		// Sort tracks and files
		try {
			var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
			filteredSongs.sort(function(a, b) {
				return (collator.compare(a['disc'], b['disc']) || collator.compare(a['tracknum'], b['tracknum']));
			});
		}
		catch (e) {
			filteredSongs.sort(function(a, b) {
				var x1 = a['disc'], x2 = b['disc'];
				var x1 = a['tracknum'], x2 = b['tracknum'];
				return x1 > x2 ? 1 : (x1 < x2 ? -1 : (y1 > y2 ? 1 : (y1 < y2 ? -1 : 0)));
			});
		}

		for (i = 0; i < filteredSongs.length; i++) {
			var songyear = filteredSongs[i].year ? filteredSongs[i].year.slice(0,4) : ' ';

			if (filteredSongs[i].disc != discNum) {
				discDiv = '<div id="lib-disc-' + filteredSongs[i].disc + '" class="lib-disc"><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-lib-disc">Disc ' + filteredSongs[i].disc + '</a></div>'
				discNum = filteredSongs[i].disc;
			}
			else {
				discDiv = '';
			}

			var composer = filteredSongs[i].composer == 'Composer tag missing' ? '</span>' : '<br><span class="songcomposer">' + filteredSongs[i].composer + '</span></span>';
			var highlight = filteredSongs[i].title == MPD.json['title'] ? ' lib-track-highlight' : '';

	    output += discDiv
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
	// Display disc num if more than 1 disc, exceot for case: Album name contains the string '[Disc' which indicates separate albums for each disc
	if (discNum > 1 && !filteredSongs[0].album.toLowerCase().includes('[disc')) {
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
	}
	else {
		var album = LIB.filters.genres.length ? LIB.filters.genres : (LIB.filters.artists.length ? LIB.filters.artists : 'Music Library');
		var artist = LIB.filters.genres.length ? LIB.filters.artists : '';

		$('#lib-coverart-img').html('<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-all">' + '<img class="lib-coverart" ' + 'src="' + UI.defCover + '"></a>');
		$('#lib-albumname').html(album);
		$('#lib-artistname').html(artist);
		$('#lib-albumyear').html('');
		$('#lib-numtracks').html(filteredSongs.length + ((filteredSongs.length == 1) ? ' track, ' : ' tracks, ') + formatTotalTime(LIB.totalTime));
	}
}

// Click genres header
$('#genreheader').on('click', '.lib-heading', function(e) {
	LIB.filters.genres.length = 0;
	LIB.filters.artists.length = 0;
	LIB.filters.albums.length = 0;
    LIB.recentlyAddedClicked = false;
	UI.libPos.fill(-2);
	storeLibPos(UI.libPos);
	clickedLibItem(e, undefined, LIB.filters.genres, renderGenres);
	$("#searchResetLib").hide();
	showSearchResetLib = false;
});

// Click artists header
$('#artistheader').on('click', '.lib-heading', function(e) {
	LIB.filters.artists.length = 0;
	LIB.filters.albums.length = 0;
    LIB.recentlyAddedClicked = false;
	UI.libPos.fill(-2);
	storeLibPos(UI.libPos);
	clickedLibItem(e, undefined, LIB.filters.artists, renderArtists);
	$("#searchResetLib").hide();
	showSearchResetLib = false;
});

// Click albums or album covers header
$('#albumheader, #albumcoverheader').on('click', '.lib-heading', function(e) {
	//console.log($(this).parent().attr('id'));
	if ($(this).parent().attr('id') == 'albumcoverheader') {
		$('#albumcovers .lib-entry').removeClass('active');
		$('#bottom-row').css('display', 'none');
		$('#lib-albumcover').css('height', '100%');
		LIB.filters.artists.length = 0;
		LIB.filters.albums.length = 0;
        LIB.recentlyAddedClicked = false;
		UI.libPos.fill(-2);
		clickedLibItem(e, undefined, LIB.filters.artists, renderArtists);
	}
	else {
		LIB.filters.albums.length = 0;
        LIB.recentlyAddedClicked = false;
		UI.libPos.fill(-2);
		clickedLibItem(e, undefined, LIB.filters.albums, renderAlbums);
	}

	storeLibPos(UI.libPos);
	$("#searchResetLib").hide();
	showSearchResetLib = false;
});

// Click genre
$('#genresList').on('click', '.lib-entry', function(e) {
	var pos = $('#genresList .lib-entry').index(this);
	LIB.filters.artists.length = 0;
	LIB.filters.albums.length = 0;
	UI.libPos[0] = -1;
	storeLibPos(UI.libPos);
	clickedLibItem(e, allGenres[pos], LIB.filters.genres, renderGenres);
});

// Click artist
$('#artistsList').on('click', '.lib-entry', function(e) {
	var pos = $('#artistsList .lib-entry').index(this);
	UI.libPos[0] = -1;
	UI.libPos[2] = pos;
	LIB.filters.albums.length = 0;
	storeLibPos(UI.libPos);
	clickedLibItem(e, filteredArtists[pos], LIB.filters.artists, renderArtists);
	if (UI.mobile) {
		$('#top-columns').animate({left: '-50%'}, 200);
	}
});

// Click album
$('#albumsList').on('click', '.lib-entry', function(e) {
	var pos = $('#albumsList .lib-entry').index(this);

	UI.libPos[0] = pos;
	UI.libPos[1] = filteredAlbumCovers.map(function(e) {return e.album;}).indexOf(filteredAlbums[pos].album);
	var albumobj = filteredAlbums[pos];
	var album = filteredAlbums[pos].album;

	storeLibPos(UI.libPos);

	LIB.albumClicked = true; // for renderSongs()
	$('#albumsList .lib-entry').removeClass('active');
	$('#albumsList .lib-entry').eq(pos).addClass('active');

	// Song list for regular album
	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);

	$('#bottom-row').css('display', 'flex')
	$('#lib-file').scrollTo(0, 200);
	UI.libAlbum = album;
});

// Click 'random album' button
$('#random-album, #random-albumcover').click(function(e) {
	var array = new Uint16Array(1);
	window.crypto.getRandomValues(array);
	pos = Math.floor((array[0] / 65535) * filteredAlbums.length);

	if ($(this).attr('id') == 'random-album') {
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

	LIB.albumClicked = true; // For renderSongs()
	$(itemSelector).removeClass('active');
	$(itemSelector).eq(pos).addClass('active');
	customScroll(scrollSelector, pos, 200);

	// Song list for regular album
	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);
});

// Click 'recently added' button
$('.recently-added').click(function(e) {
    LIB.recentlyAddedClicked = true;
	LIB.filters.albums.length = 0;
	UI.libPos.fill(-2);

	filterLib();
    renderAlbums();

	storeLibPos(UI.libPos);
	$("#searchResetLib").hide();
	showSearchResetLib = false;
});

// Click album cover menu button
$('#albumcovers').on('click', '.cover-menu', function(e) {
	var pos = $(this).parents('li').index();

	$('#albumcovers .lib-entry').eq(UI.libPos[1]).removeClass('active');
	$('#tracklist-toggle').show();

	UI.libPos[0] = filteredAlbums.map(function(e) {return e.album;}).indexOf(filteredAlbumCovers[pos].album);
	UI.libPos[1] = pos;
	storeLibPos(UI.libPos);
	$('#albumcovers .lib-entry').eq(UI.libPos[1]).addClass('active');

	LIB.albumClicked = true; // For renderSongs()
	var albumobj = filteredAlbumCovers[pos];

    // Song list for regular album
	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);
});

// Click album cover for instant play
$('#albumcovers').on('click', 'img', function(e) {
	var pos = $(this).parents('li').index();

	$('#albumcovers .lib-entry').eq(UI.libPos[1]).removeClass('active');

	UI.libPos[0] = filteredAlbums.map(function(e) {return e.album;}).indexOf(filteredAlbumCovers[pos].album);
	UI.libPos[1] = pos;
	storeLibPos(UI.libPos);
	$('#albumcovers .lib-entry').eq(UI.libPos[1]).addClass('active');

	LIB.albumClicked = true; // For renderSongs()
	var albumobj = filteredAlbumCovers[pos];

    // Song list for regular album
	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);

	var files = [];
	for (var i in filteredSongs) {
		files.push(filteredSongs[i].file);
	}

    var cmd = SESSION.json['library_instant_play'] == 'Add/Play' ? 'playall' : 'clrplayall';
	mpdDbCmd(cmd, files);
    notify(cmd, '');

	// So tracks list doesn't open
	return false;
});

// Random album instant play (button on Playback panel)
$('.ralbum').click(function(e) {
	if ($('.tab-content').hasClass('fancy')) {
		$('.ralbum svg').attr('class', 'spin');
		setTimeout(function() {
			$('.ralbum svg').attr('class', '');
		}, 1500);
	}
	var array = new Uint16Array(1);
	window.crypto.getRandomValues(array);
	pos = Math.floor((array[0] / 65535) * filteredAlbums.length);

	UI.libPos[0] = pos;
	UI.libPos[1] = filteredAlbumCovers.map(function(e) {return e.album;}).indexOf(filteredAlbums[pos].album);
	var albumobj = filteredAlbums[pos];

	storeLibPos(UI.libPos);
	LIB.albumClicked = true; // For renderSongs()

    // Song list for regular album
	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);

	var files = [];
	for (var i in filteredSongs) {
		files.push(filteredSongs[i].file);
	}

    if (SESSION.json['library_instant_play'] == 'Add/Play') {
        mpdDbCmd('playall', files);
    }
    // Clear/play using add first followed by delete. This approach makes for a smoother visual transition.
    else {
    	var endpos = $(".playlist li").length
    	mpdDbCmd('addall', files);
    	setTimeout(function() {
    		endpos == 1 ? cmd = 'delplitem&range=0' : cmd = 'delplitem&range=0:' + endpos;
    		var result = sendMoodeCmd('GET', cmd, '', true);
    		sendMpdCmd('play 0');
    	}, 500);
    }
});

// click radio cover for instant play
$('#database-radio').on('click', 'img', function(e) {
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
        notify(cmd, '');

		setTimeout(function() {
			customScroll('radiocovers', UI.radioPos, 200);
		}, 250);
	}

	// Needed ?
	return false;
});

// Radio list/cover toggle button
$('#ra-toggle-view').click(function(e) {
	if ($('#ra-toggle-view i').hasClass('fa-bars')) {
		$('#ra-toggle-view i').removeClass('fa-bars').addClass('fa-th');
		$('#radiocovers').addClass('database-radiolist');
		currentView = 'radiolist';
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'current_view': currentView}, true);
	}
	else {
		$('#ra-toggle-view i').removeClass('fa-th').addClass('fa-bars');
		$('#radiocovers').removeClass('database-radiolist');
		currentView = 'radiocover';
		var result = sendMoodeCmd('POST', 'updcfgsystem', {'current_view': currentView}, true);

		setTimeout(function() {
			$('img.lazy-radioview').lazyload({
				container: $('#radiocovers')
			});
			if (UI.radioPos >= 0) {
				customScroll('radiocovers', UI.radioPos, 200);
			}
		}, 250);
	}
});

// Click radio list item for instant play
$('#database-radio').on('click', '.db-entry', function(e) {
	var pos = $(this).parents('li').index();
	var path = $(this).parents('li').data('path');

	// set new pos
	UI.radioPos = pos;
	storeRadioPos(UI.radioPos)

    var cmd = SESSION.json['library_instant_play'] == 'Add/Play' ? 'play' : 'clrplay';
    mpdDbCmd(cmd, path);
    notify(cmd, '');

	setTimeout(function() {
		customScroll('radiocovers', UI.radioPos, 200);
	}, 250);

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
	if ($(this).data('cmd') == 'save-playlist') {
		$('#savepl-modal').modal();
	}
	if ($(this).data('cmd') == 'set-favorites') {
		var favname = sendMoodeCmd('GET', 'getfavname');
		$('#pl-favName').val(favname);
		$('#setfav-modal').modal();
	}
	if ($(this).data('cmd') == 'toggle-song') {
		$('.toggle-song').click();
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
		notify('add', '');
	}
	if ($(this).data('cmd') == 'play') {
		// NOTE: We could check to see if the file is already in the playlist and then just play it
		mpdDbCmd('play', filteredSongs[UI.dbEntry[0]].file);
		notify('add', '');
	}
	if ($(this).data('cmd') == 'clrplay') {
		mpdDbCmd('clrplay', filteredSongs[UI.dbEntry[0]].file);
		notify('clrplay', '');
		$('#pl-saveName').val(''); // Clear saved playlist name if any
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
		notify('add', '');
	}
	else if ($(this).data('cmd') == 'playall') {
		mpdDbCmd('playall', files);
		notify('add', '');
	}
	else if ($(this).data('cmd') == 'clrplayall') {
		mpdDbCmd('clrplayall', files);
		notify('clrplay', '');
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
			$('#bottom-row').css('display', 'none')
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
		notify('add', '');
	}
	if ($(this).data('cmd') == 'playall') {
		mpdDbCmd('playall', files);
		notify('add', '');
	}
	if ($(this).data('cmd') == 'clrplayall') {
		mpdDbCmd('clrplayall', files);
		notify('clrplay', '');
	}
});
