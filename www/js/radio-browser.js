/*!
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2026 The moOde audio player project / Tim Curtis
 * Copyright 2026 RadioBrowser extension   / @rubatron
 *	https://github.com/rubatron/RadioBrowser/tree/main
 * Copyright 2026 RadioBrowser integration / @Gjuju
 *	https://github.com/moode-player/moode/commit/910bee751a1f65fa80b1cd44383bc9450cacba19
 *
 * Radio Browser view.
 * Derived from @rubatron's RadioBrowser extension for moOde and re-implemented
 * using moOde's native front-end style and reusing the Radio view markup and CSS.
 */

var RB = {
    tab: 'search',      // search | recent
    offset: 0,          // Search pagination offset
    limit: 28,          // Page size (fixed)
    listsLoaded: {recent: false},
    countriesLoaded: false,
    menuUrl: ''         // URL of the tile whose context menu is open (Remove-from-recent target)
};

var RB_API = 'command/radio-browser.php';

// Build a station object from a tile's data-* attributes
function rbStationFromTile(li) {
    var $li = $(li);
    return {
        name: $li.data('name') || '',
        url: $li.data('url') || '',
        favicon: $li.data('favicon') || '',
        homepage: $li.data('homepage') || '',
        country: $li.data('country') || '',
        tags: $li.data('tags') || '',
        bitrate: parseInt($li.data('bitrate')) || 0,
        codec: $li.data('codec') || '',
        stationuuid: $li.data('uuid') || ''
    };
}

// Logo URL from the favicon (external ones proxied same-origin, cached on demand)
function rbLogoUrl(s) {
    if (s.favicon) {
        if (/^https?:\/\//i.test(s.favicon)) {
            return RB_API + '?cmd=logo&url=' + encodeURIComponent(s.favicon);
        }
        return s.favicon;
    }
    return DEFAULT_RADIO_COVER;
}

// Build a single station tile (mirrors renderRadioView() markup so Radio CSS applies)
function rbBuildTile(s, i) {
    var logo = rbLogoUrl(s);
    var meta = [];
    if (s.country) meta.push(rbEscapeHtml(s.country));
    if (s.codec) meta.push(rbEscapeHtml(s.codec));
    if (s.bitrate) meta.push(s.bitrate + ' kbps');
    var favClass = s.added ? 'rb-fav-toggle added' : 'rb-fav-toggle';
    var favIcon = s.added ? 'fa-solid' : 'fa-regular';
    var hires = (s.bitrate && s.bitrate >= 320) ? '<div class="lib-encoded-at-hires-badge">' + RADIO_HIRES_BADGE_TEXT + '</div>' : '';
    var favToggle = '<div class="' + favClass + '"><i class="' + favIcon + ' fa-sharp fa-heart"></i></div>';
    var coverMenu = '<div class="cover-menu" data-toggle="context" data-target="#context-menu-radio-browser-item"></div>';

    return '<li id="rb-' + i + '"' +
            ' data-path="' + rbEscapeHtml(s.url) + '"' +
            ' data-url="' + rbEscapeHtml(s.url) + '"' +
            ' data-name="' + rbEscapeHtml(s.name) + '"' +
            ' data-favicon="' + rbEscapeHtml(s.favicon || '') + '"' +
            ' data-homepage="' + rbEscapeHtml(s.homepage || '') + '"' +
            ' data-country="' + rbEscapeHtml(s.country || '') + '"' +
            ' data-tags="' + rbEscapeHtml(s.tags || '') + '"' +
            ' data-bitrate="' + (s.bitrate || 0) + '"' +
            ' data-codec="' + rbEscapeHtml(s.codec || '') + '"' +
            ' data-uuid="' + rbEscapeHtml(s.stationuuid || '') + '">' +
        '<div class="db-icon db-song db-browse db-action">' +
            '<div class="thumbHW">' +
                '<img loading="lazy" src="' + logo.replace(/&/g, '&amp;') + '" onerror="this.src=\'' + DEFAULT_RADIO_COVER + '\'">' +
                favToggle +
            '</div>' +
        '</div>' +
        coverMenu +
        hires +
        '<span class="station-name">' + rbEscapeHtml(s.name) + '</span>' +
        (meta.length ? '<div class="radioview-metadata-text">' + meta.join(' &middot; ') + '</div>' : '') +
    '</li>';
}

