/*!
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

var LIB = {
    albumClicked: false,
    recentlyAddedClicked: false,
    currentDate: null,
    totalTime: 0,
    totalSongs: 0,
    artistClicked: '',
    filters: {artists: [], genres: [], albums: [],}
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
var filteredSongsAlbum = [];
var filteredAlbumCovers = [];

var miscLibOptions = [];

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

/*** ** * CUE HELPERS - BEGIN * ** ***/

function isTrackIndex(audioEntry) {
	let result = ('track' === audioEntry.substring(audioEntry.lastIndexOf('/') + 1).substr(0, 5));

	return result;
}

function isCUE(audioEntry) {
	let result = ('.cue' === audioEntry.substr(audioEntry.lastIndexOf('.'), 4));

	return result;
}

function getParentDirectory(audioEntry) {
	let audioEntryParent = audioEntry.substring(0, audioEntry.lastIndexOf('/'));
	if (isTrackIndex(audioEntry)) {
		if (isCUE(audioEntryParent)) {
			audioEntryParent = audioEntryParent.substring(0, audioEntryParent.lastIndexOf('/'));
		}
	}

	return audioEntryParent;
}

/*** ** * CUE HELPERS - END * ** ***/

function loadLibrary() {
    //console.log('loadLibrary(): loading=' + GLOBAL.libLoading, currentView);
    GLOBAL.libLoading = true;

    // miscLibOptions[0] Include comment tag Yes/No
    // miscLibOptions[1] Album key: Album@Artist | Album@Artist@AlbumID | FolderPath | FolderPath@AlbumID
    // Note: AlbumID refers to the MUSICBRAINZ_ALBUMID tag
    miscLibOptions = getMiscLibOptions();

    // Convert to Yes/No
    // miscLibOptions[1] Indicates whether MBRZ albumid tag is included in the album key
    // miscLibOptions[2] Created to indicate whether FolderPath is used in the album key
    miscLibOptions[2] = miscLibOptions[1].indexOf('FolderPath') != -1 ? 'Yes' : 'No';
    miscLibOptions[1] = miscLibOptions[1].indexOf('AlbumID') != -1 ? 'Yes' : 'No';

	var loading = setTimeout(function(){
	    if (currentView == 'tag' || currentView == 'album') {
	        notify(NOTIFY_TITLE_INFO, 'library_loading');
	    }
	}, 2000);

	$.getJSON('command/music-library.php?cmd=load_library', function(data) {
		clearTimeout(loading);
        $('#lib-content').show();
		renderLibrary(data);
		if (currentView == 'album' || currentView == 'tag') {
            setLibMenuAndHeader();
        }
        if (SESSION.json['lib_scope'] == 'recent') {
            $('.view-recents').click();
        }

        GLOBAL.libRendered = true;
        GLOBAL.libLoading = false;
	});
}

function renderLibrary(data) {
    //console.log(data);
	groupLib(data);
	filterLib();

	LIB.totalSongs = allSongs.length;

	renderGenres();
}

// Rewritten by @Atair: Allow for track.genre as array of genre values
function reduceGenres(acc, track) {
	for (i = 0; i < track.genre.length; i++) {
		var genre = track.genre[i].toLowerCase();
		if (!acc[genre]) {
			acc[genre] = [];
			acc[genre].genre = track.genre[i];
		}
		acc[genre].push(track);
	}
	return acc;
}

