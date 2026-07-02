<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/radio.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

phpSession('open');

if (isset($_POST['save']) && $_POST['save'] == '1') {
	// Form variable to config param mapping table
	$mappingTable = array(
		'search_provider_itunes' => 'iTunes',
		'search_provider_deezer' => 'Deezer',
		'search_provider_musicbrainz' => 'MusicBrainz',
		'search_provider_spotify' => 'Spotify',
		'spotify_client_id' => 'SPOTIFY_CLIENT_ID',
		'spotify_client_secret' => 'SPOTIFY_CLIENT_SECRET',
		'search_provider_lastfm' => 'LastFM',
		'lastfm_api_key' => 'LASTFM_API_KEY',
		'search_provider_discogs' => 'Discogs',
		'discogs_token' => 'DISCOGS_TOKEN',
		'search_provider_theaudiodb' => 'TheAudioDB',
		'theaudiodb_api_key' => 'THEAUDIODB_API_KEY',
		'search_request_timeout' => 'REQUEST_TIMEOUT',
		'search_min_similarity' => 'MIN_SIMILARITY',
		'search_min_similarity_itunes' => 'MIN_SIMILARITY_ITUNES',
		'search_fast_deadline' => 'FAST_DEADLINE_S',
		'search_total_deadline' => 'TOTAL_DEADLINE_S',
		'search_early_stop_score' => 'EARLY_STOP_SCORE',
		'search_cover_max_size' => 'MAX_SIZE_PX',
		'search_cover_quality' => 'COVER_QUALITY',
		'log_level' => 'LOG_LEVEL',
		'sse_debounce_ms' => 'DEBOUNCE_MS',
		'sse_cache_enabled' => 'CACHE_ENABLED',
		'sse_last_event_send_delay' => 'LAST_EVENT_SEND_DELAY',
		'sse_health_check_interval' => 'HEALTH_CHECK_INTERVAL',
		'sse_segment_cover_weather' => 'SEGMENT_COVER_METEO',
		'sse_segment_cover_traffic' => 'SEGMENT_COVER_TRAFFIC',
		'sse_segment_cover_news' => 'SEGMENT_COVER_NEWS',
		'sse_segment_cover_advert' => 'SEGMENT_COVER_ADVERTISING'
	);

	// Update settings
	foreach ($_POST['config'] as $key => $value) {
		chkValue($key, $value);
		$param = $mappingTable[$key];
		if ($param == 'LOG_LEVEL') {
			$value = strtoupper($value);
		}
		sysCmd("sed -i 's|^" . $param . '=.*|' . $param . '=' . $value . "|' " . RADIOCOVER_PLUS_CFG);
	}
}

if (isset($_POST['update_clear_rcucache'])) {
	clearRadioCoverUrlCache();
	$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
	$_SESSION['notify']['msg'] = 'Cover URL cache has been cleared.';
}

phpSession('close');
// Load config
$config = parseDelimFile(file_get_contents(RADIOCOVER_PLUS_CFG), '=');

// Search providers (Free)
// iTunes
$_config['search_provider_itunes'] .= "<option value=\"True\" " . (($config['iTunes'] == 'True') ? "selected" : "") . ">Yes</option>\n";
$_config['search_provider_itunes'] .= "<option value=\"False\" " . (($config['iTunes'] == 'False') ? "selected" : "") . ">No</option>\n";
// Deezer
$_config['search_provider_deezer'] .= "<option value=\"True\" " . (($config['Deezer'] == 'True') ? "selected" : "") . ">Yes</option>\n";
$_config['search_provider_deezer'] .= "<option value=\"False\" " . (($config['Deezer'] == 'False') ? "selected" : "") . ">No</option>\n";
// MusicBrainz
$_config['search_provider_musicbrainz'] .= "<option value=\"True\" " . (($config['MusicBrainz'] == 'True') ? "selected" : "") . ">Yes</option>\n";
$_config['search_provider_musicbrainz'] .= "<option value=\"False\" " . (($config['MusicBrainz'] == 'False') ? "selected" : "") . ">No</option>\n";