function rbRenderTiles(stations, ulId) {
    RB.seen = {};          // normalized url set (dedup across appended pages)
    RB.tileIndex = 0;      // running <li> id counter
    if (!stations || stations.length === 0) {
        $('#' + ulId).html('<li class="rb-empty">No stations</li>');
        return;
    }
    document.getElementById(ulId).innerHTML = '';
    rbAppendTiles(stations, ulId);
}

// Append a batch, skipping stations already shown (client-side cross-page dedup)
function rbAppendTiles(stations, ulId) {
    var sm = document.getElementById('rb-showmore');
    if (sm) { sm.remove(); }           // keep "Show more" as the last <li>
    var html = '';
    for (var i = 0; i < stations.length; i++) {
        var key = (stations[i].url || '').trim().toLowerCase();
        if (key && RB.seen[key]) { continue; }
        if (key) { RB.seen[key] = true; }
        html += rbBuildTile(stations[i], RB.tileIndex++);
    }
    document.getElementById(ulId).insertAdjacentHTML('beforeend', html);
}

// Inject/remove the "Show more" button as the last <li> of the search grid
function rbSetShowMore(show) {
    var old = document.getElementById('rb-showmore');
    if (old) { old.remove(); }
    if (show) {
        document.getElementById('rb-covers-search').insertAdjacentHTML('beforeend',
            '<li id="rb-showmore"><button id="btn-rb-showmore" class="btn">Show more</button></li>');
    }
}