// Rewritten by @scripple
// NOTE: This routine and associated marked code blocks provide a flexible way to populate
// the artist list in Tag view based on the value of the library_tagview_artist setting.
// Artist:
// List all Artists (Artist, Album Artist, Composer, Conductor). Compilation albums listed for
// a selected artist will show only the tracks belonging to the Artist. Clicking the Album will
// toggle between showing all the album's tracks and just those for the selected artist.
// Artist (Strict):
// List only Artists (the artist tag). Compilation albums are handles same as above.
// Album Artist:
// List all Album Artists. Compilation albums are listed under the Album Artist named "Various Artists"
// or any other string that was used to identify compilation albums. This is the old 671 behavior.
// Album Artist+:
// List all Album Artists. Compilation albums listed for a selected Album Artist will show only
// the tracks belonging to the Album Artist. Clicking the Album will toggle between showing all
// the album's tracks and just those for the selected Album Artist.
function reduceArtists(acc, track) {
	if (track.album_artist &&
        SESSION.json['library_tagview_artist'] != 'Artist (Reduced)' &&
        SESSION.json['library_tagview_artist'] != 'Artist (Strict)') {
		var album_artist = (track.album_artist).toLowerCase();
		if (!acc[album_artist]) {
			acc[album_artist] = [];
			acc[album_artist].artist = track.album_artist;
		}
		acc[album_artist].push(track);
		if (SESSION.json['library_tagview_artist'].includes('Album Artist')) {
            // Album Artist or Album Artist+
			return acc;
		}
	}
	else {
		// track.album_artist not set, define album_artist for comparison below
		var album_artist = null;
	}

	// Rewritten by @Atair: Allow for track.artist as array of artist values
	if (track.artist) {
		for (var i=0; i<track.artist.length; i++) {
			var artist = track.artist[i].toLowerCase();
			if (artist != album_artist) {
				if (!acc[artist]) {
					acc[artist] = [];
					acc[artist].artist = track.artist[i];
				}
				acc[artist].push(track);
			}
		}
	}
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

// Return album_year if exists and not '' otherwise max of track years
function getYear(albumTracks){
    if (typeof(albumTracks[0].album_year) == 'undefined' || albumTracks[0].album_year == '') {
        var allYear = albumTracks.map(function(track){
            return track.year;
    	});
    	var year = Math.max.apply(null, allYear);
    } else {
        var year = albumTracks[0].album_year;
    }

    return year;
}

function getAlbumArtist(albumTracks){
    var allAlbumArtists = [];
    allAlbumArtists = albumTracks.reduce(function(acc,track){
        !acc.includes(track.album_artist) && acc.push(track.album_artist);
        return acc;
    },[]);
    return allAlbumArtists.length == 1 ? allAlbumArtists[0] : "Various";
}

function groupLib(fullLib) {
	allSongs = fullLib.map(function(track){
		var modifiedTrack = track;
		modifiedTrack.key = keyAlbum(track);
		return modifiedTrack;
	});

	allGenres = Object.values(allSongs.reduce(reduceGenres, {})).map(function(group){ return group.genre; });
    var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
    allGenres.sort(function(a, b) {
        return collator.compare(removeArticles(a), removeArticles(b));
    });

	allAlbums = Object.values(allSongs.reduce(reduceAlbums, {})).map(function(albumTracks){
		var file = findAlbumProp(albumTracks, 'file');
		var md5 = typeof(file) == 'undefined' ? 0 : $.md5(getParentDirectory(file));
		var year = getYear(albumTracks);

		return {
			key: findAlbumProp(albumTracks, 'key'),
			last_modified: getLastModified(albumTracks),
			year: year,
			album: findAlbumProp(albumTracks, 'album'),
			mb_albumid: findAlbumProp(albumTracks, 'mb_albumid'), // NOTE: mb_albumid if not present is set to '0' in genLibrary()
			genre: findAlbumProp(albumTracks, 'genre'),
			all_genres: Object.keys(albumTracks.reduce(reduceGenres, {})), // NOTE: @Atair: albumArtist is always defined due to provisions in inc/music-library php so it is not necessary to evaluate artist
			album_artist: getAlbumArtist(albumTracks),
			imgurl: md5 != 0 ? '/imagesw/thmcache/' + encodeURIComponent(md5) + '.jpg' : '/images/notfound.jpg',
			encoded_at: findAlbumProp(albumTracks, 'encoded_at'),
			comment: findAlbumProp(albumTracks, 'comment')
		};
	});

	allAlbumCovers = allAlbums.slice();

    // Natural ordering
	// @Atair: Sorting by artist makes no sense when a song has multiple artists.
    // Due to code in inc/music-library php album_artist is never empty anyway,
	// so it is safe to change the constructs like a['album_artist'] || a['artist'] just to a['album_artist'].
	// and sort by album_artist only
	allSongs.sort(function(a, b) {
		return collator.compare(removeArticles(a['album_artist']), removeArticles(b['album_artist']));
	});

    switch (SESSION.json['library_albumview_sort']) {
        case 'Album':
            allAlbumCovers.sort(function(a, b) {
                return collator.compare(removeArticles(a['album']), removeArticles(b['album']));
            });
            break;
        case 'Artist':
            // @Atair: Sort by album_artist
            allAlbumCovers.sort(function(a, b) {
                return (collator.compare(removeArticles(a['album_artist']), removeArticles(b['album_artist'])) || collator.compare(removeArticles(a['album']), removeArticles(b['album'])));
    		});
            break;
        case 'Artist/Year':
            allAlbumCovers.sort(function(a, b) {
                return (collator.compare(removeArticles(a['album_artist']), removeArticles(b['album_artist'])) || collator.compare(a['year'],b['year']));
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
            // @Atair: 'artist' is here actually album_artist due the way allAlbums were defined above.
            allAlbums.sort(function(a, b) {
				return (collator.compare(removeArticles(a['album_artist']), removeArticles(b['album_artist'])) || collator.compare(removeArticles(a['album']), removeArticles(b['album'])));
			});
            break;
        case 'Artist/Year':
            allAlbums.sort(function(a, b) {
                return (collator.compare(removeArticles(a['album_artist']), removeArticles(b['album_artist'])) || collator.compare(a['year'],b['year']));
            });
            break;
        case 'Year':
            allAlbums.sort(function(a, b) {
                return (collator.compare(a['year'], b['year']) || collator.compare(removeArticles(a['album']), removeArticles(b['album'])));
            });
            break;
    }
}

// Rewritten by @Atair: Allow for item.genre as array of genre values
function filterByGenre(item) {
	var genre = item.genre.map(function(g){return g.toLowerCase();});
	result= LIB.filters.genres.find(function(genreFilter){
		return genre.find(function(g){
			return g == genreFilter.toLowerCase();
		});
	});
	return result;
}

function filterByAllGenres(album) {
	return LIB.filters.genres.find(function(genreFilter){
		return album.all_genres.includes(genreFilter.toLowerCase());
	});
}

//Rewritten by @Atair: Allow for artists as array of artist values
function filterByArtist(item) {
	if (SESSION.json['library_tagview_artist'] == 'Album Artist') {
		var album_artist = item.album_artist.toLowerCase();
		return LIB.filters.artists.find(function(artistFilter){
			var artistFilterLower = artistFilter.toLowerCase();
			return album_artist === artistFilterLower;
		});
	} else {
		var artist = item.artist.map(function(a){return a.toLowerCase();});
		artist.push(item.album_artist.toLowerCase())
		result = LIB.filters.artists.find(function(artistFilter){
			return artist.find(function(a){
				var artistFilterLower = artistFilter.toLowerCase();
				return a === artistFilterLower;
			});
		});
		return result;
	}
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
	// @scripple: Add sort
	// Natural ordering
	// @Atair: flatten out artist arrays
	var filteredArtistsFlat = [];
	filteredArtists.forEach((a)=>{filteredArtistsFlat=filteredArtistsFlat.concat(a)});
	filteredArtists = filteredArtistsFlat.slice();

	var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
	filteredArtists.sort(function(a, b) {
		return collator.compare(removeArticles(a).toLowerCase(), removeArticles(b).toLowerCase());
	});
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
		// @scripple:
        /*if (SESSION.json['library_tagview_artist'] == 'Album Artist') {
			filteredAlbums = filteredAlbums.filter(filterByArtist);
			filteredAlbumCovers = filteredAlbumCovers.filter(filterByArtist);
		}
        // Artist or Album Artist+
		// @Atair: deactivated condition, because
		//         when Folderpath album key is set and
		//         an album contains tracks with different album_artists (which all appear in the artists column),
		//         the album would not be displayed for either album_artist when being clicked.
        //         The else case might be more expensive, but does  basically the same job as the if clause,
        //         but for all artists
		else {*/
			var artistSongs = allSongs.filter(filterByArtist);
			var songKeys = artistSongs.map(function(a) {return a.key;});
			filteredAlbums = filteredAlbums.filter(function(item){return songKeys.includes(keyAlbum(item));});
			filteredAlbumCovers = filteredAlbumCovers.filter(function(item){return songKeys.includes(keyAlbum(item));});
        //}
	}
    // Filter by file last-updated timestamp
    if (LIB.recentlyAddedClicked) {
        LIB.currentDate = new Date();
        filteredAlbums = filteredAlbums.filter(filterAlbumsByDate);
        filteredAlbumCovers = filteredAlbumCovers.filter(filterAlbumsByDate);

        // Sort descending
        var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
        filteredAlbums.sort(function(a, b) {
            return collator.compare(b['last_modified'].getTime(), a['last_modified'].getTime());
        });
        filteredAlbumCovers.sort(function(a, b) {
            return collator.compare(b['last_modified'].getTime(), a['last_modified'].getTime());
        });
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
	return SESSION.json['library_ignore_articles'] != 'None' ? string.replace(GLOBAL.regExIgnoreArticles, '$2') : string;
}

// Generate album key
function keyAlbum(obj) {
    //console.log(obj);

    // To handle no filter results
    if (obj.album == 'Nothing found') {
        return;
    }

    if (miscLibOptions[2] == 'Yes') { // Folder path albumkey
        // Use folder path
        if (typeof(obj.file) != 'undefined') {
            var md5 = $.md5(obj.file.substring(0,obj.file.lastIndexOf('/')));
        }
        else if (typeof(obj.imgurl) != 'undefined') {
            var md5 = obj.imgurl.substring(obj.imgurl.lastIndexOf('/') + 1, obj.imgurl.indexOf('.jpg'));
        }
        return obj.album.toLowerCase() + '@' + md5 + '@' + obj.mb_albumid;
    }
    else {
        // Use album_artist || artist
        return obj.album.toLowerCase() + '@' + (obj.album_artist || obj.artist).toLowerCase() + '@' + obj.mb_albumid;
    }
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
    //console.log(event);
	if (item == undefined) {
		// All
		currentFilter.length = 0;
	}
    // metaKey is true when Command-click on Mac
	else if (event.ctrlKey || event.metaKey) {
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

	var element = document.getElementById('genresList');
	element.innerHTML = output;
	if (UI.libPos[0] == -2) {
		$('#lib-genre').scrollTo(0, 200);
	}

	renderArtists();
}

var renderArtists = function() {
	var output = '';

	for (var i = 0; i < filteredArtists.length; i++) {
		// NOTE: Add "|| filteredArtists.length = 1" to automatically highlight if only 1 artist in list
		output += '<li class="lib-entry'
			+ ((LIB.filters.artists.indexOf(filteredArtists[i]) >= 0 || filteredArtists.length == 1) ? ' active' : '')
			+ '">' + filteredArtists[i] + '</li>';
	}

	var element = document.getElementById('artistsList');
	element.innerHTML = output;

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
	var tagViewComment = '';   // For display of Artist (Year) - Comment in Tag View
	var albumViewComment = '';  // For display of Artist (Year) - Comment in Album View

    if (GLOBAL.nativeLazyLoad) {
    	var tagViewLazy = '<img loading="lazy" src="';
        var albumViewLazy = '<div class="thumbHW"><img loading="lazy" src="' ;
    }
    else {
    	var tagViewLazy = '<img class="lazy-tagview" data-original="';
    	var albumViewLazy = '<div class="thumbHW"><img class="lazy-albumview" data-original="';
    }

    // SESSION.json['library_encoded_at']
    // 0 = No (searchable)
    // 1 = HD only
    // 2 = Text
    // 3 = Badge
    // 9 = No
    var encodedAtOption = parseInt(SESSION.json['library_encoded_at']);
    var tagViewHdDiv = '';
    var tagViewNvDiv = '';
    var albumViewNvDiv = '';
    var albumViewHdDiv = '';
    var albumViewTxDiv = '';
    var albumViewBgDiv = '';

	for (var i = 0; i < filteredAlbums.length; i++) {
        filteredAlbums[i].year ? tagViewYear = '(' + filteredAlbums[i].year.toString().slice(0, 4) + ')' : tagViewYear = '';
        filteredAlbumCovers[i].year ? albumViewYear = '(' + filteredAlbumCovers[i].year.toString().slice(0, 4) + ')' : albumViewYear = '';
        if (miscLibOptions[0] == 'Yes') { // Comment tag included
            filteredAlbums[i].comment ? tagViewComment = filteredAlbums[i].comment : tagViewComment = '';
            filteredAlbumCovers[i].comment ? albumViewComment = filteredAlbumCovers[i].comment : albumViewComment = '';
        }

        // encoded_at:
        // [0] bits/rate format
        // [1] flag: "l" lossy, "s" standard def or "h" high def
        // [2] channels
        if (encodedAtOption && encodedAtOption != 9) {
            // Tag view
            var encodedAt = filteredAlbums[i].encoded_at.split(',');
            var tagViewHdDiv = encodedAtOption == 1 && encodedAt[1] == 'h' ?
                '<div class="lib-encoded-at-hdonly-tagview">' + albumHDBadge(encodedAt[0].split(' ')[1]) + '</div>' : '';
            var tagViewNvDiv = encodedAtOption <= 1 ? '<div class="lib-encoded-at-notvisible">' + filteredAlbums[i].encoded_at.split(',')[0] + '</div>' : '';
            // Album view
            var encodedAt = filteredAlbumCovers[i].encoded_at.split(',');
            var albumViewNvDiv = encodedAtOption <= 1 ? '<div class="lib-encoded-at-notvisible">' + filteredAlbumCovers[i].encoded_at.split(',')[0] + '</div>' : '';
            var albumViewHdDiv = encodedAtOption == 1 && encodedAt[1] == 'h' ?
                '<div class="lib-encoded-at-hdonly">' + albumHDBadge(encodedAt[0].split(' ')[1]) + '</div>' : '';
            var albumViewTxDiv = encodedAtOption == 2 ? '<div class="lib-encoded-at-text">' + encodedAt[0] + '</div>' : '';
            var albumViewBgDiv = encodedAtOption == 3 ? '<div class="lib-encoded-at-badge">' + encodedAt[0] + '</div>' : '';
        }

        // NOTE: To handle no filter results we want artist to be '' for display
		if (SESSION.json['library_tagview_covers'] == 'Yes') {
			output += '<li class="lib-entry">'
                + tagViewLazy + filteredAlbums[i].imgurl + '">'
                + tagViewHdDiv
                + '<div class="tag-cover-text"><span class="album-name-art">' + filteredAlbums[i].album + '</span>'
                + '<span class="artist-name-art">' + filteredAlbums[i].album_artist + '</span>' // @Atair: Should be album_artist
                + '<span class="album-year">' + tagViewYear + '</span><span class="album-comment">' + tagViewComment + '</span></div>'
                + tagViewNvDiv
                + '</li>';
        }
		else {
			output += '<li class="lib-entry no-tagview-covers">'
                + '<span class="album-name">' + filteredAlbums[i].album
                + '</span><span class="artist-name-art">' + filteredAlbums[i].album_artist + '</span><span class="album-year">' + tagViewYear + '</span><span class="album-comment">' + tagViewComment + '</span>' + '</li>'  // @Atair: Should be album_artist
        }

		output2 += '<li class="lib-entry">'
            + albumViewLazy + filteredAlbumCovers[i].imgurl + '"></div>'
            + '<div class="cover-menu" data-toggle="context" data-target="#context-menu-lib-album"></div>'
			+ albumViewHdDiv
			+ albumViewBgDiv
            + '<span class="album-name">' + filteredAlbumCovers[i].album + '</span>'
            + '<div class="artyear"><span class="artist-name">' + filteredAlbumCovers[i].album_artist + '</span><span class="album-year">' + albumViewYear + '</span><span class="album-comment">' + albumViewComment + '</span></div>'
            + albumViewTxDiv
            + albumViewNvDiv
            + '</li>';
	}

    // Output the lists
	var element = document.getElementById('albumsList');
	element.innerHTML = output;
	var element = document.getElementById('albumcovers');
	element.innerHTML = output2;

	// If only 1 album automatically highlight and display tracks
	if (filteredAlbums.length == 1) {
	    $('#albumsList li').addClass('active');
		LIB.albumClicked = true;
        UI.libPos[0] = 0;
	}

    // Set ellipsis text
	if (SESSION.json["library_ellipsis_limited_text"] == "Yes") {
		$('#library-panel, #radio-panel, #playlist-panel').addClass('limited');
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

    // TODO: Investigate why JQuery .lazyload() is used and not the lazylode() function
	// Start lazy load
	if ($('.album-view-btn').hasClass('active')) {
        //lazylode('album');
		$('img.lazy-albumview').lazyload({
			container: $('#lib-albumcover')
		});
	}
	else if ($('.tag-view-btn').hasClass('active') && SESSION.json['library_tagview_covers'] == 'Yes') {
        //lazylode('tag');
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

    // Sort by album key, disc, tracknum
    // NOTE: This is mainly a CYA for badly tagged tracks
    var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
    filteredSongs.sort(function(a, b) {
        return (collator.compare(a['key'], b['key']) || collator.compare(a['disc'], b['disc']) || collator.compare(a['tracknum'], b['tracknum']));
    });

    if (LIB.artistClicked == true || LIB.albumClicked == true) {
        // Order the songs according the the order of the albums
        var orderedSongs = [];
        if (LIB.albumClicked == true) {
            for (j = 0; j < filteredSongs.length; j++) {
                if (filteredSongs[j].key == filteredAlbums[UI.libPos[0]].key) {
                    orderedSongs.push(filteredSongs[j]);
                }
            }
        }
        else {
            for (i = 0; i < filteredAlbums.length; i++) {
                for (j = 0; j < filteredSongs.length; j++) {
                    if (filteredSongs[j].key == filteredAlbums[i].key) {
                        orderedSongs.push(filteredSongs[j]);
                    }
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
                //console.log('Multi-disc: ' + filteredSongs[i].album);
                multiDisc.push(filteredSongs[i].album);
            }
            lastAlbum = filteredSongs[i].album;
            lastDisc = filteredSongs[i].disc;
        }

        // Render the song list
        lastAlbum = '';
        lastDisc = '';
		for (i = 0; i < filteredSongs.length; i++) {
			var songyear = filteredSongs[i].year ? filteredSongs[i].year.slice(0, 4) : ' ';

            // Optionally append either comment or MBRZ albumid to the album header
            if (miscLibOptions[0] == 'Yes') { // Comment tag included
                var comment = filteredSongs[i].comment != '' ? ' (' + filteredSongs[i].comment + ')' : '';
            }
            else if (miscLibOptions[1] == 'Yes') { // MBRZ albumid tag included
                var comment = filteredSongs[i].mb_albumid != '0' ? ' (' + filteredSongs[i].mb_albumid.slice(0, 8) + ')' : '';
            }
            else {
                var comment = '';
            }
            var album = filteredSongs[i].album + comment;

            if (album != lastAlbum) {
                albumDiv = '<div id=lib-album-' + (i + 1).toString() +
                    ' class="lib-album-heading"' +
                    ' heading-album="' + filteredSongs[i].album + '"' +
                    ' heading-comment="' + filteredSongs[i].comment + '"' +
                    ' heading-key="' + filteredSongs[i].mb_albumid + '"' +
                    '><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-lib-album-heading">' + album + '</a></div>';
                lastAlbum = album;
            }
            else {
                albumDiv = '';
            }

            if (multiDisc.indexOf(filteredSongs[i].album) != -1) {
                if (filteredSongs[i].disc != lastDisc) {
    				discDiv = '<div id="lib-disc-' + filteredSongs[i].disc +
                        '" class="lib-disc"' +
                        ' heading-album="' + filteredSongs[i].album + '"' +
                        ' heading-disc="' + filteredSongs[i].disc + '"' +
                        ' heading-comment="' + filteredSongs[i].comment + '"' +
                        ' heading-key="' + filteredSongs[i].mb_albumid + '"' +
                        '><a class="btn" href="#notarget" data-toggle="context" data-target="#context-menu-lib-disc">Disc ' + filteredSongs[i].disc + '</a></div>'
    				lastDisc = filteredSongs[i].disc;
    			}
    			else {
    				discDiv = '';
    			}
            }

			var composer = filteredSongs[i].composer == 'Composer tag missing' ? '</span>' : '<br><span class="songcomposer">' + filteredSongs[i].composer + '</span></span>';
            var npIcon = (filteredSongs[i].title == MPD.json['title'] && filteredSongs[i].album == MPD.json['album'] && (MPD.json['disc'] == "Disc tag missing" || 1 * filteredSongs[i].disc == 1 * MPD.json['disc']) && 1 * filteredSongs[i].tracknum == 1 * MPD.json['track'] && MPD.json['state'] == 'play') ? ' lib-track-npicon' : '';

            output += albumDiv
                + discDiv
                + '<li id="lib-song-' + (i + 1) + '" class="clearfix lib-track" data-toggle="context" data-target="#context-menu-lib-item">'
    			+ '<div class="lib-entry-song"><span class="songtrack' + npIcon + '">' + filteredSongs[i].tracknum + '</span>'
    			+ '<span class="songname">' + filteredSongs[i].title + '</span>'
    			+ '<span class="songtime"> ' + filteredSongs[i].time_mmss + '</span>'

                // TEST: Composers in artist list (see inc/music-library php function genFlatList())
    			//+ '<span class="songartist"> ' + filteredSongs[i].artist.filter(artist => artist.substr(0, 4) != '[c] ').join(', ') + composer // @Atair: Show all artists

                + '<span class="songartist"> ' + filteredSongs[i].artist.join(', ') + composer // @Atair: Show all artists
    			+ '<span class="songyear"> ' + songyear + '</span></div>'
    			+ '</li>';

			LIB.totalTime += parseSongTime(filteredSongs[i].time);
		}
	}
	else {
		for (i = 0; i < filteredSongs.length; i++) {
			LIB.totalTime += parseSongTime(filteredSongs[i].time);
		}
	}
	var element = document.getElementById('songsList');
	element.innerHTML = output;

    // Display album name heading:
    // - if more than 1 album for clicked artist
    // - if first song musicbrainz_albumid != '0'
	if ((filteredAlbums.length > 1 && LIB.artistClicked == true && LIB.albumClicked == false) ||
        filteredSongs[0].mb_albumid != '0') {
		$('.lib-album-heading').css('display', 'block');
	}

	// Display disc num if more than 1 disc, exceot for case: Album name contains the string '[Disc' which indicates separate albums for each disc
	if (lastDisc > 1 && !filteredSongs[0].album.toLowerCase().includes('[disc')) {
		$('.lib-disc').css('display', 'block');
	}

	// Cover art and metadata for Tag and Album views
	if (filteredAlbums.length == 1 || LIB.filters.albums.length || typeof(albumPos) !== 'undefined') {
		$('#lib-coverart-img').html('<a href="#notarget" data-toggle="context" data-target="#context-menu-lib-album">' +
			'<img class="lib-coverart" src="' + filteredAlbums[UI.libPos[0]].imgurl + '" ' + 'alt="Cover art not found"' + '></a>');

		if (albumPos && !UI.libPos[0]) {
			artist = filteredAlbums[UI.libPos[0]].album_artist; // @Atair: album_artist !
		} else {
			artist = filteredSongs[0].album_artist; // @Atair: album_artist !
		}
        if (filteredSongs[0].album == 'Nothing found') {
            $('#albumsList .lib-entry, #artistsList .lib-entry').removeClass('active');
            $('.lib-encoded-at-hdonly, lib-encoded-at-hdonly-tagview, .lib-encoded-at-text, .lib-encoded-at-badge').text('');
            $('.tag-cover-text').css('transform', 'translateY(0.5em)');
            $('#songsList, #lib-collection-stats').text('');
            $('#lib-coverart-img a, .cover-menu').attr('data-target', '#');
        } else {
    		$('#lib-collection-stats').html(
                formatNumCommas(filteredSongs.length)
                + ((filteredSongs.length == 1) ? ' track, ' : ' tracks, ')
                + formatLibTotalTime(LIB.totalTime)
            );
        }
	} else {
		var album = LIB.filters.genres.length ? LIB.filters.genres : (LIB.filters.artists.length ? LIB.filters.artists : 'Music Library');
		var artist = LIB.filters.genres.length ? LIB.filters.artists : '';

        if (LIB.filters.artists.length > 0) {
            var artistName = LIB.filters.artists.length == 1 ? LIB.filters.artists[0] : 'Multiple Artists Selected';
            $('#lib-coverart-img').html(
                '<img class="lib-artistart" src="' + filteredAlbums[0].imgurl + '" ' + 'alt="Cover art not found"' + '>' +
                '<button class="btn" id="tagview-text-cover" data-toggle="context" data-target="#context-menu-lib-album">' +
                artistName + '</button>'
            );
            artist = '';
        } else if (LIB.filters.genres.length > 0) {
            var genreName = LIB.filters.genres.length == 1 ? LIB.filters.genres[0] : 'Multiple Genres Selected';
            $('#lib-coverart-img').html('<button class="btn" id="tagview-text-cover" data-toggle="context" data-target="#context-menu-lib-album">' + genreName + '</button>');
        } else {
            if (SESSION.json['library_flatlist_filter'] != 'full_lib') {
                var libFilter = '<div id="lib-flatlist-filter"><i class="fa-regular fa-sharp fa-filter"></i> Filtered</div>';
            } else {
                var libFilter = '';
            }
            $('#lib-coverart-img').html('<button class="btn" id="tagview-text-cover" data-toggle="context" data-target="#context-menu-lib-album">' +
                'Music Collection' + libFilter + '</button>');
        }
        $('#lib-collection-stats').html(
            formatNumCommas(filteredAlbums.length)
            + ' albums, '
            + formatNumCommas(filteredSongs.length)
            + ((filteredSongs.length == 1) ? ' track, ' : ' tracks, ')
            +  '<br>'
            + formatLibTotalTime(LIB.totalTime)
        );
	}
}

// Click genre or menu header (reset all)
$('#genreheader, #library-header').on('click', function(e) {
    if (SESSION.json['library_flatlist_filter'] != 'full_lib' && $(e.target).parent('#genreheader').length == 0 && GLOBAL.musicScope == 'all') {
        SESSION.json['lib_active_search'] = LIB_FULL_LIBRARY;
		applyLibFilter('full_lib');
		return;
	}
	else if (currentView == 'tag' || currentView == 'album') {
		LIB.filters.genres.length = 0;
		LIB.filters.artists.length = 0;
		LIB.filters.albums.length = 0;
		LIB.artistClicked = false;
        LIB.albumClicked = false;
        $('#tracklist-toggle').html('<i class="fa-regular fa-sharp fa-list sx"></i> Show tracks');
        if ($('#lib-album-filter').val() != '') {
            $('#searchResetLib').show();
        }
        else {
            $("#searchResetLib").hide();
    		showSearchResetLib = false;
        }
		if (GLOBAL.musicScope == 'recent') {
			GLOBAL.musicScope = 'all';
		}
		if (currentView == 'album') {
			$('#albumcovers .lib-entry').removeClass('active');
			$('#bottom-row').css('display', '');
			$('#lib-albumcover').css('height', '100%');
		}
		setLibMenuAndHeader();
	}
	else if (currentView == 'radio') {
		GLOBAL.searchRadio = '';
		$('#btn-ra-search-reset').click();
		setLibMenuAndHeader();
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
    setLibMenuAndHeader();
});

// Click album or album cover header
$('#albumheader').on('click', '.lib-heading', function(e) {
    LIB.albumClicked = false;
	LIB.filters.albums.length = 0;
    UI.libPos[0] = -2;
    UI.libPos[1] = -2;
	clickedLibItem(e, undefined, LIB.filters.albums, renderAlbums);
	storeLibPos(UI.libPos);
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
    setLibMenuAndHeader();
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
	setLibMenuAndHeader();
});

// Click album
$('#albumsList').on('click', '.lib-entry', function(e) {
	var pos = $('#albumsList .lib-entry').index(this);
    LIB.albumClicked = true;
	UI.libPos[0] = pos;
	UI.libPos[1] = filteredAlbumCovers.map(function(e) {return e.key;}).indexOf(filteredAlbums[pos].key);
	var albumobj = filteredAlbums[pos];
	var album = filteredAlbums[pos].album;
	// Store the active state before it gets set below
    var alreadyActive = this.className.includes('active')
	storeLibPos(UI.libPos);
	$('#albumsList .lib-entry').removeClass('active');
	$('#albumsList .lib-entry').eq(pos).addClass('active');

	// If a compilation album is already selected (active) but for only a
	// subset of the artists such that some tracks are not shown clicking
	// the album will cause the full track list for the album to populate
	// the song list.
	// Clicking again will contract back to just the selected artists.

    if (alreadyActive && LIB.filters.artists.length && !LIB.filters.artists.includes(albumobj.album_artist)) {
		// In case of folder path key it is easier to check expanded state by # of songs.
		// Also more than one album_artist per album can be rendered correctly then.
		if (miscLibOptions[2] == 'Yes') { // folder path key true
			// Calculate # of songs in selected album
			var albumSongs = allSongs.filter(song => song.key === albumobj.key);
			if (filteredSongs.length < albumSongs.length) {
				// ==> not expanded
				// retrieve all album_artists and push
				var albumArtists = albumSongs.map(song => song.album_artist);
				var n = LIB.filters.artists.length;
				albumArtists.forEach(artist => (!LIB.filters.artists.includes(artist) && LIB.filters.artists.push(artist)));
				filterSongs();
				LIB.filters.artists = LIB.filters.artists.slice(0,n);
				// Sort songs correctly even when the tracks of an album have different album_artists
				filteredSongs.sort((a,b)=>{return a.tracknum - b.tracknum}).sort((a,b)=>{return a.disc - b.disc});
				renderSongs();
			} else {
				filterSongs();
				renderSongs();
			}
		} else {
			// @Atair: Allow for arrays of multiple artists
			var displayedArtists = filteredSongs.map(
				function getArtist(a) {
					return a.artist;
				});
			// Have to do this check because someone might have
			// ctrl+clicked to select multiple artists which may
			// or may not be on the same displayed albums.
			// So we can't just use the count.
			var expanded = false;
			// @Atair: flatten out arrays
			var filteredArtistsFlat = [];
			LIB.filters.artists.forEach((a)=>{filteredArtistsFlat=filteredArtistsFlat.concat(a)});
			// @Atair: check whether tracks' set of artists and set of filtered artists are disjunct ==> track list is expanded
			for(let trackArtists of displayedArtists) {
				var intersection = 0;
				for(let filteredArtist of filteredArtistsFlat) {
					if(trackArtists.includes(filteredArtist)) {
						intersection++;
					}
				}
				if (intersection == 0) {
					expanded = true;
					break;
				}
			}
			if (expanded) {
				filterSongs();
				renderSongs()
			}
				else {
				LIB.filters.artists.push(albumobj.album_artist);
				filterSongs();
				LIB.filters.artists.pop();
				renderSongs()
			}
		}
    }
    else {
		clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);
	}
	$('#bottom-row').css('display', 'flex')
	$('#lib-file').scrollTo(0, 200);
	UI.libAlbum = album;
});

// Click random album button
$('#random-album').click(function(e) {

	// remove old active classes...
	$('#albumsList .lib-entry').eq(UI.libPos[0]).removeClass('active');
	$('#albumcovers .lib-entry').eq(UI.libPos[1]).removeClass('active');

	var array = new Uint16Array(1);
    LIB.albumClicked = true;
	window.crypto.getRandomValues(array);
	pos = Math.floor((array[0] / 65535) * filteredAlbums.length);

	if (currentView == 'tag') {
		var itemSelector = '#albumsList .lib-entry';
		var scrollSelector = 'albums';
		UI.libPos[0] = pos;
		UI.libPos[1] = filteredAlbumCovers.map(function(e) {return e.key;}).indexOf(filteredAlbums[pos].key);
		var albumobj = filteredAlbums[pos];
	}
	else {
		var itemSelector = '#albumcovers .lib-entry';
		var scrollSelector = 'albumcovers';
		UI.libPos[0] = filteredAlbums.map(function(e) {return e.key;}).indexOf(filteredAlbumCovers[pos].key);
		UI.libPos[1] = pos;
		var albumobj = filteredAlbumCovers[pos];
	}

	storeLibPos(UI.libPos);

	//$(itemSelector).removeClass('active');
	$(itemSelector).eq(pos).addClass('active');
	customScroll(scrollSelector, pos, 0);

	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);
});

// Click album cover menu button
$('#albumcovers').on('click', '.cover-menu', function(e) {
	var pos = $(this).parents('li').index();
    LIB.albumClicked = true;

	$('#albumcovers .lib-entry').eq(UI.libPos[1]).removeClass('active');
	$('#tracklist-toggle').show();
    $('#one-touch-action-li').hide();

	UI.libPos[0] = filteredAlbums.map(function(e) {return e.key;}).indexOf(filteredAlbumCovers[pos].key);
	UI.libPos[1] = pos;
	storeLibPos(UI.libPos);
	$('#albumcovers .lib-entry').eq(UI.libPos[1]).addClass('active');

	var albumobj = filteredAlbumCovers[pos];

	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);
});

// Click album cover
$('#albumcovers').on('click', 'img', function(e) {
    if ($('#albumcovers .lib-entry .album-name').eq(UI.libPos[1]).text() == 'Nothing found') {
        return false;
    }

	var pos = $(this).parents('li').index();
    var posChange = pos != UI.libPos[1] ? true : false;
    LIB.albumClicked = true;

	$('#albumcovers .lib-entry').eq(UI.libPos[1]).removeClass('active');

	UI.libPos[0] = filteredAlbums.map(function(e) {return e.key;}).indexOf(filteredAlbumCovers[pos].key);
	UI.libPos[1] = pos;
	storeLibPos(UI.libPos);
	$('#albumcovers .lib-entry').eq(UI.libPos[1]).addClass('active');

	var albumobj = filteredAlbumCovers[pos];

	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);

	var files = [];
	for (var i in filteredSongs) {
		files.push(filteredSongs[i].file);
	}

    if (SESSION.json['library_onetouch_album'] != 'No action') {
        if (SESSION.json['library_onetouch_album'] == 'Add' || SESSION.json['library_onetouch_album'] == 'Add next') {
            var queueCmd = SESSION.json['library_onetouch_album'] == 'Add' ? 'add_group' : 'add_group_next';
            sendQueueCmd(queueCmd, files);
            notify(NOTIFY_TITLE_INFO, queueCmd, NOTIFY_DURATION_SHORT);
        }
        else if (SESSION.json['library_onetouch_album'] == 'Play' || SESSION.json['library_onetouch_album'] == 'Play next') {
            var queueCmd = SESSION.json['library_onetouch_album'] == 'Play' ? 'play_group' : 'play_group_next';
            sendQueueCmd(queueCmd, files);
        }
        else if (SESSION.json['library_onetouch_album'] == 'Clear/Play') {
            sendQueueCmd('clear_play_group', files);
        }
        else if (SESSION.json['library_onetouch_album'] == 'Show tracks') {
            showHideTracks(posChange);
        }
    }
});

// Random album instant play button on Playback
$('.ralbum').click(function(e) {
    if (SESSION.json['library_onetouch_album'] != 'No action') {
		$('#albumsList .lib-entry').eq(UI.libPos[0]).removeClass('active');
		$('#albumcovers .lib-entry').eq(UI.libPos[1]).removeClass('active');

    	$('.ralbum svg').attr('class', 'spin');
    	setTimeout(function() {
    		$('.ralbum svg').attr('class', '');
    	}, RALBUM_TIMEOUT);

    	var array = new Uint16Array(1);
        LIB.albumClicked = true;
    	window.crypto.getRandomValues(array);
    	pos = Math.floor((array[0] / 65535) * filteredAlbums.length);

        UI.libPos[0] = pos;
    	UI.libPos[1] = filteredAlbumCovers.map(function(e) {return e.key;}).indexOf(filteredAlbums[pos].key);
    	var albumobj = filteredAlbums[pos];

    	clickedLibItem(e, keyAlbum(albumobj), LIB.filters.albums, renderSongs);

    	var files = [];
    	for (var i in filteredSongs) {
    		files.push(filteredSongs[i].file);
    	}

        if (SESSION.json['library_onetouch_album'] == 'Add' || SESSION.json['library_onetouch_album'] == 'Add next') {
            var queueCmd = SESSION.json['library_onetouch_album'] == 'Add' ? 'add_group' : 'add_group_next';
            sendQueueCmd(queueCmd, files);
            notify(NOTIFY_TITLE_INFO, queueCmd, NOTIFY_DURATION_SHORT);
        }
        // NOTE: Show tracks for Album view = Play for this button
        else if (SESSION.json['library_onetouch_album'] == 'Play' || SESSION.json['library_onetouch_album'] == 'Play next' ||
            SESSION.json['library_onetouch_album'] == 'Show tracks') {
            var queueCmd = (SESSION.json['library_onetouch_album'] == 'Play' || SESSION.json['library_onetouch_album'] == 'Show tracks') ?
                'play_group' : 'play_group_next';
            sendQueueCmd(queueCmd, files);
        }
        else if (SESSION.json['library_onetouch_album'] == 'Clear/Play') {
            sendQueueCmd('clear_play_group', files);
        }

		storeLibPos(UI.libPos);
    }
});

// Click radio station
$('#database-radio').on('click', 'img', function(e) {
    var pos = $(this).parents('li').index();
	var path = $(this).parents('li').data('path');

	UI.radioPos = pos;
	storeRadioPos(UI.radioPos)

    if (UI.dbEntry[3].substr(0, 3) == 'ra-') {
        $('#' + UI.dbEntry[3]).removeClass('active');
    } else {
        $('.database-radio li').removeClass('active');
    }
    UI.dbEntry[3] = $(this).parents('li').attr('id');
    $(this).parents('li').addClass('active');

    if (SESSION.json['library_onetouch_radio'] != 'No action') {
        if (SESSION.json['library_onetouch_radio'] == 'Add' || SESSION.json['library_onetouch_radio'] == 'Add next') {
            var queueCmd = SESSION.json['library_onetouch_radio'] == 'Add' ? 'add_item' : 'add_item_next';
            sendQueueCmd(queueCmd, path);
            notify(NOTIFY_TITLE_INFO, queueCmd, NOTIFY_DURATION_SHORT);
        }
        else if (SESSION.json['library_onetouch_radio'] == 'Play' || SESSION.json['library_onetouch_radio'] == 'Play next') {
            var queueCmd = SESSION.json['library_onetouch_radio'] == 'Play' ? 'play_item' : 'play_item_next';
            sendQueueCmd(queueCmd, path);
        }
        else if (SESSION.json['library_onetouch_radio'] == 'Clear/Play') {
            sendQueueCmd('clear_play_item', path);
            notify(NOTIFY_TITLE_INFO, 'clear_play_item', NOTIFY_DURATION_SHORT);
        }
    }

	setTimeout(function() {
        customScroll('radio', UI.radioPos + 1, 200);
	}, DEFAULT_TIMEOUT);
});

// Radio manager
$('#btn-ra-manager').click(function(e) {
    var sortGroup = SESSION.json['radioview_sort_group'].split(',');
    var showHide = SESSION.json['radioview_show_hide'].split(',');
    var monitorOpt = SESSION.json['mpd_monitor_opt'].split(',');
    $('#radioview-sort-tag span').text(sortGroup[0]);
    $('#radioview-group-method span').text(sortGroup[1]);
    $('#radioview-show-hide-moode span').text(showHide[0]);
    $('#radioview-show-hide-other span').text(showHide[1]);
    $('#import-export-msg').text('');
    $('#radioview-monitor-svc span').text(SESSION.json['mpd_monitor_svc']);
    $('#radioview-monitor-opt1 span').text((monitorOpt[0] == '6' ? '6 (Default)' : monitorOpt[0]));
    $('#radioview-monitor-opt2 span').text(monitorOpt[1]);
    $('#radioview-monitor-opt3 span').text((monitorOpt[2] == '3' ? '3 (Default)' : monitorOpt[2]));

    if (SESSION.json['feat_bitmask'] & FEAT_RECORDER) {
        if (SESSION.json['recorder_status'] == 'Not installed') {
            var recorderStatusList =
            '<li class="modal-dropdown-text"><a href="#notarget" data-cmd="recorder-status-sel"><span class="text">Not installed</span></a></li>' +
            '<li class="modal-dropdown-text"><a href="#notarget" data-cmd="recorder-status-sel"><span class="text">Install recorder</span></a></li>'
        }
        else {
            var recorderStatusList =
            '<li class="modal-dropdown-text"><a href="#notarget" data-cmd="recorder-status-sel"><span class="text">On</span></a></li>' +
            '<li class="modal-dropdown-text"><a href="#notarget" data-cmd="recorder-status-sel"><span class="text">Off</span></a></li>' +
            '<li class="modal-dropdown-text"><a href="#notarget" data-cmd="recorder-status-sel"><span class="text">Uninstall recorder</span></a></li>'
        }
        $('#recorder-status-list').html(recorderStatusList);
        $('#recorder-status span').text(SESSION.json['recorder_status']);
        $.getJSON('command/recorder-cmd.php?cmd=recorder_storage_paths', function(recorderStoragePaths) {
            $('#recorder-storage-list').html(recorderStoragePaths);
            $('#recorder-storage span').text(SESSION.json['recorder_storage']);
        });
        $.getJSON('command/recorder-cmd.php?cmd=recorder_album_tag_list', function(recorderAlbumTagList) {
            $('#recorder-album-tag-list').html(recorderAlbumTagList);
            $('#recorder-album-tag span').text(SESSION.json['recorder_album_tag']);
            $('#selected-album-tag').text(SESSION.json['recorder_album_tag']);
        });
        $.getJSON('command/recorder-cmd.php?cmd=recorder_untagged_file_list', function(recorderUntaggedFiles) {
            var count = recorderUntaggedFiles['count'] == '' ? '(0)' : '(' + recorderUntaggedFiles['count'] + ')';
            $('#untagged-file-count').text(count);
            var output = '<li class="modal-dropdown-text">' +
                '<a href="#notarget" data-cmd="delete-recordings-sel">' +
                '<span class="text">No</span></a></li>';
            output += '<li class="modal-dropdown-text">' +
                '<a href="#notarget" data-cmd="delete-recordings-sel">' +
                '<span class="text">' + (count == '0' ? '' : 'All') + '</span></a></li>';
            for (i = 0; i < recorderUntaggedFiles['files'].length; i++) {
                output += '<li class="modal-dropdown-text">' +
                    '<a href="#notarget" data-cmd="delete-recordings-sel">' +
                    '<span class="text">' + recorderUntaggedFiles['files'][i] + '</span></a></li>';
            }
            $('#delete-recordings span').text('No');
        	var element = document.getElementById('recorder-untagged-files');
        	element.innerHTML = output;
        });

        $('#radio-manager-modal').modal();
    }
    else {
        $('#radio-manager-modal').modal();
    }
});

// Update Radio manager
$('#btn-upd-radio-manager').click(function(e) {
    SESSION.json['radioview_sort_group'] = $('#radioview-sort-tag span').text() + ',' + $('#radioview-group-method span').text();
    SESSION.json['radioview_show_hide'] = $('#radioview-show-hide-moode span').text() + ',' + $('#radioview-show-hide-other span').text();

    var mpdMonitorSvcChange = SESSION.json['mpd_monitor_svc'] == $('#radioview-monitor-svc span').text() ? false : true;
    SESSION.json['mpd_monitor_svc'] = $('#radioview-monitor-svc span').text();

    var monitorOpt1 = $('#radioview-monitor-opt1 span').text() == '6 (Default)' ? '6' : $('#radioview-monitor-opt1 span').text();
    var monitorOpt2 = $('#radioview-monitor-opt2 span').text();
    var monitorOpt3 = $('#radioview-monitor-opt3 span').text() == '3 (Default)' ? '3' : $('#radioview-monitor-opt3 span').text();
    var mpdMonitorOpt = SESSION.json['mpd_monitor_opt'].split(',');
    if (mpdMonitorOpt[0] == monitorOpt1 && mpdMonitorOpt[1] == monitorOpt2 && mpdMonitorOpt[2] == monitorOpt3) {
        var mpdMonitorOptChange = false;
    } else {
        var mpdMonitorOptChange = true;
    }
    SESSION.json['mpd_monitor_opt'] = monitorOpt1 + ',' + monitorOpt2 + ',' + monitorOpt3;

    if (SESSION.json['feat_bitmask'] & FEAT_RECORDER) {
        var recorderStatus = $('#recorder-status span').text();
        var recorderStatusChange = SESSION.json['recorder_status'] != recorderStatus;
        var recorderStorageChange = SESSION.json['recorder_storage'] != $('#recorder-storage span').text() ? true : false;
        SESSION.json['recorder_status'] = ($('#recorder-status span').text() == 'Install recorder' || recorderStorageChange === true) ? 'Off' : $('#recorder-status span').text();
        SESSION.json['recorder_storage'] = $('#recorder-storage span').text();
        SESSION.json['recorder_album_tag'] = $('#recorder-album-tag span').text();
    }

    $.post('command/cfg-table.php?cmd=upd_cfg_system', {
        'radioview_sort_group': SESSION.json['radioview_sort_group'],
        'radioview_show_hide': SESSION.json['radioview_show_hide'],
        'recorder_status': SESSION.json['recorder_status'],
        'recorder_storage': SESSION.json['recorder_storage'],
        'recorder_album_tag': SESSION.json['recorder_album_tag'],
        'mpd_monitor_svc': SESSION.json['mpd_monitor_svc'],
        'mpd_monitor_opt': SESSION.json['mpd_monitor_opt']
         }, function() {
            if (SESSION.json['feat_bitmask'] & FEAT_RECORDER) {
                // Stream recorder
                if (recorderStatus == 'Install recorder') {
                    notify(NOTIFY_TITLE_INFO, 'installing_plugin', '', NOTIFY_DURATION_INFINITE);
                    $.ajax({
                		type: 'GET',
                		url: 'command/recorder-cmd.php?cmd=recorder_install',
                        dataType: 'json',
                		async: true,
                		cache: false,
                		success: function(msgKey) {
                            if (msgKey == 'recorder_installed') {
                                $('#stream-recorder-options, #context-menu-stream-recorder').show();
                                $.post('command/cfg-table.php?cmd=upd_cfg_system', {'recorder_storage': '/mnt/SDCARD'});
                                notify(NOTIFY_TITLE_INFO, msgKey, 'Restart the system to make the changes effective.', NOTIFY_DURATION_MEDIUM);
                            } else {
                                notify(NOTIFY_TITLE_INFO, msgKey);
                            }
                		},
                		error: function() {
                            // A 404 on recorder-cmd.php so we revert to 'not installed'
                            SESSION.json['recorder_status'] = 'Not installed';
                            $.post('command/cfg-table.php?cmd=upd_cfg_system', {'recorder_status': 'Not installed'});
                            notify(NOTIFY_TITLE_ALERT, 'recorder_plugin_na');
                		}
                	});
                } else if (recorderStatus == 'Uninstall recorder') {
                    $.post('command/recorder-cmd.php?cmd=recorder_uninstall');
                    $('#stream-recorder-options, #context-menu-stream-recorder').hide();
                    notify(NOTIFY_TITLE_INFO, 'recorder_uninstalled');
                } else if (recorderStorageChange === true) {
                    $.post('command/recorder-cmd.php?cmd=recorder_storage_change');
                    $('.playback-context-menu i').removeClass('recorder-on');
                    $('#menu-check-recorder').css('display', 'none');
                    notify(NOTIFY_TITLE_INFO, 'settings_updated', NOTIFY_DURATION_SHORT);
                } else if (recorderStatusChange && (recorderStatus == 'On' || recorderStatus == 'Off')) {
                    $.post('command/recorder-cmd.php?cmd=recorder_on_off');
                    if (recorderStatus == 'On') {
                        $('.playback-context-menu i').addClass('recorder-on');
                        $('#menu-check-recorder').css('display', 'inline');

                    } else {
                        $('.playback-context-menu i').removeClass('recorder-on');
                        $('#menu-check-recorder').css('display', 'none');
                    }
                    notify(NOTIFY_TITLE_INFO, 'settings_updated', 'Recorder ' + recorderStatus, NOTIFY_DURATION_SHORT);
                } else if ($('#tag-recordings span').text() == 'Yes') {
                    // NOTE: Completion message sent from back-end via sendFECmd()
                    notify(NOTIFY_TITLE_INFO, 'recorder_tagging', 'Wait until completion message appears.', NOTIFY_DURATION_INFINITE);
                    $('#tag-recordings span').text('No');
                    $.post('command/recorder-cmd.php?cmd=recorder_tag_files');
                } else if ($('#delete-recordings span').text() != 'No') {
                    var fileName = $('#delete-recordings span').text();
                    $.post('command/recorder-cmd.php?cmd=recorder_delete_files', {'file_name': fileName}, function() {
                        notify(NOTIFY_TITLE_INFO, 'recorder_deleted', 'Updating library...');
                    });
                    $('#delete-recordings span').text('No'); // Reset
                // Rest of radio manager (dup of below)
                } else if (mpdMonitorSvcChange || mpdMonitorOptChange) {
                    $.post('command/radio.php?cmd=mpd_monitor_svc',
                        {'svc': SESSION.json['mpd_monitor_svc'], 'opt': SESSION.json['mpd_monitor_opt']},
                        function() {
                            var msg = mpdMonitorSvcChange === true ? 'Monitor ' + SESSION.json['mpd_monitor_svc'] + '.' :
                            (SESSION.json['mpd_monitor_svc'] == 'On' ? 'Monitor restarted.' : '');
                            notify(NOTIFY_TITLE_INFO, 'settings_updated', msg, NOTIFY_DURATION_SHORT);
                        }
                    );
                } else {
                    notify(NOTIFY_TITLE_INFO, 'settings_updated', NOTIFY_DURATION_SHORT);
                }
            } else {
                // Rest of radio manager
                if (mpdMonitorSvcChange || mpdMonitorOptChange) {
                    $.post('command/radio.php?cmd=mpd_monitor_svc',
                        {'svc': SESSION.json['mpd_monitor_svc'], 'opt': SESSION.json['mpd_monitor_opt']},
                        function() {
                            var msg = mpdMonitorSvcChange === true ? 'Monitor ' + SESSION.json['mpd_monitor_svc'] + '.' :
                            (SESSION.json['mpd_monitor_svc'] == 'On' ? 'Monitor restarted.' : '');
                            notify(NOTIFY_TITLE_INFO, 'settings_updated', msg, NOTIFY_DURATION_SHORT);
                        }
                    );
                } else {
                    notify(NOTIFY_TITLE_INFO, 'settings_updated', NOTIFY_DURATION_SHORT);
                }
            }

            setTimeout(function() {
                $('#btn-ra-refresh').click();
            }, DEFAULT_TIMEOUT);
        }
    );
});

// Click playlist entry
$('#database-playlist').on('click', 'img', function(e) {
    var pos = $(this).parents('li').index();
	var path = $(this).parents('li').data('path');

	UI.playlistPos = pos;
	storePlaylistPos(UI.playlistPos)

    if (UI.dbEntry[3].substr(0, 9) == 'pl-entry-') {
        $('#' + UI.dbEntry[3]).removeClass('active');
    }
    UI.dbEntry[3] = $(this).parents('li').attr('id');
    $(this).parents('li').addClass('active');

    if (SESSION.json['library_onetouch_pl'] != 'No action') {
        if (SESSION.json['library_onetouch_pl'] == 'Show items') {
            $.post('command/playlist.php?cmd=get_playlist_contents', {'path': path}, function(data) {
                $('#view-playlist-name').text(path);

            	// Playlist items
            	var element = document.getElementById('view-playlist-items');
            	element.innerHTML = '';
                var output = '';

                if (data['items'].length > 0) {
                    UI.dbEntry[4] = data['items'].length;
                    for (i = 0; i < data['items'].length; i++) {
                        output += '<li id="view-pl-item-' + (i + 1) + '" class="pl-item">';
                        output += '<span class="pl-item-line1">' + data['items'][i]['name'] + '</span>';
                        output += '<span class="pl-item-line2">' + data['items'][i]['line2'] + '</span>';
            			output += '</li>';
                    }
                } else {
                    output = 'Playlist is empty';
                }

                element.innerHTML = output;

        		$('#view-playlist-modal').modal();
            }, 'json');
        } else if (SESSION.json['library_onetouch_pl'] == 'Add' || SESSION.json['library_onetouch_pl'] == 'Add next') {
            var queueCmd = SESSION.json['library_onetouch_pl'] == 'Add' ? 'add_item' : 'add_item_next';
            sendQueueCmd(queueCmd, path);
            notify(NOTIFY_TITLE_INFO, queueCmd, NOTIFY_DURATION_SHORT);
        } else if (SESSION.json['library_onetouch_pl'] == 'Play' || SESSION.json['library_onetouch_pl'] == 'Play next') {
            var queueCmd = SESSION.json['library_onetouch_pl'] == 'Play' ? 'play_item' : 'play_item_next';
            sendQueueCmd(queueCmd, path);
        } else if (SESSION.json['library_onetouch_pl'] == 'Clear/Play') {
            sendQueueCmd('clear_play_item', path);
        }
    }

	setTimeout(function() {
        customScroll('playlist', UI.playlistPos + 1, 200);
	}, DEFAULT_TIMEOUT);
});

// Playlist manager modal
$('#btn-pl-manager').click(function(e) {
    var sortGroup = SESSION.json['plview_sort_group'].split(',');
    $('#plview-sort-tag span').text(sortGroup[0]);
    $('#plview-group-method span').text(sortGroup[1]);

    $('#playlist-manager-modal').modal();
});
// Update Playlist manager
$('#btn-upd-playlist-manager').click(function(e) {
    SESSION.json['plview_sort_group'] = $('#plview-sort-tag span').text() + ',' + $('#plview-group-method span').text();

    $.post('command/cfg-table.php?cmd=upd_cfg_system', {
        'plview_sort_group': SESSION.json['plview_sort_group']
        }, function() {
            notify(NOTIFY_TITLE_INFO, 'settings_updated', NOTIFY_DURATION_SHORT);
            setTimeout(function() {
                $('#btn-pl-refresh').click();
            }, DEFAULT_TIMEOUT);
        }
    );
});

// Click lib coverart
$('#lib-coverart-img').click(function(e) {
	UI.dbEntry[0] =  $.isNumeric(UI.dbEntry[0]) ? UI.dbEntry[0] : 0;
    $('#songsList li, #songsList .lib-disc a').removeClass('active');
	$('img.lib-coverart').addClass('active'); // add highlight
	$('#tracklist-toggle').hide();
    $('#one-touch-action-li').show();
});

// Click Disc
$('#songsList').on('click', '.lib-disc', function(e) {
	$('img.lib-coverart, #songsList li, #songsList .lib-disc a').removeClass('active'); // Remove highlight
	var discNum = $(this).text().split(' '); // DISC 1
	$('#lib-disc-' + discNum[1] + ' a').addClass('active');

    var headingAlbum = $(this).attr('heading-album');
    var headingDisc = $(this).attr('heading-disc');
    var headingComment = $(this).attr('heading-comment');
    var headingKey = $(this).attr('heading-key');

	filteredSongsDisc.length = 0;
	for (var i in filteredSongs) {
        var matchAlbumDisc = filteredSongs[i].album == headingAlbum && filteredSongs[i].disc == headingDisc;
        if (miscLibOptions[0] == 'Yes') { // Comment tag included
            matchAlbumDisc &= headingComment == '' || filteredSongs[i].comment == headingComment;
        }
        else if (miscLibOptions[1] == 'Yes') { // MBRZ albumid tag included
            matchAlbumDisc &= headingKey == '0' || filteredSongs[i].mb_albumid == headingKey;
        }
		if (matchAlbumDisc) {
			filteredSongsDisc.push(filteredSongs[i]);
		}
	}
	//console.log('filteredSongsDisc= ' + JSON.stringify(filteredSongsDisc));
});

// Click Album heading
$('#songsList').on('click', '.lib-album-heading', function(e) {
	$('img.lib-coverart, #songsList li, #songsList .lib-disc a').removeClass('active'); // Remove highlight
    var albumNum = $(this).attr('id').split('-'); // lib-album-1
	$('#lib-album-' + albumNum[2] + ' a').addClass('active');

    var headingAlbum = encodeHTMLEntities($(this).attr('heading-album'));
    var headingComment = $(this).attr('heading-comment');
    var headingKey = $(this).attr('heading-mb-album-id');

	filteredSongsAlbum.length = 0;
	for (var i in filteredSongs) {
        var matchAlbum = filteredSongs[i].album == headingAlbum;
        if (miscLibOptions[0] == 'Yes') { // Comment tag included
            matchAlbum &= headingComment == '' || filteredSongs[i].comment == headingComment;
        }
        else if (miscLibOptions[1] == 'Yes') { // MBRZ albumid tag included
            matchAlbum &= headingKey == '0' || filteredSongs[i].mb_albumid == headingKey;
        }
        if (matchAlbum) {
            filteredSongsAlbum.push(filteredSongs[i]);
		}
	}
	//console.log('filteredSongsAlbum= ' + JSON.stringify(filteredSongsAlbum));
});

// Click lib track
$('#songsList').on('click', '.lib-track', function(e) {
    UI.dbEntry[0] = $('#songsList .lib-track').index(this); // Store pos for use in action menu item click
	$('#songsList li, #songsList .lib-disc a').removeClass('active');
    $(this).addClass('active');

    if (SESSION.json['library_track_play'] == 'Track') {
        $('#play-track').html(' <i class="fa-regular fa-sharp fa-play sx"></i> Play');
        $('#clear-play-track').html(' <i class="fa-regular fa-sharp fa-chevron-square-right sx"></i> Clear/Play');
    }
    else {
        $('#play-track').html(' <i class="fa-regular fa-sharp fa-play sx"></i> Play+');
        $('#clear-play-track').html(' <i class="fa-regular fa-sharp fa-chevron-square-right sx"></i> Clear/Play+');
    }
});

// Click playlist item
$('#playlist-items').on('click', '.pl-item', function(e) {
    UI.dbEntry[0] = $('#playlist-items .pl-item').index(this); // Store pos for use in action menu item click
	$('#playlist-items li').removeClass('active');
    $('#pl-item-' + (UI.dbEntry[0] + 1).toString()).addClass('active');
    //console.log(UI.dbEntry[0]);
});
// Click playlist name
$('#playlist-names').on('click', '.pl-name', function(e) {
    UI.dbEntry[0] = $('#playlist-names .pl-name').index(this); // Store pos for use in the Add routine
	$('#playlist-names li').removeClass('active');
    $('#pl-name-' + (UI.dbEntry[0] + 1).toString()).addClass('active');
    //console.log(UI.dbEntry[0]);
});
// New playlist input
$('#addto-playlist-name-new').click(function(e){
    $('#pl-name-' + (UI.dbEntry[0] + 1).toString()).removeClass('active');
});
// Add to playlist
$('#btn-add-to-playlist').click(function(e){
    var newPlaylist = $('#addto-playlist-name-new').val().trim();
    if (newPlaylist == '') {
        var playlist = '';
        $('#playlist-names li').each(function(){
            if ($(this).hasClass('active')) {
                playlist = $(this).text();
                return;
            }
        });
    } else {
        var playlist = newPlaylist;
    }

    if (playlist == '') {
        notify(NOTIFY_TITLE_ALERT, 'select_playlist');
    } else {
        var path = {'playlist': playlist, 'items': UI.dbEntry[4]};
        notify(NOTIFY_TITLE_INFO, 'updating_playlist', NOTIFY_DURATION_SHORT);
        $.post('command/playlist.php?cmd=add_to_playlist', {'path': path}, function() {
            notify(NOTIFY_TITLE_INFO, 'add_to_playlist', NOTIFY_DURATION_SHORT);
            $('#btn-pl-refresh').click();
        }, 'json');
        $('#playlist-names li').removeClass('active');
        UI.dbEntry[4] = '';
    }
});

// Playback ellipsis context menu
$('#context-menu-playback a').click(function(e) {
    //console.log($(this).data('cmd'));
    switch ($(this).data('cmd')) {
        case 'save_queue_to_playlist':
            $('#save-queue-to-playlist-modal').modal();
            break;
        case 'set_favorites_name':
            $.getJSON('command/playlist.php?cmd=get_favorites_name', function(name) {
                $('#playlist-favorites-name').val(name); // Preload existing name (if any)
                $('#set-favorites-playlist-modal').modal();
            });
            break;
        case 'toggle_song':
            sendMpdCmd('playid ' + toggleSongId);
            break;
        case 'consume':
            $('#menu-check-consume').toggle();
            var toggle = $('#menu-check-consume').is(":visible") ? '1' : '0';
            sendMpdCmd('consume ' + toggle);
            break;
        case 'repeat':
            $('#menu-check-repeat').toggle();
            var toggle = $('#menu-check-repeat').is(":visible") ? '1' : '0';
    		sendMpdCmd('repeat ' + toggle);
            break;
        case 'single':
            $('#menu-check-single').toggle();
            var toggle = $('#menu-check-single').is(":visible") ? '1' : '0';
    		sendMpdCmd('single ' + toggle);
            break;
        case 'clear':
            $.post('command/queue.php?cmd=clear_playqueue', function() {
                $('#playlist-save-name').val('');
                notify(NOTIFY_TITLE_INFO, 'queue_cleared', NOTIFY_DURATION_SHORT);
            });
            break;
        case 'stream_recorder':
    		$('#menu-check-recorder').toggle();
            if ($('#menu-check-recorder').css('display') == 'block') {
                SESSION.json['recorder_status'] = 'On';
                $('.playback-context-menu i').addClass('recorder-on');
            }
            else {
                SESSION.json['recorder_status'] = 'Off';
                $('.playback-context-menu i').removeClass('recorder-on');
            }
            $.post('command/cfg-table.php?cmd=upd_cfg_system', {'recorder_status': SESSION.json['recorder_status']}, function() {
                $.post('command/recorder-cmd.php?cmd=recorder_on_off');
            });
            break;
	}
});

// Click tracks context menu item
$('#context-menu-lib-item a').click(function(e) {
	$('#lib-song-' + (UI.dbEntry[0] + 1).toString()).removeClass('active');
	$('img.lib-coverart').removeClass('active');

    switch ($(this).data('cmd')) {
        case 'add_item':
        case 'add_item_next':
    		sendQueueCmd($(this).data('cmd'), filteredSongs[UI.dbEntry[0]].file);
    		notify(NOTIFY_TITLE_INFO, 'add_item', NOTIFY_DURATION_SHORT);
            break;
        case 'play_item':
        case 'play_item_next':
            if (SESSION.json['library_track_play'] == 'Track') {
                // Track: Play only the selected track
                var cmd = $(this).data('cmd');
                var files = filteredSongs[UI.dbEntry[0]].file;
            }
            else {
                // Track+: Play selected track and all following tracks in the album
                var cmd = $(this).data('cmd') == 'play_item' ? 'play_group' : 'play_group_next';
                var files = [];
                for (i = UI.dbEntry[0]; i < filteredSongs.length; i++) {
                    files.push(filteredSongs[i].file);
                }
            }
            sendQueueCmd(cmd, files);
            break;
        /*case 'clear_add_item':
    		sendQueueCmd('clear_add_item', filteredSongs[UI.dbEntry[0]].file);
    		notify(NOTIFY_TITLE_INFO, 'clear_add_item', NOTIFY_DURATION_SHORT);
    		$('#playlist-save-name').val(''); // Clear saved playlist name if any
            break;
        }*/
        case 'clear_play_item':
            if (SESSION.json['library_track_play'] == 'Track') {
                // Track: Play only the selected track
                var cmd = $(this).data('cmd');
                var files = filteredSongs[UI.dbEntry[0]].file;
            }
            else {
                // Track+: Play selected track and all following tracks in the album
                var cmd = 'clear_play_group';
                var files = [];
                for (i = UI.dbEntry[0]; i < filteredSongs.length; i++) {
                    files.push(filteredSongs[i].file);
                }
            }
    		sendQueueCmd(cmd, files);
    		$('#playlist-save-name').val(''); // Clear saved playlist name if any
            break;
        case 'track_info_lib':
            audioInfo('track_info', filteredSongs[UI.dbEntry[0]].file);
            break;
        case 'get_playlist_names':
            renderPlaylistNames({'name': filteredSongs[UI.dbEntry[0]].title, 'files':[filteredSongs[UI.dbEntry[0]].file]});
            $('#addto-playlist-name-new').val('');
            $('#add-to-playlist-modal').modal();
            break;
	}
});

// Click coverart context menu item
$('#context-menu-lib-album a').click(function(e) {
	UI.dbEntry[0] = $.isNumeric(UI.dbEntry[0]) ? UI.dbEntry[0] : 0;

	if (!$('.album-view-button').hasClass('active')) {
		$('#lib-song-' + (UI.dbEntry[0] + 1).toString()).removeClass('active');
		$('img.lib-coverart').removeClass('active');
	}

    // Order the files according the the order of the albums
    var files = [];
    for (i = 0; i < filteredAlbums.length; i++) {
        for (j = 0; j < filteredSongs.length; j++) {
            if (filteredSongs[j].key == filteredAlbums[i].key) {
                files.push(filteredSongs[j].file);
            }
        }
    }
	//console.log('files= ' + JSON.stringify(files));

    switch ($(this).data('cmd')) {
        case 'add_group':
        case 'add_group_next':
    		sendQueueCmd($(this).data('cmd'), files);
    		notify(NOTIFY_TITLE_INFO, $(this).data('cmd'), NOTIFY_DURATION_SHORT);
            break;
        case 'play_group':
        case 'play_group_next':
		      sendQueueCmd($(this).data('cmd'), files);
              break;
        /*case 'clear_add_group':
        	sendQueueCmd('clear_add_group', files);
        	notify(NOTIFY_TITLE_INFO, $(this).data('cmd'), NOTIFY_DURATION_SHORT);
            break;
        }*/
        case 'clear_play_group':
    		sendQueueCmd('clear_play_group', files);
            break;
        case 'tracklist':
            showHideTracks(false);
            break;
        case 'get_playlist_names':
            var name = $('#tagview-text-cover').text() == '' ? filteredSongs[0].album : $('#tagview-text-cover').text();
            renderPlaylistNames({'name': name, 'files': files});
            $('#addto-playlist-name-new').val('');
            $('#add-to-playlist-modal').modal();
            break;
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

    switch ($(this).data('cmd')) {
        case 'add_group':
    		sendQueueCmd('add_group', files);
            notify(NOTIFY_TITLE_INFO, $(this).data('cmd'), NOTIFY_DURATION_SHORT);
            break;
        case 'play_group':
            sendQueueCmd('play_group', files);
            break;
        case 'clear_play_group':
    		sendQueueCmd('clear_play_group', files);
            break;
        case 'get_playlist_names':
            renderPlaylistNames({'name': filteredSongsDisc[0].album, 'files': files});
            $('#addto-playlist-name-new').val('');
            $('#add-to-playlist-modal').modal();
            break;
	}
});

// Click Album heading context menu item
$('#context-menu-lib-album-heading a').click(function(e) {
    $('#songsList .lib-album-heading a').removeClass('active');

	var files = [];
	for (var i in filteredSongsAlbum) {
		files.push(filteredSongsAlbum[i].file);
	}
	//console.log('files= ' + JSON.stringify(files));

    switch ($(this).data('cmd')) {
        case 'add_group':
    		sendQueueCmd('add_group', files);
            notify(NOTIFY_TITLE_INFO, $(this).data('cmd'), NOTIFY_DURATION_SHORT);
            break;
        case 'play_group':
            sendQueueCmd('play_group', files);
            break;
        case 'clear_play_group':
    		sendQueueCmd('clear_play_group', files);
            break;
        case 'get_playlist_names':
            renderPlaylistNames({'name': filteredSongsAlbum[0].album, 'files': files});
            $('#addto-playlist-name-new').val('');
            $('#add-to-playlist-modal').modal();
            break;
	}
});

// Format total tracks time
function formatLibTotalTime(totalSecs) {
	var output, hours, minutes, hhStr, mmStr;

    if(isNaN(parseInt(totalSecs))) {
    	output = '';
    } else {
        // Parse hours and minutes
	    hours = ~~(totalSecs / 3600); // ~~ is faster than Math.floor
    	totalSecs %= 3600; // Use remainder to calculate minutes
    	minutes = ~~(totalSecs / 60);

        // Format output string
        hhStr = hours == 0 ? '' : (hours == 1 ? hours + ' hour' : hours + ' hours');
        mmStr = minutes == 0 ? '' : (minutes == 1 ? minutes + ' min' : minutes + ' mins');
		if (hours > 0) {
            output = minutes > 0 ? hhStr + ' ' + mmStr : hhStr;
		} else {
			output = mmStr;
		}
    }

    return formatNumCommas(output);
}

function formatNumCommas(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function showHideTracks(posChange) {
    // Always show
    if (posChange === true) {
        $('#tracklist-toggle').html('<i class="fa-regular fa-sharp fa-list sx"></i> Hide tracks');
        $('#bottom-row').css('display', 'flex')
        $('#lib-albumcover').css('height', 'calc(50% - env(safe-area-inset-top) - 2.75rem)'); // Was 1.75em
        $('#index-albumcovers').attr('style', 'display:none!important');
        customScroll('tracks', 0, 200);
    }
    // Toggle
    else if ($('#bottom-row').css('display') == 'none') {
        $('#tracklist-toggle').html('<i class="fa-regular fa-sharp fa-list sx"></i> Hide tracks');
        $('#bottom-row').css('display', 'flex')
        $('#lib-albumcover').css('height', 'calc(50% - env(safe-area-inset-top) - 2.75rem)'); // Was 1.75em
        $('#index-albumcovers').attr('style', 'display:none!important');
        customScroll('tracks', 0, 200);
    }
    else {
        $('#tracklist-toggle').html('<i class="fa-regular fa-sharp fa-list sx"></i> Show tracks');
        $('#bottom-row').css('display', '')
        $('#lib-albumcover').css('height', '100%');
        $('#index-albumcovers').show();
    }

    customScroll('albumcovers', UI.libPos[1], 200);
}

function encodeHTMLEntities (str) {
    return String(str).replace(/[&<>"'`=\/]/g, function (s) {
        return htmlEntityMap[s];
    });
}