// Search providers (Non-Free)
// Spotify
$_config['search_provider_spotify'] .= "<option value=\"True\" " . (($config['Spotify'] == 'True') ? "selected" : "") . ">Yes</option>\n";
$_config['search_provider_spotify'] .= "<option value=\"False\" " . (($config['Spotify'] == 'False') ? "selected" : "") . ">No</option>\n";
$_config['spotify_client_id'] = $config['SPOTIFY_CLIENT_ID'];
$_config['spotify_client_secret'] = $config['SPOTIFY_CLIENT_SECRET'];
// LastFM
$_config['search_provider_lastfm'] .= "<option value=\"True\" " . (($config['LastFM'] == 'True') ? "selected" : "") . ">Yes</option>\n";
$_config['search_provider_lastfm'] .= "<option value=\"False\" " . (($config['LastFM'] == 'False') ? "selected" : "") . ">No</option>\n";
$_config['lastfm_api_key'] = $config['LASTFM_API_KEY'];
// Discogs
$_config['search_provider_discogs'] .= "<option value=\"True\" " . (($config['Discogs'] == 'True') ? "selected" : "") . ">Yes</option>\n";
$_config['search_provider_discogs'] .= "<option value=\"False\" " . (($config['Discogs'] == 'False') ? "selected" : "") . ">No</option>\n";
$_config['discogs_token'] = $config['DISCOGS_TOKEN'];
// TheAudioDB
$_config['search_provider_theaudiodb'] .= "<option value=\"True\" " . (($config['TheAudioDB'] == 'True') ? "selected" : "") . ">Yes</option>\n";
$_config['search_provider_theaudiodb'] .= "<option value=\"False\" " . (($config['TheAudioDB'] == 'False') ? "selected" : "") . ">No</option>\n";
$_config['theaudiodb_api_key'] = $config['THEAUDIODB_API_KEY'];

// Search settings
$_config['search_request_timeout'] = $config['REQUEST_TIMEOUT'];
$_config['search_min_similarity'] = $config['MIN_SIMILARITY'];
$_config['search_min_similarity_itunes'] = $config['MIN_SIMILARITY_ITUNES'];
$_config['search_fast_deadline'] = $config['FAST_DEADLINE_S'];
$_config['search_total_deadline'] = $config['TOTAL_DEADLINE_S'];
$_config['search_early_stop_score'] = $config['EARLY_STOP_SCORE'];
$_config['search_cover_max_size'] = $config['MAX_SIZE_PX'];
$_config['search_cover_quality'] = $config['COVER_QUALITY'];
$_rcucache_count = sqlQuery("SELECT count() FROM cfg_rcucache",sqlConnect())[0]['count()'];

// Logging
$_config['log_level'] .= "<option value=\"Info\" " . (($config['LOG_LEVEL'] == 'INFO') ? "selected" : "") . ">Info</option>\n";
$_config['log_level'] .= "<option value=\"Warning\" " . (($config['LOG_LEVEL'] == 'WARNING') ? "selected" : "") . ">Warning</option>\n";
$_config['log_level'] .= "<option value=\"Error\" " . (($config['LOG_LEVEL'] == 'ERROR') ? "selected" : "") . ">Error</option>\n";
$_config['log_level'] .= "<option value=\"Critical\" " . (($config['LOG_LEVEL'] == 'CRITICAL') ? "selected" : "") . ">Critical</option>\n";
$_config['log_level'] .= "<option value=\"Debug\" " . (($config['LOG_LEVEL'] == 'DEBUG') ? "selected" : "") . ">Debug</option>\n";

// Daemon mode settings (SSE server)
$_config['sse_debounce_ms'] = $config['DEBOUNCE_MS'];
$_config['sse_cache_enabled'] .= "<option value=\"True\" " . (($config['CACHE_ENABLED'] == 'True') ? "selected" : "") . ">Yes</option>\n";
$_config['sse_cache_enabled'] .= "<option value=\"False\" " . (($config['CACHE_ENABLED'] == 'False') ? "selected" : "") . ">No</option>\n";
$_config['sse_last_event_send_delay'] = $config['LAST_EVENT_SEND_DELAY'];
$_config['sse_health_check_interval'] = $config['HEALTH_CHECK_INTERVAL'];
$_config['sse_segment_cover_weather'] = $config['SEGMENT_COVER_METEO'];
$_config['sse_segment_cover_traffic'] = $config['SEGMENT_COVER_TRAFFIC'];
$_config['sse_segment_cover_news'] = $config['SEGMENT_COVER_NEWS'];
$_config['sse_segment_cover_advert'] = $config['SEGMENT_COVER_ADVERTISING'];

waitWorker('rcp-config');

$tpl = "rcp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