function rbEscapeHtml(text) {
    return $('<div>').text(text == null ? '' : text).html().replace(/"/g, '&quot;');
}

function rbLoading(ulId) {
    $('#' + ulId).html('<li class="rb-empty"><i class="fa-solid fa-sharp fa-spinner fa-spin"></i>&nbsp;Loading&hellip;</li>');
}

// --- Search ---------------------------------------------------------------

function rbSearch(offset, append) {
    RB.offset = offset || 0;
	var params = {
        name: $('#rb-filter').val().trim(),
		countrycode: $('#rb-country span.data-value').text(),
        tag: $('#rb-genre span.data-value').text(),
        offset: RB.offset,
        limit: RB.limit
    };

    if (!append) { rbLoading('rb-covers-search'); }
    // No filters = top stations by clickcount (search paginates via offset)
    $.getJSON(RB_API + '?cmd=search', params, function(data) {
        if (data && data.success) {
            if (append) { rbAppendTiles(data.stations, 'rb-covers-search'); }
            else { rbRenderTiles(data.stations, 'rb-covers-search'); }
            // API returns no total: show "more" while the raw batch was full
            var batch = (typeof data.batch === 'number') ? data.batch : data.stations.length;
            rbSetShowMore(batch >= RB.limit);
        } else {
            if (!append) { $('#rb-covers-search').html('<li class="rb-empty">No results</li>'); }
            rbSetShowMore(false);
        }
    }).fail(function() {
        if (!append) { $('#rb-covers-search').html('<li class="rb-empty">radio-browser.info unavailable</li>'); }
        rbSetShowMore(false);
    });
}

// --- Recently played ------------------------------------------------------

function rbLoadRecent() {
    rbLoading('rb-covers-recent');
    $.getJSON(RB_API + '?cmd=recently_played', function(data) {
        rbRenderTiles(data.success ? data.stations : [], 'rb-covers-recent');
        RB.listsLoaded.recent = true;
    });
}

// A play just updated the recent list server-side (cmd=play → rbAddRecent). Reload it now if
// the Recent tab is showing, else force a reload the next time it's opened.
function rbMarkRecentStale() {
    RB.listsLoaded.recent = false;
    if (RB.tab === 'recent') { rbLoadRecent(); }
}

// Native-style client-side filter over the loaded Recent tiles (mirrors the #ra-filter handler)
function rbFilterRecent(filter) {
    filter = (filter || '').trim();
    $('#rb-covers-recent li').each(function() {
        $(this).toggle($(this).text().search(new RegExp(filter, 'i')) >= 0);
    });
}

// --- Actions --------------------------------------------------------------

// Pre-register the stream in RADIO.json so the native now-playing renderer resolves it
function rbRegisterInRadioJson(station) {
    if (typeof RADIO === 'object' && RADIO.json && station.url && !RADIO.json[station.url]) {
        RADIO.json[station.url] = {
            name: station.name, type: 'rb', logo: 'local',
            bitrate: String(station.bitrate || ''), format: station.codec || '',
            home_page: station.homepage || '', monitor: 'No'
        };
    }
}

function rbPlay(li) {
    var station = rbStationFromTile(li);
    if (!station.url) return;
    $('#container-radio-browser .database-radio li').removeClass('active');
    $(li).addClass('active');
    rbRegisterInRadioJson(station);

    $.ajax({
        url: RB_API + '?cmd=play',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(station),
        dataType: 'json',
        success: function(data) {
            notify(data && data.success ? NOTIFY_TITLE_INFO : NOTIFY_TITLE_ALERT,
                'mpd_error', data ? data.message : 'Play failed', NOTIFY_DURATION_SHORT);
            rbMarkRecentStale(); // the play was recorded server-side; refresh the Recent tab
        }
    });
}

function rbToggleFavorite(li) {
    var $li = $(li);
    var station = rbStationFromTile(li);
    if (!station.url) return;
    var isAdded = $li.find('.rb-fav-toggle').hasClass('added');
    var cmd = isAdded ? 'remove' : 'add';

	if (cmd == 'add') {
		// Because the add is via submitJob() to worker.php daemon which gets processed up in its polling loop
		notify(NOTIFY_TITLE_INFO, 'rb_message', 'Adding station to Favorites... ', NOTIFY_DURATION_INFINITE);
	}

    $.ajax({
        url: RB_API + '?cmd=' + cmd,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(station),
        dataType: 'json',
        success: function(data) {
            if (data && data.success) {
                rbSetFavoriteState(station.url, !isAdded);
                RB.favoritesDirty = true; // refresh the native Radio grid when we return to it
            }
            notify(data && data.success ? NOTIFY_TITLE_INFO : NOTIFY_TITLE_ALERT,
                'mpd_error', data ? data.message : 'Action failed',
				NOTIFY_DURATION_SHORT);
            // 'update RADIO' lights the busy-spinner; clear it after it settles (native pattern)
            setTimeout(function() { $('.busy-spinner').hide(); }, ONE_SEC_TIMEOUT);
        }
    });
}

// Sync the heart state of every tile that shares this stream URL
function rbSetFavoriteState(url, added) {
    $('#container-radio-browser .database-radio li').each(function() {
        if ($(this).data('url') === url) {
            var $t = $(this).find('.rb-fav-toggle');
            $t.toggleClass('added', added);
            $t.find('i').toggleClass('fa-solid', added).toggleClass('fa-regular', !added);
        }
    });
}

// --- Tabs / view activation ----------------------------------------------

function rbShowTab(tab) {
    RB.tab = tab;
    $('.rb-tab').removeClass('active');
    $('#btn-rb-tab-' + tab).addClass('active');
    $('.rb-tab-pane').addClass('hide');
    $('#rb-tab-' + tab).removeClass('hide');
    // Country/genre are radio-browser.info API params — no meaning on the client-filtered
    // Recent tab. The search box stays, but switches to a native-style live filter (below).
    $('#rb-filters').toggleClass('hide', tab === 'recent');
    $('#rb-filter').attr('placeholder', tab === 'recent' ? 'search' : 'search radio-browser.info');
    // The search box is shared by both tabs and means different things per tab — reset it and
    // clear any leftover Recent filter on every switch so each tab starts clean.
    $('#rb-filter').val('');
    $('#btn-rb-search-reset').addClass('hide');
    $('#rb-covers-recent li').show();

    if (tab === 'search' && $('#rb-covers-search li').length === 0) {
        rbSearch(0);
    } else if (tab === 'recent' && !RB.listsLoaded.recent) {
        rbLoadRecent();
    }
}

// Called from makeActive() when the Radio Browser view becomes active
function rbOnViewActive() {
    if (!RB.countriesLoaded) {
        rbLoadCountriesAndGenres();
    }
    rbShowTab(RB.tab);
}
function rbLoadCountriesAndGenres() {
    RB.countriesLoaded = true;
    $.getJSON(RB_API + '?cmd=countries', function(data) {
        if (data && data.success) {
			var lines = '<li><a href="#notarget" data-cmd="rb-country-sel" data-value=""><span class="text">All Countries</span></a></li>';
            data.countries.forEach(function(item) {
                if (item.iso_3166_1 && item.name) {
					lines += '<li><a href="#notarget" data-cmd="rb-country-sel" data-value="'
						+ rbEscapeHtml(item.iso_3166_1)
						+ '"><span class="text">'
						+ rbEscapeHtml(item.name)
						+ '</span></a></li>';
                }
            });
			$('#rb-country-list').html(lines);
        }
    });
    $.getJSON(RB_API + '?cmd=genres', function(data) {
        if (data && data.success) {
			var lines = '<li><a href="#notarget" data-cmd="rb-genre-sel" data-value=""><span class="text">All Genres</span></a></li>';
            data.genres.forEach(function(item) {
                if (item.name && item.genre) {
					lines += '<li><a href="#notarget" data-cmd="rb-genre-sel" data-value="'
						+ item.genre
						+ '"><span class="text">'
						+ item.name
						+ '</span></a></li>';
                }
            });
            $('#rb-genre-list').html(lines);
        }
    });
}

// --- Event bindings -------------------------------------------------------

$(document).ready(function() {
    $('#btn-rb-tab-search').click(function() { rbShowTab('search'); });
    $('#btn-rb-tab-recent').click(function() { rbShowTab('recent'); });

    $('#btn-rb-refresh').click(function() {
        if (RB.tab === 'search') { rbSearch(0); }
        else { rbLoadRecent(); }
    });

    $('#rb-filter').on('keyup', function(e) {
        $('#btn-rb-search-reset').toggleClass('hide', $(this).val() === '');
        if (RB.tab === 'recent') {
            // Native-style client-side filter of the already-loaded Recent tiles (debounced)
            clearTimeout(searchTimer);
            var val = $(this).val();
            searchTimer = setTimeout(function() { rbFilterRecent(val); }, SEARCH_TIMEOUT);
        } else if (e.which === 13) {
            rbSearch(0);
        }
    });

    $('#btn-rb-search-reset').click(function() {
        $('#rb-filter').val('');
        $(this).addClass('hide');
        if (RB.tab === 'recent') { rbFilterRecent(''); }
        else { rbSearch(0); }
    });

	var target = document.querySelector('#rb-country span');
	var observer = new MutationObserver(mutate);
	var config = {characterData: true, attributes: false, childList: true, subtree: false};
	observer.observe(target, config);
	function mutate(mutations) {
		rbSearch(0);
	}
	var target = document.querySelector('#rb-genre span');
	var observer = new MutationObserver(mutate);
	var config = {characterData: true, attributes: false, childList: true, subtree: false};
	observer.observe(target, config);
	function mutate(mutations) {
		rbSearch(0);
	}

    $('#rb-covers-search').on('click', '#btn-rb-showmore', function() {
		rbSearch(RB.offset + RB.limit, true);
	});

    // Tile interactions (event delegation across the search/recent tabs)
    $('#container-radio-browser').on('click', '.database-radio img', function() {
        rbPlay($(this).closest('li'));
    });
    $('#container-radio-browser').on('click', '.rb-fav-toggle', function(e) {
        e.stopPropagation();
        rbToggleFavorite($(this).closest('li'));
    });
    // Register the station for now-playing; the native .cover-menu handler queues data-path
    $('#container-radio-browser').on('click', '.cover-menu', function() {
        // 'Remove from recent' only makes sense on the Recent tab
        $('#rb-ctx-remove-recent').toggleClass('hide', RB.tab !== 'recent');
        var station = rbStationFromTile($(this).closest('li'));
        if (!station.url) return;
        RB.menuUrl = station.url; // target for the Remove-from-recent action
        rbRegisterInRadioJson(station);
        $.ajax({ url: RB_API + '?cmd=register', type: 'POST',
                 contentType: 'application/json', data: JSON.stringify(station) });
    });

    $('#context-menu-radio-browser-item a[data-cmd="rb_remove_recent"]').click(function() {
        if (!RB.menuUrl) return;
        $.ajax({
            url: RB_API + '?cmd=remove_recent',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({url: RB.menuUrl}),
            dataType: 'json',
            success: function(data) {
                if (data && data.success) { rbLoadRecent(); }
                notify(data && data.success ? NOTIFY_TITLE_INFO : NOTIFY_TITLE_ALERT,
                    'mpd_error', data ? data.message : 'Action failed', NOTIFY_DURATION_SHORT);
            }
        });
    });
});
